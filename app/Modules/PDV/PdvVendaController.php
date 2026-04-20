<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use App\Support\CsrfProtection;
use PDO;
use RuntimeException;

final class PdvVendaController
{
    private PdvVendaService $service;
    private PdvVendaRepository $repo;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvPermissao $permissao
    ) {
        $this->repo = new PdvVendaRepository($pdo);
        $this->service = new PdvVendaService($pdo, $this->repo, new AuditLogger($pdo));
    }

    public function handleRequest(): void
    {
        $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'rapida');

        // Actions que retornam JSON precisam de buffer para evitar que
        // warnings/notices PHP quebrem o response (quando display_errors=On)
        $jsonActions = ['buscar-itens', 'finalizar', 'detalhe', 'cancelar', 'mover'];
        if (in_array($action, $jsonActions, true)) {
            ob_start();
        }

        try {
            match ($action) {
                'rapida'       => $this->rapida(),
                'lista'        => $this->lista(),
                'historico'    => $this->lista(),
                'fluxo'        => $this->fluxo(),
                'buscar-itens' => $this->buscarItensJson(),
                'finalizar'    => $this->finalizarJson(),
                'detalhe'      => $this->detalheJson(),
                'cancelar'     => $this->cancelarPost(),
                'mover'        => $this->moverStatusJson(),
                default        => $this->rapida(),
            };
        } catch (RuntimeException $e) {
            if ($this->isJsonRequest()) {
                $this->json(['ok' => false, 'erros' => [$e->getMessage()]], 422);
            }
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => $e->getMessage()];
            header('Location: ' . tenantCleanUrl('pdv'));
            exit();
        } catch (\Throwable $e) {
            error_log('[PdvVendaController] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if ($this->isJsonRequest()) {
                $this->json(['ok' => false, 'erros' => ['Erro interno: ' . $e->getMessage()]], 500);
            }
            throw $e;
        }
    }

    private function lista(): void
    {
        $this->assertLogado();
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);

        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para operar o PDV.');
        }

        $caixa = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);
        if (!is_array($caixa)) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Abra um caixa antes de acessar o fluxo de vendas.'];
            header('Location: ' . tenantCleanUrl('pdv'));
            exit();
        }

        $filtros = [
            'forma_pagamento_id' => !empty($_GET['forma_pagamento_id']) ? (int)$_GET['forma_pagamento_id'] : null,
            'status' => in_array($_GET['status'] ?? '', ['faturado', 'cancelado'], true) ? $_GET['status'] : null,
            'desde' => !empty($_GET['desde']) ? (string)$_GET['desde'] : null,
            'ate'   => !empty($_GET['ate']) ? (string)$_GET['ate'] . ' 23:59:59' : null,
        ];

        $vendas = $this->repo->listarDoCaixa((int)$caixa['id'], $filtros);
        $totais = $this->repo->totalizadoresDoCaixa((int)$caixa['id']);
        $formas = $this->repo->listarFormasPagamentoAtivas();

        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = 'vendas';
        $GLOBALS['__dm_pdv_caixa_resumo'] = $caixa;

        $dados = [
            'page_title' => 'Histórico de Vendas',
            'caixa' => $caixa,
            'vendas' => $vendas,
            'totais' => $totais,
            'formasPagamento' => $formas,
            'filtros' => $filtros,
            'csrfToken' => CsrfProtection::token(),
        ];

        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/pdv/vendas-lista.php';
        require __DIR__ . '/../../../public/includes/footer.php';
    }

    private function detalheJson(): void
    {
        $this->assertLogado();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->json(['ok' => false, 'erros' => ['ID inválido.']], 422);
        }

        $venda = $this->repo->findById($id);
        if ($venda === null) {
            $this->json(['ok' => false, 'erros' => ['Venda não encontrada.']], 404);
        }

        // Resolve nomes de FK para o modal
        $stmt = $this->pdo->prepare(
            'SELECT fp.nome AS forma_nome, fp.tipo AS forma_tipo, cli.nome AS cliente_nome, u.nome AS operador_nome
               FROM pdv_vendas v
               INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
               INNER JOIN usuarios u ON u.id = v.usuario_id
              WHERE v.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->json([
            'ok' => true,
            'venda' => [
                'id' => $venda->id,
                'numero' => $venda->numero,
                'status' => $venda->status,
                'valor_total' => $venda->valor_total,
                'desconto_tipo' => $venda->desconto_tipo,
                'desconto_valor' => $venda->desconto_valor,
                'observacoes' => $venda->observacoes,
                'created_at' => $venda->created_at,
                'forma_nome' => $meta['forma_nome'] ?? null,
                'cliente_nome' => $meta['cliente_nome'] ?? null,
                'operador_nome' => $meta['operador_nome'] ?? null,
            ],
            'itens' => $venda->itens,
        ]);
    }

    private function fluxo(): void
    {
        $this->assertLogado();
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);

        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para operar o PDV.');
        }

        $caixa = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);
        if (!is_array($caixa)) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Abra um caixa antes de acessar o fluxo.'];
            header('Location: ' . tenantCleanUrl('pdv'));
            exit();
        }

        $colunas = $this->repo->listarKanbanDoCaixa((int)$caixa['id']);
        $formas = $this->repo->listarFormasPagamentoAtivas();

        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = 'fluxo';
        $GLOBALS['__dm_pdv_caixa_resumo'] = $caixa;

        $dados = [
            'page_title' => 'Fluxo de Vendas',
            'caixa' => $caixa,
            'colunas' => $colunas,
            'formasPagamento' => $formas,
            'csrfToken' => CsrfProtection::token(),
        ];

        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/pdv/fluxo-kanban.php';
        require __DIR__ . '/../../../public/includes/footer.php';
    }

    private function moverStatusJson(): void
    {
        $this->assertLogado();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'erros' => ['Método inválido.']], 405);
        }
        CsrfProtection::validateRequestOrFail();

        $usuarioId = (int)($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            $this->json(['ok' => false, 'erros' => ['Sem permissão.']], 403);
        }

        $body = $this->jsonBody();
        $id = (int)($body['id'] ?? 0);
        $status = (string)($body['status'] ?? '');
        $fp = !empty($body['forma_pagamento_id']) ? (int)$body['forma_pagamento_id'] : null;

        if ($id <= 0 || $status === '') {
            $this->json(['ok' => false, 'erros' => ['Parâmetros inválidos.']], 422);
        }

        $r = $this->service->moverStatus($id, $status, $usuarioId, $fp);
        if (!$r['ok']) {
            $this->json(['ok' => false, 'erros' => [$r['erro']]], 422);
        }
        $this->json(['ok' => true]);
    }

    private function cancelarPost(): void
    {
        $this->assertLogado();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'erros' => ['Método inválido.']], 405);
        }
        CsrfProtection::validateRequestOrFail();

        $usuarioId = (int)($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            $this->json(['ok' => false, 'erros' => ['Sem permissão.']], 403);
        }

        $body = $this->jsonBody();
        $id = (int)($body['id'] ?? $_POST['id'] ?? 0);
        $motivo = isset($body['motivo']) ? (string)$body['motivo'] : (isset($_POST['motivo']) ? (string)$_POST['motivo'] : null);

        if ($id <= 0) {
            $this->json(['ok' => false, 'erros' => ['ID inválido.']], 422);
        }

        $r = $this->service->cancelar($id, $usuarioId, $motivo);
        if (!$r['ok']) {
            $this->json(['ok' => false, 'erros' => [$r['erro']]], 422);
        }
        $this->json(['ok' => true]);
    }

    private function rapida(): void
    {
        $this->assertLogado();
        $usuarioId = (int)($_SESSION['user_id'] ?? 0);

        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para operar o PDV.');
        }

        $caixa = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);
        if (!is_array($caixa)) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Abra um caixa antes de vender.'];
            header('Location: ' . tenantCleanUrl('pdv'));
            exit();
        }

        $formas = $this->repo->listarFormasPagamentoAtivas();

        $stmtClientes = $this->pdo->query(
            "SELECT id, nome, cpf_cnpj FROM clientes WHERE ativo = TRUE AND eh_cliente = TRUE ORDER BY nome"
        );
        $clientes = $stmtClientes->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $GLOBALS['__dm_layout_mode'] = 'pdv';
        $GLOBALS['__dm_pdv_active'] = 'venda';
        $GLOBALS['__dm_pdv_caixa_resumo'] = $caixa;

        $dados = [
            'page_title' => 'Venda Rápida',
            'caixa' => $caixa,
            'formasPagamento' => $formas,
            'clientes' => $clientes,
            'csrfToken' => CsrfProtection::token(),
        ];

        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/pdv/venda-rapida.php';
        require __DIR__ . '/../../../public/includes/footer.php';
    }

    private function buscarItensJson(): void
    {
        $this->assertLogado();
        $termo = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($termo) < 1) {
            $this->json(['ok' => true, 'itens' => []]);
        }

        $itens = $this->repo->buscarItens($termo, 40);
        $this->json(['ok' => true, 'itens' => array_map(static function (array $r): array {
            return [
                'tipo' => (string)$r['tipo'],
                'id' => (int)$r['id'],
                'nome' => (string)$r['nome'],
                'codigo' => $r['codigo'] ?? null,
                'valor' => (float)$r['valor'],
            ];
        }, $itens)]);
    }

    private function finalizarJson(): void
    {
        $this->assertLogado();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'erros' => ['Método inválido.']], 405);
        }
        CsrfProtection::validateRequestOrFail();

        $usuarioId = (int)($_SESSION['user_id'] ?? 0);
        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            $this->json(['ok' => false, 'erros' => ['Sem permissão.']], 403);
        }

        $caixa = $this->permissao->getCaixaAbertoDoUsuario($usuarioId);
        if (!is_array($caixa)) {
            $this->json(['ok' => false, 'erros' => ['Nenhum caixa aberto.']], 409);
        }

        $body = $this->jsonBody();

        $payload = [
            'caixa_id' => (int)$caixa['id'],
            'usuario_id' => $usuarioId,
            'cliente_id' => !empty($body['cliente_id']) ? (int)$body['cliente_id'] : null,
            'modo' => (string)($body['modo'] ?? 'pago_agora'),
            'forma_pagamento_id' => !empty($body['forma_pagamento_id']) ? (int)$body['forma_pagamento_id'] : null,
            'desconto_tipo' => $body['desconto_tipo'] ?? null,
            'desconto_valor' => (float)($body['desconto_valor'] ?? 0),
            'observacoes' => isset($body['observacoes']) ? (string)$body['observacoes'] : null,
            'itens' => is_array($body['itens'] ?? null) ? $body['itens'] : [],
        ];

        $resultado = $this->service->finalizar($payload);
        $this->json($resultado, $resultado['ok'] ? 200 : 422);
    }

    // ----- helpers -----

    private function assertLogado(): void
    {
        if (!isset($_SESSION['user_id'])) {
            if ($this->isJsonRequest()) {
                $this->json(['ok' => false, 'erros' => ['Sessão expirada.']], 401);
            }
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }
    }

    private function isJsonRequest(): bool
    {
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json')
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function json(array $payload, int $status = 200): never
    {
        // Descarta qualquer output acidental (warnings, BOM, whitespace) antes do JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit();
    }
}
