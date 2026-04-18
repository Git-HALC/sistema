<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use App\Support\CsrfProtection;
use PDO;
use RuntimeException;

/**
 * Fluxo da conferência de caixa em 3 passos:
 *   step1: operador digita valores às cegas (sem ver o sistema)
 *   step2: comparativo sistema vs informado
 *   confirmar: POST que dispara a transação (cria movimentacoes/CR, fecha caixa)
 */
final class PdvConferenciaController
{
    private PdvConferenciaService $service;
    private PdvConferenciaRepository $repo;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvPermissao $permissao
    ) {
        $this->repo = new PdvConferenciaRepository($pdo);
        $this->service = new PdvConferenciaService($pdo, $this->repo, new AuditLogger($pdo));
    }

    public function handleRequest(): void
    {
        $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'step1');

        try {
            match ($action) {
                'step1'     => $this->step1(),
                'step2'     => $this->step2(),
                'confirmar' => $this->confirmar(),
                default     => $this->step1(),
            };
        } catch (RuntimeException $e) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => $e->getMessage()];
            header('Location: ' . tenantCleanUrl('pdv'));
            exit();
        }
    }

    // Step 1: entrada às cegas
    private function step1(): void
    {
        $caixa = $this->caixaDoUsuario();
        $prep = $this->service->prepararConferencia((int)$caixa['id']);

        $this->render('pdv/conferencia-step1', [
            'page_title' => 'Conferência de Caixa — Passo 1',
            'caixa' => $caixa,
            'formas' => $prep['formas'],
            'csrfToken' => CsrfProtection::token(),
            'valoresInformados' => $_SESSION['pdv_conferencia_valores'] ?? [],
        ]);
    }

    // Step 2: comparativo
    private function step2(): void
    {
        $caixa = $this->caixaDoUsuario();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateRequestOrFail();
            $_SESSION['pdv_conferencia_valores'] = $this->extrairValoresInformados();
        }

        $valoresInformados = $_SESSION['pdv_conferencia_valores'] ?? null;
        if (!is_array($valoresInformados) || $valoresInformados === []) {
            header('Location: ' . tenantCleanUrl('pdv/conferencia'));
            exit();
        }

        $prep = $this->service->prepararConferencia((int)$caixa['id']);

        $this->render('pdv/conferencia-step2', [
            'page_title' => 'Conferência de Caixa — Passo 2',
            'caixa' => $caixa,
            'formas' => $prep['formas'],
            'totaisSistema' => $prep['totaisSistema'],
            'valoresInformados' => $valoresInformados,
            'csrfToken' => CsrfProtection::token(),
        ]);
    }

    // Step 3: confirmar = dispara transação
    private function confirmar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . tenantCleanUrl('pdv/conferencia'));
            exit();
        }
        CsrfProtection::validateRequestOrFail();

        $caixa = $this->caixaDoUsuario();
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);

        $valoresInformados = $_SESSION['pdv_conferencia_valores'] ?? [];
        if (!is_array($valoresInformados) || $valoresInformados === []) {
            throw new RuntimeException('Nenhum valor informado. Refaça o passo 1.');
        }

        $r = $this->service->confirmar((int)$caixa['id'], $usuarioId, $valoresInformados);

        if (!$r['ok']) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Falha na conferência: ' . $r['erro']];
            header('Location: ' . tenantCleanUrl('pdv/conferencia/step2'));
            exit();
        }

        unset($_SESSION['pdv_conferencia_valores']);
        $_SESSION['mensagem'] = [
            'tipo' => 'success',
            'texto' => sprintf(
                'Caixa conferido. %d venda(s) processada(s) — %d movimentação(ões), %d conta(s) a receber.',
                $r['vendas_conferidas'],
                $r['movimentacoes'],
                $r['contas_receber']
            ),
        ];
        header('Location: ' . tenantCleanUrl('pdv/caixa/' . (int)$caixa['id'] . '/relatorio'));
        exit();
    }

    // ------------ helpers ------------

    private function caixaDoUsuario(): array
    {
        $this->assertLogado();
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);

        if (!$this->permissao->podeConferirCaixas($usuarioId)
            && !$this->permissao->podeAbrirCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para conferir caixas.');
        }

        $caixa = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);
        if (!is_array($caixa)) {
            throw new RuntimeException('Nenhum caixa aberto para conferir.');
        }
        return $caixa;
    }

    /**
     * @return array<int, float>
     */
    private function extrairValoresInformados(): array
    {
        $raw = $_POST['valor'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $fpId => $valor) {
            $fpIdInt = (int)$fpId;
            if ($fpIdInt <= 0) continue;
            $out[$fpIdInt] = (float)str_replace(',', '.', (string)$valor);
        }
        return $out;
    }

    private function assertLogado(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }
    }

    private function render(string $view, array $dados): void
    {
        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = 'conferencia';
        $GLOBALS['__dm_pdv_caixa_resumo'] = $dados['caixa'] ?? null;

        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/' . $view . '.php';
        require __DIR__ . '/../../../public/includes/footer.php';
    }
}
