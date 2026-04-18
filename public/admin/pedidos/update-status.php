<?php

/**
 * Endpoint AJAX: Atualizar Status de Pedido (Kanban)
 * 
 * Recebe:
 *   - pedido_id (string): UUID do pedido
 *   - novo_status (string): Novo status ('RASCUNHO', 'PENDENTE', 'EM_PROCESSO', 'APROVADO', 'CONCLUIDO')
 * 
 * Retorna JSON:
 *   {
 *     "success": true|false,
 *     "message": "...",
 *     "pedido": {...}  // Opcional - dados do pedido atualizado
 *   }
 * 
 * Segurança:
 *   - Autenticação obrigatória (sessão)
 *   - Permissões por módulo (PermissionGate)
 *   - Validações de transição de status
 *   - Transação de banco de dados
 *   - Prepared statements (PDO)
 */

use App\Modules\Gestao_Pedidos\Pedido;
use App\Modules\Gestao_Pedidos\PedidoRepository;
use App\Modules\Gestao_Pedidos\PedidoService;
use App\Modules\Gestao_Pedidos\PedidoItemRepository;
use App\Security\PdvPermissao;

require_once __DIR__ . '/../../../config/database.php';

// =============================================================================
// Configuração de Header e Sessão
// =============================================================================

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

\App\Support\PermissionGate::init($db);

// =============================================================================
// Função de Resposta JSON
// =============================================================================

function jsonResponse(bool $success, string $message, ?array $data = null, int $httpCode = 200): never
{
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
    ];
    
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =============================================================================
// Validação de Autenticação
// =============================================================================

if (!isset($_SESSION['user_id'])) {
    jsonResponse(
        false, 
        'Sessão expirada. Por favor, faça login novamente.',
        null,
        401
    );
}

$userId = (int) $_SESSION['user_id'];
$allowPdvSharedAccess = !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
if (!$allowPdvSharedAccess) {
    \App\Support\PermissionGate::manager()?->require('pedidos', true);
} elseif (!(new PdvPermissao($db))->usuarioAtualPodeOperarPdv()) {
    jsonResponse(false, 'Sem permissÃ£o para operar o PDV.', null, 403);
}

// =============================================================================
// Validação de Método HTTP
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(
        false,
        'Método HTTP inválido. Use POST.',
        null,
        405
    );
}

// =============================================================================
// Captura de Dados da Requisição
// =============================================================================

// Suporte para application/json e application/x-www-form-urlencoded
$input = $_POST;

$rawInput = file_get_contents('php://input');
if ($rawInput !== false && trim($rawInput) !== '') {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$pedidoId = trim((string)($input['pedido_id'] ?? ''));
$novoStatus = strtoupper(trim((string)($input['novo_status'] ?? '')));

// =============================================================================
// Validação de Parâmetros Obrigatórios
// =============================================================================

if ($pedidoId === '') {
    jsonResponse(
        false,
        'Parâmetro obrigatório ausente: pedido_id',
        null,
        400
    );
}

if ($novoStatus === '') {
    jsonResponse(
        false,
        'Parâmetro obrigatório ausente: novo_status',
        null,
        400
    );
}

// Validar formato do status
if (!in_array($novoStatus, Pedido::STATUS_VALIDOS, true)) {
    jsonResponse(
        false,
        'Status inválido. Valores permitidos: ' . implode(', ', Pedido::STATUS_VALIDOS),
        null,
        400
    );
}

// =============================================================================
// Processamento da Atualização de Status
// =============================================================================

try {
    // Iniciar transação
    $db->beginTransaction();
    
    // Instanciar serviço
    $pedidoRepository = new PedidoRepository($db);
    $pedidoItemRepository = new PedidoItemRepository($db);
    $pedidoService = new PedidoService(
        $db,
        $pedidoRepository,
        $pedidoItemRepository,
        null, // ProdutoService (não necessário para atualização simples)
        null, // ContaReceberService (não necessário para atualização simples)
        null  // OrcamentoService (não necessário para atualização simples)
    );
    
    // Atualizar status via método Kanban
    $pedidoAtualizado = $pedidoService->atualizarStatusKanban($pedidoId, $novoStatus);
    
    // Commit da transa??o
    $db->commit();
    
    // Resposta de sucesso
    jsonResponse(
        true,
        'Status atualizado com sucesso para: ' . Pedido::STATUS_LABELS[$novoStatus],
        [
            'pedido' => [
                'id' => $pedidoAtualizado->id,
                'status' => $pedidoAtualizado->status,
                'status_label' => $pedidoAtualizado->statusLabel(),
                'status_badge' => $pedidoAtualizado->statusBadge(),
                'numero' => $pedidoAtualizado->numero,
            ]
        ],
        200
    );
    
} catch (\RuntimeException $e) {
    // Rollback em caso de erro
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Erro de validação ou lógica de negócio
    jsonResponse(
        false,
        $e->getMessage(),
        null,
        422
    );
    
} catch (\PDOException $e) {
    // Rollback em caso de erro de banco
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log do erro (em produção, use um sistema de log adequado)
    error_log('Erro PDO em update-status.php: ' . $e->getMessage());
    
    jsonResponse(
        false,
        'Erro ao processar requisição. Tente novamente.',
        null,
        500
    );
    
} catch (\Throwable $e) {
    // Rollback em caso de erro inesperado
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Log do erro
    error_log('Erro inesperado em update-status.php: ' . $e->getMessage());
    
    jsonResponse(
        false,
        'Erro interno do servidor. Contate o administrador.',
        null,
        500
    );
}

