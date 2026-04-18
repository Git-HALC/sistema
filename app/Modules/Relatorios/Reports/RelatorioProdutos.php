<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioProdutos extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Relatório de Produtos';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_produtos_' . date('Ymd');
    }

    // =========================================================================
    // DADOS
    // =========================================================================

    protected function fetchData(array $filtros): array
    {
        $busca        = trim((string)($filtros['busca']         ?? ''));
        $somenteAtivos = isset($filtros['somente_ativos']) ? (int)$filtros['somente_ativos'] === 1 : true;

        $filtroBusca = $busca !== '' ? '%' . $busca . '%' : '';
        $filtroAtivo = $somenteAtivos ? 1 : 0;

        $resumo = [
            'total_produtos'   => 0,
            'total_ativos'     => 0,
            'estoque_baixo'    => 0,
            'quantidade_total' => 0,
            'valor_custo_total'  => 0,
            'valor_venda_total'  => 0,
        ];

        $produtos = [];

        $stmtR = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_produtos,
                SUM(CASE WHEN ativo = TRUE THEN 1 ELSE 0 END) AS total_ativos,
                SUM(CASE WHEN estoque_atual <= estoque_minimo THEN 1 ELSE 0 END) AS estoque_baixo,
                COALESCE(SUM(estoque_atual), 0) AS quantidade_total,
                COALESCE(SUM(estoque_atual * preco_custo), 0) AS valor_custo_total,
                COALESCE(SUM(estoque_atual * preco_venda), 0) AS valor_venda_total
            FROM produtos
            WHERE (:somente_ativos = 0 OR ativo = TRUE)
              AND (:busca = '' OR nome ILIKE :busca_like OR codigo ILIKE :busca_like)
        ");
        $stmtR->execute([
            ':somente_ativos' => $filtroAtivo,
            ':busca'          => $busca,
            ':busca_like'     => $filtroBusca,
        ]);
        $rowR = $stmtR->fetch(PDO::FETCH_ASSOC) ?: [];

        $resumo['total_produtos']    = (int)($rowR['total_produtos']   ?? 0);
        $resumo['total_ativos']      = (int)($rowR['total_ativos']     ?? 0);
        $resumo['estoque_baixo']     = (int)($rowR['estoque_baixo']    ?? 0);
        $resumo['quantidade_total']  = (float)($rowR['quantidade_total']  ?? 0);
        $resumo['valor_custo_total'] = (float)($rowR['valor_custo_total'] ?? 0);
        $resumo['valor_venda_total'] = (float)($rowR['valor_venda_total'] ?? 0);

        $stmt = $this->pdo->prepare("
            SELECT
                codigo,
                nome,
                unidade,
                estoque_atual,
                estoque_minimo,
                preco_custo,
                preco_venda,
                ativo,
                (estoque_atual * preco_custo) AS valor_custo_estoque,
                (estoque_atual * preco_venda) AS valor_venda_estoque
            FROM produtos
            WHERE (:somente_ativos = 0 OR ativo = TRUE)
              AND (:busca = '' OR nome ILIKE :busca_like OR codigo ILIKE :busca_like)
            ORDER BY nome ASC
        ");
        $stmt->execute([
            ':somente_ativos' => $filtroAtivo,
            ':busca'          => $busca,
            ':busca_like'     => $filtroBusca,
        ]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'resumo'   => $resumo,
            'produtos' => $produtos,
        ];
    }

    // =========================================================================
    // PDF
    // =========================================================================

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $resumo = $dados['resumo'];

        $html = $this->htmlResumo([
            ['label' => 'Total de Produtos',          'valor' => $resumo['total_produtos']],
            ['label' => 'Ativos',                     'valor' => $resumo['total_ativos']],
            ['label' => 'Estoque Baixo',              'valor' => $resumo['estoque_baixo']],
            ['label' => 'Quantidade Total em Estoque','valor' => $this->fmt($resumo['quantidade_total'])],
            ['label' => 'Valor em Custo',             'valor' => $this->brl($resumo['valor_custo_total'])],
            ['label' => 'Valor em Venda',             'valor' => $this->brl($resumo['valor_venda_total'])],
        ]);

        $colunas = [
            ['label' => 'Código',              'align' => 'left',   'width' => '70px'],
            ['label' => 'Produto',             'align' => 'left'],
            ['label' => 'Un.',                 'align' => 'center', 'width' => '35px'],
            ['label' => 'Estoque',             'align' => 'right',  'width' => '60px'],
            ['label' => 'Mínimo',              'align' => 'right',  'width' => '60px'],
            ['label' => 'Preço Custo',         'align' => 'right',  'width' => '75px'],
            ['label' => 'Preço Venda',         'align' => 'right',  'width' => '75px'],
            ['label' => 'Valor Custo Estoque', 'align' => 'right',  'width' => '90px'],
            ['label' => 'Valor Venda Estoque', 'align' => 'right',  'width' => '90px'],
        ];

        $linhas = [];
        foreach ($dados['produtos'] as $p) {
            $estBaixo = (float)$p['estoque_atual'] <= (float)$p['estoque_minimo'];
            $linhas[] = ['cells' => [
                htmlspecialchars((string)$p['codigo']),
                htmlspecialchars((string)$p['nome']),
                htmlspecialchars((string)$p['unidade']),
                '<span class="' . ($estBaixo ? 'negativo' : '') . '">' . $this->fmt((float)$p['estoque_atual']) . '</span>',
                $this->fmt((float)$p['estoque_minimo']),
                $this->brl((float)$p['preco_custo']),
                $this->brl((float)$p['preco_venda']),
                $this->brl((float)$p['valor_custo_estoque']),
                $this->brl((float)$p['valor_venda_estoque']),
            ]];
        }

        $html .= $this->htmlTabela($colunas, $linhas);
        return $html;
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = ['orientacao' => 'landscape'];

        $busca = $filtros['busca'] ?? '';
        if (!empty($busca)) {
            $opcoes['filtros'] = 'Busca: ' . $busca;
        }

        return $opcoes;
    }
}
