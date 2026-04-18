<?php

namespace App\Modules\Relatorios\Reports;

use App\Modules\Relatorios\BaseReport;
use PDO;

class RelatorioEntradas extends BaseReport
{
    public function getTitulo(): string
    {
        return 'RelatÃ³rio de Entradas de Estoque';
    }

    public function getNomeArquivo(array $filtros = []): string
    {
        return 'relatorio_entradas_estoque_' . date('Ymd');
    }

    protected function fetchData(array $filtros): array
    {
        $busca = trim((string)($filtros['busca'] ?? ''));
        $somenteAtivos = isset($filtros['somente_ativos']) ? (int)$filtros['somente_ativos'] === 1 : true;
        $produtoId = isset($filtros['produto_id']) ? (int)($filtros['produto_id'] ?? 0) : 0;
        $dataInicio = trim((string)($filtros['data_inicio'] ?? ''));
        $dataFim = trim((string)($filtros['data_fim'] ?? ''));

        $condicoes = ["m.tipo = 'ENTRADA'"];
        $params = [];

        if ($somenteAtivos) {
            $condicoes[] = 'p.ativo = TRUE';
        }

        if ($busca !== '') {
            $condicoes[] = "(p.nome ILIKE :busca OR COALESCE(p.codigo, '') ILIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($produtoId > 0) {
            $condicoes[] = 'm.produto_id = :produto_id';
            $params[':produto_id'] = $produtoId;
        }

        if ($dataInicio !== '') {
            $condicoes[] = "(m.created_at AT TIME ZONE 'America/Sao_Paulo')::date >= :data_inicio";
            $params[':data_inicio'] = $dataInicio;
        }

        if ($dataFim !== '') {
            $condicoes[] = "(m.created_at AT TIME ZONE 'America/Sao_Paulo')::date <= :data_fim";
            $params[':data_fim'] = $dataFim;
        }

        $where = 'WHERE ' . implode(' AND ', $condicoes);

        $stmtResumo = $this->pdo->prepare(
            "SELECT COUNT(*) AS movimentacoes,
                    COUNT(DISTINCT m.produto_id) AS produtos,
                    COALESCE(SUM(m.quantidade), 0) AS qtd_total
             FROM produto_estoque_movimentacoes m
             INNER JOIN produtos p ON p.id = m.produto_id
             {$where}"
        );
        $stmtResumo->execute($params);
        $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->pdo->prepare(
            "SELECT m.id, p.codigo, p.nome, p.unidade, m.quantidade,
                    m.estoque_anterior, m.estoque_posterior, m.origem,
                    m.referencia_tipo, m.referencia_id, m.observacao,
                    m.created_at, COALESCE(u.nome, 'Sistema') AS usuario_nome
             FROM produto_estoque_movimentacoes m
             INNER JOIN produtos p ON p.id = m.produto_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             {$where}
             ORDER BY m.created_at DESC, m.id DESC"
        );
        $stmt->execute($params);

        return [
            'resumo' => [
                'movimentacoes' => (int)($resumo['movimentacoes'] ?? 0),
                'produtos' => (int)($resumo['produtos'] ?? 0),
                'qtd_total' => (float)($resumo['qtd_total'] ?? 0),
            ],
            'entradas' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    protected function buildPdfContent(array $dados, array $filtros): string
    {
        $resumo = $dados['resumo'];

        $html = $this->htmlResumo([
            ['label' => 'MovimentaÃ§Ãµes', 'valor' => $resumo['movimentacoes']],
            ['label' => 'Produtos', 'valor' => $resumo['produtos']],
            ['label' => 'Quantidade Total', 'valor' => $this->fmt($resumo['qtd_total'])],
        ]);

        $colunas = [
            ['label' => 'CÃ³digo', 'align' => 'left', 'width' => '70px'],
            ['label' => 'Produto', 'align' => 'left'],
            ['label' => 'Qtd', 'align' => 'right', 'width' => '60px'],
            ['label' => 'Antes', 'align' => 'right', 'width' => '60px'],
            ['label' => 'Depois', 'align' => 'right', 'width' => '60px'],
            ['label' => 'UsuÃ¡rio', 'align' => 'left', 'width' => '100px'],
            ['label' => 'Data', 'align' => 'left', 'width' => '90px'],
        ];

        $linhas = [];
        foreach ($dados['entradas'] as $entrada) {
            $linhas[] = ['cells' => [
                htmlspecialchars((string)($entrada['codigo'] ?? '')),
                htmlspecialchars((string)($entrada['nome'] ?? '')),
                $this->fmt((float)($entrada['quantidade'] ?? 0)),
                $this->fmt((float)($entrada['estoque_anterior'] ?? 0)),
                $this->fmt((float)($entrada['estoque_posterior'] ?? 0)),
                htmlspecialchars((string)($entrada['usuario_nome'] ?? 'Sistema')),
                $this->dataHora((string)($entrada['created_at'] ?? '')),
            ]];
        }

        return $html . $this->htmlTabela($colunas, $linhas);
    }

    protected function buildPdfOpcoes(array $filtros): array
    {
        $opcoes = [];
        $partes = [];

        if (!empty($filtros['busca'])) {
            $partes[] = 'Busca: ' . $filtros['busca'];
        }

        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $partes[] = 'PerÃ­odo: ' . $this->data((string)$filtros['data_inicio']) . ' a ' . $this->data((string)$filtros['data_fim']);
        }

        if ($partes !== []) {
            $opcoes['filtros'] = implode(' | ', $partes);
        }

        return $opcoes;
    }
}
