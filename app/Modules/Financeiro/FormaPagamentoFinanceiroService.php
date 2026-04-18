<?php

namespace App\Modules\Financeiro;

use PDO;
use RuntimeException;

class FormaPagamentoFinanceiroService
{
    public const CADASTRO_URL = '/sistema_dm/public/admin/financeiro/formas-pagamento.php';

    private FormaPagamentoRepository $formaPagamentoRepository;
    private FinanceiroService $financeiroService;

    public function __construct(
        private readonly PDO $pdo,
        ?FormaPagamentoRepository $formaPagamentoRepository = null,
        ?FinanceiroService $financeiroService = null
    ) {
        $this->formaPagamentoRepository = $formaPagamentoRepository ?? new FormaPagamentoRepository($pdo);
        $this->financeiroService = $financeiroService ?? new FinanceiroService($pdo);
    }

    public function buscarFormaDetalhada(int $formaPagamentoId): array
    {
        $forma = $this->formaPagamentoRepository->buscarDetalhadaPorId($formaPagamentoId);
        if ($forma === null || empty($forma['ativo'])) {
            throw new RuntimeException('Forma de pagamento selecionada nao esta disponivel.');
        }

        return $forma;
    }

    public function exigirConfiguracaoParaRecebimento(array $forma): void
    {
        $tipo = strtoupper((string)($forma['tipo'] ?? ''));

        if (in_array($tipo, [FormaPagamento::TIPO_CARTAO_CREDITO, FormaPagamento::TIPO_CARTAO_DEBITO], true)) {
            if (empty($forma['adquirente_id']) || !array_key_exists('prazo_dias', $forma) || (int)($forma['prazo_dias'] ?? 0) < 0) {
                throw new RuntimeException(
                    'A forma de pagamento selecionada nao possui adquirente e/ou prazo cadastrado. Atualize o cadastro antes de continuar.'
                );
            }
            return;
        }

        if (in_array($tipo, [
            FormaPagamento::TIPO_DINHEIRO,
            FormaPagamento::TIPO_PIX,
            FormaPagamento::TIPO_TRANSFERENCIA,
            FormaPagamento::TIPO_BOLETO,
        ], true) && empty($forma['conta_id'])) {
            throw new RuntimeException(
                'A forma de pagamento selecionada nao possui banco configurado. Atualize o cadastro antes de continuar.'
            );
        }
    }

    public function getCadastroUrl(): string
    {
        return self::CADASTRO_URL;
    }

    public function calcularResumoTaxa(float $valorBase, array $forma): array
    {
        $taxaPercentual = (float)($forma['taxa'] ?? 0);
        $valorTaxa = round($valorBase * ($taxaPercentual / 100), 2);
        $valorLiquido = round($valorBase - $valorTaxa, 2);

        return [
            'taxa_percentual' => $taxaPercentual,
            'valor_taxa' => $valorTaxa,
            'valor_liquido' => $valorLiquido,
        ];
    }

    public function criarRecebivelAdquirente(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO contas_receber
                (cliente_id, forma_pagamento_id, valor, data_vencimento, descricao, status, categoria_dre_id, observacoes, pedido_id, servico_id, origem, created_at, updated_at)
            VALUES
                (:cliente_id, :forma_pagamento_id, :valor, :data_vencimento, :descricao, 'PENDENTE', :categoria_dre_id, :observacoes, :pedido_id, :servico_id, :origem, NOW(), NOW())
            RETURNING id
        ");

        $stmt->execute([
            ':cliente_id' => (int)$dados['cliente_id'],
            ':forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            ':valor' => round((float)$dados['valor'], 2),
            ':data_vencimento' => $dados['data_vencimento'],
            ':descricao' => $dados['descricao'],
            ':categoria_dre_id' => $dados['categoria_dre_id'] ?? null,
            ':observacoes' => $dados['observacoes'] ?? null,
            ':pedido_id' => $dados['pedido_id'] ?? null,
            ':servico_id' => $dados['servico_id'] ?? null,
            ':origem' => $dados['origem'] ?? 'ADQUIRENTE',
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function criarContaReceberPendente(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO contas_receber
                (cliente_id, forma_pagamento_id, valor, data_vencimento, descricao, status, categoria_dre_id, observacoes, pedido_id, servico_id, origem, created_at, updated_at)
            VALUES
                (:cliente_id, :forma_pagamento_id, :valor, :data_vencimento, :descricao, 'PENDENTE', :categoria_dre_id, :observacoes, :pedido_id, :servico_id, :origem, NOW(), NOW())
            RETURNING id
        ");

        $stmt->execute([
            ':cliente_id' => $dados['cliente_id'] ?? null,
            ':forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            ':valor' => round((float)$dados['valor'], 2),
            ':data_vencimento' => $dados['data_vencimento'],
            ':descricao' => $dados['descricao'],
            ':categoria_dre_id' => $dados['categoria_dre_id'] ?? null,
            ':observacoes' => $dados['observacoes'] ?? null,
            ':pedido_id' => $dados['pedido_id'] ?? null,
            ':servico_id' => $dados['servico_id'] ?? null,
            ':origem' => $dados['origem'] ?? 'MANUAL',
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function registrarMovimentacaoImediata(array $dados): int
    {
        return $this->financeiroService->registrarMovimentacaoFinanceira([
            'conta_id' => (int)$dados['conta_id'],
            'tipo' => 'Entrada',
            'valor' => round((float)$dados['valor'], 2),
            'tipo_financeiro' => FinanceiroService::TIPO_RECEITA,
            'origem' => $dados['origem'] ?? FinanceiroService::ORIGEM_RECEBIMENTO,
            'descricao' => $dados['descricao'],
            'categoria_dre_id' => $dados['categoria_dre_id'] ?? null,
            'forma_pagamento_id' => $dados['forma_pagamento_id'] ?? null,
            'conta_receber_id' => $dados['conta_receber_id'] ?? null,
            'data' => $dados['data'] ?? date('Y-m-d H:i:s'),
            'afeta_saldo' => true,
            'pedido_id' => $dados['pedido_id'] ?? null,
            'servico_id' => $dados['servico_id'] ?? null,
        ]);
    }

    public function processarFaturamentoComCartao(array $contexto): int
    {
        $forma = $this->buscarFormaDetalhada((int)$contexto['forma_pagamento_id']);
        $this->exigirConfiguracaoParaRecebimento($forma);

        $valorBruto = round((float)$contexto['valor'], 2);
        $resumoTaxa = $this->calcularResumoTaxa($valorBruto, $forma);
        if ($resumoTaxa['valor_liquido'] <= 0) {
            throw new RuntimeException('A taxa configurada gera valor liquido invalido para a adquirente.');
        }

        $dataBase = (string)($contexto['data_base'] ?? date('Y-m-d'));
        $prazoDias = (int)($forma['prazo_dias'] ?? 0);
        $dataVencimento = date('Y-m-d', strtotime($dataBase . ' +' . $prazoDias . ' days'));
        $observacoes = trim((string)($contexto['observacoes'] ?? ''));
        if ($resumoTaxa['valor_taxa'] > 0) {
            $observacoes = trim($observacoes . ' Taxa aplicada: ' .
                number_format((float)$resumoTaxa['taxa_percentual'], 2, '.', '') .
                '% (R$ ' . number_format((float)$resumoTaxa['valor_taxa'], 2, '.', '') . ').');
        }

        return $this->criarRecebivelAdquirente([
            'cliente_id' => (int)$forma['adquirente_id'],
            'forma_pagamento_id' => (int)$contexto['forma_pagamento_id'],
            'valor' => $resumoTaxa['valor_liquido'],
            'data_vencimento' => $dataVencimento,
            'descricao' => $contexto['descricao'],
            'categoria_dre_id' => $contexto['categoria_dre_id'] ?? null,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'pedido_id' => $contexto['pedido_id'] ?? null,
            'servico_id' => $contexto['servico_id'] ?? null,
            'origem' => $contexto['origem'] ?? 'ADQUIRENTE',
        ]);
    }

    public function processarFaturamentoImediato(array $contexto): int
    {
        $forma = $this->buscarFormaDetalhada((int)$contexto['forma_pagamento_id']);
        $this->exigirConfiguracaoParaRecebimento($forma);

        return $this->registrarMovimentacaoImediata([
            'conta_id' => (int)$forma['conta_id'],
            'valor' => round((float)$contexto['valor'], 2),
            'descricao' => $contexto['descricao'],
            'categoria_dre_id' => $contexto['categoria_dre_id'] ?? null,
            'forma_pagamento_id' => (int)$contexto['forma_pagamento_id'],
            'conta_receber_id' => $contexto['conta_receber_id'] ?? null,
            'pedido_id' => $contexto['pedido_id'] ?? null,
            'servico_id' => $contexto['servico_id'] ?? null,
            'data' => $contexto['data_base'] ?? date('Y-m-d'),
            'origem' => $contexto['origem'] ?? FinanceiroService::ORIGEM_RECEBIMENTO,
        ]);
    }
}
