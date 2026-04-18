<?php

namespace App\Modules\Financeiro;

class FormaPagamento
{
    public const TIPO_DINHEIRO = 'D';
    public const TIPO_PIX = 'PIX';
    public const TIPO_TRANSFERENCIA = 'TB';
    public const TIPO_CARTAO_CREDITO = 'CC';
    public const TIPO_CARTAO_DEBITO = 'CD';
    public const TIPO_BOLETO = 'BOL';
    public const TIPO_A_FATURAR = 'AF';

    public int $id = 0;
    public string $nome = '';
    public string $tipo = self::TIPO_DINHEIRO;
    public ?string $descricao = null;
    public ?int $adquirenteId = null;
    public float $taxa = 0.0;
    public int $prazoDias = 0;
    public ?int $contaId = null;
    public bool $ativo = true;

    public static function fromArray(array $row): self
    {
        $f = new self();
        $f->id = (int)($row['id'] ?? 0);
        $f->nome = (string)($row['nome'] ?? '');
        $f->tipo = (string)($row['tipo'] ?? self::TIPO_DINHEIRO);
        $f->descricao = $row['descricao'] ?? null;
        $f->adquirenteId = isset($row['adquirente_id']) && $row['adquirente_id'] !== null
            ? (int)$row['adquirente_id']
            : null;
        $f->taxa = (float)($row['taxa'] ?? 0);
        $f->prazoDias = (int)($row['prazo_dias'] ?? 0);
        $f->contaId = isset($row['conta_id']) && $row['conta_id'] !== null
            ? (int)$row['conta_id']
            : null;
        $f->ativo = (bool)($row['ativo'] ?? true);
        return $f;
    }
}
