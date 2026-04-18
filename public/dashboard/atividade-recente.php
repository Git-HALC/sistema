<?php

declare(strict_types=1);

use App\Modules\Dashboard\DashboardApiService;
use App\Support\DashboardApiResponder;

require_once __DIR__ . '/../../config/database.php';

DashboardApiResponder::run(
    $db,
    'dashboard',
    'GET',
    static function (\PDO $pdo, array $request): array {
        $page = max(1, DashboardApiResponder::intFrom($request, 'page', 1));
        $perPage = max(1, min(20, DashboardApiResponder::intFrom($request, 'per_page', 10)));
        return (new DashboardApiService($pdo))->getAtividadeRecente($page, $perPage);
    }
);
