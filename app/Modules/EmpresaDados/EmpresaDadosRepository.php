<?php

namespace App\Modules\EmpresaDados;

use PDO;
use Throwable;

/**
 * Repositorio do cadastro local da empresa no banco do tenant.
 */
class EmpresaDadosRepository
{
    private ?PDO $masterPdo = null;

    /**
     * @param PDO $tenantPdo Conexao do banco do tenant.
     */
    public function __construct(private readonly PDO $tenantPdo)
    {
    }

    /**
     * Retorna o registro atual de empresa_local.
     *
     * @return array<string, mixed>|null
     */
    public function buscarEmpresaAtual(): ?array
    {
        $stmt = $this->tenantPdo->prepare(
            'SELECT
                id,
                nome,
                cnpj,
                contato,
                email,
                telefone,
                logradouro,
                numero,
                complemento,
                bairro,
                cidade,
                uf,
                cep,
                inscricao_estadual,
                inscricao_municipal,
                codigo_municipio,
                regime_tributario,
                ambiente_nfe,
                serie_nfe,
                proximo_numero_nfe,
                certificado_path,
                certificado_senha
             FROM empresa_local
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute();

        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($empresa) ? $empresa : null;
    }

    /**
     * Cria ou atualiza o cadastro da empresa local.
     *
     * @param array<string, mixed> $dados Dados normalizados.
     */
    public function salvarEmpresa(array $dados): bool
    {
        $empresa = $this->buscarEmpresaAtual();

        $payload = [
            ':nome' => $dados['nome'],
            ':cnpj' => $dados['cnpj'],
            ':contato' => $dados['contato'] ?: null,
            ':email' => $dados['email'] ?: null,
            ':telefone' => $dados['telefone'] ?: null,
            ':logradouro' => $dados['logradouro'] ?: null,
            ':numero' => $dados['numero'] ?: null,
            ':complemento' => $dados['complemento'] ?: null,
            ':bairro' => $dados['bairro'] ?: null,
            ':cidade' => $dados['cidade'] ?: null,
            ':uf' => $dados['uf'] ?: null,
            ':cep' => $dados['cep'] ?: null,
            ':inscricao_estadual' => $dados['inscricao_estadual'] ?: null,
            ':inscricao_municipal' => $dados['inscricao_municipal'] ?: null,
            ':codigo_municipio' => $dados['codigo_municipio'] ?: null,
            ':regime_tributario' => $dados['regime_tributario'],
            ':ambiente_nfe' => $dados['ambiente_nfe'],
            ':serie_nfe' => $dados['serie_nfe'],
            ':proximo_numero_nfe' => (int)$dados['proximo_numero_nfe'],
            ':certificado_path' => $dados['certificado_path'] ?: null,
            ':certificado_senha' => $dados['certificado_senha'] ?: null,
        ];

        try {
            if (!$this->tenantPdo->inTransaction()) {
                $this->tenantPdo->beginTransaction();
            }

            if ($empresa !== null && !empty($empresa['id'])) {
                $payload[':id'] = (int)$empresa['id'];

                $stmt = $this->tenantPdo->prepare(
                    'UPDATE empresa_local
                        SET nome = :nome,
                            cnpj = :cnpj,
                            contato = :contato,
                            email = :email,
                            telefone = :telefone,
                            logradouro = :logradouro,
                            numero = :numero,
                            complemento = :complemento,
                            bairro = :bairro,
                            cidade = :cidade,
                            uf = :uf,
                            cep = :cep,
                            inscricao_estadual = :inscricao_estadual,
                            inscricao_municipal = :inscricao_municipal,
                            codigo_municipio = :codigo_municipio,
                            regime_tributario = :regime_tributario,
                            ambiente_nfe = :ambiente_nfe,
                            serie_nfe = :serie_nfe,
                            proximo_numero_nfe = :proximo_numero_nfe,
                            certificado_path = :certificado_path,
                            certificado_senha = :certificado_senha,
                            updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id'
                );
            } else {
                $stmt = $this->tenantPdo->prepare(
                    'INSERT INTO empresa_local (
                        nome,
                        cnpj,
                        contato,
                        email,
                        telefone,
                        logradouro,
                        numero,
                        complemento,
                        bairro,
                        cidade,
                        uf,
                        cep,
                        inscricao_estadual,
                        inscricao_municipal,
                        codigo_municipio,
                        regime_tributario,
                        ambiente_nfe,
                        serie_nfe,
                        proximo_numero_nfe,
                        certificado_path,
                        certificado_senha,
                        created_at,
                        updated_at
                    ) VALUES (
                        :nome,
                        :cnpj,
                        :contato,
                        :email,
                        :telefone,
                        :logradouro,
                        :numero,
                        :complemento,
                        :bairro,
                        :cidade,
                        :uf,
                        :cep,
                        :inscricao_estadual,
                        :inscricao_municipal,
                        :codigo_municipio,
                        :regime_tributario,
                        :ambiente_nfe,
                        :serie_nfe,
                        :proximo_numero_nfe,
                        :certificado_path,
                        :certificado_senha,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )'
                );
            }

            $ok = $stmt->execute($payload);
            if (!$ok) {
                $this->tenantPdo->rollBack();
                return false;
            }

            $this->sincronizarEmpresaMaster($dados);
            $this->tenantPdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->tenantPdo->inTransaction()) {
                $this->tenantPdo->rollBack();
            }

            error_log('Falha ao salvar empresa_local e sincronizar com license-system: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sincroniza razao social e email no banco master do license-system.
     *
     * @param array<string, mixed> $dados
     */
    private function sincronizarEmpresaMaster(array $dados): void
    {
        $masterPdo = $this->obterMasterPdo();
        $bancoDados = $this->obterBancoTenantAtual();

        if ($bancoDados === null || $bancoDados === '') {
            throw new \RuntimeException('Banco do tenant atual nao identificado para sincronizacao.');
        }

        $stmt = $masterPdo->prepare(
            'UPDATE empresas
                SET razao_social = :razao_social,
                    email = :email,
                    updated_at = CURRENT_TIMESTAMP
              WHERE banco_dados = :banco_dados'
        );

        $stmt->execute([
            ':razao_social' => $dados['nome'],
            ':email' => $dados['email'] !== '' ? $dados['email'] : null,
            ':banco_dados' => $bancoDados,
        ]);

        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('Empresa do license-system nao encontrada para o banco ' . $bancoDados . '.');
        }
    }

    private function obterBancoTenantAtual(): ?string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $tenantDb = trim((string)($_SESSION['tenant_dbname'] ?? ''));
            if ($tenantDb !== '') {
                return $tenantDb;
            }
        }

        $stmt = $this->tenantPdo->query('SELECT current_database()');
        $databaseName = $stmt !== false ? trim((string)$stmt->fetchColumn()) : '';
        return $databaseName !== '' ? $databaseName : null;
    }

    private function obterMasterPdo(): PDO
    {
        if ($this->masterPdo instanceof PDO) {
            return $this->masterPdo;
        }

        $configPath = dirname(__DIR__, 3) . '/license-system/config/config.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('Configuracao do license-system nao encontrada.');
        }

        require_once $configPath;

        if (
            !defined('MASTER_DB_HOST')
            || !defined('MASTER_DB_PORT')
            || !defined('MASTER_DB_NAME')
            || !defined('MASTER_DB_USER')
            || !defined('MASTER_DB_PASS')
        ) {
            throw new \RuntimeException('Constantes de conexao do banco master nao definidas.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            MASTER_DB_HOST,
            MASTER_DB_PORT,
            MASTER_DB_NAME
        );

        $this->masterPdo = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->masterPdo;
    }
}
