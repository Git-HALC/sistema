<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!function_exists('startAdminSession')) {
    function resolveAdminSessionPath(): string
    {
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $needle = '/license-system/';
        $pos = strpos($scriptName, $needle);

        if ($pos === false) {
            return '/license-system';
        }

        $path = substr($scriptName, 0, $pos + strlen('/license-system'));
        return $path !== '' ? $path : '/license-system';
    }

    function startAdminSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name(ADMIN_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => ADMIN_SESSION_TTL,
            'path' => resolveAdminSessionPath(),
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

if (!function_exists('adminBasePath')) {
    function adminBasePath(): string
    {
        return rtrim(resolveAdminSessionPath(), '/');
    }
}

if (!function_exists('adminUrl')) {
    function adminUrl(string $path = ''): string
    {
        $base = adminBasePath();
        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('isAdminAuthenticated')) {
    function isAdminAuthenticated(): bool
    {
        startAdminSession();
        return isset($_SESSION['admin_id'], $_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
}

if (!function_exists('requireAdminAuth')) {
    function requireAdminAuth(): void
    {
        if (!isAdminAuthenticated()) {
            header('Location: ' . adminUrl('admin/login.php'));
            exit;
        }
    }
}

if (!function_exists('loginAdmin')) {
    function loginAdmin(array $admin): void
    {
        startAdminSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)($admin['id'] ?? 0);
        $_SESSION['admin_nome'] = (string)($admin['nome'] ?? '');
        $_SESSION['admin_email'] = (string)($admin['email'] ?? '');
        $_SESSION['admin_logged_in'] = true;
    }
}

if (!function_exists('logoutAdmin')) {
    function logoutAdmin(): void
    {
        startAdminSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }

        session_destroy();
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        startAdminSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool
    {
        startAdminSession();
        if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('currentAdminId')) {
    function currentAdminId(): ?int
    {
        return isAdminAuthenticated() ? (int)$_SESSION['admin_id'] : null;
    }
}

startAdminSession();
