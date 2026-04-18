<?php

namespace App\Modules\Relatorios;

use PDO;
use DateTime;
use Throwable;

/**
 * BaseReport ? classe base abstrata para todos os relat?rios.
 *
 * Responsabilidades:
 *   - Fornecer helpers de formata??o (BRL, datas)
 *   - Fornecer helpers de renderiza??o HTML para PDF (resumo, tabela, grupos)
 *   - Centralizar exporta??o via PdfService
 *   - Definir contrato via m?todos abstratos
 */
abstract class BaseReport implements ReportInterface
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // CONTRATO OBRIGAT?RIO (implementar em cada relat?rio)
    // =========================================================================

    abstract public function getTitulo(): string;

    abstract public function getNomeArquivo(array $filtros = []): string;

    /** Busca os dados no banco. Retorna array estruturado. */
    abstract protected function fetchData(array $filtros): array;

    /**
     * Constr?i o HTML do corpo do relat?rio (sem <html>/<body>).
     * Usado pelo PdfService para montar o documento completo.
     */
    abstract protected function buildPdfContent(array $dados, array $filtros): string;

    // =========================================================================
    // IMPLEMENTA??O DE ReportInterface
    // =========================================================================

    /** Busca dados para exibi??o na tela (reutiliza fetchData). */
    public function getData(array $filtros): array
    {
        return $this->fetchData($filtros);
    }

    /** Exporta PDF via PdfService e encerra a requisi??o. */
    public function exportarPdf(array $filtros): void
    {
        $dados    = $this->fetchData($filtros);
        $conteudo = $this->buildPdfContent($dados, $filtros);
        $opcoes   = $this->buildPdfOpcoes($filtros);

        PdfService::download(
            $conteudo,
            $this->getTitulo(),
            $this->getNomeArquivo($filtros),
            $opcoes
        );
    }

    // =========================================================================
    // HELPERS DE FORMATA??O
    // =========================================================================

    /** Formata valor monet?rio BRL: "R$ 1.234,56" */
    protected function brl(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    /** Formata n?mero decimal: "1.234,56" */
    protected function fmt(float $v, int $decimals = 2): string
    {
        return number_format($v, $decimals, ',', '.');
    }

    /** Formata data: "01/01/2026" ou "?" */
    protected function data(string $d): string
    {
        if (empty($d)) return '?';
        try {
            return (new DateTime($d))->format('d/m/Y');
        } catch (Throwable $e) {
            return htmlspecialchars($d);
        }
    }

    /** Formata data e hora: "01/01/2026 10:00" ou "?" */
    protected function dataHora(string $d): string
    {
        if (empty($d)) return '?';
        try {
            return (new DateTime($d))->format('d/m/Y H:i');
        } catch (Throwable $e) {
            return htmlspecialchars($d);
        }
    }

    /** Normaliza intervalo de datas (inverte se in?cio > fim). */
    protected function normalizarDatas(string &$ini, string &$fim): void
    {
        if (empty($ini) || empty($fim)) return;
        try {
            $dtIni = new DateTime($ini);
            $dtFim = new DateTime($fim);
            if ($dtIni > $dtFim) {
                [$ini, $fim] = [$fim, $ini];
            }
            $ini = (new DateTime($ini))->format('Y-m-d');
            $fim = (new DateTime($fim))->format('Y-m-d');
        } catch (Throwable $e) {
            // manter como est?o
        }
    }

    /** Texto de per?odo formatado para o PDF: "01/01/2026 a 31/01/2026" */
    protected function textoPeriodo(string $ini, string $fim): string
    {
        if (empty($ini) || empty($fim)) return '';
        return $this->data($ini) . ' a ' . $this->data($fim);
    }

    // =========================================================================
    // HELPERS DE RENDERIZA??O HTML PARA PDF
    // =========================================================================

    /**
     * Bloco de resumo (caixa cinza com pares label ? valor).
     *
     * @param array $rows [['label' => '...', 'valor' => '...', 'class' => 'positivo|negativo|neutro'], ...]
     */
    protected function htmlResumo(array $rows): string
    {
        $html = '<div class="rpt-resumo">';
        foreach ($rows as $row) {
            $class = htmlspecialchars($row['class'] ?? 'neutro');
            $html .= '<div class="rpt-resumo-row">'
                . '<span class="lbl">' . htmlspecialchars($row['label']) . '</span>'
                . '<span class="val ' . $class . '">' . htmlspecialchars((string)$row['valor']) . '</span>'
                . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Tabela HTML para PDF.
     *
     * @param array $colunas [['label' => '...', 'align' => 'right|center|left', 'width' => '120px'], ...]
     * @param array $linhas  [['cells' => [...], 'class' => ''], ...]
     *                        Pode tamb?m ser array simples de arrays de strings (sem 'cells').
     * @param array $totais  Linha de totais (array de strings); null = sem totais
     */
    protected function htmlTabela(array $colunas, array $linhas, ?array $totais = null): string
    {
        // Cabe?alho
        $html = '<table><thead><tr>';
        foreach ($colunas as $col) {
            $style = '';
            if (!empty($col['width'])) $style .= 'width:' . $col['width'] . ';';
            if (!empty($col['align'])) $style .= 'text-align:' . $col['align'] . ';';
            $html .= '<th' . ($style ? ' style="' . $style . '"' : '') . '>'
                . htmlspecialchars($col['label'])
                . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($linhas)) {
            $html .= '<tr><td colspan="' . count($colunas) . '" style="text-align:center;color:#7f8c8d;">'
                . 'Nenhum registro encontrado.'
                . '</td></tr>';
        } else {
            foreach ($linhas as $linha) {
                $cells = is_array($linha) && isset($linha['cells']) ? $linha['cells'] : $linha;
                $rowClass = $linha['class'] ?? '';
                $html .= '<tr class="' . htmlspecialchars($rowClass) . '">';
                foreach ($cells as $i => $cell) {
                    $col   = $colunas[$i] ?? [];
                    $align = $col['align'] ?? 'left';
                    $html .= '<td class="text-' . $align . '">' . $cell . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';

        // Totais
        if ($totais !== null) {
            $html .= '<tfoot><tr>';
            foreach ($totais as $i => $cell) {
                $col   = $colunas[$i] ?? [];
                $align = $col['align'] ?? 'left';
                $html .= '<td class="text-' . $align . '">' . $cell . '</td>';
            }
            $html .= '</tr></tfoot>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Cabe?alho de grupo de status para PDF.
     */
    protected function htmlGrupoHeader(string $titulo, int $count = -1): string
    {
        $badge = $count >= 0 ? ' (' . $count . ' registro' . ($count !== 1 ? 's' : '') . ')' : '';
        return '<div class="rpt-grupo"><div class="rpt-grupo-header">'
            . htmlspecialchars($titulo) . $badge
            . '</div>';
    }

    /** Fecha um bloco de grupo iniciado por htmlGrupoHeader. */
    protected function htmlGrupoFim(): string
    {
        return '</div>';
    }

    // =========================================================================
    // OP??ES DE PDF
    // =========================================================================

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = [];

        $ini = $filtros['data_inicio'] ?? '';
        $fim = $filtros['data_fim']    ?? '';
        if (!empty($ini) && !empty($fim)) {
            $opcoes['periodo'] = $this->data($ini) . ' a ' . $this->data($fim);
        }

        $status = $filtros['status'] ?? '';
        if (!empty($status) && $status !== 'todos') {
            $opcoes['filtros'] = 'Status: ' . ucfirst($status);
        }

        return $opcoes;
    }
}
