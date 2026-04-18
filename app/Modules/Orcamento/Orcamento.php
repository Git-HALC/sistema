<?php

namespace App\Modules\Orcamento;

use DateTime;

/**
 * Model: Orcamento
 *
 * Representa um orçamento no sistema.
 * Contém apenas estado e predicados de ciclo de vida.
 * Toda lógica de persistência está no OrcamentoRepository.
 * Toda lógica de negócio está no OrcamentoService.
 */
class Orcamento
{
    public const STATUS_RASCUNHO   = 'RASCUNHO';
    public const STATUS_ENVIADO    = 'ENVIADO';
    public const STATUS_APROVADO   = 'APROVADO';
    public const STATUS_REJEITADO  = 'REJEITADO';
    public const STATUS_EXPIRADO   = 'EXPIRADO';
    public const STATUS_CANCELADO  = 'CANCELADO';

    public const STATUS_LABELS = [
        self::STATUS_RASCUNHO   => 'Rascunho',
        self::STATUS_ENVIADO    => 'Enviado',
        self::STATUS_APROVADO   => 'Aprovado',
        self::STATUS_REJEITADO  => 'Rejeitado',
        self::STATUS_EXPIRADO   => 'Expirado',
        self::STATUS_CANCELADO  => 'Cancelado',
    ];

    public const STATUS_BADGES = [
        self::STATUS_RASCUNHO   => 'bg-secondary',
        self::STATUS_ENVIADO    => 'bg-info',
        self::STATUS_APROVADO   => 'bg-success',
        self::STATUS_REJEITADO  => 'bg-danger',
        self::STATUS_EXPIRADO   => 'bg-warning',
        self::STATUS_CANCELADO  => 'bg-dark',
    ];

    // -------------------------------------------------------------------------
    // Propriedades
    // -------------------------------------------------------------------------

    public int     $id              = 0;
    public ?string $codigo          = null;        // GENERATED: ORC-000001
    public ?int    $clienteId       = null;
    public string  $status          = self::STATUS_RASCUNHO;
    public string  $dataEmissao     = '';
    public ?string $dataValidade    = null;
    public ?string $observacoes     = null;
    public float   $descontoPercentual = 0.0;
    public float   $valorTotal      = 0.0;
    public ?int    $contaReceberId  = null;
    public ?int    $vendaId         = null;        // reservado para módulo Vendas
    public ?int    $usuarioId       = null;
    public string  $createdAt       = '';
    public string  $updatedAt       = '';

    // Campos extras para exibição (JOINs)
    public ?string $clienteNome     = null;
    public ?string $clienteTelefone = null;
    public ?string $usuarioNome     = null;

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function fromArray(array $row): self
    {
        $o                = new self();
        $o->id            = (int)     $row['id'];
        $o->codigo        = $row['codigo']          ?? null;
        $o->clienteId     = isset($row['cliente_id'])      ? (int) $row['cliente_id']      : null;
        $o->status        = $row['status']          ?? self::STATUS_RASCUNHO;
        $o->dataEmissao   = $row['data_orcamento']    ?? '';
        $o->dataValidade  = $row['data_validade']   ?? null;
        $o->observacoes   = $row['observacoes']     ?? null;
        $o->descontoPercentual = (float)($row['desconto_percentual'] ?? 0);
        $o->valorTotal    = (float)  ($row['valor_total']     ?? 0);
        $o->contaReceberId = isset($row['conta_receber_id']) ? (int) $row['conta_receber_id'] : null;
        $o->vendaId       = isset($row['venda_id'])          ? (int) $row['venda_id']         : null;
        $o->usuarioId     = isset($row['usuario_id'])        ? (int) $row['usuario_id']        : null;
        $o->createdAt     = $row['created_at']      ?? '';
        $o->updatedAt     = $row['updated_at']      ?? '';
        $o->clienteNome   = $row['cliente_nome']    ?? null;
        $o->clienteTelefone = $row['cliente_telefone'] ?? null;
        $o->usuarioNome   = $row['usuario_nome'] ?? null;

        return $o;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'cliente_id'      => $this->clienteId,
            'status'          => $this->status,
            'data_emissao'    => $this->dataEmissao,
            'data_validade'   => $this->dataValidade,
            'observacoes'     => $this->observacoes,
            'desconto_percentual' => $this->descontoPercentual,
            'valor_total'     => $this->valorTotal,
            'conta_receber_id' => $this->contaReceberId,
            'venda_id'        => $this->vendaId,
            'usuario_id'      => $this->usuarioId,
        ];
    }

    // -------------------------------------------------------------------------
    // Predicados de ciclo de vida
    // -------------------------------------------------------------------------

    public function estaAberto(): bool    { return $this->status === self::STATUS_RASCUNHO; }
    public function estaEnviado(): bool   { return $this->status === self::STATUS_ENVIADO; }
    public function estaAprovado(): bool  { return $this->status === self::STATUS_APROVADO; }
    public function estaRejeitado(): bool { return $this->status === self::STATUS_REJEITADO; }
    public function estaExpirado(): bool  { return $this->status === self::STATUS_EXPIRADO; }
    public function estaCancelado(): bool { return $this->status === self::STATUS_CANCELADO; }

    /** Orçamento só pode ser editado enquanto estiver ABERTO. */
    public function podeSerEditado(): bool { return $this->estaAberto(); }

    /** Orçamento só pode ser excluído quando estiver ABERTO. */
    public function podeSerExcluido(): bool { return $this->estaAberto(); }

    // -------------------------------------------------------------------------
    // Helpers de exibição
    // -------------------------------------------------------------------------

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }

    public function valorTotalFormatado(): string
    {
        return 'R$ ' . number_format($this->valorTotal, 2, ',', '.');
    }

    public function dataEmissaoFormatada(): string
    {
        return $this->dataEmissao
            ? (new DateTime($this->dataEmissao))->format('d/m/Y')
            : '';
    }

    public function dataValidadeFormatada(): string
    {
        return $this->dataValidade
            ? (new DateTime($this->dataValidade))->format('d/m/Y')
            : '—';
    }
}
