<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use PDO;

final class PdvRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function buscarUsuario(int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT u.id, u.nome, u.email, u.senha, u.nivel_acesso_id
              FROM usuarios u
             WHERE u.id = :id
             LIMIT 1
        SQL);
        $stmt->execute([':id' => $usuarioId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function proximoNumeroCaixa(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(numero_caixa), 0) + 1 FROM pdv_caixas');
        return (int) ($stmt->fetchColumn() ?: 1);
    }

    public function criarCaixa(int $usuarioId, int $numeroCaixa, float $valorSuprimento): int
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO pdv_caixas (
                usuario_abertura_id,
                numero_caixa,
                valor_suprimento
            ) VALUES (
                :usuario_abertura_id,
                :numero_caixa,
                :valor_suprimento
            )
            RETURNING id
        SQL);
        $stmt->execute([
            ':usuario_abertura_id' => $usuarioId,
            ':numero_caixa' => $numeroCaixa,
            ':valor_suprimento' => round($valorSuprimento, 2),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function buscarCaixa(int $caixaId): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT c.*,
                   u.nome AS operador_nome,
                   u.email AS operador_email
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
             WHERE c.id = :id
             LIMIT 1
        SQL);
        $stmt->execute([':id' => $caixaId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listarCaixasAbertos(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT c.*,
                   u.nome AS operador_nome
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
             WHERE c.status = 'aberto'
             ORDER BY c.data_abertura ASC, c.id ASC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function caixaEstaAberto(int $caixaId): bool
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT 1
              FROM pdv_caixas
             WHERE id = :id
               AND status = :status
             LIMIT 1
        SQL);
        $stmt->execute([
            ':id' => $caixaId,
            ':status' => 'aberto',
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function inserirOuAtualizarLancamento(
        int $caixaId,
        int $usuarioId,
        string $tipo,
        string $referenciaId,
        float $valorTotal,
        string $formaPagamento
    ): void {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO pdv_lancamentos (
                caixa_id,
                usuario_id,
                tipo,
                pedido_id,
                valor_total,
                forma_pagamento
            ) VALUES (
                :caixa_id,
                :usuario_id,
                :tipo,
                CAST(:pedido_id AS uuid),
                :valor_total,
                :forma_pagamento
            )
            ON CONFLICT (pedido_id)
            DO UPDATE SET
                caixa_id = EXCLUDED.caixa_id,
                usuario_id = EXCLUDED.usuario_id,
                valor_total = EXCLUDED.valor_total,
                forma_pagamento = EXCLUDED.forma_pagamento
        SQL);

        $stmt->execute([
            ':caixa_id' => $caixaId,
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':pedido_id' => $referenciaId,
            ':valor_total' => round($valorTotal, 2),
            ':forma_pagamento' => $formaPagamento,
        ]);
    }

    public function totaisSistemaPorForma(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT forma_pagamento, COALESCE(SUM(valor_total), 0) AS total
              FROM pdv_lancamentos
             WHERE caixa_id = :caixa_id
             GROUP BY forma_pagamento
        SQL);
        $stmt->execute([':caixa_id' => $caixaId]);

        $totais = [
            'dinheiro' => 0.0,
            'cartao' => 0.0,
            'pix' => 0.0,
            'a_faturar' => 0.0,
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $forma = (string) ($row['forma_pagamento'] ?? '');
            if (!array_key_exists($forma, $totais)) {
                continue;
            }
            $totais[$forma] = (float) ($row['total'] ?? 0);
        }

        $caixa = $this->buscarCaixa($caixaId);
        if (is_array($caixa)) {
            $totais['dinheiro'] += (float) ($caixa['valor_suprimento'] ?? 0);
        }

        return $totais;
    }

    public function atualizarFechamentoCaixa(int $caixaId, array $dados): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            UPDATE pdv_caixas
               SET status = :status,
                   data_fechamento = :data_fechamento,
                   fechamento_dinheiro = :fechamento_dinheiro,
                   fechamento_cartao = :fechamento_cartao,
                   fechamento_pix = :fechamento_pix,
                   fechamento_faturar = :fechamento_faturar,
                   sistema_dinheiro = :sistema_dinheiro,
                   sistema_cartao = :sistema_cartao,
                   sistema_pix = :sistema_pix,
                   sistema_faturar = :sistema_faturar,
                   diferenca_dinheiro = :diferenca_dinheiro,
                   diferenca_cartao = :diferenca_cartao,
                   diferenca_pix = :diferenca_pix,
                   diferenca_faturar = :diferenca_faturar,
                   diferenca_total = :diferenca_total,
                   observacao = :observacao
             WHERE id = :id
        SQL);
        $stmt->execute([
            ':id' => $caixaId,
            ':status' => $dados['status'] ?? 'fechado',
            ':data_fechamento' => $dados['data_fechamento'],
            ':fechamento_dinheiro' => $dados['fechamento_dinheiro'],
            ':fechamento_cartao' => $dados['fechamento_cartao'],
            ':fechamento_pix' => $dados['fechamento_pix'],
            ':fechamento_faturar' => $dados['fechamento_faturar'],
            ':sistema_dinheiro' => $dados['sistema_dinheiro'],
            ':sistema_cartao' => $dados['sistema_cartao'],
            ':sistema_pix' => $dados['sistema_pix'],
            ':sistema_faturar' => $dados['sistema_faturar'],
            ':diferenca_dinheiro' => $dados['diferenca_dinheiro'],
            ':diferenca_cartao' => $dados['diferenca_cartao'],
            ':diferenca_pix' => $dados['diferenca_pix'],
            ':diferenca_faturar' => $dados['diferenca_faturar'],
            ':diferenca_total' => $dados['diferenca_total'],
            ':observacao' => $dados['observacao'] ?? null,
        ]);
    }

    public function listarLancamentosDetalhados(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT l.id,
                   l.tipo,
                   l.pedido_id AS referencia_id,
                   l.valor_total,
                   l.forma_pagamento,
                   l.created_at,
                   ul.nome AS usuario_lancou,
                   p.numero::text AS numero_referencia,
                   COALESCE(cp.nome, 'Cliente avulso') AS cliente_nome,
                   COALESCE(NULLIF(p.observacoes, ''), '') AS descricao,
                   'Pedido' AS tipo_label
              FROM pdv_lancamentos l
              INNER JOIN usuarios ul ON ul.id = l.usuario_id
              LEFT JOIN pedidos p ON p.id = l.pedido_id
              LEFT JOIN clientes cp ON cp.id = p.cliente_id
             WHERE l.caixa_id = :caixa_id
             ORDER BY l.created_at ASC, l.id ASC
        SQL);
        $stmt->execute([':caixa_id' => $caixaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarOperadores(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT DISTINCT u.id, u.nome
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
             ORDER BY u.nome ASC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarCaixasGerenciais(array $filtros): array
    {
        $condicoes = [];
        $params = [];

        if (!empty($filtros['data'])) {
            $condicoes[] = 'DATE(c.data_abertura) = :data';
            $params[':data'] = $filtros['data'];
        }

        if (!empty($filtros['operador'])) {
            $condicoes[] = 'c.usuario_abertura_id = :operador';
            $params[':operador'] = (int) $filtros['operador'];
        }

        if (!empty($filtros['status'])) {
            $condicoes[] = 'c.status = :status';
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['diferenca'])) {
            if ($filtros['diferenca'] === 'ok') {
                $condicoes[] = 'COALESCE(c.diferenca_total, 0) = 0';
            } elseif ($filtros['diferenca'] === 'divergente') {
                $condicoes[] = 'COALESCE(c.diferenca_total, 0) <> 0';
            }
        }

        if (!empty($filtros['conferencia'])) {
            if ($filtros['conferencia'] === 'pendente') {
                $condicoes[] = "c.status = 'fechado' AND COALESCE(c.conferencia_concluida, FALSE) = FALSE";
            } elseif ($filtros['conferencia'] === 'concluida') {
                $condicoes[] = 'COALESCE(c.conferencia_concluida, FALSE) = TRUE';
            }
        }

        $where = $condicoes === [] ? '' : 'WHERE ' . implode(' AND ', $condicoes);

        $sql = <<<SQL
            SELECT c.*,
                   u.nome AS operador_nome,
                   COALESCE(c.sistema_dinheiro, 0) + COALESCE(c.sistema_cartao, 0) + COALESCE(c.sistema_pix, 0) + COALESCE(c.sistema_faturar, 0) AS total_lancado,
                   COALESCE(c.fechamento_dinheiro, 0) + COALESCE(c.fechamento_cartao, 0) + COALESCE(c.fechamento_pix, 0) + COALESCE(c.fechamento_faturar, 0) AS total_digitado
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
              {$where}
             ORDER BY c.data_abertura DESC, c.id DESC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function dashboardConferenciaHoje(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE DATE(data_abertura) = CURRENT_DATE) AS total_caixas_hoje,
                COUNT(*) FILTER (WHERE status = 'aberto') AS caixas_abertos_agora,
                COALESCE(SUM(
                    CASE
                        WHEN DATE(data_abertura) = CURRENT_DATE
                        THEN COALESCE(sistema_dinheiro, 0) + COALESCE(sistema_cartao, 0) + COALESCE(sistema_pix, 0) + COALESCE(sistema_faturar, 0)
                        ELSE 0
                    END
                ), 0) AS total_faturado_hoje,
                COALESCE(SUM(
                    CASE
                        WHEN DATE(data_abertura) = CURRENT_DATE AND status = 'fechado'
                        THEN ABS(COALESCE(diferenca_total, 0))
                        ELSE 0
                    END
                ), 0) AS total_divergencias_hoje
              FROM pdv_caixas
        SQL);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    public function faturamentoPorCaixaHoje(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT c.numero_caixa,
                   u.nome AS operador_nome,
                   COALESCE(c.sistema_dinheiro, 0) + COALESCE(c.sistema_cartao, 0) + COALESCE(c.sistema_pix, 0) + COALESCE(c.sistema_faturar, 0) AS total_faturado
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
             WHERE DATE(c.data_abertura) = CURRENT_DATE
             ORDER BY c.numero_caixa ASC, c.id ASC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function salvarObservacao(int $caixaId, string $observacao): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            UPDATE pdv_caixas
               SET observacao = :observacao
             WHERE id = :id
        SQL);
        $stmt->execute([
            ':id' => $caixaId,
            ':observacao' => trim($observacao),
        ]);
    }

    public function buscarFormaPagamentoFinanceiraPorGrupo(string $grupo): ?array
    {
        $tipos = match ($grupo) {
            'dinheiro' => ['D'],
            'cartao' => ['CC', 'CD'],
            'pix' => ['PIX'],
            'a_faturar' => ['AF'],
            default => [],
        };

        if ($tipos === []) {
            return null;
        }

        $placeholders = [];
        $params = [];
        foreach ($tipos as $index => $tipo) {
            $key = ':tipo_' . $index;
            $placeholders[] = $key;
            $params[$key] = $tipo;
        }

        $sql = '
            SELECT id, nome, tipo
              FROM formas_pagamento
             WHERE ativo = TRUE
               AND tipo IN (' . implode(', ', $placeholders) . ')
             ORDER BY CASE tipo
                          WHEN \'D\' THEN 1
                          WHEN \'PIX\' THEN 2
                          WHEN \'CD\' THEN 3
                          WHEN \'CC\' THEN 4
                          WHEN \'AF\' THEN 5
                          ELSE 9
                      END,
                      id ASC
             LIMIT 1
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
