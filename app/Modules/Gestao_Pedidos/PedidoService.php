<?php

namespace App\Modules\Gestao_Pedidos;

use App\Modules\Produtos\EstoqueMovimentacaoRepository;
use App\Modules\Produtos\EstoqueMovimentacaoService;
use App\Modules\Produtos\ProdutoService;
use App\Modules\Financeiro\ContaReceberRepository;
use App\Modules\Financeiro\ContaReceberService;
use App\Modules\Financeiro\FormaPagamento;
use App\Modules\Financeiro\FormaPagamentoFinanceiroService;
use App\Modules\Orcamento\OrcamentoService;
use PDO;
use RuntimeException;

class PedidoService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PedidoRepository $pedidoRepository,
        private readonly PedidoItemRepository $pedidoItemRepository,
        private readonly ?ProdutoService $produtoService = null,
        private readonly ?ContaReceberService $financeiroService = null,
        private readonly ?OrcamentoService $orcamentoService = null
    ) {}

    public function criarPedido(array $data, string $tipoCriacao = 'novo'): Pedido
    {
        $tipoCriacao = $tipoCriacao === 'deOrcamento' ? 'deOrcamento' : 'novo';
        $clienteId = (string)($data['cliente_id'] ?? '');
        $statusInicial = strtoupper((string)($data['status'] ?? Pedido::STATUS_PENDENTE));
        $descontoPedido = $this->normalizarDescontoPedido($data, $tipoCriacao);

        if ($clienteId === '' && $tipoCriacao === 'deOrcamento' && !empty($data['orcamento_id'])) {
            $stmt = $this->pdo->prepare('SELECT cliente_id FROM orcamentos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$data['orcamento_id']]);
            $clienteId = (string)($stmt->fetchColumn() ?: '');
            $data['cliente_id'] = $clienteId;
        }

        if ($clienteId === '') {
            throw new RuntimeException('cliente_id Ã© obrigatÃ³rio para criar pedido.');
        }

        if (!in_array($statusInicial, Pedido::STATUS_VALIDOS, true)) {
            throw new RuntimeException('Status invÃ¡lido para criaÃ§Ã£o de pedido.');
        }

        $this->pdo->beginTransaction();
        try {
            $itens = $tipoCriacao === 'deOrcamento'
                ? $this->resolverItensDeOrcamento($data)
                : $this->normalizarItens($data['itens'] ?? []);
            $itens = $this->preencherNomesProdutos($itens);

            $this->validarItens($itens);

            $pedido = $this->pedidoRepository->create([
                'id' => $data['id'] ?? PedidoRepository::uuid(),
                'cliente_id' => $clienteId,
                'usuario_id' => $_SESSION['user_id'] ?? null,
                'orcamento_id' => $tipoCriacao === 'deOrcamento' ? ($data['orcamento_id'] ?? null) : ($data['orcamento_id'] ?? null),
                'data_pedido' => $data['data_pedido'] ?? date('Y-m-d H:i:s'),
                'status' => $statusInicial,
                'observacoes' => $data['observacoes'] ?? null,
                'data_entrega_prevista' => $data['data_entrega_prevista'] ?? null,
                'data_entrega_realizada' => $data['data_entrega_realizada'] ?? null,
                'valor_total' => 0,
                'desconto_tipo' => $descontoPedido['tipo'],
                'desconto_valor' => $descontoPedido['valor'],
                'ativo' => $statusInicial !== Pedido::STATUS_CANCELADO,
            ]);

            if ($statusInicial !== Pedido::STATUS_CANCELADO) {
                $this->ajustarEstoque($itens, 'DEBITAR', $pedido->id);
            }

            foreach ($itens as $item) {
                $item['pedido_id'] = $pedido->id;
                $this->pedidoItemRepository->create($item);
            }

            $valorTotal = $this->calcularTotalPedido($itens, $descontoPedido);

            $pedido = $this->pedidoRepository->update($pedido->id, [
                'valor_total' => round($valorTotal, 4),
                'desconto_tipo' => $descontoPedido['tipo'],
                'desconto_valor' => $descontoPedido['valor'],
            ]);

            $this->pdo->commit();

            return $this->buscarPedidoPorId($pedido->id) ?? $pedido;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function buscarPedidoPorId(string $id): ?Pedido
    {
        $pedido = $this->pedidoRepository->findById($id, ['cliente', 'orcamento']);
        if ($pedido === null) {
            return null;
        }

        $pedido->itens = $this->pedidoItemRepository->findByPedidoId($pedido->id);
        return $pedido;
    }

    public function buscarUsuarioCriadorPedido(Pedido $pedido): ?string
    {
        $stmtHasColumn = $this->pdo->prepare("\n            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'pedidos'
              AND column_name = 'usuario_id'
            LIMIT 1
        ");
        $stmtHasColumn->execute();
        $temUsuarioIdEmPedidos = (bool)$stmtHasColumn->fetchColumn();

        if ($temUsuarioIdEmPedidos) {
            $stmt = $this->pdo->prepare("\n                SELECT u.nome
                FROM pedidos p
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $pedido->id]);
            $nome = $stmt->fetchColumn();
            if (!empty($nome)) {
                return (string)$nome;
            }
        }

        if (!empty($pedido->orcamento_id)) {
            $stmtOrc = $this->pdo->prepare("\n                SELECT u.nome
                FROM orcamentos o
                LEFT JOIN usuarios u ON u.id = o.usuario_id
                WHERE o.id = :orcamento_id
                LIMIT 1
            ");
            $stmtOrc->execute([':orcamento_id' => (int)$pedido->orcamento_id]);
            $nomeOrc = $stmtOrc->fetchColumn();
            if (!empty($nomeOrc)) {
                return (string)$nomeOrc;
            }
        }

        $stmtAudExists = $this->pdo->prepare("\n            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'auditoria_usuarios'
            LIMIT 1
        ");
        $stmtAudExists->execute();
        $temAuditoriaUsuarios = (bool)$stmtAudExists->fetchColumn();

        if ($temAuditoriaUsuarios) {
            $stmtAudPedido = $this->pdo->prepare("\n                SELECT usuario_nome
                FROM auditoria_usuarios
                WHERE modulo = 'pedidos'
                  AND acao = 'CRIAR'
                  AND dados IS NOT NULL
                  AND dados->>'pedido_id' = :pedido_id
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmtAudPedido->execute([':pedido_id' => $pedido->id]);
            $nomeAudPedido = $stmtAudPedido->fetchColumn();
            if (!empty($nomeAudPedido)) {
                return (string)$nomeAudPedido;
            }

            $stmtAudOrc = $this->pdo->prepare("\n                SELECT usuario_nome
                FROM auditoria_usuarios
                WHERE modulo = 'orcamentos'
                  AND acao = 'APROVAR'
                  AND dados IS NOT NULL
                  AND dados->>'pedido_id' = :pedido_id
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmtAudOrc->execute([':pedido_id' => $pedido->id]);
            $nomeAudOrc = $stmtAudOrc->fetchColumn();
            if (!empty($nomeAudOrc)) {
                return (string)$nomeAudOrc;
            }
        }

        return null;
    }

    public function listarPedidos(array $filtros): array
    {
        $perPage = max(1, (int)($filtros['per_page'] ?? 15));
        return $this->pedidoRepository->findAll($filtros, $perPage);
    }

    public function listarClientes(): array
    {
        $stmt = $this->pdo->query("\n            SELECT id, nome, cpf_cnpj\n            FROM clientes\n            WHERE ativo = TRUE\n            ORDER BY nome ASC\n        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarProdutos(): array
    {
        $stmt = $this->pdo->query("\n            SELECT id, nome, codigo, preco_venda, estoque_atual, ativo\n            FROM produtos\n            WHERE ativo = TRUE\n            ORDER BY nome ASC\n        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarOrcamentosAprovadosSemPedido(): array
    {
        $stmt = $this->pdo->query("\n            SELECT o.id, o.numero, o.cliente_id, c.nome AS cliente_nome, o.valor_total\n            FROM orcamentos o\n            LEFT JOIN clientes c ON c.id = o.cliente_id\n            LEFT JOIN pedidos p ON p.orcamento_id = o.id AND p.ativo = TRUE\n            WHERE o.status = 'APROVADO'\n              AND p.id IS NULL\n            ORDER BY o.id DESC\n        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function atualizarPedido(string $id, array $data): Pedido
    {
        $atual = $this->buscarPedidoPorId($id);
        if ($atual === null) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        $novoStatus = strtoupper((string)($data['status'] ?? $atual->status));
        $this->validarTransicaoStatus($atual->status, $novoStatus);
        $descontoPedido = $this->normalizarDescontoPedido($data, 'novo', $atual);

        $itensNovos = array_key_exists('itens', $data)
            ? $this->normalizarItens($data['itens'] ?? [])
            : array_map(static fn(PedidoItem $i): array => $i->toArray(), $atual->itens);
        $itensNovos = $this->preencherNomesProdutos($itensNovos);

        if (!empty($itensNovos)) {
            $this->validarItens($itensNovos);
        }

        $this->pdo->beginTransaction();
        try {
            if (array_key_exists('itens', $data) && $atual->status !== Pedido::STATUS_CANCELADO) {
                $itensAtuais = array_map(static fn(PedidoItem $i): array => $i->toArray(), $atual->itens);
                $this->ajustarEstoque($itensAtuais, 'CREDITAR', $id);

                if ($novoStatus !== Pedido::STATUS_CANCELADO) {
                    $this->ajustarEstoque($itensNovos, 'DEBITAR', $id);
                }

                $this->pedidoItemRepository->deleteByPedidoId($id);
                foreach ($itensNovos as $item) {
                    $item['pedido_id'] = $id;
                    $this->pedidoItemRepository->create($item);
                }
            }

            $itensParaTotal = array_key_exists('itens', $data)
                ? $itensNovos
                : array_map(static fn(PedidoItem $i): array => $i->toArray(), $atual->itens);
            $valorTotal = $this->calcularTotalPedido($itensParaTotal, $descontoPedido);

            $pedido = $this->pedidoRepository->update($id, [
                'cliente_id' => $data['cliente_id'] ?? $atual->cliente_id,
                'orcamento_id' => array_key_exists('orcamento_id', $data) ? $data['orcamento_id'] : $atual->orcamento_id,
                'data_pedido' => $data['data_pedido'] ?? $atual->data_pedido,
                'status' => $novoStatus,
                'observacoes' => array_key_exists('observacoes', $data) ? $data['observacoes'] : $atual->observacoes,
                'data_entrega_prevista' => array_key_exists('data_entrega_prevista', $data) ? $data['data_entrega_prevista'] : $atual->data_entrega_prevista,
                'data_entrega_realizada' => array_key_exists('data_entrega_realizada', $data) ? $data['data_entrega_realizada'] : $atual->data_entrega_realizada,
                'valor_total' => round($valorTotal, 4),
                'desconto_tipo' => $descontoPedido['tipo'],
                'desconto_valor' => $descontoPedido['valor'],
            ]);

            $this->pdo->commit();
            return $this->buscarPedidoPorId($pedido->id) ?? $pedido;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function cancelarPedido(string $id): Pedido
    {
        $pedido = $this->buscarPedidoPorId($id);
        if ($pedido === null) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        $stmtContaBaixada = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM contas_receber\n            WHERE pedido_id = :pedido_id\n              AND status = 'PAGO'\n        ");
        $stmtContaBaixada->execute([':pedido_id' => $pedido->id]);
        $qtdContasBaixadas = (int)$stmtContaBaixada->fetchColumn();

        if ($qtdContasBaixadas > 0) {
            throw new RuntimeException(
                'NÃ£o Ã© possÃ­vel cancelar o pedido porque existe conta a receber vinculada com status Recebido/baixada. ' .
                'Estorne o faturamento do pedido antes de cancelar.'
            );
        }

        if ($pedido->status === Pedido::STATUS_FATURADO) {
            throw new RuntimeException('Pedido faturado nÃ£o pode ser cancelado.');
        }

        if ($pedido->status === Pedido::STATUS_CANCELADO) {
            return $pedido;
        }

        $this->pdo->beginTransaction();
        try {
            $itens = array_map(static fn(PedidoItem $i): array => $i->toArray(), $pedido->itens);
            $this->ajustarEstoque($itens, 'CREDITAR', $id);

            $pedido = $this->pedidoRepository->update($id, [
                'status' => Pedido::STATUS_CANCELADO,
                'ativo' => false,
            ]);

            if (!empty($pedido->orcamento_id)) {
                $stmtOrcamento = $this->pdo->prepare('SELECT status FROM orcamentos WHERE id = :id LIMIT 1 FOR UPDATE');
                $stmtOrcamento->execute([':id' => (int)$pedido->orcamento_id]);
                $statusOrcamento = (string)($stmtOrcamento->fetchColumn() ?: '');

                if ($statusOrcamento === 'APROVADO') {
                    $stmtAbrir = $this->pdo->prepare('UPDATE orcamentos SET status = :status, updated_at = NOW() WHERE id = :id');
                    $stmtAbrir->execute([
                        ':status' => 'RASCUNHO',
                        ':id' => (int)$pedido->orcamento_id,
                    ]);
                }
            }

            $this->pdo->commit();
            return $this->buscarPedidoPorId($pedido->id) ?? $pedido;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function faturarPedido(string $id, array $dadosFaturamento): Pedido
    {
        $pedido = $this->buscarPedidoPorId($id);
        if ($pedido === null) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        if ($pedido->status === Pedido::STATUS_CANCELADO) {
            throw new RuntimeException('Pedido cancelado nÃ£o pode ser faturado.');
        }

        if ($pedido->status === Pedido::STATUS_FATURADO) {
            return $pedido;
        }

        $formaPagamentoId = (int)($dadosFaturamento['forma_pagamento_id'] ?? 0);
        if ($formaPagamentoId <= 0) {
            throw new RuntimeException('Selecione a forma de pagamento para faturar o pedido.');
        }

        $formaFinanceira = new FormaPagamentoFinanceiroService($this->pdo);
        $forma = $formaFinanceira->buscarFormaDetalhada($formaPagamentoId);
        $tipoForma = strtoupper((string)($forma['tipo'] ?? ''));
        $dataBase = (string)($dadosFaturamento['data_faturamento'] ?? date('Y-m-d'));
        $descricaoBase = 'Faturamento Pedido ' . ($pedido->numero ?? $pedido->id);

        $this->pdo->beginTransaction();
        try {
            if (in_array($tipoForma, [FormaPagamento::TIPO_CARTAO_CREDITO, FormaPagamento::TIPO_CARTAO_DEBITO], true)) {
                $formaFinanceira->processarFaturamentoComCartao([
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $pedido->valor_total,
                    'descricao' => 'Repasse adquirente pedido #' . ($pedido->numero ?? $pedido->id),
                    'observacoes' => 'Gerado automaticamente no faturamento do pedido.',
                    'pedido_id' => $pedido->id,
                    'origem' => 'PEDIDO',
                    'data_base' => $dataBase,
                ]);
            } elseif ($tipoForma === FormaPagamento::TIPO_A_FATURAR) {
                $dataVencimento = trim((string)($dadosFaturamento['data_vencimento'] ?? ''));
                if ($dataVencimento === '') {
                    throw new RuntimeException('Informe a data de vencimento para faturar como "A faturar".');
                }

                $formaFinanceira->criarContaReceberPendente([
                    'cliente_id' => $pedido->cliente_id !== null ? (int)$pedido->cliente_id : null,
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $pedido->valor_total,
                    'data_vencimento' => $dataVencimento,
                    'descricao' => $descricaoBase,
                    'observacoes' => 'Gerado automaticamente no faturamento do pedido.',
                    'pedido_id' => $pedido->id,
                    'origem' => 'PEDIDO',
                ]);
            } else {
                $formaFinanceira->processarFaturamentoImediato([
                    'forma_pagamento_id' => $formaPagamentoId,
                    'valor' => $pedido->valor_total,
                    'descricao' => $descricaoBase,
                    'pedido_id' => $pedido->id,
                    'data_base' => $dataBase,
                ]);
            }

            $pedido = $this->pedidoRepository->update($id, [
                'status' => Pedido::STATUS_FATURADO,
                'data_faturamento' => $dataBase . ' 00:00:00',
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->buscarPedidoPorId($pedido->id) ?? $pedido;
    }

    public function estornarFaturamentoPedido(string $id): Pedido
    {
        $pedido = $this->buscarPedidoPorId($id);
        if ($pedido === null) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        if ($pedido->status !== Pedido::STATUS_FATURADO) {
            throw new RuntimeException('Somente pedidos faturados podem ter faturamento estornado.');
        }

        $this->pdo->beginTransaction();
        try {
            // Fluxo correto:
            // - Se hÃ¡ contas com status diferente de PENDENTE (como PAGO), nÃ£o deixar estornar
            // - Se todas as contas sÃ£o PENDENTE (recebimento parcial), deixar estornar
            $stmtContasNaoPendente = $this->pdo->prepare("\n                SELECT COUNT(*)\n                FROM contas_receber\n                WHERE pedido_id = :pedido_id\n                  AND (status NOT IN ('PENDENTE') OR (valor_pago IS NOT NULL AND valor_pago > 0))\n            ");
            $stmtContasNaoPendente->execute([':pedido_id' => $pedido->id]);
            $qtdNaoPendente = (int)$stmtContasNaoPendente->fetchColumn();

            if ($qtdNaoPendente > 0) {
                throw new RuntimeException(
                    'N?o ? poss?vel estornar o faturamento deste pedido porque existe conta a receber ' .
                    'com recebimento confirmado (nÃ£o estÃ¡ mais pendente).'
                );
            }

            $stmtDel = $this->pdo->prepare('DELETE FROM contas_receber WHERE pedido_id = :pedido_id');
            $stmtDel->execute([':pedido_id' => $pedido->id]);

            // Ao estornar faturamento, pedido retorna ao status CONCLUIDO
            $pedido = $this->pedidoRepository->update($id, [
                'status' => Pedido::STATUS_CONCLUIDO,
                'data_faturamento' => null,
            ]);

            $this->pdo->commit();
            return $this->buscarPedidoPorId($pedido->id) ?? $pedido;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function resolverItensDeOrcamento(array $data): array
    {
        $orcamentoId = isset($data['orcamento_id']) ? (int)$data['orcamento_id'] : 0;
        if ($orcamentoId <= 0) {
            throw new RuntimeException('orcamento_id Ã© obrigatÃ³rio para criar pedido de orÃ§amento.');
        }

        if ($this->orcamentoService === null) {
            throw new RuntimeException('OrcamentoService nÃ£o configurado para criaÃ§Ã£o de pedido por orÃ§amento.');
        }

        $resultado = $this->orcamentoService->buscarParaEdicao($orcamentoId);
        if ($resultado === null) {
            throw new RuntimeException('OrÃ§amento nÃ£o encontrado para conversÃ£o.');
        }

        $itens = [];
        foreach (($resultado['itens'] ?? []) as $itemOrc) {
            $itens[] = [
                'produto_id' => isset($itemOrc->produtoId) ? (string)$itemOrc->produtoId : null,
                'nome_produto' => (string)($itemOrc->nomeProduto ?? ''),
                'quantidade' => (float)($itemOrc->quantidade ?? 0),
                'valor_unitario' => (float)($itemOrc->precoUnitario ?? 0),
                'observacoes' => null,
            ];
        }

        return $itens;
    }

    private function normalizarItens(array $itensRaw): array
    {
        $itens = [];
        foreach ($itensRaw as $raw) {
            $itens[] = [
                'produto_id' => isset($raw['produto_id']) && $raw['produto_id'] !== '' ? (string)$raw['produto_id'] : null,
                'nome_produto' => trim((string)($raw['nome_produto'] ?? '')),
                'quantidade' => (float)str_replace(',', '.', (string)($raw['quantidade'] ?? 0)),
                'valor_unitario' => (float)str_replace(',', '.', (string)($raw['valor_unitario'] ?? 0)),
                'observacoes' => isset($raw['observacoes']) ? trim((string)$raw['observacoes']) : null,
            ];
        }
        return $itens;
    }

    private function preencherNomesProdutos(array $itens): array
    {
        $ids = [];
        foreach ($itens as $item) {
            if (!empty($item['produto_id']) && trim((string)($item['nome_produto'] ?? '')) === '') {
                $ids[] = (int)$item['produto_id'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return $itens;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $idx => $produtoId) {
            $ph = ':produto_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $produtoId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nome FROM produtos WHERE id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        $nomesPorId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nomesPorId[(int)$row['id']] = (string)$row['nome'];
        }

        foreach ($itens as &$item) {
            $produtoId = isset($item['produto_id']) ? (int)$item['produto_id'] : 0;
            if ($produtoId > 0 && trim((string)($item['nome_produto'] ?? '')) === '' && isset($nomesPorId[$produtoId])) {
                $item['nome_produto'] = $nomesPorId[$produtoId];
            }
        }
        unset($item);

        return $itens;
    }

    private function normalizarDescontoPedido(array $data, string $tipoCriacao = 'novo', ?Pedido $pedidoAtual = null): array
    {
        $tipoInformado = strtoupper(trim((string)($data['desconto_tipo'] ?? '')));
        $valorInformado = str_replace(',', '.', (string)($data['desconto_valor'] ?? '0'));
        $valor = round((float)$valorInformado, 2);

        if ($tipoInformado === '' && $pedidoAtual !== null) {
            return [
                'tipo' => $pedidoAtual->desconto_tipo,
                'valor' => round((float)$pedidoAtual->desconto_valor, 2),
            ];
        }

        if ($tipoInformado === '' && $tipoCriacao === 'deOrcamento' && !empty($data['orcamento_id'])) {
            $stmt = $this->pdo->prepare('SELECT desconto_percentual FROM orcamentos WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int)$data['orcamento_id']]);
            $descontoOrcamento = round((float)($stmt->fetchColumn() ?: 0), 2);

            if ($descontoOrcamento > 0) {
                return ['tipo' => 'PERCENTUAL', 'valor' => $descontoOrcamento];
            }

            return ['tipo' => null, 'valor' => 0.0];
        }

        if ($tipoInformado === '' || $valor <= 0) {
            return ['tipo' => null, 'valor' => 0.0];
        }

        if (!in_array($tipoInformado, ['VALOR', 'PERCENTUAL'], true)) {
            throw new RuntimeException('Tipo de desconto do pedido invalido.');
        }

        if ($valor < 0) {
            throw new RuntimeException('Valor do desconto nao pode ser negativo.');
        }

        if ($tipoInformado === 'PERCENTUAL' && $valor > 100) {
            throw new RuntimeException('Desconto percentual do pedido nao pode ser maior que 100%.');
        }

        return ['tipo' => $tipoInformado, 'valor' => $valor];
    }

    private function calcularSubtotalItens(array $itens): float
    {
        $subtotal = 0.0;
        foreach ($itens as $item) {
            $subtotal += round((float)$item['quantidade'] * (float)$item['valor_unitario'], 4);
        }

        return round($subtotal, 4);
    }

    private function calcularTotalPedido(array $itens, array $descontoPedido): float
    {
        $subtotal = $this->calcularSubtotalItens($itens);
        $descontoAplicado = 0.0;
        $tipo = $descontoPedido['tipo'] ?? null;
        $valor = round((float)($descontoPedido['valor'] ?? 0), 2);

        if ($tipo === 'PERCENTUAL') {
            $descontoAplicado = round($subtotal * ($valor / 100), 4);
        } elseif ($tipo === 'VALOR') {
            $descontoAplicado = round($valor, 4);
        }

        if ($descontoAplicado > $subtotal) {
            throw new RuntimeException('O desconto nao pode ser maior que o subtotal dos itens.');
        }

        return round($subtotal - $descontoAplicado, 4);
    }

    private function validarItens(array $itens): void
    {
        if (empty($itens)) {
            throw new RuntimeException('O pedido deve conter ao menos um item.');
        }

        $linha = 0;
        foreach ($itens as $item) {
            $linha++;
            if ((float)($item['quantidade'] ?? 0) <= 0) {
                throw new RuntimeException("Item {$linha}: quantidade deve ser maior que zero.");
            }
            if ((float)($item['valor_unitario'] ?? 0) < 0) {
                throw new RuntimeException("Item {$linha}: valor_unitario nÃ£o pode ser negativo.");
            }
            if (empty($item['nome_produto']) && empty($item['produto_id'])) {
                throw new RuntimeException("Item {$linha}: informe produto_id ou nome_produto.");
            }
        }
    }

    private function validarTransicaoStatus(string $statusAtual, string $novoStatus): void
    {
        $statusAtual = strtoupper($statusAtual);
        $novoStatus = strtoupper($novoStatus);

        if (!in_array($novoStatus, Pedido::STATUS_VALIDOS, true)) {
            throw new RuntimeException('Status invÃ¡lido para pedido.');
        }

        if ($statusAtual === Pedido::STATUS_CANCELADO && $novoStatus !== Pedido::STATUS_CANCELADO) {
            throw new RuntimeException('Pedido cancelado nÃ£o pode voltar para outro status.');
        }

        if ($statusAtual === Pedido::STATUS_FATURADO && in_array($novoStatus, [Pedido::STATUS_RASCUNHO, Pedido::STATUS_PENDENTE], true)) {
            throw new RuntimeException('Pedido faturado nÃ£o pode retornar para status inicial.');
        }
    }

    private function ajustarEstoque(array $itens, string $operacao, ?string $pedidoId = null): void
    {
        if (empty($itens)) {
            return;
        }

        $tipo = match (strtoupper($operacao)) {
            'DEBITAR' => 'SAIDA',
            'CREDITAR' => 'ENTRADA',
            default => throw new RuntimeException('Operacao de estoque invalida.'),
        };

        $numeroPedido = null;
        if ($pedidoId !== null && $pedidoId !== '') {
            $pedido = $this->buscarPedidoPorId($pedidoId);
            $numeroPedido = $pedido?->numero ?? $pedidoId;
        }

        $observacao = 'Saida automatica vinculada ao pedido "' . ($numeroPedido ?? $pedidoId ?? '') . '".';
        if ($tipo === 'ENTRADA') {
            $observacao = 'Estorno do pedido "' . ($numeroPedido ?? $pedidoId ?? '') . '"';
        }

        (new EstoqueMovimentacaoService(
            $this->pdo,
            new EstoqueMovimentacaoRepository($this->pdo)
        ))->movimentarItens(
            $itens,
            $tipo,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
            'PEDIDO',
            'PEDIDO',
            $pedidoId,
            $observacao
        );
    }

    /**
     * Atualiza o status do pedido via Kanban (drag-and-drop).
     * Valida transiÃ§Ãµes permitidas e nÃ£o altera regras financeiras existentes.
     */
    public function atualizarStatusKanban(string $pedidoId, string $novoStatus): Pedido
    {
        $pedido = $this->buscarPedidoPorId($pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('Pedido nÃ£o encontrado.');
        }

        // Validar status vÃ¡lido
        if (!in_array($novoStatus, Pedido::STATUS_VALIDOS, true)) {
            throw new RuntimeException('Status invÃ¡lido: ' . $novoStatus);
        }

        // Se jÃ¡ estÃ¡ no status desejado, retornar sem fazer nada
        if ($pedido->status === $novoStatus) {
            return $pedido;
        }

        // Validar transiÃ§Ãµes permitidas
        $this->validarTransicaoKanban($pedido->status, $novoStatus);

        // AtualÃ³gica financeira)
        $sucesso = $this->pedidoRepository->atualizarStatusSimples($pedidoId, $novoStatus);

        if (!$sucesso) {
            throw new RuntimeException('Falha ao atualizar status do pedido.');
        }

        // Retornar pedido atualizado
        return $this->buscarPedidoPorId($pedidoId) ?? $pedido;
    }

    /**
     * Valida se a transiÃ§Ã£o de status Ã© permitida no Kanban.
     * Regras:
     * - CANCELADO e FATURADO nÃ£o podem ser movidos (somente via aÃ§Ãµes especÃ­ficas)
     * - Qualquer outro status pode ser movido livremente entre as colunas Kanban
     */
    private function validarTransicaoKanban(string $statusAtual, string $novoStatus): void
    {
        // FATURADO nÃ£o pode ser alterado via Kanban (exige estorno)
        if ($statusAtual === Pedido::STATUS_FATURADO) {
            throw new RuntimeException(
                'Pedidos faturados nÃ£o podem ter status alterado via Kanban. ' .
                'Use a aÃ§Ã£o "Estornar Faturamento" se necessÃ¡rio.'
            );
        }

        // CANCELADO nÃ£o pode ser alterado via Kanban
        if ($statusAtual === Pedido::STATUS_CANCELADO) {
            throw new RuntimeException('Pedidos cancelados nÃ£o podem ter status alterado.');
        }

        // NÃ£o pode mover PARA faturado via Kanban (exige aÃ§Ã£o especÃ­fica de faturamento)
        if ($novoStatus === Pedido::STATUS_FATURADO) {
            throw new RuntimeException(
                'NÃ£o Ã© possÃ­vel marcar como FATURADO via Kanban. ' .
                'Use a aÃ§Ã£o "Faturar Pedido" que gera a conta a receber automaticamente.'
            );
        }

        // NÃ£o pode mover PARA cancelado via Kanban (exige aÃ§Ã£o especÃ­fica)
        if ($novoStatus === Pedido::STATUS_CANCELADO) {
            throw new RuntimeException(
                'NÃ£o Ã© possÃ­vel cancelar via Kanban. ' .
                'Use a aÃ§Ã£o "Cancelar Pedido" que ajusta estoque automaticamente.'
            );
        }

        // Demais transiÃ§Ãµes sÃ£o permitidas:
        // RASCUNHO -> PENDENTE, EM_PROCESSO, APROVADO, CONCLUIDO
        // PENDENTE -> RASCUNHO, EM_PROCESSO, APROVADO, CONCLUIDO
        // EM_PROCESSO -> PENDENTE, APROVADO, CONCLUIDO
        // APROVADO -> PENDENTE, EM_PROCESSO, CONCLUIDO
        // CONCLUIDO -> PENDENTE, EM_PROCESSO, APROVADO
    }

}
