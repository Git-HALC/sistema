<?php

namespace App\Modules\Financeiro;

use App\Modules\Clientes\ClienteRepository;
use PDO;

/**
 * FormaPagamentoController — camada HTTP do módulo de Formas de Pagamento.
 */
class FormaPagamentoController
{
    private FormaPagamentoService $service;
    private FormaPagamentoRepository $repo;
    private ContaRepository $contaRepo;
    private ClienteRepository $clienteRepo;

    private const BASE_URL = '/sistema_dm/public/admin/financeiro/formas-pagamento.php';

    public function __construct(PDO $pdo)
    {
        $this->repo = new FormaPagamentoRepository($pdo);
        $this->service = new FormaPagamentoService($this->repo);
        $this->contaRepo = new ContaRepository($pdo);
        $this->clienteRepo = new ClienteRepository($pdo);
        $this->verificarAutenticacao();
    }

    // =========================================================================
    // Roteamento
    // =========================================================================

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        match ($action) {
            'opcoes-json' => $this->opcoesJson(),
            'salvar'  => $this->salvar(),
            'excluir' => $this->excluir(),
            default   => $this->index(),
        };
    }

    // =========================================================================
    // Actions
    // =========================================================================

    private function index(): void
    {
        $formasPagamento = $this->service->listar(false);
        $contasBanco = $this->contaRepo->listar(true);
        $fornecedores = $this->clienteRepo->listarFornecedoresAtivos();

        $this->view('financeiro/formas-pagamento/index', [
            'titulo'          => 'Formas de Pagamento',
            'formasPagamento' => $formasPagamento,
            'contasBanco' => $contasBanco,
            'fornecedores' => $fornecedores,
        ]);
    }

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }

        $dados = [
            'nome' => trim($_POST['nome'] ?? ''),
            'tipo' => $_POST['tipo'] ?? FormaPagamento::TIPO_DINHEIRO,
            'descricao' => trim($_POST['descricao'] ?? ''),
            'adquirente_id' => !empty($_POST['adquirente_id']) ? (int) $_POST['adquirente_id'] : null,
            'taxa' => $_POST['taxa'] ?? '0',
            'prazo_dias' => $_POST['prazo_dias'] ?? 0,
            'conta_id' => !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null,
        ];
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        // Para edição, preserva status ativo (o modal não envia o campo)
        if ($id) {
            $dados['ativo'] = true;
        }

        $resultado = $this->service->salvar($dados, $id);

        if (!$resultado['ok']) {
            $this->flash('error', implode('<br>', $resultado['erros']));
        } else {
            $this->flash(
                'success',
                $id ? 'Forma de pagamento atualizada com sucesso!' : 'Forma de pagamento criada com sucesso!'
            );
        }

        $this->redirecionar();
    }

    private function excluir(): void
    {
        if (!$this->isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(403);
            exit();
        }

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID não informado.']);
        }

        try {
            $resultado = $this->service->excluir($id);
            $this->json([
                'success' => $resultado['ok'],
                'message' => $resultado['ok'] ? 'Excluído com sucesso!' : 'Erro ao excluir.',
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Erro ao excluir: ' . $e->getMessage()]);
        }
    }

    private function opcoesJson(): void
    {
        $formas = $this->service->listar(true);
        $this->json([
            'success' => true,
            'cadastro_url' => FormaPagamentoFinanceiroService::CADASTRO_URL,
            'formas' => array_map(static function (array $forma): array {
                return [
                    'id' => (int)$forma['id'],
                    'nome' => (string)$forma['nome'],
                    'tipo' => (string)$forma['tipo'],
                    'taxa' => (float)($forma['taxa'] ?? 0),
                    'prazo_dias' => (int)($forma['prazo_dias'] ?? 0),
                    'conta_id' => isset($forma['conta_id']) ? (int)$forma['conta_id'] : null,
                    'conta_nome' => $forma['conta_nome'] ?? null,
                    'adquirente_id' => isset($forma['adquirente_id']) ? (int)$forma['adquirente_id'] : null,
                    'adquirente_nome' => $forma['adquirente_nome'] ?? null,
                ];
            }, $formas),
        ]);
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /sistema_dm/public/login.php');
            exit();
        }

        if ((int) ($_SESSION['user_role'] ?? 0) !== 1) {
            header('Location: /sistema_dm/public/admin/dashboard.php');
            exit();
        }
    }

    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';
        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View não encontrada: {$view}</div></div>";
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirecionar(?string $url = null): never
    {
        header('Location: ' . ($url ?? self::BASE_URL));
        exit();
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados);
        exit();
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
