<?php

namespace App\Modules\Produtos;

use PDO;
use RuntimeException;

class EstoqueMovimentacaoRepository
{
    private const TABLE = 'produto_estoque_movimentacoes';

    public function __construct(private readonly PDO $pdo) {}

    public function movimentar(
        int $produtoId,
        float $quantidade,
        string $tipo,
        ?int $usuarioId = null,
        string $origem = 'MANUAL',
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        ?string $observacao = null
    ): void {
        $tipo = strtoupper(trim($tipo));
        if (!in_array($tipo, ['ENTRADA', 'SAIDA'], true)) {
            throw new RuntimeException('Tipo de movimenta��o de estoque inv�lido.');
        }

        $quantidade = round($quantidade, 4);
        if ($quantidade <= 0) {
            throw new RuntimeException('Quantidade de movimenta��o deve ser maior que zero.');
        }

        $stmtProduto = $this->pdo->prepare('
            SELECT id, nome, ativo, estoque_atual
            FROM produtos
            WHERE id = :id
            FOR UPDATE
        ');
        $stmtProduto->execute([':id' => $produtoId]);
        $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($produto === null) {
            throw new RuntimeException("Produto ID {$produtoId} n�o encontrado para movimenta��o de estoque.");
        }

        $ativo = filter_var($produto['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$ativo) {
            throw new RuntimeException('Produto "' . $produto['nome'] . '" est� inativo e n�o pode ter estoque movimentado.');
        }

        $estoqueAnterior = (float) $produto['estoque_atual'];
        $estoquePosterior = $tipo === 'ENTRADA'
            ? $estoqueAnterior + $quantidade
            : $estoqueAnterior - $quantidade;

        if ($estoquePosterior < 0) {
            throw new RuntimeException(
                'Estoque insuficiente para o produto "' . $produto['nome'] . '". ' .
                'Dispon�vel: ' . number_format($estoqueAnterior, 4, ',', '.') .
                ' | Necess�rio: ' . number_format($quantidade, 4, ',', '.')
            );
        }

        $stmtUpdate = $this->pdo->prepare('
            UPDATE produtos
            SET estoque_atual = :estoque_atual,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmtUpdate->execute([
            ':estoque_atual' => round($estoquePosterior, 4),
            ':id' => $produtoId,
        ]);

        $stmtInsert = $this->pdo->prepare('
            INSERT INTO ' . self::TABLE . ' (
                produto_id, usuario_id, tipo, origem, referencia_tipo, referencia_id,
                quantidade, estoque_anterior, estoque_posterior, observacao
            ) VALUES (
                :produto_id, :usuario_id, :tipo, :origem, :referencia_tipo, :referencia_id,
                :quantidade, :estoque_anterior, :estoque_posterior, :observacao
            )
        ');
        $stmtInsert->execute([
            ':produto_id' => $produtoId,
            ':usuario_id' => $usuarioId,
            ':tipo' => $tipo,
            ':origem' => strtoupper(trim($origem)) ?: 'MANUAL',
            ':referencia_tipo' => $referenciaTipo !== null && $referenciaTipo !== '' ? strtoupper(trim($referenciaTipo)) : null,
            ':referencia_id' => $referenciaId !== null && $referenciaId !== '' ? trim($referenciaId) : null,
            ':quantidade' => $quantidade,
            ':estoque_anterior' => round($estoqueAnterior, 4),
            ':estoque_posterior' => round($estoquePosterior, 4),
            ':observacao' => $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null,
        ]);
    }

    public function listarMovimentacoes(array $filtros = [], ?string $tipo = null): array
    {
        $condicoes = [];
        $params = [];

        if ($tipo !== null && $tipo !== '') {
            $condicoes[] = 'm.tipo = :tipo';
            $params[':tipo'] = strtoupper($tipo);
        }

        $busca = trim((string)($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $condicoes[] = '(p.nome ILIKE :busca OR COALESCE(p.codigo, \'\') ILIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        $produtoId = isset($filtros['produto_id']) ? (int)$filtros['produto_id'] : 0;
        if ($produtoId > 0) {
            $condicoes[] = 'm.produto_id = :produto_id';
            $params[':produto_id'] = $produtoId;
        }

        $somenteAtivos = !array_key_exists('somente_ativos', $filtros) || (int)$filtros['somente_ativos'] === 1;
        if ($somenteAtivos) {
            $condicoes[] = 'p.ativo = TRUE';
        }

        $dataInicio = trim((string)($filtros['data_inicio'] ?? ''));
        if ($dataInicio !== '') {
            $condicoes[] = "(m.created_at AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
            $params[':data_inicio'] = $dataInicio;
        }

        $dataFim = trim((string)($filtros['data_fim'] ?? ''));
        if ($dataFim !== '') {
            $condicoes[] = "(m.created_at AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
            $params[':data_fim'] = $dataFim;
        }

        $where = $condicoes ? ('WHERE ' . implode(' AND ', $condicoes)) : '';

        // Referencias legadas (PEDIDO / SERVICO) foram removidas na Fase 1 da auditoria.
        // Fonte atual: pdv_vendas (tipo_ref = 'VENDA_PDV').
        $sql = <<<SQL
            SELECT
                m.id,
                m.produto_id,
                p.codigo,
                p.nome,
                p.unidade,
                m.tipo,
                m.origem,
                m.referencia_tipo,
                m.referencia_id,
                m.quantidade,
                m.estoque_anterior,
                m.estoque_posterior,
                CASE
                    WHEN m.referencia_tipo = 'VENDA_PDV' AND v.numero IS NOT NULL AND m.tipo = 'SAIDA'
                        THEN 'Saida automatica vinculada a venda PDV #' || v.numero || '.'
                    WHEN m.referencia_tipo = 'VENDA_PDV' AND v.numero IS NOT NULL AND m.tipo = 'ENTRADA'
                        THEN 'Estorno da venda PDV #' || v.numero || '.'
                    ELSE m.observacao
                END AS observacao,
                m.created_at,
                u.nome AS usuario_nome
            FROM produto_estoque_movimentacoes m
            INNER JOIN produtos p ON p.id = m.produto_id
            LEFT JOIN usuarios u ON u.id = m.usuario_id
            LEFT JOIN pdv_vendas v
                   ON m.referencia_tipo = 'VENDA_PDV'
                  AND m.referencia_id ~ '^[0-9]+\$'
                  AND v.id = m.referencia_id::integer
            {$where}
            ORDER BY m.created_at DESC, m.id DESC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
