<?php

namespace App\Modules\Financeiro;

use PDO;
use RuntimeException;

class ContaPagarRepository
{
    private const TABLE     = 'contas_pagar';
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

    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        [$where, $params] = $this->buildWhere($filtros);
        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = "
            SELECT cp.*,
                   cat.nome  AS categoria_dre_nome,
                   cli.nome  AS cliente_nome
            FROM " . self::TABLE . " cp
            LEFT JOIN categorias_dre cat ON cp.categoria_dre_id = cat.id
            LEFT JOIN clientes cli ON cp.cliente_id = cli.id
            {$where}
            ORDER BY cp.data_vencimento DESC, cp.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = $this->contar($where, $params);

        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $pagina,
            'itens_por_pagina' => $porPagina,
            'total_paginas' => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    public function buscarVencidos(array $filtros, int $pagina, int $porPagina): array
    {
        $filtros['status'] = 'PENDENTE';
        [$where, $params] = $this->buildWhere($filtros);

        $vencClause = empty($where)
            ? 'WHERE cp.data_vencimento < CURRENT_DATE'
            : $where . ' AND cp.data_vencimento < CURRENT_DATE';

        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = "
            SELECT cp.*,
                   cat.nome AS categoria_dre_nome,
                   cli.nome  AS cliente_nome,
                   (CURRENT_DATE - cp.data_vencimento) AS dias_atraso,
                   CASE
                       WHEN (CURRENT_DATE - cp.data_vencimento) <= 30 THEN 'Leve'
                       WHEN (CURRENT_DATE - cp.data_vencimento) <= 60 THEN 'Moderado'
                       ELSE 'Grave'
                   END AS nivel_atraso
            FROM " . self::TABLE . " cp
            LEFT JOIN categorias_dre cat ON cp.categoria_dre_id = cat.id
            LEFT JOIN clientes cli ON cp.cliente_id = cli.id
            {$vencClause}
            ORDER BY cp.data_vencimento ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlCount = "SELECT COUNT(*) FROM " . self::TABLE . " cp {$vencClause}";
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        return [
            'dados' => $dados,
            'total' => $total,
            'pagina' => $pagina,
            'itens_por_pagina' => $porPagina,
            'total_paginas' => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . "
                (cliente_id, descricao, valor, data_vencimento, status, categoria_dre_id, observacoes)
            VALUES
                (:cliente_id, :descricao, :valor, :data_vencimento, 'PENDENTE', :categoria_dre_id, :observacoes)
        ");

        $stmt->execute([
            ':cliente_id' => !empty($dados['cliente_id']) ? (int)$dados['cliente_id'] : null,
            ':descricao' => $dados['descricao'] ?? null,
            ':valor' => $dados['valor'] ?? 0,
            ':data_vencimento' => $dados['data_vencimento'],
            ':categoria_dre_id' => !empty($dados['categoria_dre_id']) ? $dados['categoria_dre_id'] : null,
            ':observacoes' => $dados['observacoes'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId('contas_pagar_id_seq');
    }

    public function atualizar(int $id, array $dados): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a pagar n?o encontrada.');
        }
        if ($conta['status'] === 'PAGO') {
            throw new RuntimeException('N?o ? poss?vel editar uma conta j? paga.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE " . self::TABLE . " SET
                cliente_id       = :cliente_id,
                valor            = :valor,
                data_vencimento  = :data_vencimento,
                descricao        = :descricao,
                categoria_dre_id = :categoria_dre_id,
                observacoes      = :observacoes,
                status           = :status
            WHERE id = :id
        ");

        return $stmt->execute([
            ':id' => $id,
            ':cliente_id' => !empty($dados['cliente_id']) ? (int)$dados['cliente_id'] : ((int)($conta['cliente_id'] ?? 0) ?: null),
            ':valor' => $dados['valor'],
            ':data_vencimento' => $dados['data_vencimento'],
            ':descricao' => $dados['descricao'] ?? null,
            ':categoria_dre_id' => !empty($dados['categoria_dre_id']) ? $dados['categoria_dre_id'] : null,
            ':observacoes' => $dados['observacoes'] ?? null,
            ':status' => $dados['status'] ?? $conta['status'],
        ]);
    }

    public function baixar(int $id, array $dados): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a pagar n?o encontrada.');
        }
        if ($conta['status'] === 'PAGO') {
            throw new RuntimeException('Esta conta j? foi paga.');
        }

        $contaId = (int) ($dados['conta_id'] ?? 0);
        if ($contaId <= 0) {
            throw new RuntimeException('Conta banc?ria inv?lida para baixa.');
        }

        $valorPago = (float) ($dados['valor_pago'] ?? 0);
        $desconto  = (float) ($dados['desconto_pagamento'] ?? 0);
        
        // Valida??o: desconto + valor_pago deve totalizar o valor da conta para permitir desconto
        if ($desconto > 0) {
            $totalComDesconto = $valorPago + $desconto;
            $saldoRestante = (float)$conta['valor'] - ((float)($conta['valor_pago'] ?? 0));
            if (abs($totalComDesconto - $saldoRestante) > 0.01) {
                throw new RuntimeException('Desconto s? ? permitido quando o valor pago + desconto totaliza o saldo restante da conta.');
            }
        }
        
        $valorPagoAcumulado = ((float)($conta['valor_pago'] ?? 0)) + $valorPago;
        $descontoAcumulado  = ((float)($conta['desconto'] ?? 0)) + $desconto;
        $status = ($valorPagoAcumulado + $descontoAcumulado) >= (float)$conta['valor'] ? 'PAGO' : 'PENDENTE';
        $dataPagamento = $dados['data_pagamento'] ?? date('Y-m-d');

        // Se FinanceiroService est? dispon?vel, usar ele (nova arquitetura com transa??o centralizada)
        if ($this->financeiroService !== null) {
            try {
                $categoriaId = !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null;
                
                // Iniciar transa??o centralizada no FinanceiroService
                $this->financeiroService->iniciarTransacao();
                
                // Registrar pagamento como DESPESA
                $this->financeiroService->registrarMovimentacaoFinanceira([
                    'conta_id' => $contaId,
                    'tipo' => 'Sa?da',
                    'valor' => $valorPago,
                    'tipo_financeiro' => 'DESPESA',
                    'origem' => 'PAGAMENTO',
                    'descricao' => 'Baixa conta a pagar #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                    'categoria_dre_id' => $categoriaId,
                    'conta_pagar_id' => $id,
                    'data' => $dataPagamento,
                ]);
                
                // Se houver desconto, registrar como movimenta??o separada (dedu??o)
                // afeta_saldo = false: desconto vai para DRE mas N?O altera o saldo da conta banc?ria
                if ($desconto > 0) {
                    $categoriaDescontos = $this->getCategoriDescontosPagar();
                    if ($categoriaDescontos) {
                        $this->financeiroService->registrarMovimentacaoFinanceira([
                            'conta_id' => $contaId,
                            'tipo' => 'Entrada',
                            'valor' => $desconto,
                            'tipo_financeiro' => 'RECEITA',
                            'origem' => 'PAGAMENTO',
                            'descricao' => 'Desconto obtido na baixa #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                            'categoria_dre_id' => $categoriaDescontos,
                            'conta_pagar_id' => $id,
                            'data' => $dataPagamento,
                            'afeta_saldo' => false,
                        ]);
                    }
                }
                
                // Atualizar status da conta (dentro da mesma transa??o)
                $stmt = $this->pdo->prepare("
                    UPDATE " . self::TABLE . " SET
                        valor_pago     = COALESCE(valor_pago, 0) + :valor_pago,
                        desconto       = COALESCE(desconto, 0) + :desconto,
                        status         = :status,
                        data_pagamento = :data_pagamento,
                        categoria_dre_id = COALESCE(:categoria_dre_id, categoria_dre_id)
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':valor_pago' => $valorPago,
                    ':desconto' => $desconto,
                    ':status' => $status,
                    ':data_pagamento' => $dataPagamento,
                    ':categoria_dre_id' => !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null,
                    ':id' => $id,
                ]);
                
                // Commit da transa??o centralizada
                $this->financeiroService->commit();
                return true;
                
            } catch (\Exception $e) {
                try {
                    $this->financeiroService->rollback();
                } catch (\Exception $se) {
                    // Ignore rollback errors
                }
                throw new RuntimeException('Erro ao baixar conta a pagar: ' . $e->getMessage());
            }
        }
        
        // Fallback: usar inser??o direta de movimenta??o (antiga arquitetura)
        $this->pdo->beginTransaction();
        try {
            // Movimenta??o de sa?da (pagamento)
            $stmtMov = $this->pdo->prepare("
                INSERT INTO " . self::TABLE_MOV . "
                    (conta_id, tipo, valor, desconto, data_movimentacao, descricao, categoria_dre_id, conta_pagar_id)
                VALUES
                    (:conta_id, 'Sa?da', :valor, :desconto, :data_movimentacao, :descricao, :categoria_dre_id, :conta_pagar_id)
            ");
            $stmtMov->execute([
                ':conta_id' => $contaId,
                ':valor' => $valorPago,
                ':desconto' => $desconto,
                ':data_movimentacao' => $dataPagamento,
                ':descricao' => 'Baixa conta a pagar #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                ':categoria_dre_id' => !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null,
                ':conta_pagar_id' => $id,
            ]);

            // Se houver desconto, lan?ar em "Descontos obtidos" (Dedu??es)
            if ($desconto > 0) {
                $categoriaDescontos = $this->getCategoriDescontosPagar();
                if ($categoriaDescontos) {
                    $stmtDesc = $this->pdo->prepare("
                        INSERT INTO " . self::TABLE_MOV . "
                            (conta_id, tipo, valor, data_movimentacao, descricao, categoria_dre_id, conta_pagar_id, afeta_saldo)
                        VALUES
                            (:conta_id, 'Entrada', :valor, :data_movimentacao, :descricao, :categoria_dre_id, :conta_pagar_id, FALSE)
                    ");

                    $stmtDesc->execute([
                        ':conta_id' => $contaId,
                        ':valor' => $desconto,
                        ':data_movimentacao' => $dataPagamento,
                        ':descricao' => 'Desconto obtido na baixa #' . $id . ' - ' . ($conta['descricao'] ?? ''),
                        ':categoria_dre_id' => $categoriaDescontos,
                        ':conta_pagar_id' => $id,
                    ]);
                }
            }
            
            // Atualizar status da conta
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . " SET
                    valor_pago     = COALESCE(valor_pago, 0) + :valor_pago,
                    desconto       = COALESCE(desconto, 0) + :desconto,
                    status         = :status,
                    data_pagamento = :data_pagamento,
                    categoria_dre_id = COALESCE(:categoria_dre_id, categoria_dre_id)
                WHERE id = :id
            ");
            $stmt->execute([
                ':valor_pago' => $valorPago,
                ':desconto' => $desconto,
                ':status' => $status,
                ':data_pagamento' => $dataPagamento,
                ':categoria_dre_id' => !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null,
                ':id' => $id,
            ]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Erro ao baixar conta a pagar: ' . $e->getMessage());
        }
    }

    /**
     * Estorna um pagamento total ou parcial.
     * Se valor n?o informado ou maior/igual ao total, estorna tudo.
     * Caso contr?rio, estorna apenas o valor especificado da ?ltima movimenta??o.
     */
    public function estornar(int $id, ?float $valorEstorno = null, ?int $usuarioId = null): bool
    {
        $conta = $this->buscarPorId($id);
        if (!$conta) {
            throw new RuntimeException('Conta a pagar n?o encontrada.');
        }
        
        $totalPago = (float)($conta['valor_pago'] ?? 0);
        $totalDesconto = (float)($conta['desconto'] ?? 0);
        $totalEfetivo = $totalPago + $totalDesconto;
        
        if ($totalEfetivo <= 0) {
            throw new RuntimeException('N?o h? valores para estornar.');
        }

        // Se n?o informou valor ou informou valor >= total, estorna tudo
        $estornoTotal = !$valorEstorno || $valorEstorno >= $totalEfetivo;
        
        if (!$estornoTotal && $valorEstorno <= 0) {
            throw new RuntimeException('Valor de estorno inv?lido.');
        }

        $this->pdo->beginTransaction();
        try {
            if ($estornoTotal) {
                // Estorno total: deleta todas as movimenta??es e zera tudo
                $stmtMov = $this->pdo->prepare('DELETE FROM ' . self::TABLE_MOV . ' WHERE conta_pagar_id = :id');
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
                // Estorno parcial: busca ?ltima movimenta??o de sa?da (pagamento)
                $stmtUltima = $this->pdo->prepare("
                    SELECT id, valor, desconto
                    FROM " . self::TABLE_MOV . "
                    WHERE conta_pagar_id = :id AND tipo = 'Sa?da'
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
            throw new RuntimeException('Conta a pagar n?o encontrada.');
        }

        // Bloquear exclus?o se a conta ainda est? PAGA (com movimenta??es vinculadas)
        if ($conta['status'] === 'PAGO') {
            throw new RuntimeException(
                'N?o ? poss?vel excluir uma conta paga com movimenta??es vinculadas. ' .
                'Estorne o pagamento primeiro para remover as movimenta??es e ent?o poder? excluir a conta.'
            );
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    private function buildWhere(array $filtros): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filtros['status']) && $filtros['status'] !== 'Vencido') {
            $conditions[] = 'cp.status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['cliente_id'])) {
            $conditions[] = 'cp.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int) $filtros['cliente_id'];
        }
        if (!empty($filtros['data_inicio'])) {
            $conditions[] = 'cp.data_vencimento >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $conditions[] = 'cp.data_vencimento <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        if (!empty($filtros['busca'])) {
            $conditions[] = '(cp.descricao ILIKE :busca OR cp.fornecedor ILIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }

    private function contar(string $where, array $params): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " cp {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function recalcularSaldoConta(?int $contaId): void
    {
        return;
    }

    /**
     * Obt?m o ID da categoria DRE "Descontos obtidos" (Dedu??es)
     * Para contas a pagar, o desconto obtido reduz as dedu??es da receita bruta.
     */
    private function getCategoriDescontosPagar(): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM categorias_dre WHERE nome = 'Descontos obtidos' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }
}
