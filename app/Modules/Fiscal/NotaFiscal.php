<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use DateInterval;
use DateTimeImmutable;

/**
 * Entidade de nota fiscal eletronica vinculada a um pedido ou servico.
 */
class NotaFiscal
{
    public const STATUS_PENDENTE = 'PENDENTE';
    public const STATUS_EMITIDA = 'EMITIDA';
    public const STATUS_AUTORIZADA = 'AUTORIZADA';
    public const STATUS_CANCELADA = 'CANCELADA';
    public const STATUS_REJEITADA = 'REJEITADA';
    public const STATUS_INUTILIZADA = 'INUTILIZADA';

    /**
     * @var string[]
     */
    public const STATUS_VALIDOS = [
        self::STATUS_PENDENTE,
        self::STATUS_EMITIDA,
        self::STATUS_AUTORIZADA,
        self::STATUS_CANCELADA,
        self::STATUS_REJEITADA,
        self::STATUS_INUTILIZADA,
    ];

    public string $id = '';
    public ?string $pedido_id = null;
    public ?string $servico_id = null;
    public int $numero_nfe = 0;
    public string $chave_acesso = '';
    public string $status = self::STATUS_PENDENTE;
    public ?string $data_emissao = null;
    public ?string $xml_nfe = null;
    public ?string $n_prot = null;
    public string $created_at = '';
    public string $updated_at = '';

    /**
     * Hidrata a entidade com base em uma linha do banco.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $nfe = new self();
        $nfe->id = (string)($row['id'] ?? '');
        $nfe->pedido_id = isset($row['pedido_id']) && $row['pedido_id'] !== '' ? (string)$row['pedido_id'] : null;
        $nfe->servico_id = isset($row['servico_id']) && $row['servico_id'] !== '' ? (string)$row['servico_id'] : null;
        $nfe->numero_nfe = (int)($row['numero_nfe'] ?? 0);
        $nfe->chave_acesso = (string)($row['chave_acesso'] ?? '');
        $nfe->status = strtoupper((string)($row['status'] ?? self::STATUS_PENDENTE));
        $nfe->data_emissao = isset($row['data_emissao']) && $row['data_emissao'] !== '' ? (string)$row['data_emissao'] : null;
        $nfe->xml_nfe = isset($row['xml_nfe']) ? (string)$row['xml_nfe'] : null;
        $nfe->n_prot = isset($row['n_prot']) && $row['n_prot'] !== '' ? (string)$row['n_prot'] : null;
        $nfe->created_at = (string)($row['created_at'] ?? '');
        $nfe->updated_at = (string)($row['updated_at'] ?? '');

        return $nfe;
    }

    /**
     * Converte a entidade em array serializavel.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pedido_id' => $this->pedido_id,
            'servico_id' => $this->servico_id,
            'numero_nfe' => $this->numero_nfe,
            'chave_acesso' => $this->chave_acesso,
            'status' => $this->status,
            'data_emissao' => $this->data_emissao,
            'xml_nfe' => $this->xml_nfe,
            'n_prot' => $this->n_prot,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Indica se a NF-e esta autorizada.
     */
    public function isAutorizada(): bool
    {
        return $this->status === self::STATUS_AUTORIZADA;
    }

    public function origemTipo(): string
    {
        return $this->servico_id !== null && $this->servico_id !== '' ? 'SERVICO' : 'PEDIDO';
    }

    /**
     * Indica se a NF-e ainda pode ser cancelada dentro da janela de 24 horas.
     */
    public function isCancelavel(): bool
    {
        if (!$this->isAutorizada() || $this->data_emissao === null) {
            return false;
        }

        try {
            $limite = (new DateTimeImmutable($this->data_emissao))->add(new DateInterval('PT24H'));
            return $limite >= new DateTimeImmutable();
        } catch (\Throwable) {
            return false;
        }
    }
}
