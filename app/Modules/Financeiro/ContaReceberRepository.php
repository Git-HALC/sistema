<?php

namespace App\Modules\Financeiro;

use PDO;
use RuntimeException;

class ContaReceberRepository
{
    private const TABLE     = 'contas_receber';
    private const TABLE_MOV = 'movimentacoes';

    public function __construct(
        private readonly PDO $pdo,
        private ?FinanceiroService $financeiroService = null
    ) {}
    
    /**
     * Define o servi?o de movimenta??es financeiras (inje??o opcional).
     */
    public function setFinanceiroService(FinanceiroService $service): void
    {
        $this->financeiroService = $service;
    }

    // =========================================================================
    // Reads
    // =========================================================================

    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        [$where, $params] = $this->buildWhere($filtros);
        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = "
            SELECT cr.*,
                   cli.nome  AS cliente_nome,
                   cat.nome  AS categoria_dre_nome,
                   fp.nome   AS forma_pagamento_nome,
                   fp_receb.nome AS forma_pagamento_recebimento_nome
            FROM " . self::TABLE . " cr
            LEFT JOIN clientes       cli ON cr.cliente_id      = cli.id
            LEFT JOIN categorias_dre cat ON cr.categoria_dre_id = cat.id
            LEFT JOIN formas_pagamento fp ON cr.forma_pagamento_id = fp.id
            LEFT JOIN LATERAL (
                SELECT m.forma_pagamento_id
                FROM " . self::TABLE_MOV . " m
                WHERE m.conta_receber_id = cr.id
                  AND m.tipo_origem = 'RECEBIMENTO'
                  AND m.forma_pagamento_id IS NOT NULL
                ORDER BY m.id DESC
                LIMIT 1
            ) ult_mov ON TRUE
            LEFT JOIN formas_pagamento fp_receb ON ult_mov.forma_pagamento_id = fp_receb.id
            {$where}
            ORDER BY cr.data_vencimento DESC, cr.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = $this->contar($where, $params);

        return [
            'dados'            => $dados,
            'total'            => $total,
            'pagina'           => $pagina,
            'itens_por_pagina' => $porPagina,
            'total_paginas'    => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    public function buscarVencidos(array $filtros, int $pagina, int $porPagina): array
    {
        [$where, $params] = $this->buildWhere($filtros);

        $vencClause = empty($where)
            ? 'WHERE cr.status = :status_vencido AND cr.data_vencimento < CURRENT_DATE'
            : $where . ' AND cr.status = :status_vencido AND cr.data_vencimento < CURRENT_DATE';

        $params[':status_vencido'] = 'VENCIDO';
        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = "
            SELECT cr.*,
                   cli.nome AS cliente_nome,
                   cat.nome AS categoria_dre_nome,
                   fp.nome  AS forma_pagamento_nome,
                   fp_receb.nome AS forma_pagamento_recebimento_nome,
                   (CURRENT_DATE - cr.data_vencimento) AS dias_atraso,
                   CASE
                       WHEN (CURRENT_DATE - cr.data_vencimento) <= 30 THEN 'Leve'
                       WHEN (CURRENT_DATE - cr.data_vencimento) <= 60 THEN 'Moderado'
                       ELSE 'Grave'
                   END AS nivel_atraso
            FROM " . self::TABLE . " cr
            LEFT JOIN clientes       cli ON cr.cliente_id      = cli.id
            LEFT JOIN categorias_dre cat ON cr.categoria_dre_id = cat.id
            LEFT JOIN formas_pagamento fp ON cr.forma_pagamento_id = fp.id
            LEFT JOIN LATERAL (
                SELECT m.forma_pagamento_id
                FROM " . self::TABLE_MOV . " m
                WHERE m.conta_receber_id = cr.id
                  AND m.tipo_origem = 'RECEBIMENTO'
                  AND m.forma_pagamento_id IS NOT NULL
                ORDER BY m.id DESC
                LIMIT 1
            ) ult_mov ON TRUE
            LEFT JOIN formas_pagamento fp_receb ON ult_mov.forma_pagamento_id = fp_receb.id
            {$vencClause}
            ORDER BY cr.data_vencimento ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*) FROM " . self::TABLE . " cr
                     LEFT JOIN clientes cli ON cr.cliente_id = cli.id
                     {$vencClause}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        return [
            'dados'            => $dados,
            'total'            => $total,
            'pagina'           => $pagina,
            'itens_por_pagina' => $porPagina,
            'total_paginas'    => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    // Writes
    // =========================================================================

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (cliente_id, forma_pagamento_id, valor, data_vencimento, data_pagamento, valor_pago,
                 descricao, status, categoria_dre_id, observacoes, orcamento_id, pedido_id, origem, created_at, updated_at)
            VALUES
                (:cliente_id, :forma_pagamento_id, :valor, :data_vencimento, :data_pagamento, :valor_pago,
                 :descricao, :status, :categoria_dre_id, :observacoes, :orcamento_id, :pedido_id, :origem, NOW(), NOW())
        ");

        $stmt->execute([
            ':cliente_id'       => $dados['cliente_id']       ?? null,
            ':forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            ':valor'            => $dados['valor']            ?? 0,
            ':data_vencimento'  => $dados['data_vencimento'],
            ':data_pagamento'   => $dados['data_pagamento']   ?? null,
            ':valor_pago'       => $dados['valor_pago']       ?? 0,
            ':descricao'        => $dados['descricao']         ?? null,
            ':status'           => $dados['status']            ?? 'PENDENTE',
            ':categoria_dre_id' => $dados['categoria_dre_id']  ?? null,
            ':observacoes'      => $dados['observacoes']       ?? null,
            ':orcamento_id'     => $dados['orcamento_id']      ?? null,
            ':pedido_id'        => !empty($dados['pedido_id']) ? (string)$dados['pedido_id'] : null,
            ':origem'           => $dados['origem']            ?? 'MANUAL',
        ]);

        return (int) $this->pdo->lastInsertId('contas_receber_id_seq');
    }

    public function atualizar(int $id, array $dados): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a receber n?o encontrada.');
        }
        if ($conta['status'] === 'PAGO') {
            throw new RuntimeException('N?o ? poss?vel editar uma conta j? paga.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . " SET
                cliente_id       = :cliente_id,
                forma_pagamento_id = :forma_pagamento_id,
                valor            = :valor,
                data_vencimento  = :data_vencimento,
                descricao        = :descricao,
                categoria_dre_id = :categoria_dre_id,
                observacoes      = :observacoes,
                status           = :status,
                updated_at       = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id'               => $id,
            ':cliente_id'       => $dados['cliente_id']       ?? $conta['cliente_id'],
            ':forma_pagamento_id' => $dados['forma_pagamento_id'] ?? $conta['forma_pagamento_id'],
            ':valor'            => $dados['valor']             ?? $conta['valor'],
            ':data_vencimento'  => $dados['data_vencimento']  ?? $conta['data_vencimento'],
            ':descricao'        => $dados['descricao']         ?? $conta['descricao'],
            ':categoria_dre_id' => $dados['categoria_dre_id']  ?? $conta['categoria_dre_id'],
            ':observacoes'      => $dados['observacoes']       ?? $conta['observacoes'],
            ':status'           => $dados['status']            ?? $conta['status'],
        ]);
    }

    /**
     * Registra recebimento da conta.
     * 
     * REGRA CORRIGIDA:
     * - valor_recebido = valor_informado - desconto
     * - Se valor_recebido < valor_conta ? RECEBIMENTO PARCIAL
     * - Se valor_recebido >= valor_conta ? PAGO
     * 
     * @param int $id ID da conta
     * @param array $dados Com: valor_pago, desconto_recebimento, data_recebimento, conta_id, categoria_dre_id
     * @return bool true se sucesso
     * @throws RuntimeException
     */
    public function baixar(int $id, array $dados): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a receber nao encontrada.');
        }
        if (in_array($conta['status'], ['PAGO', 'CANCELADO'], true)) {
            throw new RuntimeException('Esta conta ja foi paga ou cancelada.');
        }

        $formaPagamentoId = (int)($dados['forma_pagamento_id'] ?? 0);
        if ($formaPagamentoId <= 0) {
            throw new RuntimeException('Forma de pagamento invalida para recebimento.');
        }

        $formaFinanceira = new FormaPagamentoFinanceiroService(
            $this->pdo,
            new FormaPagamentoRepository($this->pdo),
            $this->financeiroService ?? new FinanceiroService($this->pdo)
        );
        $forma = $formaFinanceira->buscarFormaDetalhada($formaPagamentoId);
        $formaFinanceira->exigirConfiguracaoParaRecebimento($forma);

        $valorRecebidoEfetivo = (float)($dados['valor_pago'] ?? $dados['valor_recebido'] ?? 0);
        $desconto = (float)($dados['desconto_recebimento'] ?? 0);
        $valorRecebido = $valorRecebidoEfetivo + $desconto;

        if ($valorRecebido < 0) {
            throw new RuntimeException('Valor recebido nao pode ser negativo.');
        }

        $valorPagoAnterior = (float)($conta['valor_pago'] ?? 0);
        $descontoAnterior = (float)($conta['desconto'] ?? 0);
        $valorPagoAcumulado = $valorPagoAnterior + $valorRecebidoEfetivo;
        $descontoAcumulado = $descontoAnterior + $desconto;
        $totalAcumulado = $valorPagoAcumulado + $descontoAcumulado;
        $valorTotal = (float)$conta['valor'];

        if ($desconto > 0 && $totalAcumulado < $valorTotal) {
            throw new RuntimeException('Desconto so e permitido quando o total acumulado quita completamente a conta.');
        }

        $status = $totalAcumulado >= $valorTotal ? 'PAGO' : 'PENDENTE';
        $dataPagamento = $dados['data_pagamento'] ?? $dados['data_recebimento'] ?? date('Y-m-d');
        $categoriaId = !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null;
        $tipoForma = strtoupper((string)($forma['tipo'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            if (in_array($tipoForma, [FormaPagamento::TIPO_CARTAO_CREDITO, FormaPagamento::TIPO_CARTAO_DEBITO], true)) {
                $resumoTaxa = $formaFinanceira->calcularResumoTaxa($valorRecebidoEfetivo, $forma);
                if ($resumoTaxa['valor_liquido'] <= 0) {
                    throw new RuntimeException('A taxa configurada gera valor liquido invalido para a adquirente.');
                }

                $prazoDias = (int)($forma['prazo_dias'] ?? 0);
                $dataVencimento = date('Y-m-d', strtotime($dataPagamento . ' +' . $prazoDias . ' days'));
                $observacoes = 'Gerado automaticamente a partir do recebimento da conta #' . $id . '.';
                if ($resumoTaxa['valor_taxa'] > 0) {
                    $observacoes .= ' Taxa aplicada: ' . number_format((float)$resumoTaxa['taxa_percentual'], 2, '.', '') . '%';
                    $observacoes .= ' (R$ ' . number_format((float)$resumoTaxa['valor_taxa'], 2, '.', '') . ').';
                }

                $formaFinanceira->criarRecebivelAdquirente([
                    'cliente_id' => (int)$forma['adquirente_id'],
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $resumoTaxa['valor_liquido'],
                    'data_vencimento' => $dataVencimento,
                    'descricao' => 'Recebimento via adquirente da conta #' . $id,
                    'categoria_dre_id' => $categoriaId,
                    'observacoes' => $observacoes,
                    'origem' => 'ADQUIRENTE',
                ]);
            } else {
                $contaId = (int)($forma['conta_id'] ?? 0);
                if ($contaId <= 0) {
                    throw new RuntimeException('A forma de pagamento selecionada nao possui banco configurado.');
                }

                $formaFinanceira->registrarMovimentacaoImediata([
                    'conta_id' => $contaId,
                    'valor' => $valorRecebidoEfetivo,
                    'descricao' => 'Recebimento conta a receber #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                    'categoria_dre_id' => $categoriaId,
                    'forma_pagamento_id' => $formaPagamentoId,
                    'conta_receber_id' => $id,
                    'data' => $dataPagamento,
                    'origem' => FinanceiroService::ORIGEM_RECEBIMENTO,
                ]);

                if ($desconto > 0) {
                    $categoriaDescontos = $this->getCategoriDescontosReceber();
                    if ($categoriaDescontos) {
                        $formaFinanceira->registrarMovimentacaoImediata([
                            'conta_id' => $contaId,
                            'valor' => $desconto,
                            'descricao' => 'Desconto concedido no recebimento #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                            'categoria_dre_id' => $categoriaDescontos,
                            'forma_pagamento_id' => $formaPagamentoId,
                            'conta_receber_id' => $id,
                            'data' => $dataPagamento,
                            'origem' => FinanceiroService::ORIGEM_RECEBIMENTO,
                        ]);

                        $stmtDesc = $this->pdo->prepare("
                            UPDATE " . self::TABLE_MOV . "
                            SET tipo = 'Sa?da', afeta_saldo = FALSE
                            WHERE conta_receber_id = :conta_receber_id
                              AND forma_pagamento_id = :forma_pagamento_id
                              AND descricao = :descricao
                            ORDER BY id DESC
                            LIMIT 1
                        ");
                        $stmtDesc->execute([
                            ':conta_receber_id' => $id,
                            ':forma_pagamento_id' => $formaPagamentoId,
                            ':descricao' => 'Desconto concedido no recebimento #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                        ]);
                    }
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    valor_pago = COALESCE(valor_pago, 0) + :valor_recebido_efetivo,
                    desconto = COALESCE(desconto, 0) + :desconto,
                    status = :status,
                    data_pagamento = :data_pagamento,
                    categoria_dre_id = COALESCE(:categoria_dre_id, categoria_dre_id),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':valor_recebido_efetivo' => $valorRecebidoEfetivo,
                ':desconto' => $desconto,
                ':status' => $status,
                ':data_pagamento' => $dataPagamento,
                ':categoria_dre_id' => $categoriaId,
                ':id' => $id,
            ]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Erro ao receber conta: ' . $e->getMessage());
        }
    }

    /**
     * Estorna um recebimento total ou parcial.
     * Se valor n?o informado ou maior/igual ao total, estorna tudo.
     * Caso contr?rio, estorna apenas o valor especificado da ?ltima movimenta??o.
     */
    public function estornar(int $id, ?float $valorEstorno = null, ?int $usuarioId = null): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a receber n?o encontrada.');
        }
        
        $totalPago = (float)($conta['valor_pago'] ?? 0);
        $totalDesconto = (float)($conta['desconto'] ?? 0);
        $totalRecebido = $totalPago + $totalDesconto;
        
        if ($totalRecebido <= 0) {
            throw new RuntimeException('N?o h? valores para estornar.');
        }

        // Se n?o informou valor ou informou valor >= total, estorna tudo
        $estornoTotal = !$valorEstorno || $valorEstorno >= $totalRecebido;
        
        if (!$estornoTotal && $valorEstorno <= 0) {
            throw new RuntimeException('Valor de estorno inv?lido.');
        }

        $contasAdquirente = $this->buscarContasAdquirenteVinculadas($id);
        $this->validarEstornoContasAdquirente($contasAdquirente, $estornoTotal);

        $this->pdo->beginTransaction();
        try {
            if ($estornoTotal) {
                $this->excluirContasAdquirenteVinculadas($contasAdquirente);

                // Estorno total: deleta todas as movimenta??es e zera tudo
                $stmtMov = $this->pdo->prepare('DELETE FROM ' . self::TABLE_MOV . ' WHERE conta_receber_id = :id');
                $stmtMov->execute([':id' => $id]);

                $stmt = $this->pdo->prepare("
                    UPDATE " . self::TABLE . " SET
                        valor_pago     = 0,
                        desconto       = 0,
                        status         = 'PENDENTE',
                        data_pagamento = NULL,
                        updated_at     = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $id]);
            } else {
                // Estorno parcial: busca ?ltima movimenta??o de entrada
                $stmtUltima = $this->pdo->prepare("
                    SELECT id, valor, desconto
                    FROM " . self::TABLE_MOV . "
                    WHERE conta_receber_id = :id AND tipo = 'Entrada'
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ");
                $stmtUltima->execute([':id' => $id]);
                $ultimaMov = $stmtUltima->fetch(\PDO::FETCH_ASSOC);
                
                if (!$ultimaMov) {
                    throw new RuntimeException('Nenhuma movimenta??o encontrada para estornar.');
                }
                
                $valorMovimentacao = (float)$ultimaMov['valor'];
                $descontoMovimentacao = (float)$ultimaMov['desconto'];
                $totalMovimentacao = $valorMovimentacao + $descontoMovimentacao;
                
                if ($valorEstorno > $totalMovimentacao) {
                    throw new RuntimeException('Valor de estorno n?o pode ser maior que o valor da ?ltima movimenta??o (R$ ' . number_format($totalMovimentacao, 2, ',', '.') . ').');
                }
                
                // Calcula propor??o para desconto
                $proporcaoEstorno = $totalMovimentacao > 0 ? ($valorEstorno / $totalMovimentacao) : 0;
                $valorPagoEstorno = $valorMovimentacao * $proporcaoEstorno;
                $descontoEstorno = $descontoMovimentacao * $proporcaoEstorno;
                
                // Se o estorno ? igual ao total da movimenta??o, deleta
                if (abs($valorEstorno - $totalMovimentacao) < 0.01) {
                    $stmtDel = $this->pdo->prepare('DELETE FROM ' . self::TABLE_MOV . ' WHERE id = :id');
                    $stmtDel->execute([':id' => $ultimaMov['id']]);
                } else {
                    // Sen?o, reduz os valores da movimenta??o
                    $stmtUpd = $this->pdo->prepare("
                        UPDATE " . self::TABLE_MOV . "
                        SET valor = valor - :valor_estorno,
                            desconto = desconto - :desconto_estorno
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':valor_estorno' => $valorPagoEstorno,
                        ':desconto_estorno' => $descontoEstorno,
                        ':id' => $ultimaMov['id']
                    ]);
                }
                
                // Atualiza a conta
                $novoValorPago = $totalPago - $valorPagoEstorno;
                $novoDesconto = $totalDesconto - $descontoEstorno;
                $valorConta = (float)$conta['valor'];
                $novoStatus = ($novoValorPago + $novoDesconto) >= $valorConta ? 'PAGO' : 'PENDENTE';
                
                $stmt = $this->pdo->prepare("
                    UPDATE " . self::TABLE . " SET
                        valor_pago = :valor_pago,
                        desconto = :desconto,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':valor_pago' => $novoValorPago,
                    ':desconto' => $novoDesconto,
                    ':status' => $novoStatus,
                    ':id' => $id
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Erro ao estornar conta: ' . $e->getMessage());
        }
    }

    public function excluir(int $id): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a receber n?o encontrada.');
        }

        // Bloquear exclus?o se a conta ainda est? PAGA (com movimenta??es vinculadas)
        if ($conta['status'] === 'PAGO') {
            throw new RuntimeException(
                'N?o ? poss?vel excluir uma conta paga com movimenta??es vinculadas. ' .
                'Estorne o recebimento primeiro para remover as movimenta??es e ent?o poder? excluir a conta.'
            );
        }

        if (!empty($conta['pedido_id']) || strtoupper((string)($conta['origem'] ?? '')) === 'PEDIDO') {
            throw new RuntimeException(
                'Esta conta a receber foi gerada pelo faturamento de um pedido e n?o pode ser exclu?da manualmente. ' .
                'Estorne o faturamento no m?dulo de Pedidos para remover esta conta.'
            );
        }

        if (!empty($conta['servico_id']) || strtoupper((string)($conta['origem'] ?? '')) === 'SERVICO') {
            throw new RuntimeException(
                'Esta conta a receber foi gerada pelo faturamento de um servi?o e n?o pode ser exclu?da manualmente. ' .
                'Estorne o faturamento no m?dulo de Servi?os para remover esta conta.'
            );
        }

        if (strtoupper((string)($conta['origem'] ?? '')) === 'ADQUIRENTE') {
            throw new RuntimeException(
                'Esta conta a receber foi gerada automaticamente como repasse da adquirente do cartao e nao pode ser excluida manualmente. ' .
                'Estorne o recebimento da conta original para remover este vinculo com consistencia.'
            );
        }

        if (!empty($conta['orcamento_id']) || (($conta['origem'] ?? null) === 'ORCAMENTO')) {
            $orcamentoRef = null;

            if (!empty($conta['orcamento_id'])) {
                $stmt = $this->pdo->prepare('SELECT numero FROM orcamentos WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int)$conta['orcamento_id']]);
                $orcamentoRef = $stmt->fetchColumn() ?: ('#' . (int)$conta['orcamento_id']);
            }

            throw new RuntimeException(
                'Esta conta a receber est? vinculada ao or?amento ' . ($orcamentoRef ?? 'de origem autom?tica') .
                '. Fa?a o estorno pelo m?dulo de Or?amentos para manter a consist?ncia.'
            );
        }

        $this->verificarNfeVinculada($id);

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildWhere(array $filtros): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filtros['status']) && $filtros['status'] !== 'Vencido') {
            $conditions[] = 'cr.status = :status';
            $params[':status'] = strtoupper((string)$filtros['status']);
        }
        if (!empty($filtros['cliente_id'])) {
            $conditions[] = 'cr.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int) $filtros['cliente_id'];
        }
        if (!empty($filtros['data_inicio'])) {
            $conditions[] = 'cr.data_vencimento >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $conditions[] = 'cr.data_vencimento <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        if (!empty($filtros['busca'])) {
            $conditions[] = '(cli.nome ILIKE :busca OR cr.descricao ILIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buscarContasAdquirenteVinculadas(int $contaOriginalId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM " . self::TABLE . "
            WHERE UPPER(COALESCE(origem, '')) = 'ADQUIRENTE'
              AND descricao = :descricao
            ORDER BY id DESC
        ");
        $stmt->execute([
            ':descricao' => 'Recebimento via adquirente da conta #' . $contaOriginalId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $contasAdquirente
     */
    private function validarEstornoContasAdquirente(array $contasAdquirente, bool $estornoTotal): void
    {
        if ($contasAdquirente === []) {
            return;
        }

        if (!$estornoTotal) {
            throw new RuntimeException(
                'Nao e possivel fazer estorno parcial de um recebimento com repasse automatico para adquirente. ' .
                'Faca o estorno total da conta original para remover tambem a conta gerada automaticamente.'
            );
        }

        foreach ($contasAdquirente as $contaAdquirente) {
            $status = strtoupper((string)($contaAdquirente['status'] ?? ''));
            $valorPago = (float)($contaAdquirente['valor_pago'] ?? 0);
            $desconto = (float)($contaAdquirente['desconto'] ?? 0);

            if (!in_array($status, ['PENDENTE', 'VENCIDO'], true) || ($valorPago + $desconto) > 0) {
                throw new RuntimeException(
                    'Não é possivel estornar o recebimento original porque existe uma conta recebida vinculada a essa conta. ' .
                    'Estorne primeiro a conta da vinculada e tente novamente.'
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $contasAdquirente
     */
    private function excluirContasAdquirenteVinculadas(array $contasAdquirente): void
    {
        if ($contasAdquirente === []) {
            return;
        }

        $stmtMov = $this->pdo->prepare('DELETE FROM ' . self::TABLE_MOV . ' WHERE conta_receber_id = :id');
        $stmtConta = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');

        foreach ($contasAdquirente as $contaAdquirente) {
            $contaId = (int)($contaAdquirente['id'] ?? 0);
            if ($contaId <= 0) {
                continue;
            }

            $stmtMov->execute([':id' => $contaId]);
            $stmtConta->execute([':id' => $contaId]);
        }
    }

    private function contar(string $where, array $params): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " cr
                LEFT JOIN clientes cli ON cr.cliente_id = cli.id
                LEFT JOIN formas_pagamento fp ON cr.forma_pagamento_id = fp.id
                {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function recalcularSaldoConta(?int $contaId): void
    {
        // Fun??o n?o utilizada no novo schema
        return;
    }

    /**
     * Obt?m o ID da categoria DRE "Descontos Cedidos em Vendas" (Despesa Operacional)
     * Para contas a receber, o desconto ? uma despesa.
     */
    private function getCategoriDescontosReceber(): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM categorias_dre WHERE nome = 'Descontos Cedidos em Vendas' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }

    private function verificarNfeVinculada(int $contaId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT pn.numero_nfe
            FROM contas_receber cr
            INNER JOIN pedidos p ON p.id = cr.pedido_id
            INNER JOIN pedido_nfe pn ON pn.pedido_id = p.id
            WHERE cr.id = :conta_id
              AND cr.pedido_id IS NOT NULL
              AND pn.status = 'AUTORIZADA'
            ORDER BY pn.data_emissao DESC NULLS LAST, pn.numero_nfe DESC
            LIMIT 1
        ");
        $stmt->execute([':conta_id' => $contaId]);

        $numeroNfe = $stmt->fetchColumn();
        if ($numeroNfe === false || $numeroNfe === null || $numeroNfe === '') {
            return;
        }

        throw new NfeVinculadaException((string)$numeroNfe);
    }
}
