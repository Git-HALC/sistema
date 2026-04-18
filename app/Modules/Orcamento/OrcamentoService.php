<?php

namespace App\Modules\Orcamento;

use App\Modules\Produtos\EstoqueMovimentacaoRepository;
use App\Modules\Produtos\EstoqueMovimentacaoService;
use App\Modules\Servicos\Servico;
use App\Modules\Servicos\ServicoCatalogoRepository;
use App\Modules\Servicos\ServicoRepository;
use App\Modules\Servicos\ServicoService;
use DateTime;
use PDO;
use Throwable;

class OrcamentoService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OrcamentoRepository $orcRepo,
        private readonly OrcamentoItemRepository $itemRepo
    ) {}

    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        $registros = $this->orcRepo->listar($filtros, $pagina, $porPagina);
        $total = $this->orcRepo->totalRegistros($filtros);

        return [
            'registros' => $registros,
            'total' => $total,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
            'totalPaginas' => (int) ceil($total / max(1, $porPagina)),
        ];
    }

    public function buscarParaEdicao(int $id): ?array
    {
        $orcamento = $this->orcRepo->buscarPorId($id);
        if ($orcamento === null) {
            return null;
        }

        return [
            'orcamento' => $orcamento,
            'itens' => $this->itemRepo->buscarPorOrcamento($id),
        ];
    }

    public function salvarComItens(array $dados, array $itensRaw, ?int $id): array
    {
        $erros = array_merge(
            $this->validarCabecalho($dados),
            $this->validarItensRaw($itensRaw),
            $this->validarProdutosDosItens($itensRaw)
        );

        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        try {
            $this->pdo->beginTransaction();
            $itens = $this->montarItens($itensRaw);

            if ($id === null) {
                $orcamento = new Orcamento();
                $orcamento->clienteId = $dados['cliente_id'] ? (int) $dados['cliente_id'] : null;
                $orcamento->status = Orcamento::STATUS_RASCUNHO;
                $orcamento->dataEmissao = (string) $dados['data_emissao'];
                $orcamento->dataValidade = !empty($dados['data_validade']) ? (string) $dados['data_validade'] : null;
                $orcamento->observacoes = !empty($dados['observacoes']) ? (string) $dados['observacoes'] : null;
                $orcamento->descontoPercentual = (float) ($dados['desconto_percentual'] ?? 0);
                $orcamento->usuarioId = !empty($dados['usuario_id']) ? (int) $dados['usuario_id'] : null;

                $novoId = $this->orcRepo->criar($orcamento);
                $this->itemRepo->substituirTodos($novoId, $itens);
                $this->recalcularSubtotal($novoId, $orcamento->descontoPercentual);

                $this->pdo->commit();
                return ['ok' => true, 'id' => $novoId];
            }

            $orcamento = $this->orcRepo->buscarPorId($id);
            if ($orcamento === null) {
                $this->pdo->rollBack();
                return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
            }

            if (!$orcamento->podeSerEditado()) {
                $this->pdo->rollBack();
                return ['ok' => false, 'erros' => ["Orcamento com status '{$orcamento->statusLabel()}' nao pode ser editado."]];
            }

            $orcamento->clienteId = $dados['cliente_id'] ? (int) $dados['cliente_id'] : null;
            $orcamento->dataEmissao = (string) $dados['data_emissao'];
            $orcamento->dataValidade = !empty($dados['data_validade']) ? (string) $dados['data_validade'] : null;
            $orcamento->observacoes = !empty($dados['observacoes']) ? (string) $dados['observacoes'] : null;
            $orcamento->descontoPercentual = (float) ($dados['desconto_percentual'] ?? 0);

            $this->orcRepo->atualizar($orcamento);
            $this->itemRepo->substituirTodos($id, $itens);
            $this->recalcularSubtotal($id, $orcamento->descontoPercentual);

            $this->pdo->commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'erros' => ['Erro interno ao salvar orcamento: ' . $e->getMessage()]];
        }
    }

    public function adicionarItem(int $orcamentoId, array $dadosItem): array
    {
        $orcamento = $this->orcRepo->buscarPorId($orcamentoId);
        if ($orcamento === null) {
            return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
        }

        if (!$orcamento->podeSerEditado()) {
            return ['ok' => false, 'erros' => ["Orcamento '{$orcamento->statusLabel()}' nao aceita novos itens."]];
        }

        $erros = $this->validarItemRaw($dadosItem, 1);
        if (!empty($erros)) {
            return ['ok' => false, 'erros' => $erros];
        }

        try {
            $this->pdo->beginTransaction();

            $item = $this->montarItem($dadosItem);
            $item->orcamentoId = $orcamentoId;

            $itemId = $this->itemRepo->criar($item);
            $valorTotal = $this->recalcularSubtotal($orcamentoId, $orcamento->descontoPercentual);

            $this->pdo->commit();

            return [
                'ok' => true,
                'id' => $itemId,
                'subtotal' => $item->subtotal,
                'valorTotal' => $valorTotal,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'erros' => ['Erro ao adicionar item: ' . $e->getMessage()]];
        }
    }

    public function recalcularSubtotal(int $orcamentoId, ?float $descontoPercentual = null): float
    {
        $subtotalItens = $this->itemRepo->somarSubtotais($orcamentoId);

        if ($descontoPercentual === null) {
            $orcamento = $this->orcRepo->buscarPorId($orcamentoId);
            $descontoPercentual = (float) ($orcamento?->descontoPercentual ?? 0);
        }

        $descontoPercentual = max(0, min(100, (float) $descontoPercentual));
        $total = $subtotalItens * (1 - ($descontoPercentual / 100));
        $total = max(0, $total);

        $this->orcRepo->atualizarValorTotal($orcamentoId, $total);

        return $total;
    }

    public function aprovarOrcamento(int $id, array $df): array
    {
        $orcamento = $this->orcRepo->buscarPorId($id);
        if ($orcamento === null) {
            return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
        }

        if (!$orcamento->estaAberto()) {
            return ['ok' => false, 'erros' => ["Apenas orcamentos abertos podem ser aprovados. Status atual: '{$orcamento->statusLabel()}'."]];
        }

        $itens = $this->itemRepo->buscarPorOrcamento($id);
        if (count($itens) === 0) {
            return ['ok' => false, 'erros' => ['O orcamento precisa ter ao menos um item para ser aprovado.']];
        }

        $temServico = $this->orcamentoTemItensDoTipo($itens, OrcamentoItem::TIPO_SERVICO);
        $temProduto = $this->orcamentoTemItensDoTipo($itens, OrcamentoItem::TIPO_PRODUTO);

        $errosAprovacao = $this->validarDadosAprovacao($df, $temServico);
        if (!empty($errosAprovacao)) {
            return ['ok' => false, 'erros' => $errosAprovacao];
        }

        try {
            $this->pdo->beginTransaction();

            $this->validarEstoqueDisponivelOrcamento($id);
            $this->orcRepo->atualizarStatus($id, Orcamento::STATUS_APROVADO);



            $resultadoAprovacao = ['ok' => true];

            if ($temServico) {
                $servico = $this->criarServicoPorAprovacao($orcamento, $itens, $df);
                $resultadoAprovacao['servico_id'] = $servico['id'] ?? null;
                $resultadoAprovacao['servico_numero'] = $servico['numero'] ?? null;
            } elseif ($temProduto) {
                $pedido = $this->criarPedidoPorAprovacao($orcamento);
                $resultadoAprovacao['pedido_id'] = $pedido['id'] ?? null;
                $resultadoAprovacao['pedido_numero'] = $pedido['numero'] ?? null;
            }

            if (!empty($resultadoAprovacao['pedido_id']) || !empty($resultadoAprovacao['servico_id'])) {
                $this->pdo->commit();
                return $resultadoAprovacao;
            }

            throw new \RuntimeException('Orcamento aprovado sem itens validos para gerar pedido ou servico.');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['ok' => false, 'erros' => ['Erro ao aprovar orcamento: ' . $e->getMessage()]];
        }
    }

    public function cancelar(int $id): array
    {
        $orcamento = $this->orcRepo->buscarPorId($id);
        if ($orcamento === null) {
            return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
        }

        if ($orcamento->estaAprovado()) {
            return ['ok' => false, 'erros' => ['Orcamento aprovado nao pode ser cancelado diretamente. Use o estorno da aprovacao.']];
        }

        if ($orcamento->estaCancelado()) {
            return ['ok' => false, 'erros' => ['Orcamento ja esta cancelado.']];
        }

        try {
            $this->pdo->beginTransaction();
            $this->orcRepo->atualizarStatus($id, Orcamento::STATUS_CANCELADO);
            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'erros' => ['Erro ao cancelar: ' . $e->getMessage()]];
        }
    }

    public function estornarAprovacao(int $id, ?int $usuarioId = null): array
    {
        $orcamento = $this->orcRepo->buscarPorId($id);
        if ($orcamento === null) {
            return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
        }

        if (!$orcamento->estaAprovado()) {
            return ['ok' => false, 'erros' => ["Somente orcamentos aprovados podem ser estornados. Status atual: '{$orcamento->statusLabel()}'."]];
        }

        $stmtPedidoAtivo = $this->pdo->prepare("
            SELECT id, numero, status
            FROM pedidos
            WHERE orcamento_id = :orcamento_id
              AND status <> 'CANCELADO'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmtPedidoAtivo->execute([':orcamento_id' => $id]);
        $pedidoAtivo = $stmtPedidoAtivo->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!empty($pedidoAtivo)) {
            $identificadorPedido = !empty($pedidoAtivo['numero']) ? ('#' . $pedidoAtivo['numero']) : ((string) $pedidoAtivo['id']);
            return ['ok' => false, 'erros' => ['Não é possível estornar o orçamento enquanto existir pedido gerado. Pedido (' . $identificadorPedido . ') em aberto.']];
        }

        $servicoAtivo = (new ServicoRepository($this->pdo))->findByOrcamentoId($id);
        if ($servicoAtivo !== null && $servicoAtivo->status !== Servico::STATUS_CANCELADO) {
            $identificadorServico = $servicoAtivo->numero !== null ? ('#' . $servicoAtivo->numero) : $servicoAtivo->id;
            return ['ok' => false, 'erros' => ['Não é possível estornar o orçamento enquanto existir servico gerado. Serviço (' . $identificadorServico . ') em aberto.']];
        }

        try {
            $this->pdo->beginTransaction();
            $this->orcRepo->atualizarStatus($id, Orcamento::STATUS_RASCUNHO);

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'erros' => ['Erro ao estornar orcamento: ' . $e->getMessage()]];
        }
    }

    public function excluir(int $id): array
    {
        $orcamento = $this->orcRepo->buscarPorId($id);
        if ($orcamento === null) {
            return ['ok' => false, 'erros' => ['Orcamento nao encontrado.']];
        }

        if ($orcamento->estaCancelado()) {
            return ['ok' => false, 'erros' => ['Orcamento cancelado nao pode ser excluido.']];
        }

        if (!$orcamento->podeSerExcluido()) {
            return ['ok' => false, 'erros' => ['Orcamento aprovado nao pode ser excluido.']];
        }

        try {
            $this->pdo->beginTransaction();
            $this->orcRepo->excluir($id);
            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'erros' => ['Erro ao excluir: ' . $e->getMessage()]];
        }
    }

    public function listarClientes(): array
    {
        return $this->pdo
            ->query("SELECT id, nome FROM clientes WHERE ativo = TRUE ORDER BY nome")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarProdutos(): array
    {
        return $this->pdo
            ->query("SELECT id, nome, unidade, preco_venda FROM produtos WHERE ativo = TRUE ORDER BY nome")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarServicosCatalogo(): array
    {
        return array_map(
            static fn($item) => [
                'id' => $item->id,
                'nome' => $item->nome,
                'descricao' => $item->descricao,
                'valor_base' => $item->valorBase,
                'ativo' => $item->ativo,
            ],
            (new ServicoCatalogoRepository($this->pdo))->listar(true)
        );
    }

    public function listarFormasPagamento(): array
    {
        return $this->pdo
            ->query("SELECT id, nome FROM formas_pagamento WHERE ativo = TRUE ORDER BY nome")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCategoriasDreReceita(): array
    {
        return $this->pdo
            ->query("SELECT id, nome FROM categorias_dre WHERE tipo = 'Receita' AND ativo = TRUE ORDER BY ordem, nome")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    private function criarContaReceber(Orcamento $orcamento, array $df): int
    {
        $descricao = sprintf('Orcamento %s aprovado', $orcamento->codigo ?? "ORC-{$orcamento->id}");
        $observacoes = sprintf(
            'Conta gerada automaticamente pela aprovacao do %s em %s.',
            $orcamento->codigo ?? "ORC-{$orcamento->id}",
            (new DateTime())->format('d/m/Y H:i')
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO contas_receber
                (cliente_id, valor, data_vencimento, descricao, status,
                 categoria_dre_id, observacoes, origem, orcamento_id)
            VALUES
                (:cliente_id, :valor, :data_vencimento, :descricao, 'PENDENTE',
                 :categoria_dre_id, :observacoes, 'ORCAMENTO', :orcamento_id)
        ");

        $stmt->execute([
            ':cliente_id' => $orcamento->clienteId,
            ':valor' => $orcamento->valorTotal,
            ':data_vencimento' => $df['data_vencimento'],
            ':descricao' => $descricao,
            ':categoria_dre_id' => !empty($df['categoria_dre_id']) ? (int) $df['categoria_dre_id'] : null,
            ':observacoes' => $observacoes,
            ':orcamento_id' => $orcamento->id,
        ]);

        return (int) $this->pdo->lastInsertId('contas_receber_id_seq');
    }

    private function montarItens(array $itensRaw): array
    {
        $itens = [];
        foreach ($itensRaw as $raw) {
            $itens[] = $this->montarItem($raw);
        }
        return $itens;
    }

    private function montarItem(array $raw): OrcamentoItem
    {
        $item = new OrcamentoItem();
        $item->tipoItem = strtoupper((string) ($raw['tipo_item'] ?? OrcamentoItem::TIPO_PRODUTO));
        $item->produtoId = !empty($raw['produto_id']) ? (int) $raw['produto_id'] : null;
        $item->servicoId = !empty($raw['servico_id']) ? (int) $raw['servico_id'] : null;
        $item->nomeProduto = trim((string) ($raw['nome_produto'] ?? ''));
        $item->nomeServico = trim((string) ($raw['nome_servico'] ?? '')) ?: null;
        $item->descricaoItem = trim((string) ($raw['descricao_item'] ?? '')) ?: null;
        $item->quantidade = (float) str_replace(',', '.', (string) ($raw['quantidade'] ?? 0));
        $item->precoUnitario = (float) str_replace(',', '.', (string) ($raw['preco_unitario'] ?? 0));
        $item->calcularSubtotal();

        return $item;
    }

    private function validarCabecalho(array $dados): array
    {
        $erros = [];

        if (empty($dados['data_emissao'])) {
            $erros[] = 'Data de emissao e obrigatoria.';
        }

        $desconto = (float) ($dados['desconto_percentual'] ?? 0);
        if ($desconto < 0 || $desconto > 100) {
            $erros[] = 'Desconto geral deve estar entre 0% e 100%.';
        }

        if (!empty($dados['data_validade']) && !empty($dados['data_emissao']) && $dados['data_validade'] < $dados['data_emissao']) {
            $erros[] = 'Data de validade nao pode ser anterior a data de emissao.';
        }

        return $erros;
    }

    private function validarItensRaw(array $itensRaw): array
    {
        if (empty($itensRaw)) {
            return ['O orcamento precisa ter ao menos um item.'];
        }

        $erros = [];
        foreach ($itensRaw as $i => $raw) {
            $erros = array_merge($erros, $this->validarItemRaw($raw, $i + 1));
        }

        return $erros;
    }

    private function validarItemRaw(array $raw, int $linha): array
    {
        $erros = [];
        $pfx = 'Item ' . $linha;

        $tipoItem = strtoupper((string) ($raw['tipo_item'] ?? OrcamentoItem::TIPO_PRODUTO));
        $produtoId = isset($raw['produto_id']) ? (int) $raw['produto_id'] : 0;
        $servicoId = isset($raw['servico_id']) ? (int) $raw['servico_id'] : 0;

        if (!in_array($tipoItem, [OrcamentoItem::TIPO_PRODUTO, OrcamentoItem::TIPO_SERVICO], true)) {
            $erros[] = $pfx . ': tipo de item invalido.';
        }

        if ($tipoItem === OrcamentoItem::TIPO_PRODUTO) {
            if ($produtoId <= 0) {
                $erros[] = $pfx . ': selecione um produto cadastrado na lista.';
            }
            if (trim((string) ($raw['nome_produto'] ?? '')) === '') {
                $erros[] = $pfx . ': nome do produto e obrigatorio.';
            }
        }

        if ($tipoItem === OrcamentoItem::TIPO_SERVICO) {
            if ($servicoId <= 0) {
                $erros[] = $pfx . ': selecione um servico cadastrado na lista.';
            }
            if (trim((string) ($raw['nome_servico'] ?? '')) === '') {
                $erros[] = $pfx . ': nome do servico e obrigatorio.';
            }
        }

        $qtd = (float) str_replace(',', '.', (string) ($raw['quantidade'] ?? 0));
        if ($qtd <= 0) {
            $erros[] = $pfx . ': quantidade deve ser maior que zero.';
        }

        $preco = (float) str_replace(',', '.', (string) ($raw['preco_unitario'] ?? -1));
        if ($preco < 0) {
            $erros[] = $pfx . ': preco unitario nao pode ser negativo.';
        }

        return $erros;
    }

    private function validarProdutosDosItens(array $itensRaw): array
    {
        $erros = [];
        $produtoIds = [];
        $servicoIds = [];

        foreach ($itensRaw as $raw) {
            $tipoItem = strtoupper((string) ($raw['tipo_item'] ?? OrcamentoItem::TIPO_PRODUTO));
            $produtoId = isset($raw['produto_id']) ? (int) $raw['produto_id'] : 0;
            $servicoId = isset($raw['servico_id']) ? (int) $raw['servico_id'] : 0;

            if ($tipoItem === OrcamentoItem::TIPO_PRODUTO && $produtoId > 0) {
                $produtoIds[$produtoId] = true;
            }

            if ($tipoItem === OrcamentoItem::TIPO_SERVICO && $servicoId > 0) {
                $servicoIds[$servicoId] = true;
            }
        }

        $produtos = $this->buscarEntidadesAtivas('produtos', array_keys($produtoIds), 'pid');
        $servicos = $this->buscarEntidadesAtivas('servicos_catalogo', array_keys($servicoIds), 'sid');

        foreach ($itensRaw as $linha => $raw) {
            $tipoItem = strtoupper((string) ($raw['tipo_item'] ?? OrcamentoItem::TIPO_PRODUTO));
            $produtoId = isset($raw['produto_id']) ? (int) $raw['produto_id'] : 0;
            $servicoId = isset($raw['servico_id']) ? (int) $raw['servico_id'] : 0;

            if ($tipoItem === OrcamentoItem::TIPO_PRODUTO && $produtoId > 0) {
                $produto = $produtos[$produtoId] ?? null;
                if ($produto === null) {
                    $erros[] = 'Item ' . ($linha + 1) . ': produto selecionado nao existe mais no cadastro.';
                    continue;
                }
                if (!filter_var($produto['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $erros[] = 'Item ' . ($linha + 1) . ': produto "' . $produto['nome'] . '" esta inativo.';
                }
            }

            if ($tipoItem === OrcamentoItem::TIPO_SERVICO && $servicoId > 0) {
                $servico = $servicos[$servicoId] ?? null;
                if ($servico === null) {
                    $erros[] = 'Item ' . ($linha + 1) . ': servico selecionado nao existe mais no cadastro.';
                    continue;
                }
                if (!filter_var($servico['ativo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $erros[] = 'Item ' . ($linha + 1) . ': servico "' . $servico['nome'] . '" esta inativo.';
                }
            }
        }

        return $erros;
    }

    private function validarEstoqueDisponivelOrcamento(int $orcamentoId): void
    {
        $itens = $this->itemRepo->buscarPorOrcamento($orcamentoId);
        if (empty($itens)) {
            return;
        }

        (new EstoqueMovimentacaoService(
            $this->pdo,
            new EstoqueMovimentacaoRepository($this->pdo)
        ))->validarDisponibilidadeItens(array_map(static function (OrcamentoItem $item): array {
            return [
                'produto_id' => $item->produtoId,
                'quantidade' => $item->quantidade,
            ];
        }, array_filter($itens, static fn(OrcamentoItem $item): bool => ($item->tipoItem ?? OrcamentoItem::TIPO_PRODUTO) === OrcamentoItem::TIPO_PRODUTO)));
    }

    private function validarDadosAprovacao(array $df, bool $exigirDadosVeiculo = false): array
    {
        $erros = [];

        if ($exigirDadosVeiculo) {
            $placa = strtoupper((string)($df['placa'] ?? ''));
            $placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';
            $modeloVeiculo = trim((string)($df['modelo_veiculo'] ?? ''));

            if ($placa === '') {
                $erros[] = 'Placa e obrigatoria para gerar servico a partir do orcamento.';
            } elseif (strlen($placa) !== 7) {
                $erros[] = 'Placa invalida. Informe 7 caracteres validos.';
            }

            if ($modeloVeiculo === '') {
                $erros[] = 'Modelo do carro e obrigatorio para gerar servico a partir do orcamento.';
            }
        }

        return $erros;
    }

    public function orcamentoTemServico(array $itens): bool
    {
        return $this->orcamentoTemItensDoTipo($itens, OrcamentoItem::TIPO_SERVICO);
    }

    private function orcamentoTemItensDoTipo(array $itens, string $tipo): bool
    {
        foreach ($itens as $item) {
            if (($item->tipoItem ?? OrcamentoItem::TIPO_PRODUTO) === $tipo) {
                return true;
            }
        }

        return false;
    }

    private function buscarEntidadesAtivas(string $tabela, array $ids, string $prefixo): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $idx => $id) {
            $ph = ':' . $prefixo . $idx;
            $placeholders[] = $ph;
            $params[$ph] = (int) $id;
        }

        $stmt = $this->pdo->prepare('SELECT id, nome, ativo FROM ' . $tabela . ' WHERE id IN (' . implode(',', $placeholders) . ')');
        $stmt->execute($params);

        $dados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[(int) $row['id']] = $row;
        }

        return $dados;
    }

    private function criarServicoPorAprovacao(Orcamento $orcamento, array $itens, array $df = []): array
    {
        $servicoExistente = (new ServicoRepository($this->pdo))->findByOrcamentoId($orcamento->id);
        if ($servicoExistente !== null) {
            return [
                'id' => $servicoExistente->id,
                'numero' => (int) ($servicoExistente->numero ?? 0),
            ];
        }

        $stmtCliente = $this->pdo->prepare('SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1');
        $stmtCliente->execute([':id' => $orcamento->clienteId]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC) ?: [];

        $nomesServico = [];
        $nomesProduto = [];
        $produtoId = null;
        $produtoQuantidade = 0.0;
        $servicoCatalogoId = null;

        foreach ($itens as $item) {
            if (($item->tipoItem ?? OrcamentoItem::TIPO_PRODUTO) === OrcamentoItem::TIPO_SERVICO) {
                $servicoCatalogoId ??= $item->servicoId;
                if (!empty($item->nomeServico)) {
                    $nomesServico[] = $item->nomeServico;
                }
                continue;
            }

            $produtoId ??= $item->produtoId;
            $produtoQuantidade += (float) ($item->quantidade ?? 0);
            if ($item->nomeProduto !== '') {
                $nomesProduto[] = $item->nomeProduto;
            }
        }

        $servico = (new ServicoService(
            $this->pdo,
            new ServicoRepository($this->pdo),
            new ServicoCatalogoRepository($this->pdo)
        ))->criarServicoPorAprovacaoOrcamento([
            'cliente_id' => $orcamento->clienteId,
            'usuario_id' => $orcamento->usuarioId,
            'orcamento_id' => $orcamento->id,
            'servico_catalogo_id' => $servicoCatalogoId,
            'produto_id' => $produtoId,
            'produto_quantidade' => $produtoQuantidade > 0 ? round($produtoQuantidade, 4) : 0.0,
            'nome_cliente' => (string) ($cliente['nome'] ?? ($orcamento->clienteNome ?? 'Cliente')),
            'telefone_cliente' => (string) ($cliente['telefone'] ?? ($orcamento->clienteTelefone ?? '')),
            'servico_nome' => implode(', ', array_unique($nomesServico)) ?: 'Servico de orcamento aprovado',
            'produto_nome' => implode(', ', array_unique($nomesProduto)) ?: null,
            'placa' => trim((string)($df['placa'] ?? '')),
            'modelo_veiculo' => trim((string)($df['modelo_veiculo'] ?? '')),
            'valor_total' => $orcamento->valorTotal,
            'observacoes' => $orcamento->observacoes,
            'status' => Servico::STATUS_PENDENTE,
        ]);

        return [
            'id' => $servico->id,
            'numero' => (int) ($servico->numero ?? 0),
        ];
    }

    private function criarPedidoPorAprovacao(Orcamento $orcamento): array
    {
        $stmtExiste = $this->pdo->prepare("
            SELECT id, numero
            FROM pedidos
            WHERE orcamento_id = :orcamento_id
              AND ativo = TRUE
              AND status <> 'CANCELADO'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmtExiste->execute([':orcamento_id' => $orcamento->id]);
        $existente = $stmtExiste->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!empty($existente)) {
            return [
                'id' => (string) ($existente['id'] ?? ''),
                'numero' => (int) ($existente['numero'] ?? 0),
            ];
        }

        $stmtPedido = $this->pdo->prepare("
            INSERT INTO pedidos
                (id, cliente_id, orcamento_id, data_pedido, status, valor_total, observacoes, ativo)
            VALUES
                (uuid_generate_v4(), :cliente_id, :orcamento_id, NOW(), 'PENDENTE', :valor_total, :observacoes, TRUE)
            RETURNING id, numero
        ");
        $stmtPedido->execute([
            ':cliente_id' => $orcamento->clienteId,
            ':orcamento_id' => $orcamento->id,
            ':valor_total' => round((float) $orcamento->valorTotal, 4),
            ':observacoes' => 'Gerado automaticamente pela aprovacao do orcamento ' . ($orcamento->codigo ?? ('ORC-' . $orcamento->id)),
        ]);

        $pedidoRow = $stmtPedido->fetch(PDO::FETCH_ASSOC) ?: [];
        $pedidoId = (string) ($pedidoRow['id'] ?? '');
        $pedidoNumero = (int) ($pedidoRow['numero'] ?? 0);

        if ($pedidoId === '') {
            throw new \RuntimeException('Falha ao gerar pedido automatico do orcamento aprovado.');
        }

        $itens = $this->itemRepo->buscarPorOrcamento($orcamento->id);
        $stmtItem = $this->pdo->prepare("
            INSERT INTO pedido_itens
                (id, pedido_id, produto_id, nome_produto, quantidade, valor_unitario, valor_total_item, observacoes)
            VALUES
                (uuid_generate_v4(), :pedido_id, :produto_id, :nome_produto, :quantidade, :valor_unitario, :valor_total_item, :observacoes)
        ");

        foreach ($itens as $item) {
            if (($item->tipoItem ?? OrcamentoItem::TIPO_PRODUTO) !== OrcamentoItem::TIPO_PRODUTO) {
                continue;
            }

            $stmtItem->execute([
                ':pedido_id' => $pedidoId,
                ':produto_id' => $item->produtoId,
                ':nome_produto' => $item->nomeProduto,
                ':quantidade' => round((float) $item->quantidade, 4),
                ':valor_unitario' => round((float) $item->precoUnitario, 4),
                ':valor_total_item' => round((float) $item->subtotal, 4),
                ':observacoes' => null,
            ]);
        }

        (new EstoqueMovimentacaoService(
            $this->pdo,
            new EstoqueMovimentacaoRepository($this->pdo)
        ))->movimentarItens(
            $itens,
            'SAIDA',
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : ($orcamento->usuarioId !== null ? (int) $orcamento->usuarioId : null),
            'PEDIDO',
            'PEDIDO',
            $pedidoId,
            'Saida automatica ao gerar pedido pela aprovacao do orcamento ' . ($orcamento->codigo ?? ('ORC-' . $orcamento->id))
        );

        return ['id' => $pedidoId, 'numero' => $pedidoNumero];
    }
}
