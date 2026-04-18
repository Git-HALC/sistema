<?php

namespace App\Modules\Financeiro;

use PDO;

class DreRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Gera relatório DRE usando APENAS a tabela movimentacoes como fonte de verdade.
     * 
     * Esta é a nova arquitetura centralizada onde TODAS as operações financeiras
     * são registradas através de movimentacoes.
     * 
     * Fluxo:
     * - ContaPagarService.baixar() → FinanceiroService.registrarMovimentacaoFinanceira()
     * - ContaReceberService.receber() → FinanceiroService.registrarMovimentacaoFinanceira()
     * - DreRepository.gerarCentralizado() ← lê APENAS de movimentacoes
     * 
     * @param string $dataInicio Data inicial (YYYY-MM-DD)
     * @param string $dataFim Data final (YYYY-MM-DD)
     * @return array Relatório DRE estruturado
     */
    public function gerarCentralizado(string $dataInicio, string $dataFim): array
    {
        $dre = [
            'periodo'    => ['inicio' => $dataInicio, 'fim' => $dataFim],
            'receita_bruta' => ['total' => 0, 'detalhes' => []],
            'deducoes' => [
                'impostos'    => 0,
                'devolucoes'  => 0,
                'abatimentos' => 0,
                'total'       => 0,
                'detalhes'    => []
            ],
            'receita_liquida'      => 0,
            'cpv_cmv'              => ['total' => 0, 'detalhes' => []],
            'lucro_bruto'          => 0,
            'despesas_operacionais' => [
                'vendas'          => 0,
                'administrativas' => 0,
                'financeiras'     => 0,
                'total'           => 0,
                'detalhes'        => []
            ],
            'lair' => 0,
            'tributos_sobre_lucro' => [
                'irpj'     => 0,
                'csll'     => 0,
                'total'    => 0,
                'detalhes' => []
            ],
            'lucro_liquido' => 0
        ];

        $params = [':data_inicio' => $dataInicio, ':data_fim' => $dataFim];

        // =====================================================================
        // 1. RECEITA BRUTA
        // Origem: Movimentações ENTRADA com categoria Receita
        // Inclui: RECEBIMENTO e MANUAL de clientes
        // =====================================================================
        
        $sqlReceita = "
            SELECT cat.id, cat.nome,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(DISTINCT m.id) AS quantidade
            FROM categorias_dre cat
            LEFT JOIN movimentacoes m ON (
                m.categoria_dre_id = cat.id
                AND m.tipo = 'Entrada'
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
            )
            WHERE cat.tipo = 'Receita' AND cat.ativo = TRUE
            GROUP BY cat.id, cat.nome
            ORDER BY cat.ordem, cat.nome
        ";

        $stmt = $this->pdo->prepare($sqlReceita);
        $stmt->execute($params);

        $receitasAgregadas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (float)$row['valor'];
            $catId = $row['id'];
            
            if (!isset($receitasAgregadas[$catId])) {
                $receitasAgregadas[$catId] = [
                    'nome' => $row['nome'],
                    'valor' => 0,
                    'quantidade' => 0
                ];
            }
            
            $receitasAgregadas[$catId]['valor'] += $total;
            $receitasAgregadas[$catId]['quantidade'] += (int)$row['quantidade'];
        }

        // Consolidar receitas
        foreach ($receitasAgregadas as $catId => $dados) {
            $total = $dados['valor'];
            $dre['receita_bruta']['total'] += $total;
            $dre['receita_bruta']['detalhes'][] = [
                'categoria' => $dados['nome'],
                'valor' => $total,
                'quantidade' => $dados['quantidade']
            ];
        }

        // =====================================================================
        // 2. DEDUÇÕES
        // Origem: Movimentações de qualquer tipo com categoria Dedução
        // Inclui: Descontos, Abatimentos, Impostos
        // =====================================================================

        $sqlDeducoes = "
            SELECT cat.id, cat.nome,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(DISTINCT m.id) AS quantidade
            FROM categorias_dre cat
            LEFT JOIN movimentacoes m ON (
                m.categoria_dre_id = cat.id
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
            )
            WHERE cat.tipo = 'Dedução' AND cat.ativo = TRUE
            GROUP BY cat.id, cat.nome
            ORDER BY cat.ordem, cat.nome
        ";

        $stmt = $this->pdo->prepare($sqlDeducoes);
        $stmt->execute($params);

        $deducoesAgregadas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (float)$row['valor'];
            $catId = $row['id'];

            if (!isset($deducoesAgregadas[$catId])) {
                $deducoesAgregadas[$catId] = [
                    'nome' => $row['nome'],
                    'valor' => 0,
                    'quantidade' => 0
                ];
            }

            $deducoesAgregadas[$catId]['valor'] += $total;
            $deducoesAgregadas[$catId]['quantidade'] += (int)$row['quantidade'];
        }

        // Consolidar deduções
        foreach ($deducoesAgregadas as $catId => $dados) {
            $total = $dados['valor'];
            $nome = strtolower($dados['nome']);

            $dre['deducoes']['total'] += $total;

            if (strpos($nome, 'icms') !== false || strpos($nome, 'ipi') !== false ||
                strpos($nome, 'pis') !== false  || strpos($nome, 'cofins') !== false ||
                strpos($nome, 'iss') !== false) {
                $dre['deducoes']['impostos'] += $total;
            } elseif (strpos($nome, 'devolucao') !== false) {
                $dre['deducoes']['devolucoes'] += $total;
            } else {
                $dre['deducoes']['abatimentos'] += $total;
            }

            $dre['deducoes']['detalhes'][] = [
                'categoria' => $dados['nome'],
                'valor' => $total,
                'quantidade' => $dados['quantidade']
            ];
        }

        // 3. RECEITA LÍQUIDA
        $dre['receita_liquida'] = $dre['receita_bruta']['total'] - $dre['deducoes']['total'];

        // =====================================================================
        // 4. CPV/CMV
        // Origem: Movimentações SAÍDA com categoria CPV
        // =====================================================================

        $sqlCPV = "
            SELECT cat.id, cat.nome,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(DISTINCT m.id) AS quantidade
            FROM categorias_dre cat
            LEFT JOIN movimentacoes m ON (
                m.categoria_dre_id = cat.id
                AND m.tipo = 'Saída'
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
            )
            WHERE cat.tipo = 'CPV' AND cat.ativo = TRUE
            GROUP BY cat.id, cat.nome
            ORDER BY cat.ordem, cat.nome
        ";

        $stmt = $this->pdo->prepare($sqlCPV);
        $stmt->execute($params);

        $cpvAgregados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (float)$row['valor'];
            $catId = $row['id'];
            
            if (!isset($cpvAgregados[$catId])) {
                $cpvAgregados[$catId] = [
                    'nome' => $row['nome'],
                    'valor' => 0,
                    'quantidade' => 0
                ];
            }
            
            $cpvAgregados[$catId]['valor'] += $total;
            $cpvAgregados[$catId]['quantidade'] += (int)$row['quantidade'];
        }

        // Consolidar CPV
        foreach ($cpvAgregados as $catId => $dados) {
            $total = $dados['valor'];
            $dre['cpv_cmv']['total'] += $total;
            $dre['cpv_cmv']['detalhes'][] = [
                'categoria' => $dados['nome'],
                'valor' => $total,
                'quantidade' => $dados['quantidade']
            ];
        }

        // 5. LUCRO BRUTO
        $dre['lucro_bruto'] = $dre['receita_liquida'] - $dre['cpv_cmv']['total'];

        // =====================================================================
        // 6. DESPESAS OPERACIONAIS
        // Origem: Movimentações SAÍDA com categoria Despesa Operacional ou Despesa Financeira
        // =====================================================================

        $sqlDespesas = "
            SELECT cat.id, cat.nome, cat.tipo,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(DISTINCT m.id) AS quantidade
            FROM categorias_dre cat
            LEFT JOIN movimentacoes m ON (
                m.categoria_dre_id = cat.id
                AND m.tipo = 'Saída'
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
            )
            WHERE cat.tipo IN ('Despesa Operacional', 'Despesa Financeira') AND cat.ativo = TRUE
            GROUP BY cat.id, cat.nome, cat.tipo
            ORDER BY cat.ordem, cat.nome
        ";

        $stmt = $this->pdo->prepare($sqlDespesas);
        $stmt->execute($params);

        $despesasAgregadas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (float)$row['valor'];
            $catId = $row['id'];
            
            if (!isset($despesasAgregadas[$catId])) {
                $despesasAgregadas[$catId] = [
                    'nome' => $row['nome'],
                    'tipo' => $row['tipo'],
                    'valor' => 0,
                    'quantidade' => 0
                ];
            }
            
            $despesasAgregadas[$catId]['valor'] += $total;
            $despesasAgregadas[$catId]['quantidade'] += (int)$row['quantidade'];
        }

        // Consolidar despesas operacionais
        foreach ($despesasAgregadas as $catId => $dados) {
            $total = $dados['valor'];
            $nome = strtolower($dados['nome']);
            $tipo = strtolower($dados['tipo']);

            $dre['despesas_operacionais']['total'] += $total;

            if ($tipo === 'despesa financeira' || strpos($nome, 'juro') !== false ||
                strpos($nome, 'banco') !== false || strpos($nome, 'taxa') !== false) {
                $dre['despesas_operacionais']['financeiras'] += $total;
            } elseif (strpos($nome, 'vendedor') !== false || strpos($nome, 'comissao') !== false ||
                      strpos($nome, 'marketing') !== false || strpos($nome, 'propaganda') !== false) {
                $dre['despesas_operacionais']['vendas'] += $total;
            } else {
                $dre['despesas_operacionais']['administrativas'] += $total;
            }

            $dre['despesas_operacionais']['detalhes'][] = [
                'categoria' => $dados['nome'],
                'valor' => $total,
                'quantidade' => $dados['quantidade'],
                'tipo' => $dados['tipo']
            ];
        }

        // 7. LAIR
        $dre['lair'] = $dre['lucro_bruto'] - $dre['despesas_operacionais']['total'];

        // =====================================================================
        // 8. TRIBUTOS SOBRE O LUCRO
        // Origem: Movimentações de qualquer tipo com categoria Tributo
        // =====================================================================

        $sqlTributos = "
            SELECT cat.id, cat.nome,
                   COALESCE(SUM(m.valor), 0) AS valor,
                   COUNT(DISTINCT m.id) AS quantidade
            FROM categorias_dre cat
            LEFT JOIN movimentacoes m ON (
                m.categoria_dre_id = cat.id
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
            )
            WHERE cat.tipo = 'Tributo' AND cat.ativo = TRUE
            GROUP BY cat.id, cat.nome
            ORDER BY cat.ordem, cat.nome
        ";

        $stmt = $this->pdo->prepare($sqlTributos);
        $stmt->execute($params);

        $tributosAgregados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = (float)$row['valor'];
            $catId = $row['id'];
            
            if (!isset($tributosAgregados[$catId])) {
                $tributosAgregados[$catId] = [
                    'nome' => $row['nome'],
                    'valor' => 0,
                    'quantidade' => 0
                ];
            }
            
            $tributosAgregados[$catId]['valor'] += $total;
            $tributosAgregados[$catId]['quantidade'] += (int)$row['quantidade'];
        }

        // Consolidar tributos
        foreach ($tributosAgregados as $catId => $dados) {
            $total = $dados['valor'];
            $nome = strtolower($dados['nome']);

            $dre['tributos_sobre_lucro']['total'] += $total;

            if (strpos($nome, 'irpj') !== false || strpos($nome, 'imposto de renda') !== false) {
                $dre['tributos_sobre_lucro']['irpj'] += $total;
            } elseif (strpos($nome, 'csll') !== false || strpos($nome, 'contribuição social') !== false) {
                $dre['tributos_sobre_lucro']['csll'] += $total;
            }

            $dre['tributos_sobre_lucro']['detalhes'][] = [
                'categoria' => $dados['nome'],
                'valor' => $total,
                'quantidade' => $dados['quantidade']
            ];
        }

        // Estimativa de tributos se não houver pagamentos registrados (Lucro Presumido)
        if ($dre['tributos_sobre_lucro']['total'] == 0 && $dre['lair'] > 0) {
            $dre['tributos_sobre_lucro']['irpj']  = $dre['lair'] * 0.15;
            $dre['tributos_sobre_lucro']['csll']  = $dre['lair'] * 0.09;
            $dre['tributos_sobre_lucro']['total'] =
                $dre['tributos_sobre_lucro']['irpj'] + $dre['tributos_sobre_lucro']['csll'];
        }

        // 9. LUCRO LÍQUIDO
        $dre['lucro_liquido'] = $dre['lair'] - $dre['tributos_sobre_lucro']['total'];

        return $dre;
    }

    /**
     * Método legado - mantido para compatibilidade retroativa.
     * DEPRECADO: use gerarCentralizado() em vez disso.
     */
    public function gerar(string $dataInicio, string $dataFim): array
    {
        return $this->gerarCentralizado($dataInicio, $dataFim);
    }
}
