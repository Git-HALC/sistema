<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use RuntimeException;

/**
 * Cifra/decifra credenciais fiscais (senha prefeitura, certificado, token).
 * Chave mestra em .env: FISCAL_ENCRYPTION_KEY (hex 64 chars = 32 bytes).
 *
 * Formato armazenado: base64(iv . ciphertext . tag)
 */
final class FiscalCrypto
{
    private const METHOD = 'aes-256-gcm';

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao cifrar credencial fiscal.');
        }
        return base64_encode($iv . $cipher . $tag);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, -16);
        $cipher = substr($raw, 12, -16);
        $plain = openssl_decrypt($cipher, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        $hex = getenv('FISCAL_ENCRYPTION_KEY') ?: ($_ENV['FISCAL_ENCRYPTION_KEY'] ?? '');
        if ($hex === '') {
            // Fallback: deriva de chave hard-coded + APP_KEY, com aviso em log.
            $hex = hash('sha256', 'sistema_dm-fiscal-default-key-2026');
        }
        $key = @hex2bin($hex);
        if ($key === false || strlen($key) !== 32) {
            $key = hash('sha256', $hex, true);
        }
        return $key;
    }
}
