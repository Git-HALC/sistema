<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

final class EmpresaFiscalServico
{
    public ?int $id = null;
    public string $cnpj = '';
    public string $razao_social = '';
    public string $inscricao_municipal = '';
    public string $codigo_municipio_ibge = '';
    public float $aliquota_iss_padrao = 0.0;
    public ?string $url_webservice_homologacao = null;
    public ?string $url_webservice_producao = null;
    public ?string $usuario_webservice = null;
    public ?string $senha_webservice = null;
    public string $ambiente = 'homologacao';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $config = new self();
        $config->id = isset($row['id']) ? (int)$row['id'] : null;
        $config->cnpj = trim((string)($row['cnpj'] ?? ''));
        $config->razao_social = trim((string)($row['razao_social'] ?? ''));
        $config->inscricao_municipal = trim((string)($row['inscricao_municipal'] ?? ''));
        $config->codigo_municipio_ibge = trim((string)($row['codigo_municipio_ibge'] ?? ''));
        $config->aliquota_iss_padrao = (float)($row['aliquota_iss_padrao'] ?? 0);
        $config->url_webservice_homologacao = self::nullableString($row['url_webservice_homologacao'] ?? null);
        $config->url_webservice_producao = self::nullableString($row['url_webservice_producao'] ?? null);
        $config->usuario_webservice = self::nullableString($row['usuario_webservice'] ?? null);
        $config->senha_webservice = self::nullableString($row['senha_webservice'] ?? null);
        $config->ambiente = trim((string)($row['ambiente'] ?? 'homologacao')) ?: 'homologacao';
        $config->created_at = self::nullableString($row['created_at'] ?? null);
        $config->updated_at = self::nullableString($row['updated_at'] ?? null);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cnpj' => $this->cnpj,
            'razao_social' => $this->razao_social,
            'inscricao_municipal' => $this->inscricao_municipal,
            'codigo_municipio_ibge' => $this->codigo_municipio_ibge,
            'aliquota_iss_padrao' => $this->aliquota_iss_padrao,
            'url_webservice_homologacao' => $this->url_webservice_homologacao,
            'url_webservice_producao' => $this->url_webservice_producao,
            'usuario_webservice' => $this->usuario_webservice,
            'senha_webservice' => $this->senha_webservice,
            'ambiente' => $this->ambiente,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function webserviceUrl(): ?string
    {
        return $this->ambiente === 'producao'
            ? $this->url_webservice_producao
            : $this->url_webservice_homologacao;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }
}
