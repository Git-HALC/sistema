<?php

namespace App\Modules\Relatorios;

use PDO;
use Exception;

/**
 * RelatorioController - Dispatcher HTTP para relatórios
 * 
 * Responsabilidades:
 * - Verificar autenticação e autorização
 * - Parsear filtros GET/POST
 * - Validar entrada
 * - Delegar para Service
 * - Retornar PDF/HTML/JSON conforme solicitado
 * 
 * Padrão MVC: Dispatcher → Controller → Service → Repository
 * 
 * Routes:
 * - ?action=contas-receber&export=pdf
 * - ?action=contas-pagar&export=pdf
 * - ?action=movimentacoes&export=pdf
 * - ?action=dre&export=pdf
 */
class RelatorioController
{
    private RelatorioService $service;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new RelatorioService($pdo);

        // Verificar autenticação
        $this->verificarAutenticacao();
    }

    /**
     * Gerenciar requisição HTTP
     */
    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'listar';
        $export = $_GET['export'] ?? $_POST['export'] ?? null;

        // Se for exportação, gerar PDF
        if ($export === 'pdf') {
            $this->exportarPdf($action);
            return; // exit() dentro da função
        }

        // Senão, mostrar formulário/preview
        match ($action) {
            'contas-receber' => $this->showContasReceber(),
            'contas-pagar' => $this->showContasPagar(),
            'movimentacoes' => $this->showMovimentacoes(),
            'dre' => $this->showDre(),
            'produtos' => $this->showProdutos(),
            default => $this->showFormularioSelecao(),
        };
    }

    /**
     * Exportar PDF
     */
    private function exportarPdf(string $tipo): void
    {
        try {
            $data_inicio = $_GET['data_inicio'] ?? $_POST['data_inicio'] ?? '';
            $data_fim = $_GET['data_fim'] ?? $_POST['data_fim'] ?? '';
            $status = $_GET['status'] ?? $_POST['status'] ?? 'todos';

            // Sanitizar inputs
            $data_inicio = preg_replace('/[^0-9\-]/', '', $data_inicio);
            $data_fim = preg_replace('/[^0-9\-]/', '', $data_fim);
            $status = preg_replace('/[^a-zA-Z0-9_]/', '', $status);

            match ($tipo) {
                'contas-receber' => $this->service->gerarContasReceber($data_inicio, $data_fim, $status)->download(
                    'contas_receber_' . date('Y-m-d')
                ),
                'contas-pagar' => $this->service->gerarContasPagar($data_inicio, $data_fim, $status)->download(
                    'contas_pagar_' . date('Y-m-d')
                ),
                default => $this->erroJson('Tipo de relatório não suportado'),
            };
        } catch (Exception $e) {
            $this->erroJson('Erro ao gerar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar formulário de contas a receber
     */
    private function showContasReceber(): void
    {
        $data_inicio = $_GET['data_inicio'] ?? $_POST['data_inicio'] ?? '';
        $data_fim = $_GET['data_fim'] ?? $_POST['data_fim'] ?? '';
        $status = $_GET['status'] ?? $_POST['status'] ?? 'todos';

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Contas a Receber</title>
            <style>
                body { font-family: Arial; max-width: 800px; margin: 50px auto; }
                form { background: #f5f5f5; padding: 20px; border-radius: 8px; }
                input, select, button { padding: 8px; margin: 5px; border: 1px solid #ccc; border-radius: 4px; }
                button { background: #007bff; color: white; cursor: pointer; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h1>Relatório de Contas a Receber</h1>
            <form method="POST">
                <div>
                    <label>Data Início:</label>
                    <input type="date" name="data_inicio" value="$data_inicio">
                </div>
                <div>
                    <label>Data Fim:</label>
                    <input type="date" name="data_fim" value="$data_fim">
                </div>
                <div>
                    <label>Status:</label>
                    <select name="status">
                        <option value="todos">Todos</option>
                        <option value="PENDENTE" " . ($status === 'PENDENTE' ? 'selected' : '') . ">Pendente</option>
                        <option value="PAGO" " . ($status === 'PAGO' ? 'selected' : '') . ">Pago</option>
                        <option value="VENCIDO" " . ($status === 'VENCIDO' ? 'selected' : '') . ">Vencido</option>
                    </select>
                </div>
                <button type="submit" name="export" value="pdf">Exportar PDF</button>
                <button type="submit">Ver Relatório</button>
            </form>
        </body>
        </html>
        HTML;
    }

    /**
     * Mostrar formulário de contas a pagar
     */
    private function showContasPagar(): void
    {
        $data_inicio = $_GET['data_inicio'] ?? $_POST['data_inicio'] ?? '';
        $data_fim = $_GET['data_fim'] ?? $_POST['data_fim'] ?? '';
        $status = $_GET['status'] ?? $_POST['status'] ?? 'todos';

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Contas a Pagar</title>
            <style>
                body { font-family: Arial; max-width: 800px; margin: 50px auto; }
                form { background: #f5f5f5; padding: 20px; border-radius: 8px; }
                input, select, button { padding: 8px; margin: 5px; border: 1px solid #ccc; border-radius: 4px; }
                button { background: #007bff; color: white; cursor: pointer; }
                button:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h1>Relatório de Contas a Pagar</h1>
            <form method="POST">
                <div>
                    <label>Data Início:</label>
                    <input type="date" name="data_inicio" value="$data_inicio">
                </div>
                <div>
                    <label>Data Fim:</label>
                    <input type="date" name="data_fim" value="$data_fim">
                </div>
                <div>
                    <label>Status:</label>
                    <select name="status">
                        <option value="todos">Todos</option>
                        <option value="PENDENTE" " . ($status === 'PENDENTE' ? 'selected' : '') . ">Pendente</option>
                        <option value="PAGO" " . ($status === 'PAGO' ? 'selected' : '') . ">Pago</option>
                        <option value="VENCIDO" " . ($status === 'VENCIDO' ? 'selected' : '') . ">Vencido</option>
                    </select>
                </div>
                <button type="submit" name="export" value="pdf">Exportar PDF</button>
                <button type="submit">Ver Relatório</button>
            </form>
        </body>
        </html>
        HTML;
    }

    /**
     * Mostrar formulário de movimentações
     */
    private function showMovimentacoes(): void
    {
        echo '<p>Relatório de Movimentações - Em desenvolvimento</p>';
    }

    /**
     * Mostrar formulário de DRE
     */
    private function showDre(): void
    {
        echo '<p>Relatório de DRE - Em desenvolvimento</p>';
    }

    /**
     * Mostrar formulário de produtos
     */
    private function showProdutos(): void
    {
        echo '<p>Relatório de Produtos - Em desenvolvimento</p>';
    }

    /**
     * Mostrar formulário de seleção inicial
     */
    private function showFormularioSelecao(): void
    {
        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatórios</title>
            <style>
                body { font-family: Arial; max-width: 600px; margin: 50px auto; }
                .relatorios { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .relatorio-box { border: 1px solid #ccc; padding: 20px; border-radius: 8px; text-align: center; }
                .relatorio-box a { display: block; padding: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
                .relatorio-box a:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <h1>Relatórios do Sistema</h1>
            <div class="relatorios">
                <div class="relatorio-box">
                    <h3>Contas a Receber</h3>
                    <p>Situação de recebimentos</p>
                    <a href="?action=contas-receber">Acessar</a>
                </div>
                <div class="relatorio-box">
                    <h3>Contas a Pagar</h3>
                    <p>Situação de pagamentos</p>
                    <a href="?action=contas-pagar">Acessar</a>
                </div>
                <div class="relatorio-box">
                    <h3>Movimentações</h3>
                    <p>Histórico de movimento</p>
                    <a href="?action=movimentacoes">Acessar</a>
                </div>
                <div class="relatorio-box">
                    <h3>DRE</h3>
                    <p>Demonstração de Resultado</p>
                    <a href="?action=dre">Acessar</a>
                </div>
                <div class="relatorio-box">
                    <h3>Produtos</h3>
                    <p>Estoque e movimentação</p>
                    <a href="?action=produtos">Acessar</a>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Verificar autenticação
     */
    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? 0, [1, 2])) {
            header('Location: /sistema_dm/public/login.php');
            exit();
        }
    }

    /**
     * Retornar erro em JSON
     */
    private function erroJson(string $mensagem): void
    {
        header('Content-Type: application/json');
        echo json_encode(['erro' => $mensagem]);
        exit();
    }
}
