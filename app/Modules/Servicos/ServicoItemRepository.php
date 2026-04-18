<?php

namespace App\Modules\Servicos;

use PDO;
use RuntimeException;

class ServicoItemRepository
{
    private const TABLE = 'servico_itens';

    private ?bool $tableExists = null;

    public function __construct(private readonly PDO $pdo) {}

    public function create(array $data): ServicoItem
    {
        if (!$this->hasTable()) {
            throw new RuntimeException('Tabela de itens de serviço não encontrada.');
        }

        $item = ServicoItem::fromArray($data + ['id' => ServicoRepository::uuid()]);
        $item->calcularTotal();

        $stmt = $this->pdo->prepare(
            "INSERT INTO " . self::TABLE . "
                (id, servico_id, produto_id, nome_produto, quantidade, valor_unitario, valor_total_item, observacoes)
             VALUES
                (:id, :servico_id, :produto_id, :nome_produto, :quantidade, :valor_unitario, :valor_total_item, :observacoes)"
        );

        $stmt->execute([
            ':id' => $item->id,
            ':servico_id' => $item->servicoId,
            ':produto_id' => $item->produtoId,
            ':nome_produto' => $item->nomeProduto,
            ':quantidade' => $item->quantidade,
            ':valor_unitario' => $item->valorUnitario,
            ':valor_total_item' => $item->valorTotalItem,
            ':observacoes' => $item->observacoes,
        ]);

        return $item;
    }

    /** @return ServicoItem[] */
    public function findByServicoId(string $servicoId): array
    {
        if (!$this->hasTable()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                si.id,
                si.servico_id,
                si.produto_id,
                COALESCE(NULLIF(si.nome_produto, ''), p.nome, '') AS nome_produto,
                si.quantidade,
                si.valor_unitario,
                si.valor_total_item,
                si.observacoes,
                si.created_at,
                si.updated_at
             FROM " . self::TABLE . " si
             LEFT JOIN produtos p ON p.id = si.produto_id
             WHERE si.servico_id = :servico_id
             ORDER BY si.created_at ASC, si.id ASC"
        );
        $stmt->execute([':servico_id' => $servicoId]);

        return array_map(
            static fn(array $row): ServicoItem => ServicoItem::fromArray($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function deleteByServicoId(string $servicoId): bool
    {
        if (!$this->hasTable()) {
            return true;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE servico_id = :servico_id');
        $stmt->execute([':servico_id' => $servicoId]);
        return $stmt->rowCount() >= 0;
    }

    public function hasTable(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'servico_itens'
            LIMIT 1
        ");
        $stmt->execute();

        return $this->tableExists = (bool)$stmt->fetchColumn();
    }
}
