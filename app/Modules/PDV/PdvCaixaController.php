<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\CsrfProtection;
use PDO;
use RuntimeException;

final class PdvCaixaController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvService $service,
        private readonly PdvPermissao $permissao
    ) {
    }

    public function handleRequest(): void
    {
        $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'seletor');

        try {
            match ($action) {
                'abrir-form' => $this->abrirForm(),
                'abrir-post' => $this->abrirPost(),
                'caixa' => $this->caixa((int) ($_GET['id'] ?? 0)),
                'fechar-form' => $this->fecharForm((int) ($_GET['id'] ?? 0)),
                'fechar-post' => $this->fecharPost((int) ($_POST['id'] ?? 0)),
                'relatorio' => $this->relatorio((int) ($_GET['id'] ?? 0)),
                default => $this->seletor(),
            };
        } catch (RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('pdv');
        }
    }

    public function seletor(): void
    {
        $this->assertUsuarioLogado();

        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeAbrirCaixa($usuarioId) && !$this->permissao->podeLancarNoCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para acessar o PDV.');
        }

        $caixas = $this->permissao->getCaixasAbertos();
        $caixaAtual = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);

        $this->render('pdv/seletor', [
            'page_title' => 'PDV',
            'caixas' => $caixas,
            'caixaAtualUsuario' => $caixaAtual,
            'podeAbrirNovoCaixa' => !$this->permissao->temCaixaAberto($usuarioId) && $this->permissao->podeAbrirCaixa($usuarioId),
        ], 'abertura');
    }

    public function abrirForm(): void
    {
        $this->assertUsuarioLogado();

        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeAbrirCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para abrir caixa.');
        }

        if ($this->permissao->temCaixaAberto($usuarioId)) {
            throw new RuntimeException('Você já possui um caixa aberto.');
        }

        $this->render('pdv/abrir', [
            'page_title' => 'Abrir Caixa',
            'csrfToken' => CsrfProtection::token(),
            'usuarioNome' => (string) ($_SESSION['user_name'] ?? ''),
            'usuarioEmail' => (string) ($_SESSION['user_email'] ?? ''),
        ], 'abertura');
    }

    public function abrirPost(): void
    {
        $this->assertUsuarioLogado();
        CsrfProtection::validateRequestOrFail();

        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        $senha = (string) ($_POST['senha_confirmacao'] ?? '');
        $valorSuprimento = (float) str_replace(',', '.', (string) ($_POST['valor_suprimento'] ?? '0'));

        $caixa = $this->service->abrirCaixa($usuarioId, $senha, $valorSuprimento);
        $_SESSION['pdv_caixa_id'] = (int) ($caixa['id'] ?? 0);

        $this->flash('success', 'Caixa aberto com sucesso.');
        $this->redirect('pdv/caixa/' . (int) ($caixa['id'] ?? 0));
    }

    public function caixa(int $id): void
    {
        $this->assertUsuarioLogado();
        $caixa = $this->service->obterDadosRelatorio($id)['caixa'] ?? null;
        if (!is_array($caixa)) {
            throw new RuntimeException('Caixa não encontrado.');
        }

        if (($caixa['status'] ?? '') !== 'aberto' && !$this->permissao->podeAcessarRelatorio((int) ($_SESSION['user_id'] ?? 0), $id)) {
            throw new RuntimeException('Sem permissão para acessar este caixa.');
        }

        $_SESSION['pdv_caixa_id'] = $id;

        $this->render('pdv/caixa', [
            'page_title' => 'Caixa #' . (int) ($caixa['numero_caixa'] ?? 0),
            'caixa' => $caixa,
            'caixasAbertos' => $this->permissao->getCaixasAbertos(),
            'podeFechar' => $this->permissao->podeFecharCaixa((int) ($_SESSION['user_id'] ?? 0), $id),
        ], 'abertura', $caixa);
    }

    public function fecharForm(int $id): void
    {
        $this->assertUsuarioLogado();

        if (!$this->permissao->podeFecharCaixa((int) ($_SESSION['user_id'] ?? 0), $id)) {
            throw new RuntimeException('Sem permissão para fechar este caixa.');
        }

        $caixa = $this->service->obterDadosRelatorio($id)['caixa'] ?? null;
        if (!is_array($caixa) || ($caixa['status'] ?? '') !== 'aberto') {
            throw new RuntimeException('Caixa inválido para fechamento.');
        }

        $this->render('pdv/fechar', [
            'page_title' => 'Fechamento Cego',
            'caixa' => $caixa,
            'csrfToken' => CsrfProtection::token(),
        ], 'abertura', $caixa);
    }

    public function fecharPost(int $id): void
    {
        $this->assertUsuarioLogado();
        CsrfProtection::validateRequestOrFail();

        $caixa = $this->service->fecharCaixa(
            $id,
            (int) ($_SESSION['user_id'] ?? 0),
            [
                'dinheiro' => $_POST['dinheiro'] ?? 0,
                'cartao' => $_POST['cartao'] ?? 0,
                'pix' => $_POST['pix'] ?? 0,
                'a_faturar' => $_POST['a_faturar'] ?? 0,
            ],
            false,
            null
        );

        unset($_SESSION['pdv_caixa_id']);

        $this->flash('success', 'Caixa fechado com sucesso.');
        $query = !empty($_POST['deseja_imprimir']) ? '?print=1' : '';
        $this->redirect('pdv/caixa/' . (int) ($caixa['id'] ?? 0) . '/relatorio' . $query);
    }

    public function relatorio(int $id): void
    {
        $this->assertUsuarioLogado();

        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeAcessarRelatorio($usuarioId, $id)) {
            throw new RuntimeException('Sem permissão para visualizar o relatório deste caixa.');
        }

        $dados = $this->service->obterDadosRelatorio($id);
        $this->render('pdv/relatorio', [
            'page_title' => 'Relatório de Fechamento',
            'relatorio' => $dados,
            'impressaoAutomatica' => !empty($_GET['print']),
        ], 'abertura', $dados['caixa']);
    }

    private function render(string $view, array $dados, string $menuAtivo, ?array $caixa = null): void
    {
        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = $menuAtivo;
        $GLOBALS['__dm_pdv_caixa_resumo'] = $caixa;
        $GLOBALS['__dm_pdv_multiplos_abertos'] = count($this->permissao->getCaixasAbertos()) > 1;

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

    private function assertUsuarioLogado(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }
    }
}
