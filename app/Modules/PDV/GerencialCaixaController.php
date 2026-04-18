<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\CsrfProtection;
use PDO;
use RuntimeException;

final class GerencialCaixaController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvService $service,
        private readonly PdvPermissao $permissao,
        private readonly PdvRelatorioController $relatorioController
    ) {
    }

    public function handleRequest(): void
    {
        $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'index');

        try {
            match ($action) {
                'detalhe' => $this->detalhe((int) ($_GET['id'] ?? 0)),
                'forcar-fechamento' => $this->forcarFechamento((int) ($_POST['id'] ?? 0)),
                'salvar-observacao' => $this->salvarObservacao((int) ($_POST['id'] ?? 0)),
                'pdf' => $this->relatorioController->pdf((int) ($_GET['id'] ?? 0)),
                'termica' => $this->relatorioController->termica((int) ($_GET['id'] ?? 0)),
                default => $this->index(),
            };
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('gerencial/caixas');
        }
    }

    public function index(): void
    {
        $this->assertPermissaoConferencia();

        $filtros = [
            'data' => trim((string) ($_GET['data'] ?? '')),
            'operador' => trim((string) ($_GET['operador'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'diferenca' => trim((string) ($_GET['diferenca'] ?? '')),
        ];

        $this->render('gerencial-caixas/index', [
            'page_title' => 'Conferência de Caixas',
            'dashboard' => $this->service->dashboardConferenciaHoje(),
            'grafico' => $this->service->faturamentoPorCaixaHoje(),
            'caixas' => $this->service->listarCaixasGerenciais($filtros),
            'operadores' => (new PdvRepository($this->pdo))->listarOperadores(),
            'filtros' => $filtros,
            'csrfToken' => CsrfProtection::token(),
            'isAdmin' => ((int) ($_SESSION['user_role'] ?? 0) === 1),
        ]);
    }

    public function detalhe(int $id): void
    {
        $this->assertPermissaoConferencia();

        $dados = $this->service->obterDadosRelatorio($id);
        $this->render('gerencial-caixas/detalhe', [
            'page_title' => 'Detalhe do Caixa',
            'relatorio' => $dados,
            'csrfToken' => CsrfProtection::token(),
            'isAdmin' => ((int) ($_SESSION['user_role'] ?? 0) === 1),
        ]);
    }

    public function forcarFechamento(int $id): void
    {
        $this->assertAdmin();
        CsrfProtection::validateRequestOrFail();

        $observacao = trim((string) ($_POST['observacao'] ?? ''));
        $this->service->fecharCaixa(
            $id,
            (int) ($_SESSION['user_id'] ?? 0),
            [
                'dinheiro' => 0,
                'cartao' => 0,
                'pix' => 0,
                'a_faturar' => 0,
            ],
            true,
            $observacao !== '' ? $observacao : 'Fechamento forçado pelo administrador.'
        );

        $this->flash('success', 'Caixa fechado à força com sucesso.');
        $this->redirect('gerencial/caixas/' . $id);
    }

    public function salvarObservacao(int $id): void
    {
        $this->assertPermissaoConferencia();
        CsrfProtection::validateRequestOrFail();

        $this->service->salvarObservacao($id, (string) ($_POST['observacao'] ?? ''));
        $this->flash('success', 'Observação salva com sucesso.');
        $this->redirect('gerencial/caixas/' . $id);
    }

    private function render(string $view, array $dados): void
    {
        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';

        $path = __DIR__ . '/../../views/' . $view . '.php';
        if (is_file($path)) {
            require $path;
        } else {
            echo "<div class='container-fluid mt-4'><div class='alert alert-danger'>View não encontrada: " . htmlspecialchars($view) . '</div></div>';
        }

        require __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . tenantCleanUrl($path));
        exit();
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function assertPermissaoConferencia(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }

        if (!$this->permissao->podeConferirCaixas((int) ($_SESSION['user_id'] ?? 0))) {
            throw new RuntimeException('Sem permissão para acessar a conferência de caixas.');
        }
    }

    private function assertAdmin(): void
    {
        $this->assertPermissaoConferencia();

        if ((int) ($_SESSION['user_role'] ?? 0) !== 1) {
            throw new RuntimeException('Apenas administradores podem forçar o fechamento.');
        }
    }
}
