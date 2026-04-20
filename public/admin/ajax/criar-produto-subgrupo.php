<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';

use App\Modules\Produtos\ProdutoSubgrupoRepository;
use App\Support\AuditLogger;
use App\Support\CsrfProtection;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Sessão expirada.']);
    exit();
}

if ((int)($_SESSION['user_role'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'erro' => 'Apenas administradores podem criar subgrupos.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método inválido.']);
    exit();
}

try {
    CsrfProtection::validateRequestOrFail();
} catch (\Throwable $e) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'erro' => 'Token CSRF inválido.']);
    exit();
}

$grupoId = (int)($_POST['grupo_id'] ?? 0);
$nome = trim((string)($_POST['nome'] ?? ''));
$descricao = trim((string)($_POST['descricao'] ?? ''));

if ($grupoId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Selecione o grupo pai primeiro.']);
    exit();
}
if ($nome === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'O nome do subgrupo é obrigatório.']);
    exit();
}
if (mb_strlen($nome) > 100) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Nome deve ter até 100 caracteres.']);
    exit();
}

$repo = new ProdutoSubgrupoRepository($db);
if ($repo->existsNomeNoGrupo($grupoId, $nome)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'erro' => 'Já existe um subgrupo com este nome neste grupo.']);
    exit();
}

try {
    $id = $repo->create([
        'grupo_id' => $grupoId,
        'nome' => $nome,
        'descricao' => $descricao !== '' ? $descricao : null,
        'ativo' => true,
    ]);
    (new AuditLogger($db))->registrar(
        'produto_subgrupos', 'CRIAR_INLINE', 'produto_subgrupo', $id,
        'Subgrupo criado via formulário de produto: ' . $nome,
        ['grupo_id' => $grupoId, 'nome' => $nome]
    );
    echo json_encode(['ok' => true, 'id' => $id, 'nome' => $nome, 'grupo_id' => $grupoId]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Falha ao salvar: ' . $e->getMessage()]);
}
