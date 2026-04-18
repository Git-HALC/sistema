<?php

namespace App\Modules\Fiscal;

use PDO;

/**
 * Repositório da configuração fiscal da empresa.
 */
class EmpresaFiscalRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Retorna o único registro fiscal da empresa.
     */
    public function get(): ?EmpresaFiscal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresa_local ORDER BY id ASC LIMIT 1');
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? EmpresaFiscal::fromArray($row) : null;
    }

    /**
     * Persiste a configuração fiscal da empresa.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): bool
    {
        $existing = $this->get();

        $payload = [
            ':cnpj' => $data['cnpj'] ?? '',
            ':nome' => $data['nome'] ?? '',
            ':inscricao_estadual' => $data['ie'] ?? ($data['inscricao_estadual'] ?? null),
            ':logradouro' => $data['logradouro'] ?? null,
            ':numero' => $data['numero'] ?? null,
            ':complemento' => $data['complemento'] ?? null,
            ':bairro' => $data['bairro'] ?? null,
            ':cidade' => $data['cidade'] ?? null,
            ':uf' => $data['uf'] ?? null,
            ':cep' => $data['cep'] ?? null,
            ':codigo_municipio' => $data['codigo_municipio'] ?? null,
            ':regime_tributario' => $data['regime_tributario'] ?? '1',
            ':ambiente_nfe' => $data['ambiente_nfe'] ?? '2',
            ':serie_nfe' => $data['serie_nfe'] ?? '001',
            ':proximo_numero_nfe' => (int)($data['proximo_numero_nfe'] ?? 1),
            ':certificado_path' => $data['certificado_path'] ?? null,
            ':certificado_senha' => $data['certificado_senha'] ?? null,
        ];

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE empresa_local
                SET cnpj = :cnpj,
                    nome = :nome,
                    inscricao_estadual = :inscricao_estadual,
                    logradouro = :logradouro,
                    numero = :numero,
                    complemento = :complemento,
                    bairro = :bairro,
                    cidade = :cidade,
                    uf = :uf,
                    cep = :cep,
                    codigo_municipio = :codigo_municipio,
                    regime_tributario = :regime_tributario,
                    ambiente_nfe = :ambiente_nfe,
                    serie_nfe = :serie_nfe,
                    proximo_numero_nfe = :proximo_numero_nfe,
                    certificado_path = :certificado_path,
                    certificado_senha = :certificado_senha,
                    updated_at = NOW()
                WHERE id = (
                    SELECT id FROM empresa_local ORDER BY id ASC LIMIT 1
                )
            ");

            return $stmt->execute($payload);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO empresa_local (
                cnpj, nome, inscricao_estadual, logradouro, numero, complemento, bairro, cidade, uf, cep,
                codigo_municipio, regime_tributario, ambiente_nfe, serie_nfe, proximo_numero_nfe,
                certificado_path, certificado_senha, created_at, updated_at
            ) VALUES (
                :cnpj, :nome, :inscricao_estadual, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :cep,
                :codigo_municipio, :regime_tributario, :ambiente_nfe, :serie_nfe, :proximo_numero_nfe,
                :certificado_path, :certificado_senha, NOW(), NOW()
            )
        ");

        return $stmt->execute($payload);
    }
}
