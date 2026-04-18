<?php
declare(strict_types=1);

if (!function_exists('tenantEnsureSession')) {
    function tenantEnsureSession(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
    }
}

if (!function_exists('tenantSlugify')) {
    function tenantSlugify(string $value): string
    {
        $slug = trim($value);
        if ($slug === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        if (is_string($transliterated) && $transliterated !== '') {
            $slug = $transliterated;
        }

        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }
}

if (!function_exists('tenantBasePublicPath')) {
    function tenantBasePublicPath(): string
    {
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $marker = '/public/';
        $pos = strpos($scriptName, $marker);

        if ($pos !== false) {
            $base = substr($scriptName, 0, $pos + strlen('/public'));
        } else {
            $base = '/sistema_dm/public';
        }

        $base = '/' . ltrim($base, '/');
        return rtrim($base, '/');
    }
}

if (!function_exists('tenantBaseAppPath')) {
    function tenantBaseAppPath(): string
    {
        $basePublic = tenantBasePublicPath();
        if (str_ends_with($basePublic, '/public')) {
            return substr($basePublic, 0, -7) ?: '/';
        }

        return $basePublic;
    }
}

if (!function_exists('tenantResolveFromRequestUri')) {
    function tenantResolveFromRequestUri(): ?string
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($requestUri === '') {
            return null;
        }

        $path = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return null;
        }

        $base = tenantBasePublicPath();
        $prefix = $base . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));
        if ($relative === false || $relative === '') {
            return null;
        }

        $parts = explode('/', $relative, 2);
        $candidateRaw = rawurldecode((string)($parts[0] ?? ''));
        if ($candidateRaw === '') {
            return null;
        }

        $reserved = [
            'admin',
            'assets',
            'uploads',
            'includes',
            'login.php',
            'auth.php',
            'logout.php',
            'index.php',
            'blocked.php',
            'erro.php',
            'clientes.php',
            'landing.php',
            'orcamento_pdf_publico.php',
        ];

        if (in_array(strtolower($candidateRaw), $reserved, true)) {
            return null;
        }

        $slug = tenantSlugify($candidateRaw);
        return $slug !== '' ? $slug : null;
    }
}

if (!function_exists('tenantCurrentSlug')) {
    function tenantCurrentSlug(): ?string
    {
        $fromGet = (string)($_GET['tenant'] ?? $_GET['empresa'] ?? '');
        $candidate = $fromGet !== '' ? tenantSlugify($fromGet) : null;

        if ($candidate === null || $candidate === '') {
            $candidate = tenantResolveFromRequestUri();
        }

        if (($candidate === null || $candidate === '') && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $sessionCandidate = (string)($_SESSION['tenant_slug'] ?? '');
            $candidate = $sessionCandidate !== '' ? tenantSlugify($sessionCandidate) : null;
        }

        if ($candidate !== null && $candidate !== '' && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $sessionSlugAtual = (string)($_SESSION['tenant_slug'] ?? '');
            if ($sessionSlugAtual !== '' && tenantSlugify($sessionSlugAtual) !== $candidate) {
                unset($_SESSION['tenant_dbname']);
            }

            $_SESSION['tenant_slug'] = $candidate;
        }

        return ($candidate !== null && $candidate !== '') ? $candidate : null;
    }
}

if (!function_exists('tenantPublicPrefix')) {
    function tenantPublicPrefix(): string
    {
        $base = tenantBasePublicPath();
        $slug = tenantCurrentSlug();

        if ($slug === null) {
            return $base;
        }

        return $base . '/' . $slug;
    }
}

if (!function_exists('tenantCleanPrefix')) {
    function tenantCleanPrefix(): string
    {
        $base = tenantBaseAppPath();
        $slug = tenantCurrentSlug();

        if ($slug === null) {
            return $base;
        }

        return rtrim($base, '/') . '/' . $slug;
    }
}

if (!function_exists('tenantUrl')) {
    function tenantUrl(string $relativePath = ''): string
    {
        $prefix = tenantPublicPrefix();
        if ($relativePath === '' || $relativePath === '/') {
            return $prefix;
        }

        return $prefix . '/' . ltrim($relativePath, '/');
    }
}

if (!function_exists('tenantCleanUrl')) {
    function tenantCleanUrl(string $relativePath = ''): string
    {
        $prefix = tenantCleanPrefix();
        if ($relativePath === '' || $relativePath === '/') {
            return $prefix;
        }

        return rtrim($prefix, '/') . '/' . ltrim($relativePath, '/');
    }
}

if (!function_exists('dmContextUrl')) {
    function dmContextUrl(string $globalKey, string $fallbackPath = ''): string
    {
        $candidate = $GLOBALS[$globalKey] ?? null;
        if (is_string($candidate) && trim($candidate) !== '') {
            return $candidate;
        }

        if ($fallbackPath === '' || $fallbackPath === '/') {
            return function_exists('tenantUrl') ? tenantUrl() : '/';
        }

        return function_exists('tenantUrl')
            ? tenantUrl($fallbackPath)
            : '/' . ltrim($fallbackPath, '/');
    }
}

if (!function_exists('dmBuildUrl')) {
    function dmBuildUrl(string $baseUrl, string $query = ''): string
    {
        $query = ltrim($query, '?&');
        if ($query === '') {
            return $baseUrl;
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $query;
    }
}

if (!function_exists('dmIsPdvContext')) {
    function dmIsPdvContext(): bool
    {
        return !empty($GLOBALS['__dm_pdv_context']);
    }
}

if (!function_exists('dmPdvCaixaId')) {
    function dmPdvCaixaId(): int
    {
        return (int) ($_SESSION['pdv_caixa_id'] ?? 0);
    }
}

if (!function_exists('tenantPath')) {
    function tenantPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || PHP_SAPI === 'cli') {
            return $path;
        }

        $slug = tenantCurrentSlug();
        if ($slug === null) {
            return $path;
        }

        $base = tenantBasePublicPath();
        $tenantBase = $base . '/' . $slug;

        // Mantem query/hash
        $matches = [];
        preg_match('/^([^?#]*)(\?[^#]*)?(#.*)?$/', $path, $matches);
        $purePath = (string)($matches[1] ?? '');
        $query = (string)($matches[2] ?? '');
        $hash = (string)($matches[3] ?? '');

        if (!str_starts_with($purePath, $base . '/')) {
            return $path;
        }
        if (str_starts_with($purePath, $tenantBase . '/')) {
            return $path;
        }

        $relative = ltrim(substr($purePath, strlen($base)), '/');
        return $tenantBase . '/' . $relative . $query . $hash;
    }
}

if (!function_exists('tenantEnforcePrefixedRequest')) {
    function tenantEnforcePrefixedRequest(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $slug = tenantCurrentSlug();
        if ($slug === null || $slug === '') {
            return;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $requestPath = (string)(parse_url($requestUri, PHP_URL_PATH) ?? '');
        if ($requestPath === '') {
            return;
        }

        $base = tenantBasePublicPath();
        $tenantBase = $base . '/' . $slug;

        if (!str_starts_with($requestPath, $base . '/')) {
            return;
        }
        if (str_starts_with($requestPath, $tenantBase . '/')) {
            return;
        }

        $relative = ltrim(substr($requestPath, strlen($base)), '/');
        if ($relative === '') {
            return;
        }

        $first = strtolower((string)explode('/', $relative, 2)[0]);
        $reserved = ['assets', 'uploads'];
        if (in_array($first, $reserved, true)) {
            return;
        }

        $query = (string)(parse_url($requestUri, PHP_URL_QUERY) ?? '');
        $target = $tenantBase . '/' . $relative . ($query !== '' ? ('?' . $query) : '');
        header('Location: ' . $target);
        exit();
    }
}

if (!function_exists('tenantRewritePublicUrls')) {
    function tenantRewritePublicUrls(string $buffer): string
    {
        if ($buffer === '' || PHP_SAPI === 'cli') {
            return $buffer;
        }

        $slug = tenantCurrentSlug();
        if ($slug === null || $slug === '') {
            return $buffer;
        }

        $contentType = '';
        foreach (headers_list() as $headerLine) {
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = strtolower(trim(substr($headerLine, strlen('Content-Type:'))));
                break;
            }
        }

        if ($contentType !== '') {
            $isTextual = str_contains($contentType, 'text/') ||
                str_contains($contentType, 'javascript') ||
                str_contains($contentType, 'json') ||
                str_contains($contentType, 'xml');
            if (!$isTextual) {
                return $buffer;
            }
        }

        $base = preg_quote(tenantBasePublicPath(), '/');
        $slugQuoted = preg_quote($slug, '/');

        return (string)preg_replace(
            '/(' . $base . '\\/)(?!' . $slugQuoted . '\\/)/',
            '$1' . $slug . '/',
            $buffer
        );
    }
}

if (!function_exists('tenantBootstrap')) {
    function tenantBootstrap(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $booted = true;

        if (PHP_SAPI === 'cli') {
            return;
        }

        tenantEnforcePrefixedRequest();

        if (function_exists('header_register_callback')) {
            header_register_callback(static function (): void {
                foreach (headers_list() as $headerLine) {
                    if (stripos($headerLine, 'Location:') !== 0) {
                        continue;
                    }

                    $location = trim(substr($headerLine, strlen('Location:')));
                    if ($location === '') {
                        continue;
                    }

                    $rewritten = tenantPath($location);
                    if ($rewritten !== $location) {
                        header_remove('Location');
                        header('Location: ' . $rewritten);
                    }
                }
            });
        }

        if (!defined('TENANT_OUTPUT_REWRITE_DISABLED')) {
            ob_start('tenantRewritePublicUrls');
        }
    }
}
