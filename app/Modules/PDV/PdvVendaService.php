<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Support\AuditLogger;
use PDO;
use RuntimeException;

final class PdvVendaService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvVendaRepository $repo,
        private readonly AuditLogger $audit
    ) {}

    /**
     * @param array{caixa_id:int, usuario_id:int, cliente_id:?int, forma_pagamento_id:int,
     *   desconto_tipo:?string, desconto_valor:float, observacoes:?string,
     *   itens: array<int, array<string,mixed>>} $payload
     * @return array{ok:bool, id:?int, numero:?int, erros:string[]}
     */
    public function finalizar(array $payload): array
    {
        $erros = [];
        $modoRaw = (string)($payload['modo'] ?? 'pago_agora');
        $modo = in_array($modoRaw, ['pago_agora', 'cobrar_depois'], true) ? $modoRaw : 'pago_agora';

        if (empty($payload['caixa_id'])) {
            $erros[] = 'Caixa não informado.';
        }
        if (empty($payload['usuario_id'])) {
            $erros[] = 'Usuário não identificado.';
        }
        if ($modo === 'pago_agora' && empty($payload['forma_pagamento_id'])) {
            $erros[] = 'Forma de pagamento obrigatória.';
        }
        if (empty($payload['itens']) || !is_array($payload['itens'])) {
            $erros[] = 'Adicione ao menos 1 item.';
        }

        if ($erros) {
            return ['ok' => false, 'id' => null, 'numero' => null, 'erros' => $erros];
        }

        $itensNormalizados = [];
        $subtotal = 0.0;
        foreach ($payload['itens'] as $raw) {
            $tipo = strtoupper((string)($raw['tipo_item'] ?? ''));
            if (!in_array($tipo, ['PRODUTO', 'SERVICO'], true)) {
                $erros[] = 'Tipo de item inválido.';
                continue;
            }
            $qtd = (float)($raw['quantidade'] ?? 0);
            $vu = (float)($raw['valor_unitario'] ?? 0);
            $nome = trim((string)($raw['nome_item'] ?? ''));

            if ($qtd <= 0) {
                $erros[] = 'Quantidade deve ser > 0 (item: ' . $nome . ').';
                continue;
            }
            if ($vu < 0) {
                $erros[] = 'Valor unitário inválido (item: ' . $nome . ').';
                continue;
            }
            if ($nome === '') {
                $erros[] = 'Item sem nome.';
                continue;
            }

            $vt = round($qtd * $vu, 4);
            $subtotal += $vt;

            $itensNormalizados[] = [
                'tipo_item' => $tipo,
                'produto_id' => $tipo === 'PRODUTO' && !empty($raw['produto_id']) ? (int)$raw['produto_id'] : null,
                'servico_id' => $tipo === 'SERVICO' && !empty($raw['servico_id']) ? (int)$raw['servico_id'] : null,
                'nome_item' => mb_substr($nome, 0, 255),
                'quantidade' => $qtd,
                'valor_unitario' => $vu,
                'valor_total_item' => $vt,
            ];
        }

        if ($erros) {
            return ['ok' => false, 'id' => null, 'numero' => null, 'erros' => $erros];
        }

        $descontoTipo = in_array($payload['desconto_tipo'] ?? null, ['VALOR', 'PERCENTUAL'], true)
            ? (string)$payload['desconto_tipo'] : null;
        $descontoValor = max(0.0, (float)($payload['desconto_valor'] ?? 0));
        $total = $this->aplicarDesconto($subtotal, $descontoTipo, $descontoValor);

        $venda = new PdvVenda();
        $venda->caixa_id = (int)$payload['caixa_id'];
        $venda->usuario_id = (int)$payload['usuario_id'];
        $venda->cliente_id = !empty($payload['cliente_id']) ? (int)$payload['cliente_id'] : null;
        $venda->forma_pagamento_id = $modo === 'pago_agora' ? (int)$payload['forma_pagamento_id'] : null;
        $venda->desconto_tipo = $descontoTipo;
        $venda->desconto_valor = $descontoValor;
        $venda->valor_total = $total;
        $venda->observacoes = !empty($payload['observacoes']) ? (string)$payload['observacoes'] : null;
        $venda->origem = $modo === 'pago_agora' ? 'rapida' : 'fluxo';
        $venda->status = $modo === 'pago_agora' ? 'faturado' : 'pendente';

        try {
            $this->pdo->beginTransaction();
            $id = $this->repo->criar($venda, $itensNormalizados);

            // Recupera numero gerado por BIGSERIAL
            $stmt = $this->pdo->prepare('SELECT numero FROM pdv_vendas WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $numero = (int)$stmt->fetchColumn();

            $this->audit->registrar(
                'pdv',
                'VENDA_FATURAR',
                'pdv_vendas',
                $id,
                'Venda rápida #' . $numero . ' finalizada.',
                [
                    'venda_id' => $id,
                    'numero' => $numero,
                    'total' => $total,
                    'qtd_itens' => count($itensNormalizados),
                    'forma_pagamento_id' => $venda->forma_pagamento_id,
                ]
            );

            $this->pdo->commit();
            return ['ok' => true, 'id' => $id, 'numero' => $numero, 'erros' => []];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'id' => null, 'numero' => null, 'erros' => [$e->getMessage()]];
        }
    }

    /**
     * Transiciona o status de uma venda do Kanban.
     * FATURADO exige forma_pagamento_id.
     *
     * @return array{ok:bool, erro:?string}
     */
    public function moverStatus(int $vendaId, string $novoStatus, int $usuarioId, ?int $formaPagamentoId = null): array
    {
        $fluxo = ['pendente' => 1, 'em_processo' => 2, 'concluido' => 3, 'faturado' => 4];
        if (!isset($fluxo[$novoStatus])) {
            return ['ok' => false, 'erro' => 'Status inválido.'];
        }

        if ($novoStatus === 'faturado' && $formaPagamentoId === null) {
            return ['ok' => false, 'erro' => 'Forma de pagamento obrigatória para faturar.'];
        }

        $r = $this->repo->moverStatus($vendaId, $novoStatus, $formaPagamentoId);
        if (!$r['ok']) {
            $erro = match ($r['erro']) {
                'nao_encontrada' => 'Venda não encontrada.',
                'caixa_fechado'  => 'Caixa fechado — impossível alterar.',
                'cancelada'      => 'Venda cancelada não pode ser alterada.',
                'transicao_invalida' => 'Transição inválida (só avança no fluxo).',
                default          => 'Falha ao atualizar status.',
            };
            return ['ok' => false, 'erro' => $erro];
        }

        $this->audit->registrar(
            'pdv', 'VENDA_STATUS', 'pdv_vendas', $vendaId,
            'Venda movida para ' . strtoupper($novoStatus),
            ['venda_id' => $vendaId, 'status' => $novoStatus, 'usuario_id' => $usuarioId]
        );

        return ['ok' => true, 'erro' => null];
    }

    /**
     * @return array{ok:bool, erro:?string}
     */
    public function cancelar(int $vendaId, int $usuarioId, ?string $motivo): array
    {
        $r = $this->repo->cancelarVenda($vendaId, $usuarioId, $motivo);
        if (!$r['ok']) {
            $erro = match ($r['status_caixa']) {
                'inexistente' => 'Venda não encontrada.',
                'fechado'     => 'Caixa já fechado — não é possível cancelar esta venda.',
                'aberto'      => 'Venda já cancelada ou não pôde ser cancelada.',
                default       => 'Não foi possível cancelar a venda.',
            };
            return ['ok' => false, 'erro' => $erro];
        }

        $this->audit->registrar(
            'pdv',
            'VENDA_CANCELAR',
            'pdv_vendas',
            $vendaId,
            'Venda PDV cancelada.',
            ['venda_id' => $vendaId, 'motivo' => $motivo]
        );

        return ['ok' => true, 'erro' => null];
    }

    private function aplicarDesconto(float $subtotal, ?string $tipo, float $valor): float
    {
        if ($tipo === null || $valor <= 0) {
            return round($subtotal, 4);
        }
        if ($tipo === 'PERCENTUAL') {
            $valor = min(100.0, $valor);
            return round($subtotal * (1 - $valor / 100), 4);
        }
        return round(max(0.0, $subtotal - $valor), 4);
    }
}
