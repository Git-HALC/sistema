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
     * Fechamento às cegas do caixa (no PDV):
     *   - grava valores informados por forma em pdv_conferencia_itens
     *   - fecha o caixa (status=fechado, data_fechamento=NOW)
     *   - NÃO gera movimentações nem contas a receber
     *   - NÃO marca conferencia_concluida (isso ocorre na conferência gerencial)
     *
     * Bloqueia o fechamento se houver vendas origem='fluxo' em Kanban
     * (status pendente/em_processo/concluido). Essas vendas precisam ser
     * faturadas ou canceladas antes do fechamento.
     *
     * @param int $caixaId
     * @param int $usuarioId
     * @param array<int, float> $valoresInformados  key=forma_pagamento_id
     * @return array{ok:bool, erro:?string}
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
            return $this->falha('Apenas o operador que abriu o caixa pode fechá-lo.');
        }

        // Bloqueio: nenhuma venda pendente no fluxo
        $pendentes = $this->repo->contarVendasFluxoPendentes($caixaId);
        if ($pendentes > 0) {
            return $this->falha(sprintf(
                'Existem %d venda(s) em aberto no Fluxo (pendente/em processo/concluído). ' .
                'Fature ou cancele-as antes de fechar o caixa.',
                $pendentes
            ));
        }

        $formas = $this->repo->formasAtivas();
        $totaisSistema = $this->repo->totaisSistemaPorForma($caixaId);

        try {
            $this->pdo->beginTransaction();

            // Grava valores às cegas (sistema vs informado) para todas as formas ativas
            foreach ($formas as $f) {
                $fpId = $f['id'];
                $valorSistema = (float)($totaisSistema[$fpId] ?? 0.0);
                $valorInformado = (float)($valoresInformados[$fpId] ?? 0.0);
                $this->repo->upsertConferenciaItem($caixaId, $fpId, $valorSistema, $valorInformado);
            }

            // Fecha caixa (sem marcar conferencia_concluida)
            $this->repo->fecharCaixaBlind($caixaId, $usuarioId);

            $this->audit->registrar(
                'pdv', 'FECHAR_CAIXA_BLIND', 'pdv_caixas', $caixaId,
                'Fechamento às cegas do caixa. Conferência pendente.',
                ['caixa_id' => $caixaId, 'formas_registradas' => count($formas)]
            );

            $this->pdo->commit();
            return ['ok' => true, 'erro' => null];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->falha($e->getMessage());
        }
    }

    /**
     * Conferência efetiva (executada no módulo Gerencial por usuário com permissão):
     *   - valida que o caixa está fechado e ainda não foi conferido
     *   - gera movimentações/contas a receber para cada venda faturada
     *   - marca vendas como conferida=TRUE
     *   - marca pdv_caixas.conferencia_concluida=TRUE
     *
     * @return array{ok:bool, erro:?string, vendas_conferidas:int, movimentacoes:int, contas_receber:int}
     */
    public function conferirEGerarPostings(int $caixaId, int $usuarioConferenciaId): array
    {
        $caixa = $this->repo->caixa($caixaId);
        if ($caixa === null) {
            return $this->falhaDetalhada('Caixa não encontrado.');
        }
        if (($caixa['status'] ?? '') !== 'fechado') {
            return $this->falhaDetalhada('Caixa ainda não foi fechado às cegas pelo operador.');
        }
        if (!empty($caixa['conferencia_concluida'])) {
            return $this->falhaDetalhada('Caixa já foi conferido anteriormente.');
        }

        $vendas = $this->repo->vendasParaConferir($caixaId);

        // Pré-validação: toda forma usada precisa ter configuração coerente
        $errosConfig = $this->validarConfiguracaoFormas($vendas);
        if ($errosConfig !== []) {
            return $this->falhaDetalhada(
                'Conferência bloqueada. Corrija a configuração das formas de pagamento:' . "\n- "
                . implode("\n- ", $errosConfig)
            );
        }

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

                // R1 — RECEITA POR COMPETENCIA: lanca a receita em TODA venda,
                // independente da forma (dinheiro, cartao, a faturar, etc.).
                // afeta_dre=TRUE, afeta_saldo=FALSE (nao mexe banco aqui)
                $contaReceita = isset($v['conta_id']) && $v['conta_id'] !== '' ? (int)$v['conta_id'] : null;
                if ($contaReceita === null) {
                    // Precisa de alguma conta por FK; pega a primeira ativa
                    $contaReceita = (int)$this->pdo->query(
                        'SELECT id FROM contas WHERE ativo = TRUE ORDER BY id LIMIT 1'
                    )->fetchColumn();
                }
                $predominante = $this->repo->tipoItemPredominante((int)$v['id']);
                $this->repo->inserirReceitaCompetencia(
                    $contaReceita, $valor, 'Receita competencia — ' . $descricao,
                    $fpId, $predominante, (int)$v['id']
                );
                $contadorMov++;

                // R4 — DEDUCAO: desconto concedido na venda
                $desconto = $this->repo->descontoVenda((int)$v['id']);
                if ($desconto > 0.009) {
                    $this->repo->inserirDescontoConcedido(
                        $contaReceita, $desconto,
                        'Desconto concedido — ' . $descricao, $fpId
                    );
                    $contadorMov++;
                }

                if (in_array($tipo, self::TIPOS_RECEBIMENTO_DIRETO, true)) {
                    // Dinheiro / PIX / TB: entrada de caixa/banco (patrimonial apenas)
                    $contaId = isset($v['conta_id']) && $v['conta_id'] !== '' ? (int)$v['conta_id'] : null;
                    if ($contaId === null) {
                        throw new RuntimeException(
                            'Forma "' . $v['fp_nome'] . '" precisa ter uma Conta vinculada antes da conferência.'
                        );
                    }
                    $this->repo->inserirMovimentacaoEntrada($contaId, $valor, $descricao, $fpId);
                    $contadorMov++;
                } elseif (in_array($tipo, self::TIPOS_GERAM_CR, true)) {
                    // Regras de cobrança:
                    //  - CC/CD (cartão): titular do CR é a ADQUIRENTE (fp.adquirente_id)
                    //    com prazo de repasse da forma de pagamento (fp.prazo_dias).
                    //  - AF (a faturar): titular é o cliente da venda; prazo do cliente
                    //    (clientes.prazo_faturamento_dias); 0 = 30 dias automáticos.
                    //  - BOL: titular = cliente da venda; prazo = fp.prazo_dias.
                    $clienteVendaId = isset($v['cliente_id']) && $v['cliente_id'] !== ''
                        ? (int)$v['cliente_id'] : null;

                    $valorCR = $valor; // padrao: valor bruto
                    $observacoesCR = null;

                    if (in_array($tipo, ['CC', 'CD'], true)) {
                        $adquirenteId = isset($v['fp_adquirente_id']) && $v['fp_adquirente_id'] !== ''
                            ? (int)$v['fp_adquirente_id'] : null;
                        if ($adquirenteId === null) {
                            throw new RuntimeException(
                                'Forma "' . $v['fp_nome'] . '" (cartão) precisa ter Adquirente '
                                . 'cadastrada antes da conferência.'
                            );
                        }
                        $titularId = $adquirenteId;
                        $prazo = (int)($v['fp_prazo_dias'] ?? 0);

                        // R2 (adaptada): taxa sera lancada como Despesa Financeira no
                        // momento da BAIXA do CR (quando adquirente paga de fato).
                        // Aqui apenas gravamos no CR o valor liquido + metadado da taxa.
                        $taxaPercent = (float)($v['fp_taxa'] ?? 0);
                        if ($taxaPercent > 0) {
                            $valorTaxa = round($valor * ($taxaPercent / 100), 2);
                            $valorCR = round($valor - $valorTaxa, 2);
                            $observacoesCR = 'Taxa aplicada: '
                                . number_format($taxaPercent, 2, '.', '')
                                . '% (R$ ' . number_format($valorTaxa, 2, '.', '') . '). '
                                . 'Sera lancada como Despesa Financeira no momento da baixa.';
                        }
                    } elseif ($tipo === 'AF') {
                        if ($clienteVendaId === null) {
                            throw new RuntimeException(
                                'Venda #' . ((int)$v['numero']) . ' (A Faturar) sem cliente — '
                                . 'impossível gerar conta a receber.'
                            );
                        }
                        $titularId = $clienteVendaId;
                        $prazoCliente = (int)($v['cliente_prazo_faturamento'] ?? 0);
                        $prazo = $prazoCliente > 0 ? $prazoCliente : 30; // fallback 30
                    } else { // BOL
                        $titularId = $clienteVendaId;
                        $prazo = (int)($v['fp_prazo_dias'] ?? 0);
                    }

                    $this->repo->inserirContaReceberPdv(
                        $descricao,
                        $titularId,
                        $fpId,
                        $valorCR,
                        $prazo,
                        (int)$v['id'],
                        $observacoesCR
                    );
                    $contadorCR++;
                } else {
                    throw new RuntimeException('Tipo de pagamento "' . $tipo . '" não suportado.');
                }

                $this->repo->marcarVendaConferida((int)$v['id'], $caixaId);
                $contadorVendas++;
            }

            $this->repo->marcarConferenciaConcluida($caixaId, $usuarioConferenciaId);

            $this->audit->registrar(
                'pdv', 'CONFERIR_CAIXA_GERENCIAL', 'pdv_caixas', $caixaId,
                'Conferência gerencial concluída; lançamentos financeiros gerados.',
                [
                    'caixa_id' => $caixaId,
                    'usuario_conferencia_id' => $usuarioConferenciaId,
                    'vendas_conferidas' => $contadorVendas,
                    'movimentacoes_geradas' => $contadorMov,
                    'contas_receber_geradas' => $contadorCR,
                ]
            );

            $this->pdo->commit();
            return [
                'ok' => true, 'erro' => null,
                'vendas_conferidas' => $contadorVendas,
                'movimentacoes' => $contadorMov,
                'contas_receber' => $contadorCR,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->falhaDetalhada($e->getMessage());
        }
    }

    /**
     * Verifica, antes de abrir transação, se todas as formas usadas nas vendas
     * a conferir estão configuradas:
     *  - D/PIX/TB → exige conta_id (banco de recebimento)
     *  - CC/CD    → exige adquirente_id E conta_id
     *  - BOL      → exige conta_id
     *  - AF       → sem exigência aqui (prazo pelo cliente)
     *
     * @param array<int, array<string, mixed>> $vendas
     * @return array<int, string> lista de mensagens de erro (uma por forma)
     */
    private function validarConfiguracaoFormas(array $vendas): array
    {
        $erros = [];
        $vistos = [];
        foreach ($vendas as $v) {
            $fpId = (int)$v['forma_pagamento_id'];
            if (isset($vistos[$fpId])) {
                continue;
            }
            $vistos[$fpId] = true;

            $tipo = strtoupper((string)$v['fp_tipo']);
            $nome = (string)$v['fp_nome'];
            $temConta = isset($v['conta_id']) && $v['conta_id'] !== '' && $v['conta_id'] !== null;
            $temAdquirente = isset($v['fp_adquirente_id']) && $v['fp_adquirente_id'] !== '' && $v['fp_adquirente_id'] !== null;

            if (in_array($tipo, ['CC', 'CD'], true)) {
                if (!$temAdquirente) {
                    $erros[] = 'Forma "' . $nome . '" (cartão) sem Adquirente cadastrada.';
                }
                if (!$temConta) {
                    $erros[] = 'Forma "' . $nome . '" (cartão) sem Banco de recebimento.';
                }
            } elseif (in_array($tipo, ['D', 'PIX', 'TB', 'BOL'], true)) {
                if (!$temConta) {
                    $erros[] = 'Forma "' . $nome . '" sem Banco de recebimento.';
                }
            }
        }

        // AF (A Faturar) em venda sem cliente — bloqueia a conferência
        foreach ($vendas as $v) {
            $tipo = strtoupper((string)$v['fp_tipo']);
            $temCliente = isset($v['cliente_id']) && $v['cliente_id'] !== '' && $v['cliente_id'] !== null;
            if ($tipo === 'AF' && !$temCliente) {
                $erros[] = 'Venda #' . (int)$v['numero'] . ' (A Faturar) sem cliente — obrigatório informar antes de conferir.';
            }
        }

        return $erros;
    }

    /**
     * Lista vendas detalhadas do caixa para conferência venda-a-venda.
     * @return array<int, array<string, mixed>>
     */
    public function vendasDoCaixa(int $caixaId): array
    {
        return $this->repo->vendasDoCaixaDetalhado($caixaId);
    }

    /**
     * Altera a forma de pagamento de uma venda (uso gerencial).
     * Recalcula os totais de sistema em pdv_conferencia_itens.
     *
     * @return array{ok:bool, erro:?string}
     */
    public function trocarFormaPagamentoVenda(int $vendaId, int $novaFormaId, int $usuarioId): array
    {
        try {
            $tipoNovo = $this->repo->tipoFormaPagamento($novaFormaId);
            if ($tipoNovo === 'AF') {
                $temCliente = $this->repo->vendaTemCliente($vendaId);
                if (!$temCliente) {
                    return [
                        'ok' => false,
                        'erro' => 'Para alterar a forma para "A Faturar" é obrigatório informar o cliente da venda. '
                            . 'Cadastre o cliente na venda antes de trocar a forma de pagamento.',
                    ];
                }
            }

            $this->pdo->beginTransaction();

            $r = $this->repo->alterarFormaPagamentoVenda($vendaId, $novaFormaId);
            if (!$r['ok']) {
                $this->pdo->rollBack();
                return ['ok' => false, 'erro' => $r['erro']];
            }

            $this->repo->recalcularValorSistema((int)$r['caixa_id']);

            $this->audit->registrar(
                'pdv', 'ALTERAR_FORMA_PAGAMENTO', 'pdv_vendas', $vendaId,
                'Forma de pagamento da venda alterada (conferência gerencial).',
                ['venda_id' => $vendaId, 'nova_forma_id' => $novaFormaId, 'usuario_id' => $usuarioId]
            );

            $this->pdo->commit();
            return ['ok' => true, 'erro' => null];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    private function falhaDetalhada(string $msg): array
    {
        return [
            'ok' => false, 'erro' => $msg,
            'vendas_conferidas' => 0, 'movimentacoes' => 0, 'contas_receber' => 0,
        ];
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
