<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class CsrfProtection
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY], $token);
    }

    public static function validateRequestOrFail(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::validate(is_string($token) ? $token : null)) {
            throw new RuntimeException('Token CSRF invalido.');
        }
    }
}
