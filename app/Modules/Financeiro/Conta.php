<?php

namespace App\Modules\Financeiro;

class Conta
{
    public int    $id            = 0;
    public string $nome          = '';
    public string $tipo          = 'Banco';
    public ?string $banco        = null;
    public ?string $agencia      = null;
    public ?string $numeroConta  = null;
    public float  $saldoInicial  = 0.0;
    public float  $saldoAtual    = 0.0;
    public ?string $dataSaldoInicial = null;
    public bool   $ativo         = true;

    public static function fromArray(array $row): self
    {
        $c               = new self();
        $c->id           = (int)   ($row['id']             ?? 0);
        $c->nome         = (string)($row['nome']           ?? '');
        $c->tipo         = (string)($row['tipo']           ?? 'Banco');
        $c->banco        =          $row['banco']          ?? null;
        $c->agencia      =          $row['agencia']        ?? null;
        $c->numeroConta  =          $row['numero_conta']   ?? null;
        $c->saldoInicial = (float)  ($row['saldo_inicial'] ?? 0);
        $c->saldoAtual   = (float)  ($row['saldo_atual']   ?? 0);
        $c->dataSaldoInicial = isset($row['created_at']) ? date('Y-m-d', strtotime((string)$row['created_at'])) : null;
        $c->ativo        = (bool)   ($row['ativo']         ?? true);
        return $c;
    }
}
