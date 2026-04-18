<?php

namespace App\Modules\Produtos;

use PDO;
use RuntimeException;

class EstoqueMovimentacaoService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EstoqueMovimentacaoRepository $repository
    ) {}

    public function validarDisponibilidadeItens(array $itens): void
    {
        $quantidades = [];
        foreach ($itens as $item) {
            $produtoId = $this->extrairProdutoId($item);
            if ($produtoId <= 0) {
                continue;
            }

            $quantidade = $this->extrairQuantidade($item);
            if (!isset($quantidades[$produtoId])) {
                $quantidades[$produtoId] = 0.0;
            }
            $quantidades[$produtoId] += $quantidade;
        }

        if ($quantidades === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach (array_keys($quantidades) as $index => $produtoId) {
            $ph = ':id' . $index;
            $placeholders[] = $ph;
            $params[$ph] = $produtoId;
        }

        $stmt = $this->pdo->prepare('
            SELECT id, nome, ativo, estoque_atual
            FROM produtos
            WHERE id IN (' . implode(',', $placeholders) . ')
        ');
        $stmt->execute($params);

        $produtos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $produtos[(int)$row['id']] = $row;
        }

        foreach ($quantidades as $produtoId => $quantidade) {
            $produto = $produtos[$produtoId] ?? null;
            if ($produto === null) {
                throw new RuntimeException("Produto ID {$produtoId} não encontrado para validação de estoque.");
            }

            $ativo = filter_var($produto['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$ativo) {
                throw new RuntimeException('Produto "' . $produto['nome'] . '" está inativo e não pode ser movimentado.');
            }

            $estoqueAtual = (float)$produto['estoque_atual'];
            if ($estoqueAtual < $quantidade) {
                throw new RuntimeException(
                    'Estoque insuficiente para o produto "' . $produto['nome'] . '". ' .
                    'Disponível: ' . number_format($estoqueAtual, 4, ',', '.') .
                    ' | Necessário: ' . number_format($quantidade, 4, ',', '.')
                );
            }
        }
    }

    public function movimentarItens(
        array $itens,
        string $tipo,
        ?int $usuarioId = null,
        string $origem = 'MANUAL',
        ?string $referenciaTipo = null,
        ?string $referenciaId = null,
        ?string $observacao = null
    ): void {
        $agrupados = [];
        foreach ($itens as $item) {
            $produtoId = $this->extrairProdutoId($item);
            if ($produtoId <= 0) {
                continue;
            }

            $quantidade = $this->extrairQuantidade($item);
            if (!isset($agrupados[$produtoId])) {
                $agrupados[$produtoId] = 0.0;
            }
            $agrupados[$produtoId] += $quantidade;
        }

        foreach ($agrupados as $produtoId => $quantidade) {
            $this->repository->movimentar(
                $produtoId,
                $quantidade,
                $tipo,
                $usuarioId,
                $origem,
                $referenciaTipo,
                $referenciaId,
                $observacao
            );
        }
    }

    public function movimentarManual(array $dados, int $usuarioId): void
    {
        $produtoId = (int)($dados['produto_id'] ?? 0);
        $tipo = strtoupper(trim((string)($dados['tipo'] ?? '')));
        $quantidade = (float)str_replace(',', '.', (string)($dados['quantidade'] ?? '0'));

        if ($produtoId <= 0) {
            throw new RuntimeException('Selecione um produto para movimentar.');
        }

        if (!in_array($tipo, ['ENTRADA', 'SAIDA'], true)) {
            throw new RuntimeException('Tipo de movimentação inválido.');
        }

        if ($quantidade <= 0) {
            throw new RuntimeException('Quantidade deve ser maior que zero.');
        }

        $this->repository->movimentar(
            $produtoId,
            $quantidade,
            $tipo,
            $usuarioId,
            'MANUAL',
            'INVENTARIO',
            (string)$produtoId,
            trim((string)($dados['observacao'] ?? '')) ?: null
        );
    }

    public function listarMovimentacoes(array $filtros = [], ?string $tipo = null): array
    {
        return $this->repository->listarMovimentacoes($filtros, $tipo);
    }

    private function extrairProdutoId(mixed $item): int
    {
        if (is_array($item)) {
            return (int)($item['produto_id'] ?? 0);
        }

        if (is_object($item)) {
            return (int)($item->produtoId ?? $item->produto_id ?? 0);
        }

        return 0;
    }

    private function extrairQuantidade(mixed $item): float
    {
        if (is_array($item)) {
            return (float)($item['quantidade'] ?? 0);
        }

        if (is_object($item)) {
            return (float)($item->quantidade ?? 0);
        }

        return 0.0;
    }
}
