<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Security\PdvPermissao;
use App\Support\AuditLogger;
use PDO;
use RuntimeException;

final class PdvService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvRepository $repository,
        private readonly PdvPermissao $permissao,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function abrirCaixa(int $usuarioId, string $senhaConfirmacao, float $valorSuprimento): array
    {
        if (!$this->permissao->podeAbrirCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para abrir caixa.');
        }

        if ($this->permissao->temCaixaAberto($usuarioId)) {
            throw new RuntimeException('Você já possui um caixa aberto.');
        }

        $usuario = $this->repository->buscarUsuario($usuarioId);
        if ($usuario === null || !password_verify($senhaConfirmacao, (string) ($usuario['senha'] ?? ''))) {
            throw new RuntimeException('Confirmação de senha inválida.');
        }

        $numeroCaixa = $this->repository->proximoNumeroCaixa();
        $caixaId = $this->repository->criarCaixa($usuarioId, $numeroCaixa, $valorSuprimento);

        $this->auditLogger->registrar(
            'pdv',
            'ABRIR_CAIXA',
            'pdv_caixas',
            $caixaId,
            'Caixa aberto no PDV.',
            [
                'caixa_id' => $caixaId,
                'numero_caixa' => $numeroCaixa,
                'valor_suprimento' => round($valorSuprimento, 2),
            ]
        );

        $caixa = $this->repository->buscarCaixa($caixaId);
        if ($caixa === null) {
            throw new RuntimeException('Caixa aberto, mas não foi possível recuperar os dados do caixa.');
        }

        return $caixa;
    }

    public function fecharCaixa(
        int $caixaId,
        int $usuarioId,
        array $fechamento,
        bool $forcar = false,
        ?string $observacao = null
    ): array {
        $caixa = $this->repository->buscarCaixa($caixaId);
        if ($caixa === null) {
            throw new RuntimeException('Caixa não encontrado.');
        }

        if (($caixa['status'] ?? '') !== 'aberto') {
            throw new RuntimeException('O caixa informado já está fechado.');
        }

        if (!$forcar && !$this->permissao->podeFecharCaixa($usuarioId, $caixaId)) {
            throw new RuntimeException('Sem permissão para fechar este caixa.');
        }

        $digitado = [
            'dinheiro' => $this->normalizarValor($fechamento['dinheiro'] ?? 0),
            'cartao' => $this->normalizarValor($fechamento['cartao'] ?? 0),
            'pix' => $this->normalizarValor($fechamento['pix'] ?? 0),
            'a_faturar' => $this->normalizarValor($fechamento['a_faturar'] ?? 0),
        ];

        $sistema = $this->repository->totaisSistemaPorForma($caixaId);
        $diferencas = [
            'dinheiro' => round($digitado['dinheiro'] - $sistema['dinheiro'], 2),
            'cartao' => round($digitado['cartao'] - $sistema['cartao'], 2),
            'pix' => round($digitado['pix'] - $sistema['pix'], 2),
            'a_faturar' => round($digitado['a_faturar'] - $sistema['a_faturar'], 2),
        ];

        $totalSistema = array_sum($sistema);
        $totalDigitado = array_sum($digitado);
        $diferencaTotal = round($totalDigitado - $totalSistema, 2);

        $this->repository->atualizarFechamentoCaixa($caixaId, [
            'status' => 'fechado',
            'data_fechamento' => date('Y-m-d H:i:s'),
            'fechamento_dinheiro' => $digitado['dinheiro'],
            'fechamento_cartao' => $digitado['cartao'],
            'fechamento_pix' => $digitado['pix'],
            'fechamento_faturar' => $digitado['a_faturar'],
            'sistema_dinheiro' => round($sistema['dinheiro'], 2),
            'sistema_cartao' => round($sistema['cartao'], 2),
            'sistema_pix' => round($sistema['pix'], 2),
            'sistema_faturar' => round($sistema['a_faturar'], 2),
            'diferenca_dinheiro' => $diferencas['dinheiro'],
            'diferenca_cartao' => $diferencas['cartao'],
            'diferenca_pix' => $diferencas['pix'],
            'diferenca_faturar' => $diferencas['a_faturar'],
            'diferenca_total' => $diferencaTotal,
            'observacao' => $observacao,
        ]);

        $descricao = $forcar ? 'Caixa fechado à força.' : 'Caixa fechado no PDV.';
        $this->auditLogger->registrar(
            'pdv',
            $forcar ? 'FORCAR_FECHAMENTO' : 'FECHAR_CAIXA',
            'pdv_caixas',
            $caixaId,
            $descricao,
            [
                'caixa_id' => $caixaId,
                'total_sistema' => round($totalSistema, 2),
                'total_digitado' => round($totalDigitado, 2),
                'diferenca_total' => $diferencaTotal,
            ]
        );

        $fechado = $this->repository->buscarCaixa($caixaId);
        if ($fechado === null) {
            throw new RuntimeException('Caixa fechado, mas não foi possível recuperar os dados atualizados.');
        }

        return $fechado;
    }

    public function registrarLancamentoPedido(string $pedidoId, int $caixaId, string $formaPagamento, int $usuarioId): void
    {
        $pedido = $this->buscarPedido($pedidoId);
        if ($pedido === null) {
            throw new RuntimeException('Pedido não encontrado para lançamento no caixa.');
        }

        $this->registrarLancamento(
            $caixaId,
            $usuarioId,
            'pedido',
            $pedidoId,
            (float) ($pedido['valor_total'] ?? 0),
            $formaPagamento,
            (string) ($pedido['numero'] ?? $pedidoId)
        );
    }

    public function registrarLancamentoServico(string $servicoId, int $caixaId, string $formaPagamento, int $usuarioId): void
    {
        $servico = $this->buscarServico($servicoId);
        if ($servico === null) {
            throw new RuntimeException('Serviço não encontrado para lançamento no caixa.');
        }

        $this->registrarLancamento(
            $caixaId,
            $usuarioId,
            'servico',
            $servicoId,
            (float) ($servico['valor_total'] ?? 0),
            $formaPagamento,
            (string) ($servico['numero'] ?? $servicoId)
        );
    }

    public function getFormaPagamentoFinanceiraId(string $formaPagamento): int
    {
        $forma = $this->repository->buscarFormaPagamentoFinanceiraPorGrupo($formaPagamento);
        if ($forma === null) {
            throw new RuntimeException('Não existe forma de pagamento financeira ativa compatível com o PDV.');
        }

        return (int) ($forma['id'] ?? 0);
    }

    public function obterDadosRelatorio(int $caixaId): array
    {
        $caixa = $this->repository->buscarCaixa($caixaId);
        if ($caixa === null) {
            throw new RuntimeException('Caixa não encontrado.');
        }

        $lancamentos = $this->repository->listarLancamentosDetalhados($caixaId);

        // Agrega pdv_conferencia_itens (fluxo novo às cegas) por bucket visual:
        // D → dinheiro | CC/CD → cartao | PIX → pix | AF/BOL/TB → a_faturar
        $buckets = ['dinheiro' => 'D', 'cartao' => ['CC', 'CD'], 'pix' => 'PIX', 'a_faturar' => ['AF', 'BOL', 'TB']];
        $sistema = ['dinheiro' => 0.0, 'cartao' => 0.0, 'pix' => 0.0, 'a_faturar' => 0.0];
        $operador = ['dinheiro' => 0.0, 'cartao' => 0.0, 'pix' => 0.0, 'a_faturar' => 0.0];
        $diferencas = ['dinheiro' => 0.0, 'cartao' => 0.0, 'pix' => 0.0, 'a_faturar' => 0.0];

        $stmt = $this->repository->pdo()->prepare(
            "SELECT fp.tipo, ci.valor_sistema, ci.valor_informado, ci.diferenca
               FROM pdv_conferencia_itens ci
               INNER JOIN formas_pagamento fp ON fp.id = ci.forma_pagamento_id
              WHERE ci.caixa_id = :id"
        );
        $stmt->execute([':id' => $caixaId]);
        $temConferencia = false;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
            $temConferencia = true;
            $tipo = (string)$r['tipo'];
            $key = null;
            foreach ($buckets as $bucketKey => $tipos) {
                $tiposArr = is_array($tipos) ? $tipos : [$tipos];
                if (in_array($tipo, $tiposArr, true)) { $key = $bucketKey; break; }
            }
            if ($key === null) continue;
            $sistema[$key] += (float)$r['valor_sistema'];
            $operador[$key] += (float)$r['valor_informado'];
            $diferencas[$key] += (float)$r['diferenca'];
        }

        // Se ainda nao ha conferencia, calcula SISTEMA ao vivo a partir das vendas
        // do caixa (operador fica zero ate o fechamento as cegas).
        if (!$temConferencia) {
            $stmtAoVivo = $this->repository->pdo()->prepare(
                "SELECT fp.tipo, COALESCE(SUM(v.valor_total), 0) AS total
                   FROM pdv_vendas v
                   INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
                  WHERE v.caixa_id = :id AND v.status = 'faturado'
                  GROUP BY fp.tipo"
            );
            $stmtAoVivo->execute([':id' => $caixaId]);
            foreach ($stmtAoVivo->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
                $tipo = (string)$r['tipo'];
                foreach ($buckets as $bucketKey => $tipos) {
                    $tiposArr = is_array($tipos) ? $tipos : [$tipos];
                    if (in_array($tipo, $tiposArr, true)) {
                        $sistema[$bucketKey] += (float)$r['total'];
                        break;
                    }
                }
            }
        }

        $totalSistema = round(array_sum($sistema), 2);
        $totalOperador = round(array_sum($operador), 2);

        return [
            'caixa' => $caixa,
            'lancamentos' => $lancamentos,
            'sistema' => $sistema,
            'operador' => $operador,
            'diferencas' => $diferencas,
            'total_sistema' => $totalSistema,
            'total_operador' => $totalOperador,
            'diferenca_total' => $temConferencia
                ? round($totalOperador - $totalSistema, 2)
                : (float)($caixa['diferenca_total'] ?? 0),
        ];
    }

    public function listarCaixasGerenciais(array $filtros): array
    {
        return $this->repository->listarCaixasGerenciais($filtros);
    }

    public function dashboardConferenciaHoje(): array
    {
        return $this->repository->dashboardConferenciaHoje();
    }

    public function faturamentoPorCaixaHoje(): array
    {
        return $this->repository->faturamentoPorCaixaHoje();
    }

    public function salvarObservacao(int $caixaId, string $observacao): void
    {
        $this->repository->salvarObservacao($caixaId, $observacao);
        $this->auditLogger->registrar(
            'pdv',
            'OBSERVAR_CAIXA',
            'pdv_caixas',
            $caixaId,
            'Observação gerencial atualizada.',
            ['observacao' => trim($observacao)]
        );
    }

    private function registrarLancamento(
        int $caixaId,
        int $usuarioId,
        string $tipo,
        string $referenciaId,
        float $valorTotal,
        string $formaPagamento,
        string $numeroReferencia
    ): void {
        if (!$this->permissao->podeLancarNoCaixa($usuarioId)) {
            throw new RuntimeException('Sem permissão para lançar no caixa.');
        }

        if (!$this->repository->caixaEstaAberto($caixaId)) {
            throw new RuntimeException('O caixa selecionado não está aberto.');
        }

        if (!in_array($formaPagamento, ['dinheiro', 'cartao', 'pix', 'a_faturar'], true)) {
            throw new RuntimeException('Forma de pagamento do PDV inválida.');
        }

        $this->repository->inserirOuAtualizarLancamento(
            $caixaId,
            $usuarioId,
            $tipo,
            $referenciaId,
            $valorTotal,
            $formaPagamento
        );

        $this->auditLogger->registrar(
            'pdv',
            'LANCAR_' . strtoupper($tipo),
            'pdv_lancamentos',
            $caixaId,
            'Lançamento registrado no caixa.',
            [
                'caixa_id' => $caixaId,
                'tipo' => $tipo,
                'referencia_id' => $referenciaId,
                'numero_referencia' => $numeroReferencia,
                'forma_pagamento' => $formaPagamento,
                'valor_total' => round($valorTotal, 2),
            ]
        );
    }

    private function buscarPedido(string $pedidoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, numero, valor_total
               FROM pedidos
              WHERE id = CAST(:id AS uuid)
              LIMIT 1'
        );
        $stmt->execute([':id' => $pedidoId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function buscarServico(string $servicoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, numero, valor_total
               FROM servicos
              WHERE id = CAST(:id AS uuid)
              LIMIT 1'
        );
        $stmt->execute([':id' => $servicoId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function normalizarValor(mixed $valor): float
    {
        return round((float) str_replace(',', '.', (string) $valor), 2);
    }
}
