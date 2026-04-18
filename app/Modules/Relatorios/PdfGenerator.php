<?php

namespace App\Modules\Relatorios;

use Dompdf\Dompdf;
use Dompdf\Options;
use DateTime;
use Exception;

/**
 * PdfGenerator - Gerador centralizado de PDFs para relat?rios
 * 
 * Responsabilidades:
 * - Centralizar configura??o Dompdf
 * - Padroniza??o de layout (cabe?alho, rodap?, estilos)
 * - Garantir UTF-8 correto
 * - Otimizar performance
 * - Remover duplica??o de CSS
 * 
 * Uso:
 * ```php
 * $generator = new PdfGenerator([
 *     'titulo' => 'Contas a Receber',
 *     'empresa_nome' => 'Minha Empresa',
 *     'empresa_cnpj' => '12.345.678/0000-00'
 * ]);
 * 
 * $generator->gerarPdf($htmlContent, 'contas_receber.pdf');
 * ```
 */
class PdfGenerator
{
    private Dompdf $dompdf;
    private array $config = [];
    private string $usuario_nome = 'Sistema';
    private DateTime $data_geracao;

    /**
     * Configura??es padr?o
     */
    private const ESTILOS_PADRAO = '
        <style>
            /* Reset */
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            /* Body */
            body {
                font-family: "Helvetica", "Arial", sans-serif;
                font-size: 11pt;
                line-height: 1.5;
                color: #212529;
                background-color: #ffffff;
                padding: 20px;
            }
            
            /* Tipografia */
            h1 { font-size: 20pt; font-weight: bold; margin: 15px 0 10px 0; color: #2c3e50; }
            h2 { font-size: 14pt; font-weight: bold; margin: 12px 0 8px 0; color: #34495e; }
            h3 { font-size: 12pt; font-weight: bold; margin: 10px 0 6px 0; color: #34495e; }
            p { margin: 5px 0; }
            
            /* Cabe?alho do Relat?rio */
            .relatorio-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #34495e;
            }
            
            .relatorio-header .empresa-nome {
                font-size: 16pt;
                font-weight: bold;
                color: #2c3e50;
            }
            
            .relatorio-header .empresa-cnpj {
                font-size: 10pt;
                color: #7f8c8d;
                margin-top: 5px;
            }
            
            .relatorio-header .titulo {
                font-size: 14pt;
                font-weight: bold;
                color: #34495e;
                margin-top: 10px;
            }
            
            .relatorio-header .periodo {
                font-size: 10pt;
                color: #7f8c8d;
                margin-top: 8px;
            }
            
            /* Resumo */
            .resumo {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 25px;
            }
            
            .resumo-item {
                display: flex;
                justify-content: space-between;
                margin: 8px 0;
                padding: 5px 0;
            }
            
            .resumo-label {
                font-weight: bold;
                color: #212529;
            }
            
            .resumo-valor {
                text-align: right;
                color: #212529;
            }
            
            .resumo-valor.destaque {
                font-weight: bold;
            }
            
            .resumo-valor.positivo {
                color: #27ae60;
                font-weight: bold;
            }
            
            .resumo-valor.negativo {
                color: #e74c3c;
                font-weight: bold;
            }
            
            /* Tabelas */
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                page-break-inside: avoid;
            }
            
            table thead {
                background-color: #34495e;
                color: #ffffff;
            }
            
            table th {
                padding: 12px 8px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #2c3e50;
                font-size: 10pt;
            }
            
            table td {
                padding: 10px 8px;
                border: 1px solid #dee2e6;
                font-size: 10pt;
            }
            
            table tbody tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            
            table tbody tr:hover {
                background-color: #ecf0f1;
            }
            
            /* Alinhamento de Colunas */
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .text-left { text-align: left; }
            
            /* Valores Monet?rios */
            .numero-moeda {
                text-align: right;
                font-family: "Courier New", monospace;
            }
            
            .numero-moeda.positivo { color: #27ae60; }
            .numero-moeda.negativo { color: #e74c3c; }
            .numero-moeda.neutro { color: #212529; }
            
            /* Grupos de Status */
            .status-grupo {
                margin: 20px 0;
                page-break-inside: avoid;
            }
            
            .status-header {
                background-color: #34495e;
                color: #ffffff;
                padding: 12px 15px;
                margin-bottom: 10px;
                border-radius: 6px;
                font-weight: bold;
            }
            
            /* Rodap? */
            .relatorio-footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
                text-align: center;
                font-size: 9pt;
                color: #7f8c8d;
            }
            
            /* Quebra de P?gina */
            .page-break {
                page-break-after: always;
            }
            
            /* Avisos */
            .alerta {
                background-color: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                padding: 12px;
                margin: 15px 0;
                color: #856404;
            }
            
            .alerta.info {
                background-color: #d1ecf1;
                border-color: #bee5eb;
                color: #0c5460;
            }
            
            .alerta.sucesso {
                background-color: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            }
            
            .alerta.erro {
                background-color: #f8d7da;
                border-color: #f5c6cb;
                color: #721c24;
            }
            
            /* Marcadores */
            ul { margin: 10px 0 10px 20px; }
            li { margin: 5px 0; }
            
            /* C?digo/Monospace */
            code { 
                font-family: "Courier New", monospace;
                background-color: #f8f9fa;
                padding: 2px 6px;
                border-radius: 3px;
            }
            
            /* Responsividade para PDF */
            @media print {
                body { margin: 0; padding: 10px; }
                table { page-break-inside: avoid; }
                tr { page-break-inside: avoid; }
            }
        </style>
    ';

    /**
     * Construtor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'empresa_nome' => 'Sistema',
            'empresa_cnpj' => '',
            'usuario_nome' => $_SESSION['user_name'] ?? 'Sistema',
            'usuario_id' => $_SESSION['user_id'] ?? null,
        ], $config);

        $this->usuario_nome = $this->config['usuario_nome'];
        $this->data_geracao = new DateTime();

        // Configurar Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false); // Bloqueia remote images
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', realpath(__DIR__ . '/../../..')); // Root do projeto

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Gerar PDF com HTML
     * 
     * @param string $htmlContent Conte?do HTML do relat?rio (SEM <html>, <body>, etc)
     * @param string $titulo T?tulo do relat?rio
     * @param array $opcoes Op??es: [
     *     'periodo_inicio' => '2026-01-01',
     *     'periodo_fim' => '2026-03-03',
     *     'filtros' => 'Status: PENDENTE, Cliente: XYZ'
     * ]
     * @return Dompdf Inst?ncia para acesso ao output
     */
    public function gerarPdf(string $htmlContent, string $titulo, array $opcoes = []): Dompdf
    {
        // Construir HTML completo
        $html = $this->construirHtmlCompleto($htmlContent, $titulo, $opcoes);

        // Carregar no Dompdf
        $this->dompdf->loadHtml($html, 'UTF-8');

        // Configurar paper
        $this->dompdf->setPaper('A4', 'portrait');

        // Renderizar
        $this->dompdf->render();

        return $this->dompdf;
    }

    /**
     * Construir HTML completo com header, footer, estilos
     */
    private function construirHtmlCompleto(string $htmlContent, string $titulo, array $opcoes = []): string
    {
        $periodo = '';
        if (!empty($opcoes['periodo_inicio']) && !empty($opcoes['periodo_fim'])) {
            try {
                $dt_inicio = new DateTime($opcoes['periodo_inicio']);
                $dt_fim = new DateTime($opcoes['periodo_fim']);
                $periodo = '<div class="periodo">Per?odo: ' . 
                    $dt_inicio->format('d/m/Y') . ' a ' . 
                    $dt_fim->format('d/m/Y') . '</div>';
            } catch (Exception $e) {
                // Ignorar se data inv?lida
            }
        }

        $filtros = '';
        if (!empty($opcoes['filtros'])) {
            $filtros = '<div class="periodo">Filtros: ' . htmlspecialchars($opcoes['filtros']) . '</div>';
        }

        $cnpj = '';
        if (!empty($this->config['empresa_cnpj'])) {
            $cnpj = '<div class="empresa-cnpj">CNPJ: ' . htmlspecialchars($this->config['empresa_cnpj']) . '</div>';
        }

        $data_geracao = $this->data_geracao->format('d/m/Y H:i:s');
        $estilos = self::ESTILOS_PADRAO;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>$titulo</title>
            $estilos
        </head>
        <body>
            <!-- CABE?ALHO -->
            <div class="relatorio-header">
                <div class="empresa-nome">{$this->config['empresa_nome']}</div>
                $cnpj
                <div class="titulo">$titulo</div>
                $periodo
                $filtros
            </div>
            
            <!-- CONTE?DO PRINCIPAL -->
            <div class="relatorio-conteudo">
                $htmlContent
            </div>
            
            <!-- RODAP? -->
            <div class="relatorio-footer">
                <div>Gerado em: $data_geracao por {$this->usuario_nome}</div>
                <div style="margin-top: 5px; font-size: 8pt;">Relat?rio gerado automaticamente pelo sistema</div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Obter output PDF como string
     */
    public function output(): string
    {
        return $this->dompdf->output();
    }

    /**
     * Fazer download do PDF
     */
    public function download(string $nomeArquivo): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $this->output();
        exit();
    }

    /**
     * Renderizar no navegador (preview)
     */
    public function preview(string $nomeArquivo = 'relatorio.pdf'): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nomeArquivo . '.pdf"');
        
        echo $this->output();
        exit();
    }

    /**
     * Salvar em arquivo
     */
    public function salvarArquivo(string $caminho): bool
    {
        try {
            $dir = dirname($caminho);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($caminho, $this->output());
            return true;
        } catch (Exception $e) {
            error_log('Erro ao salvar PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter inst?ncia Dompdf para opera??es avan?adas
     */
    public function getDompdf(): Dompdf
    {
        return $this->dompdf;
    }

    /**
     * Estat?sticas do PDF gerado
     */
    public function getStats(): array
    {
        return [
            'tamanho_bytes' => strlen($this->output()),
            'tamanho_kb' => round(strlen($this->output()) / 1024, 2),
            'data_geracao' => $this->data_geracao->format('c'),
            'usuario' => $this->usuario_nome,
        ];
    }
}
