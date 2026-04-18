<?php

namespace App\Modules\Servicos;

class ServicoCatalogo
{
    public ?int $id = null;
    public string $nome = '';
    public ?string $descricao = null;
    public float $valor_base = 0.0;
    public bool $ativo = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromArray(array $row): self
    {
        $s = new self();
        $s->id = isset($row['id']) && $row['id'] !== '' ? (int)$row['id'] : null;
        $s->nome = (string)($row['nome'] ?? '');
        $s->descricao = isset($row['descricao']) && $row['descricao'] !== '' ? (string)$row['descricao'] : null;
        $s->valor_base = (float)($row['valor_base'] ?? 0);
        $s->ativo = !empty($row['ativo']);
        $s->created_at = $row['created_at'] ?? null;
        $s->updated_at = $row['updated_at'] ?? null;
        return $s;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'valor_base' => $this->valor_base,
            'ativo' => $this->ativo,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
