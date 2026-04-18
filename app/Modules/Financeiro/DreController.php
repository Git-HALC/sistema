<?php

namespace App\Modules\Financeiro;

use PDO;

class DreController
{
    private const BASE_URL   = '/sistema_dm/public/admin/financeiro/dre.php';
    private const LOGIN_URL  = '/sistema_dm/public/login.php';
    private const ROLES_PERMITIDOS = [1, 3];

    private DreService $service;

    public function __construct(PDO $pdo)
    {
        $this->service = new DreService(new DreRepository($pdo));
        $this->verificarAutenticacao();
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if ($method === 'POST' && $action === 'export_pdf') {
            $this->exportarPdf();
            return;
        }

        $this->index();
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    private function index(): void
    {
        $dataInicio = isset($_GET['data_inicio']) && $_GET['data_inicio'] !== '' ? (string)$_GET['data_inicio'] : null;
        $dataFim = isset($_GET['data_fim']) && $_GET['data_fim'] !== '' ? (string)$_GET['data_fim'] : null;

        if ($dataInicio && $dataFim) {
            $dre = $this->service->gerarPorPeriodo($dataInicio, $dataFim);
            $mes = (int)date('m', strtotime($dataInicio));
            $ano = (int)date('Y', strtotime($dataInicio));
        } else {
            $mes = (int)($_GET['mes'] ?? date('m'));
            $ano = (int)($_GET['ano'] ?? date('Y'));
            $dre = $this->service->gerar($mes, $ano);
        }

        $titulo = 'Demonstração de Resultado do Exercício (DRE)';

        $this->view('financeiro/dre/index', compact('dre', 'mes', 'ano', 'titulo', 'dataInicio', 'dataFim'));
    }

    private function exportarPdf(): void
    {
        $vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';

        if (!file_exists($vendorAutoload)) {
            http_response_code(500);
            echo 'Dompdf não encontrado.';
            exit;
        }

        require_once $vendorAutoload;

        $dreData = json_decode($_POST['dre_data'] ?? '', true);
        $periodo = $_POST['periodo'] ?? '';

        if (!$dreData) {
            http_response_code(400);
            echo 'Dados da DRE não encontrados.';
            exit;
        }

        // Preparar dados para a view
        ob_start();
        require __DIR__ . '/../../views/financeiro/dre/pdf.php';
        $html = ob_get_clean();

        // Configurar Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Gerar nome do arquivo
        $partes   = explode(' a ', $periodo);
        $dPartes  = explode('/', $partes[0] ?? '01/01/2000');
        $mesAno   = ($dPartes[1] ?? '00') . '-' . ($dPartes[2] ?? '0000');
        $filename = "DRE_{$mesAno}.pdf";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        echo $dompdf->output();
        exit;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verificarAutenticacao(): void
    {
        if (!isset($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), self::ROLES_PERMITIDOS, true)) {
            header('Location: ' . self::LOGIN_URL);
            exit;
        }
    }

    private function view(string $view, array $dados = []): void
    {
        extract($dados);
        require_once __DIR__ . '/../../../public/includes/header.php';
        $path = __DIR__ . '/../../views/' . $view . '.php';
        file_exists($path)
            ? require $path
            : print "<div class='container mt-4'><div class='alert alert-danger'>View não encontrada: {$view}</div></div>";
        require_once __DIR__ . '/../../../public/includes/footer.php';
    }
}
