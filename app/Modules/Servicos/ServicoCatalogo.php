<?php

namespace App\Modules\Servicos;

class ServicoCatalogo
{
    public int $id = 0;
    public string $nome = '';
    public ?string $descricao = null;
    public float $valorBase = 0.0;
    public bool $ativo = true;

    public static function fromArray(array $row): self
    {
        $item = new self();
        $item->id = (int)($row['id'] ?? 0);
        $item->nome = (string)($row['nome'] ?? '');
        $item->descricao = isset($row['descricao']) && $row['descricao'] !== '' ? (string)$row['descricao'] : null;
        $item->valorBase = (float)($row['valor_base'] ?? 0);
        $item->ativo = filter_var($row['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        return $item;
    }
}
