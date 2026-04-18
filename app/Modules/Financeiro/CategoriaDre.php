<?php

namespace App\Modules\Financeiro;

class CategoriaDre
{
    public int    $id        = 0;
    public string $nome      = '';
    public string $tipo      = 'Receita'; // Receita|Despesa
    public ?string $descricao = null;
    public int    $ordem     = 0;
    public bool   $ativo     = true;

    public static function fromArray(array $row): self
    {
        $c          = new self();
        $c->id      = (int)   ($row['id']        ?? 0);
        $c->nome    = (string)($row['nome']       ?? '');
        $c->tipo    = (string)($row['tipo']       ?? 'Receita');
        $c->descricao =        $row['descricao']  ?? null;
        $c->ordem   = (int)   ($row['ordem']      ?? 0);
        $c->ativo   = (bool)  ($row['ativo']      ?? true);
        return $c;
    }
}
