<?php
declare(strict_types=1);

namespace App\Modules\PDV;

final class PdvVenda
{
    public ?int $id = null;
    public int $caixa_id = 0;
    public int $usuario_id = 0;
    public ?int $cliente_id = null;
    public ?int $numero = null;
    public string $status = 'faturado';
    public ?int $forma_pagamento_id = null;
    public ?string $desconto_tipo = null;
    public float $desconto_valor = 0.0;
    public float $valor_total = 0.0;
    public ?string $observacoes = null;
    public string $origem = 'rapida';
    public bool $protegido = true;
    public ?string $created_at = null;

    /** @var array<int, array<string, mixed>> */
    public array $itens = [];

    public static function fromArray(array $row): self
    {
        $v = new self();
        $v->id = isset($row['id']) ? (int)$row['id'] : null;
        $v->caixa_id = (int)($row['caixa_id'] ?? 0);
        $v->usuario_id = (int)($row['usuario_id'] ?? 0);
        $v->cliente_id = isset($row['cliente_id']) && $row['cliente_id'] !== '' ? (int)$row['cliente_id'] : null;
        $v->numero = isset($row['numero']) ? (int)$row['numero'] : null;
        $v->status = (string)($row['status'] ?? 'faturado');
        $v->forma_pagamento_id = isset($row['forma_pagamento_id']) && $row['forma_pagamento_id'] !== ''
            ? (int)$row['forma_pagamento_id'] : null;
        $v->desconto_tipo = isset($row['desconto_tipo']) && $row['desconto_tipo'] !== '' ? (string)$row['desconto_tipo'] : null;
        $v->desconto_valor = (float)($row['desconto_valor'] ?? 0);
        $v->valor_total = (float)($row['valor_total'] ?? 0);
        $v->observacoes = isset($row['observacoes']) && $row['observacoes'] !== '' ? (string)$row['observacoes'] : null;
        $v->origem = (string)($row['origem'] ?? 'rapida');
        $v->protegido = !empty($row['protegido']);
        $v->created_at = $row['created_at'] ?? null;
        return $v;
    }
}
