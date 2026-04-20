<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';

use App\Modules\Produtos\ProdutoSubgrupoRepository;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada']);
    exit();
}

$grupoId = filter_input(INPUT_GET, 'grupo_id', FILTER_VALIDATE_INT);
if (!$grupoId) {
    echo json_encode(['ok' => true, 'itens' => []]);
    exit();
}

$repo = new ProdutoSubgrupoRepository($db);
$itens = $repo->porGrupoAtivos($grupoId);

echo json_encode([
    'ok' => true,
    'itens' => array_map(
        static fn ($r) => ['id' => (int)$r['id'], 'nome' => (string)$r['nome']],
        $itens
    ),
], JSON_UNESCAPED_UNICODE);
