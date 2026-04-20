<?php

declare(strict_types=1);

namespace App\Modules\Financeiro;

use PDO;

/**
 * DRE por regime de competencia (2026-04-19).
 *
 * Fonte unica: `movimentacoes` onde `afeta_dre = TRUE`.
 * Recebimentos de CR/CP tem `afeta_dre=FALSE` — nao entram no DRE (apenas saldo).
 *
 * Estrutura (R6):
 *   (1) Receita Bruta     [Receita]
 *   (2) (-) Deducoes      [Deducao]
 *   (3) = Receita Liquida
 *   (4) (-) CPV/CMV       [CPV]
 *   (5) = Lucro Bruto
 *   (6) (-) Despesas Operacionais   [Despesa Operacional]
 *   (7) = Resultado Operacional
 *   (8) (+/-) Resultado Financeiro  [Receita Financeira] - [Despesa Financeira]
 *   (9) = Resultado antes dos Tributos (LAIR)
 *  (10) (-) IRPJ/CSLL     [Tributo]
 *  (11) = Lucro Liquido
 */
class DreRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** Alias compat para DreService::gerar/gerarPorPeriodo. */
    public function gerar(string $dataInicio, string $dataFim): array
    {
        return $this->gerarCentralizado($dataInicio, $dataFim);
    }

    /**
     * Lista movimentacoes que compoem um bloco do DRE no periodo.
     * Retorna por categoria do bloco com as movs individuais (id, data, descricao, valor).
     *
     * @param string $bloco Chave do bloco (01_receita_bruta, 02_deducoes, ...)
     * @return array<int, array{categoria:string, codigo:?string, total:float, quantidade:int, movimentacoes:array}>
     */
    public function detalhesBloco(string $bloco, string $dataInicio, string $dataFim): array
    {
        // Mapeia bloco -> tipos de categoria + filtros adicionais
        $filtros = $this->filtrosDoBloco($bloco);
        if ($filtros === null) {
            return [];
        }

        [$tipos, $codigos] = $filtros;

        $tipoIn = implode(',', array_fill(0, count($tipos), '?'));
        $params = $tipos;
        $sqlCodigo = '';
        if ($codigos !== null && count($codigos) > 0) {
            $codIn = implode(',', array_fill(0, count($codigos), '?'));
            $sqlCodigo = " AND cat.codigo IN ($codIn) ";
            $params = array_merge($params, $codigos);
        }
        $params[] = $dataInicio;
        $params[] = $dataFim;

        $sql = "
            SELECT m.id, m.valor, m.descricao, m.data_movimentacao,
                   m.tipo, m.tipo_origem, m.pdv_venda_id,
                   cat.id AS categoria_id, cat.nome AS categoria_nome, cat.codigo AS categoria_codigo,
                   fp.nome AS forma_pagamento_nome,
                   v.numero AS venda_numero
              FROM movimentacoes m
              INNER JOIN categorias_dre cat ON cat.id = m.categoria_dre_id
              LEFT JOIN formas_pagamento fp ON fp.id = m.forma_pagamento_id
              LEFT JOIN pdv_vendas v ON v.id = m.pdv_venda_id
             WHERE m.afeta_dre = TRUE
               AND cat.tipo IN ($tipoIn)
               $sqlCodigo
               AND DATE(m.data_movimentacao) BETWEEN ? AND ?
             ORDER BY cat.nome, m.data_movimentacao DESC, m.id DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Agrupa por categoria
        $grupos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $catId = (int)$row['categoria_id'];
            if (!isset($grupos[$catId])) {
                $grupos[$catId] = [
                    'categoria' => (string)$row['categoria_nome'],
                    'codigo' => $row['categoria_codigo'],
                    'total' => 0.0,
                    'quantidade' => 0,
                    'movimentacoes' => [],
                ];
            }
            $grupos[$catId]['total'] += (float)$row['valor'];
            $grupos[$catId]['quantidade']++;
            $grupos[$catId]['movimentacoes'][] = [
                'id' => (int)$row['id'],
                'data' => (string)$row['data_movimentacao'],
                'descricao' => (string)($row['descricao'] ?? ''),
                'valor' => (float)$row['valor'],
                'forma_pagamento' => $row['forma_pagamento_nome'],
                'venda_numero' => $row['venda_numero'] ? (int)$row['venda_numero'] : null,
                'tipo_origem' => (string)($row['tipo_origem'] ?? ''),
            ];
        }
        return array_values($grupos);
    }

    /**
     * Lista movimentacoes de uma categoria DRE especifica no periodo.
     * @return array<int, array<string,mixed>>
     */
    public function movimentacoesPorCategoria(int $categoriaId, string $dataInicio, string $dataFim): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.valor, m.descricao, m.data_movimentacao,
                    m.tipo, m.tipo_origem, m.pdv_venda_id,
                    cat.nome AS categoria_nome, cat.codigo AS categoria_codigo,
                    fp.nome AS forma_pagamento_nome,
                    v.numero AS venda_numero,
                    cli.nome AS cliente_nome
               FROM movimentacoes m
               INNER JOIN categorias_dre cat ON cat.id = m.categoria_dre_id
               LEFT JOIN formas_pagamento fp ON fp.id = m.forma_pagamento_id
               LEFT JOIN pdv_vendas v ON v.id = m.pdv_venda_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
              WHERE m.afeta_dre = TRUE
                AND m.categoria_dre_id = :cat
                AND DATE(m.data_movimentacao) BETWEEN :ini AND :fim
              ORDER BY m.data_movimentacao DESC, m.id DESC"
        );
        $stmt->execute([
            ':cat' => $categoriaId,
            ':ini' => $dataInicio,
            ':fim' => $dataFim,
        ]);
        return array_map(static fn ($r) => [
            'id' => (int)$r['id'],
            'data' => (string)$r['data_movimentacao'],
            'descricao' => (string)($r['descricao'] ?? ''),
            'valor' => (float)$r['valor'],
            'categoria' => (string)$r['categoria_nome'],
            'codigo' => $r['categoria_codigo'],
            'forma_pagamento' => $r['forma_pagamento_nome'],
            'venda_numero' => $r['venda_numero'] ? (int)$r['venda_numero'] : null,
            'cliente_nome' => $r['cliente_nome'],
            'tipo_origem' => (string)($r['tipo_origem'] ?? ''),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array{0:array<string>,1:?array<string>}|null  [tiposCategoria, codigosWhitelist|null] */
    private function filtrosDoBloco(string $bloco): ?array
    {
        return match ($bloco) {
            '01_receita_bruta'         => [['Receita'], ['01', '02']],
            '02_deducoes'              => [['Deducao'], null],
            '04_cpv'                   => [['CPV'], null],
            '06_despesas_operacionais' => [['Despesa Operacional'], null],
            '08_resultado_financeiro'  => [['Despesa Financeira', 'Receita'], ['51', '52', '53', '03']],
            '10_tributos'              => [['Tributo'], null],
            default                    => null,
        };
    }

    public function gerarCentralizado(string $dataInicio, string $dataFim): array
    {
        $porTipo = $this->agregarPorTipoECategoria($dataInicio, $dataFim);

        $receitaOp = $this->somarBloco($porTipo, 'Receita', ['Receita Produto', 'Receita Servico']);
        $receitaOutras = $this->somarTipo($porTipo, 'Receita') - $receitaOp;
        $receitaBrutaTotal = $receitaOp;

        $deducoes = $this->somarTipo($porTipo, 'Deducao');
        $cpv = $this->somarTipo($porTipo, 'CPV');
        $despesaOp = $this->somarTipo($porTipo, 'Despesa Operacional');
        $despesaFin = $this->somarTipo($porTipo, 'Despesa Financeira');
        $receitaFin = $this->somarNomeLike($porTipo, 'Receita', 'financeir');
        $tributos = $this->somarTipo($porTipo, 'Tributo');

        $receitaLiquida = $receitaBrutaTotal - $deducoes;
        $lucroBruto = $receitaLiquida - $cpv;
        $resultadoOp = $lucroBruto - $despesaOp;
        $resultadoFin = $receitaFin - $despesaFin;
        $lair = $resultadoOp + $resultadoFin;
        $lucroLiquido = $lair - $tributos;

        return [
            'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim],
            'blocos' => [
                '01_receita_bruta' => [
                    'label' => 'Receita Bruta',
                    'valor' => round($receitaBrutaTotal, 2),
                    'detalhes' => $this->detalhesFiltrados($porTipo, 'Receita', ['Receita Produto', 'Receita Servico']),
                ],
                '02_deducoes' => [
                    'label' => '(-) Deducoes da Receita',
                    'valor' => round($deducoes, 2),
                    'detalhes' => $this->detalhesPorTipo($porTipo, 'Deducao'),
                ],
                '03_receita_liquida' => [
                    'label' => 'Receita Liquida',
                    'valor' => round($receitaLiquida, 2),
                ],
                '04_cpv' => [
                    'label' => '(-) Custos (CPV/CMV)',
                    'valor' => round($cpv, 2),
                    'detalhes' => $this->detalhesPorTipo($porTipo, 'CPV'),
                ],
                '05_lucro_bruto' => [
                    'label' => 'Lucro Bruto',
                    'valor' => round($lucroBruto, 2),
                ],
                '06_despesas_operacionais' => [
                    'label' => '(-) Despesas Operacionais',
                    'valor' => round($despesaOp, 2),
                    'detalhes' => $this->detalhesPorTipo($porTipo, 'Despesa Operacional'),
                ],
                '07_resultado_operacional' => [
                    'label' => 'Resultado Operacional',
                    'valor' => round($resultadoOp, 2),
                ],
                '08_resultado_financeiro' => [
                    'label' => '(+/-) Resultado Financeiro',
                    'valor' => round($resultadoFin, 2),
                    'receita_financeira' => round($receitaFin, 2),
                    'despesa_financeira' => round($despesaFin, 2),
                    'detalhes_despesa' => $this->detalhesPorTipo($porTipo, 'Despesa Financeira'),
                ],
                '09_lair' => [
                    'label' => 'Resultado Antes dos Tributos (LAIR)',
                    'valor' => round($lair, 2),
                ],
                '10_tributos' => [
                    'label' => '(-) IRPJ / CSLL',
                    'valor' => round($tributos, 2),
                    'detalhes' => $this->detalhesPorTipo($porTipo, 'Tributo'),
                ],
                '11_lucro_liquido' => [
                    'label' => 'Lucro Liquido',
                    'valor' => round($lucroLiquido, 2),
                ],
            ],
            'outras_receitas_fora_bruta' => round($receitaOutras, 2),
        ];
    }

    /**
     * @return array<string, array<int, array{categoria_id:int, nome:string, valor:float, quantidade:int}>>
     */
    private function agregarPorTipoECategoria(string $dataInicio, string $dataFim): array
    {
        $sql = "
            SELECT cat.tipo, cat.id AS categoria_id, cat.nome,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(m.id) AS quantidade
              FROM movimentacoes m
             INNER JOIN categorias_dre cat ON cat.id = m.categoria_dre_id
             WHERE m.afeta_dre = TRUE
               AND DATE(m.data_movimentacao) BETWEEN :inicio AND :fim
             GROUP BY cat.tipo, cat.id, cat.nome
             ORDER BY cat.tipo, cat.nome
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':inicio' => $dataInicio, ':fim' => $dataFim]);

        $agg = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tipo = (string)$r['tipo'];
            $agg[$tipo][] = [
                'categoria_id' => (int)$r['categoria_id'],
                'nome' => (string)$r['nome'],
                'valor' => (float)$r['valor'],
                'quantidade' => (int)$r['quantidade'],
            ];
        }
        return $agg;
    }

    private function somarTipo(array $agg, string $tipo): float
    {
        $total = 0.0;
        foreach ($agg[$tipo] ?? [] as $r) {
            $total += (float)$r['valor'];
        }
        return $total;
    }

    /** Soma apenas categorias cujo tipo esteja na whitelist E pertenca ao grupo dado. */
    private function somarBloco(array $agg, string $tipoGrupo, array $tiposCategoria): float
    {
        $mapa = [];
        foreach ($agg[$tipoGrupo] ?? [] as $r) {
            $mapa[$r['categoria_id']] = (float)$r['valor'];
        }

        // Busca categorias que casam pelos tipos desejados
        $sql = "SELECT id FROM categorias_dre WHERE tipo IN (" .
               implode(',', array_fill(0, count($tiposCategoria), '?')) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($tiposCategoria);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        // Heuristica: inclui TODA categoria tipo='Receita' que tenha codigo 01 ou 02
        $sql2 = "SELECT id FROM categorias_dre WHERE codigo IN ('01','02')";
        foreach ($this->pdo->query($sql2)->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
            $ids[] = (int)$id;
        }
        $ids = array_unique($ids);

        $total = 0.0;
        foreach ($mapa as $catId => $valor) {
            if (in_array($catId, $ids, true)) {
                $total += $valor;
            }
        }
        // Fallback: se nao achou nenhuma categoria 01/02, soma todo o tipo Receita
        // mas exclui Receitas Financeiras
        if ($total < 0.0001) {
            foreach ($agg[$tipoGrupo] ?? [] as $r) {
                if (stripos((string)$r['nome'], 'financeir') === false) {
                    $total += (float)$r['valor'];
                }
            }
        }
        return $total;
    }

    private function somarNomeLike(array $agg, string $tipo, string $padrao): float
    {
        $total = 0.0;
        foreach ($agg[$tipo] ?? [] as $r) {
            if (stripos((string)$r['nome'], $padrao) !== false) {
                $total += (float)$r['valor'];
            }
        }
        return $total;
    }

    private function detalhesPorTipo(array $agg, string $tipo): array
    {
        return $agg[$tipo] ?? [];
    }

    private function detalhesFiltrados(array $agg, string $tipoGrupo, array $tiposCategoria): array
    {
        $sql = "SELECT id FROM categorias_dre WHERE codigo IN ('01','02')";
        $idsPermitidos = array_map('intval', $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $detalhes = [];
        foreach ($agg[$tipoGrupo] ?? [] as $r) {
            if (in_array($r['categoria_id'], $idsPermitidos, true)
                || stripos((string)$r['nome'], 'produto') !== false
                || stripos((string)$r['nome'], 'servico') !== false) {
                $detalhes[] = $r;
            }
        }
        return $detalhes;
    }
}
