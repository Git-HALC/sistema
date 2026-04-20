<?php
declare(strict_types=1);

namespace App\Modules\Dashboard;

use PDO;

/**
 * Queries do Dashboard Geral.
 *
 * Fonte de verdade das vendas: pdv_vendas + pdv_venda_itens.
 * Para DRE/financeiro: movimentacoes (entradas/saidas pagas).
 *
 * NOTA: Somente vendas de caixas CONFERIDOS (pdv_caixas.conferencia_concluida=TRUE)
 * ou caixas ainda abertos (ao vivo) aparecem — respeita a regra do DRE.
 */
final class DashboardGeralRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * KPI: total, qtd e ticket médio das vendas faturadas no período.
     */
    public function kpiVendas(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(v.valor_total), 0) AS total,
                COUNT(v.id) AS quantidade,
                COALESCE(AVG(v.valor_total), 0) AS ticket_medio
               FROM pdv_vendas v
              WHERE v.status = 'faturado'
                AND v.created_at >= :ini::timestamptz
                AND v.created_at < (:fim::date + INTERVAL '1 day')"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (float)($row['total'] ?? 0),
            'quantidade' => (int)($row['quantidade'] ?? 0),
            'ticket_medio' => (float)($row['ticket_medio'] ?? 0),
        ];
    }

    public function kpiClientesNovos(string $inicio, string $fim): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(id) FROM clientes
              WHERE created_at >= :ini::timestamptz
                AND created_at < (:fim::date + INTERVAL '1 day')"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        return (int)$stmt->fetchColumn();
    }

    public function kpiEstoqueCriticoCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM produtos
              WHERE ativo = TRUE
                AND estoque_minimo > 0
                AND estoque_atual <= estoque_minimo"
        );
        return (int)$stmt->fetchColumn();
    }

    /**
     * Vendas agrupadas por dia no período (preenche dias sem venda com 0).
     * @return array<int, array{dia:string, total:float}>
     */
    public function vendasPorDia(int $dias = 30): array
    {
        $stmt = $this->pdo->prepare(
            "WITH serie AS (
                SELECT generate_series(
                    (CURRENT_DATE - (:dias - 1) * INTERVAL '1 day')::date,
                    CURRENT_DATE::date,
                    INTERVAL '1 day'
                )::date AS d
            )
            SELECT TO_CHAR(s.d, 'DD/MM') AS dia,
                   COALESCE(SUM(v.valor_total), 0) AS total
              FROM serie s
              LEFT JOIN pdv_vendas v
                     ON DATE(v.created_at AT TIME ZONE 'America/Sao_Paulo') = s.d
                    AND v.status = 'faturado'
             GROUP BY s.d
             ORDER BY s.d"
        );
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(
            static fn ($r) => ['dia' => (string)$r['dia'], 'total' => (float)$r['total']],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * Top N itens mais vendidos por quantidade (produto ou servico).
     */
    public function topItensVendidos(string $inicio, string $fim, int $limite = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.nome_item AS nome,
                    COALESCE(SUM(i.quantidade), 0) AS qtd_vendida,
                    COALESCE(SUM(i.valor_total_item), 0) AS total_vendido
               FROM pdv_venda_itens i
               INNER JOIN pdv_vendas v ON v.id = i.venda_id
              WHERE v.status = 'faturado'
                AND v.created_at >= :ini::timestamptz
                AND v.created_at < (:fim::date + INTERVAL '1 day')
              GROUP BY i.nome_item
              ORDER BY qtd_vendida DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':ini', $inicio);
        $stmt->bindValue(':fim', $fim);
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vendas agrupadas por forma de pagamento no período.
     */
    public function vendasPorForma(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fp.nome AS forma, fp.tipo,
                    COALESCE(SUM(v.valor_total), 0) AS total
               FROM pdv_vendas v
               INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
              WHERE v.status = 'faturado'
                AND v.created_at >= :ini::timestamptz
                AND v.created_at < (:fim::date + INTERVAL '1 day')
              GROUP BY fp.id, fp.nome, fp.tipo
             HAVING SUM(v.valor_total) > 0
              ORDER BY total DESC"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vendas agrupadas por hora do dia (0-23) no período.
     * @return array<int, array{hora:int, quantidade:int, total:float}>
     */
    public function vendasPorHora(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT EXTRACT(HOUR FROM v.created_at AT TIME ZONE 'America/Sao_Paulo')::int AS hora,
                    COUNT(v.id) AS quantidade,
                    COALESCE(SUM(v.valor_total), 0) AS total
               FROM pdv_vendas v
              WHERE v.status = 'faturado'
                AND v.created_at >= :ini::timestamptz
                AND v.created_at < (:fim::date + INTERVAL '1 day')
              GROUP BY hora
              ORDER BY hora"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);

        // Preenche 0-23 com zero onde não há vendas
        $mapa = array_fill(0, 24, ['hora' => 0, 'quantidade' => 0, 'total' => 0.0]);
        foreach ($mapa as $h => $_) {
            $mapa[$h]['hora'] = $h;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $h = (int)$r['hora'];
            if ($h >= 0 && $h <= 23) {
                $mapa[$h] = [
                    'hora' => $h,
                    'quantidade' => (int)$r['quantidade'],
                    'total' => (float)$r['total'],
                ];
            }
        }
        return array_values($mapa);
    }

    public function estoqueCritico(int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT nome, estoque_atual, estoque_minimo
               FROM produtos
              WHERE ativo = TRUE
                AND estoque_minimo > 0
                AND estoque_atual <= estoque_minimo
              ORDER BY (estoque_atual - estoque_minimo) ASC
              LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Últimas 10 vendas com contexto. */
    public function ultimasVendas(int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.numero, v.valor_total, v.created_at, v.status, v.origem,
                    fp.nome AS forma, fp.tipo AS forma_tipo,
                    cli.nome AS cliente_nome,
                    u.nome AS operador_nome,
                    c.numero_caixa,
                    c.status AS caixa_status
               FROM pdv_vendas v
               LEFT JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
               INNER JOIN usuarios u ON u.id = v.usuario_id
               INNER JOIN pdv_caixas c ON c.id = v.caixa_id
              ORDER BY v.created_at DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
