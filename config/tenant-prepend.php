<?php
declare(strict_types=1);

// Carrega .env (se existir) antes do bootstrap de tenant, via getenv/putenv.
(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === '' || getenv($k) !== false) continue;
        $v = trim($v, " \t\n\r\0\x0B\"'");
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
})();

require_once __DIR__ . '/tenant.php';
tenantBootstrap();
