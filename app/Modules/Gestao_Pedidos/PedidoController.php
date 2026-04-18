<?php

namespace App\Modules\Gestao_Pedidos;

use App\Modules\Financeiro\ClienteHelperTrait;
use App\Modules\Financeiro\ContaReceberRepository;
use App\Modules\Financeiro\ContaReceberService;
use App\Modules\Orcamento\OrcamentoItemRepository;
use App\Modules\Orcamento\OrcamentoRepository;
use App\Modules\Orcamento\OrcamentoService;
use App\Modules\PDV\PdvRepository;
use App\Modules\PDV\PdvService;
use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use App\Support\PermissionGate;
use PDO;

class PedidoController
{
    use ClienteHelperTrait;

    private PedidoService $service;
    private AuditLogger $audit;
    private ?PdvService $pdvService = null;
    private const BASE_URL = 'admin/pedidos.php';

    public function __construct(private readonly PDO $pdo)
    {
        $orcamentoService = new OrcamentoService(
            $pdo,
            new OrcamentoRepository($pdo),
            new OrcamentoItemRepository($pdo)
        );

        $financeiroService = new ContaReceberService(new ContaReceberRepository($pdo));

        $this->service = new PedidoService(
            $pdo,
            new PedidoRepository($pdo),
            new PedidoItemRepository($pdo),
            null,
            $financeiroService,
            $orcamentoService
        );

        $this->audit = new AuditLogger($pdo);

        $this->verificarAutenticacao();
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'kanban';

        try {
            match ($action) {
                'buscar-clientes' => $this->buscarClientes(),
                'cadastrar-cliente' => $this->cadastrarCliente(),
                'listar' => $this->listar(),
                'kanban' => $this->kanban(),
                'novo' => $this->novo(),
                'editar' => $this->editar((string)($_GET['id'] ?? '')),
                'salvar' => $this->salvar(),
                'index' => $this->index(),
                'store' => $this->store(),
                'show' => $this->show((string)($_GET['id'] ?? '')),
                'update' => $this->update((string)($_GET['id'] ?? $_POST['id'] ?? '')),
                'cancel' => $this->cancel((string)($_GET['id'] ?? $_POST['id'] ?? '')),
                'invoice' => $this->invoice((string)($_GET['id'] ?? $_POST['id'] ?? '')),
                'uninvoice' => $this->uninvoice((string)($_GET['id'] ?? $_POST['id'] ?? '')),
                default => $this->json(['success' => false, 'message' => 'Ação inválida.'], 404),
            };
        } catch (\Throwable $e) {
            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $this->flash('error', $e->getMessage());
            $this->redirect();
        }
    }

    public function listar(): void
    {
        $filtros = $this->filtrosRequest();
        $resultado = $this->service->listarPedidos($filtros);

        $this->view('pedidos/index', [
            'page_title' => 'Lista de Pedidos',
            'pedidos' => $resultado['dados'] ?? [],
            'total' => (int)($resultado['total'] ?? 0),
            'paginaAtual' => (int)($resultado['pagina'] ?? 1),
            'totalPaginas' => (int)($resultado['total_paginas'] ?? 1),
            'filtros' => [
                'busca' => (string)($filtros['busca'] ?? ''),
                'status' => (string)($filtros['status'] ?? ''),
                'data_inicio' => (string)($filtros['data_inicio'] ?? ''),
                'data_fim' => (string)($filtros['data_fim'] ?? ''),
            ],
            'statusOpcoes' => Pedido::STATUS_VALIDOS,
        ]);
    }

    public function kanban(): void
    {
        $filtros = $this->filtrosRequest();
        
        // Não filtrar por um status específico no Kanban
        unset($filtros['status']);

        // Buscar todos os pedidos ativos
        $filtros['ativo'] = true;

        // Modo kanban: filtra por data_entrega_prevista em vez de data_pedido
        // e exclui pedidos FATURADOS automaticamente
        $filtros['kanban_mode'] = true;

        // Padrão: data_inicio = hoje (entrega prevista >= hoje)
        // Sem data_fim padrão — mostrar todos os pedidos com entrega futura/sem data
        $hoje = $this->obterDataBrasilia();
        if (empty($filtros['data_inicio'])) {
            $filtros['data_inicio'] = $hoje;
        }
        
        $resultado = $this->service->listarPedidos($filtros);

        $this->view('pedidos/kanban', [
            'page_title' => 'Fluxo de Pedidos',
            'pedidos' => $resultado['dados'] ?? [],
            'total' => (int)($resultado['total'] ?? 0),
            'filtros' => [
                'busca' => (string)($filtros['busca'] ?? ''),
                'data_inicio' => (string)($filtros['data_inicio'] ?? ''),
                'data_fim' => (string)($filtros['data_fim'] ?? ''),
            ],
        ]);
    }

    public function index(): void
    {
        $filtros = $this->filtrosRequest();

        $resultado = $this->service->listarPedidos($filtros);
        $this->json(['success' => true, 'data' => $resultado]);
    }

    public function novo(): void
    {
        $this->view('pedidos/form', [
            'page_title' => 'Novo Pedido',
            'editando' => false,
            'pedido' => null,
            'clientes' => $this->service->listarClientes(),
            'produtos' => $this->service->listarProdutos(),
            'orcamentosAprovados' => $this->service->listarOrcamentosAprovadosSemPedido(),
        ]);
    }

    public function editar(string $id): void
    {
        if ($id === '') {
            $this->flash('error', 'Pedido nao encontrado.');
            $this->redirect();
        }

        $pedido = $this->service->buscarPedidoPorId($id);
        if ($pedido === null) {
            $this->flash('error', 'Pedido nao encontrado.');
            $this->redirect();
        }

        if (in_array($pedido->status, [Pedido::STATUS_FATURADO, Pedido::STATUS_CANCELADO], true)) {
            $this->flash('error', 'Pedidos faturados ou cancelados nao podem ser editados.');
            $this->redirect(self::BASE_URL . '?action=show&id=' . urlencode($id));
        }

        $this->view('pedidos/form', [
            'page_title' => 'Editar Pedido #' . ($pedido->numero ?? $pedido->id),
            'editando' => true,
            'pedido' => $pedido,
            'clientes' => $this->service->listarClientes(),
            'produtos' => $this->service->listarProdutos(),
            'orcamentosAprovados' => [],
        ]);
    }

    public function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        $payload = $_POST;
        $pedidoId = (string)($payload['id'] ?? '');
        $payload['tipo_criacao'] = (string)($payload['tipo_criacao'] ?? 'novo');
        $payload['itens'] = $_POST['itens'] ?? [];

        if ($pedidoId !== '') {
            $this->validarUpdate($payload);
            $pedido = $this->service->atualizarPedido($pedidoId, $payload);
            $pedido = $this->processarLancamentoPdvPedido($pedido, $payload);

            $this->audit->registrar(
                'pedidos',
                'ATUALIZAR',
                'pedido',
                null,
                'Pedido atualizado.',
                [
                    'pedido_id' => $pedido->id,
                    'pedido_numero' => $pedido->numero,
                ]
            );

            $this->flash('success', 'Pedido atualizado com sucesso.');
            $this->redirect(self::BASE_URL . '?action=show&id=' . urlencode($pedido->id));
        }

        $this->validarStore($payload);

        $tipoCriacao = (string)$payload['tipo_criacao'];
        $pedido = $this->service->criarPedido($payload, $tipoCriacao);
        $pedido = $this->processarLancamentoPdvPedido($pedido, $payload);

        $this->audit->registrar(
            'pedidos',
            'CRIAR',
            'pedido',
            null,
            'Pedido criado.',
            [
                'pedido_id' => $pedido->id,
                'pedido_numero' => $pedido->numero,
                'orcamento_id' => $pedido->orcamento_id,
                'tipo_criacao' => $tipoCriacao,
            ]
        );

        $identificador = $pedido->numero ?? $pedido->id;
        $this->flash('success', 'Pedido criado com sucesso: ' . $identificador);
        $this->redirect();
    }

    public function store(): void
    {
        $payload = $this->input();
        $this->validarStore($payload);

        $tipoCriacao = (string)($payload['tipo_criacao'] ?? 'novo');
        $pedido = $this->service->criarPedido($payload, $tipoCriacao);

        $this->audit->registrar(
            'pedidos',
            'CRIAR',
            'pedido',
            null,
            'Pedido criado via API.',
            [
                'pedido_id' => $pedido->id,
                'pedido_numero' => $pedido->numero,
                'orcamento_id' => $pedido->orcamento_id,
                'tipo_criacao' => $tipoCriacao,
            ]
        );

        $this->json(['success' => true, 'data' => $this->pedidoResponse($pedido)], 201);
    }

    public function show(string $id): void
    {
        if ($id === '') {
            $this->json(['success' => false, 'message' => 'ID do pedido é obrigatório.'], 400);
        }

        $pedido = $this->service->buscarPedidoPorId($id);
        if ($pedido === null) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => 'Pedido não encontrado.'], 404);
            }

            $this->flash('error', 'Pedido não encontrado.');
            $this->redirect();
        }

        if (!$this->wantsJson()) {
            $this->view('pedidos/visualizar', [
                'page_title' => 'Pedido #' . ($pedido->numero ?? $pedido->id),
                'pedido' => $pedido,
                'usuarioCriador' => $this->service->buscarUsuarioCriadorPedido($pedido),
            ]);
            return;
        }

        $this->json([
            'success' => true,
            'data' => $this->pedidoResponse($pedido),
            'redirect' => $this->route(self::BASE_URL . '?action=show&id=' . urlencode((string)$pedido->id)),
        ]);
    }

    public function update(string $id): void
    {
        if ($id === '') {
            $this->json(['success' => false, 'message' => 'ID do pedido é obrigatório.'], 400);
        }

        $payload = $this->input();
        $this->validarUpdate($payload);

        $pedido = $this->service->atualizarPedido($id, $payload);
        $this->json(['success' => true, 'data' => $this->pedidoResponse($pedido)]);
    }

    public function cancel(string $id): void
    {
        if ($id === '') {
            $this->json(['success' => false, 'message' => 'ID do pedido é obrigatório.'], 400);
        }

        $pedido = $this->service->cancelarPedido($id);

        if (!$this->wantsJson()) {
            $this->flash('success', 'Pedido cancelado com sucesso.');
            $this->redirect();
        }

        $this->json(['success' => true, 'data' => $this->pedidoResponse($pedido)]);
    }

    public function invoice(string $id): void
    {
        if ($id === '') {
            $this->json(['success' => false, 'message' => 'ID do pedido é obrigatório.'], 400);
        }

        $dadosFaturamento = [
            'forma_pagamento_id' => (int)($_POST['forma_pagamento_id'] ?? $_GET['forma_pagamento_id'] ?? 0),
            'data_vencimento' => trim((string)($_POST['data_vencimento'] ?? $_GET['data_vencimento'] ?? '')),
            'data_faturamento' => trim((string)($_POST['data_faturamento'] ?? $_GET['data_faturamento'] ?? date('Y-m-d'))),
        ];

        try {
            $pedido = $this->service->faturarPedido($id, $dadosFaturamento);
        } catch (\Throwable $e) {
            $requerConfiguracao = str_contains($e->getMessage(), 'nao possui adquirente e/ou prazo cadastrado')
                || str_contains($e->getMessage(), 'nao possui banco configurado');

            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'requires_payment_config' => $requerConfiguracao,
                    'redirect_url' => $requerConfiguracao ? \App\Modules\Financeiro\FormaPagamentoFinanceiroService::CADASTRO_URL : null,
                ], 422);
            }

            throw $e;
        }

        if (!$this->wantsJson()) {
            $this->flash('success', 'Pedido faturado com sucesso.');
            $this->redirect(self::BASE_URL . '?action=show&id=' . urlencode($id));
        }

        $this->json(['success' => true, 'data' => $this->pedidoResponse($pedido)]);
    }

    public function uninvoice(string $id): void
    {
        if ($id === '') {
            $this->json(['success' => false, 'message' => 'ID do pedido é obrigatório.'], 400);
        }

        $pedido = $this->service->estornarFaturamentoPedido($id);

        if (!$this->wantsJson()) {
            $this->flash('success', 'Faturamento do pedido estornado com sucesso.');
            $this->redirect();
        }

        $this->json(['success' => true, 'data' => $this->pedidoResponse($pedido)]);
    }

    private function validarStore(array $payload): void
    {
        $tipo = (string)($payload['tipo_criacao'] ?? 'novo');
        if ($tipo === 'deOrcamento') {
            if (empty($payload['orcamento_id'])) {
                throw new \RuntimeException('orcamento_id é obrigatório para tipo_criacao=deOrcamento.');
            }

            if (empty($payload['cliente_id'])) {
                return;
            }

            return;
        }

        if (empty($payload['cliente_id'])) {
            throw new \RuntimeException('cliente_id é obrigatório.');
        }

        if (!empty($payload['data_entrega_prevista'])) {
            date_default_timezone_set('America/Sao_Paulo');
            $hoje = date('Y-m-d');
            if ($payload['data_entrega_prevista'] < $hoje) {
                throw new \RuntimeException('A data de entrega prevista não pode ser anterior à data atual.');
            }
        }

        if (empty($payload['itens']) || !is_array($payload['itens'])) {
            throw new \RuntimeException('itens é obrigatório.');
        }

        $linha = 0;
        foreach ($payload['itens'] as $item) {
            $linha++;
            if (empty($item['produto_id']) && empty($item['nome_produto'])) {
                throw new \RuntimeException("itens.{$linha}: produto_id ou nome_produto é obrigatório.");
            }
            if ((float)($item['quantidade'] ?? 0) <= 0) {
                throw new \RuntimeException("itens.{$linha}: quantidade deve ser maior que zero.");
            }
            if ((float)($item['valor_unitario'] ?? 0) < 0) {
                throw new \RuntimeException("itens.{$linha}: valor_unitario não pode ser negativo.");
            }
        }
        $this->validarDescontoPedido($payload);
    }

    private function validarUpdate(array $payload): void
    {
        if (isset($payload['itens']) && !is_array($payload['itens'])) {
            throw new \RuntimeException('itens deve ser um array.');
        }

        if (!isset($payload['itens'])) {
            return;
        }

        $linha = 0;
        foreach ($payload['itens'] as $item) {
            $linha++;
            if (empty($item['produto_id']) && empty($item['nome_produto'])) {
                throw new \RuntimeException("itens.{$linha}: produto_id ou nome_produto é obrigatório.");
            }
            if ((float)($item['quantidade'] ?? 0) <= 0) {
                throw new \RuntimeException("itens.{$linha}: quantidade deve ser maior que zero.");
            }
            if ((float)($item['valor_unitario'] ?? 0) < 0) {
                throw new \RuntimeException("itens.{$linha}: valor_unitario não pode ser negativo.");
            }
        }
        $this->validarDescontoPedido($payload);
    }

    private function validarDescontoPedido(array $payload): void
    {
        $tipo = strtoupper(trim((string)($payload['desconto_tipo'] ?? '')));
        $valor = (float)str_replace(',', '.', (string)($payload['desconto_valor'] ?? '0'));

        if ($tipo === '' || $valor <= 0) {
            return;
        }

        if (!in_array($tipo, ['VALOR', 'PERCENTUAL'], true)) {
            throw new \RuntimeException('Tipo de desconto do pedido invalido.');
        }

        if ($valor < 0) {
            throw new \RuntimeException('O desconto do pedido nao pode ser negativo.');
        }

        if ($tipo === 'PERCENTUAL' && $valor > 100) {
            throw new \RuntimeException('O desconto percentual nao pode ser maior que 100%.');
        }
    }

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
        }

        PermissionGate::init($this->pdo);
        if ($this->isPdvSharedContext() && $this->usuarioPodeOperarPdv()) {
            return;
        }

        if (!PermissionGate::can('pedidos')) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => 'Sem permissão para acessar pedidos.'], 403);
            }

            $this->flash('error', 'Sem permissão para acessar pedidos.');
            header('Location: ' . $this->route('admin/dashboard.php'));
            exit();
        }
    }

    private function filtrosRequest(): array
    {
        return [
            'status' => $_GET['status'] ?? null,
            'cliente_id' => $_GET['cliente_id'] ?? null,
            'busca' => trim((string)($_GET['busca'] ?? '')),
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null,
            'pagina' => (int)($_GET['pagina'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 15),
        ];
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

    private function redirect(?string $url = null): never
    {
        header('Location: ' . $this->route($url ?? self::BASE_URL));
        exit();
    }

    private function route(string $path): string
    {
        if ($path === self::BASE_URL) {
            return function_exists('dmContextUrl')
                ? dmContextUrl('__dm_pedido_base_url', self::BASE_URL)
                : (function_exists('tenantUrl') ? \tenantUrl($path) : '/' . ltrim($path, '/'));
        }

        if (str_starts_with($path, self::BASE_URL . '?')) {
            $baseUrl = function_exists('dmContextUrl')
                ? dmContextUrl('__dm_pedido_base_url', self::BASE_URL)
                : (function_exists('tenantUrl') ? \tenantUrl(self::BASE_URL) : '/' . ltrim(self::BASE_URL, '/'));
            $query = substr($path, strlen(self::BASE_URL . '?'));

            return function_exists('dmBuildUrl')
                ? dmBuildUrl($baseUrl, $query)
                : $baseUrl . '?' . ltrim($query, '?&');
        }

        return function_exists('tenantUrl') ? \tenantUrl($path) : '/' . ltrim($path, '/');
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $format = strtolower((string)($_GET['format'] ?? $_POST['format'] ?? ''));

        return $format === 'json'
            || str_contains($accept, 'application/json')
            || $xrw === 'xmlhttprequest'
            || in_array((string)($_GET['action'] ?? ''), ['index', 'store', 'update'], true);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function pedidoResponse(Pedido $pedido): array
    {
        return $pedido->toArray() + [
            'cliente' => $pedido->cliente,
            'orcamento' => $pedido->orcamento,
            'itens' => array_map(static fn(PedidoItem $i): array => $i->toArray(), $pedido->itens),
        ];
    }

    private function input(): array
    {
        $payload = $_POST;

        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $payload = $json + $payload;
            }
        }

        return $payload;
    }

    private function json(array $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados);
        exit();
    }

    /**
     * Obtém a data atual no fuso horário de Brasília (UTC-3)
     * @return string Data no formato 'Y-m-d'
     */
    private function obterDataBrasilia(): string
    {
        date_default_timezone_set('America/Sao_Paulo');
        return date('Y-m-d');
    }

    private function processarLancamentoPdvPedido(Pedido $pedido, array $payload): Pedido
    {
        if (!$this->isPdvSharedContext()) {
            return $pedido;
        }

        $caixaId = (int)($payload['caixa_id'] ?? ($_SESSION['pdv_caixa_id'] ?? 0));
        if ($caixaId <= 0) {
            throw new \RuntimeException('Selecione um caixa aberto para continuar no PDV.');
        }

        $formaPagamento = $this->normalizarFormaPagamentoPdv($payload['pdv_forma_pagamento'] ?? '');
        if ($formaPagamento === '') {
            throw new \RuntimeException('Selecione a forma de pagamento do PDV.');
        }

        if ($pedido->status !== Pedido::STATUS_FATURADO) {
            $dadosFaturamento = [
                'forma_pagamento_id' => $this->pdvService()->getFormaPagamentoFinanceiraId($formaPagamento),
                'data_faturamento' => trim((string)($payload['pdv_data_faturamento'] ?? date('Y-m-d'))),
                'data_vencimento' => trim((string)($payload['pdv_data_vencimento'] ?? ($formaPagamento === 'a_faturar' ? date('Y-m-d') : ''))),
            ];

            $pedido = $this->service->faturarPedido($pedido->id, $dadosFaturamento);
        }

        $this->pdvService()->registrarLancamentoPedido(
            $pedido->id,
            $caixaId,
            $formaPagamento,
            (int)($_SESSION['user_id'] ?? 0)
        );

        return $this->service->buscarPedidoPorId($pedido->id) ?? $pedido;
    }

    private function pdvService(): PdvService
    {
        if ($this->pdvService instanceof PdvService) {
            return $this->pdvService;
        }

        $this->pdvService = new PdvService(
            $this->pdo,
            new PdvRepository($this->pdo),
            new PdvPermissao($this->pdo),
            $this->audit
        );

        return $this->pdvService;
    }

    private function isPdvSharedContext(): bool
    {
        return !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
    }

    private function usuarioPodeOperarPdv(): bool
    {
        return (new PdvPermissao($this->pdo))->usuarioAtualPodeOperarPdv();
    }

    private function normalizarFormaPagamentoPdv(mixed $formaPagamento): string
    {
        $forma = strtolower(trim((string)$formaPagamento));

        return in_array($forma, ['dinheiro', 'cartao', 'pix', 'a_faturar'], true)
            ? $forma
            : '';
    }
}
