<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioCrStatus extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Contas a Receber por Situação';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        $status = $filtros['status'] ?? 'todos';
        return 'contas_receber_status_' . $status . '_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $ini    = $filtros['data_inicio'] ?? '';
        $fim    = $filtros['data_fim']    ?? '';
        $status = $filtros['status']      ?? 'todos';

        if (!empty($ini) && !empty($fim)) {
            $this->normalizarDatas($ini, $fim);
        }

        $sql = "SELECT
                    cr.id,
                    cr.descricao,
                    cr.valor AS valor_original,
                    cr.valor_pago AS valor_recebido,
                    cr.data_vencimento,
                    cr.created_at AS data_emissao,
                    cr.data_pagamento AS data_recebimento,
                    cr.status,
                    cr.observacoes,
                    c.nome AS cliente_nome,
                    CASE
                        WHEN cr.data_vencimento < CURRENT_DATE AND cr.status != 'PAGO' THEN 'Vencido'
                        WHEN cr.data_vencimento = CURRENT_DATE AND cr.status != 'PAGO' THEN 'Vence Hoje'
                        WHEN cr.data_vencimento > CURRENT_DATE AND cr.data_vencimento <= CURRENT_DATE + INTERVAL '7 days' AND cr.status != 'PAGO' THEN 'Vence em Breve'
                        ELSE 'Em Dia'
                    END AS situacao,
                    CURRENT_DATE - cr.data_vencimento AS dias_vencimento
                FROM contas_receber cr
                LEFT JOIN clientes c ON cr.cliente_id = c.id
                WHERE 1=1";

        $params = [];

        if ($status !== 'todos') {
            $sql .= " AND cr.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($ini) && !empty($fim)) {
            $sql .= " AND DATE(cr.data_vencimento) BETWEEN :ini AND :fim";
            $params[':ini'] = $ini;
            $params[':fim'] = $fim;
        }

        $sql .= " ORDER BY cr.status, cr.data_vencimento ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($contas as &$conta) {
            $conta['valor_original']  = (float)($conta['valor_original']  ?? 0);
            $conta['valor_recebido']  = (float)($conta['valor_recebido']  ?? 0);
            $conta['valor_aberto']    = $conta['valor_original'] - $conta['valor_recebido'];
        }
        unset($conta);

        $totais = [
            'total_titulos'  => count($contas),
            'valor_original' => array_sum(array_column($contas, 'valor_original')),
            'valor_recebido' => array_sum(array_column($contas, 'valor_recebido')),
            'valor_aberto'   => array_sum(array_column($contas, 'valor_aberto')),
        ];

        return [
            'contas'      => $contas,
            'totais'      => $totais,
            'data_inicio' => $ini,
            'data_fim'    => $fim,
            'status'      => $status,
        ];
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $totais = $dados['totais'];
        $status = $dados['status'];

        $statusTxt = $status === 'todos' ? 'Todos os status' : $status;
        $html  = '<p style="font-size:9pt;margin-bottom:8px;">Status: <strong>' . htmlspecialchars($statusTxt) . '</strong></p>';

        $html .= $this->htmlResumo([
            ['label' => 'Total de Títulos',        'valor' => $totais['total_titulos']],
            ['label' => 'Valor Original',           'valor' => $this->brl($totais['valor_original'])],
            ['label' => 'Valor Recebido',           'valor' => $this->brl($totais['valor_recebido']), 'class' => 'positivo'],
            ['label' => 'Valor em Aberto',          'valor' => $this->brl($totais['valor_aberto']),   'class' => 'negativo'],
        ]);

        // Agrupar por status
        $grupos = [];
        foreach ($dados['contas'] as $c) {
            $grupos[$c['status']][] = $c;
        }

        $colunas = [
            ['label' => 'Cliente',            'align' => 'left'],
            ['label' => 'Doc.',               'align' => 'center', 'width' => '50px'],
            ['label' => 'Emissão',            'align' => 'center', 'width' => '70px'],
            ['label' => 'Vencimento',         'align' => 'center', 'width' => '70px'],
            ['label' => 'Valor Original',     'align' => 'right',  'width' => '90px'],
            ['label' => 'Valor Aberto',       'align' => 'right',  'width' => '90px'],
        ];

        foreach ($grupos as $grpStatus => $contas) {
            $html .= $this->htmlGrupoHeader($grpStatus, count($contas));

            $linhas = [];
            foreach ($contas as $c) {
                $estornado = !empty($c['observacoes']) && strpos($c['observacoes'], 'Título estornado em') !== false;
                $nomeCliente = htmlspecialchars($c['cliente_nome'] ?? '—');
                if ($estornado) {
                    $nomeCliente .= ' <span class="negativo">(Estornado)</span>';
                }
                $linhas[] = ['cells' => [
                    $nomeCliente,
                    htmlspecialchars((string)$c['id']),
                    $this->data((string)($c['data_emissao']   ?? '')),
                    $this->data((string)($c['data_vencimento'] ?? '')),
                    $this->brl($c['valor_original']),
                    '<span class="' . ($c['valor_aberto'] > 0 ? 'negativo' : 'positivo') . '">' . $this->brl($c['valor_aberto']) . '</span>',
                ]];
            }

            $html .= $this->htmlTabela($colunas, $linhas);
            $html .= $this->htmlGrupoFim();
        }

        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = [];

        $ini = $filtros['data_inicio'] ?? '';
        $fim = $filtros['data_fim']    ?? '';
        if (!empty($ini) && !empty($fim)) {
            $opcoes['periodo'] = $this->data($ini) . ' a ' . $this->data($fim);
        }

        $status = $filtros['status'] ?? 'todos';
        if ($status !== 'todos') {
            $opcoes['filtros'] = 'Status: ' . $status;
        }

        return $opcoes;
    }
}
