<?php

namespace App\Modules\Usuarios;

use App\Support\AuditLogger;
use App\Support\LicenseGate;
use App\Support\PermissionManager;
use PDO;

/**
 * UsuariosController - camada HTTP do modulo de Usuarios.
 *
 * Responsabilidades:
 *   - Verificar autenticacao (somente Admin).
 *   - Parsear entrada HTTP.
 *   - Delegar ao UsuarioService.
 *   - Renderizar view ou redirecionar.
 */
class UsuariosController
{
    private UsuarioService    $service;
    private UsuarioRepository $repo;
    private AuditLogger $audit;
    private int $porPagina = 10;
    private ?PermissionManager $permissionManager = null;

    private const BASE_URL = 'admin/usuarios.php';

    public function __construct(private readonly PDO $pdo)
    {
        $this->repo    = new UsuarioRepository($pdo);
        $this->service = new UsuarioService($this->repo);
        $this->audit   = new AuditLogger($pdo);
        $this->verificarAutenticacao();
    }

    // =========================================================================
    // Roteamento
    // =========================================================================

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';

        match ($action) {
            'novo'      => $this->novo(),
            'salvar'    => $this->salvar(),
            'editar'    => $this->editar(),
            'atualizar' => $this->atualizar(),
            'excluir'   => $this->excluir(),
            default     => $this->index(),
        };
    }

    // =========================================================================
    // Actions - views
    // =========================================================================

    private function index(): void
    {
        $planInfo = LicenseGate::getPlanInfo();
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
        $resultado = $this->service->listar($pagina, $this->porPagina);

        $this->view('usuarios/index', [
            'titulo'        => 'Usuarios',
            'usuarios'      => $resultado['usuarios'],
            'totalUsuarios' => $resultado['total'],
            'totalPaginas'  => $resultado['totalPaginas'],
            'pagina'        => $resultado['pagina'],
            'planInfo'      => $planInfo,
            'upgradeUrl'    => $this->suporteUpgradeUrl(),
        ]);
    }

    private function novo(): void
    {
        $planInfo = LicenseGate::getPlanInfo();
        if (!(bool) ($planInfo['pode_criar'] ?? true)) {
            $this->flash('error', (string) ($planInfo['mensagem'] ?? 'Limite de usuarios atingido.'));
            $this->redirecionar(self::BASE_URL . '?limite_usuarios=1');
        }

        $form = [
            'nome'            => '',
            'email'           => '',
            'telefone'        => '',
            'nivel_acesso_id' => 2,
            'ativo'           => 1,
            'modulos'         => [],
            'pode_operar_pdv' => 0,
            'pode_conferir_caixa' => 0,
        ];

        if (!empty($_SESSION['form_data'])) {
            $form = array_merge($form, $_SESSION['form_data']);
            unset($_SESSION['form_data']);
        }

        $form['modulos'] = $this->normalizarModuloIds((array) ($form['modulos'] ?? []));
        $modulosPersonalizacao = $this->marcarModulosSelecionados(
            $this->repo->listarModulos(),
            $form['modulos']
        );

        $this->view('usuarios/form', [
            'titulo'   => 'Novo Usuario',
            'form'     => $form,
            'editando' => false,
            'niveisAcesso' => $this->repo->listarNiveisAcesso(),
            'modulosPersonalizacao' => $modulosPersonalizacao,
        ]);
    }

    private function editar(): void
    {
        $id = !empty($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) $this->redirecionar();

        $usuario = $this->repo->buscarPorId($id);
        if (!$usuario) {
            $this->flash('error', 'Usuario nao encontrado.');
            $this->redirecionar();
        }

        if ($this->repo->isAdminPadrao($id)) {
            $this->flash('error', 'O administrador padrao nao e exibido para edicao.');
            $this->redirecionar();
        }

        $form = array_merge($usuario, ['senha' => '', 'confirmar_senha' => '']);

        if (!empty($_SESSION['form_data'])) {
            $form = array_merge($form, $_SESSION['form_data']);
            unset($_SESSION['form_data']);
        }

        $form['pode_operar_pdv'] = 0;
        $form['pode_conferir_caixa'] = 0;

        if ((int) ($form['nivel_acesso_id'] ?? 0) === 4) {
            $modulosPersonalizacao = $this->permissionManager()->getAllModulosComStatus($id);
            $permissoesPdv = $this->repo->buscarPermissoesPersonalizadas($id);
            $form['pode_operar_pdv'] = !empty($permissoesPdv['pode_operar_pdv']) ? 1 : 0;
            $form['pode_conferir_caixa'] = !empty($permissoesPdv['pode_conferir_caixa']) ? 1 : 0;
        } else {
            $modulosPersonalizacao = $this->marcarModulosSelecionados($this->repo->listarModulos(), []);
        }

        if (array_key_exists('modulos', $form)) {
            $form['modulos'] = $this->normalizarModuloIds((array) $form['modulos']);
            $modulosPersonalizacao = $this->marcarModulosSelecionados($modulosPersonalizacao, $form['modulos']);
        } else {
            $form['modulos'] = $this->extrairModulosAtivos($modulosPersonalizacao);
        }

        $this->view('usuarios/form', [
            'titulo'   => 'Editar Usuario',
            'form'     => $form,
            'editando' => true,
            'niveisAcesso' => $this->repo->listarNiveisAcesso(),
            'modulosPersonalizacao' => $modulosPersonalizacao,
        ]);
    }

    // =========================================================================
    // Actions - writes
    // =========================================================================

    private function salvar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirecionar();

        // Bloqueio de seguranca backend para evitar bypass via requisicao direta.
        $planInfo = LicenseGate::getPlanInfo();
        if (!(bool) ($planInfo['pode_criar'] ?? true)) {
            http_response_code(403);
            $mensagem = (string) ($planInfo['mensagem'] ?? 'Limite de usuarios atingido.');

            if ($this->isAjax()) {
                $this->json([
                    'success' => false,
                    'message' => $mensagem,
                ]);
            }

            $this->flash('error', $mensagem);
            $this->redirecionar(self::BASE_URL . '?limite_usuarios=1');
        }

        $modulosSelecionados = $this->normalizarModuloIds((array) ($_POST['modulos'] ?? []));

        $dados = [
            'nome'            => trim($_POST['nome']             ?? ''),
            'email'           => trim($_POST['email']            ?? ''),
            'telefone'        => substr(trim($_POST['telefone']  ?? ''), 0, 20),
            'nivel_acesso_id' => (int) ($_POST['nivel_acesso_id'] ?? 0),
            'senha'           => (string) ($_POST['senha']         ?? ''),
            'confirmar_senha' => (string) ($_POST['confirmar_senha'] ?? ''),
            'ativo'           => isset($_POST['ativo']) ? 1 : 0,
            'modulos'         => $modulosSelecionados,
            'pode_operar_pdv' => isset($_POST['pode_operar_pdv']) ? 1 : 0,
            'pode_conferir_caixa' => isset($_POST['pode_conferir_caixa']) ? 1 : 0,
        ];

        $resultado = $this->service->criar($dados);

        if (!$resultado['ok']) {
            $this->flash('error', implode('<br>', $resultado['erros']));
            $_SESSION['form_data'] = $dados;
            $this->redirecionar(self::BASE_URL . '?action=novo');
        }

        $novoUsuarioId = (int) ($resultado['id'] ?? 0);
        $this->sincronizarPermissoesPersonalizadas(
            $novoUsuarioId,
            (int) $dados['nivel_acesso_id'],
            $modulosSelecionados
        );

        $this->audit->registrar(
            'cadastros_usuarios',
            'CRIAR',
            'usuario',
            $novoUsuarioId,
            'Usuario criado: ' . ($dados['nome'] ?? ''),
            [
                'email' => $dados['email'] ?? null,
                'nivel_acesso_id' => $dados['nivel_acesso_id'] ?? null,
            ]
        );

        $this->flash('success', 'Usuario criado com sucesso.');
        $this->redirecionar();
    }

    private function atualizar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->redirecionar();

        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) { $this->flash('error', 'ID nao informado.'); $this->redirecionar(); }

        if ($this->repo->isAdminPadrao($id)) {
            $this->flash('error', 'O administrador padrao nao pode ser alterado por esta tela.');
            $this->redirecionar();
        }

        $modulosSelecionados = $this->normalizarModuloIds((array) ($_POST['modulos'] ?? []));

        $dados = [
            'nome'            => trim($_POST['nome']             ?? ''),
            'email'           => trim($_POST['email']            ?? ''),
            'telefone'        => substr(trim($_POST['telefone']  ?? ''), 0, 20),
            'nivel_acesso_id' => (int) ($_POST['nivel_acesso_id'] ?? 0),
            'senha'           => (string) ($_POST['senha']         ?? ''),
            'confirmar_senha' => (string) ($_POST['confirmar_senha'] ?? ''),
            'ativo'           => isset($_POST['ativo']) ? 1 : 0,
            'modulos'         => $modulosSelecionados,
            'pode_operar_pdv' => isset($_POST['pode_operar_pdv']) ? 1 : 0,
            'pode_conferir_caixa' => isset($_POST['pode_conferir_caixa']) ? 1 : 0,
        ];

        $resultado = $this->service->atualizar($id, $dados);

        if (!$resultado['ok']) {
            $this->flash('error', implode('<br>', $resultado['erros']));
            $_SESSION['form_data'] = $dados;
            $this->redirecionar(self::BASE_URL . '?action=editar&id=' . $id);
        }

        $this->sincronizarPermissoesPersonalizadas(
            $id,
            (int) $dados['nivel_acesso_id'],
            $modulosSelecionados
        );

        $this->audit->registrar(
            'cadastros_usuarios',
            'ATUALIZAR',
            'usuario',
            $id,
            'Usuario atualizado: ' . ($dados['nome'] ?? ''),
            [
                'email' => $dados['email'] ?? null,
                'nivel_acesso_id' => $dados['nivel_acesso_id'] ?? null,
                'ativo' => $dados['ativo'] ?? null,
            ]
        );

        $this->flash('success', 'Usuario atualizado com sucesso.');
        $this->redirecionar();
    }

    // =========================================================================
    // Actions - AJAX
    // =========================================================================

    private function excluir(): void
    {
        $this->requireAjaxPost();
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) $this->json(['success' => false, 'message' => 'ID nao informado.']);

        $resultado = $this->service->excluir($id, (int) $_SESSION['user_id']);

        if ($resultado['ok']) {
            $this->audit->registrar('cadastros_usuarios', 'EXCLUIR', 'usuario', $id, 'Usuario excluido.');
        }

        $this->json([
            'success' => $resultado['ok'],
            'message' => $resultado['ok']
                ? 'Usuario excluido com sucesso.'
                : implode(' ', $resultado['erros'] ?? []),
        ]);
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
        }

        if ((int) ($_SESSION['user_role'] ?? 0) !== 1) {
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
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View nao encontrada: {$view}</div></div>";

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function redirecionar(?string $url = null): never
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

    private function json(array $dados): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($dados);
        exit();
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function requireAjaxPost(): void
    {
        if (!$this->isAjax() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(403);
            exit();
        }
    }

    private function permissionManager(): PermissionManager
    {
        if ($this->permissionManager === null) {
            $this->permissionManager = new PermissionManager(
                $this->pdo,
                (int) ($_SESSION['user_id'] ?? 0),
                (int) ($_SESSION['user_role'] ?? 0)
            );
        }

        return $this->permissionManager;
    }

    private function sincronizarPermissoesPersonalizadas(int $targetUserId, int $nivelAcessoId, array $modulosSelecionados): void
    {
        if ($targetUserId <= 0) {
            return;
        }

        if ($nivelAcessoId === 4) {
            $this->permissionManager()->salvarPermissoesPersonalizado(
                $targetUserId,
                $this->normalizarModuloIds($modulosSelecionados)
            );
            $this->repo->salvarPermissoesPdv(
                $targetUserId,
                !empty($_POST['pode_operar_pdv']),
                !empty($_POST['pode_conferir_caixa'])
            );
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM permissoes_usuario WHERE usuario_id = :uid');
        $stmt->execute([':uid' => $targetUserId]);
        $this->repo->removerPermissoesPdv($targetUserId);
    }

    /** @param array<int|string, mixed> $moduloIds */
    private function normalizarModuloIds(array $moduloIds): array
    {
        $normalizados = [];

        foreach ($moduloIds as $moduloId) {
            $id = (int) $moduloId;
            if ($id <= 0) {
                continue;
            }
            $normalizados[$id] = $id;
        }

        return array_values($normalizados);
    }

    /**
     * @param array<int, array<string, mixed>> $modulos
     * @param array<int, int|string> $selecionados
     * @return array<int, array<string, mixed>>
     */
    private function marcarModulosSelecionados(array $modulos, array $selecionados): array
    {
        $selecionadosNormalizados = $this->normalizarModuloIds($selecionados);

        foreach ($modulos as &$modulo) {
            $moduloId = (int) ($modulo['id'] ?? 0);
            $modulo['ativo'] = in_array($moduloId, $selecionadosNormalizados, true);
        }
        unset($modulo);

        return $modulos;
    }

    /** @param array<int, array<string, mixed>> $modulos */
    private function extrairModulosAtivos(array $modulos): array
    {
        $ativos = [];

        foreach ($modulos as $modulo) {
            if (empty($modulo['ativo'])) {
                continue;
            }

            $moduloId = (int) ($modulo['id'] ?? 0);
            if ($moduloId > 0) {
                $ativos[] = $moduloId;
            }
        }

        return $this->normalizarModuloIds($ativos);
    }

    private function suporteUpgradeUrl(): string
    {
        $whats = defined('SUPORTE_WHATSAPP') ? preg_replace('/\D+/', '', (string) SUPORTE_WHATSAPP) : '';
        if (is_string($whats) && $whats !== '') {
            return 'https://wa.me/' . rawurlencode($whats);
        }

        $email = defined('LICENSE_CONTATO_EMAIL')
            ? trim((string) LICENSE_CONTATO_EMAIL)
            : 'financeiro@seu-dominio.com';

        return 'mailto:' . rawurlencode($email);
    }
}
