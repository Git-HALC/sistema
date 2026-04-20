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

        if (($_GET['export'] ?? '') === 'csv') {
            $this->exportarCsv();
            return;
        }

        if ($action === 'detalhes') {
            $this->detalhes();
            return;
        }

        $this->index();
    }

    private function detalhes(): void
    {
        // Limpa qualquer output em buffer (notices/warnings que quebrariam o JSON)
        while (ob_get_level() > 0) { ob_end_clean(); }
        ob_start();
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $ini = (string)($_GET['inicio'] ?? date('Y-m-01'));
            $fim = (string)($_GET['fim'] ?? date('Y-m-t'));
            $repo = new DreRepository($this->pdo);

            // + por categoria (subitem)
            if (!empty($_GET['categoria_id'])) {
                $movs = $repo->movimentacoesPorCategoria(
                    (int)$_GET['categoria_id'], $ini, $fim
                );
                $payload = [
                    'ok' => true,
                    'categoria_id' => (int)$_GET['categoria_id'],
                    'inicio' => $ini,
                    'fim' => $fim,
                    'movimentacoes' => $movs,
                ];
            } else {
                $bloco = (string)($_GET['bloco'] ?? '');
                if ($bloco === '') {
                    throw new \RuntimeException('informe bloco ou categoria_id.');
                }
                $grupos = $repo->detalhesBloco($bloco, $ini, $fim);
                $payload = [
                    'ok' => true,
                    'bloco' => $bloco,
                    'inicio' => $ini,
                    'fim' => $fim,
                    'grupos' => $grupos,
                ];
            }
            ob_end_clean();
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) { ob_end_clean(); }
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function exportarCsv(): void
    {
        $ini = isset($_GET['data_inicio']) && $_GET['data_inicio'] !== '' ? (string)$_GET['data_inicio'] : date('Y-m-01');
        $fim = isset($_GET['data_fim']) && $_GET['data_fim'] !== '' ? (string)$_GET['data_fim'] : date('Y-m-t');
        $dre = $this->service->gerarPorPeriodo($ini, $fim);
        $blocos = $dre['blocos'] ?? [];
        $receitaBruta = (float)($blocos['01_receita_bruta']['valor'] ?? 0);

        $filename = sprintf('DRE_%s_a_%s.csv', $ini, $fim);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Bloco', 'Descricao', 'Valor (R$)', 'Margem %'], ';');
        foreach ($blocos as $chave => $bloco) {
            $valor = (float)($bloco['valor'] ?? 0);
            $margem = $receitaBruta > 0 ? ($valor / $receitaBruta) * 100 : 0;
            fputcsv($out, [
                $chave,
                (string)($bloco['label'] ?? ''),
                number_format($valor, 2, ',', '.'),
                number_format($margem, 1, ',', '.') . '%',
            ], ';');
            foreach ($bloco['detalhes'] ?? [] as $d) {
                fputcsv($out, ['  -> ' . $chave, (string)$d['nome'], number_format((float)$d['valor'], 2, ',', '.'), ''], ';');
            }
        }
        fclose($out);
        exit;
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
