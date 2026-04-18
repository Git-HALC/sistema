<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\CsrfProtection;
use PDO;
use RuntimeException;

final class PdvLancamentoController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvService $service,
        private readonly PdvPermissao $permissao
    ) {
    }

    public function handleRequest(): void
    {
        $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'registrar');

        try {
            match ($action) {
                'selecionar' => $this->selecionarCaixa(),
                default => $this->registrar(),
            };
        } catch (RuntimeException $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function resolverCaixaOuRenderizarSelecao(string $destino, string $menuAtivo): ?int
    {
        $caixaSelecionado = (int) ($_GET['caixa_id'] ?? $_POST['caixa_id'] ?? $_SESSION['pdv_caixa_id'] ?? 0);
        if ($caixaSelecionado > 0 && $this->validarCaixaSelecionado($caixaSelecionado)) {
            $_SESSION['pdv_caixa_id'] = $caixaSelecionado;
            return $caixaSelecionado;
        }

        $caixasAbertos = $this->permissao->getCaixasAbertos();
        if ($caixasAbertos === []) {
            throw new RuntimeException('Não existem caixas abertos para lançamento.');
        }

        if (count($caixasAbertos) === 1) {
            $id = (int) ($caixasAbertos[0]['id'] ?? 0);
            $_SESSION['pdv_caixa_id'] = $id;
            return $id > 0 ? $id : null;
        }

        $this->renderSelecao($caixasAbertos, $destino, $menuAtivo);
        return null;
    }

    public function selecionarCaixa(): void
    {
        $this->assertUsuarioLogado();

        $destino = (string) ($_GET['destino'] ?? tenantCleanUrl('pdv'));
        $menuAtivo = (string) ($_GET['menu_ativo'] ?? 'abertura');
        $this->renderSelecao($this->permissao->getCaixasAbertos(), $destino, $menuAtivo);
    }

    public function registrar(): void
    {
        $this->assertUsuarioLogado();
        CsrfProtection::validateRequestOrFail();

        $usuarioId = (int) ($_SESSION['user_id'] ?? 0);
        $caixaId = (int) ($_POST['caixa_id'] ?? 0);
        $tipo = (string) ($_POST['tipo'] ?? '');
        $referenciaId = (string) ($_POST['referencia_id'] ?? '');
        $formaPagamento = (string) ($_POST['forma_pagamento'] ?? '');

        if ($tipo === 'pedido') {
            $this->service->registrarLancamentoPedido($referenciaId, $caixaId, $formaPagamento, $usuarioId);
        } elseif ($tipo === 'servico') {
            $this->service->registrarLancamentoServico($referenciaId, $caixaId, $formaPagamento, $usuarioId);
        } else {
            throw new RuntimeException('Tipo de lançamento inválido.');
        }

        $this->json([
            'success' => true,
            'message' => 'Lançamento registrado no caixa.',
        ]);
    }

    private function validarCaixaSelecionado(int $caixaId): bool
    {
        foreach ($this->permissao->getCaixasAbertos() as $caixa) {
            if ((int) ($caixa['id'] ?? 0) === $caixaId) {
                return true;
            }
        }

        return false;
    }

    private function renderSelecao(array $caixasAbertos, string $destino, string $menuAtivo): never
    {
        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = $menuAtivo;
        $GLOBALS['__dm_pdv_caixa_resumo'] = null;
        $GLOBALS['__dm_pdv_multiplos_abertos'] = count($caixasAbertos) > 1;

        $page_title = 'Selecionar Caixa';
        $csrfToken = CsrfProtection::token();
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/pdv/selecionar_caixa.php';
        require __DIR__ . '/../../../public/includes/footer.php';
        exit();
    }

    private function json(array $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    private function assertUsuarioLogado(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }
    }
}
