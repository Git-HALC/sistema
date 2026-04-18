<?php

declare(strict_types=1);

namespace App\Modules\FiscalServico;

use App\Support\CsrfProtection;
use App\Support\PermissionGate;
use PDO;

final class NotaFiscalServicoController
{
    private const BASE_URL = 'admin/fiscal-servico.php';

    private NotaFiscalServicoRepository $notaRepository;
    private EmpresaFiscalServicoRepository $configRepository;
    private NotaFiscalServicoService $service;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notaRepository = new NotaFiscalServicoRepository($pdo);
        $this->configRepository = new EmpresaFiscalServicoRepository($pdo);
        $this->service = new NotaFiscalServicoService($pdo, $this->notaRepository, $this->configRepository);
        $this->verificarAutenticacao();
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'index';

        try {
            match ($action) {
                'index' => $this->index(),
                'detalhe' => $this->detalhe(),
                'gerar' => $this->gerar(),
                'reenviar' => $this->reenviar(),
                'pdf' => $this->pdf(),
                'enviar-email' => $this->enviarEmail(),
                'cancelar' => $this->cancelar(),
                'configuracoes' => $this->configuracoes(),
                'salvar-configuracoes' => $this->salvarConfiguracoes(),
                default => $this->json(['sucesso' => false, 'erro' => 'Acao invalida.'], 404),
            };
        } catch (\Throwable $e) {
            if ($this->wantsJson()) {
                $this->json(['sucesso' => false, 'erro' => $e->getMessage()], 422);
            }

            $this->flash('error', $e->getMessage());
            if ($action === 'index') {
                $this->view('fiscal-servico/index', [
                    'page_title' => 'NFS-e Emitidas',
                    'notas' => [],
                    'filtros' => [
                        'status' => $_GET['status'] ?? null,
                        'data_inicio' => $_GET['data_inicio'] ?? null,
                        'data_fim' => $_GET['data_fim'] ?? null,
                    ],
                ]);
            }

            $this->redirect(self::BASE_URL . '?action=index');
        }
    }

    private function index(): void
    {
        $filtros = [
            'status' => $_GET['status'] ?? null,
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null,
        ];

        $this->view('fiscal-servico/index', [
            'page_title' => 'NFS-e Emitidas',
            'notas' => $this->notaRepository->listarTodas($filtros),
            'filtros' => $filtros,
        ]);
    }

    private function detalhe(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('error', 'NFS-e nao informada.');
            $this->redirect(self::BASE_URL . '?action=index');
        }

        $nota = $this->notaRepository->buscarPorId($id);
        if ($nota === null) {
            $this->flash('error', 'NFS-e nao encontrada.');
            $this->redirect(self::BASE_URL . '?action=index');
        }

        $servico = $this->service->buscarServicoDetalhado($nota->servico_id);
        if ($servico === null) {
            $this->flash('error', 'Servico vinculado nao encontrado.');
            $this->redirect(self::BASE_URL . '?action=index');
        }

        $this->view('fiscal-servico/detalhe', [
            'page_title' => 'Detalhe da NFS-e',
            'nota' => $nota,
            'servico' => $servico,
        ]);
    }

    private function gerar(): void
    {
        $this->requirePost();
        $servicoId = trim((string)($_POST['servico_id'] ?? ''));
        if ($servicoId === '') {
            $this->json(['sucesso' => false, 'erro' => 'servico_id e obrigatorio.'], 400);
        }

        $resultado = $this->service->gerarNota($servicoId);
        $payload = $resultado;
        $payload['nota'] = $resultado['nota'] instanceof NotaFiscalServico ? $resultado['nota']->toArray() : null;

        if ($this->wantsJson()) {
            $this->json($payload, $resultado['sucesso'] ? 200 : 422);
        }

        $this->flash(
            $resultado['sucesso'] ? 'success' : (($resultado['aviso'] ?? null) !== null ? 'warning' : 'error'),
            (string)($resultado['erro'] ?? $resultado['aviso'] ?? 'NFS-e gerada com sucesso.')
        );
        $this->redirectBack();
    }

    private function reenviar(): void
    {
        $this->requirePost();
        $id = (int)($_POST['id'] ?? 0);
        $nota = $this->service->enviarParaPrefeitura($id);

        if ($this->wantsJson()) {
            $this->json(['sucesso' => true, 'nota' => $nota->toArray()]);
        }

        $this->flash('success', 'NFS-e reenviada com sucesso.');
        $this->redirectBack();
    }

    private function pdf(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $pdf = $this->service->gerarPdf($id);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="nfse-' . $id . '.pdf"');
        echo $pdf;
        exit;
    }

    private function enviarEmail(): void
    {
        $this->requirePost();
        $id = (int)($_POST['id'] ?? 0);
        $enviado = $this->service->enviarEmail($id);

        $payload = [
            'sucesso' => $enviado,
            'erro' => $enviado ? null : 'Nao foi possivel enviar o e-mail da NFS-e.',
        ];

        if ($this->wantsJson()) {
            $this->json($payload, $enviado ? 200 : 422);
        }

        $this->flash($enviado ? 'success' : 'error', $enviado ? 'E-mail da NFS-e enviado com sucesso.' : 'Nao foi possivel enviar o e-mail da NFS-e.');
        $this->redirectBack();
    }

    private function cancelar(): void
    {
        $this->requirePost();
        $id = (int)($_POST['id'] ?? 0);
        $nota = $this->service->cancelarNota($id);

        if ($this->wantsJson()) {
            $this->json(['sucesso' => true, 'nota' => $nota->toArray()]);
        }

        $this->flash('success', 'NFS-e cancelada com sucesso.');
        $this->redirectBack();
    }

    private function configuracoes(): void
    {
        $this->view('fiscal-servico/configuracoes', [
            'page_title' => 'Configuracoes da NFS-e',
            'config' => $this->configRepository->buscarConfiguracao(),
        ]);
    }

    private function salvarConfiguracoes(): void
    {
        $this->requirePost();

        $config = new EmpresaFiscalServico();
        $config->cnpj = trim((string)($_POST['cnpj'] ?? ''));
        $config->razao_social = trim((string)($_POST['razao_social'] ?? ''));
        $config->inscricao_municipal = trim((string)($_POST['inscricao_municipal'] ?? ''));
        $config->codigo_municipio_ibge = trim((string)($_POST['codigo_municipio_ibge'] ?? ''));
        $config->aliquota_iss_padrao = (float)($_POST['aliquota_iss_padrao'] ?? 0);
        $config->url_webservice_homologacao = $this->nullableString($_POST['url_webservice_homologacao'] ?? null);
        $config->url_webservice_producao = $this->nullableString($_POST['url_webservice_producao'] ?? null);
        $config->usuario_webservice = $this->nullableString($_POST['usuario_webservice'] ?? null);
        $config->senha_webservice = $this->nullableString($_POST['senha_webservice'] ?? null);
        $config->ambiente = trim((string)($_POST['ambiente'] ?? 'homologacao')) ?: 'homologacao';

        $ok = $this->configRepository->salvarConfiguracao($config);

        if ($this->wantsJson()) {
            $this->json(['sucesso' => $ok], $ok ? 200 : 422);
        }

        $this->flash($ok ? 'success' : 'error', $ok ? 'Configuracoes da NFS-e salvas.' : 'Falha ao salvar configuracoes da NFS-e.');
        $this->redirect(self::BASE_URL . '?action=configuracoes');
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        $GLOBALS['__dm_skip_header_auth_redirect'] = true;
        require_once __DIR__ . '/../../../public/includes/header.php';

        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View nao encontrada: {$view}</div></div>";

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['mensagem'] = [
            'tipo' => $type,
            'texto' => $message,
        ];

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(string $path = self::BASE_URL . '?action=index'): void
    {
        header('Location: ' . tenantUrl($path));
        exit;
    }

    private function redirectBack(): void
    {
        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '') {
            header('Location: ' . $referer);
            exit;
        }

        $this->redirect(self::BASE_URL . '?action=index');
    }

    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['sucesso' => false, 'erro' => 'Metodo nao permitido.'], 405);
        }

        CsrfProtection::validateRequestOrFail();
    }

    private function wantsJson(): bool
    {
        return str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit;
        }

        PermissionGate::init($this->pdo);
        if (!PermissionGate::can('fiscal')) {
            if ($this->wantsJson()) {
                $this->json(['sucesso' => false, 'erro' => 'Sem permissao para acessar o modulo Fiscal de Servicos.'], 403);
            }

            $this->flash('error', 'Sem permissao para acessar o modulo Fiscal de Servicos.');
            header('Location: ' . tenantUrl('admin/dashboard.php'));
            exit;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }
}
