<?php
declare(strict_types=1);

namespace App\Support;

class PermissionGate
{
    private static ?PermissionManager $instance = null;

    public static function init(\PDO $pdo): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
            return;
        }

        self::$instance = new PermissionManager(
            $pdo,
            (int)$_SESSION['user_id'],
            (int)$_SESSION['user_role']
        );
    }

    public static function require(string $slug): void
    {
        self::$instance?->require($slug);
    }

    public static function can(string $slug): bool
    {
        return self::$instance?->can($slug) ?? false;
    }

    public static function getModulos(): array
    {
        return self::$instance?->getModulos() ?? [];
    }

    public static function manager(): ?PermissionManager
    {
        return self::$instance;
    }
}
