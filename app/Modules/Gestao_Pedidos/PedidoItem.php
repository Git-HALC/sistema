<?php

namespace App\Modules\Gestao_Pedidos;

class PedidoItem
{
    public string $id = '';
    public string $pedido_id = '';
    public ?string $produto_id = null;
    public string $nome_produto = '';
    public float $quantidade = 0.0;
    public float $valor_unitario = 0.0;
    public float $valor_total_item = 0.0;
    public ?string $observacoes = null;
    public string $created_at = '';
    public string $updated_at = '';

    public array $fillable = [
        'id',
        'pedido_id',
        'produto_id',
        'nome_produto',
        'quantidade',
        'valor_unitario',
        'valor_total_item',
        'observacoes',
    ];

    public array $casts = [
        'quantidade' => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total_item' => 'decimal:4',
    ];

    public string $keyType = 'string';
    public bool $incrementing = false;

    public static function fromArray(array $row): self
    {
        $i = new self();
        $i->id = (string)($row['id'] ?? '');
        $i->pedido_id = (string)($row['pedido_id'] ?? '');
        $i->produto_id = isset($row['produto_id']) && $row['produto_id'] !== '' ? (string)$row['produto_id'] : null;
        $i->nome_produto = (string)($row['nome_produto'] ?? '');
        $i->quantidade = (float)($row['quantidade'] ?? 0);
        $i->valor_unitario = (float)($row['valor_unitario'] ?? 0);
        $i->valor_total_item = (float)($row['valor_total_item'] ?? 0);
        $i->observacoes = isset($row['observacoes']) ? (string)$row['observacoes'] : null;
        $i->created_at = (string)($row['created_at'] ?? '');
        $i->updated_at = (string)($row['updated_at'] ?? '');

        return $i;
    }

    public function calcularTotal(): float
    {
        $this->valor_total_item = round($this->quantidade * $this->valor_unitario, 4);
        return $this->valor_total_item;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'produto_id' => $this->produto_id,
            'nome_produto' => $this->nome_produto,
            'quantidade' => $this->quantidade,
            'valor_unitario' => $this->valor_unitario,
            'valor_total_item' => $this->valor_total_item,
            'observacoes' => $this->observacoes,
        ];
    }
}
