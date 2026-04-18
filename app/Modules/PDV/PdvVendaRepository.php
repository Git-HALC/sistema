<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use PDO;
use RuntimeException;

final class PdvVendaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array<int, array{tipo_item:string, produto_id:?int, servico_id:?int, nome_item:string, quantidade:float, valor_unitario:float, valor_total_item:float}> $itens
     */
    public function criar(PdvVenda $venda, array $itens): int
    {
        if ($itens === []) {
            throw new RuntimeException('Venda precisa de ao menos 1 item.');
        }

        $mustCommit = !$this->pdo->inTransaction();
        if ($mustCommit) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO pdv_vendas
                    (caixa_id, usuario_id, cliente_id, status, forma_pagamento_id,
                     desconto_tipo, desconto_valor, valor_total, observacoes, origem, protegido)
                 VALUES
                    (:caixa_id, :usuario_id, :cliente_id, :status, :forma_pagamento_id,
                     :desconto_tipo, :desconto_valor, :valor_total, :observacoes, :origem, TRUE)
                 RETURNING id, numero'
            );
            $stmt->execute([
                ':caixa_id' => $venda->caixa_id,
                ':usuario_id' => $venda->usuario_id,
                ':cliente_id' => $venda->cliente_id,
                ':status' => $venda->status,
                ':forma_pagamento_id' => $venda->forma_pagamento_id,
                ':desconto_tipo' => $venda->desconto_tipo,
                ':desconto_valor' => $venda->desconto_valor,
                ':valor_total' => $venda->valor_total,
                ':observacoes' => $venda->observacoes,
                ':origem' => $venda->origem,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $vendaId = (int)$row['id'];

            $stmtItem = $this->pdo->prepare(
                'INSERT INTO pdv_venda_itens
                    (venda_id, tipo_item, produto_id, servico_id, nome_item,
                     quantidade, valor_unitario, valor_total_item)
                 VALUES
                    (:venda_id, :tipo_item, :produto_id, :servico_id, :nome_item,
                     :quantidade, :valor_unitario, :valor_total_item)'
            );

            foreach ($itens as $i) {
                $stmtItem->execute([
                    ':venda_id' => $vendaId,
                    ':tipo_item' => $i['tipo_item'],
                    ':produto_id' => $i['tipo_item'] === 'PRODUTO' ? $i['produto_id'] : null,
                    ':servico_id' => $i['tipo_item'] === 'SERVICO' ? $i['servico_id'] : null,
                    ':nome_item' => $i['nome_item'],
                    ':quantidade' => $i['quantidade'],
                    ':valor_unitario' => $i['valor_unitario'],
                    ':valor_total_item' => $i['valor_total_item'],
                ]);
            }

            if ($mustCommit) {
                $this->pdo->commit();
            }

            return $vendaId;
        } catch (\Throwable $e) {
            if ($mustCommit && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Busca produtos + servicos ativos para a grade de venda rapida.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarItens(string $termo, int $limite = 50): array
    {
        $termoLike = '%' . $termo . '%';
        $sql = "
            SELECT 'PRODUTO' AS tipo, id, nome, codigo AS codigo, preco_venda AS valor
              FROM produtos
             WHERE ativo = TRUE
               AND (nome ILIKE :busca OR COALESCE(codigo,'') ILIKE :busca)
            UNION ALL
            SELECT 'SERVICO' AS tipo, id, nome, NULL::varchar AS codigo, valor_base AS valor
              FROM servicos_catalogo
             WHERE ativo = TRUE
               AND nome ILIKE :busca
             ORDER BY nome ASC
             LIMIT :limite
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':busca', $termoLike);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array{id:int, nome:string, tipo:string}>
     */
    public function listarFormasPagamentoAtivas(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, nome, tipo FROM formas_pagamento WHERE ativo = TRUE ORDER BY nome'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?PdvVenda
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pdv_vendas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $venda = PdvVenda::fromArray($row);

        $stmtItens = $this->pdo->prepare(
            'SELECT * FROM pdv_venda_itens WHERE venda_id = :id ORDER BY id'
        );
        $stmtItens->execute([':id' => $id]);
        $venda->itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $venda;
    }

    /**
     * Listagem detalhada das vendas de um caixa, com filtros opcionais.
     *
     * @param array{forma_pagamento_id?:?int, status?:?string, desde?:?string, ate?:?string} $filtros
     * @return array<int, array<string, mixed>>
     */
    public function listarDoCaixa(int $caixaId, array $filtros = []): array
    {
        $where = ['v.caixa_id = :caixa_id'];
        $params = [':caixa_id' => $caixaId];

        if (!empty($filtros['forma_pagamento_id'])) {
            $where[] = 'v.forma_pagamento_id = :fp';
            $params[':fp'] = (int)$filtros['forma_pagamento_id'];
        }
        if (!empty($filtros['status']) && in_array($filtros['status'], ['faturado', 'cancelado'], true)) {
            $where[] = 'v.status = :st';
            $params[':st'] = $filtros['status'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'v.created_at >= :desde';
            $params[':desde'] = $filtros['desde'];
        }
        if (!empty($filtros['ate'])) {
            $where[] = 'v.created_at <= :ate';
            $params[':ate'] = $filtros['ate'];
        }

        $sql = "
            SELECT v.id, v.numero, v.status, v.valor_total, v.desconto_tipo, v.desconto_valor,
                   v.created_at, v.cancelado_em, v.motivo_cancelamento, v.observacoes,
                   fp.nome AS forma_pagamento_nome, fp.tipo AS forma_pagamento_tipo,
                   u.nome AS operador_nome,
                   uc.nome AS cancelado_por_nome,
                   cli.nome AS cliente_nome,
                   (SELECT COUNT(*) FROM pdv_venda_itens i WHERE i.venda_id = v.id) AS qtd_itens,
                   (SELECT string_agg(i.nome_item, ' · ' ORDER BY i.id)
                      FROM (SELECT nome_item, id FROM pdv_venda_itens
                             WHERE venda_id = v.id ORDER BY id LIMIT 3) i) AS resumo_itens
              FROM pdv_vendas v
              LEFT JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
              INNER JOIN usuarios u ON u.id = v.usuario_id
              LEFT JOIN usuarios uc ON uc.id = v.cancelado_por_id
              LEFT JOIN clientes cli ON cli.id = v.cliente_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY v.created_at DESC, v.id DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{total:float, qtd:int, ticket_medio:float, por_forma: array<string, array{nome:string, tipo:string, total:float, qtd:int}>}
     */
    public function totalizadoresDoCaixa(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fp.id AS forma_id, fp.nome AS forma_nome, fp.tipo AS forma_tipo,
                    COALESCE(SUM(v.valor_total), 0) AS total,
                    COUNT(v.id) AS qtd
               FROM formas_pagamento fp
          LEFT JOIN pdv_vendas v ON v.forma_pagamento_id = fp.id
                                 AND v.caixa_id = :caixa_id
                                 AND v.status = 'faturado'
              WHERE fp.ativo = TRUE
           GROUP BY fp.id, fp.nome, fp.tipo
           ORDER BY fp.nome"
        );
        $stmt->execute([':caixa_id' => $caixaId]);

        $total = 0.0;
        $qtd = 0;
        $porForma = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $vTotal = (float)$row['total'];
            $vQtd = (int)$row['qtd'];
            $total += $vTotal;
            $qtd += $vQtd;
            $porForma[(string)$row['forma_tipo']] = [
                'nome' => (string)$row['forma_nome'],
                'tipo' => (string)$row['forma_tipo'],
                'total' => $vTotal,
                'qtd' => $vQtd,
            ];
        }

        return [
            'total' => round($total, 2),
            'qtd' => $qtd,
            'ticket_medio' => $qtd > 0 ? round($total / $qtd, 2) : 0.0,
            'por_forma' => $porForma,
        ];
    }

    /**
     * Vendas no Kanban (origem=fluxo, não canceladas), agrupadas por status.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    public function listarKanbanDoCaixa(int $caixaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.id, v.numero, v.status, v.valor_total, v.created_at,
                   u.nome AS operador_nome,
                   cli.nome AS cliente_nome,
                   (SELECT string_agg(i.nome_item, ' · ' ORDER BY i.id)
                      FROM (SELECT nome_item, id FROM pdv_venda_itens
                             WHERE venda_id = v.id ORDER BY id LIMIT 2) i) AS resumo_itens,
                   (SELECT COUNT(*) FROM pdv_venda_itens i WHERE i.venda_id = v.id) AS qtd_itens
              FROM pdv_vendas v
              INNER JOIN usuarios u ON u.id = v.usuario_id
              LEFT JOIN clientes cli ON cli.id = v.cliente_id
             WHERE v.caixa_id = :caixa_id
               AND v.origem = 'fluxo'
               AND v.status <> 'cancelado'
             ORDER BY v.created_at ASC
        ");
        $stmt->execute([':caixa_id' => $caixaId]);

        $colunas = ['pendente' => [], 'em_processo' => [], 'concluido' => [], 'faturado' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $st = (string)$row['status'];
            if (isset($colunas[$st])) {
                $colunas[$st][] = $row;
            }
        }
        return $colunas;
    }

    /**
     * @return array{ok:bool, erro:?string}
     */
    public function moverStatus(int $vendaId, string $novoStatus, ?int $formaPagamentoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.status AS vs, v.origem, v.forma_pagamento_id, c.status AS cs
               FROM pdv_vendas v
               INNER JOIN pdv_caixas c ON c.id = v.caixa_id
              WHERE v.id = :id"
        );
        $stmt->execute([':id' => $vendaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'erro' => 'nao_encontrada'];
        }
        if ((string)$row['cs'] !== 'aberto') {
            return ['ok' => false, 'erro' => 'caixa_fechado'];
        }
        if ((string)$row['vs'] === 'cancelado') {
            return ['ok' => false, 'erro' => 'cancelada'];
        }

        $ordem = ['pendente' => 1, 'em_processo' => 2, 'concluido' => 3, 'faturado' => 4];
        $atual = $ordem[(string)$row['vs']] ?? 0;
        $destino = $ordem[$novoStatus] ?? 0;
        if ($destino === 0 || $destino < $atual) {
            return ['ok' => false, 'erro' => 'transicao_invalida'];
        }

        $params = [':id' => $vendaId, ':status' => $novoStatus];
        $setFp = '';
        if ($novoStatus === 'faturado') {
            if ($formaPagamentoId === null) {
                return ['ok' => false, 'erro' => 'transicao_invalida'];
            }
            $setFp = ', forma_pagamento_id = :fp, faturada_em = CURRENT_TIMESTAMP';
            $params[':fp'] = $formaPagamentoId;
        }

        $upd = $this->pdo->prepare(
            "UPDATE pdv_vendas SET status = :status $setFp WHERE id = :id"
        );
        $upd->execute($params);

        return ['ok' => $upd->rowCount() === 1, 'erro' => null];
    }

    /**
     * @return array{ok:bool, status_caixa:string}
     */
    public function cancelarVenda(int $vendaId, int $usuarioId, ?string $motivo): array
    {
        // Caixa precisa estar aberto
        $stmt = $this->pdo->prepare(
            'SELECT c.status AS caixa_status, v.status AS venda_status
               FROM pdv_vendas v
               INNER JOIN pdv_caixas c ON c.id = v.caixa_id
              WHERE v.id = :id'
        );
        $stmt->execute([':id' => $vendaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'status_caixa' => 'inexistente'];
        }
        if ((string)$row['caixa_status'] !== 'aberto') {
            return ['ok' => false, 'status_caixa' => (string)$row['caixa_status']];
        }
        if ((string)$row['venda_status'] === 'cancelado') {
            return ['ok' => false, 'status_caixa' => 'aberto'];
        }

        $upd = $this->pdo->prepare(
            "UPDATE pdv_vendas
                SET status = 'cancelado',
                    cancelado_por_id = :uid,
                    cancelado_em = CURRENT_TIMESTAMP,
                    motivo_cancelamento = :motivo
              WHERE id = :id AND status = 'faturado'"
        );
        $upd->execute([
            ':uid' => $usuarioId,
            ':motivo' => $motivo !== null && $motivo !== '' ? $motivo : null,
            ':id' => $vendaId,
        ]);

        return ['ok' => $upd->rowCount() === 1, 'status_caixa' => 'aberto'];
    }
}
