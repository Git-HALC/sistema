<?php

namespace App\Modules\Financeiro;

use PDO;

/**
 * ContaController — camada HTTP do módulo de Contas Bancárias/Caixa.
 *
 * Responsabilidades:
 *   • Verificar autenticação (somente Admin).
 *   • Parsear entrada HTTP.
 *   • Delegar ao ContaService.
 *   • Renderizar view (que já inclui header/footer) ou redirecionar.
 */
class ContaController
{
    private ContaService $service;

    private const BASE_URL = '/sistema_dm/public/admin/financeiro/contas.php';

    public function __construct(PDO $pdo)
    {
        $this->service = new ContaService(new ContaRepository($pdo));
        $this->verificarAutenticacao();
    }

    // =========================================================================
    // Roteamento
    // =========================================================================

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        match ($action) {
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
        $contas = $this->service->listar(false);

        $this->view('financeiro/contas/index', [
            'titulo' => 'Contas Bancárias/Caixa',
            'contas' => $contas,
        ]);
    }

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }

        $dados = [
            'nome'            => trim($_POST['nome']            ?? ''),
            'tipo'            => $_POST['tipo']                 ?? 'Banco',
            'banco'           => trim($_POST['banco']           ?? ''),
            'agencia'         => trim($_POST['agencia']         ?? ''),
            'numero_conta'    => trim($_POST['numero_conta']    ?? ''),
            'saldo_inicial'   => (float) ($_POST['saldo_inicial'] ?? 0),
            'data_saldo_inicial' => $_POST['data_saldo_inicial'] ?? date('Y-m-d'),
            'ativo'           => isset($_POST['ativo']) ? (int)$_POST['ativo'] : 1,
        ];
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        $resultado = $this->service->salvar($dados, $id);

        if (!$resultado['ok']) {
            $this->flash('error', implode('<br>', $resultado['erros']));
        } else {
            $this->flash('success', $id ? 'Conta atualizada com sucesso!' : 'Conta criada com sucesso!');
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

        $resultado = $this->service->excluir($id);
        $this->json([
            'success' => $resultado['ok'],
            'message' => $resultado['ok'] ? 'Excluído com sucesso!' : 'Erro ao excluir.',
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

    /**
     * Inclui a view. As views do módulo Financeiro já contêm header e footer.
     */
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
