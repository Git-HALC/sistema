<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use App\Support\CsrfProtection;
use App\Support\PermissionGate;
use PDO;

final class PerfilTributarioController
{
    private PerfilTributarioService $service;
    private const BASE_URL = '/sistema_dm/public/admin/fiscal/perfil.php';

    public function __construct(private readonly PDO $pdo)
    {
        $this->service = new PerfilTributarioService($pdo);
        PermissionGate::init($pdo);
        PermissionGate::require('fiscal');
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'index';
        match ($action) {
            'salvar-nfce' => $this->salvarNfce(),
            'salvar-nfse' => $this->salvarNfse(),
            'salvar-tributacao' => $this->salvarTributacao(),
            'salvar-servicos' => $this->salvarServicos(),
            'testar-sefaz' => $this->testarSefaz(),
            'autenticar-nfse' => $this->autenticarNfse(),
            default => $this->index(),
        };
    }

    private function index(): void
    {
        $dados = $this->service->obterPerfilCompleto();
        $this->render('fiscal/perfil/index', [
            'page_title' => 'Perfil Tributário',
            'perfil' => $dados['perfil'],
            'empresa' => $dados['empresa'],
            'tributacao' => $dados['tributacao'],
            'servicos' => $dados['servicos'],
            'csrfToken' => CsrfProtection::token(),
        ]);
    }

    private function salvarNfce(): void
    {
        $this->validarPost();
        $this->service->salvarConfigNfce($_POST);
        $this->flash('success', 'Configuração NFC-e atualizada.');
        $this->redirect('#tab-nfce');
    }

    private function salvarNfse(): void
    {
        $this->validarPost();
        try {
            $this->service->salvarConfigNfse($_POST, $_FILES);
            $this->flash('success', 'Configuração NFS-e Nacional atualizada.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Erro ao salvar NFS-e: ' . $e->getMessage());
        }
        $this->redirect('#tab-nfse');
    }

    private function salvarTributacao(): void
    {
        $this->validarPost();
        $linhas = [];
        $ufs = $_POST['uf'] ?? [];
        foreach ($ufs as $i => $uf) {
            $linhas[] = [
                'uf_destino' => (string)$uf,
                'icms_aliquota' => (float)str_replace(',', '.', (string)($_POST['icms_aliquota'][$i] ?? 0)),
                'icms_aliquota_inter' => (float)str_replace(',', '.', (string)($_POST['icms_aliquota_inter'][$i] ?? 0)),
                'fcp_aliquota' => (float)str_replace(',', '.', (string)($_POST['fcp_aliquota'][$i] ?? 0)),
                'simples_anexo' => (int)($_POST['simples_anexo'][$i] ?? 0),
                'simples_aliquota' => (float)str_replace(',', '.', (string)($_POST['simples_aliquota'][$i] ?? 0)),
            ];
        }
        $n = $this->service->salvarTributacaoLote($linhas);
        $this->flash('success', "Tributação por UF salva ({$n} linhas).");
        $this->redirect('#tab-tributacao');
    }

    private function salvarServicos(): void
    {
        $this->validarPost();
        $linhas = [];
        foreach (($_POST['servico_id'] ?? []) as $i => $sid) {
            $linhas[] = [
                'servico_id' => (int)$sid,
                'codigo_lc116' => (string)($_POST['codigo_lc116'][$i] ?? ''),
                'descricao_lc116' => (string)($_POST['descricao_lc116'][$i] ?? ''),
                'cnae' => (string)($_POST['cnae'][$i] ?? ''),
                'codigo_municipio' => (string)($_POST['codigo_municipio'][$i] ?? ''),
                'iss_aliquota' => (float)str_replace(',', '.', (string)($_POST['iss_aliquota'][$i] ?? 0)),
                'iss_retido' => !empty($_POST['iss_retido'][$i]),
            ];
        }
        $n = $this->service->salvarConfigServicos($linhas);
        $this->flash('success', "Configuração ISS salva ({$n} serviços).");
        $this->redirect('#tab-servicos');
    }

    private function testarSefaz(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $repo = new PerfilTributarioRepository($this->pdo);
            $perfil = $repo->obterPerfil(1);
            if (!$perfil) {
                echo json_encode(['ok' => false, 'erro' => 'Perfil não configurado.']);
                exit;
            }
            // Usa sped-nfe para ping de status do servico
            $emp = $this->pdo->query('SELECT * FROM empresa_local WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            $uf = strtoupper((string)($emp['uf'] ?? 'SP'));
            $amb = (int)($perfil['ambiente_nfce'] ?? 2);

            $tester = new SefazStatusTester();
            $resp = $tester->testar($uf, $amb);
            echo json_encode(['ok' => true, 'uf' => $uf, 'ambiente' => $amb, 'resultado' => $resp]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    private function autenticarNfse(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            CsrfProtection::validateRequestOrFail();
            $auth = new NfseNacionalAutenticador($this->pdo);
            $res = $auth->autenticar();
            echo json_encode($res);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }

    private function validarPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('');
        }
        CsrfProtection::validateRequestOrFail();
    }

    private function render(string $view, array $data): void
    {
        extract($data);
        require_once __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/' . $view . '.php';
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function redirect(string $hash = ''): never
    {
        header('Location: ' . self::BASE_URL . $hash);
        exit;
    }
}
