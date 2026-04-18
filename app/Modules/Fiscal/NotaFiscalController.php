<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use App\Support\CsrfProtection;
use App\Support\PermissionGate;
use PDO;

/**
 * Controller do modulo Fiscal.
 */
class NotaFiscalController
{
    private const BASE_URL = 'admin/fiscal.php';

    private NotaFiscalRepository $notaFiscalRepository;
    private EmpresaFiscalRepository $empresaFiscalRepository;
    private NotaFiscalService $service;

    /**
     * @param PDO $pdo Conexao principal do sistema.
     */
    public function __construct(private readonly PDO $pdo)
    {
        $this->notaFiscalRepository = new NotaFiscalRepository($pdo);
        $this->empresaFiscalRepository = new EmpresaFiscalRepository($pdo);
        $this->service = new NotaFiscalService($pdo, $this->notaFiscalRepository, $this->empresaFiscalRepository);

        $this->verificarAutenticacao();
    }

    /**
     * Despacha a acao solicitada para o metodo correspondente.
     */
    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        try {
            match ($action) {
                'listar' => $this->listar(),
                'faturaveis' => $this->faturaveis(),
                'homologacao' => $this->homologacao(),
                'visualizar' => $this->visualizar(),
                'emitir' => $this->emitir(),
                'cancelar' => $this->cancelar(),
                'xml' => $this->xml(),
                'log' => $this->log(),
                'configurar' => $this->configurar(),
                'validar' => $this->validar(),
                default => $this->json(['sucesso' => false, 'erro' => 'Acao invalida.'], 404),
            };
        } catch (\Throwable $e) {
            if ($this->wantsJson()) {
                $this->json(['sucesso' => false, 'erro' => $e->getMessage()], 422);
            }

            $this->flash('error', $e->getMessage());
            $this->redirect();
        }
    }

    /**
     * Lista NF-e emitidas com filtros e paginacao.
     */
    public function listar(): void
    {
        $filtros = $this->filtrosRequest();
        $resultado = $this->notaFiscalRepository->findAll($filtros, (int)($filtros['per_page'] ?? 15));

        $this->view('fiscal/index', [
            'page_title' => 'NF-e Emitidas',
            'notas' => $resultado['dados'] ?? [],
            'total' => (int)($resultado['total'] ?? 0),
            'totalNotas' => (int)($resultado['total'] ?? 0),
            'paginaAtual' => (int)($resultado['pagina'] ?? 1),
            'totalPaginas' => (int)($resultado['total_paginas'] ?? 1),
            'filtros' => $filtros,
        ]);
    }

    /**
     * Exibe pedidos faturados ainda sem NF-e emitida.
     */
    public function faturaveis(): void
    {
        $filtros = $this->filtrosRequest();

        $this->view('fiscal/faturaveis', [
            'page_title' => 'Pedidos Faturaveis',
            'pedidos' => $this->mapearPedidosFaturaveis($this->service->listarPedidosFaturaveis($filtros)),
            'filtros' => $filtros,
        ]);
    }

    /**
     * Exibe painel de homologacao com teste de emissao e log.
     */
    public function homologacao(): void
    {
        $empresa = $this->empresaFiscalRepository->get();

        $this->view('fiscal/homologacao', [
            'page_title' => 'Painel de Homologacao',
            'empresa' => $empresa,
            'pedidosFaturaveis' => $this->mapearPedidosFaturaveis($this->service->listarPedidosFaturaveis([])),
        ]);
    }

    /**
     * Exibe os detalhes completos da NF-e.
     */
    public function visualizar(): void
    {
        $id = (string)($_GET['id'] ?? '');
        if ($id === '') {
            $this->flash('error', 'ID da NF-e e obrigatorio.');
            $this->redirect(self::BASE_URL . '?action=listar');
        }

        $nfe = $this->notaFiscalRepository->findById($id);
        if ($nfe === null) {
            $this->flash('error', 'NF-e nao encontrada.');
            $this->redirect(self::BASE_URL . '?action=listar');
        }

        $pedido = $nfe->pedido_id !== null ? $this->buscarPedidoComItens($nfe->pedido_id) : null;
        $servico = null;
        $origemTipo = $nfe->origemTipo();
        if ($origemTipo === 'PEDIDO') {
            if ($pedido === null) {
                $this->flash('error', 'Pedido vinculado a NF-e nao encontrado.');
                $this->redirect(self::BASE_URL . '?action=listar');
            }
            $cliente = $this->buscarClienteFiscal($pedido['cliente_id'] ?? null);
        } else {
            $servico = $this->buscarServicoComItens((string)$nfe->servico_id);
            if ($servico === null) {
                $this->flash('error', 'Servico vinculado a NF-e nao encontrado.');
                $this->redirect(self::BASE_URL . '?action=listar');
            }
            $cliente = $this->buscarClienteFiscal($servico['cliente_id'] ?? null);
        }
        $empresa = $this->empresaFiscalRepository->get();

        $this->view('fiscal/visualizar', compact('nfe', 'pedido', 'servico', 'cliente', 'empresa', 'origemTipo'));
    }

    /**
     * Emite a NF-e para um pedido faturado.
     */
    public function emitir(): void
    {
        $this->requirePost();
        $pedidoId = (string)($_POST['pedido_id'] ?? '');
        $servicoId = (string)($_POST['servico_id'] ?? '');

        if ($pedidoId === '' && $servicoId === '') {
            $this->json(['sucesso' => false, 'erro' => 'pedido_id ou servico_id e obrigatorio.'], 400);
        }

        $resultado = $servicoId !== ''
            ? $this->service->emitirServico($servicoId)
            : $this->service->emitir($pedidoId);
        $payload = $resultado;
        $payload['nfe'] = $resultado['nfe'] instanceof NotaFiscal ? $resultado['nfe']->toArray() : null;

        $this->json($payload, $resultado['sucesso'] ? 200 : 422);
    }

    /**
     * Cancela uma NF-e ja autorizada.
     */
    public function cancelar(): void
    {
        $this->requirePost();
        $nfeId = (string)($_POST['nfe_id'] ?? $_POST['id'] ?? '');
        $justificativa = (string)($_POST['justificativa'] ?? '');

        if ($nfeId === '') {
            $this->json(['sucesso' => false, 'erro' => 'nfe_id e obrigatorio.'], 400);
        }

        $resultado = $this->service->cancelar($nfeId, $justificativa);
        $this->json($resultado, $resultado['sucesso'] ? 200 : 422);
    }

    /**
     * Retorna o XML salvo para download.
     */
    public function xml(): void
    {
        $id = (string)($_GET['id'] ?? '');
        if ($id === '') {
            http_response_code(400);
            echo 'ID da NF-e e obrigatorio.';
            exit();
        }

        $nfe = $this->notaFiscalRepository->findById($id);
        if ($nfe === null || $nfe->xml_nfe === null || $nfe->xml_nfe === '') {
            http_response_code(404);
            echo 'XML da NF-e nao encontrado.';
            exit();
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="nfe_' . $nfe->chave_acesso . '.xml"');
        echo $nfe->xml_nfe;
        exit();
    }

    /**
     * Retorna as ultimas linhas do fiscal.log.
     */
    public function log(): void
    {
        $basePath = defined('APP_PATH') ? APP_PATH : dirname(__DIR__, 3);
        $logPath = $basePath . '/storage/logs/fiscal.log';
        $conteudo = file_exists($logPath)
            ? htmlspecialchars(implode("\n", array_slice(array_reverse(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)), 0, 30)))
            : '(nenhum log registrado ainda)';

        $this->json(['sucesso' => true, 'linhas' => $conteudo, 'log' => $conteudo]);
    }

    /**
     * Exibe ou salva a configuracao fiscal da empresa.
     */
    public function configurar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            CsrfProtection::validateRequestOrFail();
            $salvo = $this->empresaFiscalRepository->save($_POST);

            if ($this->wantsJson()) {
                $this->json([
                    'sucesso' => $salvo,
                    'erro' => $salvo ? null : 'Nao foi possivel salvar a configuracao fiscal.',
                ], $salvo ? 200 : 422);
            }

            $this->flash($salvo ? 'success' : 'error', $salvo ? 'Configuracao fiscal salva com sucesso.' : 'Falha ao salvar configuracao fiscal.');
            $this->redirect(self::BASE_URL . '?action=configurar');
        }

        $this->view('fiscal/configurar', [
            'page_title' => 'Configuracao Fiscal',
            'empresa' => $this->empresaFiscalRepository->get(),
        ]);
    }

    /**
     * Executa somente a validacao previa da emissao.
     */
    public function validar(): void
    {
        $this->requirePost();
        $pedidoId = (string)($_POST['pedido_id'] ?? '');
        $servicoId = (string)($_POST['servico_id'] ?? '');

        if ($pedidoId === '' && $servicoId === '') {
            $this->json(['valido' => false, 'erros' => ['pedido_id ou servico_id e obrigatorio.']], 400);
        }

        $this->json(
            $servicoId !== ''
                ? $this->service->validarServicoParaEmissao($servicoId)
                : $this->service->validarPedidoParaEmissao($pedidoId)
        );
    }

    /**
     * Garante sessao autenticada e permissao do modulo.
     */
    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
        }

        PermissionGate::init($this->pdo);
        if (!PermissionGate::can('fiscal')) {
            if ($this->wantsJson()) {
                $this->json(['sucesso' => false, 'erro' => 'Sem permissao para acessar o modulo Fiscal.'], 403);
            }

            $this->flash('error', 'Sem permissao para acessar o modulo Fiscal.');
            header('Location: ' . $this->route('admin/dashboard.php'));
            exit();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosRequest(): array
    {
        return [
            'status' => $_GET['status'] ?? null,
            'cliente_id' => $_GET['cliente_id'] ?? null,
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null,
            'pagina' => (int)($_GET['pagina'] ?? 1),
            'per_page' => (int)($_GET['per_page'] ?? 15),
        ];
    }

    /**
     * @param string $view
     * @param array<string, mixed> $dados
     */
    private function view(string $view, array $dados = []): void
    {
        if (!defined('APP_PATH')) {
            define('APP_PATH', dirname(__DIR__, 3));
        }

        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';

        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View nao encontrada: {$view}</div></div>";

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirect(?string $url = null): never
    {
        header('Location: ' . $this->route($url ?? self::BASE_URL));
        exit();
    }

    private function route(string $path): string
    {
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
        $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

        return $format === 'json'
            || str_contains($accept, 'application/json')
            || $xrw === 'xmlhttprequest'
            || in_array($action, ['emitir', 'cancelar', 'validar', 'log'], true);
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function json(array $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    private function requirePost(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new NotaFiscalException('Metodo HTTP nao permitido para esta operacao.');
        }

        CsrfProtection::validateRequestOrFail();
    }

    /**
     * @param array<int, array<string, mixed>> $pedidos
     * @return array<int, array<string, mixed>>
     */
    private function mapearPedidosFaturaveis(array $pedidos): array
    {
        return array_map(
            static function (array $pedido): array {
                return [
                    'origem_tipo' => (string)($pedido['origem_tipo'] ?? 'PEDIDO'),
                    'pedido_id' => isset($pedido['pedido_id']) ? (string)$pedido['pedido_id'] : '',
                    'servico_id' => isset($pedido['servico_id']) ? (string)$pedido['servico_id'] : '',
                    'numero' => (string)($pedido['numero'] ?? ''),
                    'cliente_id' => (string)($pedido['cliente_id'] ?? ''),
                    'cliente_nome' => (string)($pedido['cliente_nome'] ?? ''),
                    'data_faturamento' => (string)($pedido['data_faturamento'] ?? $pedido['data_pedido'] ?? ''),
                    'valor_total' => (float)($pedido['valor_total'] ?? 0),
                    'total_itens' => isset($pedido['total_itens']) ? (int)$pedido['total_itens'] : null,
                    'status' => (string)($pedido['status'] ?? ''),
                ];
            },
            $pedidos
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarPedidoComItens(string $pedidoId): ?array
    {
        $stmtPedido = $this->pdo->prepare(
            "SELECT p.*, c.nome AS cliente_nome
             FROM pedidos p
             LEFT JOIN clientes c ON c.id::text = p.cliente_id::text
             WHERE p.id::text = :id
             LIMIT 1"
        );
        $stmtPedido->execute([':id' => $pedidoId]);
        $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            return null;
        }

        $stmtItens = $this->pdo->prepare(
            "SELECT pi.*, 
                    COALESCE(pi.nome_produto, pr.nome, 'Produto sem nome') AS nome_produto,
                    pf.ncm,
                    pf.cfop
             FROM pedido_itens pi
             LEFT JOIN produtos pr ON pr.id::text = pi.produto_id::text
             LEFT JOIN produto_fiscal pf ON pf.produto_id::text = pi.produto_id::text
             WHERE pi.pedido_id::text = :pedido_id
             ORDER BY pi.created_at ASC"
        );
        $stmtItens->execute([':pedido_id' => $pedidoId]);
        $pedido['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $pedido;
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarClienteFiscal(mixed $clienteId): array
    {
        if ($clienteId === null || $clienteId === '') {
            return [
                'nome' => 'Cliente nao encontrado',
                'cpf_cnpj' => '',
                'ie' => null,
            ];
        }

        $stmt = $this->pdo->prepare(
            "SELECT nome, cpf_cnpj, ie
             FROM clientes
             WHERE id::text = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => (string)$clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        return $cliente ?: [
            'nome' => 'Cliente nao encontrado',
            'cpf_cnpj' => '',
            'ie' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarServicoComItens(string $servicoId): ?array
    {
        if ($servicoId === '') {
            return null;
        }

        $stmtServico = $this->pdo->prepare(
            "SELECT s.*,
                    COALESCE(c.nome, s.nome_cliente) AS cliente_nome
             FROM servicos s
             LEFT JOIN clientes c ON c.id::text = s.cliente_id::text
             WHERE s.id::text = :id
             LIMIT 1"
        );
        $stmtServico->execute([':id' => $servicoId]);
        $servico = $stmtServico->fetch(PDO::FETCH_ASSOC);

        if (!$servico) {
            return null;
        }

        $stmtItens = $this->pdo->prepare(
            "SELECT si.*,
                    COALESCE(si.nome_produto, pr.nome, 'Produto sem nome') AS nome_produto,
                    pf.ncm,
                    pf.cfop
             FROM servico_itens si
             LEFT JOIN produtos pr ON pr.id::text = si.produto_id::text
             LEFT JOIN produto_fiscal pf ON pf.produto_id::text = si.produto_id::text
             WHERE si.servico_id::text = :servico_id
             ORDER BY si.created_at ASC"
        );
        $stmtItens->execute([':servico_id' => $servicoId]);
        $servico['itens'] = $stmtItens->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($servico['itens'] === [] && !empty($servico['produto_id'])) {
            $stmtItemLegado = $this->pdo->prepare(
                "SELECT s.produto_id,
                        COALESCE(s.produto_nome, pr.nome, 'Produto sem nome') AS nome_produto,
                        COALESCE(NULLIF(s.produto_quantidade, 0), 1) AS quantidade,
                        COALESCE(s.produto_valor_unitario, 0) AS valor_unitario,
                        COALESCE(NULLIF(s.produto_quantidade, 0), 1) * COALESCE(s.produto_valor_unitario, 0) AS valor_total_item,
                        pf.ncm,
                        pf.cfop
                 FROM servicos s
                 LEFT JOIN produtos pr ON pr.id::text = s.produto_id::text
                 LEFT JOIN produto_fiscal pf ON pf.produto_id::text = s.produto_id::text
                 WHERE s.id::text = :servico_id
                 LIMIT 1"
            );
            $stmtItemLegado->execute([':servico_id' => $servicoId]);
            $itemLegado = $stmtItemLegado->fetch(PDO::FETCH_ASSOC);
            $servico['itens'] = $itemLegado ? [$itemLegado] : [];
        }

        return $servico;
    }
}
