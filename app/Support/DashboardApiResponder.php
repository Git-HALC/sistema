<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

class DashboardApiResponder
{
    public static function run(
        PDO $pdo,
        string $permission,
        string $method,
        callable $handler,
        bool $requireCsrf = false
    ): void {
        try {
            self::ensureSession();
            self::ensureAuthenticated();
            PermissionGate::init($pdo);
            self::ensurePermission($permission);
            self::ensureMethod($method);

            $request = self::requestData();
            if ($requireCsrf) {
                self::validateCsrf($request);
            }

            $data = $handler($pdo, $request);
            self::json($data);
        } catch (DashboardApiException $e) {
            self::error($e->getMessage(), $e->statusCode());
        } catch (Throwable $e) {
            error_log('Dashboard API error: ' . $e->getMessage());
            self::error('Erro interno ao processar a requisicao.', 500);
        }
    }

    public static function intFrom(array $source, string $key, int $default = 0): int
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return $default;
        }

        if (!is_numeric($source[$key])) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return (int)$source[$key];
    }

    public static function positiveInt(array $source, string $key, ?string $label = null): int
    {
        $value = self::intFrom($source, $key, 0);
        if ($value <= 0) {
            throw new DashboardApiException(($label ?? $key) . ' e obrigatorio.', 400);
        }

        return $value;
    }

    public static function optionalPositiveInt(array $source, string $key): ?int
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }

        $value = self::intFrom($source, $key, 0);
        if ($value <= 0) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return $value;
    }

    public static function optionalPositiveFloat(array $source, string $key): ?float
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }

        $value = self::floatValue($source[$key], $key);
        if ($value <= 0) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return $value;
    }

    public static function nonNegativeFloat(array $source, string $key, float $default = 0.0): float
    {
        if (!array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return $default;
        }

        $value = self::floatValue($source[$key], $key);
        if ($value < 0) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return $value;
    }

    public static function enum(array $source, string $key, array $allowed, string $default): string
    {
        $value = strtoupper(trim((string)($source[$key] ?? $default)));
        $normalizedAllowed = array_map(
            static fn(string $item): string => strtoupper(trim($item)),
            $allowed
        );

        if (!in_array($value, $normalizedAllowed, true)) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return $value;
    }

    public static function ymdDate(array $source, string $key, ?string $default = null): string
    {
        $value = trim((string)($source[$key] ?? $default ?? ''));
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new DashboardApiException('Data invalida para ' . $key . '.', 400);
        }

        return $value;
    }

    public static function optionalString(array $source, string $key, ?int $maxLength = null): ?string
    {
        if (!array_key_exists($key, $source) || $source[$key] === null) {
            return null;
        }

        $value = trim((string)$source[$key]);
        if ($value === '') {
            return null;
        }

        if ($maxLength !== null) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function ensureAuthenticated(): void
    {
        if (!isset($_SESSION['user_id'])) {
            throw new DashboardApiException('Acesso negado.', 401);
        }
    }

    private static function ensurePermission(string $permission): void
    {
        if (!PermissionGate::can($permission)) {
            throw new DashboardApiException('Acesso negado.', 403);
        }
    }

    private static function ensureMethod(string $method): void
    {
        $currentMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($currentMethod !== strtoupper($method)) {
            throw new DashboardApiException('Metodo nao permitido.', 405);
        }
    }

    private static function requestData(): array
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'GET') {
            return $_GET;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new DashboardApiException('JSON invalido.', 400);
            }

            return $decoded;
        }

        parse_str($raw, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    private static function validateCsrf(array $request): void
    {
        $token = self::optionalString($request, 'csrf_token');
        if ($token === null) {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            $token = is_string($headerToken) ? trim($headerToken) : null;
        }

        if ($token === null || !CsrfProtection::validate($token)) {
            throw new DashboardApiException('Token CSRF invalido.', 403);
        }
    }

    private static function floatValue(mixed $value, string $key): float
    {
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $value) === 1) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } elseif (str_contains($value, ',') && !str_contains($value, '.')) {
                $value = str_replace(',', '.', $value);
            }
        }

        if (!is_numeric($value)) {
            throw new DashboardApiException('Parametro invalido: ' . $key . '.', 400);
        }

        return (float)$value;
    }

    private static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function error(string $message, int $status): void
    {
        self::json(['erro' => $message], $status);
    }
}
