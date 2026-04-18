<?php

namespace App\Modules\Servicos;

class ServicoItem
{
    public string $id = '';
    public string $servicoId = '';
    public ?int $produtoId = null;
    public string $nomeProduto = '';
    public float $quantidade = 0.0;
    public float $valorUnitario = 0.0;
    public float $valorTotalItem = 0.0;
    public ?string $observacoes = null;
    public string $createdAt = '';
    public string $updatedAt = '';

    public static function fromArray(array $row): self
    {
        $item = new self();
        $item->id = (string)($row['id'] ?? '');
        $item->servicoId = (string)($row['servico_id'] ?? '');
        $item->produtoId = isset($row['produto_id']) && $row['produto_id'] !== '' ? (int)$row['produto_id'] : null;
        $item->nomeProduto = (string)($row['nome_produto'] ?? '');
        $item->quantidade = (float)($row['quantidade'] ?? 0);
        $item->valorUnitario = (float)($row['valor_unitario'] ?? 0);
        $item->valorTotalItem = (float)($row['valor_total_item'] ?? 0);
        $item->observacoes = isset($row['observacoes']) && $row['observacoes'] !== '' ? (string)$row['observacoes'] : null;
        $item->createdAt = (string)($row['created_at'] ?? '');
        $item->updatedAt = (string)($row['updated_at'] ?? '');

        return $item;
    }

    public function calcularTotal(): float
    {
        $this->valorTotalItem = round($this->quantidade * $this->valorUnitario, 4);
        return $this->valorTotalItem;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'servico_id' => $this->servicoId,
            'produto_id' => $this->produtoId,
            'nome_produto' => $this->nomeProduto,
            'quantidade' => $this->quantidade,
            'valor_unitario' => $this->valorUnitario,
            'valor_total_item' => $this->valorTotalItem,
            'observacoes' => $this->observacoes,
        ];
    }
}
