<?php

namespace App\Modules\EmpresaDados;

use App\Support\PermissionGate;
use PDO;

/**
 * Controller do modulo de dados da empresa.
 */
class EmpresaDadosController
{
    private const BASE_URL = 'admin/dados-empresa.php';

    private EmpresaDadosService $service;

    /**
     * @param PDO $tenantPdo Conexao do tenant.
     */
    public function __construct(private readonly PDO $tenantPdo)
    {
        $this->service = new EmpresaDadosService(new EmpresaDadosRepository($tenantPdo));
        $this->verificarAutenticacao();
    }

    /**
     * Despacha a requisicao do modulo.
     */
    public function handleRequest(): void
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->salvar();
            return;
        }

        $this->form();
    }

    /**
     * Exibe o formulario de dados da empresa.
     */
    private function form(): void
    {
        $empresa = $this->service->obter() ?? $this->empresaVazia();

        if (!empty($_SESSION['form_data'])) {
            $empresa = array_merge($empresa, (array)$_SESSION['form_data']);
            unset($_SESSION['form_data']);
        }

        $this->view('empresa-dados/form', [
            'page_title' => 'Dados da Empresa',
            'empresa' => $empresa,
            'nomeBloqueado' => trim((string)($empresa['nome'] ?? '')) !== '',
        ]);
    }

    /**
     * Processa a gravacao do formulario.
     */
    private function salvar(): void
    {
        $resultado = $this->service->salvar($_POST, $_FILES);

        if (!$resultado['ok']) {
            $_SESSION['form_data'] = $resultado['dados'] ?? [];
            $this->flash('error', implode('<br>', $resultado['erros'] ?? ['Nao foi possivel salvar os dados.']));
            $this->redirect(self::BASE_URL);
        }

        $this->flash('success', 'Dados da empresa salvos com sucesso.');
        $this->redirect(self::BASE_URL);
    }

    /**
     * Garante autenticacao e permissao do modulo.
     */
    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $this->route('login.php'));
            exit();
        }

        PermissionGate::init($this->tenantPdo);
        if (!PermissionGate::can('fiscal') && (int)($_SESSION['user_role'] ?? 0) !== 1) {
            $this->flash('error', 'Acesso restrito a administradores.');
            header('Location: ' . $this->route('admin/dashboard.php'));
            exit();
        }
    }

    /**
     * Renderiza uma view HTML.
     *
     * @param string $view Caminho relativo da view.
     * @param array<string, mixed> $dados Variaveis disponibilizadas.
     */
    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';

        $path = __DIR__ . '/../../views/' . $view . '.php';
        if (file_exists($path)) {
            require_once $path;
        } else {
            print "<div class='container mt-4'><div class='alert alert-danger'>View nao encontrada: {$view}</div></div>";
        }

        require_once __DIR__ . '/../../../public/includes/footer.php';
    }

    /**
     * Armazena mensagem flash.
     */
    private function flash(string $tipo, string $texto): void
    {
        $_SESSION['mensagem'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /**
     * Redireciona considerando tenant atual.
     */
    private function redirect(string $url): never
    {
        header('Location: ' . $this->route($url));
        exit();
    }

    /**
     * Resolve rotas com tenant quando disponivel.
     */
    private function route(string $path): string
    {
        return function_exists('tenantUrl') ? \tenantUrl($path) : '/sistema_dm/public/' . ltrim($path, '/');
    }

    /**
     * Estrutura padrao para formulario vazio.
     *
     * @return array<string, mixed>
     */
    private function empresaVazia(): array
    {
        return [
            'id' => 0,
            'nome' => '',
            'cnpj' => '',
            'contato' => '',
            'email' => '',
            'telefone' => '',
            'logradouro' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'cidade' => '',
            'uf' => '',
            'cep' => '',
            'inscricao_estadual' => '',
            'inscricao_municipal' => '',
            'codigo_municipio' => '',
            'regime_tributario' => '1',
            'ambiente_nfe' => '2',
            'serie_nfe' => '001',
            'proximo_numero_nfe' => 1,
            'certificado_path' => '',
            'certificado_senha' => '',
        ];
    }
}