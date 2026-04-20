<?php
declare(strict_types=1);

namespace App\Modules\Produtos;

use App\Support\AuditLogger;
use PDO;

final class ProdutoSubgrupoController
{
    private const BASE_URL = 'admin/produto-subgrupos.php';
    private ProdutoSubgrupoRepository $repo;
    private ProdutoGrupoRepository $repoGrupo;
    private AuditLogger $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repo = new ProdutoSubgrupoRepository($pdo);
        $this->repoGrupo = new ProdutoGrupoRepository($pdo);
        $this->audit = new AuditLogger($pdo);
        $this->assertAdmin();
    }

    public function handleRequest(): void
    {
        $action = $_POST['action'] ?? ($_GET['action'] ?? 'index');
        $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id']
            : (isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null);

        match ($action) {
            'novo'     => $this->form(null),
            'editar'   => $id ? $this->form($id) : $this->redirect(),
            'salvar'   => $this->salvar(),
            'inativar' => $this->toggleAtivo($id, false),
            'reativar' => $this->toggleAtivo($id, true),
            default    => $this->index(),
        };
    }

    private function index(): void
    {
        $busca = trim((string)($_GET['busca'] ?? ''));
        $grupoId = isset($_GET['grupo_id']) && ctype_digit((string)$_GET['grupo_id']) ? (int)$_GET['grupo_id'] : null;
        $ativoRaw = $_GET['ativo'] ?? '';
        $ativo = match ($ativoRaw) { '1' => true, '0' => false, default => null };
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $r = $this->repo->listar($busca, $grupoId, $ativo, $pagina, 15);
        $this->view('produtos/subgrupos/lista', [
            'page_title' => 'Subgrupos de Produtos',
            'subgrupos' => $r['dados'],
            'grupos' => $this->repoGrupo->ativosParaSelect(),
            'busca' => $busca,
            'grupoFiltro' => $grupoId,
            'ativoFiltro' => (string)$ativoRaw,
            'paginaAtual' => $pagina,
            'totalPaginas' => $r['paginas'],
            'total' => $r['total'],
        ]);
    }

    private function form(?int $id): void
    {
        $sg = $id ? $this->repo->findById($id) : null;
        if ($id && !$sg) {
            $this->flash('error', 'Subgrupo não encontrado.');
            $this->redirect();
        }
        $this->view('produtos/subgrupos/form', [
            'page_title' => $id ? 'Editar Subgrupo' : 'Novo Subgrupo',
            'subgrupo' => $sg ?: ['id' => null, 'grupo_id' => null, 'nome' => '', 'descricao' => '', 'ativo' => true],
            'grupos' => $this->repoGrupo->ativosParaSelect(),
            'editando' => (bool)$id,
        ]);
    }

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirect();

        $id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null;
        $grupoId = isset($_POST['grupo_id']) && ctype_digit((string)$_POST['grupo_id']) ? (int)$_POST['grupo_id'] : 0;
        $nome = trim((string)($_POST['nome'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $ativo = isset($_POST['ativo']);

        $erros = [];
        if ($grupoId <= 0) $erros[] = 'Selecione o grupo pai.';
        if ($nome === '') $erros[] = 'Nome é obrigatório.';
        elseif ($grupoId > 0 && $this->repo->existsNomeNoGrupo($grupoId, $nome, $id)) {
            $erros[] = 'Já existe um subgrupo com este nome neste grupo.';
        }

        if ($erros) {
            $this->flash('error', implode('<br>', $erros));
            $this->view('produtos/subgrupos/form', [
                'page_title' => $id ? 'Editar Subgrupo' : 'Novo Subgrupo',
                'subgrupo' => ['id' => $id, 'grupo_id' => $grupoId, 'nome' => $nome, 'descricao' => $descricao, 'ativo' => $ativo],
                'grupos' => $this->repoGrupo->ativosParaSelect(),
                'editando' => (bool)$id,
            ]);
            return;
        }

        $dados = ['grupo_id' => $grupoId, 'nome' => $nome, 'descricao' => $descricao !== '' ? $descricao : null, 'ativo' => $ativo];

        try {
            $this->pdo->beginTransaction();
            if ($id) { $this->repo->update($id, $dados); $novoId = $id; }
            else     { $novoId = $this->repo->create($dados); }
            $this->audit->registrar(
                'produto_subgrupos',
                $id ? 'ATUALIZAR' : 'CRIAR',
                'produto_subgrupo',
                $novoId,
                ($id ? 'Subgrupo atualizado: ' : 'Subgrupo criado: ') . $nome,
                $dados
            );
            $this->pdo->commit();
            $this->flash('success', $id ? 'Subgrupo atualizado.' : 'Subgrupo cadastrado.');
            $this->redirect();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->flash('error', 'Erro ao salvar: ' . $e->getMessage());
            $this->redirect();
        }
    }

    private function toggleAtivo(?int $id, bool $ativo): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) $this->redirect();
        $stmt = $this->pdo->prepare('UPDATE produto_subgrupos SET ativo = :a WHERE id = :id');
        $stmt->execute([':a' => $ativo ? 'true' : 'false', ':id' => $id]);
        $this->audit->registrar('produto_subgrupos', $ativo ? 'REATIVAR' : 'INATIVAR', 'produto_subgrupo', $id, '', []);
        $this->flash('success', $ativo ? 'Subgrupo reativado.' : 'Subgrupo inativado.');
        $this->redirect();
    }

    private function assertAdmin(): void
    {
        if (!isset($_SESSION['user_id'])) { header('Location: ' . tenantUrl('login.php')); exit(); }
        if ((int)($_SESSION['user_role'] ?? 0) !== 1) {
            $this->flash('error', 'Acesso restrito a administradores.');
            header('Location: ' . tenantUrl('admin/dashboard.php')); exit();
        }
    }
    private function view(string $view, array $dados): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';
        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path) ? require_once $path : print "<div class='alert alert-danger'>View {$view} não encontrada</div>";
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }
    private function redirect(): never { header('Location: ' . tenantUrl(self::BASE_URL)); exit(); }
    private function flash(string $tipo, string $texto): void { $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto]; }
}
