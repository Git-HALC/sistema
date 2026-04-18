<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

final class NotaFiscalServico
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ENVIADA = 'enviada';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_ERRO = 'erro';

    public ?int $id = null;
    public string $servico_id = '';
    public ?string $numero_nfse = null;
    public int $numero_rps = 0;
    public string $codigo_verificacao = '';
    public string $xml_enviado = '';
    public string $xml_retorno = '';
    public string $pdf_path = '';
    public string $status = self::STATUS_PENDENTE;
    public string $erro_mensagem = '';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $nota = new self();
        $nota->id = isset($row['id']) ? (int)$row['id'] : null;
        $nota->servico_id = trim((string)($row['servico_id'] ?? ''));
        $nota->numero_nfse = self::nullableString($row['numero_nfse'] ?? null);
        $nota->numero_rps = (int)($row['numero_rps'] ?? 0);
        $nota->codigo_verificacao = trim((string)($row['codigo_verificacao'] ?? ''));
        $nota->xml_enviado = trim((string)($row['xml_enviado'] ?? ''));
        $nota->xml_retorno = trim((string)($row['xml_retorno'] ?? ''));
        $nota->pdf_path = trim((string)($row['pdf_path'] ?? ''));
        $nota->status = trim((string)($row['status'] ?? self::STATUS_PENDENTE)) ?: self::STATUS_PENDENTE;
        $nota->erro_mensagem = trim((string)($row['erro_mensagem'] ?? ''));
        $nota->created_at = self::nullableString($row['created_at'] ?? null);
        $nota->updated_at = self::nullableString($row['updated_at'] ?? null);

        return $nota;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'servico_id' => $this->servico_id,
            'numero_nfse' => $this->numero_nfse,
            'numero_rps' => $this->numero_rps,
            'codigo_verificacao' => $this->codigo_verificacao,
            'xml_enviado' => $this->xml_enviado,
            'xml_retorno' => $this->xml_retorno,
            'pdf_path' => $this->pdf_path,
            'status' => $this->status,
            'erro_mensagem' => $this->erro_mensagem,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }
}
