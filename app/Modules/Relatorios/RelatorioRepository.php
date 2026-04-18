<?php

namespace App\Modules\Relatorios;

use PDO;
use Exception;

/**
 * RelatorioRepository - Acesso a dados para relatórios
 * 
 * Responsabilidades:
 * - Centralizar todas as queries
 * - Evitar N+1 queries (JOINs quando possível)
 * - Padronizar filtros
 * - Cache simples de queries frequentes
 * - Garantir prepared statements
 * 
 * Padrão: Cada método retorna ARRAY DE ASSOCIATIVO
 * 
 * @example
 * $repo = new RelatorioRepository($pdo);
 * $dados = $repo->buscarContasReceber($data_inicio, $data_fim, 'PENDENTE');
 * // Retorna: [
 * //     'registros' => [...],
 * //     'totais' => [...],
 * //     'linhas' => 42
 * // ]
 */
class RelatorioRepository
{
    private PDO $pdo;
    private array $cache = [];
    private bool $usar_cache = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // RELATÓRIOS FINANCEIROS
    // =========================================================================

    /**
     * Buscar contas a receber com filtros
     * 
     * @param string $data_inicio Formato: YYYY-MM-DD
     * @param string $data_fim Formato: YYYY-MM-DD
     * @param string $status PENDENTE|PAGO|VENCIDO|CANCELADO|todos
     * @param int|null $cliente_id Opcional
     * @return array ['registros' => [...], 'totais' => [...], 'linhas' => int]
     */
    public function buscarContasReceber(
        string $data_inicio = '',
        string $data_fim = '',
        string $status = 'todos',
        ?int $cliente_id = null
    ): array {
        $sql = "
            SELECT 
                cr.id,
                cr.descricao,
                cr.valor as valor_original,
                COALESCE(cr.valor_pago, 0) as valor_recebido,
                cr.desconto as desconto_concedido,
                cr.data_vencimento,
                cr.created_at as data_emissao,
                cr.data_pagamento as data_recebimento,
                cr.status,
                cr.observacoes,
                COALESCE(cr.codigo, '') as codigo,
                c.id as cliente_id,
                c.nome as cliente_nome,
                c.email as cliente_email,
                (cr.valor - COALESCE(cr.valor_pago, 0) - COALESCE(cr.desconto, 0)) as valor_aberto,
                CASE 
                    WHEN cr.status = 'PAGO' THEN 'Recebido'
                    WHEN cr.status = 'VENCIDO' AND cr.data_vencimento < CURRENT_DATE THEN 'Vencido'
                    WHEN cr.data_vencimento > CURRENT_DATE THEN 'Em Dia'
                    ELSE 'Vencido'
                END as situacao,
                CURRENT_DATE - cr.data_vencimento as dias_vencimento
            FROM contas_receber cr
            LEFT JOIN clientes c ON cr.cliente_id = c.id
            WHERE 1=1
        ";

        $params = [];

        // Filtro por status
        if ($status !== 'todos') {
            $sql .= " AND cr.status = :status";
            $params[':status'] = $status;
        }

        // Filtro por período de vencimento
        if (!empty($data_inicio) && !empty($data_fim)) {
            $sql .= " AND DATE(cr.data_vencimento) BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $data_inicio;
            $params[':data_fim'] = $data_fim;
        }

        // Filtro por cliente
        if ($cliente_id !== null) {
            $sql .= " AND cr.cliente_id = :cliente_id";
            $params[':cliente_id'] = $cliente_id;
        }

        $sql .= " ORDER BY cr.data_vencimento ASC, cr.status ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular totais
        $totais = [
            'total_titulos' => count($registros),
            'valor_original' => 0,
            'valor_recebido' => 0,
            'desconto_concedido' => 0,
            'valor_aberto' => 0,
            'media_valor' => 0,
            'valor_minimo' => PHP_INT_MAX,
            'valor_maximo' => 0,
        ];

        foreach ($registros as $registro) {
            $totais['valor_original'] += (float)$registro['valor_original'];
            $totais['valor_recebido'] += (float)$registro['valor_recebido'];
            $totais['desconto_concedido'] += (float)$registro['desconto_concedido'];
            $totais['valor_aberto'] += (float)$registro['valor_aberto'];
            $totais['valor_maximo'] = max($totais['valor_maximo'], (float)$registro['valor_original']);
            $totais['valor_minimo'] = min($totais['valor_minimo'], (float)$registro['valor_original']);
        }

        if ($totais['total_titulos'] > 0) {
            $totais['media_valor'] = $totais['valor_original'] / $totais['total_titulos'];
        }

        if ($totais['valor_minimo'] === PHP_INT_MAX) {
            $totais['valor_minimo'] = 0;
        }

        return [
            'registros' => $registros,
            'totais' => $totais,
            'linhas' => count($registros),
        ];
    }

    /**
     * Buscar contas a pagar com filtros
     * 
     * @param string $data_inicio Formato: YYYY-MM-DD
     * @param string $data_fim Formato: YYYY-MM-DD
     * @param string $status PENDENTE|PAGO|VENCIDO|CANCELADO|todos
     * @param int|null $empresa_id Opcional
     * @return array ['registros' => [...], 'totais' => [...], 'linhas' => int]
     */
    public function buscarContasPagar(
        string $data_inicio = '',
        string $data_fim = '',
        string $status = 'todos',
        ?int $empresa_id = null
    ): array {
        $sql = "
            SELECT 
                cp.id,
                cp.descricao,
                cp.valor as valor_original,
                COALESCE(cp.valor_pago, 0) as valor_pago,
                cp.desconto as desconto_obtido,
                cp.data_vencimento,
                cp.data_pagamento,
                cp.created_at as data_emissao,
                cp.status,
                cp.observacoes,
                COALESCE(cp.codigo, '') as codigo,
                e.id as empresa_id,
                e.nome as empresa_nome,
                (cp.valor - COALESCE(cp.valor_pago, 0) - COALESCE(cp.desconto, 0)) as valor_aberto,
                CASE 
                    WHEN cp.status = 'PAGO' THEN 'Pago'
                    WHEN cp.status = 'VENCIDO' AND cp.data_vencimento < CURRENT_DATE THEN 'Vencido'
                    WHEN cp.data_vencimento > CURRENT_DATE THEN 'Em Dia'
                    ELSE 'Vencido'
                END as situacao,
                CURRENT_DATE - cp.data_vencimento as dias_vencimento
            FROM contas_pagar cp
            LEFT JOIN empresas e ON cp.empresa_id = e.id
            WHERE 1=1
        ";

        $params = [];

        if ($status !== 'todos') {
            $sql .= " AND cp.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($data_inicio) && !empty($data_fim)) {
            $sql .= " AND DATE(cp.data_vencimento) BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $data_inicio;
            $params[':data_fim'] = $data_fim;
        }

        if ($empresa_id !== null) {
            $sql .= " AND cp.empresa_id = :empresa_id";
            $params[':empresa_id'] = $empresa_id;
        }

        $sql .= " ORDER BY cp.data_vencimento ASC, cp.status ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular totais
        $totais = [
            'total_titulos' => count($registros),
            'valor_original' => 0,
            'valor_pago' => 0,
            'desconto_obtido' => 0,
            'valor_aberto' => 0,
            'media_valor' => 0,
        ];

        foreach ($registros as $registro) {
            $totais['valor_original'] += (float)$registro['valor_original'];
            $totais['valor_pago'] += (float)$registro['valor_pago'];
            $totais['desconto_obtido'] += (float)$registro['desconto_obtido'];
            $totais['valor_aberto'] += (float)$registro['valor_aberto'];
        }

        if ($totais['total_titulos'] > 0) {
            $totais['media_valor'] = $totais['valor_original'] / $totais['total_titulos'];
        }

        return [
            'registros' => $registros,
            'totais' => $totais,
            'linhas' => count($registros),
        ];
    }

    /**
     * Buscar movimentações financeiras com filtros
     */
    public function buscarMovimentacoes(
        string $data_inicio = '',
        string $data_fim = '',
        string $tipo = 'todos', // Entrada|Saída|todos
        int|null $conta_id = null,
        int|null $usuario_id = null
    ): array {
        $sql = "
            SELECT 
                m.id,
                m.conta_id,
                c.nome as conta_nome,
                m.tipo,
                m.valor,
                m.desconto,
                m.data_movimentacao,
                m.descricao,
                m.categoria_dre_id,
                cd.nome as categoria_nome,
                m.conta_receber_id,
                m.conta_pagar_id,
                m.usuario_id,
                u.name as usuario_nome
            FROM movimentacoes m
            LEFT JOIN contas c ON m.conta_id = c.id
            LEFT JOIN categorias_dre cd ON m.categoria_dre_id = cd.id
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($data_inicio) && !empty($data_fim)) {
            $sql .= " AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $data_inicio;
            $params[':data_fim'] = $data_fim;
        }

        if ($tipo !== 'todos') {
            $sql .= " AND m.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($conta_id !== null) {
            $sql .= " AND m.conta_id = :conta_id";
            $params[':conta_id'] = $conta_id;
        }

        if ($usuario_id !== null) {
            $sql .= " AND m.usuario_id = :usuario_id";
            $params[':usuario_id'] = $usuario_id;
        }

        $sql .= " ORDER BY m.data_movimentacao DESC, m.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totais = [
            'total_linhas' => count($registros),
            'total_entradas' => 0,
            'total_saidas' => 0,
            'saldo' => 0,
        ];

        foreach ($registros as $registro) {
            if ($registro['tipo'] === 'Entrada') {
                $totais['total_entradas'] += (float)$registro['valor'];
            } else {
                $totais['total_saidas'] += (float)$registro['valor'];
            }
        }

        $totais['saldo'] = $totais['total_entradas'] - $totais['total_saidas'];

        return [
            'registros' => $registros,
            'totais' => $totais,
            'linhas' => count($registros),
        ];
    }

    /**
     * Buscar dados para DRE
     */
    public function buscarDadosDre(
        string $data_inicio = '',
        string $data_fim = ''
    ): array {
        // Buscar todas as movimentações no período
        $sql = "
            SELECT 
                m.valor,
                m.tipo,
                cd.tipo as categoria_tipo,
                cd.nome as categoria_nome
            FROM movimentacoes m
            LEFT JOIN categorias_dre cd ON m.categoria_dre_id = cd.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($data_inicio) && !empty($data_fim)) {
            $sql .= " AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $data_inicio;
            $params[':data_fim'] = $data_fim;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return [
            'movimentacoes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    // =========================================================================
    // RELATÓRIOS DE PRODUTOS
    // =========================================================================

    /**
     * Buscar produtos com filtros e resumo
     */
    public function buscarProdutos(
        string $busca = '',
        bool $somente_ativos = true,
        int|null $categoria_id = null
    ): array {
        $sql = "
            SELECT 
                p.id,
                p.codigo,
                p.nome,
                p.descricao,
                p.ativo,
                p.estoque_atual,
                p.estoque_minimo,
                p.preco_custo,
                p.preco_venda,
                (p.estoque_atual * p.preco_custo) as valor_custo_estoque,
                (p.estoque_atual * p.preco_venda) as valor_venda_estoque,
                CASE 
                    WHEN p.estoque_atual <= 0 THEN 'Fora de Estoque'
                    WHEN p.estoque_atual <= p.estoque_minimo THEN 'Estoque Baixo'
                    ELSE 'Em Estoque'
                END as situacao_estoque
            FROM produtos p
            WHERE 1=1
        ";

        $params = [];

        if ($somente_ativos) {
            $sql .= " AND p.ativo = TRUE";
        }

        if (!empty($busca)) {
            $sql .= " AND (p.nome ILIKE :busca OR p.codigo ILIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($categoria_id !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoria_id;
        }

        $sql .= " ORDER BY p.nome ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totais = [
            'total_produtos' => count($registros),
            'estoque_total' => 0,
            'valor_custo_total' => 0,
            'valor_venda_total' => 0,
            'produtos_ativos' => 0,
            'produtos_inativos' => 0,
            'estoque_baixo' => 0,
        ];

        foreach ($registros as $produto) {
            $totais['estoque_total'] += $produto['estoque_atual'];
            $totais['valor_custo_total'] += $produto['valor_custo_estoque'];
            $totais['valor_venda_total'] += $produto['valor_venda_estoque'];

            if ($produto['ativo']) {
                $totais['produtos_ativos']++;
            } else {
                $totais['produtos_inativos']++;
            }

            if ($produto['estoque_atual'] <= $produto['estoque_minimo']) {
                $totais['estoque_baixo']++;
            }
        }

        return [
            'registros' => $registros,
            'totais' => $totais,
            'linhas' => count($registros),
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Validar e normalizar datas
     */
    public static function validarDataIntervalo(string &$data_inicio, string &$data_fim): bool
    {
        try {
            $dt_inicio = new \DateTime($data_inicio);
            $dt_fim = new \DateTime($data_fim);

            if ($dt_inicio > $dt_fim) {
                [$data_inicio, $data_fim] = [$data_fim, $data_inicio];
            }

            $data_inicio = $dt_inicio->format('Y-m-d');
            $data_fim = $dt_fim->format('Y-m-d');

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Contar registros de uma tabela com filtros simples
     */
    public function contar(string $tabela, array $filtros = []): int
    {
        $sql = "SELECT COUNT(*) FROM $tabela WHERE 1=1";
        $params = [];

        foreach ($filtros as $coluna => $valor) {
            $sql .= " AND $coluna = :$coluna";
            $params[":$coluna"] = $valor;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}
