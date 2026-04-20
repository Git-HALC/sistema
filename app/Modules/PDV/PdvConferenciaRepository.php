<?php
declare(strict_types=1);

namespace App\Modules\PDV;

use PDO;

final class PdvConferenciaRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Formas de pagamento ativas com dados necessários para a conferência.
     * @return array<int, array{id:int, nome:string, tipo:string, conta_id:?int, prazo_dias:int, taxa:float, ativo:bool}>
     */
    public function formasAtivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nome, tipo, conta_id, COALESCE(prazo_dias, 0) AS prazo_dias, COALESCE(taxa, 0) AS taxa, ativo
               FROM formas_pagamento
              WHERE ativo = TRUE
              ORDER BY nome"
        );
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $rows[] = [
                'id' => (int)$r['id'],
                'nome' => (string)$r['nome'],
                'tipo' => (string)$r['tipo'],
                'conta_id' => isset($r['conta_id']) && $r['conta_id'] !== '' ? (int)$r['conta_id'] : null,
                'prazo_dias' => (int)$r['prazo_dias'],
                'taxa' => (float)$r['taxa'],
                'ativo' => !empty($r['ativo']),
            ];
        }
        return $rows;
    }

    /**
     * Totais do sistema por forma de pagamento (apenas vendas faturadas não conferidas).
     * @return array<int, float>  key = forma_pagamento_id
     */
    public function totaisSistemaPorForma(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT forma_pagamento_id, COALESCE(SUM(valor_total), 0) AS total
               FROM pdv_vendas
              WHERE caixa_id = :caixa_id
                AND status = 'faturado'
                AND conferida = FALSE
                AND forma_pagamento_id IS NOT NULL
           GROUP BY forma_pagamento_id"
        );
        $stmt->execute([':caixa_id' => $caixaId]);

        $totais = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $totais[(int)$r['forma_pagamento_id']] = (float)$r['total'];
        }
        return $totais;
    }

    /**
     * Vendas faturadas ainda não conferidas do caixa.
     * @return array<int, array<string, mixed>>
     */
    public function vendasParaConferir(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.numero, v.valor_total, v.cliente_id, v.forma_pagamento_id,
                    fp.tipo AS fp_tipo, fp.nome AS fp_nome, fp.conta_id,
                    fp.prazo_dias AS fp_prazo_dias, fp.adquirente_id AS fp_adquirente_id,
                    COALESCE(fp.taxa, 0) AS fp_taxa,
                    COALESCE(cli.prazo_faturamento_dias, 0) AS cliente_prazo_faturamento
               FROM pdv_vendas v
               INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
              WHERE v.caixa_id = :caixa_id
                AND v.status = 'faturado'
                AND v.conferida = FALSE
              ORDER BY v.id"
        );
        $stmt->execute([':caixa_id' => $caixaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lanca a RECEITA (competencia) de uma venda PDV.
     * afeta_dre=TRUE, afeta_saldo=FALSE — resultado sem mover banco.
     */
    public function inserirReceitaCompetencia(
        int $contaId,
        float $valor,
        string $descricao,
        int $formaPagamentoId,
        string $tipoItemPredominante,
        ?int $vendaId = null
    ): void {
        $codigo = strtoupper($tipoItemPredominante) === 'SERVICO' ? '02' : '01';
        $catId = $this->resolverCategoria($codigo);
        // ON CONFLICT (pdv_venda_id, tipo_origem) DO NOTHING — idempotente.
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 categoria_dre_id, forma_pagamento_id, pdv_venda_id, tipo_origem,
                 protegido, afeta_saldo, afeta_dre)
             VALUES
                (:conta_id, 'Entrada', :valor, CURRENT_TIMESTAMP, :descricao,
                 :cat, :fp, :venda, 'VENDA_COMPETENCIA',
                 TRUE, FALSE, TRUE)
             ON CONFLICT (pdv_venda_id, tipo_origem)
             WHERE pdv_venda_id IS NOT NULL AND tipo_origem = 'VENDA_COMPETENCIA'
             DO NOTHING"
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':valor' => $valor,
            ':descricao' => $descricao,
            ':cat' => $catId,
            ':fp' => $formaPagamentoId,
            ':venda' => $vendaId,
        ]);
    }

    /**
     * Entrada patrimonial (banco/caixa). afeta_saldo=TRUE, afeta_dre=FALSE.
     * Usada para vendas dinheiro/PIX/TB — a receita correspondente sai em inserirReceitaCompetencia.
     */
    public function inserirMovimentacaoEntrada(
        int $contaId,
        float $valor,
        string $descricao,
        int $formaPagamentoId
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 forma_pagamento_id, tipo_origem, protegido, afeta_saldo, afeta_dre)
             VALUES
                (:conta_id, 'Entrada', :valor, CURRENT_TIMESTAMP, :descricao,
                 :fp, 'RECEBIMENTO', TRUE, TRUE, FALSE)"
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':valor' => $valor,
            ':descricao' => $descricao,
            ':fp' => $formaPagamentoId,
        ]);
    }

    /**
     * Lanca a TAXA do cartao como Despesa Financeira.
     * afeta_dre=TRUE, afeta_saldo=FALSE (adquirente ja retem a taxa).
     */
    public function inserirTaxaCartao(
        int $contaId,
        float $valorTaxa,
        string $descricao,
        int $formaPagamentoId
    ): void {
        $catId = $this->resolverCategoria('51');
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 categoria_dre_id, forma_pagamento_id, tipo_origem,
                 protegido, afeta_saldo, afeta_dre)
             VALUES
                (:conta_id, 'Saida', :valor, CURRENT_TIMESTAMP, :descricao,
                 :cat, :fp, 'TAXA_CARTAO',
                 TRUE, FALSE, TRUE)"
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':valor' => $valorTaxa,
            ':descricao' => $descricao,
            ':cat' => $catId,
            ':fp' => $formaPagamentoId,
        ]);
    }

    /** Lanca um desconto concedido como Deducao da receita. */
    public function inserirDescontoConcedido(
        int $contaId,
        float $valor,
        string $descricao,
        int $formaPagamentoId
    ): void {
        $catId = $this->resolverCategoria('41');
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 categoria_dre_id, forma_pagamento_id, tipo_origem,
                 protegido, afeta_saldo, afeta_dre)
             VALUES
                (:conta_id, 'Saida', :valor, CURRENT_TIMESTAMP, :descricao,
                 :cat, :fp, 'DEDUCAO',
                 TRUE, FALSE, TRUE)"
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':valor' => $valor,
            ':descricao' => $descricao,
            ':cat' => $catId,
            ':fp' => $formaPagamentoId,
        ]);
    }

    private function resolverCategoria(string $codigo): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categorias_dre WHERE codigo = :c LIMIT 1');
        $stmt->execute([':c' => $codigo]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    public function inserirContaReceberPdv(
        string $descricao,
        ?int $clienteId,
        int $formaPagamentoId,
        float $valor,
        int $prazoDias,
        int $pdvVendaId,
        ?string $observacoes = null
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contas_receber
                (descricao, cliente_id, forma_pagamento_id, valor,
                 data_vencimento, status, origem, protegido, pdv_venda_id, observacoes)
             VALUES
                (:descricao, :cliente_id, :fp, :valor,
                 CURRENT_DATE + (:prazo || ' days')::INTERVAL, 'PENDENTE', 'PDV', TRUE, :venda_id, :obs)"
        );
        $stmt->execute([
            ':descricao' => $descricao,
            ':cliente_id' => $clienteId,
            ':fp' => $formaPagamentoId,
            ':valor' => $valor,
            ':prazo' => max(0, $prazoDias),
            ':venda_id' => $pdvVendaId,
            ':obs' => $observacoes,
        ]);
    }

    public function marcarVendaConferida(int $vendaId, int $caixaId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pdv_vendas
                SET conferida = TRUE, caixa_conferencia_id = :caixa_id
              WHERE id = :id AND conferida = FALSE"
        );
        $stmt->execute([':caixa_id' => $caixaId, ':id' => $vendaId]);
    }

    public function upsertConferenciaItem(
        int $caixaId,
        int $formaPagamentoId,
        float $valorSistema,
        float $valorInformado
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO pdv_conferencia_itens
                (caixa_id, forma_pagamento_id, valor_sistema, valor_informado, conferido_em)
             VALUES
                (:caixa_id, :fp, :vs, :vi, CURRENT_TIMESTAMP)
             ON CONFLICT (caixa_id, forma_pagamento_id) DO UPDATE
                SET valor_sistema = EXCLUDED.valor_sistema,
                    valor_informado = EXCLUDED.valor_informado,
                    conferido_em = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            ':caixa_id' => $caixaId,
            ':fp' => $formaPagamentoId,
            ':vs' => $valorSistema,
            ':vi' => $valorInformado,
        ]);
    }

    public function finalizarCaixa(int $caixaId, int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pdv_caixas
                SET status = 'fechado',
                    conferencia_concluida = TRUE,
                    conferencia_em = CURRENT_TIMESTAMP,
                    conferencia_usuario_id = :uid,
                    data_fechamento = CURRENT_TIMESTAMP
              WHERE id = :id AND status = 'aberto'"
        );
        $stmt->execute([':id' => $caixaId, ':uid' => $usuarioId]);
    }

    /** Fechamento as cegas: apenas fecha caixa, conferencia_concluida permanece FALSE. */
    public function fecharCaixaBlind(int $caixaId, int $operadorId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pdv_caixas
                SET status = 'fechado',
                    data_fechamento = CURRENT_TIMESTAMP
              WHERE id = :id AND status = 'aberto'"
        );
        $stmt->execute([':id' => $caixaId]);
    }

    /** Marca conferencia_concluida=TRUE (executado pelo gerencial apos posting). */
    public function marcarConferenciaConcluida(int $caixaId, int $usuarioConferenciaId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE pdv_caixas
                SET conferencia_concluida = TRUE,
                    conferencia_em = CURRENT_TIMESTAMP,
                    conferencia_usuario_id = :uid
              WHERE id = :id"
        );
        $stmt->execute([':id' => $caixaId, ':uid' => $usuarioConferenciaId]);
    }

    /** Quantas vendas em Kanban (origem=fluxo) ainda nao resolvidas neste caixa. */
    public function contarVendasFluxoPendentes(int $caixaId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM pdv_vendas
              WHERE caixa_id = :id
                AND origem = 'fluxo'
                AND status IN ('pendente','em_processo','concluido')"
        );
        $stmt->execute([':id' => $caixaId]);
        return (int)$stmt->fetchColumn();
    }

    public function caixa(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pdv_caixas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Lista as conferências já feitas em um caixa (para tela de leitura pós-fechamento).
     * @return array<int, array<string, mixed>>
     */
    public function itensDoCaixa(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ci.*, fp.nome AS forma_nome, fp.tipo AS forma_tipo
               FROM pdv_conferencia_itens ci
               INNER JOIN formas_pagamento fp ON fp.id = ci.forma_pagamento_id
              WHERE ci.caixa_id = :caixa_id
              ORDER BY fp.nome"
        );
        $stmt->execute([':caixa_id' => $caixaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista todas as vendas faturadas do caixa (origem rápida ou fluxo), com a
     * forma de pagamento atual + dados para a grid de conferência venda-a-venda.
     *
     * @return array<int, array<string, mixed>>
     */
    public function vendasDoCaixaDetalhado(int $caixaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.id, v.numero, v.valor_total, v.created_at, v.origem,
                    v.forma_pagamento_id, v.conferida,
                    fp.nome AS forma_nome, fp.tipo AS forma_tipo,
                    cli.nome AS cliente_nome,
                    u.nome AS operador_nome,
                    (SELECT string_agg(i.nome_item, ' · ' ORDER BY i.id)
                       FROM (SELECT nome_item, id FROM pdv_venda_itens
                              WHERE venda_id = v.id ORDER BY id LIMIT 3) i
                    ) AS resumo_itens,
                    (SELECT COUNT(*) FROM pdv_venda_itens WHERE venda_id = v.id) AS qtd_itens
               FROM pdv_vendas v
               LEFT JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
               INNER JOIN usuarios u ON u.id = v.usuario_id
               LEFT JOIN clientes cli ON cli.id = v.cliente_id
              WHERE v.caixa_id = :id
                AND v.status = 'faturado'
              ORDER BY fp.tipo, v.created_at"
        );
        $stmt->execute([':id' => $caixaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Atualiza a forma de pagamento de uma venda faturada (antes da conferência).
     * Retorna o caixa_id para que o Service possa recalcular totais.
     *
     * @return array{ok:bool, erro:?string, caixa_id:?int}
     */
    /** Retorna 'PRODUTO' ou 'SERVICO' conforme soma majoritaria dos itens. */
    public function tipoItemPredominante(int $vendaId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN UPPER(tipo_item)='PRODUTO' THEN valor_total_item ELSE 0 END), 0) AS prod,
                COALESCE(SUM(CASE WHEN UPPER(tipo_item)='SERVICO' THEN valor_total_item ELSE 0 END), 0) AS serv
               FROM pdv_venda_itens WHERE venda_id = :id"
        );
        $stmt->execute([':id' => $vendaId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['prod' => 0, 'serv' => 0];
        return (float)$r['prod'] >= (float)$r['serv'] ? 'PRODUTO' : 'SERVICO';
    }

    public function descontoVenda(int $vendaId): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(desconto_valor, 0) FROM pdv_vendas WHERE id = :id"
        );
        $stmt->execute([':id' => $vendaId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    public function tipoFormaPagamento(int $formaPagamentoId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM formas_pagamento WHERE id = :id AND ativo = TRUE');
        $stmt->execute([':id' => $formaPagamentoId]);
        $t = $stmt->fetchColumn();
        return $t !== false && $t !== null ? strtoupper((string)$t) : null;
    }

    public function vendaTemCliente(int $vendaId): bool
    {
        $stmt = $this->pdo->prepare('SELECT cliente_id FROM pdv_vendas WHERE id = :id');
        $stmt->execute([':id' => $vendaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        return !empty($row['cliente_id']);
    }

    public function alterarFormaPagamentoVenda(int $vendaId, int $novaFormaId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT v.caixa_id, v.status, c.conferencia_concluida
               FROM pdv_vendas v
               INNER JOIN pdv_caixas c ON c.id = v.caixa_id
              WHERE v.id = :id"
        );
        $stmt->execute([':id' => $vendaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'erro' => 'Venda não encontrada.', 'caixa_id' => null];
        }
        if ((string)$row['status'] !== 'faturado') {
            return ['ok' => false, 'erro' => 'Apenas vendas faturadas podem ter a forma de pagamento alterada.', 'caixa_id' => null];
        }
        if (!empty($row['conferencia_concluida'])) {
            return ['ok' => false, 'erro' => 'Caixa já conferido. A forma de pagamento não pode ser alterada.', 'caixa_id' => null];
        }

        // Valida que a nova forma existe e está ativa
        $chk = $this->pdo->prepare('SELECT 1 FROM formas_pagamento WHERE id = :id AND ativo = TRUE');
        $chk->execute([':id' => $novaFormaId]);
        if (!$chk->fetchColumn()) {
            return ['ok' => false, 'erro' => 'Forma de pagamento inválida ou inativa.', 'caixa_id' => null];
        }

        $upd = $this->pdo->prepare(
            'UPDATE pdv_vendas SET forma_pagamento_id = :fp WHERE id = :id'
        );
        $upd->execute([':fp' => $novaFormaId, ':id' => $vendaId]);

        return ['ok' => $upd->rowCount() === 1, 'erro' => null, 'caixa_id' => (int)$row['caixa_id']];
    }

    /**
     * Recalcula valor_sistema em pdv_conferencia_itens por forma de pagamento,
     * mantendo valor_informado intacto. Usado após trocas de forma de venda.
     */
    public function recalcularValorSistema(int $caixaId): void
    {
        $formas = $this->formasAtivas();
        $totais = $this->totaisSistemaPorForma($caixaId);

        foreach ($formas as $f) {
            $fpId = (int)$f['id'];
            $valorSistema = (float)($totais[$fpId] ?? 0.0);

            // Preserva valor_informado se já existe, atualiza só valor_sistema
            $stmt = $this->pdo->prepare(
                "INSERT INTO pdv_conferencia_itens
                    (caixa_id, forma_pagamento_id, valor_sistema, valor_informado, conferido_em)
                 VALUES (:caixa_id, :fp, :vs, 0, CURRENT_TIMESTAMP)
                 ON CONFLICT (caixa_id, forma_pagamento_id) DO UPDATE
                    SET valor_sistema = EXCLUDED.valor_sistema,
                        conferido_em = CURRENT_TIMESTAMP"
            );
            $stmt->execute([
                ':caixa_id' => $caixaId,
                ':fp' => $fpId,
                ':vs' => $valorSistema,
            ]);
        }
    }
}
