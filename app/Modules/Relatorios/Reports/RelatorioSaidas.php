<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioSaidas extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Relatório de Saídas (Pedidos Ativos)';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_saidas_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $busca        = trim((string)($filtros['busca']         ?? ''));
        $somenteAtivos = isset($filtros['somente_ativos']) ? (int)$filtros['somente_ativos'] === 1 : true;
        $dataInicio   = $filtros['data_inicio'] ?? date('Y-m-01');
        $dataFim      = $filtros['data_fim']    ?? date('Y-m-t');

        $this->normalizarDatas($dataInicio, $dataFim);

        $filtroBusca = $busca !== '' ? '%' . $busca . '%' : '';
        $filtroAtivo = $somenteAtivos ? 1 : 0;

        $resumo = ['produtos' => 0, 'orcamentos' => 0, 'qtd_saida' => 0, 'valor_saida' => 0];

        $params = [
            ':data_inicio'   => $dataInicio,
            ':data_fim'      => $dataFim,
            ':somente_ativos'=> $filtroAtivo,
            ':busca'         => $busca,
            ':busca_like'    => $filtroBusca,
        ];

        $stmtR = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT COALESCE(p.id::text, oi.nome_produto)) AS produtos,
                COUNT(DISTINCT oi.pedido_id) AS orcamentos,
                COALESCE(SUM(oi.quantidade), 0) AS qtd_saida,
                COALESCE(SUM(oi.valor_total_item), 0) AS valor_saida
            FROM pedido_itens oi
            INNER JOIN pedidos o ON o.id = oi.pedido_id
            LEFT JOIN produtos p ON p.id = oi.produto_id
            WHERE o.status NOT IN ('RASCUNHO', 'CANCELADO')
              AND DATE(o.data_pedido) BETWEEN :data_inicio AND :data_fim
              AND (:somente_ativos = 0 OR p.ativo = TRUE OR p.id IS NULL)
              AND (:busca = '' OR COALESCE(p.nome, oi.nome_produto) ILIKE :busca_like OR COALESCE(p.codigo, '') ILIKE :busca_like)
        ");
        $stmtR->execute($params);
        $rowR = $stmtR->fetch(PDO::FETCH_ASSOC) ?: [];

        $resumo['produtos']   = (int)($rowR['produtos']    ?? 0);
        $resumo['orcamentos'] = (int)($rowR['orcamentos']  ?? 0);
        $resumo['qtd_saida']  = (float)($rowR['qtd_saida']   ?? 0);
        $resumo['valor_saida']= (float)($rowR['valor_saida']  ?? 0);

        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(p.codigo, '-') AS codigo,
                COALESCE(p.nome, oi.nome_produto) AS nome,
                p.unidade,
                SUM(oi.quantidade) AS quantidade_saida,
                SUM(oi.valor_total_item) AS valor_saida,
                COUNT(DISTINCT oi.pedido_id) AS orcamentos
            FROM pedido_itens oi
            INNER JOIN pedidos o ON o.id = oi.pedido_id
            LEFT JOIN produtos p ON p.id = oi.produto_id
            WHERE o.status NOT IN ('RASCUNHO', 'CANCELADO')
              AND DATE(o.data_pedido) BETWEEN :data_inicio AND :data_fim
              AND (:somente_ativos = 0 OR p.ativo = TRUE OR p.id IS NULL)
              AND (:busca = '' OR COALESCE(p.nome, oi.nome_produto) ILIKE :busca_like OR COALESCE(p.codigo, '') ILIKE :busca_like)
            GROUP BY COALESCE(p.codigo, '-'), COALESCE(p.nome, oi.nome_produto), p.unidade
            ORDER BY quantidade_saida DESC, valor_saida DESC
        ");
        $stmt->execute($params);
        $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'resumo'      => $resumo,
            'saidas'      => $saidas,
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
        ];
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $resumo = $dados['resumo'];

        $html = $this->htmlResumo([
            ['label' => 'Produtos com Saída',    'valor' => $resumo['produtos']],
            ['label' => 'Orçamentos Aprovados',  'valor' => $resumo['orcamentos']],
            ['label' => 'Qtd Saída',             'valor' => $this->fmt($resumo['qtd_saida'])],
            ['label' => 'Valor Saída',           'valor' => $this->brl($resumo['valor_saida']), 'class' => 'negativo'],
        ]);

        $colunas = [
            ['label' => 'Código',      'align' => 'left',   'width' => '70px'],
            ['label' => 'Produto',     'align' => 'left'],
            ['label' => 'Un.',         'align' => 'center', 'width' => '35px'],
            ['label' => 'Qtd Saída',   'align' => 'right',  'width' => '80px'],
            ['label' => 'Valor Saída', 'align' => 'right',  'width' => '90px'],
            ['label' => 'Orçamentos',  'align' => 'right',  'width' => '70px'],
        ];

        $linhas = [];
        foreach ($dados['saidas'] as $s) {
            $linhas[] = ['cells' => [
                htmlspecialchars((string)$s['codigo']),
                htmlspecialchars((string)$s['nome']),
                htmlspecialchars((string)$s['unidade']),
                $this->fmt((float)$s['quantidade_saida']),
                $this->brl((float)$s['valor_saida']),
                (string)(int)$s['orcamentos'],
            ]];
        }

        $html .= $this->htmlTabela($colunas, $linhas);
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

        $busca = $filtros['busca'] ?? '';
        if (!empty($busca)) {
            $opcoes['filtros'] = 'Busca: ' . $busca;
        }

        return $opcoes;
    }
}
