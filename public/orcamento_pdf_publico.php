<?php

require_once __DIR__ . '/../config/database.php';

use App\Modules\Orcamento\OrcamentoItemRepository;
use App\Modules\Orcamento\OrcamentoRepository;
use App\Modules\Orcamento\OrcamentoService;
use App\Support\ShareLinkSigner;
use Dompdf\Dompdf;
use Dompdf\Options;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = trim((string)($_GET['token'] ?? ''));

if ($id <= 0 || $token === '') {
    http_response_code(400);
    echo 'Link inválido.';
    exit();
}

$service = new OrcamentoService(
    $db,
    new OrcamentoRepository($db),
    new OrcamentoItemRepository($db)
);

$resultado = $service->buscarParaEdicao($id);
if ($resultado === null) {
    http_response_code(404);
    echo 'Orçamento não encontrado.';
    exit();
}

$orcamento = $resultado['orcamento'];
$itens = $resultado['itens'];

if (!ShareLinkSigner::validarTokenOrcamento((int)$orcamento->id, (string)$orcamento->dataEmissao, (float)$orcamento->valorTotal, $token)) {
    http_response_code(403);
    echo 'Token inválido.';
    exit();
}

$logoHtml = '';
$helperPath = __DIR__ . '/admin/financeiro/relatorio_helper.php';
if (file_exists($helperPath)) {
    require_once $helperPath;
    if (function_exists('getLogoHtml')) {
        $logoHtml = getLogoHtml('logo');
    }
}

ob_start();
require __DIR__ . '/../app/views/orcamentos/pdf.php';
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$codigo = $orcamento->codigo ?? ('ORC-' . $orcamento->id);
$filename = 'orcamento_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $codigo) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $dompdf->output();
exit();
