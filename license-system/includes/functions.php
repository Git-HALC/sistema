<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function gerarChaveLicenca(string $cnpj): string
{
    $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';
    return hash('sha256', $cnpj . time() . SECRET_KEY);
}

function validarCNPJ(string $cnpj): bool
{
    $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';

    if (strlen($cnpj) !== 14) {
        return false;
    }

    if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $base = substr($cnpj, 0, 12);
    $digitos = substr($cnpj, 12, 2);

    $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $soma1 = 0;
    for ($i = 0; $i < 12; $i++) {
        $soma1 += ((int)$base[$i]) * $pesos1[$i];
    }
    $resto1 = $soma1 % 11;
    $dv1 = $resto1 < 2 ? 0 : 11 - $resto1;

    $baseComDv1 = $base . $dv1;
    $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $soma2 = 0;
    for ($i = 0; $i < 13; $i++) {
        $soma2 += ((int)$baseComDv1[$i]) * $pesos2[$i];
    }
    $resto2 = $soma2 % 11;
    $dv2 = $resto2 < 2 ? 0 : 11 - $resto2;

    return $digitos === (string)$dv1 . (string)$dv2;
}

function calcularLicencaFim(string $tipo): string
{
    $tipo = strtolower(trim($tipo));
    $inicio = new DateTimeImmutable('today');

    return match ($tipo) {
        'mensal' => $inicio->modify('+30 days')->format('Y-m-d'),
        'anual'  => $inicio->modify('+365 days')->format('Y-m-d'),
        'trial'  => $inicio->modify('+15 days')->format('Y-m-d'),
        default  => $inicio->modify('+30 days')->format('Y-m-d'),
    };
}

function diasRestantes(string $fim): int
{
    $timestampFim = strtotime($fim);
    if ($timestampFim === false) {
        return 0;
    }

    $dias = (int)floor(($timestampFim - time()) / 86400);
    return max(0, $dias);
}

function sanitizeDatabaseName(string $databaseName): ?string
{
    $databaseName = strtolower(trim($databaseName));
    $databaseName = str_replace('-', '_', $databaseName);

    if (!preg_match('/^[a-z][a-z0-9_]{2,62}$/', $databaseName)) {
        return null;
    }

    return $databaseName;
}

function resolveClientLoginBaseUrl(): string
{
    $configured = defined('CLIENT_LOGIN_BASE_URL') ? trim((string)CLIENT_LOGIN_BASE_URL) : '';
    if ($configured !== '') {
        $configured = rtrim($configured, '/');
        if (str_ends_with(strtolower($configured), '/login.php')) {
            $configured = substr($configured, 0, -10);
        }
        return $configured;
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return 'http://localhost/sistema_dm/public';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = '/sistema_dm';
    $needle = '/license-system/';
    $pos = strpos($scriptName, $needle);

    if ($pos !== false) {
        $detected = substr($scriptName, 0, $pos);
        if ($detected !== '') {
            $basePath = $detected;
        }
    }

    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath . '/public';
}

function gerarSlugEmpresa(string $nomeEmpresa): string
{
    $nomeEmpresa = trim($nomeEmpresa);
    if ($nomeEmpresa === '') {
        return 'empresa';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nomeEmpresa);
    if (is_string($ascii) && $ascii !== '') {
        $nomeEmpresa = $ascii;
    }

    $empresaSlug = strtolower($nomeEmpresa);
    $empresaSlug = preg_replace('/[^a-z0-9]+/', '-', $empresaSlug) ?? '';
    $empresaSlug = trim($empresaSlug, '-');

    return $empresaSlug !== '' ? $empresaSlug : 'empresa';
}

function gerarUrlAcessoEmpresa(string $nomeEmpresa): string
{
    $empresaSlug = gerarSlugEmpresa($nomeEmpresa);
    $baseUrl = resolveClientLoginBaseUrl();
    return rtrim($baseUrl, '/') . '/' . rawurlencode($empresaSlug) . '/login.php';
}

function formatarCNPJ(string $cnpj): string
{
    $cnpj = preg_replace('/\D+/', '', $cnpj) ?? '';
    if (strlen($cnpj) !== 14) {
        return $cnpj;
    }

    return substr($cnpj, 0, 2) . '.' .
        substr($cnpj, 2, 3) . '.' .
        substr($cnpj, 5, 3) . '/' .
        substr($cnpj, 8, 4) . '-' .
        substr($cnpj, 12, 2);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
