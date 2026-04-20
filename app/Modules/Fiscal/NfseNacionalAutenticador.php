<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use PDO;
use RuntimeException;

/**
 * Autentica no Emissor Nacional da NFS-e (nfse.gov.br).
 * 3 modos de autenticacao:
 *   - usuario_senha : form-auth + sessao
 *   - certificado   : mTLS com certificado A1/A3
 *   - govbr         : OAuth via conta gov.br (fluxo Authorization Code + PKCE)
 *
 * Estrutura dos endpoints (v2.01 padrao nacional):
 *   Homologacao: https://adn.nfse.gov.br/homologacao
 *   Producao:    https://adn.nfse.gov.br
 * Referencia da API oficial: https://www.gov.br/nfse/pt-br
 */
final class NfseNacionalAutenticador
{
    private PerfilTributarioRepository $repo;

    private const BASE_HOMOLOG = 'https://adn.nfse.gov.br/homologacao';
    private const BASE_PROD    = 'https://adn.nfse.gov.br';

    public function __construct(private readonly PDO $pdo)
    {
        $this->repo = new PerfilTributarioRepository($pdo);
    }

    public function autenticar(): array
    {
        $perfil = $this->repo->obterPerfil(1);
        if (!$perfil) {
            throw new RuntimeException('Perfil tributario nao configurado.');
        }
        return match ((string)($perfil['nfse_modo_auth'] ?? 'usuario_senha')) {
            'usuario_senha' => $this->autenticarUsuarioSenha($perfil),
            'certificado'   => $this->autenticarCertificado($perfil),
            'govbr'         => $this->autenticarGovbr($perfil),
            default         => throw new RuntimeException('Modo de autenticacao invalido.'),
        };
    }

    private function baseUrl(array $perfil): string
    {
        return (($perfil['ambiente_nfse'] ?? 'homologacao') === 'producao')
            ? self::BASE_PROD
            : self::BASE_HOMOLOG;
    }

    private function autenticarUsuarioSenha(array $perfil): array
    {
        $usuario = (string)($perfil['nfse_usuario'] ?? '');
        $senha = FiscalCrypto::decrypt($perfil['nfse_senha_cifrada'] ?? null);
        if ($usuario === '' || $senha === null || $senha === '') {
            throw new RuntimeException('Usuario/senha da NFS-e nao configurados.');
        }
        $url = $this->baseUrl($perfil) . '/SessaoTransmissor/autenticar';
        $resp = $this->postJson($url, [
            'usuario' => $usuario,
            'senha'   => $senha,
        ]);
        $token = $resp['token'] ?? $resp['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('Autenticacao falhou: token nao retornado. Resposta: ' . json_encode($resp));
        }
        $expira = isset($resp['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int)$resp['expires_in'])
            : date('Y-m-d H:i:s', time() + 3600);
        $this->repo->atualizarTokenNfse((int)$perfil['id'], (string)$token, $expira);
        return ['ok' => true, 'modo' => 'usuario_senha', 'token_expira_em' => $expira];
    }

    private function autenticarCertificado(array $perfil): array
    {
        $path = (string)($perfil['nfse_certificado_path'] ?? '');
        $senha = FiscalCrypto::decrypt($perfil['nfse_certificado_senha_cifrada'] ?? null);
        if ($path === '' || !file_exists($path)) {
            throw new RuntimeException('Certificado A1 nao encontrado: ' . $path);
        }
        $url = $this->baseUrl($perfil) . '/SessaoTransmissor/autenticar-certificado';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSLCERT => $path,
            CURLOPT_SSLCERTPASSWD => $senha,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException('Auth via certificado falhou (HTTP ' . $status . '): ' . ($err ?: substr((string)$body, 0, 200)));
        }
        $resp = json_decode((string)$body, true) ?: [];
        $token = $resp['token'] ?? $resp['access_token'] ?? null;
        if (!$token) {
            throw new RuntimeException('Certificado aceito mas token nao veio. Payload: ' . substr((string)$body, 0, 200));
        }
        $expira = date('Y-m-d H:i:s', time() + (int)($resp['expires_in'] ?? 3600));
        $this->repo->atualizarTokenNfse((int)$perfil['id'], (string)$token, $expira);
        return ['ok' => true, 'modo' => 'certificado', 'token_expira_em' => $expira];
    }

    private function autenticarGovbr(array $perfil): array
    {
        // Fluxo Authorization Code + PKCE: iniciado na UI. Aqui apenas
        // valida/refresh do token ja armazenado.
        $token = (string)($perfil['nfse_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Nenhum token gov.br armazenado. Acesse a autorizacao OAuth em /fiscal/nfse/govbr/authorize');
        }
        $expira = (string)($perfil['nfse_token_expira_em'] ?? '');
        if ($expira !== '' && strtotime($expira) < time()) {
            throw new RuntimeException('Token gov.br expirado. Refaca a autorizacao OAuth.');
        }
        return ['ok' => true, 'modo' => 'govbr', 'token_expira_em' => $expira];
    }

    public function tokenValidoOuRenovar(): string
    {
        $perfil = $this->repo->obterPerfil(1);
        if (!$perfil) throw new RuntimeException('Perfil tributario nao configurado.');
        $expira = (string)($perfil['nfse_token_expira_em'] ?? '');
        $valido = $expira !== '' && strtotime($expira) > (time() + 60);
        if ($valido && !empty($perfil['nfse_token'])) {
            return (string)$perfil['nfse_token'];
        }
        $this->autenticar();
        $perfil = $this->repo->obterPerfil(1);
        return (string)($perfil['nfse_token'] ?? '');
    }

    private function postJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException('Falha cURL NFS-e Nacional: ' . $err);
        }
        if ($status >= 400) {
            throw new RuntimeException('HTTP ' . $status . ' do NFS-e Nacional: ' . substr((string)$body, 0, 300));
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida do NFS-e Nacional: ' . substr((string)$body, 0, 200));
        }
        return $data;
    }
}
