<?php
declare(strict_types=1);

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

/**
 * Relatorio: Vendas PDV agregadas por nome do item (produto ou servico).
 */
class RelatorioVendasPdvPorItem extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Vendas PDV por Item';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_vendas_pdv_por_item_' . date('Ymd');
    }

    protected function fetchData(array $filtros): array
    {
        $dataInicio = $filtros['data_inicio'] ?? date('Y-m-01');
        $dataFim    = $filtros['data_fim']    ?? date('Y-m-t');
        $tipo       = $filtros['tipo']        ?? '';

        $this->normalizarDatas($dataInicio, $dataFim);

        $where = [
            "DATE(v.created_at) BETWEEN :ini AND :fim",
            "v.status = 'faturado'",
        ];
        $params = [':ini' => $dataInicio, ':fim' => $dataFim];

        if (in_array($tipo, ['PRODUTO', 'SERVICO'], true)) {
            $where[] = 'i.tipo_item = :tipo';
            $params[':tipo'] = $tipo;
        }

        $stmt = $this->pdo->prepare(
            "SELECT i.tipo_item, i.nome_item,
                    SUM(i.quantidade) AS qtd_total,
                    SUM(i.valor_total_item) AS valor_total,
                    AVG(i.valor_unitario) AS valor_unitario_medio,
                    COUNT(DISTINCT v.id) AS qtd_vendas
               FROM pdv_venda_itens i
               INNER JOIN pdv_vendas v ON v.id = i.venda_id
              WHERE " . implode(' AND ', $where) . "
              GROUP BY i.tipo_item, i.nome_item
              ORDER BY valor_total DESC"
        );
        $stmt->execute($params);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalGeral = 0.0;
        foreach ($itens as $it) {
            $totalGeral += (float)$it['valor_total'];
        }

        return [
            'itens' => $itens,
            'total_geral' => $totalGeral,
            'qtd_distintos' => count($itens),
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
    }

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $resumo = $this->htmlResumo([
            'Período' => $this->textoPeriodo($dados['data_inicio'], $dados['data_fim']),
            'Itens distintos' => (string)$dados['qtd_distintos'],
            'Total faturado' => $this->brl($dados['total_geral']),
        ]);

        $linhas = [];
        foreach ($dados['itens'] as $it) {
            $linhas[] = [
                htmlspecialchars((string)$it['tipo_item']),
                htmlspecialchars((string)$it['nome_item']),
                (string)(int)$it['qtd_total'],
                (string)(int)$it['qtd_vendas'],
                $this->brl($it['valor_unitario_medio']),
                $this->brl($it['valor_total']),
            ];
        }

        $tabela = $this->htmlGrupoHeader('Ranking por volume faturado', count($linhas))
            . $this->htmlTabela(['Tipo', 'Item', 'Qtd', 'Vendas', 'Unit. Médio', 'Total'], $linhas)
            . $this->htmlGrupoFim();

        return $resumo . $tabela;
    }
}
