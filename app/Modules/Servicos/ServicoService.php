<?php

namespace App\Modules\Servicos;

use App\Modules\Financeiro\FormaPagamento;
use App\Modules\Financeiro\FormaPagamentoFinanceiroService;
use App\Modules\Produtos\EstoqueMovimentacaoRepository;
use App\Modules\Produtos\EstoqueMovimentacaoService;
use PDO;
use RuntimeException;

class ServicoService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ServicoRepository $servicoRepository,
        private readonly ServicoCatalogoRepository $catalogoRepository,
        private readonly ServicoItemRepository $servicoItemRepository
    ) {}

    public function listarServicos(array $filtros): array
    {
        $perPage = max(1, (int)($filtros['per_page'] ?? 15));
        return $this->servicoRepository->findAll($filtros, $perPage);
    }

    public function listarCatalogo(bool $somenteAtivos = false): array
    {
        return $this->catalogoRepository->listar($somenteAtivos);
    }

    public function salvarCatalogo(array $dados): int
    {
        $nome = trim((string)($dados['nome'] ?? ''));
        if ($nome === '') {
            throw new RuntimeException('Nome do servico e obrigatorio.');
        }

        $valorBase = (float)str_replace(',', '.', (string)($dados['valor_base'] ?? '0'));
        if ($valorBase < 0) {
            throw new RuntimeException('Valor base do servico nao pode ser negativo.');
        }

        return $this->catalogoRepository->salvar([
            'id' => isset($dados['id']) ? (int)$dados['id'] : null,
            'nome' => $nome,
            'descricao' => trim((string)($dados['descricao'] ?? '')) ?: null,
            'valor_base' => $valorBase,
            'ativo' => !empty($dados['ativo']),
        ]);
    }

    public function excluirCatalogo(int $id): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Servico invalido para exclusao.');
        }

        if (!$this->catalogoRepository->excluir($id)) {
            throw new RuntimeException('Nao foi possivel excluir o servico.');
        }
    }

    public function buscarServico(string $id): ?Servico
    {
        $servico = $this->servicoRepository->findById($id);
        if ($servico === null) {
            return null;
        }

        $servico->itens = $this->carregarItensServico($servico);
        return $servico;
    }

    public function validarDadosServico(array $dados): array
    {
        $errors = [];

        $clienteId = isset($dados['cliente_id']) ? (int)$dados['cliente_id'] : 0;
        if ($clienteId <= 0) {
            $errors['cliente_id'] = 'Selecione um cliente.';
        }

        $nomeCliente = trim((string)($dados['nome_cliente'] ?? ''));
        if ($nomeCliente === '') {
            $errors['nome_cliente'] = 'Nome do cliente e obrigatorio.';
        }

        $telefoneCliente = trim((string)($dados['telefone_cliente'] ?? ''));
        if ($telefoneCliente !== '') {
            $telefoneNumeros = preg_replace('/\D+/', '', $telefoneCliente) ?? '';
            if ($telefoneNumeros === '' || strlen($telefoneNumeros) < 10 || strlen($telefoneNumeros) > 11) {
                $errors['telefone_cliente'] = 'Telefone do cliente invalido.';
            }
        }

        $servicoCatalogoId = isset($dados['servico_catalogo_id']) ? (int)$dados['servico_catalogo_id'] : 0;
        if ($servicoCatalogoId <= 0) {
            $errors['servico_catalogo_id'] = 'Selecione um servico.';
        }

        $servicoNome = trim((string)($dados['servico_nome'] ?? ''));
        if ($servicoNome === '') {
            $errors['servico_nome'] = 'Servico e obrigatorio.';
        }

        $servicoValor = (float)str_replace(',', '.', (string)($dados['servico_valor'] ?? $dados['valor_total'] ?? '0'));
        if ($servicoValor < 0) {
            $errors['servico_valor'] = 'Valor do servico nao pode ser negativo.';
        }

        try {
            $itens = $this->normalizarItensServico($dados['itens'] ?? []);
            $this->validarItensServico($itens);
        } catch (\Throwable $e) {
            $errors['itens'] = $e->getMessage();
            $itens = [];
        }

        $placa = strtoupper((string)($dados['placa'] ?? ''));
        $placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';
        if ($placa !== '' && strlen($placa) !== 7) {
            $errors['placa'] = 'Placa invalida. Informe 7 caracteres validos.';
        }

        $status = strtoupper((string)($dados['status'] ?? Servico::STATUS_PENDENTE));
        if (!in_array($status, Servico::STATUS_VALIDOS, true)) {
            $errors['status'] = 'Status invalido para servico.';
        }

        try {
            $desconto = $this->normalizarDescontoServico($dados);
        } catch (\Throwable $e) {
            $errors['desconto_valor'] = $e->getMessage();
            $desconto = ['tipo' => null, 'valor' => 0.0];
        }

        try {
            $subtotal = $this->calcularSubtotalServico([
                'servico_valor' => $servicoValor,
                'itens' => $itens,
            ]);
            $valorTotal = $this->calcularTotalServico([
                'servico_valor' => $servicoValor,
                'itens' => $itens,
            ], $desconto);
            if ($subtotal < 0 || $valorTotal < 0) {
                $errors['valor_total'] = 'Valor total do servico invalido.';
            }
        } catch (\Throwable $e) {
            $errors['valor_total'] = $e->getMessage();
        }

        return $errors;
    }

    public function criarServico(array $dados): Servico
    {
        $errors = $this->validarDadosServico($dados);
        if (!empty($errors)) {
            throw new RuntimeException(reset($errors) ?: 'Dados invalidos para o servico.');
        }

        $payload = $this->normalizarPayloadServico($dados);
        $itens = $payload['itens'] ?? [];
        unset($payload['itens']);
        $movimentarEstoque = !array_key_exists('movimentar_estoque', $dados) || (bool)$dados['movimentar_estoque'];

        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }
        try {
            $servico = $this->servicoRepository->create($payload);
            $this->salvarItensServico($servico->id, $itens);
            $servico->itens = $this->carregarItensServico($servico);
            if ($movimentarEstoque) {
                $this->movimentarItensProdutoServico($servico, $servico->itens, 'SAIDA');
            }
            if ($controlaTransacao) {
                $this->pdo->commit();
            }
            return $servico;
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function atualizarServico(string $id, array $dados): Servico
    {
        $servicoAtual = $this->buscarServico($id);
        if ($servicoAtual === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        if (in_array($servicoAtual->status, [Servico::STATUS_FATURADO, Servico::STATUS_CANCELADO], true)) {
            throw new RuntimeException('Servicos faturados ou cancelados nao podem ser editados.');
        }

        $dadosMesclados = array_merge($servicoAtual->toArray(), $dados, ['id' => $id]);
        $errors = $this->validarDadosServico($dadosMesclados);
        if (!empty($errors)) {
            throw new RuntimeException(reset($errors) ?: 'Dados invalidos para o servico.');
        }

        $payload = $this->normalizarPayloadServico($dadosMesclados);
        $itens = $payload['itens'] ?? [];
        unset($payload['itens']);
        $itensAtuais = $this->carregarItensServico($servicoAtual);
        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->reverterEstoqueServico($servicoAtual, $itensAtuais);
            $servicoAtualizado = $this->servicoRepository->update($id, $payload);
            $this->servicoItemRepository->deleteByServicoId($id);
            $this->salvarItensServico($id, $itens);
            $servicoAtualizado->itens = $this->carregarItensServico($servicoAtualizado);
            $this->movimentarItensProdutoServico($servicoAtualizado, $servicoAtualizado->itens, 'SAIDA');

            if ($controlaTransacao) {
                $this->pdo->commit();
            }

            return $servicoAtualizado;
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancelarServico(string $id): Servico
    {
        $servico = $this->buscarServico($id);
        if ($servico === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        if ($servico->status === Servico::STATUS_CANCELADO) {
            return $servico;
        }

        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($servico->status === Servico::STATUS_FATURADO) {
                $this->exigirContasReceberEmAbertoParaReversao($servico->id, 'cancelar');
                $this->excluirContasReceberVinculadas($servico->id);
            }

            $this->reverterEstoqueServico($servico);

            $servicoAtualizado = $this->servicoRepository->update($id, [
                'status' => Servico::STATUS_CANCELADO,
                'ativo' => false,
                'data_faturamento' => null,
            ]);

            if ($controlaTransacao) {
                $this->pdo->commit();
            }

            return $servicoAtualizado;
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function estornarFaturamentoServico(string $id): Servico
    {
        $servico = $this->buscarServico($id);
        if ($servico === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        if ($servico->status !== Servico::STATUS_FATURADO) {
            throw new RuntimeException('Somente servicos faturados podem ter faturamento estornado.');
        }

        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->exigirContasReceberEmAbertoParaReversao($servico->id, 'estornar');
            $this->excluirContasReceberVinculadas($servico->id);

            $servicoAtualizado = $this->servicoRepository->update($id, [
                'status' => Servico::STATUS_CONCLUIDO,
                'ativo' => true,
                'data_faturamento' => null,
            ]);

            if ($controlaTransacao) {
                $this->pdo->commit();
            }

            return $servicoAtualizado;
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function excluirServico(string $id): void
    {
        $servico = $this->buscarServico($id);
        if ($servico === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($this->servicoPossuiContaReceber($servico->id)) {
                $this->exigirContasReceberEmAbertoParaReversao($servico->id, 'excluir');
                $this->excluirContasReceberVinculadas($servico->id);
            }

            if ($servico->status !== Servico::STATUS_CANCELADO) {
                $this->reverterEstoqueServico($servico);
            }

            if (!$this->servicoRepository->delete($servico->id)) {
                throw new RuntimeException('Nao foi possivel excluir o servico.');
            }

            if ($controlaTransacao) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function atualizarStatusKanban(string $id, string $novoStatus): Servico
    {
        $servico = $this->buscarServico($id);
        if ($servico === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        $novoStatus = strtoupper($novoStatus);
        if (!in_array($novoStatus, Servico::STATUS_VALIDOS, true)) {
            throw new RuntimeException('Status invalido.');
        }

        if (in_array($servico->status, [Servico::STATUS_CANCELADO, Servico::STATUS_FATURADO], true)) {
            throw new RuntimeException('Este servico nao pode ter status alterado via Kanban.');
        }

        if (in_array($novoStatus, [Servico::STATUS_CANCELADO, Servico::STATUS_FATURADO], true)) {
            throw new RuntimeException('Use a acao especifica para cancelar ou faturar o servico.');
        }

        $ok = $this->servicoRepository->atualizarStatusSimples($id, $novoStatus);
        if (!$ok) {
            throw new RuntimeException('Falha ao atualizar status do servico.');
        }

        return $this->buscarServico($id) ?? $servico;
    }

    public function listarClientes(): array
    {
        $stmt = $this->pdo->query('SELECT id, nome, telefone FROM clientes WHERE ativo = TRUE ORDER BY nome');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarProdutosCompradosPorCliente(int $clienteId): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nome, codigo, preco_venda, estoque_atual
             FROM produtos
             WHERE ativo = TRUE
             ORDER BY nome"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarServicoPorOrcamento(int $orcamentoId): ?Servico
    {
        return $this->servicoRepository->findByOrcamentoId($orcamentoId);
    }

    public function criarServicoPorAprovacaoOrcamento(array $dados): Servico
    {
        $dados['movimentar_estoque'] = $dados['movimentar_estoque'] ?? false;
        return $this->criarServico($dados);
    }

    /**
     * @return ServicoItem[]
     */
    private function carregarItensServico(Servico $servico): array
    {
        $itens = $this->servicoItemRepository->findByServicoId($servico->id);
        if ($itens !== []) {
            return $itens;
        }

        if (($servico->produtoId ?? null) === null || (int)$servico->produtoId <= 0) {
            return [];
        }

        return [
            ServicoItem::fromArray([
                'servico_id' => $servico->id,
                'produto_id' => (int)$servico->produtoId,
                'nome_produto' => (string)($servico->produtoNome ?? ''),
                'quantidade' => max(0.0001, (float)($servico->produtoQuantidade > 0 ? $servico->produtoQuantidade : 1)),
                'valor_unitario' => (float)($servico->produtoValorUnitario ?? 0),
                'valor_total_item' => round(
                    max(0.0001, (float)($servico->produtoQuantidade > 0 ? $servico->produtoQuantidade : 1)) * (float)($servico->produtoValorUnitario ?? 0),
                    4
                ),
                'observacoes' => 'Item legado do servico.',
            ]),
        ];
    }

    /**
     * @param array<int, mixed> $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizarItensServico(array $rawItems): array
    {
        $itens = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $produtoId = isset($item['produto_id']) && $item['produto_id'] !== '' ? (int)$item['produto_id'] : 0;
            $quantidade = round((float)str_replace(',', '.', (string)($item['quantidade'] ?? '0')), 4);
            $valorUnitario = round((float)str_replace(',', '.', (string)($item['valor_unitario'] ?? '0')), 4);
            $nomeProduto = trim((string)($item['nome_produto'] ?? ''));

            if ($produtoId <= 0 && $quantidade <= 0 && $valorUnitario <= 0 && $nomeProduto === '') {
                continue;
            }

            $itens[] = [
                'produto_id' => $produtoId,
                'nome_produto' => $nomeProduto,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'valor_total_item' => round($quantidade * $valorUnitario, 4),
            ];
        }

        return $itens;
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    private function validarItensServico(array $itens): void
    {
        foreach ($itens as $index => $item) {
            if ((int)($item['produto_id'] ?? 0) <= 0) {
                throw new RuntimeException('Selecione um produto valido no item #' . ($index + 1) . '.');
            }

            if ((float)($item['quantidade'] ?? 0) <= 0) {
                throw new RuntimeException('Informe uma quantidade maior que zero no item #' . ($index + 1) . '.');
            }

            if ((float)($item['valor_unitario'] ?? 0) < 0) {
                throw new RuntimeException('Valor unitario invalido no item #' . ($index + 1) . '.');
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     * @return array<int, array<string, mixed>>
     */
    private function preencherNomesProdutosItens(array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $ids = [];
        foreach ($itens as $item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            if ($produtoId > 0) {
                $ids[$produtoId] = $produtoId;
            }
        }

        if ($ids === []) {
            return $itens;
        }

        $placeholders = [];
        $params = [];
        $position = 0;
        foreach (array_values($ids) as $produtoId) {
            $key = ':produto_' . $position++;
            $placeholders[] = $key;
            $params[$key] = $produtoId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nome FROM produtos WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        $nomesPorProduto = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $produto) {
            $nomesPorProduto[(int)$produto['id']] = (string)$produto['nome'];
        }

        foreach ($itens as &$item) {
            $produtoId = (int)($item['produto_id'] ?? 0);
            if ($produtoId > 0 && isset($nomesPorProduto[$produtoId])) {
                $item['nome_produto'] = $nomesPorProduto[$produtoId];
            }
        }
        unset($item);

        return $itens;
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     * @return array{produto_id:?int, produto_nome:?string, produto_quantidade:float, produto_valor_unitario:float}
     */
    private function montarResumoProdutoServico(array $itens): array
    {
        if ($itens === []) {
            return [
                'produto_id' => null,
                'produto_nome' => null,
                'produto_quantidade' => 0.0,
                'produto_valor_unitario' => 0.0,
            ];
        }

        if (count($itens) === 1) {
            $item = $itens[0];
            return [
                'produto_id' => (int)$item['produto_id'],
                'produto_nome' => (string)$item['nome_produto'],
                'produto_quantidade' => round((float)$item['quantidade'], 4),
                'produto_valor_unitario' => round((float)$item['valor_unitario'], 4),
            ];
        }

        $quantidadeTotal = 0.0;
        foreach ($itens as $item) {
            $quantidadeTotal += (float)$item['quantidade'];
        }

        return [
            'produto_id' => null,
            'produto_nome' => count($itens) . ' produtos',
            'produto_quantidade' => round($quantidadeTotal, 4),
            'produto_valor_unitario' => 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    private function salvarItensServico(string $servicoId, array $itens): void
    {
        foreach ($itens as $item) {
            $this->servicoItemRepository->create([
                'servico_id' => $servicoId,
                'produto_id' => (int)$item['produto_id'],
                'nome_produto' => (string)$item['nome_produto'],
                'quantidade' => (float)$item['quantidade'],
                'valor_unitario' => (float)$item['valor_unitario'],
                'valor_total_item' => (float)$item['valor_total_item'],
                'observacoes' => null,
            ]);
        }
    }

    /**
     * @param ServicoItem[] $itens
     */
    private function movimentarItensProdutoServico(Servico $servico, array $itens, string $tipo): void
    {
        $itensEstoque = [];
        foreach ($itens as $item) {
            if (($item->produtoId ?? null) === null || (int)$item->produtoId <= 0) {
                continue;
            }

            $itensEstoque[] = [
                'produto_id' => (int)$item->produtoId,
                'quantidade' => max(0.0001, (float)$item->quantidade),
            ];
        }

        if ($itensEstoque === []) {
            return;
        }

        $observacao = $tipo === 'SAIDA'
            ? 'Saida automatica vinculada ao servico.'
            : 'Estorno do servico "' . ($servico->numero ?? $servico->id ?? '') . '"';

        (new EstoqueMovimentacaoService(
            $this->pdo,
            new EstoqueMovimentacaoRepository($this->pdo)
        ))->movimentarItens(
            $itensEstoque,
            $tipo,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : ($servico->usuarioId !== null ? (int)$servico->usuarioId : null),
            'SERVICO',
            'SERVICO',
            $servico->id,
            $observacao
        );
    }

    /**
     * @param ServicoItem[]|null $itens
     */
    private function reverterEstoqueServico(Servico $servico, ?array $itens = null): void
    {
        $this->movimentarItensProdutoServico($servico, $itens ?? $this->carregarItensServico($servico), 'ENTRADA');
    }

    public function faturarServico(string $id, array $dadosFaturamento): Servico
    {
        $servico = $this->buscarServico($id);
        if ($servico === null) {
            throw new RuntimeException('Servico nao encontrado.');
        }

        if ($servico->status === Servico::STATUS_CANCELADO) {
            throw new RuntimeException('Servico cancelado nao pode ser faturado.');
        }

        if ($servico->status === Servico::STATUS_FATURADO) {
            return $servico;
        }

        $formaPagamentoId = (int)($dadosFaturamento['forma_pagamento_id'] ?? 0);
        if ($formaPagamentoId <= 0) {
            throw new RuntimeException('Selecione a forma de pagamento para faturar o servico.');
        }

        $formaFinanceira = new FormaPagamentoFinanceiroService($this->pdo);
        $forma = $formaFinanceira->buscarFormaDetalhada($formaPagamentoId);
        $tipoForma = strtoupper((string)($forma['tipo'] ?? ''));
        $dataBase = (string)($dadosFaturamento['data_faturamento'] ?? date('Y-m-d'));
        $descricaoBase = 'Faturamento Servico ' . ($servico->numero ?? $servico->id);

        $controlaTransacao = !$this->pdo->inTransaction();
        if ($controlaTransacao) {
            $this->pdo->beginTransaction();
        }
        try {
            if (in_array($tipoForma, [FormaPagamento::TIPO_CARTAO_CREDITO, FormaPagamento::TIPO_CARTAO_DEBITO], true)) {
                $formaFinanceira->processarFaturamentoComCartao([
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $servico->valorTotal,
                    'descricao' => 'Repasse adquirente servico #' . ($servico->numero ?? $servico->id),
                    'observacoes' => 'Gerado automaticamente no faturamento do servico.',
                    'servico_id' => $servico->id,
                    'origem' => 'SERVICO',
                    'data_base' => $dataBase,
                ]);
            } elseif ($tipoForma === FormaPagamento::TIPO_A_FATURAR) {
                $dataVencimento = trim((string)($dadosFaturamento['data_vencimento'] ?? ''));
                if ($dataVencimento === '') {
                    throw new RuntimeException('Informe a data de vencimento para faturar como "A faturar".');
                }

                $formaFinanceira->criarContaReceberPendente([
                    'cliente_id' => $servico->clienteId,
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $servico->valorTotal,
                    'data_vencimento' => $dataVencimento,
                    'descricao' => $descricaoBase,
                    'observacoes' => 'Gerado automaticamente no faturamento do servico.',
                    'servico_id' => $servico->id,
                    'origem' => 'SERVICO',
                ]);
            } else {
                $formaFinanceira->processarFaturamentoImediato([
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $servico->valorTotal,
                    'descricao' => $descricaoBase,
                    'servico_id' => $servico->id,
                    'data_base' => $dataBase,
                ]);
            }

            $servico = $this->servicoRepository->update($id, [
                'status' => Servico::STATUS_FATURADO,
                'data_faturamento' => $dataBase . ' 00:00:00',
            ]);

            if ($controlaTransacao) {
                $this->pdo->commit();
            }
            return $this->buscarServico($servico->id) ?? $servico;
        } catch (\Throwable $e) {
            if ($controlaTransacao && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function servicoPossuiContaReceber(string $servicoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contas_receber WHERE servico_id = :servico_id');
        $stmt->execute([':servico_id' => $servicoId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function exigirContasReceberEmAbertoParaReversao(string $servicoId, string $acao): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM contas_receber
            WHERE servico_id = :servico_id
              AND (status <> 'PENDENTE' OR COALESCE(valor_pago, 0) > 0 OR COALESCE(desconto, 0) > 0)
        ");
        $stmt->execute([':servico_id' => $servicoId]);
        $qtdBloqueios = (int)$stmt->fetchColumn();

        if ($qtdBloqueios <= 0) {
            return;
        }

        $mensagem = match ($acao) {
            'cancelar' => 'Nao e possivel cancelar o servico porque existe conta a receber vinculada com status diferente de aberto.',
            'excluir' => 'Nao e possivel excluir o servico porque existe conta a receber vinculada com status diferente de aberto.',
            default => 'Nao e possivel estornar o servico porque existe conta a receber vinculada com status diferente de aberto.',
        };

        throw new RuntimeException($mensagem);
    }

    private function excluirContasReceberVinculadas(string $servicoId): void
    {
        $stmtIds = $this->pdo->prepare('SELECT id FROM contas_receber WHERE servico_id = :servico_id');
        $stmtIds->execute([':servico_id' => $servicoId]);
        $ids = array_map('intval', $stmtIds->fetchAll(PDO::FETCH_COLUMN) ?: []);

        if ($ids === []) {
            return;
        }

        $stmtDeleteMov = $this->pdo->prepare('DELETE FROM movimentacoes WHERE conta_receber_id = :conta_receber_id');
        foreach ($ids as $contaReceberId) {
            $stmtDeleteMov->execute([':conta_receber_id' => $contaReceberId]);
        }

        $stmtDeleteConta = $this->pdo->prepare('DELETE FROM contas_receber WHERE servico_id = :servico_id');
        $stmtDeleteConta->execute([':servico_id' => $servicoId]);
    }

    private function normalizarPayloadServico(array $dados): array
    {
        $telefoneCliente = trim((string)($dados['telefone_cliente'] ?? ''));
        if ($telefoneCliente === '') {
            $telefoneCliente = null;
        } else {
            $telefoneCliente = substr($telefoneCliente, 0, 20);
        }

        $placa = strtoupper((string)($dados['placa'] ?? ''));
        $placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';

        $modeloVeiculo = trim((string)($dados['modelo_veiculo'] ?? ''));
        $observacoes = trim((string)($dados['observacoes'] ?? ''));
        $servicoNome = trim((string)($dados['servico_nome'] ?? ''));
        $servicoValor = round((float)str_replace(',', '.', (string)($dados['servico_valor'] ?? $dados['valor_total'] ?? '0')), 4);
        $itens = $this->preencherNomesProdutosItens($this->normalizarItensServico($dados['itens'] ?? []));
        $this->validarItensServico($itens);
        $resumoProdutos = $this->montarResumoProdutoServico($itens);
        $desconto = $this->normalizarDescontoServico($dados);
        $valorTotal = $this->calcularTotalServico([
            'servico_valor' => $servicoValor,
            'itens' => $itens,
        ], $desconto);

        return [
            'id' => !empty($dados['id']) ? (string)$dados['id'] : null,
            'cliente_id' => isset($dados['cliente_id']) ? (int)$dados['cliente_id'] : null,
            'usuario_id' => isset($dados['usuario_id']) && $dados['usuario_id'] !== '' ? (int)$dados['usuario_id'] : null,
            'orcamento_id' => isset($dados['orcamento_id']) && $dados['orcamento_id'] !== '' ? (int)$dados['orcamento_id'] : null,
            'servico_catalogo_id' => isset($dados['servico_catalogo_id']) && $dados['servico_catalogo_id'] !== '' ? (int)$dados['servico_catalogo_id'] : null,
            'produto_id' => $resumoProdutos['produto_id'],
            'produto_quantidade' => $resumoProdutos['produto_quantidade'],
            'servico_valor' => $servicoValor,
            'produto_valor_unitario' => $resumoProdutos['produto_valor_unitario'],
            'desconto_tipo' => $desconto['tipo'],
            'desconto_valor' => $desconto['valor'],
            'data_servico' => $dados['data_servico'] ?? date('Y-m-d H:i:s'),
            'status' => strtoupper((string)($dados['status'] ?? Servico::STATUS_PENDENTE)),
            'nome_cliente' => substr(trim((string)($dados['nome_cliente'] ?? '')), 0, 255),
            'telefone_cliente' => $telefoneCliente,
            'servico_nome' => substr($servicoNome, 0, 255),
            'produto_nome' => $resumoProdutos['produto_nome'] !== null ? substr((string)$resumoProdutos['produto_nome'], 0, 255) : null,
            'placa' => $placa !== '' ? $placa : null,
            'modelo_veiculo' => $modeloVeiculo !== '' ? substr($modeloVeiculo, 0, 120) : null,
            'valor_total' => $valorTotal,
            'observacoes' => $observacoes !== '' ? $observacoes : null,
            'ativo' => !array_key_exists('ativo', $dados) || (bool)$dados['ativo'],
            'itens' => $itens,
        ];
    }

    private function normalizarDescontoServico(array $dados): array
    {
        $tipo = strtoupper(trim((string)($dados['desconto_tipo'] ?? '')));
        $valor = round((float)str_replace(',', '.', (string)($dados['desconto_valor'] ?? '0')), 2);

        if ($tipo === '' || $valor <= 0) {
            return ['tipo' => null, 'valor' => 0.0];
        }

        if (!in_array($tipo, ['VALOR', 'PERCENTUAL'], true)) {
            throw new RuntimeException('Tipo de desconto do servico invalido.');
        }

        if ($valor < 0) {
            throw new RuntimeException('Valor do desconto do servico nao pode ser negativo.');
        }

        if ($tipo === 'PERCENTUAL' && $valor > 100) {
            throw new RuntimeException('Desconto percentual do servico nao pode ser maior que 100%.');
        }

        return ['tipo' => $tipo, 'valor' => $valor];
    }

    private function calcularSubtotalServico(array $dados): float
    {
        $servicoValor = round((float)($dados['servico_valor'] ?? 0), 4);
        return round($servicoValor + $this->calcularTotalProdutos($dados['itens'] ?? []), 4);
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    private function calcularTotalProdutos(array $itens): float
    {
        $total = 0.0;
        foreach ($itens as $item) {
            $total += round((float)($item['quantidade'] ?? 0) * (float)($item['valor_unitario'] ?? 0), 4);
        }

        return round($total, 4);
    }

    private function calcularTotalServico(array $dados, array $desconto): float
    {
        $subtotal = $this->calcularSubtotalServico($dados);
        $descontoAplicado = 0.0;
        $tipo = $desconto['tipo'] ?? null;
        $valor = round((float)($desconto['valor'] ?? 0), 2);

        if ($tipo === 'PERCENTUAL') {
            $descontoAplicado = round($subtotal * ($valor / 100), 4);
        } elseif ($tipo === 'VALOR') {
            $descontoAplicado = round($valor, 4);
        }

        if ($descontoAplicado > $subtotal) {
            throw new RuntimeException('O desconto nao pode ser maior que o subtotal do servico.');
        }

        return round($subtotal - $descontoAplicado, 4);
    }
}
