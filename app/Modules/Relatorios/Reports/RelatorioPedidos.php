<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioPedidos extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Relatório de Pedidos';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_pedidos_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $ini    = $filtros['data_inicio'] ?? date('Y-m-01');
        $fim    = $filtros['data_fim']    ?? date('Y-m-t');
        $status = trim($filtros['status'] ?? 'todos');

        $this->normalizarDatas($ini, $fim);

        // --- Resumo ---
        $sqlResumo = "
            SELECT
                COUNT(*)                                                           AS total,
                COALESCE(SUM(valor_total), 0)                                      AS valor_total,
                COUNT(CASE WHEN status = 'RASCUNHO'    THEN 1 END)                AS rascunho,
                COUNT(CASE WHEN status = 'PENDENTE'    THEN 1 END)                AS pendente,
                COUNT(CASE WHEN status = 'EM_PROCESSO' THEN 1 END)                AS em_processo,
                COUNT(CASE WHEN status = 'APROVADO'    THEN 1 END)                AS aprovado,
                COUNT(CASE WHEN status = 'FATURADO'    THEN 1 END)                AS faturado,
                COUNT(CASE WHEN status = 'CONCLUIDO'   THEN 1 END)                AS concluido,
                COUNT(CASE WHEN status = 'CANCELADO'   THEN 1 END)                AS cancelado,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO') THEN valor_total END), 0) AS valor_ativo
            FROM pedidos
            WHERE ativo = TRUE
              AND DATE(data_pedido) BETWEEN :ini AND :fim
        ";

        $stmt = $this->pdo->prepare($sqlResumo);
        $stmt->execute([':ini' => $ini, ':fim' => $fim]);
        $resumo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int)($resumo['total'] ?? 0);
        $resumo['total']       = $total;
        $resumo['valor_total'] = (float)($resumo['valor_total'] ?? 0);
        $resumo['valor_ativo'] = (float)($resumo['valor_ativo'] ?? 0);
        $resumo['valor_medio'] = $total > 0 ? $resumo['valor_total'] / $total : 0;
        $resumo['rascunho']    = (int)($resumo['rascunho']    ?? 0);
        $resumo['pendente']    = (int)($resumo['pendente']    ?? 0);
        $resumo['em_processo'] = (int)($resumo['em_processo'] ?? 0);
        $resumo['aprovado']    = (int)($resumo['aprovado']    ?? 0);
        $resumo['faturado']    = (int)($resumo['faturado']    ?? 0);
        $resumo['concluido']   = (int)($resumo['concluido']   ?? 0);
        $resumo['cancelado']   = (int)($resumo['cancelado']   ?? 0);

        // --- Lista de pedidos ---
        $params   = [':ini' => $ini, ':fim' => $fim];
        $sqlWhere = '';
        if ($status !== 'todos' && $status !== '') {
            $sqlWhere = 'AND p.status = :status';
            $params[':status'] = strtoupper($status);
        }

        $sqlPedidos = "
            SELECT
                p.numero,
                COALESCE(c.nome, '—') AS cliente,
                p.status,
                DATE(p.data_pedido)               AS data_pedido,
                p.data_entrega_prevista,
                p.data_entrega_realizada,
                p.valor_total,
                COALESCE(u.nome, '—')             AS usuario,
                p.observacoes
            FROM pedidos p
            LEFT JOIN clientes  c ON c.id = p.cliente_id
            LEFT JOIN usuarios  u ON u.id = p.usuario_id
            WHERE p.ativo = TRUE
              AND DATE(p.data_pedido) BETWEEN :ini AND :fim
              {$sqlWhere}
            ORDER BY p.data_pedido DESC, p.numero DESC
        ";

        $stmt = $this->pdo->prepare($sqlPedidos);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'resumo'      => $resumo,
            'pedidos'     => $pedidos,
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
        $resumo  = $dados['resumo'];
        $pedidos = $dados['pedidos'];

        // --- Resumo ---
        $html = $this->htmlResumo([
            ['label' => 'Total de Pedidos',       'valor' => $resumo['total'],                     'class' => 'neutro'],
            ['label' => 'Valor Total',             'valor' => $this->brl($resumo['valor_total']),   'class' => 'positivo'],
            ['label' => 'Valor Médio por Pedido',  'valor' => $this->brl($resumo['valor_medio']),   'class' => 'neutro'],
            ['label' => 'Pendentes',               'valor' => $resumo['pendente'],                  'class' => 'neutro'],
            ['label' => 'Em Processo',             'valor' => $resumo['em_processo'],               'class' => 'neutro'],
            ['label' => 'Aprovados',               'valor' => $resumo['aprovado'],                  'class' => 'positivo'],
            ['label' => 'Faturados',               'valor' => $resumo['faturado'],                  'class' => 'positivo'],
            ['label' => 'Concluídos',              'valor' => $resumo['concluido'],                 'class' => 'positivo'],
            ['label' => 'Cancelados',              'valor' => $resumo['cancelado'],                 'class' => 'negativo'],
        ]);

        // --- Tabela ---
        $colunas = [
            ['label' => '#',             'align' => 'center', 'width' => '45px'],
            ['label' => 'Cliente',       'align' => 'left'],
            ['label' => 'Status',        'align' => 'center', 'width' => '80px'],
            ['label' => 'Data Pedido',   'align' => 'center', 'width' => '75px'],
            ['label' => 'Entrega Prev.', 'align' => 'center', 'width' => '75px'],
            ['label' => 'Valor Total',   'align' => 'right',  'width' => '85px'],
            ['label' => 'Responsável',   'align' => 'left',   'width' => '110px'],
        ];

        $statusLabels = [
            'RASCUNHO'    => 'Rascunho',
            'PENDENTE'    => 'Pendente',
            'EM_PROCESSO' => 'Em Processo',
            'APROVADO'    => 'Aprovado',
            'FATURADO'    => 'Faturado',
            'CONCLUIDO'   => 'Concluído',
            'CANCELADO'   => 'Cancelado',
        ];
        $statusClasses = [
            'RASCUNHO'    => 'neutro',
            'PENDENTE'    => 'neutro',
            'EM_PROCESSO' => 'neutro',
            'APROVADO'    => 'positivo',
            'FATURADO'    => 'positivo',
            'CONCLUIDO'   => 'positivo',
            'CANCELADO'   => 'negativo',
        ];

        $linhas     = [];
        $totalValor = 0.0;
        foreach ($pedidos as $p) {
            $totalValor += (float)($p['valor_total'] ?? 0);
            $st      = strtoupper($p['status'] ?? '');
            $stLabel = $statusLabels[$st] ?? $st;
            $stClass = $statusClasses[$st] ?? 'neutro';
            $linhas[] = [
                'cells' => [
                    htmlspecialchars((string)($p['numero'] ?? '')),
                    htmlspecialchars((string)($p['cliente'] ?? '—')),
                    '<span class="' . $stClass . '">' . htmlspecialchars($stLabel) . '</span>',
                    $this->data((string)($p['data_pedido'] ?? '')),
                    $this->data((string)($p['data_entrega_prevista'] ?? '')),
                    $this->brl((float)($p['valor_total'] ?? 0)),
                    htmlspecialchars((string)($p['usuario'] ?? '—')),
                ],
            ];
        }

        $totais = [
            '',
            '<strong>Total (' . count($pedidos) . ' pedidos)</strong>',
            '', '', '',
            '<strong>' . $this->brl($totalValor) . '</strong>',
            '',
        ];

        $html .= $this->htmlTabela($colunas, $linhas, $totais);
        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = parent::buildPdfOpcoes($filtros);
        $status = $filtros['status'] ?? 'todos';
        if ($status !== 'todos' && $status !== '') {
            $label = ucfirst(strtolower(str_replace('_', ' ', $status)));
            $opcoes['filtros'] = isset($opcoes['filtros'])
                ? $opcoes['filtros'] . ' | Status: ' . $label
                : 'Status: ' . $label;
        }
        return $opcoes;
    }
}
