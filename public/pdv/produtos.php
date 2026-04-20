<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

use App\Modules\PDV\PdvRepository;
use App\Security\PdvPermissao;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$repository = new PdvRepository($db);
$permissao = new PdvPermissao($db);
if (!$permissao->usuarioAtualPodeOperarPdv()) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

$GLOBALS['__dm_layout_mode'] = 'pdv';
$GLOBALS['__dm_pdv_active'] = 'produtos';
$GLOBALS['__dm_allow_pdv_shared_access'] = true;
$GLOBALS['__dm_pdv_context'] = true;
$GLOBALS['__dm_produto_base_url'] = tenantCleanUrl('pdv/produtos');
$GLOBALS['__dm_pedido_base_url'] = tenantCleanUrl('pdv/pedidos');
$GLOBALS['__dm_servico_base_url'] = tenantCleanUrl('pdv/servicos');
$GLOBALS['__dm_cliente_base_url'] = tenantCleanUrl('pdv/clientes');
$GLOBALS['__dm_pdv_caixa_resumo'] = !empty($_SESSION['pdv_caixa_id']) ? $repository->buscarCaixa((int) $_SESSION['pdv_caixa_id']) : null;
$GLOBALS['__dm_pdv_multiplos_abertos'] = count($permissao->getCaixasAbertos()) > 1;
$GLOBALS['__dm_pdv_readonly'] = true;

// Bloqueia qualquer ação de escrita/inventário no contexto PDV (apenas consulta)
$acoesBloqueadas = ['novo', 'editar', 'salvar', 'excluir', 'inventario', 'movimentar-estoque'];
if (in_array($_GET['action'] ?? '', $acoesBloqueadas, true)
    || in_array($_POST['action'] ?? '', $acoesBloqueadas, true)) {
    header('Location: ' . tenantCleanUrl('pdv/produtos'));
    exit();
}

require __DIR__ . '/../admin/produtos.php';
