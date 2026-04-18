<?php

use App\Modules\Servicos\Servico;
use App\Modules\Servicos\ServicoCatalogoRepository;
use App\Modules\Servicos\ServicoItemRepository;
use App\Modules\Servicos\ServicoRepository;
use App\Modules\Servicos\ServicoService;
use App\Security\PdvPermissao;

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);
$allowPdvSharedAccess = !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
if (!$allowPdvSharedAccess) {
    \App\Support\PermissionGate::manager()?->require('servicos', true);
} elseif (!(new PdvPermissao($db))->usuarioAtualPodeOperarPdv()) {
    jsonResponseServico(false, 'Sem permissÃ£o para operar o PDV.', null, 403);
}

function jsonResponseServico(bool $success, string $message, ?array $data = null, int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponseServico(false, 'M?todo HTTP inv?lido.', null, 405);
}

$input = $_POST;
$rawInput = file_get_contents('php://input');
if ($rawInput !== false && trim($rawInput) !== '') {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$servicoId = trim((string)($input['servico_id'] ?? ''));
$novoStatus = strtoupper(trim((string)($input['novo_status'] ?? '')));

if ($servicoId === '' || $novoStatus === '') {
    jsonResponseServico(false, 'Par?metros obrigat?rios ausentes.', null, 400);
}

if (!in_array($novoStatus, Servico::STATUS_VALIDOS, true)) {
    jsonResponseServico(false, 'Status inv?lido.', null, 400);
}

try {
    $db->beginTransaction();

    $service = new ServicoService(
        $db,
        new ServicoRepository($db),
        new ServicoCatalogoRepository($db),
        new ServicoItemRepository($db)
    );

    $servico = $service->atualizarStatusKanban($servicoId, $novoStatus);

    $db->commit();

    jsonResponseServico(true, 'Status atualizado com sucesso.', [
        'servico' => [
            'id' => $servico->id,
            'status' => $servico->status,
            'status_label' => $servico->statusLabel(),
            'status_badge' => $servico->statusBadge(),
            'numero' => $servico->numero,
        ],
    ]);
} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    jsonResponseServico(false, $e->getMessage(), null, 422);
}
