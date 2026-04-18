<?php

namespace App\Modules\Servicos;

use App\Support\AuditLogger;
use PDO;

/**
 * Mini-modulo Servicos (auditoria 2026-04-18).
 * Opera apenas sobre servicos_catalogo (id, nome, descricao, valor_base, ativo).
 */
class ServicoController
{
    private ServicoCatalogoService $service;
    private AuditLogger $audit;
    private int $itensPorPagina = 15;

    private const BASE_URL = 'admin/servicos.php';

    public function __construct(private readonly PDO $pdo)
    {
        $this->audit = new AuditLogger($pdo);
        $this->service = new ServicoCatalogoService(
            $pdo,
            new ServicoCatalogoRepository($pdo)
        );

        $this->verificarAutenticacao();
    }

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? ($_GET['action'] ?? 'index');
        $id = $this->idGet();

        match ($action) {
            'novo'     => $this->novoForm(),
            'editar'   => $id ? $this->editarForm($id) : $this->redirecionar(),
            'store'    => $this->store(),
            'inativar' => $this->inativar(),
            'reativar' => $this->reativar(),
            default    => $this->index(),
        };
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    public function index(): void
    {
        $busca = trim((string)($_GET['busca'] ?? ''));
        $ativoParam = $_GET['ativo'] ?? '';
        $ativoFiltro = match ($ativoParam) {
            '1', 'ativos' => true,
            '0', 'inativos' => false,
            default => null,
        };
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $r = $this->service->listar($busca, $ativoFiltro, $pagina, $this->itensPorPagina);

        $this->view('servicos/lista', [
            'page_title'   => 'Serviços',
            'servicos'     => $r['dados'],
            'busca'        => $busca,
            'ativoFiltro'  => $ativoParam,
            'paginaAtual'  => $pagina,
            'totalPaginas' => $r['paginas'],
            'total'        => $r['total'],
        ]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }

        $id = $this->idPost();

        $dados = [
            'nome'       => trim((string)($_POST['nome'] ?? '')),
            'descricao'  => trim((string)($_POST['descricao'] ?? '')),
            'valor_base' => $this->float($_POST['valor_base'] ?? 0),
            'ativo'      => isset($_POST['ativo']),
        ];

        $r = $this->service->salvar($dados, $id);

        if (!$r['ok']) {
            $draft = ServicoCatalogo::fromArray($dados + ['id' => $id]);
            $this->flash('error', implode('<br>', $r['erros']));
            $this->view('servicos/form', [
                'page_title' => $id ? 'Editar Serviço' : 'Novo Serviço',
                'servico'    => $draft,
                'editando'   => (bool)$id,
            ]);
            return;
        }

        $this->audit->registrar(
            'servicos_catalogo',
            $id ? 'ATUALIZAR' : 'CRIAR',
            'servico_catalogo',
            (int)($r['id'] ?? 0),
            ($id ? 'Serviço atualizado: ' : 'Serviço criado: ') . $dados['nome'],
            ['nome' => $dados['nome'], 'valor_base' => $dados['valor_base']]
        );

        $this->flash('success', $id ? 'Serviço atualizado com sucesso!' : 'Serviço cadastrado com sucesso!');
        $this->redirecionar();
    }

    private function novoForm(): void
    {
        $this->view('servicos/form', [
            'page_title' => 'Novo Serviço',
            'servico'    => new ServicoCatalogo(),
            'editando'   => false,
        ]);
    }

    private function editarForm(int $id): void
    {
        $servico = $this->service->buscar($id);
        if ($servico === null) {
            $this->flash('error', 'Serviço não encontrado.');
            $this->redirecionar();
        }

        $this->view('servicos/form', [
            'page_title' => 'Editar Serviço',
            'servico'    => $servico,
            'editando'   => true,
        ]);
    }

    private function inativar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }
        $id = $this->idPost();
        if ($id !== null && $this->service->inativar($id)) {
            $this->audit->registrar('servicos_catalogo', 'INATIVAR', 'servico_catalogo', $id, 'Serviço inativado', []);
            $this->flash('success', 'Serviço inativado.');
        } else {
            $this->flash('error', 'Falha ao inativar serviço.');
        }
        $this->redirecionar();
    }

    private function reativar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirecionar();
        }
        $id = $this->idPost();
        if ($id !== null && $this->service->reativar($id)) {
            $this->audit->registrar('servicos_catalogo', 'REATIVAR', 'servico_catalogo', $id, 'Serviço reativado', []);
            $this->flash('success', 'Serviço reativado.');
        } else {
            $this->flash('error', 'Falha ao reativar serviço.');
        }
        $this->redirecionar();
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
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
        return function_exists('tenantUrl') ? \tenantUrl($path) : '/' . ltrim($path, '/');
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function idGet(): ?int
    {
        $id = $_POST['id'] ?? ($_GET['id'] ?? null);
        return isset($id) && ctype_digit((string)$id) ? (int)$id : null;
    }

    private function idPost(): ?int
    {
        return isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null;
    }

    private function float(mixed $v): float
    {
        return (float)str_replace(',', '.', (string)$v);
    }
}
