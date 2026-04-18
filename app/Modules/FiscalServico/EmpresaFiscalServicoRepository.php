<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

use PDO;
use RuntimeException;

final class EmpresaFiscalServicoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscarConfiguracao(): ?EmpresaFiscalServico
    {
        $stmt = $this->pdo->prepare('SELECT * FROM empresa_fiscal_servico ORDER BY id ASC LIMIT 1');
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $config = EmpresaFiscalServico::fromArray($row);
        $config->usuario_webservice = $this->resolverCredencial(
            'NFSE_WEBSERVICE_USER',
            $config->usuario_webservice
        );
        $config->senha_webservice = $this->resolverCredencial(
            'NFSE_WEBSERVICE_PASSWORD',
            $config->senha_webservice
        );

        return $config;
    }

    public function salvarConfiguracao(EmpresaFiscalServico $config): bool
    {
        $existing = $this->buscarConfiguracao();

        $payload = [
            ':cnpj' => $config->cnpj,
            ':razao_social' => $config->razao_social,
            ':inscricao_municipal' => $config->inscricao_municipal,
            ':codigo_municipio_ibge' => $config->codigo_municipio_ibge,
            ':aliquota_iss_padrao' => $config->aliquota_iss_padrao,
            ':url_webservice_homologacao' => $config->url_webservice_homologacao,
            ':url_webservice_producao' => $config->url_webservice_producao,
            ':usuario_webservice' => $this->criptografarSeNecessario($config->usuario_webservice),
            ':senha_webservice' => $this->criptografarSeNecessario($config->senha_webservice),
            ':ambiente' => $config->ambiente,
        ];

        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE empresa_fiscal_servico
                 SET cnpj = :cnpj,
                     razao_social = :razao_social,
                     inscricao_municipal = :inscricao_municipal,
                     codigo_municipio_ibge = :codigo_municipio_ibge,
                     aliquota_iss_padrao = :aliquota_iss_padrao,
                     url_webservice_homologacao = :url_webservice_homologacao,
                     url_webservice_producao = :url_webservice_producao,
                     usuario_webservice = :usuario_webservice,
                     senha_webservice = :senha_webservice,
                     ambiente = :ambiente,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $payload[':id'] = $existing->id;

            return $stmt->execute($payload);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO empresa_fiscal_servico (
                cnpj, razao_social, inscricao_municipal, codigo_municipio_ibge, aliquota_iss_padrao,
                url_webservice_homologacao, url_webservice_producao, usuario_webservice, senha_webservice,
                ambiente, created_at, updated_at
            ) VALUES (
                :cnpj, :razao_social, :inscricao_municipal, :codigo_municipio_ibge, :aliquota_iss_padrao,
                :url_webservice_homologacao, :url_webservice_producao, :usuario_webservice, :senha_webservice,
                :ambiente, NOW(), NOW()
            )'
        );

        return $stmt->execute($payload);
    }

    private function resolverCredencial(string $envName, ?string $storedValue): ?string
    {
        $envValue = trim((string)getenv($envName));
        if ($envValue !== '') {
            return $envValue;
        }

        if ($storedValue === null || $storedValue === '') {
            return null;
        }

        return $this->descriptografar($storedValue);
    }

    private function criptografarSeNecessario(?string $value): ?string
    {
        $plain = trim((string)$value);
        if ($plain === '') {
            return null;
        }

        if (str_starts_with($plain, 'enc::')) {
            return $plain;
        }

        $key = $this->chaveCriptografia();
        $ivLength = (int)openssl_cipher_iv_length('aes-256-cbc');
        $iv = random_bytes($ivLength);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao criptografar credencial da NFS-e.');
        }

        return 'enc::' . base64_encode($iv . $cipher);
    }

    private function descriptografar(string $value): string
    {
        if (!str_starts_with($value, 'enc::')) {
            return $value;
        }

        $decoded = base64_decode(substr($value, 5), true);
        if ($decoded === false) {
            throw new RuntimeException('Credencial criptografada da NFS-e esta invalida.');
        }

        $ivLength = (int)openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $ivLength);
        $cipher = substr($decoded, $ivLength);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $this->chaveCriptografia(), OPENSSL_RAW_DATA, $iv);

        if ($plain === false) {
            throw new RuntimeException('Falha ao descriptografar credencial da NFS-e.');
        }

        return $plain;
    }

    private function chaveCriptografia(): string
    {
        $key = trim((string)(getenv('NFSE_CREDENTIAL_KEY') ?: getenv('APP_KEY')));
        if ($key === '') {
            throw new RuntimeException('Defina NFSE_CREDENTIAL_KEY ou APP_KEY para criptografar credenciais da NFS-e.');
        }

        return hash('sha256', $key, true);
    }
}
