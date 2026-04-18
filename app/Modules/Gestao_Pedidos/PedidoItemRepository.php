<?php

namespace App\Modules\Gestao_Pedidos;

use PDO;
use RuntimeException;

class PedidoItemRepository
{
    private const TABLE = 'pedido_itens';

    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): PedidoItem
    {
        $id = $data['id'] ?? PedidoRepository::uuid();

        $item = PedidoItem::fromArray($data + ['id' => $id]);
        $item->calcularTotal();

        $stmt = $this->pdo->prepare(
            "INSERT INTO " . self::TABLE . "
                (id, pedido_id, produto_id, nome_produto, quantidade, valor_unitario, valor_total_item, observacoes)
             VALUES
                (:id, :pedido_id, :produto_id, :nome_produto, :quantidade, :valor_unitario, :valor_total_item, :observacoes)"
        );

        $stmt->execute([
            ':id' => $item->id,
            ':pedido_id' => $item->pedido_id,
            ':produto_id' => $this->intOrNull($item->produto_id),
            ':nome_produto' => $item->nome_produto,
            ':quantidade' => $item->quantidade,
            ':valor_unitario' => $item->valor_unitario,
            ':valor_total_item' => $item->valor_total_item,
            ':observacoes' => $item->observacoes,
        ]);

        return $item;
    }

    /** @return PedidoItem[] */
    public function findByPedidoId(string $pedidoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                pi.id,
                pi.pedido_id,
                pi.produto_id,
                COALESCE(NULLIF(pi.nome_produto, ''), p.nome, '') AS nome_produto,
                pi.quantidade,
                pi.valor_unitario,
                pi.valor_total_item,
                pi.observacoes,
                pi.created_at,
                pi.updated_at
             FROM " . self::TABLE . " pi
             LEFT JOIN produtos p ON p.id::text = pi.produto_id::text
             WHERE pi.pedido_id = :pedido_id
             ORDER BY pi.created_at ASC, pi.id ASC"
        );
        $stmt->execute([':pedido_id' => $pedidoId]);

        return array_map(
            static fn(array $row): PedidoItem => PedidoItem::fromArray($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function update(string $id, array $data): PedidoItem
    {
        $stmtCurrent = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmtCurrent->execute([':id' => $id]);
        $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            throw new RuntimeException('Item de pedido não encontrado para atualização.');
        }

        $item = PedidoItem::fromArray($current + $data);
        $item->calcularTotal();

        $stmt = $this->pdo->prepare(
            "UPDATE " . self::TABLE . "
             SET pedido_id = :pedido_id,
                 produto_id = :produto_id,
                 nome_produto = :nome_produto,
                 quantidade = :quantidade,
                 valor_unitario = :valor_unitario,
                 valor_total_item = :valor_total_item,
                 observacoes = :observacoes,
                 updated_at = NOW()
             WHERE id = :id"
        );

        $stmt->execute([
            ':id' => $id,
            ':pedido_id' => $item->pedido_id,
            ':produto_id' => $this->intOrNull($item->produto_id),
            ':nome_produto' => $item->nome_produto,
            ':quantidade' => $item->quantidade,
            ':valor_unitario' => $item->valor_unitario,
            ':valor_total_item' => $item->valor_total_item,
            ':observacoes' => $item->observacoes,
        ]);

        return $item;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByPedidoId(string $pedidoId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE pedido_id = :pedido_id");
        $stmt->execute([':pedido_id' => $pedidoId]);
        return $stmt->rowCount() >= 0;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }
}
