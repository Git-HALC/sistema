<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use App\Support\AuditLogger;
use PDO;
use RuntimeException;

final class PdvConferenciaService
{
    // Tipos de forma_pagamento que geram entrada direta em movimentacoes
    private const TIPOS_RECEBIMENTO_DIRETO = ['D', 'PIX', 'TB'];

    // Tipos que geram contas a receber (adquirente paga depois)
    private const TIPOS_GERAM_CR = ['CC', 'CD', 'BOL', 'AF'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PdvConferenciaRepository $repo,
        private readonly AuditLogger $audit
    ) {}

    /**
     * Monta os dados do step 1 / step 2:
     * - formas ativas
     * - totais calculados do sistema (apenas para step 2; step 1 esconde)
     *
     * @return array{formas:array, totaisSistema:array<int,float>}
     */
    public function prepararConferencia(int $caixaId): array
    {
        $formas = $this->repo->formasAtivas();
        $totaisSistema = $this->repo->totaisSistemaPorForma($caixaId);

        return ['formas' => $formas, 'totaisSistema' => $totaisSistema];
    }

    /**
     * Executa a conferência em uma única transação.
     *
     * @param int $caixaId
     * @param int $usuarioId
     * @param array<int, float> $valoresInformados  key=forma_pagamento_id, value=valor informado pelo operador
     * @return array{ok:bool, erro:?string, vendas_conferidas:int, movimentacoes:int, contas_receber:int}
     */
    public function confirmar(int $caixaId, int $usuarioId, array $valoresInformados): array
    {
        $caixa = $this->repo->caixa($caixaId);
        if ($caixa === null) {
            return $this->falha('Caixa não encontrado.');
        }
        if (($caixa['status'] ?? '') !== 'aberto') {
            return $this->falha('Caixa já está fechado.');
        }
        if ((int)($caixa['usuario_abertura_id'] ?? 0) !== $usuarioId) {
            return $this->falha('Apenas o operador que abriu o caixa pode conferi-lo.');
        }

        $formas = $this->repo->formasAtivas();
        $formasPorId = [];
        foreach ($formas as $f) {
            $formasPorId[$f['id']] = $f;
        }

        $totaisSistema = $this->repo->totaisSistemaPorForma($caixaId);
        $vendas = $this->repo->vendasParaConferir($caixaId);

        $contadorMov = 0;
        $contadorCR = 0;
        $contadorVendas = 0;

        try {
            $this->pdo->beginTransaction();

            foreach ($vendas as $v) {
                $tipo = (string)$v['fp_tipo'];
                $numero = (int)$v['numero'];
                $fpId = (int)$v['forma_pagamento_id'];
                $valor = (float)$v['valor_total'];
                $descricao = 'Venda PDV #' . $numero;

                if (in_array($tipo, self::TIPOS_RECEBIMENTO_DIRETO, true)) {
                    $contaId = isset($v['conta_id']) && $v['conta_id'] !== '' ? (int)$v['conta_id'] : null;
                    if ($contaId === null) {
                        throw new RuntimeException(
                            'Forma "' . $v['fp_nome'] . '" precisa ter uma Conta vinculada antes da conferência.'
                        );
                    }
                    $this->repo->inserirMovimentacaoEntrada($contaId, $valor, $descricao, $fpId);
                    $contadorMov++;
                } elseif (in_array($tipo, self::TIPOS_GERAM_CR, true)) {
                    $prazo = (int)($v['prazo_dias'] ?? 0);
                    $clienteId = isset($v['cliente_id']) && $v['cliente_id'] !== '' ? (int)$v['cliente_id'] : null;
                    $this->repo->inserirContaReceberPdv(
                        $descricao,
                        $clienteId,
                        $fpId,
                        $valor,
                        $prazo,
                        (int)$v['id']
                    );
                    $contadorCR++;
                } else {
                    throw new RuntimeException(
                        'Tipo de pagamento "' . $tipo . '" não suportado na conferência.'
                    );
                }

                $this->repo->marcarVendaConferida((int)$v['id'], $caixaId);
                $contadorVendas++;
            }

            // Gravar itens da conferência (sistema vs informado) para todas as formas ativas
            foreach ($formas as $f) {
                $fpId = $f['id'];
                $valorSistema = (float)($totaisSistema[$fpId] ?? 0.0);
                $valorInformado = (float)($valoresInformados[$fpId] ?? 0.0);
                $this->repo->upsertConferenciaItem($caixaId, $fpId, $valorSistema, $valorInformado);
            }

            // Fecha o caixa
            $this->repo->finalizarCaixa($caixaId, $usuarioId);

            $this->audit->registrar(
                'pdv',
                'CONFERIR_CAIXA',
                'pdv_caixas',
                $caixaId,
                'Conferência de caixa concluída.',
                [
                    'caixa_id' => $caixaId,
                    'vendas_conferidas' => $contadorVendas,
                    'movimentacoes_geradas' => $contadorMov,
                    'contas_receber_geradas' => $contadorCR,
                ]
            );

            $this->pdo->commit();

            return [
                'ok' => true,
                'erro' => null,
                'vendas_conferidas' => $contadorVendas,
                'movimentacoes' => $contadorMov,
                'contas_receber' => $contadorCR,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->falha($e->getMessage());
        }
    }

    private function falha(string $msg): array
    {
        return [
            'ok' => false,
            'erro' => $msg,
            'vendas_conferidas' => 0,
            'movimentacoes' => 0,
            'contas_receber' => 0,
        ];
    }
}
