<?php

use App\Modules\Clientes\ClienteRepository;
use App\Modules\Clientes\ClienteService;
use App\Security\PdvPermissao;

$route = static function (string $path = ''): string {
    $baseUrl = function_exists('dmContextUrl')
        ? dmContextUrl('__dm_cliente_base_url', 'admin/clientes.php')
        : (function_exists('tenantUrl') ? tenantUrl('admin/clientes.php') : '/admin/clientes.php');

    if ($path === '' || $path === 'admin/clientes.php' || $path === '/sistema_dm/public/admin/clientes.php') {
        return $baseUrl;
    }

    if (str_starts_with($path, 'admin/clientes.php?')) {
        return function_exists('dmBuildUrl')
            ? dmBuildUrl($baseUrl, substr($path, strlen('admin/clientes.php?')))
            : $baseUrl . '&' . substr($path, strlen('admin/clientes.php?'));
    }

    return function_exists('tenantUrl') ? tenantUrl($path) : '/' . ltrim($path, '/');
};

// Iniciar a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado e tem permissão
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $route('login.php'));
    exit();
}

// Incluir o autoloader do Composer se existir
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Incluir o arquivo de configuração do banco de dados
require_once __DIR__ . '/../../config/database.php';

// Obter a instância do banco de dados
$database = Database::getInstance();
$pdo = $database->getConnection();

\App\Support\PermissionGate::init($pdo);
$allowPdvSharedAccess = !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
if (!$allowPdvSharedAccess) {
    \App\Support\PermissionGate::require('clientes');
} elseif (!(new PdvPermissao($pdo))->usuarioAtualPodeOperarPdv()) {
    header('Location: ' . $route('login.php'));
    exit();
}

$clienteRepo = new ClienteRepository($pdo);
$clienteService = new ClienteService($clienteRepo);

// Processar ações
$action = $_POST['action'] ?? ($_GET['action'] ?? 'listar');
$id = $_POST['id'] ?? ($_GET['id'] ?? null);

switch ($action) {
    case 'novo':
        // Exibir formulário de novo cliente
        $titulo = 'Novo Cliente';
        $cliente = [
            'nome' => '',
            'cpf_cnpj' => '',
            'telefone' => '',
            'email' => '',
            'eh_cliente' => true,
            'eh_fornecedor' => false,
            'logradouro' => '',
            'numero_endereco' => '',
            'complemento' => '',
            'bairro' => '',
            'cidade' => '',
            'estado' => '',
            'cep' => '',
            'codigo_municipio' => '',
            'ie' => '',
            'ind_ie_dest' => '9',
        ];
        include __DIR__ . '/../../app/views/clientes/form.php';
        break;

    case 'editar':
        // Exibir formulário de edição
        if (!$id) {
            $_SESSION['error_message'] = 'ID do cliente não informado.';
            header('Location: ' . $route('admin/clientes.php'));
            exit();
        }

        $clienteObj = $clienteRepo->buscarPorId((int)$id);
        $cliente = $clienteObj ? $clienteObj->toArray() : null;
        if (!$cliente) {
            $_SESSION['error_message'] = 'Cliente não encontrado.';
            header('Location: ' . $route('admin/clientes.php'));
            exit();
        }

        $titulo = 'Editar Cliente';
        include __DIR__ . '/../../app/views/clientes/form.php';
        break;

    case 'salvar':
        // Processar formulário de salvar
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => ['Requisição inválida.']]);
                exit();
            }
            header('Location: ' . $route('admin/clientes.php'));
            exit();
        }

        $dados = [
            'nome' => trim($_POST['nome'] ?? ''),
            'cpf_cnpj' => preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? ''),
            'telefone' => trim($_POST['telefone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'eh_cliente' => isset($_POST['eh_cliente']),
            'eh_fornecedor' => isset($_POST['eh_fornecedor']),
            'logradouro' => trim($_POST['logradouro'] ?? ''),
            'numero_endereco' => trim($_POST['numero_endereco'] ?? ''),
            'complemento' => trim($_POST['complemento'] ?? ''),
            'bairro' => trim($_POST['bairro'] ?? ''),
            'cidade' => trim($_POST['cidade'] ?? ''),
            'estado' => trim($_POST['estado'] ?? ''),
            'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
            'codigo_municipio' => preg_replace('/\D/', '', $_POST['codigo_municipio'] ?? ''),
            'ie' => trim($_POST['ie'] ?? ''),
            'ind_ie_dest' => trim($_POST['ind_ie_dest'] ?? '9'),
        ];

        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $resultado = $clienteService->atualizar((int)$_POST['id'], $dados);
            $sucesso = (bool)($resultado['ok'] ?? false);
            $mensagem = $sucesso ? 'Cliente atualizado com sucesso!' : implode('<br>', $resultado['erros'] ?? ['Erro ao atualizar cliente.']);
        } else {
            $resultado = $clienteService->criar($dados);
            $sucesso = (bool)($resultado['ok'] ?? false);
            $mensagem = $sucesso ? 'Cliente cadastrado com sucesso!' : implode('<br>', $resultado['erros'] ?? ['Erro ao cadastrar cliente.']);
        }

        if (!$sucesso) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => explode('<br>', $mensagem)]);
                exit();
            }

            $_SESSION['error_message'] = $mensagem;
            $_SESSION['form_data'] = $dados;
            $redirect = isset($_POST['id']) && !empty($_POST['id'])
                ? $route('admin/clientes.php?action=editar&id=' . $_POST['id'])
                : $route('admin/clientes.php?action=novo');
            header('Location: ' . $redirect);
            exit();
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => (bool)$sucesso,
                'message' => $mensagem,
                'redirect' => $route('admin/clientes.php'),
            ]);
            exit();
        }

        $_SESSION['success_message'] = $mensagem;
        header('Location: ' . $route('admin/clientes.php'));
        exit();

    case 'excluir':
        // Verificar se é uma requisição AJAX
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        // Inicializar array de resposta
        $response = ['success' => false, 'message' => ''];

        try {
            $requestId = $_POST['id'] ?? ($_GET['id'] ?? null);

            // Verificar se o ID foi fornecido
            if ($requestId === null || !is_numeric((string)$requestId)) {
                throw new Exception('ID do cliente não informado ou inválido.');
            }

            $id = (int)$requestId;

            // Verificar se o cliente existe
            $cliente = $clienteRepo->buscarPorId($id);
            if (!$cliente) {
                throw new Exception('Cliente não encontrado.');
            }

            // Excluir o cliente
            $resultado = $clienteRepo->excluir($id);

            if ($resultado === true) {
                $response['success'] = true;
                $response['message'] = 'Cliente excluído com sucesso!';

                // Apenas define mensagem de sessão se não for AJAX
                if (!$isAjax) {
                    $_SESSION['success_message'] = $response['message'];
                    header('Location: ' . $route('admin/clientes.php'));
                    exit;
                }
            } else {
                throw new Exception('Erro ao excluir o cliente. Tente novamente.');
            }
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();

            if (!$isAjax) {
                $_SESSION['error_message'] = $response['message'];
                header('Location: ' . $route('admin/clientes.php'));
                exit;
            }
        }

        // Se for uma requisição AJAX, retorna JSON
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

    case 'listar':
    default:
        // Listar clientes
        $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        $itensPorPagina = 10;

        // Consulta direta para listagem paginada
        $offset = max(0, ($pagina - 1) * $itensPorPagina);

        // Contar total de clientes ativos
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = TRUE");
        $totalClientes = $countStmt->fetch()['total'];
        $totalPaginas = ceil($totalClientes / $itensPorPagina);

        // Buscar clientes
        $sql = "SELECT c.*
                FROM clientes c
                WHERE c.ativo = TRUE
                ORDER BY c.nome ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$itensPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = [
            'titulo' => 'Clientes',
            'clientes' => $clientes,
            'paginaAtual' => $pagina,
            'totalPaginas' => $totalPaginas,
            'totalClientes' => $totalClientes,
        ];
        extract($dados);
        include __DIR__ . '/../includes/header.php';
        include __DIR__ . '/../../app/views/clientes/index.php';
        include __DIR__ . '/../includes/footer.php';
        break;
}
