<?php

namespace App\Modules\Gestao_Pedidos;

use DateTime;

class Pedido
{
    public const STATUS_RASCUNHO    = 'RASCUNHO';
    public const STATUS_PENDENTE    = 'PENDENTE';
    public const STATUS_EM_PROCESSO = 'EM_PROCESSO';
    public const STATUS_APROVADO    = 'APROVADO';
    public const STATUS_FATURADO    = 'FATURADO';
    public const STATUS_CANCELADO   = 'CANCELADO';
    public const STATUS_CONCLUIDO   = 'CONCLUIDO';

    public const STATUS_VALIDOS = [
        self::STATUS_RASCUNHO,
        self::STATUS_PENDENTE,
        self::STATUS_EM_PROCESSO,
        self::STATUS_APROVADO,
        self::STATUS_FATURADO,
        self::STATUS_CANCELADO,
        self::STATUS_CONCLUIDO,
    ];

    public const STATUS_LABELS = [
        self::STATUS_RASCUNHO => 'Rascunho',
        self::STATUS_PENDENTE => 'Pendente',
        self::STATUS_EM_PROCESSO => 'Em Processo',
        self::STATUS_APROVADO => 'Aprovado',
        self::STATUS_FATURADO => 'Faturado',
        self::STATUS_CANCELADO => 'Cancelado',
        self::STATUS_CONCLUIDO => 'Concluído',
    ];

    public const STATUS_BADGES = [
        self::STATUS_RASCUNHO => 'bg-secondary',
        self::STATUS_PENDENTE => 'bg-warning',
        self::STATUS_EM_PROCESSO => 'bg-info',
        self::STATUS_APROVADO => 'bg-success',
        self::STATUS_FATURADO => 'bg-primary',
        self::STATUS_CANCELADO => 'bg-danger',
        self::STATUS_CONCLUIDO => 'bg-dark',
    ];

    public string $id = '';
    public ?int $numero = null;
    public ?string $cliente_id = null;
    public ?string $orcamento_id = null;
    public string $data_pedido = '';
    public string $status = self::STATUS_PENDENTE;
    public float $valor_total = 0.0;
    public ?string $desconto_tipo = null;
    public float $desconto_valor = 0.0;
    public ?string $observacoes = null;
    public ?string $data_faturamento = null;
    public ?string $data_entrega_prevista = null;
    public ?string $data_entrega_realizada = null;
    public bool $ativo = true;
    public string $created_at = '';
    public string $updated_at = '';

    public array $fillable = [
        'id',
        'numero',
        'cliente_id',
        'orcamento_id',
        'data_pedido',
        'status',
        'valor_total',
        'desconto_tipo',
        'desconto_valor',
        'observacoes',
        'data_faturamento',
        'data_entrega_prevista',
        'data_entrega_realizada',
        'ativo',
    ];

    public array $casts = [
        'data_pedido' => 'datetime',
        'data_faturamento' => 'datetime',
        'data_entrega_prevista' => 'date',
        'data_entrega_realizada' => 'date',
        'valor_total' => 'decimal:4',
        'desconto_valor' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public string $keyType = 'string';
    public bool $incrementing = false;

    /** @var PedidoItem[] */
    public array $itens = [];
    public ?array $cliente = null;
    public ?array $orcamento = null;

    public static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (string)($row['id'] ?? '');
        $p->numero = isset($row['numero']) ? (int)$row['numero'] : null;
        $p->cliente_id = isset($row['cliente_id']) && $row['cliente_id'] !== '' ? (string)$row['cliente_id'] : null;
        $p->orcamento_id = isset($row['orcamento_id']) && $row['orcamento_id'] !== '' ? (string)$row['orcamento_id'] : null;
        $p->data_pedido = (string)($row['data_pedido'] ?? '');
        $p->status = strtoupper((string)($row['status'] ?? self::STATUS_PENDENTE));
        $p->valor_total = (float)($row['valor_total'] ?? 0);
        $p->desconto_tipo = isset($row['desconto_tipo']) && $row['desconto_tipo'] !== '' ? strtoupper((string)$row['desconto_tipo']) : null;
        $p->desconto_valor = (float)($row['desconto_valor'] ?? 0);
        $p->observacoes = isset($row['observacoes']) ? (string)$row['observacoes'] : null;
        $p->data_faturamento = isset($row['data_faturamento']) && $row['data_faturamento'] !== '' ? (string)$row['data_faturamento'] : null;
        $p->data_entrega_prevista = isset($row['data_entrega_prevista']) && $row['data_entrega_prevista'] !== '' ? (string)$row['data_entrega_prevista'] : null;
        $p->data_entrega_realizada = isset($row['data_entrega_realizada']) && $row['data_entrega_realizada'] !== '' ? (string)$row['data_entrega_realizada'] : null;
        $p->ativo = filter_var($row['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $p->created_at = (string)($row['created_at'] ?? '');
        $p->updated_at = (string)($row['updated_at'] ?? '');

        return $p;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'cliente_id' => $this->cliente_id,
            'orcamento_id' => $this->orcamento_id,
            'data_pedido' => $this->data_pedido,
            'status' => $this->status,
            'valor_total' => $this->valor_total,
            'desconto_tipo' => $this->desconto_tipo,
            'desconto_valor' => $this->desconto_valor,
            'observacoes' => $this->observacoes,
            'data_faturamento' => $this->data_faturamento,
            'data_entrega_prevista' => $this->data_entrega_prevista,
            'data_entrega_realizada' => $this->data_entrega_realizada,
            'ativo' => $this->ativo,
        ];
    }

    public function dataPedidoFormatada(): string
    {
        if ($this->data_pedido === '') {
            return '';
        }

        try {
            return (new DateTime($this->data_pedido))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $this->data_pedido;
        }
    }

    public function valorTotalFormatado(): string
    {
        return 'R$ ' . number_format($this->valor_total, 2, ',', '.');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }
}
