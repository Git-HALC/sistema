<?php

namespace App\Modules\Financeiro;

use PDO;
use RuntimeException;
use DateTime;

/**
 * FinanceiroService - Camada Centralizada de Registro de Movimenta??es Financeiras
 * 
 * Esta classe implementa o princ?pio DRY (Don't Repeat Yourself) para todas as
 * opera??es financeiras, garantindo:
 * 
 * ? Consist?ncia cont?bil em toda a aplica??o
 * ? Rastreamento de origem (MANUAL, RECEBIMENTO, PAGAMENTO, ESTORNO)
 * ? Transa??es ACID garantidas
 * ? Elimina??o de duplica??es
 * 
 * Fluxo de Dados:
 * 
 *   ContaPagarService/ContaReceberService
 *              ?
 *   registrarMovimentacaoFinanceira()  ? Ponto ?nico de entrada
 *              ?
 *         Valida??es
 *              ?
 *      INSERT movimentacoes
 *              ?
 *         Trigger (atualiza saldo de contas)
 *              ?
 *      DreRepository consulta APENAS movimentacoes
 * 
 */
class FinanceiroService
{
    const TIPO_RECEITA = 'RECEITA';
    const TIPO_DESPESA = 'DESPESA';
    
    const ORIGEM_MANUAL = 'MANUAL';
    const ORIGEM_RECEBIMENTO = 'RECEBIMENTO';
    const ORIGEM_PAGAMENTO = 'PAGAMENTO';
    const ORIGEM_ESTORNO = 'ESTORNO';
    
    private PDO $pdo;
    private bool $inTransaction = false;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Registra uma movimenta??o financeira de forma centralizada.
     * 
     * Esta ? a ?NICA fun??o que deve inserir em movimentacoes.
     * Todos os outros m?todos devem chamar esta fun??o.
     * 
     * @param array $dados {
     *     @var int $conta_id (obrigat?rio) ID da conta banc?ria
     *     @var string $tipo (obrigat?rio) 'Entrada' ou 'Sa?da'
     *     @var float $valor (obrigat?rio) Valor da movimenta??o > 0
     *     @var string $tipo_financeiro (obrigat?rio) 'RECEITA' ou 'DESPESA'
     *     @var string $origem (obrigat?rio) 'MANUAL|RECEBIMENTO|PAGAMENTO|ESTORNO'
     *     @var string $descricao (opcional) Descri??o da movimenta??o
     *     @var int $categoria_dre_id (opcional) ID da categoria DRE
     *     @var int $forma_pagamento_id (opcional) ID da forma de pagamento
     *     @var int $conta_receber_id (opcional) ID da conta a receber
     *     @var int $conta_pagar_id (opcional) ID da conta a pagar
     *     @var DateTime|string $data (opcional, default: agora) Data da movimenta??o
     * }
     * 
     * @return int ID da movimenta??o inserida
     * 
     * @throws RuntimeException Se houver erro de valida??o ou banco
     */
    public function registrarMovimentacaoFinanceira(array $dados): int
    {
        // =====================================================================
        // VALIDA??ES
        // =====================================================================
        
        // Validar campos obrigat?rios
        if (empty($dados['conta_id'])) {
            throw new RuntimeException('conta_id ? obrigat?rio');
        }
        if (empty($dados['tipo']) || !in_array($dados['tipo'], ['Entrada', 'Sa?da'])) {
            throw new RuntimeException('tipo deve ser "Entrada" ou "Sa?da"');
        }
        if (empty($dados['valor']) || (float)$dados['valor'] <= 0) {
            throw new RuntimeException('valor deve ser maior que 0');
        }
        if (empty($dados['tipo_financeiro']) || !in_array($dados['tipo_financeiro'], [self::TIPO_RECEITA, self::TIPO_DESPESA])) {
            throw new RuntimeException('tipo_financeiro deve ser "RECEITA" ou "DESPESA"');
        }
        if (empty($dados['origem']) || !in_array($dados['origem'], [
            self::ORIGEM_MANUAL, 
            self::ORIGEM_RECEBIMENTO, 
            self::ORIGEM_PAGAMENTO, 
            self::ORIGEM_ESTORNO
        ])) {
            throw new RuntimeException('origem deve ser: MANUAL, RECEBIMENTO, PAGAMENTO ou ESTORNO');
        }
        
        // Validar coer?ncia: Entrada deve ser RECEITA, Sa?da deve ser DESPESA
        // EXCE??O: Estorno pode ser o oposto
        if ($dados['origem'] !== self::ORIGEM_ESTORNO) {
            if ($dados['tipo'] === 'Entrada' && $dados['tipo_financeiro'] !== self::TIPO_RECEITA) {
                throw new RuntimeException('Entrada deve ter tipo_financeiro = RECEITA');
            }
            if ($dados['tipo'] === 'Sa?da' && $dados['tipo_financeiro'] !== self::TIPO_DESPESA) {
                throw new RuntimeException('Sa?da deve ter tipo_financeiro = DESPESA');
            }
        }
        
        // Validar conta existe
        $stmt = $this->pdo->prepare('SELECT id FROM contas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $dados['conta_id']]);
        if (!$stmt->fetch()) {
            throw new RuntimeException('Conta banc?ria ID ' . $dados['conta_id'] . ' n?o existe');
        }
        
        // Validar categoria DRE se fornecida
        if (!empty($dados['categoria_dre_id'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM categorias_dre WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $dados['categoria_dre_id']]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Categoria DRE ID ' . $dados['categoria_dre_id'] . ' n?o existe');
            }
        }
        
        // =====================================================================
        // PREPARA??O DE DADOS
        // =====================================================================
        
        $valor = (float)$dados['valor'];
        $descricao = $dados['descricao'] ?? null;
        $categoriaId = !empty($dados['categoria_dre_id']) ? (int)$dados['categoria_dre_id'] : null;
        $formaPagamentoId = !empty($dados['forma_pagamento_id']) ? (int)$dados['forma_pagamento_id'] : null;
        $contaReceberId = !empty($dados['conta_receber_id']) ? (int)$dados['conta_receber_id'] : null;
        $contaPagadoraId = !empty($dados['conta_pagar_id']) ? (int)$dados['conta_pagar_id'] : null;
        $pedidoId = !empty($dados['pedido_id']) ? (string)$dados['pedido_id'] : null;
        $servicoId = !empty($dados['servico_id']) ? (string)$dados['servico_id'] : null;
        
        // Converter data para timestamp
        $data = $dados['data'] ?? new DateTime();
        if (is_string($data)) {
            $data = new DateTime($data);
        }
        $dataMovimentacao = $data->format('Y-m-d H:i:s');
        
        // =====================================================================
        // EXECU??O COM TRANSA??O
        // =====================================================================
        
        $wasInTransaction = $this->inTransaction || $this->pdo->inTransaction();
        
        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
        
        try {
            // Inserir movimenta??o
            $afetaSaldo = isset($dados['afeta_saldo']) ? (bool)$dados['afeta_saldo'] : true;

            $stmt = $this->pdo->prepare("
                INSERT INTO movimentacoes
                (conta_id, tipo, valor, tipo_origem, data_movimentacao, descricao,
                 categoria_dre_id, forma_pagamento_id, conta_receber_id, conta_pagar_id, pedido_id, servico_id, afeta_saldo, created_at)
                VALUES
                (:conta_id, :tipo, :valor, :tipo_origem, :data_movimentacao, :descricao,
                 :categoria_dre_id, :forma_pagamento_id, :conta_receber_id, :conta_pagar_id, :pedido_id, :servico_id, :afeta_saldo, NOW())
                RETURNING id
            ");

            $stmt->execute([
                ':conta_id' => $dados['conta_id'],
                ':tipo' => $dados['tipo'],
                ':valor' => $valor,
                ':tipo_origem' => $dados['origem'],
                ':data_movimentacao' => $dataMovimentacao,
                ':descricao' => $descricao,
                ':categoria_dre_id' => $categoriaId,
                ':forma_pagamento_id' => $formaPagamentoId,
                ':conta_receber_id' => $contaReceberId,
                ':conta_pagar_id' => $contaPagadoraId,
                ':pedido_id' => $pedidoId,
                ':servico_id' => $servicoId,
                ':afeta_saldo' => $afetaSaldo ? 'true' : 'false',
            ]);
            
            $movimentacaoId = $stmt->fetch(PDO::FETCH_COLUMN);
            
            if (!$wasInTransaction) {
                $this->pdo->commit();
                $this->inTransaction = false;
            }
            
            return (int)$movimentacaoId;
            
        } catch (\Exception $e) {
            if (!$wasInTransaction) {
                $this->pdo->rollBack();
                $this->inTransaction = false;
            }
            throw new RuntimeException('Erro ao registrar movimenta??o: ' . $e->getMessage());
        }
    }
    
    /**
     * Registra um par de movimenta??es (valor pago + desconto).
     * ?til para recebimentos/pagamentos que envolvam desconto.
     * 
     * @param array $dados Mesmo formato de registrarMovimentacaoFinanceira
     * @param float $desconto Valor do desconto
     * @param int $categoriaDreDescontoId ID da categoria DRE para o desconto
     * 
     * @return array ['movimentacao_principal' => int, 'movimentacao_desconto' => int]
     */
    public function registrarMovimentacaoComDesconto(
        array $dados,
        float $desconto,
        int $categoriaDreDescontoId
    ): array {
        if ($desconto <= 0) {
            throw new RuntimeException('Desconto deve ser maior que 0');
        }
        
        // Registrar movimenta??o principal
        $movPrincipal = $this->registrarMovimentacaoFinanceira($dados);
        
        // Registrar desconto como movimenta??o separada
        // L?gica: Desconto ? sempre uma DEDU??O
        // - Para Recebimento: desconto ? ENTRADA em "Descontos Cedidos em Vendas" (Despesa)
        // - Para Pagamento: desconto ? ENTRADA em "Descontos Obtidos" (Dedu??o)
        
        $tipoDesconto = $dados['tipo'] === 'Entrada' ? 'Sa?da' : 'Entrada';
        $tipoFinanceiroDesconto = $dados['tipo'] === 'Entrada' ? 'DESPESA' : 'RECEITA';
        
        $movDesconto = $this->registrarMovimentacaoFinanceira([
            'conta_id' => $dados['conta_id'],
            'tipo' => $tipoDesconto,
            'valor' => $desconto,
            'tipo_financeiro' => $tipoFinanceiroDesconto,
            'origem' => $dados['origem'],
            'descricao' => 'Desconto - ' . ($dados['descricao'] ?? ''),
            'categoria_dre_id' => $categoriaDreDescontoId,
            'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            'conta_receber_id' => $dados['conta_receber_id'] ?? null,
            'conta_pagar_id' => $dados['conta_pagar_id'] ?? null,
            'data' => $dados['data'] ?? new DateTime(),
        ]);
        
        return [
            'movimentacao_principal' => $movPrincipal,
            'movimentacao_desconto' => $movDesconto,
        ];
    }
    
    /**
     * Inicia transa??o manual para m?ltiplas movimenta??es.
     * Use quando quiser garantir atomicidade em m?ltiplas opera??es.
     */
    public function iniciarTransacao(): void
    {
        if (!$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }
    
    /**
     * Commit da transa??o manual.
     */
    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }
    
    /**
     * Rollback da transa??o manual.
     */
    public function rollback(): void
    {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }
}
