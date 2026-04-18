<?php

namespace App\Modules\Orcamento;

use PDO;

/**
 * Repository: OrcamentoItemRepository
 *
 * Responsável exclusivamente por persistência da tabela orcamento_itens.
 * Operações de lote usadas na criação/substituição de itens.
 */
class OrcamentoItemRepository
{
    private const TABLE = 'orcamento_itens';

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Escrita
    // =========================================================================

    /**
     * Insere um único item e retorna o ID gerado.
     * Chama calcularSubtotal() para garantir consistência.
     */
    public function criar(OrcamentoItem $item): int
    {
        $item->calcularSubtotal();

        $stmt = $this->pdo->prepare("
            INSERT INTO orcamento_itens
                (orcamento_id, tipo_item, produto_id, servico_id, nome_produto, nome_servico, descricao_item,
                 quantidade, valor_unitario, valor_total_item)
            VALUES
                (:orcamento_id, :tipo_item, :produto_id, :servico_id, :nome_produto, :nome_servico, :descricao_item,
                 :quantidade, :valor_unitario, :valor_total_item)
        ");

        $stmt->execute($this->bind($item));

        $id      = (int) $this->pdo->lastInsertId(self::TABLE . '_id_seq');
        $item->id = $id;

        return $id;
    }

    /**
     * Insere todos os itens de uma vez (DELETE-ALL + INSERT).
     * Usado na criação e na atualização completa de um orçamento.
     *
     * @param OrcamentoItem[] $itens
     */
    public function substituirTodos(int $orcamentoId, array $itens): void
    {
        $this->excluirPorOrcamento($orcamentoId);

        foreach ($itens as $ordem => $item) {
            $item->orcamentoId = $orcamentoId;
            $item->ordem       = $ordem;
            $this->criar($item);
        }
    }

    /**
     * Remove todos os itens de um orçamento.
     */
    public function excluirPorOrcamento(int $orcamentoId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM orcamento_itens WHERE orcamento_id = :id"
        );
        $stmt->execute([':id' => $orcamentoId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Remove um item específico pelo ID.
     */
    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM orcamento_itens WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Leitura
    // =========================================================================

    /**
     * Retorna todos os itens de um orçamento ordenados.
     *
     * @return OrcamentoItem[]
     */
    public function buscarPorOrcamento(int $orcamentoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT oi.*
            FROM orcamento_itens oi
            WHERE oi.orcamento_id = :id
            ORDER BY oi.id
        ");
        $stmt->execute([':id' => $orcamentoId]);

        return array_map(
            fn(array $row) => OrcamentoItem::fromArray($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Soma dos subtotais de todos os itens de um orçamento.
     * Usado pelo Service para recalcular o total do orçamento.
     */
    public function somarSubtotais(int $orcamentoId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(valor_total_item), 0)
            FROM orcamento_itens
            WHERE orcamento_id = :id
        ");
        $stmt->execute([':id' => $orcamentoId]);

        return (float) $stmt->fetchColumn();
    }

    // =========================================================================
    // Helper privado
    // =========================================================================

    private function bind(OrcamentoItem $i): array
    {
        return [
            ':orcamento_id'     => $i->orcamentoId,
            ':tipo_item'        => $i->tipoItem,
            ':produto_id'       => $i->produtoId,
            ':servico_id'       => $i->servicoId,
            ':nome_produto'     => $i->nomeProduto,
            ':nome_servico'     => $i->nomeServico,
            ':descricao_item'   => $i->descricaoItem ?? null,
            ':quantidade'       => $i->quantidade,
            ':valor_unitario'   => $i->precoUnitario,
            ':valor_total_item' => $i->subtotal,
        ];
    }
}
