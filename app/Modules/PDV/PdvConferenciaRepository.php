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
                    fp.tipo AS fp_tipo, fp.nome AS fp_nome, fp.conta_id, fp.prazo_dias
               FROM pdv_vendas v
               INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
              WHERE v.caixa_id = :caixa_id
                AND v.status = 'faturado'
                AND v.conferida = FALSE
              ORDER BY v.id"
        );
        $stmt->execute([':caixa_id' => $caixaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function inserirMovimentacaoEntrada(
        int $contaId,
        float $valor,
        string $descricao,
        int $formaPagamentoId
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 forma_pagamento_id, tipo_origem, protegido, afeta_saldo)
             VALUES
                (:conta_id, 'Entrada', :valor, CURRENT_TIMESTAMP, :descricao,
                 :fp, 'RECEBIMENTO', TRUE, TRUE)"
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':valor' => $valor,
            ':descricao' => $descricao,
            ':fp' => $formaPagamentoId,
        ]);
    }

    public function inserirContaReceberPdv(
        string $descricao,
        ?int $clienteId,
        int $formaPagamentoId,
        float $valor,
        int $prazoDias,
        int $pdvVendaId
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contas_receber
                (descricao, cliente_id, forma_pagamento_id, valor,
                 data_vencimento, status, origem, protegido, pdv_venda_id)
             VALUES
                (:descricao, :cliente_id, :fp, :valor,
                 CURRENT_DATE + (:prazo || ' days')::INTERVAL, 'PENDENTE', 'PDV', TRUE, :venda_id)"
        );
        $stmt->execute([
            ':descricao' => $descricao,
            ':cliente_id' => $clienteId,
            ':fp' => $formaPagamentoId,
            ':valor' => $valor,
            ':prazo' => max(0, $prazoDias),
            ':venda_id' => $pdvVendaId,
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
}
