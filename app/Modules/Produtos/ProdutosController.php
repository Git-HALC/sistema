<?php

namespace App\Modules\Produtos;

use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use PDO;

/**
 * ProdutosController — camada HTTP do módulo de Produtos.
 *
 * Responsabilidades:
 *   • Verificar autenticação e autorização.
 *   • Parsear e sanitizar entrada HTTP (GET / POST).
 *   • Delegar toda lógica ao ProdutoService.
 *   • Carregar view ou redirecionar.
 *
 * Sem regra de negócio, sem SQL, sem cálculos.
 */
class ProdutosController
{
    private ProdutoService $service;
    private AuditLogger $audit;
    private PDO $pdo;
    private int $itensPorPagina = 15;

    private const BASE_URL = 'admin/produtos.php';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->audit = new AuditLogger($pdo);
        $this->service = new ProdutoService(
            $pdo,
            new ProdutoRepository($pdo),
            new ProdutoFiscalRepository($pdo)
        );

        $this->verificarAutenticacao();
    }

    // ----------------------------------------------------------------
    // Roteamento
    // ----------------------------------------------------------------

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? ($_GET['action'] ?? 'listar');
        $id     = $this->idGet();

        match ($action) {
            'novo'    => $this->novoForm(),
            'editar'  => $id ? $this->editarForm($id) : $this->redirecionar(),
            'inventario' => $this->inventario(),
            'movimentar-estoque' => $this->movimentarEstoque(),
            'salvar'  => $this->salvar(),
            'excluir' => $id ? $this->excluir($id)    : $this->redirecionar(),
            default   => $this->index(),
        };
    }

    // ----------------------------------------------------------------
    // Actions
    // ----------------------------------------------------------------

    private function index(): void
    {
        $busca  = trim($_GET['busca'] ?? '');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $r = $this->service->listar($busca, $pagina, $this->itensPorPagina);

        $this->view('produtos/index', [
            'page_title'   => 'Produtos',
            'produtos'     => $r['produtos'],
            'busca'        => $busca,
            'paginaAtual'  => $r['pagina'],
            'totalPaginas' => $r['totalPags'],
            'total'        => $r['total'],
        ]);
    }

    private function novoForm(): void
    {
        $grupoRepo = new ProdutoGrupoRepository($this->pdo);
        $this->view('produtos/form', [
            'page_title'    => 'Novo Produto',
            'produto'       => new Produto(),
            'fiscal'        => null,
            'editando'      => false,
            'grupos'        => $grupoRepo->ativosParaSelect(),
            'subgruposInit' => [],
            'csrfToken'     => \App\Support\CsrfProtection::token(),
        ]);
    }

    private function editarForm(int $id): void
    {
        $resultado = $this->service->buscarParaEdicao($id);

        if (!$resultado) {
            $this->flash('error', 'Produto não encontrado.');
            $this->redirecionar();
        }

        $grupoRepo = new ProdutoGrupoRepository($this->pdo);
        $subgrupoRepo = new ProdutoSubgrupoRepository($this->pdo);
        $grupoId = (int)($resultado['produto']->grupo_id ?? 0);

        $this->view('produtos/form', [
            'page_title'    => 'Editar Produto',
            'produto'       => $resultado['produto'],
            'fiscal'        => $resultado['fiscal'],
            'editando'      => true,
            'grupos'        => $grupoRepo->ativosParaSelect(),
            'subgruposInit' => $grupoId > 0 ? $subgrupoRepo->porGrupoAtivos($grupoId) : [],
            'csrfToken'     => \App\Support\CsrfProtection::token(),
        ]);
    }

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }

        $id = $this->idPost();

        $dadosProduto = [
            'id'             => $id ?? 0,
            'codigo'         => trim($_POST['codigo']        ?? ''),
            'nome'           => trim($_POST['nome']          ?? ''),
            'descricao'      => trim($_POST['descricao']     ?? ''),
            'unidade'        => trim($_POST['unidade']       ?? 'UN'),
            'preco_custo'    => $this->float($_POST['preco_custo']    ?? 0),
            'preco_venda'    => $this->float($_POST['preco_venda']    ?? 0),
            'estoque_atual'  => $this->float($_POST['estoque_atual']  ?? 0),
            'estoque_minimo' => $this->float($_POST['estoque_minimo'] ?? 0),
            'ativo'          => isset($_POST['ativo']),
            'grupo_id'       => !empty($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null,
            'subgrupo_id'    => !empty($_POST['subgrupo_id']) ? (int)$_POST['subgrupo_id'] : null,
        ];

        $dadosFiscais = [
            'ncm'                     => preg_replace('/\D/', '', $_POST['ncm']  ?? ''),
            'cest'                    => preg_replace('/\D/', '', $_POST['cest'] ?? ''),
            'cfop'                    => preg_replace('/\D/', '', $_POST['cfop'] ?? ''),
            'origem'                  => $_POST['origem']                                ?? '0',
            'csosn_cst'               => trim($_POST['csosn_cst']                        ?? ''),
            'cst_pis'                 => trim($_POST['cst_pis']                          ?? ''),
            'cst_cofins'              => trim($_POST['cst_cofins']                       ?? ''),
            'modalidade_bc_icms'      => trim($_POST['modalidade_bc_icms']               ?? ''),
            'aliquota_icms'           => $this->float($_POST['aliquota_icms']            ?? 0),
            'aliquota_icms_st'        => $this->float($_POST['aliquota_icms_st']         ?? 0),
            'aliquota_ipi'            => $this->float($_POST['aliquota_ipi']             ?? 0),
            'aliquota_pis'            => $this->float($_POST['aliquota_pis']             ?? 0),
            'aliquota_cofins'         => $this->float($_POST['aliquota_cofins']          ?? 0),
            'reducao_bc_icms'         => $this->float($_POST['reducao_bc_icms']          ?? 0),
            'codigo_beneficio_fiscal' => trim($_POST['codigo_beneficio_fiscal']          ?? ''),
            'ind_escala'              => trim($_POST['ind_escala']                       ?? 'S'),
            'cnpj_fabricante'         => preg_replace('/\D/', '', $_POST['cnpj_fabricante'] ?? ''),
        ];

        $resultado = $this->service->salvar($dadosProduto, $dadosFiscais, $id);

        if (!$resultado['ok']) {
            $produtoDraft = Produto::fromArray($dadosProduto);
            $fiscalDraft  = ProdutoFiscal::fromArray($dadosFiscais + ['produto_id' => $id ?? 0]);

            $this->flash('error', implode('<br>', $resultado['erros']));
            $this->view('produtos/form', [
                'page_title' => $id ? 'Editar Produto' : 'Novo Produto',
                'produto'    => $produtoDraft,
                'fiscal'     => $fiscalDraft->vazio() ? null : $fiscalDraft,
                'editando'   => (bool) $id,
            ]);
            return;
        }

        $acao = $id ? 'ATUALIZAR' : 'CRIAR';
        $this->audit->registrar(
            'cadastros_produtos',
            $acao,
            'produto',
            (int)($resultado['id'] ?? $id ?? 0),
            ($id ? 'Produto atualizado: ' : 'Produto criado: ') . ($dadosProduto['nome'] ?? ''),
            [
                'codigo' => $dadosProduto['codigo'] ?? null,
                'nome' => $dadosProduto['nome'] ?? null,
                'preco_venda' => $dadosProduto['preco_venda'] ?? 0,
            ]
        );

        $this->flash('success', $id ? 'Produto atualizado com sucesso!' : 'Produto cadastrado com sucesso!');
        $this->redirecionar();
    }

    private function inventario(): void
    {
        $busca = trim((string)($_GET['busca'] ?? ''));
        $produtoId = isset($_GET['produto_id']) ? (int)$_GET['produto_id'] : 0;
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $dataInicio = trim((string)($_GET['data_inicio'] ?? ''));
        $dataFim = trim((string)($_GET['data_fim'] ?? ''));

        $filtros = [
            'busca' => $busca,
            'produto_id' => $produtoId > 0 ? $produtoId : null,
            'tipo' => $tipo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'somente_ativos' => 1,
        ];

        $movimentacoes = $this->estoqueService()->listarMovimentacoes($filtros, $tipo !== '' ? $tipo : null);
        $produtos = $this->service->listar($busca, 1, 200)['produtos'] ?? [];

        $this->view('produtos/inventario', [
            'page_title' => 'Inventário de Estoque',
            'movimentacoes' => $movimentacoes,
            'produtos' => $produtos,
            'filtros' => $filtros,
        ]);
    }

    private function movimentarEstoque(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar('action=inventario');
        }

        try {
            $this->estoqueService()->movimentarManual([
                'produto_id' => $_POST['produto_id'] ?? null,
                'tipo' => $_POST['tipo'] ?? '',
                'quantidade' => $_POST['quantidade'] ?? '0',
                'observacao' => $_POST['observacao'] ?? '',
            ], (int)($_SESSION['user_id'] ?? 0));

            $this->audit->registrar(
                'cadastros_produtos',
                'MOVIMENTAR_ESTOQUE',
                'produto',
                (int)($_POST['produto_id'] ?? 0),
                'Movimentação manual de estoque registrada.',
                [
                    'tipo' => strtoupper((string)($_POST['tipo'] ?? '')),
                    'quantidade' => (float)$this->float($_POST['quantidade'] ?? '0'),
                ]
            );

            $this->flash('success', 'Movimentação de estoque registrada com sucesso!');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirecionar('action=inventario');
    }

    private function excluir(int $id): void
    {
        $sucesso  = $this->service->excluir($id);
        $mensagem = $sucesso ? 'Produto excluído com sucesso!' : 'Erro ao excluir produto.';

        if ($sucesso) {
            $this->audit->registrar('cadastros_produtos', 'EXCLUIR', 'produto', $id, 'Produto excluído.');
        }

        if ($this->isAjax()) {
            $this->json([
                'success' => $sucesso,
                'message' => $mensagem,
            ]);
        }

        $sucesso
            ? $this->flash('success', $mensagem)
            : $this->flash('error',   $mensagem);

        $this->redirecionar();
    }

    // ----------------------------------------------------------------
    // Helpers privados
    // ----------------------------------------------------------------

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
        }

        if ($this->isPdvSharedContext() && $this->usuarioPodeOperarPdv()) {
            return;
        }

        if ((int)($_SESSION['user_role'] ?? 0) !== 1) {
            $this->flash('error', 'Acesso restrito a administradores.');
            header('Location: ' . $this->route('admin/dashboard.php'));
            exit();
        }
    }

    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';

        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require_once $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View não encontrada: {$view}</div></div>";

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirecionar(string $query = ''): never
    {
        $destino = $this->route(self::BASE_URL);
        if ($query !== '') {
            $destino .= (str_contains($destino, '?') ? '&' : '?') . ltrim($query, '?&');
        }
        header('Location: ' . $destino);
        exit();
    }

    private function route(string $path): string
    {
        if ($path === self::BASE_URL) {
            return function_exists('dmContextUrl')
                ? dmContextUrl('__dm_produto_base_url', self::BASE_URL)
                : (function_exists('tenantUrl') ? \tenantUrl($path) : '/' . ltrim($path, '/'));
        }

        if (str_starts_with($path, self::BASE_URL . '?')) {
            $baseUrl = function_exists('dmContextUrl')
                ? dmContextUrl('__dm_produto_base_url', self::BASE_URL)
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

    private function idGet(): ?int
    {
        $id = $_POST['id'] ?? ($_GET['id'] ?? null);
        return isset($id) && ctype_digit((string)$id)
            ? (int)$id : null;
    }

    private function idPost(): ?int
    {
        return isset($_POST['id']) && ctype_digit((string)$_POST['id'])
            ? (int)$_POST['id'] : null;
    }

    private function float(mixed $v): float
    {
        return (float)str_replace(',', '.', (string)$v);
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados);
        exit();
    }

    private function estoqueService(): EstoqueMovimentacaoService
    {
        static $service = null;
        if ($service instanceof EstoqueMovimentacaoService) {
            return $service;
        }

        $service = new EstoqueMovimentacaoService(
            $this->pdo,
            new EstoqueMovimentacaoRepository($this->pdo)
        );

        return $service;
    }

    private function isPdvSharedContext(): bool
    {
        return !empty($GLOBALS['__dm_allow_pdv_shared_access']) && !empty($GLOBALS['__dm_pdv_context']);
    }

    private function usuarioPodeOperarPdv(): bool
    {
        return (new PdvPermissao($this->pdo))->usuarioAtualPodeOperarPdv();
    }
}
