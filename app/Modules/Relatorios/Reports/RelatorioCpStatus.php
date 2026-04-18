<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioCpStatus extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Contas a Pagar por Situação';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        $status = $filtros['status'] ?? 'todos';
        return 'contas_pagar_status_' . $status . '_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $ini    = $filtros['data_inicio'] ?? '';
        $fim    = $filtros['data_fim']    ?? '';
        $status = $filtros['status']      ?? 'todos';
        $temClienteId = $this->contasPagarTemClienteId();

        if (!empty($ini) && !empty($fim)) {
            $this->normalizarDatas($ini, $fim);
        }

        $sql = "SELECT
                    cp.id AS numero_documento,
                    cp.descricao,
                    cp.valor AS valor_original,
                    cp.valor_pago,
                    cp.data_vencimento,
                    cp.created_at AS data_emissao,
                    cp.data_pagamento,
                    cp.status,
                    cp.observacoes,
                    " . ($temClienteId ? "COALESCE(c.nome, cp.fornecedor) AS fornecedor_nome," : "cp.fornecedor AS fornecedor_nome,") . "
                    CASE
                        WHEN cp.data_vencimento < CURRENT_DATE AND cp.status NOT IN ('Pago', 'Parcialmente Pago') THEN 'Vencido'
                        WHEN cp.data_vencimento = CURRENT_DATE AND cp.status NOT IN ('Pago', 'Parcialmente Pago') THEN 'Vence Hoje'
                        WHEN cp.data_vencimento > CURRENT_DATE AND cp.data_vencimento <= CURRENT_DATE + INTERVAL '7 days' AND cp.status NOT IN ('Pago', 'Parcialmente Pago') THEN 'Vence em Breve'
                        ELSE 'Em Dia'
                    END AS situacao,
                    CURRENT_DATE - cp.data_vencimento AS dias_vencimento
                FROM contas_pagar cp
                " . ($temClienteId ? "LEFT JOIN clientes c ON cp.cliente_id = c.id" : "") . "
                WHERE 1=1";

        $params = [];

        if ($status !== 'todos') {
            $sql .= " AND cp.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($ini) && !empty($fim)) {
            $sql .= " AND DATE(cp.data_vencimento) BETWEEN :ini AND :fim";
            $params[':ini'] = $ini;
            $params[':fim'] = $fim;
        }

        $sql .= " ORDER BY cp.status, cp.data_vencimento ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($contas as &$conta) {
            $conta['valor_original'] = (float)($conta['valor_original'] ?? 0);
            $conta['valor_pago']     = (float)($conta['valor_pago']     ?? 0);
            $conta['valor_aberto']   = $conta['valor_original'] - $conta['valor_pago'];
        }
        unset($conta);

        $totais = [
            'total_titulos'  => count($contas),
            'valor_original' => array_sum(array_column($contas, 'valor_original')),
            'valor_pago'     => array_sum(array_column($contas, 'valor_pago')),
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
            ['label' => 'Total de Títulos',  'valor' => $totais['total_titulos']],
            ['label' => 'Valor Original',    'valor' => $this->brl($totais['valor_original'])],
            ['label' => 'Valor Pago',        'valor' => $this->brl($totais['valor_pago']),   'class' => 'positivo'],
            ['label' => 'Valor em Aberto',   'valor' => $this->brl($totais['valor_aberto']), 'class' => 'negativo'],
        ]);

        // Agrupar por status
        $grupos = [];
        foreach ($dados['contas'] as $c) {
            $grupos[$c['status']][] = $c;
        }

        $colunas = [
            ['label' => 'Fornecedor',        'align' => 'left'],
            ['label' => 'Doc.',              'align' => 'center', 'width' => '50px'],
            ['label' => 'Emissão',           'align' => 'center', 'width' => '70px'],
            ['label' => 'Vencimento',        'align' => 'center', 'width' => '70px'],
            ['label' => 'Valor Original',    'align' => 'right',  'width' => '90px'],
            ['label' => 'Valor Aberto',      'align' => 'right',  'width' => '90px'],
        ];

        foreach ($grupos as $grpStatus => $contas) {
            $html .= $this->htmlGrupoHeader($grpStatus, count($contas));

            $linhas = [];
            foreach ($contas as $c) {
                $estornado = !empty($c['observacoes']) && strpos($c['observacoes'], 'Título estornado em') !== false;
                $nomeForn  = htmlspecialchars($c['fornecedor_nome'] ?? '—');
                if ($estornado) {
                    $nomeForn .= ' <span class="negativo">(Estornado)</span>';
                }
                $linhas[] = ['cells' => [
                    $nomeForn,
                    htmlspecialchars((string)$c['numero_documento']),
                    $this->data((string)($c['data_emissao']    ?? '')),
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

    private function contasPagarTemClienteId(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'contas_pagar'
              AND column_name = 'cliente_id'
            LIMIT 1
        ");
        $stmt->execute();

        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    }
}
