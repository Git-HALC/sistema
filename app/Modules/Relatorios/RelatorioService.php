<?php

namespace App\Modules\Relatorios;

use PDO;
use Exception;
use DateTime;

/**
 * RelatorioService - Orquestra??o de relat?rios
 * 
 * Responsabilidades:
 * - Valida??o de filtros
 * - Orquestra??o entre Repository e PdfGenerator
 * - Transforma??es de dados antes de renderizar
 * - Gera??o de relat?rios por tipo
 * - Cache simples
 * 
 * @example
 * $service = new RelatorioService($pdo);
 * $relatorio = $service->gerarContasReceber('2026-01-01', '2026-03-03', 'PENDENTE');
 * // Retorna inst?ncia Dompdf pronta para download
 */
class RelatorioService
{
    private RelatorioRepository $repo;
    private PdfGenerator $pdfGenerator;

    public function __construct(PDO $pdo, ?PdfGenerator $pdfGenerator = null)
    {
        $this->repo = new RelatorioRepository($pdo);
        
        // Se n?o fornecer gerador, criar default
        $this->pdfGenerator = $pdfGenerator ?? new PdfGenerator([
            'empresa_nome' => $_SERVER['HTTP_HOST'] ?? 'Sistema',
            'usuario_nome' => $_SESSION['user_name'] ?? 'Sistema',
        ]);
    }

    // =========================================================================
    // RELAT?RIO: CONTAS A RECEBER
    // =========================================================================

    /**
     * Gerar relat?rio de contas a receber
     * 
     * @param string $data_inicio YYYY-MM-DD
     * @param string $data_fim YYYY-MM-DD
     * @param string $status PENDENTE|PAGO|VENCIDO|CANCELADO|todos
     * @return \Dompdf\Dompdf Inst?ncia Dompdf pronta para download/preview
     */
    public function gerarContasReceber(
        string $data_inicio = '',
        string $data_fim = '',
        string $status = 'todos'
    ) {
        // Validar e normalizar datas
        if (!empty($data_inicio) && !empty($data_fim)) {
            if (!RelatorioRepository::validarDataIntervalo($data_inicio, $data_fim)) {
                $data_inicio = '';
                $data_fim = '';
            }
        }

        // Buscar dados otimizados (1 query)
        $dados = $this->repo->buscarContasReceber($data_inicio, $data_fim, $status);

        // Gerar HTML
        $html = $this->renderizarContasReceber($dados);

        // Gerar PDF
        $filtros = "Status: " . ucfirst(str_replace('_', ' ', $status));
        
        return $this->pdfGenerator->gerarPdf(
            $html,
            'Relat?rio de Contas a Receber',
            [
                'periodo_inicio' => $data_inicio,
                'periodo_fim' => $data_fim,
                'filtros' => $filtros,
            ]
        );
    }

    /**
     * Renderizar HTML do relat?rio de contas a receber
     */
    private function renderizarContasReceber(array $dados): string
    {
        $registros = $dados['registros'] ?? [];
        $totais = $dados['totais'] ?? [];

        // Resumo
        $html = $this->renderizarResumo(
            'Contas a Receber',
            [
                'Total de T?tulos' => $totais['total_titulos'] ?? 0,
                'Valor Original' => number_format($totais['valor_original'] ?? 0, 2, ',', '.'),
                'Valor Recebido' => number_format($totais['valor_recebido'] ?? 0, 2, ',', '.'),
                'Desconto Concedido' => number_format($totais['desconto_concedido'] ?? 0, 2, ',', '.'),
                'Valor em Aberto' => number_format($totais['valor_aberto'] ?? 0, 2, ',', '.'),
            ]
        );

        // Agrupar por status
        $agrupado = [];
        foreach ($registros as $registro) {
            $status = $registro['status'];
            if (!isset($agrupado[$status])) {
                $agrupado[$status] = [];
            }
            $agrupado[$status][] = $registro;
        }

        // Renderizar por status
        foreach ($agrupado as $status => $contas) {
            $html .= '
            <div class="status-grupo">
                <div class="status-header">' . htmlspecialchars($status ?? 'Desconhecido') . ' - ' . count($contas) . ' t?tulo(s)</div>
                <table>
                    <thead>
                        <tr>
                            <th>C?digo</th>
                            <th>Descri??o</th>
                            <th>Cliente</th>
                            <th>Vencimento</th>
                            <th class="text-right">Valor Original</th>
                            <th class="text-right">Valor Recebido</th>
                            <th class="text-right">Aberto</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            $subtotal_original = 0;
            $subtotal_recebido = 0;
            $subtotal_aberto = 0;

            foreach ($contas as $conta) {
                $valor_original = (float)($conta['valor_original'] ?? 0);
                $valor_recebido = (float)($conta['valor_recebido'] ?? 0);
                $valor_aberto = (float)($conta['valor_aberto'] ?? 0);

                $subtotal_original += $valor_original;
                $subtotal_recebido += $valor_recebido;
                $subtotal_aberto += $valor_aberto;

                $vencimento = date('d/m/Y', strtotime($conta['data_vencimento']));

                $html .= '
                        <tr>
                            <td>' . htmlspecialchars($conta['codigo'] ?? '') . '</td>
                            <td>' . htmlspecialchars($conta['descricao'] ?? '') . '</td>
                            <td>' . htmlspecialchars($conta['cliente_nome'] ?? '') . '</td>
                            <td class="text-center">' . $vencimento . '</td>
                            <td class="text-right numero-moeda">R$ ' . number_format($valor_original, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda positivo">R$ ' . number_format($valor_recebido, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda negativo">R$ ' . number_format($valor_aberto, 2, ',', '.') . '</td>
                        </tr>
                ';
            }

            // Subtotal
            $html .= '
                        <tr style="background-color: #ecf0f1; font-weight: bold;">
                            <td colspan="4" style="text-align: right;">Subtotal do Status:</td>
                            <td class="text-right numero-moeda">R$ ' . number_format($subtotal_original, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda positivo">R$ ' . number_format($subtotal_recebido, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda negativo">R$ ' . number_format($subtotal_aberto, 2, ',', '.') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ';
        }

        return $html;
    }

    // =========================================================================
    // RELAT?RIO: CONTAS A PAGAR
    // =========================================================================

    /**
     * Gerar relat?rio de contas a pagar
     */
    public function gerarContasPagar(
        string $data_inicio = '',
        string $data_fim = '',
        string $status = 'todos'
    ) {
        if (!empty($data_inicio) && !empty($data_fim)) {
            if (!RelatorioRepository::validarDataIntervalo($data_inicio, $data_fim)) {
                $data_inicio = '';
                $data_fim = '';
            }
        }

        $dados = $this->repo->buscarContasPagar($data_inicio, $data_fim, $status);
        $html = $this->renderizarContasPagar($dados);

        $filtros = "Status: " . ucfirst(str_replace('_', ' ', $status));
        
        return $this->pdfGenerator->gerarPdf(
            $html,
            'Relat?rio de Contas a Pagar',
            [
                'periodo_inicio' => $data_inicio,
                'periodo_fim' => $data_fim,
                'filtros' => $filtros,
            ]
        );
    }

    /**
     * Renderizar HTML do relat?rio de contas a pagar
     */
    private function renderizarContasPagar(array $dados): string
    {
        $registros = $dados['registros'] ?? [];
        $totais = $dados['totais'] ?? [];

        $html = $this->renderizarResumo(
            'Contas a Pagar',
            [
                'Total de T?tulos' => $totais['total_titulos'] ?? 0,
                'Valor Original' => number_format($totais['valor_original'] ?? 0, 2, ',', '.'),
                'Valor Pago' => number_format($totais['valor_pago'] ?? 0, 2, ',', '.'),
                'Desconto Obtido' => number_format($totais['desconto_obtido'] ?? 0, 2, ',', '.'),
                'Valor em Aberto' => number_format($totais['valor_aberto'] ?? 0, 2, ',', '.'),
            ]
        );

        $agrupado = [];
        foreach ($registros as $registro) {
            $status = $registro['status'];
            if (!isset($agrupado[$status])) {
                $agrupado[$status] = [];
            }
            $agrupado[$status][] = $registro;
        }

        foreach ($agrupado as $status => $contas) {
            $html .= '
            <div class="status-grupo">
                <div class="status-header">' . htmlspecialchars($status ?? 'Desconhecido') . ' - ' . count($contas) . ' t?tulo(s)</div>
                <table>
                    <thead>
                        <tr>
                            <th>C?digo</th>
                            <th>Descri??o</th>
                            <th>Empresa/Fornecedor</th>
                            <th>Vencimento</th>
                            <th class="text-right">Valor Original</th>
                            <th class="text-right">Valor Pago</th>
                            <th class="text-right">Aberto</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            $subtotal_original = 0;
            $subtotal_pago = 0;
            $subtotal_aberto = 0;

            foreach ($contas as $conta) {
                $valor_original = (float)($conta['valor_original'] ?? 0);
                $valor_pago = (float)($conta['valor_pago'] ?? 0);
                $valor_aberto = (float)($conta['valor_aberto'] ?? 0);

                $subtotal_original += $valor_original;
                $subtotal_pago += $valor_pago;
                $subtotal_aberto += $valor_aberto;

                $vencimento = date('d/m/Y', strtotime($conta['data_vencimento']));

                $html .= '
                        <tr>
                            <td>' . htmlspecialchars($conta['codigo'] ?? '') . '</td>
                            <td>' . htmlspecialchars($conta['descricao'] ?? '') . '</td>
                            <td>' . htmlspecialchars($conta['empresa_nome'] ?? '') . '</td>
                            <td class="text-center">' . $vencimento . '</td>
                            <td class="text-right numero-moeda">R$ ' . number_format($valor_original, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda positivo">R$ ' . number_format($valor_pago, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda negativo">R$ ' . number_format($valor_aberto, 2, ',', '.') . '</td>
                        </tr>
                ';
            }

            $html .= '
                        <tr style="background-color: #ecf0f1; font-weight: bold;">
                            <td colspan="4" style="text-align: right;">Subtotal do Status:</td>
                            <td class="text-right numero-moeda">R$ ' . number_format($subtotal_original, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda positivo">R$ ' . number_format($subtotal_pago, 2, ',', '.') . '</td>
                            <td class="text-right numero-moeda negativo">R$ ' . number_format($subtotal_aberto, 2, ',', '.') . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ';
        }

        return $html;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Renderizar se??o de resumo padronizada
     */
    private function renderizarResumo(string $titulo, array $itens): string
    {
        $html = '<div class="resumo"><h3>' . htmlspecialchars($titulo) . '</h3>';

        foreach ($itens as $label => $valor) {
            $classe_valor = '';
            
            // Aplicar classe de cor baseado no label
            if (strpos(strtolower($label), 'aberto') !== false || strpos(strtolower($label), 'desconto') !== false) {
                $classe_valor = 'negativo';
            } elseif (strpos(strtolower($label), 'recebido') !== false || strpos(strtolower($label), 'pago') !== false) {
                $classe_valor = 'positivo';
            }

            $classe_valor = $classe_valor ? " class=\"resumo-valor $classe_valor\"" : ' class="resumo-valor"';

            $html .= '
            <div class="resumo-item">
                <span class="resumo-label">' . htmlspecialchars($label) . ':</span>
                <span' . $classe_valor . '>' . htmlspecialchars((string)$valor) . '</span>
            </div>
            ';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Obter inst?ncia PdfGenerator para opera??es avan?adas
     */
    public function getPdfGenerator(): PdfGenerator
    {
        return $this->pdfGenerator;
    }

    /**
     * Obter inst?ncia Repository para acesso direto a dados
     */
    public function getRepository(): RelatorioRepository
    {
        return $this->repo;
    }
}
