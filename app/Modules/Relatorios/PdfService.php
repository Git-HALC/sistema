<?php

namespace App\Modules\Relatorios;

use Dompdf\Dompdf;
use Dompdf\Options;
use DateTime;

/**
 * PdfService — serviço centralizado de geração de PDF.
 *
 * Nenhum relatório gera PDF diretamente: todos delegam aqui.
 * Uso:
 *   PdfService::download($html, 'Título', 'arquivo', $opcoes);
 *   PdfService::inline($html, 'Título', 'arquivo', $opcoes);
 */
class PdfService
{
    // -------------------------------------------------------------------------
    // CSS ÚNICO COMPARTILHADO
    // -------------------------------------------------------------------------
    private const CSS = '
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #1a1a2e;
            background: #ffffff;
            padding: 0;
        }

        /* CABEÇALHO */
        .rpt-header {
            border-bottom: 2px solid #2c3e50;
            margin-bottom: 18px;
            padding-bottom: 12px;
            text-align: center;
        }
        .rpt-header .logo {
            max-height: 52px;
            margin-bottom: 6px;
        }
        .rpt-header .empresa {
            font-size: 13pt;
            font-weight: bold;
            color: #2c3e50;
        }
        .rpt-header .titulo {
            font-size: 12pt;
            font-weight: bold;
            color: #34495e;
            margin-top: 4px;
        }
        .rpt-header .meta {
            font-size: 9pt;
            color: #7f8c8d;
            margin-top: 3px;
        }

        /* RESUMO */
        .rpt-resumo {
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 16px;
            background: #f8f9fa;
        }
        .rpt-resumo-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #ecf0f1;
            font-size: 9.5pt;
        }
        .rpt-resumo-row:last-child { border-bottom: none; }
        .rpt-resumo-row .lbl { font-weight: bold; color: #2c3e50; }
        .rpt-resumo-row .val { text-align: right; }
        .rpt-resumo-row .positivo { color: #27ae60; font-weight: bold; }
        .rpt-resumo-row .negativo { color: #c0392b; font-weight: bold; }
        .rpt-resumo-row .neutro   { color: #1a1a2e; }

        /* GRUPO DE STATUS */
        .rpt-grupo {
            margin: 14px 0 6px 0;
        }
        .rpt-grupo-header {
            background: #2c3e50;
            color: #ffffff;
            padding: 7px 10px;
            font-weight: bold;
            font-size: 9.5pt;
            border-radius: 3px;
            margin-bottom: 4px;
        }

        /* TABELA */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        table thead tr {
            background: #2c3e50;
            color: #ffffff;
        }
        table th {
            padding: 7px 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1a252f;
        }
        table td {
            padding: 5px 6px;
            border: 1px solid #d5d8dc;
        }
        table tbody tr:nth-child(even) { background: #f4f6f7; }
        table tfoot tr { background: #eaf0fb; font-weight: bold; }
        table tfoot td { border-top: 2px solid #2c3e50; }

        .text-right  { text-align: right !important; }
        .text-center { text-align: center !important; }
        .text-left   { text-align: left !important; }

        .mono { font-family: "Courier New", monospace; }
        .positivo { color: #27ae60; }
        .negativo { color: #c0392b; }

        .badge-aberto    { color: #2980b9; }
        .badge-pago      { color: #27ae60; }
        .badge-recebido  { color: #27ae60; }
        .badge-vencido   { color: #c0392b; }
        .badge-parcial   { color: #e67e22; }
        .badge-cancelado { color: #95a5a6; }
        .badge-estornado { color: #c0392b; }
        .badge-entrada   { color: #27ae60; }
        .badge-saida     { color: #c0392b; }

        /* SUBTOTAL */
        .subtotal td { background: #dde4f0 !important; font-weight: bold; }

        /* RODAPÉ */
        .rpt-footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #bdc3c7;
            text-align: center;
            font-size: 8pt;
            color: #7f8c8d;
        }
    ';

    // -------------------------------------------------------------------------
    // API PÚBLICA
    // -------------------------------------------------------------------------

    /**
     * Envia o PDF como download.
     *
     * @param string $conteudo   HTML do corpo do relatório (sem <html>/<body>)
     * @param string $titulo     Título do relatório
     * @param string $filename   Nome do arquivo (sem .pdf)
     * @param array  $opcoes     ['periodo' => '...', 'filtros' => '...']
     */
    public static function download(
        string $conteudo,
        string $titulo,
        string $filename,
        array $opcoes = []
    ): void {
        $dompdf = self::build($conteudo, $titulo, $opcoes);
        $dompdf->stream($filename . '.pdf', ['Attachment' => 1]);
        exit();
    }

    /**
     * Renderiza o PDF inline no navegador.
     */
    public static function inline(
        string $conteudo,
        string $titulo,
        string $filename,
        array $opcoes = []
    ): void {
        $dompdf = self::build($conteudo, $titulo, $opcoes);
        $dompdf->stream($filename . '.pdf', ['Attachment' => 0]);
        exit();
    }

    /**
     * Retorna o PDF como string binária.
     */
    public static function output(
        string $conteudo,
        string $titulo,
        array $opcoes = []
    ): string {
        $dompdf = self::build($conteudo, $titulo, $opcoes);
        return $dompdf->output();
    }

    // -------------------------------------------------------------------------
    // HELPERS PÚBLICOS PARA OS RELATÓRIOS
    // -------------------------------------------------------------------------

    /**
     * Retorna o HTML da logo do sistema (tag <img> com data URI ou vazio).
     */
    public static function getLogoHtml(): string
    {
        $settingsPath = dirname(__DIR__, 3) . '/config/site_settings.php';
        if (!file_exists($settingsPath)) {
            return '';
        }

        $settings = include $settingsPath;
        $logoPath  = $settings['logo'] ?? '';

        if (empty($logoPath)) {
            return '';
        }

        // Converter URL relativa → caminho de arquivo absoluto
        if (strpos($logoPath, '/sistema_dm/public/') === 0) {
            $rel      = substr($logoPath, strlen('/sistema_dm/public/'));
            $fullPath = dirname(__DIR__, 3) . '/public/' . $rel;
        } else {
            $fullPath = dirname(__DIR__, 3) . '/public/assets/img/logo.png';
        }

        if (!file_exists($fullPath)) {
            return '';
        }

        $info = @getimagesize($fullPath);
        if (!$info) {
            return '';
        }

        // PNG/GIF require the GD extension to be processed by Dompdf — skip if unavailable
        if ($info['mime'] === 'image/png' && !function_exists('imagecreatefrompng')) {
            return '';
        }
        if ($info['mime'] === 'image/gif' && !function_exists('imagecreatefromgif')) {
            return '';
        }

        $data = base64_encode(file_get_contents($fullPath));
        $mime = $info['mime'];
        return '<img class="logo" src="data:' . $mime . ';base64,' . $data . '">';
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    private static function build(
        string $conteudo,
        string $titulo,
        array $opcoes
    ): Dompdf {
        $html = self::montarHtml($conteudo, $titulo, $opcoes);

        $opt = new Options();
        $opt->set('defaultFont', 'Arial');
        $opt->set('isHtml5ParserEnabled', true);
        $opt->set('isRemoteEnabled', false);
        $opt->set('chroot', dirname(__DIR__, 3));

        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $opcoes['orientacao'] ?? 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    private static function montarHtml(
        string $conteudo,
        string $titulo,
        array $opcoes
    ): string {
        $logoHtml = self::getLogoHtml();
        $css      = self::CSS;
        $agora    = (new DateTime())->format('d/m/Y H:i:s');
        $usuario  = htmlspecialchars($_SESSION['user_name'] ?? 'Sistema');

        $periodoHtml = '';
        if (!empty($opcoes['periodo'])) {
            $periodoHtml = '<div class="meta">Período: ' . htmlspecialchars($opcoes['periodo']) . '</div>';
        }

        $filtrosHtml = '';
        if (!empty($opcoes['filtros'])) {
            $filtrosHtml = '<div class="meta">Filtros: ' . htmlspecialchars($opcoes['filtros']) . '</div>';
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{$titulo}</title>
            <style>{$css}</style>
        </head>
        <body>
            <div class="rpt-header">
                {$logoHtml}
                <div class="titulo">{$titulo}</div>
                {$periodoHtml}
                {$filtrosHtml}
            </div>

            <div class="rpt-body">
                {$conteudo}
            </div>

            <div class="rpt-footer">
                Gerado em {$agora} por {$usuario} &nbsp;|&nbsp; Relatório gerado automaticamente pelo sistema
            </div>
        </body>
        </html>
        HTML;
    }
}
