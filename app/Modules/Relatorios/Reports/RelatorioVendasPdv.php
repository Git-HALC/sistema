<?php
declare(strict_types=1);

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

/**
 * Relatorio: Vendas PDV por periodo, com totais por forma de pagamento.
 */
class RelatorioVendasPdv extends BaseReport
{
    public function getTitulo(): string
    {
        return 'Vendas PDV por Período';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_vendas_pdv_' . date('Ymd');
    }

    protected function fetchData(array $filtros): array
    {
        $dataInicio = $filtros['data_inicio'] ?? date('Y-m-01');
        $dataFim    = $filtros['data_fim']    ?? date('Y-m-t');
        $status     = $filtros['status']      ?? '';

        $this->normalizarDatas($dataInicio, $dataFim);

        $where = ['DATE(v.created_at) BETWEEN :ini AND :fim'];
        $params = [':ini' => $dataInicio, ':fim' => $dataFim];

        if (in_array($status, ['faturado', 'cancelado', 'pendente', 'em_processo', 'concluido'], true)) {
            $where[] = 'v.status = :st';
            $params[':st'] = $status;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // Listagem
        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.numero, v.status, v.valor_total, v.created_at,
                    fp.nome AS forma_pagamento_nome, fp.tipo AS forma_pagamento_tipo,
                    u.nome AS operador_nome, cli.nome AS cliente_nome,
                    (SELECT COUNT(*) FROM pdv_venda_itens i WHERE i.venda_id = v.id) AS qtd_itens
               FROM pdv_vendas v
               LEFT JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               INNER JOIN usuarios u ON u.id = v.usuario_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
             {$whereSql}
             ORDER BY v.created_at DESC, v.id DESC"
        );
        $stmt->execute($params);
        $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Totais por forma de pagamento (apenas faturadas)
        $stmtFp = $this->pdo->prepare(
            "SELECT fp.id, fp.nome, fp.tipo,
                    COALESCE(SUM(CASE WHEN v.status='faturado' THEN v.valor_total ELSE 0 END), 0) AS total,
                    COUNT(CASE WHEN v.status='faturado' THEN 1 END) AS qtd
               FROM formas_pagamento fp
               LEFT JOIN pdv_vendas v ON v.forma_pagamento_id = fp.id
                                      AND DATE(v.created_at) BETWEEN :ini AND :fim
              GROUP BY fp.id, fp.nome, fp.tipo
              HAVING COUNT(CASE WHEN v.status='faturado' THEN 1 END) > 0
              ORDER BY total DESC"
        );
        $stmtFp->execute([':ini' => $dataInicio, ':fim' => $dataFim]);
        $porForma = $stmtFp->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalGeral = 0.0;
        $qtdGeral = 0;
        foreach ($porForma as $p) {
            $totalGeral += (float)$p['total'];
            $qtdGeral += (int)$p['qtd'];
        }

        return [
            'vendas' => $vendas,
            'por_forma' => $porForma,
            'total_geral' => $totalGeral,
            'qtd_geral' => $qtdGeral,
            'ticket_medio' => $qtdGeral > 0 ? $totalGeral / $qtdGeral : 0.0,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
        ];
    }

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $resumo = $this->htmlResumo([
            'Período' => $this->textoPeriodo($dados['data_inicio'], $dados['data_fim']),
            'Qtd. vendas (faturadas)' => (string)$dados['qtd_geral'],
            'Total faturado' => $this->brl($dados['total_geral']),
            'Ticket médio' => $this->brl($dados['ticket_medio']),
        ]);

        $linhasForma = [];
        foreach ($dados['por_forma'] as $p) {
            $linhasForma[] = [
                htmlspecialchars((string)$p['nome']),
                (string)$p['qtd'],
                $this->brl($p['total']),
            ];
        }

        $tabelaForma = $this->htmlGrupoHeader('Totais por forma de pagamento', count($linhasForma))
            . $this->htmlTabela(['Forma de Pagamento', 'Qtd', 'Total'], $linhasForma)
            . $this->htmlGrupoFim();

        $linhasVendas = [];
        foreach ($dados['vendas'] as $v) {
            $linhasVendas[] = [
                '#' . (int)$v['numero'],
                date('d/m/Y H:i', strtotime((string)$v['created_at'])),
                htmlspecialchars((string)($v['cliente_nome'] ?? 'Avulso')),
                htmlspecialchars((string)($v['forma_pagamento_nome'] ?? '—')),
                htmlspecialchars((string)$v['status']),
                $this->brl($v['valor_total']),
            ];
        }

        $tabelaVendas = $this->htmlGrupoHeader('Vendas do período', count($linhasVendas))
            . $this->htmlTabela(['#', 'Data/Hora', 'Cliente', 'Pagamento', 'Status', 'Total'], $linhasVendas)
            . $this->htmlGrupoFim();

        return $resumo . $tabelaForma . $tabelaVendas;
    }
}
