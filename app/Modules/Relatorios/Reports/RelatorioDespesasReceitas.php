<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioDespesasReceitas extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Demonstrativo de Despesas e Receitas';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'despesas_receitas_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $ini = $filtros['data_inicio'] ?? date('Y-m-01');
        $fim = $filtros['data_fim']    ?? date('Y-m-t');
        $det = $filtros['detalhamento'] ?? 'mensal';

        $this->normalizarDatas($ini, $fim);

        // Totais gerais
        $sqlTotais = "
            SELECT
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS total_receitas,
                SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS total_despesas
            FROM (
                SELECT valor_pago AS valor, 'receita' AS tipo
                FROM contas_receber
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT valor_pago AS valor, 'despesa' AS tipo
                FROM contas_pagar
                WHERE status IN ('Pago', 'Parcialmente Pago')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT valor AS valor,
                       CASE WHEN tipo = 'Entrada' THEN 'receita' ELSE 'despesa' END AS tipo
                FROM movimentacoes
                WHERE categoria_dre_id IS NOT NULL
                  AND DATE(data_movimentacao) BETWEEN :ini AND :fim
                  AND conta_receber_id IS NULL
                  AND conta_pagar_id IS NULL
            ) AS fluxo
        ";

        $stmt = $this->pdo->prepare($sqlTotais);
        $stmt->execute([':ini' => $ini, ':fim' => $fim]);
        $totais = $stmt->fetch(PDO::FETCH_ASSOC);

        $totais['total_receitas'] = (float)($totais['total_receitas'] ?? 0);
        $totais['total_despesas'] = (float)($totais['total_despesas'] ?? 0);
        $totais['saldo_liquido']  = $totais['total_receitas'] - $totais['total_despesas'];

        $detalhes = $this->buscarDetalhes($ini, $fim, $det);

        return [
            'totais'       => $totais,
            'detalhes'     => $detalhes,
            'detalhamento' => $det,
            'data_inicio'  => $ini,
            'data_fim'     => $fim,
        ];
    }

    private function buscarDetalhes(string $ini, string $fim, string $det): array
    {
        if ($det === 'mensal') {
            return $this->buscarDetalhesMensal($ini, $fim);
        }

        if ($det === 'trimestral') {
            return $this->buscarDetalhesTrimestral($ini, $fim);
        }

        return [];
    }

    private function buscarDetalhesMensal(string $ini, string $fim): array
    {
        $sql = "
            SELECT
                EXTRACT(YEAR  FROM data_mov) AS ano,
                EXTRACT(MONTH FROM data_mov) AS mes,
                COUNT(*)                     AS total_transacoes,
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS receitas_mes,
                SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS despesas_mes,
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE -valor END) AS saldo_mes
            FROM (
                SELECT DATE(data_pagamento) AS data_mov, valor_pago AS valor, 'receita' AS tipo
                FROM contas_receber
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT DATE(data_pagamento) AS data_mov, valor_pago AS valor, 'despesa' AS tipo
                FROM contas_pagar
                WHERE status IN ('Pago', 'Parcialmente Pago')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT DATE(data_movimentacao) AS data_mov, valor AS valor,
                       CASE WHEN tipo = 'Entrada' THEN 'receita' ELSE 'despesa' END AS tipo
                FROM movimentacoes
                WHERE categoria_dre_id IS NOT NULL
                  AND DATE(data_movimentacao) BETWEEN :ini AND :fim
                  AND conta_receber_id IS NULL
                  AND conta_pagar_id IS NULL
            ) AS fluxo
            GROUP BY ano, mes
            ORDER BY ano, mes
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ini' => $ini, ':fim' => $fim]);
        $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meses = [
            1 => 'Janeiro',   2 => 'Fevereiro', 3 => 'Março',    4 => 'Abril',
            5 => 'Maio',      6 => 'Junho',      7 => 'Julho',    8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro',   11 => 'Novembro', 12 => 'Dezembro',
        ];

        $detalhes = [];
        foreach ($periodos as $p) {
            $detalhes[] = [
                'tipo'             => 'resumo',
                'nome_mes'         => $meses[(int)$p['mes']] . '/' . (int)$p['ano'],
                'receitas_mes'     => (float)$p['receitas_mes'],
                'despesas_mes'     => (float)$p['despesas_mes'],
                'saldo_mes'        => (float)$p['saldo_mes'],
                'total_transacoes' => (int)$p['total_transacoes'],
                'ano'              => (int)$p['ano'],
                'mes'              => (int)$p['mes'],
            ];
        }

        return $detalhes;
    }

    private function buscarDetalhesTrimestral(string $ini, string $fim): array
    {
        $sql = "
            SELECT
                EXTRACT(YEAR FROM data_mov) AS ano,
                CASE
                    WHEN EXTRACT(MONTH FROM data_mov) BETWEEN 1 AND 3 THEN 1
                    WHEN EXTRACT(MONTH FROM data_mov) BETWEEN 4 AND 6 THEN 2
                    WHEN EXTRACT(MONTH FROM data_mov) BETWEEN 7 AND 9 THEN 3
                    ELSE 4
                END AS trimestre,
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END) AS receitas_trimestre,
                SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END) AS despesas_trimestre,
                SUM(CASE WHEN tipo = 'receita' THEN valor ELSE -valor END) AS saldo_trimestre
            FROM (
                SELECT DATE(data_pagamento) AS data_mov, valor_pago AS valor, 'receita' AS tipo
                FROM contas_receber
                WHERE status IN ('PAGO', 'PARCIALMENTE_PAGO')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT DATE(data_pagamento) AS data_mov, valor_pago AS valor, 'despesa' AS tipo
                FROM contas_pagar
                WHERE status IN ('Pago', 'Parcialmente Pago')
                  AND DATE(data_pagamento) BETWEEN :ini AND :fim
                  AND data_pagamento IS NOT NULL

                UNION ALL

                SELECT DATE(data_movimentacao) AS data_mov, valor AS valor,
                       CASE WHEN tipo = 'Entrada' THEN 'receita' ELSE 'despesa' END AS tipo
                FROM movimentacoes
                WHERE categoria_dre_id IS NOT NULL
                  AND DATE(data_movimentacao) BETWEEN :ini AND :fim
                  AND conta_receber_id IS NULL
                  AND conta_pagar_id IS NULL
            ) AS fluxo
            GROUP BY ano, trimestre
            ORDER BY ano, trimestre
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ini' => $ini, ':fim' => $fim]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['receitas_trimestre'] = (float)$r['receitas_trimestre'];
            $r['despesas_trimestre'] = (float)$r['despesas_trimestre'];
            $r['saldo_trimestre']    = (float)$r['saldo_trimestre'];
            $r['periodo']            = (int)$r['ano'] . ' — T' . (int)$r['trimestre'];
        }

        return $rows;
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $totais   = $dados['totais'];
        $det      = $dados['detalhamento'];
        $detalhes = $dados['detalhes'];

        // Resumo
        $html = $this->htmlResumo([
            ['label' => 'Total de Receitas', 'valor' => $this->brl($totais['total_receitas']), 'class' => 'positivo'],
            ['label' => 'Total de Despesas', 'valor' => $this->brl($totais['total_despesas']), 'class' => 'negativo'],
            ['label' => 'Saldo Líquido',     'valor' => $this->brl($totais['saldo_liquido']),
             'class' => $totais['saldo_liquido'] >= 0 ? 'positivo' : 'negativo'],
        ]);

        if (empty($detalhes)) {
            return $html;
        }

        if ($det === 'mensal') {
            $colunas = [
                ['label' => 'Mês/Ano',             'align' => 'left'],
                ['label' => 'Total Receitas',       'align' => 'right'],
                ['label' => 'Total Despesas',       'align' => 'right'],
                ['label' => 'Saldo Mensal',         'align' => 'right'],
            ];

            $linhas = [];
            foreach ($detalhes as $d) {
                if ($d['tipo'] !== 'resumo') continue;
                $saldoClass = $d['saldo_mes'] >= 0 ? 'positivo' : 'negativo';
                $linhas[] = [
                    'cells' => [
                        htmlspecialchars($d['nome_mes']),
                        '<span class="positivo">' . $this->brl($d['receitas_mes']) . '</span>',
                        '<span class="negativo">' . $this->brl($d['despesas_mes']) . '</span>',
                        '<span class="' . $saldoClass . '">' . $this->brl($d['saldo_mes']) . '</span>',
                    ],
                ];
            }
            $html .= $this->htmlTabela($colunas, $linhas);
        } elseif ($det === 'trimestral') {
            $colunas = [
                ['label' => 'Período',             'align' => 'left'],
                ['label' => 'Total Receitas',      'align' => 'right'],
                ['label' => 'Total Despesas',      'align' => 'right'],
                ['label' => 'Saldo Trimestral',    'align' => 'right'],
            ];

            $linhas = [];
            foreach ($detalhes as $d) {
                $saldoClass = $d['saldo_trimestre'] >= 0 ? 'positivo' : 'negativo';
                $linhas[] = [
                    'cells' => [
                        htmlspecialchars($d['periodo']),
                        '<span class="positivo">' . $this->brl($d['receitas_trimestre']) . '</span>',
                        '<span class="negativo">' . $this->brl($d['despesas_trimestre']) . '</span>',
                        '<span class="' . $saldoClass . '">' . $this->brl($d['saldo_trimestre']) . '</span>',
                    ],
                ];
            }
            $html .= $this->htmlTabela($colunas, $linhas);
        }

        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = parent::buildPdfOpcoes($filtros);
        $det    = $filtros['detalhamento'] ?? 'mensal';
        $labels = ['mensal' => 'Mensal', 'trimestral' => 'Trimestral', 'resumido' => 'Resumido'];
        $opcoes['filtros'] = 'Detalhamento: ' . ($labels[$det] ?? $det);
        return $opcoes;
    }
}
