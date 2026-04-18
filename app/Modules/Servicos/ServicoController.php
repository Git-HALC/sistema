<?php

namespace App\Modules\Servicos;

use App\Modules\Fiscal\NotaFiscalRepository;
use App\Modules\FiscalServico\NotaFiscalServicoRepository;
use App\Modules\Financeiro\ClienteHelperTrait;
use App\Modules\Financeiro\FormaPagamentoFinanceiroService;
use App\Modules\PDV\PdvRepository;
use App\Modules\PDV\PdvService;
use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use App\Support\PermissionGate;
use PDO;

class ServicoController
{
    use ClienteHelperTrait;

    private const BASE_URL = 'admin/servicos.php';
    private ServicoService $service;
    private AuditLogger $audit;
    private ?PdvService $pdvService = null;

    public function __construct(private readonly PDO $pdo)
    {
        $this->service = new ServicoService(
            $pdo,
            new ServicoRepository($pdo),
            new ServicoCatalogoRepository($pdo),
            new ServicoItemRepository($pdo)
        );
        $this->audit = new AuditLogger($pdo);
        $this->verificarAutenticacao();
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'kanban';

        match ($action) {
            'buscar-clientes' => $this->buscarClientes(),
            'cadastrar-cliente' => $this->cadastrarCliente(),
            'buscar-produtos-cliente' => $this->buscarProdutosCliente(),
            'catalogo' => $this->catalogo(),
            'salvar-catalogo' => $this->salvarCatalogo(),
            'excluir-catalogo' => $this->excluirCatalogo(),
            'listar' => $this->listar(),
            'kanban' => $this->kanban(),
            'novo' => $this->novo(),
            'editar' => $this->editar((string)($_GET['id'] ?? '')),
            'salvar' => $this->salvar(),
            'visualizar' => $this->visualizar((string)($_GET['id'] ?? '')),
            'faturar' => $this->faturar((string)($_GET['id'] ?? '')),
            'estornar' => $this->estornar((string)($_GET['id'] ?? '')),
            'cancelar' => $this->cancelar((string)($_GET['id'] ?? '')),
            'excluir' => $this->excluir((string)($_GET['id'] ?? '')),
            default => $this->kanban(),
        };
    }

    public function listar(): void
    {
        $filtros = $this->filtrosRequest();
        $resultado = $this->service->listarServicos($filtros);

        $this->view('servicos/index', [
            'page_title' => 'Lista de Servicos',
            'servicos' => $resultado['dados'] ?? [],
            'total' => (int)($resultado['total'] ?? 0),
            'paginaAtual' => (int)($resultado['pagina'] ?? 1),
            'totalPaginas' => (int)($resultado['total_paginas'] ?? 1),
            'filtros' => $filtros,
            'statusOpcoes' => Servico::STATUS_VALIDOS,
        ]);
    }

    public function kanban(): void
    {
        $filtros = $this->filtrosRequest();
        unset($filtros['status']);
        $filtros['ativo'] = true;
        $filtros['kanban_mode'] = true;
        if (empty($filtros['data_inicio'])) {
            $filtros['data_inicio'] = $this->obterDataBrasilia();
        }

        $resultado = $this->service->listarServicos($filtros);

        $this->view('servicos/kanban', [
            'page_title' => 'Fluxo de Servicos',
            'servicos' => $resultado['dados'] ?? [],
            'total' => (int)($resultado['total'] ?? 0),
            'filtros' => $filtros,
        ]);
    }

    public function novo(): void
    {
        $this->view('servicos/form', [
            'page_title' => 'Iniciar Novo Servico',
            'editando' => false,
            'servico' => null,
            'clientes' => $this->service->listarClientes(),
            'catalogo' => $this->service->listarCatalogo(true),
            'produtosCliente' => [],
        ]);
    }

    public function editar(string $id): void
    {
        if ($id === '') {
            $this->flash('error', 'Servico nao encontrado.');
            $this->redirect('action=listar');
        }

        $servico = $this->service->buscarServico($id);
        if ($servico === null) {
            $this->flash('error', 'Servico nao encontrado.');
            $this->redirect('action=listar');
        }

        if (in_array($servico->status, [Servico::STATUS_FATURADO, Servico::STATUS_CANCELADO], true)) {
            $this->flash('error', 'Servicos faturados ou cancelados nao podem ser editados.');
            $this->redirect('action=visualizar&id=' . urlencode($id));
        }

        $this->view('servicos/form', [
            'page_title' => 'Editar Servico #' . ($servico->numero ?? $servico->id),
            'editando' => true,
            'servico' => $servico,
            'clientes' => $this->service->listarClientes(),
            'catalogo' => $this->service->listarCatalogo(true),
            'produtosCliente' => $servico->clienteId !== null ? $this->service->listarProdutosCompradosPorCliente((int)$servico->clienteId) : [],
        ]);
    }

    public function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect();
        }

        $servicoId = trim((string)($_POST['id'] ?? ''));
        $dados = [
            'id' => $servicoId !== '' ? $servicoId : null,
            'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
            'usuario_id' => $_SESSION['user_id'] ?? null,
            'servico_catalogo_id' => $_POST['servico_catalogo_id'] ?? null,
            'nome_cliente' => trim((string)($_POST['nome_cliente'] ?? '')),
            'telefone_cliente' => trim((string)($_POST['telefone_cliente'] ?? '')),
            'servico_nome' => trim((string)($_POST['servico_nome'] ?? '')),
            'servico_valor' => str_replace(',', '.', (string)($_POST['servico_valor'] ?? $_POST['valor_total'] ?? '0')),
            'placa' => trim((string)($_POST['placa'] ?? '')),
            'modelo_veiculo' => trim((string)($_POST['modelo_veiculo'] ?? '')),
            'desconto_tipo' => trim((string)($_POST['desconto_tipo'] ?? '')),
            'desconto_valor' => str_replace(',', '.', (string)($_POST['desconto_valor'] ?? '0')),
            'valor_total' => str_replace(',', '.', (string)($_POST['valor_total'] ?? '0')),
            'observacoes' => trim((string)($_POST['observacoes'] ?? '')),
            'itens' => $_POST['itens'] ?? [],
        ];

        $errors = $this->service->validarDadosServico($dados);
        if (!empty($errors)) {
            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Corrija os campos destacados.',
                    'errors' => $errors,
                ], 422);
            }

            $this->flash('error', reset($errors) ?: 'Corrija os campos obrigatorios.');
            $this->redirect('action=novo');
        }

        try {
            $servico = $servicoId !== ''
                ? $this->service->atualizarServico($servicoId, $dados)
                : $this->service->criarServico($dados);
            $servico = $this->processarLancamentoPdvServico($servico, $_POST);

            $this->audit->registrar('servicos', $servicoId !== '' ? 'ATUALIZAR' : 'CRIAR', 'servico', null, $servicoId !== '' ? 'Servico atualizado.' : 'Servico criado.', [
                'servico_id' => $servico->id,
                'servico_numero' => $servico->numero,
            ]);

            if ($this->isAjax()) {
                $this->json([
                    'success' => true,
                    'message' => $servicoId !== '' ? 'Servico atualizado com sucesso.' : 'Servico iniciado com sucesso.',
                    'redirect' => $this->buildUrl($servicoId !== '' ? 'action=visualizar&id=' . urlencode((string)$servico->id) : 'action=kanban'),
                    'servico' => [
                        'id' => $servico->id,
                        'numero' => $servico->numero,
                    ],
                ]);
            }

            $this->flash('success', $servicoId !== '' ? 'Servico atualizado com sucesso.' : 'Servico iniciado com sucesso.');
            $this->redirect($servicoId !== '' ? 'action=visualizar&id=' . urlencode((string)$servico->id) : 'action=kanban');
        } catch (\Throwable $e) {
            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [],
                ], 422);
            }

            $this->flash('error', $e->getMessage());
            $this->redirect('action=novo');
        }
    }

    public function visualizar(string $id): void
    {
        $servico = $this->service->buscarServico($id);
        if ($servico === null) {
            $this->flash('error', 'Servico nao encontrado.');
            $this->redirect('action=listar');
        }

        $usuarioCriador = 'Nao identificado';
        if (($servico->usuarioId ?? null) !== null) {
            $stmt = $this->pdo->prepare('SELECT nome FROM usuarios WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$servico->usuarioId]);
            $nomeUsuario = $stmt->fetchColumn();
            if (!empty($nomeUsuario)) {
                $usuarioCriador = (string)$nomeUsuario;
            }
        }

        $faturamento = [
            'conta_receber_id' => null,
            'forma_conta' => null,
            'forma_recebimento' => null,
        ];

        $stmtContaReceber = $this->pdo->prepare("
            SELECT
                cr.id,
                fp.nome AS forma_conta,
                fp_receb.nome AS forma_recebimento
            FROM contas_receber cr
            LEFT JOIN formas_pagamento fp ON fp.id = cr.forma_pagamento_id
            LEFT JOIN LATERAL (
                SELECT m.forma_pagamento_id
                FROM movimentacoes m
                WHERE m.conta_receber_id = cr.id
                  AND m.tipo_origem = 'RECEBIMENTO'
                  AND m.forma_pagamento_id IS NOT NULL
                ORDER BY m.id DESC
                LIMIT 1
            ) ult_mov ON TRUE
            LEFT JOIN formas_pagamento fp_receb ON fp_receb.id = ult_mov.forma_pagamento_id
            WHERE cr.servico_id = :servico_id
            ORDER BY cr.id DESC
            LIMIT 1
        ");
        $stmtContaReceber->execute([':servico_id' => $servico->id]);
        $contaReceber = $stmtContaReceber->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($contaReceber !== null) {
            $faturamento['conta_receber_id'] = isset($contaReceber['id']) ? (int)$contaReceber['id'] : null;
            $faturamento['forma_conta'] = !empty($contaReceber['forma_conta']) ? (string)$contaReceber['forma_conta'] : null;
            $faturamento['forma_recebimento'] = !empty($contaReceber['forma_recebimento']) ? (string)$contaReceber['forma_recebimento'] : null;
        }

        if ($faturamento['forma_conta'] === null) {
            $stmtMovimentacao = $this->pdo->prepare("
                SELECT fp.nome
                FROM movimentacoes m
                LEFT JOIN formas_pagamento fp ON fp.id = m.forma_pagamento_id
                WHERE m.servico_id = :servico_id
                  AND m.forma_pagamento_id IS NOT NULL
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stmtMovimentacao->execute([':servico_id' => $servico->id]);
            $formaMovimentacao = $stmtMovimentacao->fetchColumn();
            if ($formaMovimentacao !== false && $formaMovimentacao !== null && $formaMovimentacao !== '') {
                $faturamento['forma_conta'] = (string)$formaMovimentacao;
            }
        }

        $notaFiscalProduto = (new NotaFiscalRepository($this->pdo))->findByServicoId($servico->id);
        $notaFiscalServico = (new NotaFiscalServicoRepository($this->pdo))->buscarPorServicoId($servico->id);

        $this->view('servicos/visualizar', [
            'page_title' => 'Serviço #' . ($servico->numero ?? $servico->id),
            'servico' => $servico,
            'usuarioCriador' => $usuarioCriador,
            'faturamento' => $faturamento,
            'notaFiscalProduto' => $notaFiscalProduto,
            'notaFiscalServico' => $notaFiscalServico,
        ]);
    }

    public function cancelar(string $id): void
    {
        try {
            $this->service->cancelarServico($id);
            $this->flash('success', 'Servico cancelado com sucesso.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=listar');
    }

    public function estornar(string $id): void
    {
        try {
            $this->service->estornarFaturamentoServico($id);
            $this->flash('success', 'Faturamento do servico estornado com sucesso.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=listar');
    }

    public function excluir(string $id): void
    {
        try {
            $this->service->excluirServico($id);
            $this->flash('success', 'Servico excluido com sucesso.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=listar');
    }

    public function faturar(string $id): void
    {
        $dadosFaturamento = [
            'forma_pagamento_id' => (int)($_POST['forma_pagamento_id'] ?? $_GET['forma_pagamento_id'] ?? 0),
            'data_vencimento' => trim((string)($_POST['data_vencimento'] ?? $_GET['data_vencimento'] ?? '')),
            'data_faturamento' => trim((string)($_POST['data_faturamento'] ?? $_GET['data_faturamento'] ?? date('Y-m-d'))),
        ];

        try {
            $servico = $this->service->faturarServico($id, $dadosFaturamento);
            $this->audit->registrar('servicos', 'FATURAR', 'servico', null, 'Servico faturado.', [
                'servico_id' => $servico->id,
                'servico_numero' => $servico->numero,
            ]);

            if ($this->isAjax()) {
                $this->json([
                    'success' => true,
                    'message' => 'Servico faturado com sucesso.',
                    'redirect' => $this->buildUrl('action=visualizar&id=' . urlencode((string)$servico->id)),
                ]);
            }

            $this->flash('success', 'Servico faturado com sucesso.');
        } catch (\Throwable $e) {
            $requerConfiguracao = str_contains($e->getMessage(), 'nao possui adquirente e/ou prazo cadastrado')
                || str_contains($e->getMessage(), 'nao possui banco configurado');

            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'requires_payment_config' => $requerConfiguracao,
                    'redirect_url' => $requerConfiguracao ? FormaPagamentoFinanceiroService::CADASTRO_URL : null,
                ], 422);
            }

            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=visualizar&id=' . urlencode($id));
    }

    public function catalogo(): void
    {
        $catalogoEdicao = null;
        if (!empty($_GET['id'])) {
            foreach ($this->service->listarCatalogo(false) as $item) {
                if ($item->id === (int)$_GET['id']) {
                    $catalogoEdicao = $item;
                    break;
                }
            }
        }

        $this->view('servicos/catalogo', [
            'page_title' => 'Cadastro de Servicos',
            'servicosCatalogo' => $this->service->listarCatalogo(false),
            'catalogoEdicao' => $catalogoEdicao,
        ]);
    }

    public function salvarCatalogo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('action=catalogo');
        }

        try {
            $id = $this->service->salvarCatalogo([
                'id' => $_POST['id'] ?? null,
                'nome' => $_POST['nome'] ?? '',
                'descricao' => $_POST['descricao'] ?? '',
                'valor_base' => $_POST['valor_base'] ?? '0',
                'ativo' => isset($_POST['ativo']),
            ]);

            $this->audit->registrar('servicos', !empty($_POST['id']) ? 'ATUALIZAR' : 'CRIAR', 'servico_catalogo', $id, 'Cadastro de servico salvo.');
            $this->flash('success', 'Cadastro de servico salvo com sucesso.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=catalogo');
    }

    public function excluirCatalogo(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $this->flash('error', 'Servico invalido para exclusao.');
            $this->redirect('action=catalogo');
        }

        try {
            $this->service->excluirCatalogo($id);
            $this->audit->registrar('servicos', 'EXCLUIR', 'servico_catalogo', $id, 'Servico removido do catalogo.');
            $this->flash('success', 'Servico excluido com sucesso.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('action=catalogo');
    }

    public function buscarProdutosCliente(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $clienteId = (int)($_GET['cliente_id'] ?? $_POST['cliente_id'] ?? 0);
        echo json_encode([
            'success' => true,
            'data' => $clienteId > 0 ? $this->service->listarProdutosCompradosPorCliente($clienteId) : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function filtrosRequest(): array
    {
        return [
            'busca' => trim((string)($_GET['busca'] ?? '')),
            'status' => trim((string)($_GET['status'] ?? '')),
            'data_inicio' => trim((string)($_GET['data_inicio'] ?? '')),
            'data_fim' => trim((string)($_GET['data_fim'] ?? '')),
            'pagina' => max(1, (int)($_GET['pagina'] ?? 1)),
        ];
    }

    private function verificarAutenticacao(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit;
        }

        PermissionGate::init($this->pdo);
        if ($this->isPdvSharedContext() && $this->usuarioPodeOperarPdv()) {
            return;
        }

        if (!PermissionGate::can('servicos')) {
            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => 'Sem permissao para acessar servicos.',
                ], 403);
            }

            $this->flash('error', 'Sem permissao para acessar servicos.');
            header('Location: ' . tenantUrl('admin/dashboard.php'));
            exit;
        }
    }

    private function redirect(string $query = ''): void
    {
        header('Location: ' . $this->buildUrl($query));
        exit;
    }

    private function buildUrl(string $query = ''): string
    {
        $destino = function_exists('dmContextUrl')
            ? dmContextUrl('__dm_servico_base_url', self::BASE_URL)
            : tenantUrl(self::BASE_URL);

        if ($query !== '') {
            return function_exists('dmBuildUrl')
                ? dmBuildUrl($destino, $query)
                : $destino . (str_contains($destino, '?') ? '&' : '?') . ltrim($query, '?&');
        }

        return $destino;
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function json(array $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require_once __DIR__ . '/../../../public/includes/header.php';

        $pathView = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($pathView)
            ? require $pathView
            : print "<div class='container mt-4'><div class='alert alert-danger'>View nao encontrada: {$view}</div></div>";

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function isPdvSharedContext(): bool
    {
        return !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
    }

    private function usuarioPodeOperarPdv(): bool
    {
        return (new PdvPermissao($this->pdo))->usuarioAtualPodeOperarPdv();
    }

    private function processarLancamentoPdvServico(Servico $servico, array $payload): Servico
    {
        if (!$this->isPdvSharedContext()) {
            return $servico;
        }

        $caixaId = (int)($payload['caixa_id'] ?? ($_SESSION['pdv_caixa_id'] ?? 0));
        if ($caixaId <= 0) {
            throw new \RuntimeException('Selecione um caixa aberto para continuar no PDV.');
        }

        $formaPagamento = $this->normalizarFormaPagamentoPdv($payload['pdv_forma_pagamento'] ?? '');
        if ($formaPagamento === '') {
            throw new \RuntimeException('Selecione a forma de pagamento do PDV.');
        }

        if ($servico->status !== Servico::STATUS_FATURADO) {
            $dadosFaturamento = [
                'forma_pagamento_id' => $this->pdvService()->getFormaPagamentoFinanceiraId($formaPagamento),
                'data_faturamento' => trim((string)($payload['pdv_data_faturamento'] ?? date('Y-m-d'))),
                'data_vencimento' => trim((string)($payload['pdv_data_vencimento'] ?? ($formaPagamento === 'a_faturar' ? date('Y-m-d') : ''))),
            ];

            $servico = $this->service->faturarServico($servico->id, $dadosFaturamento);
        }

        $this->pdvService()->registrarLancamentoServico(
            $servico->id,
            $caixaId,
            $formaPagamento,
            (int)($_SESSION['user_id'] ?? 0)
        );

        return $this->service->buscarServico($servico->id) ?? $servico;
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

    private function normalizarFormaPagamentoPdv(mixed $formaPagamento): string
    {
        $forma = strtolower(trim((string)$formaPagamento));

        return in_array($forma, ['dinheiro', 'cartao', 'pix', 'a_faturar'], true)
            ? $forma
            : '';
    }

    private function obterDataBrasilia(): string
    {
        return (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
    }
}
