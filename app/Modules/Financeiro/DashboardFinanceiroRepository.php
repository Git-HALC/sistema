<?php
declare(strict_types=1);

namespace App\Modules\Financeiro;

use PDO;

/**
 * Queries do Dashboard Financeiro.
 *
 * Regras:
 *  - Receita/despesa realizada = movimentacoes com afeta_saldo=TRUE
 *  - Fluxo a receber/pagar = contas_receber/contas_pagar PENDENTE
 *  - Vendas só após conferência do caixa (via movimentacoes/contas_receber)
 */
final class DashboardFinanceiroRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function kpisRealizados(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END), 0) AS receita,
                COALESCE(SUM(CASE WHEN tipo = 'Saida'   THEN valor ELSE 0 END), 0) AS despesa
               FROM movimentacoes
              WHERE afeta_saldo = TRUE
                AND data_movimentacao >= :ini::timestamptz
                AND data_movimentacao < (:fim::date + INTERVAL '1 day')"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $receita = (float)($row['receita'] ?? 0);
        $despesa = (float)($row['despesa'] ?? 0);
        return [
            'receita' => $receita,
            'despesa' => $despesa,
            'saldo' => $receita - $despesa,
        ];
    }

    /**
     * Projeção simples: realizado até hoje + média diária × dias restantes.
     */
    public function projecaoMesAtual(): array
    {
        $hoje = new \DateTimeImmutable('today');
        $ini = $hoje->modify('first day of this month')->format('Y-m-d');
        $fim = $hoje->modify('last day of this month')->format('Y-m-d');
        $diasPassados = (int)$hoje->format('j');
        $totalDiasMes = (int)$hoje->modify('last day of this month')->format('j');

        $kpi = $this->kpisRealizados($ini, $hoje->format('Y-m-d'));
        $diasPassados = max(1, $diasPassados);
        $mediaDiaria = $kpi['receita'] / $diasPassados;
        $projecao = $mediaDiaria * $totalDiasMes;

        return [
            'realizado' => $kpi['receita'],
            'projecao' => $projecao,
            'dias_passados' => $diasPassados,
            'dias_mes' => $totalDiasMes,
        ];
    }

    /**
     * Fluxo semanal: receitas x despesas agrupadas por semana ISO.
     */
    public function fluxoSemanal(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT TO_CHAR(DATE_TRUNC('week', data_movimentacao), 'DD/MM') AS semana,
                    COALESCE(SUM(CASE WHEN tipo='Entrada' THEN valor ELSE 0 END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN tipo='Saida'   THEN valor ELSE 0 END), 0) AS despesas
               FROM movimentacoes
              WHERE afeta_saldo = TRUE
                AND data_movimentacao >= :ini::timestamptz
                AND data_movimentacao < (:fim::date + INTERVAL '1 day')
              GROUP BY DATE_TRUNC('week', data_movimentacao)
              ORDER BY DATE_TRUNC('week', data_movimentacao)"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * DRE resumido baseado em movimentacoes pagas + pdv_vendas de caixas conferidos.
     */
    public function dreResumido(string $inicio, string $fim): array
    {
        // REGIME DE COMPETENCIA (2026-04-19):
        // Receita = movimentacoes.afeta_dre=TRUE + categoria.tipo='Receita'
        // Recebimentos patrimoniais nao entram mais aqui.
        $stmt = $this->pdo->prepare(
            "SELECT
                -- Receita Bruta (competencia): todas categorias tipo Receita com codigo 01/02
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE
                    AND cd.tipo = 'Receita'
                    AND (cd.codigo IN ('01','02') OR cd.nome ILIKE '%vendas%')
                    AND m.data_movimentacao >= :i1::date
                    AND m.data_movimentacao < (:f1::date + INTERVAL '1 day')) AS receita_cr,

                -- Reservado (ja esta em receita_cr acima)
                0::numeric AS receita_pdv,

                -- CPV (Custo dos Produtos Vendidos) — categoria_dre tipo CPV
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE AND cd.tipo = 'CPV'
                    AND m.data_movimentacao >= :i3::timestamptz
                    AND m.data_movimentacao < (:f3::date + INTERVAL '1 day')) AS cpv,

                -- Despesas Operacionais
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE
                    AND cd.tipo = 'Despesa Operacional'
                    AND m.data_movimentacao >= :i4::timestamptz
                    AND m.data_movimentacao < (:f4::date + INTERVAL '1 day')) AS despesa_op,

                -- Despesas Financeiras
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE
                    AND cd.tipo = 'Despesa Financeira'
                    AND m.data_movimentacao >= :i5::timestamptz
                    AND m.data_movimentacao < (:f5::date + INTERVAL '1 day')) AS despesa_fin,

                -- Deduções
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE AND cd.tipo = 'Deducao'
                    AND m.data_movimentacao >= :i6::timestamptz
                    AND m.data_movimentacao < (:f6::date + INTERVAL '1 day')) AS deducoes,

                -- Tributos
                (SELECT COALESCE(SUM(m.valor), 0)
                   FROM movimentacoes m
                   INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
                  WHERE m.afeta_dre = TRUE AND cd.tipo = 'Tributo'
                    AND m.data_movimentacao >= :i7::timestamptz
                    AND m.data_movimentacao < (:f7::date + INTERVAL '1 day')) AS tributos"
        );
        $params = [];
        foreach ([1,3,4,5,6,7] as $n) {
            $params[":i$n"] = $inicio;
            $params[":f$n"] = $fim;
        }
        $stmt->execute($params);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $receitaBruta = (float)($r['receita_cr'] ?? 0) + (float)($r['receita_pdv'] ?? 0);
        $deducoes = (float)($r['deducoes'] ?? 0);
        $cpv = (float)($r['cpv'] ?? 0);
        $despesaOp = (float)($r['despesa_op'] ?? 0);
        $despesaFin = (float)($r['despesa_fin'] ?? 0);
        $tributos = (float)($r['tributos'] ?? 0);

        $receitaLiquida = $receitaBruta - $deducoes;
        $lucroBruto = $receitaLiquida - $cpv;
        $ebitda = $lucroBruto - $despesaOp;
        $lucroAntesIR = $ebitda - $despesaFin;
        $lucroLiquido = $lucroAntesIR - $tributos;

        return compact(
            'receitaBruta', 'deducoes', 'receitaLiquida',
            'cpv', 'lucroBruto',
            'despesaOp', 'ebitda', 'despesaFin',
            'tributos', 'lucroLiquido'
        );
    }

    /**
     * Contas a receber faixas (vencidas, hoje, 7d, 15d, 30d).
     */
    public function crPorVencimento(): array
    {
        $stmt = $this->pdo->query(
            "SELECT faixa,
                    COALESCE(SUM(valor - COALESCE(valor_pago, 0)), 0) AS total
               FROM (
                   SELECT CASE
                       WHEN data_vencimento < CURRENT_DATE THEN 'Vencidas'
                       WHEN data_vencimento = CURRENT_DATE THEN 'Hoje'
                       WHEN data_vencimento <= CURRENT_DATE + 7 THEN '7 dias'
                       WHEN data_vencimento <= CURRENT_DATE + 15 THEN '15 dias'
                       WHEN data_vencimento <= CURRENT_DATE + 30 THEN '30 dias'
                   END AS faixa,
                   valor, valor_pago
                   FROM contas_receber
                  WHERE status IN ('PENDENTE', 'VENCIDO')
                    AND data_vencimento <= CURRENT_DATE + 30
               ) t
              WHERE faixa IS NOT NULL
              GROUP BY faixa"
        );
        // Ordena manualmente
        $ordem = ['Vencidas' => 1, 'Hoje' => 2, '7 dias' => 3, '15 dias' => 4, '30 dias' => 5];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        usort($rows, fn ($a, $b) => ($ordem[$a['faixa']] ?? 99) <=> ($ordem[$b['faixa']] ?? 99));
        return array_map(
            static fn ($r) => ['faixa' => (string)$r['faixa'], 'total' => (float)$r['total']],
            $rows
        );
    }

    public function porCategoria(string $inicio, string $fim, string $tipoMov, array $tiposCategoria): array
    {
        $placeholders = [];
        $params = [':ini' => $inicio, ':fim' => $fim, ':tm' => $tipoMov];
        foreach ($tiposCategoria as $idx => $t) {
            $key = ":t{$idx}";
            $placeholders[] = $key;
            $params[$key] = $t;
        }
        $inSql = implode(',', $placeholders);

        $stmt = $this->pdo->prepare(
            "SELECT cd.nome AS categoria,
                    COALESCE(SUM(m.valor), 0) AS total
               FROM movimentacoes m
               INNER JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
              WHERE m.afeta_saldo = TRUE
                AND m.tipo = :tm
                AND cd.tipo IN ($inSql)
                AND m.data_movimentacao >= :ini::timestamptz
                AND m.data_movimentacao < (:fim::date + INTERVAL '1 day')
              GROUP BY cd.id, cd.nome
              HAVING SUM(m.valor) > 0
              ORDER BY total DESC
              LIMIT 8"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function receitasPorCategoria(string $inicio, string $fim): array
    {
        return $this->porCategoria($inicio, $fim, 'Entrada', ['Receita']);
    }

    public function despesasPorCategoria(string $inicio, string $fim): array
    {
        return $this->porCategoria(
            $inicio, $fim, 'Saida',
            ['Despesa', 'Despesa Operacional', 'Despesa Financeira', 'CPV', 'Tributo']
        );
    }

    /**
     * Recebimentos (afeta_saldo=TRUE) agrupados por forma de pagamento no periodo.
     * Nao impacta DRE — serve apenas para visibilidade operacional.
     * @return array<int, array{forma:string, tipo:string, quantidade:int, total:float}>
     */
    public function recebimentosPorForma(string $inicio, string $fim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fp.nome AS forma,
                    fp.tipo,
                    COUNT(m.id) AS quantidade,
                    COALESCE(SUM(m.valor), 0) AS total
               FROM movimentacoes m
               INNER JOIN formas_pagamento fp ON fp.id = m.forma_pagamento_id
              WHERE m.tipo = 'Entrada'
                AND m.afeta_saldo = TRUE
                AND m.data_movimentacao >= :ini::date
                AND m.data_movimentacao < (:fim::date + INTERVAL '1 day')
              GROUP BY fp.id, fp.nome, fp.tipo
             HAVING SUM(m.valor) > 0
              ORDER BY total DESC"
        );
        $stmt->execute([':ini' => $inicio, ':fim' => $fim]);
        return array_map(
            static fn ($r) => [
                'forma' => (string)$r['forma'],
                'tipo' => (string)$r['tipo'],
                'quantidade' => (int)$r['quantidade'],
                'total' => round((float)$r['total'], 2),
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function saldoContas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nome, tipo, banco, COALESCE(saldo_atual, 0) AS saldo_atual
               FROM contas
              WHERE ativo = TRUE
              ORDER BY saldo_atual DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contasPagarPorVencimento(): array
    {
        $stmt = $this->pdo->query(
            "SELECT faixa,
                    COALESCE(SUM(valor - COALESCE(valor_pago, 0)), 0) AS total
               FROM (
                   SELECT CASE
                       WHEN data_vencimento < CURRENT_DATE THEN 'Vencidas'
                       WHEN data_vencimento = CURRENT_DATE THEN 'Hoje'
                       WHEN data_vencimento <= CURRENT_DATE + 7 THEN '7 dias'
                       WHEN data_vencimento <= CURRENT_DATE + 15 THEN '15 dias'
                       WHEN data_vencimento <= CURRENT_DATE + 30 THEN '30 dias'
                   END AS faixa,
                   valor, valor_pago
                   FROM contas_pagar
                  WHERE status IN ('PENDENTE', 'VENCIDO')
                    AND data_vencimento <= CURRENT_DATE + 30
               ) t
              WHERE faixa IS NOT NULL
              GROUP BY faixa"
        );
        $ordem = ['Vencidas' => 1, 'Hoje' => 2, '7 dias' => 3, '15 dias' => 4, '30 dias' => 5];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        usort($rows, fn ($a, $b) => ($ordem[$a['faixa']] ?? 99) <=> ($ordem[$b['faixa']] ?? 99));
        return array_map(
            static fn ($r) => ['faixa' => (string)$r['faixa'], 'total' => (float)$r['total']],
            $rows
        );
    }

    public function ultimosLancamentos(int $limite = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.tipo, m.valor, m.descricao, m.data_movimentacao,
                    m.tipo_origem, m.protegido,
                    ct.nome AS conta_nome,
                    cd.nome AS categoria_nome
               FROM movimentacoes m
               LEFT JOIN contas ct ON ct.id = m.conta_id
               LEFT JOIN categorias_dre cd ON cd.id = m.categoria_dre_id
              ORDER BY m.data_movimentacao DESC, m.id DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
