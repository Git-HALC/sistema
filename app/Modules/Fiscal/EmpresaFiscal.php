<?php

namespace App\Modules\Fiscal;

/**
 * Entidade de configuração fiscal da empresa emissora.
 */
class EmpresaFiscal
{
    public string $cnpj = '';
    public string $nome = '';
    public ?string $ie = null;
    public ?string $logradouro = null;
    public ?string $numero = null;
    public ?string $complemento = null;
    public ?string $bairro = null;
    public ?string $cidade = null;
    public ?string $uf = null;
    public ?string $cep = null;
    public ?string $codigo_municipio = null;
    public ?string $regime_tributario = null;
    public ?string $ambiente_nfe = null;
    public ?string $serie_nfe = null;
    public int $proximo_numero_nfe = 1;
    public ?string $certificado_path = null;
    public ?string $certificado_senha = null;

    /**
     * Hidrata a entidade com base em uma linha da tabela empresa_local.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $empresa = new self();
        $empresa->cnpj = (string)($row['cnpj'] ?? '');
        $empresa->nome = (string)($row['nome'] ?? '');
        $empresa->ie = isset($row['inscricao_estadual']) && $row['inscricao_estadual'] !== '' ? (string)$row['inscricao_estadual'] : null;
        $empresa->logradouro = isset($row['logradouro']) ? (string)$row['logradouro'] : null;
        $empresa->numero = isset($row['numero']) ? (string)$row['numero'] : null;
        $empresa->complemento = isset($row['complemento']) ? (string)$row['complemento'] : null;
        $empresa->bairro = isset($row['bairro']) ? (string)$row['bairro'] : null;
        $empresa->cidade = isset($row['cidade']) ? (string)$row['cidade'] : null;
        $empresa->uf = isset($row['uf']) ? (string)$row['uf'] : null;
        $empresa->cep = isset($row['cep']) ? (string)$row['cep'] : null;
        $empresa->codigo_municipio = isset($row['codigo_municipio']) ? (string)$row['codigo_municipio'] : null;
        $empresa->regime_tributario = isset($row['regime_tributario']) ? (string)$row['regime_tributario'] : null;
        $empresa->ambiente_nfe = isset($row['ambiente_nfe']) ? (string)$row['ambiente_nfe'] : null;
        $empresa->serie_nfe = isset($row['serie_nfe']) ? (string)$row['serie_nfe'] : null;
        $empresa->proximo_numero_nfe = (int)($row['proximo_numero_nfe'] ?? 1);
        $empresa->certificado_path = isset($row['certificado_path']) ? (string)$row['certificado_path'] : null;
        $empresa->certificado_senha = isset($row['certificado_senha']) ? (string)$row['certificado_senha'] : null;

        return $empresa;
    }

    /**
     * Converte a entidade em array compatível com a persistência.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cnpj' => $this->cnpj,
            'nome' => $this->nome,
            'inscricao_estadual' => $this->ie,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
            'codigo_municipio' => $this->codigo_municipio,
            'regime_tributario' => $this->regime_tributario,
            'ambiente_nfe' => $this->ambiente_nfe,
            'serie_nfe' => $this->serie_nfe,
            'proximo_numero_nfe' => $this->proximo_numero_nfe,
            'certificado_path' => $this->certificado_path,
            'certificado_senha' => $this->certificado_senha,
        ];
    }
}
