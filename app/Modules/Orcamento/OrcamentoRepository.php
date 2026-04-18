<?php

namespace App\Modules\Orcamento;

use PDO;

/**
 * Repository: OrcamentoRepository
 *
 * Responsável exclusivamente por persistência da tabela orcamentos.
 * Sem lógica de negócio. Retorna modelos Orcamento (nunca arrays brutos).
 */
class OrcamentoRepository
{
    private const TABLE = 'orcamentos';
    private ?bool $hasDescontoPercentualColumn = null;

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Escrita
    // =========================================================================

    /**
     * Insere um novo orçamento e retorna o ID gerado.
     * Itens são persistidos pelo OrcamentoItemRepository.
     */
    public function criar(Orcamento $o): int
    {
        if ($this->temColunaDescontoPercentual()) {
            $sql = "
                INSERT INTO orcamentos
                    (cliente_id, status, data_orcamento, data_validade,
                     observacoes, desconto_percentual, valor_total, usuario_id)
                VALUES
                    (:cliente_id, :status, :data_orcamento, :data_validade,
                     :observacoes, :desconto_percentual, :valor_total, :usuario_id)
            ";
        } else {
            $sql = "
                INSERT INTO orcamentos
                    (cliente_id, status, data_orcamento, data_validade,
                     observacoes, valor_total, usuario_id)
                VALUES
                    (:cliente_id, :status, :data_orcamento, :data_validade,
                     :observacoes, :valor_total, :usuario_id)
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bind($o));

        $id       = (int) $this->pdo->lastInsertId(self::TABLE . '_id_seq');
        $o->id    = $id;
        $o->codigo = 'ORC-' . str_pad($id, 6, '0', STR_PAD_LEFT);

        return $id;
    }

    /**
     * Atualiza apenas os campos de cabeçalho do orçamento.
     * Status, conta_receber_id e valor_total têm métodos próprios.
     */
    public function atualizar(Orcamento $o): bool
    {
        if ($this->temColunaDescontoPercentual()) {
            $sql = "
                UPDATE orcamentos SET
                    cliente_id          = :cliente_id,
                    data_orcamento      = :data_orcamento,
                    data_validade       = :data_validade,
                    observacoes         = :observacoes,
                    desconto_percentual = :desconto_percentual,
                    updated_at          = NOW()
                WHERE id = :id AND status = 'RASCUNHO'
            ";
        } else {
            $sql = "
                UPDATE orcamentos SET
                    cliente_id    = :cliente_id,
                    data_orcamento      = :data_orcamento,
                    data_validade = :data_validade,
                    observacoes   = :observacoes,
                    updated_at    = NOW()
                WHERE id = :id AND status = 'RASCUNHO'
            ";
        }

        $params = [
            ':cliente_id'     => $o->clienteId,
            ':data_orcamento' => $o->dataEmissao,
            ':data_validade'  => $o->dataValidade ?: null,
            ':observacoes'    => $o->observacoes ?: null,
            ':id'             => $o->id,
        ];

        if ($this->temColunaDescontoPercentual()) {
            $params[':desconto_percentual'] = round($o->descontoPercentual, 2);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza apenas o status do orçamento.
     */
    public function atualizarStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE orcamentos
            SET status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':status' => $status, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Registra a ContaReceber gerada na aprovação.
     */
    public function atualizarContaReceber(int $id, int $contaReceberId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE orcamentos
            SET conta_receber_id = :cr_id, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':cr_id' => $contaReceberId, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }
    
    public function limparContaReceber(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE orcamentos
            SET conta_receber_id = NULL, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Atualiza o valor total (recalculado a partir dos itens).
     */
    public function atualizarValorTotal(int $id, float $valorTotal): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE orcamentos
            SET valor_total = :valor, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':valor' => round($valorTotal, 4), ':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Exclui o orçamento (e seus itens via CASCADE).
     * A proteção contra exclusão de aprovados é feita no Service.
     */
    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM orcamentos WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Leitura
    // =========================================================================

    public function buscarPorId(int $id): ?Orcamento
    {
        $stmt = $this->pdo->prepare("
                            SELECT o.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, u.nome AS usuario_nome
            FROM orcamentos o
            LEFT JOIN clientes c ON c.id = o.cliente_id
                        LEFT JOIN usuarios u ON u.id = o.usuario_id
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Orcamento::fromArray($row) : null;
    }

    /**
     * Lista orçamentos paginados com filtros opcionais.
     *
     * Filtros suportados: status, cliente_id, busca, data_inicio, data_fim
     *
     * @return Orcamento[]
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        [$where, $params] = $this->buildWhere($filtros);

        $offset = max(0, ($pagina - 1) * $porPagina);

        $sql = "
                            SELECT o.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, u.nome AS usuario_nome
            FROM orcamentos o
            LEFT JOIN clientes c ON c.id = o.cliente_id
                        LEFT JOIN usuarios u ON u.id = o.usuario_id
            {$where}
            ORDER BY o.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn(array $row) => Orcamento::fromArray($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function totalRegistros(array $filtros): int
    {
        [$where, $params] = $this->buildWhere($filtros);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM orcamentos o LEFT JOIN clientes c ON c.id = o.cliente_id {$where}"
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    private function bind(Orcamento $o): array
    {
        $bind = [
            ':cliente_id'     => $o->clienteId,
            ':status'         => $o->status,
            ':data_orcamento' => $o->dataEmissao,
            ':data_validade'  => $o->dataValidade ?: null,
            ':observacoes'    => $o->observacoes  ?: null,
            ':valor_total'    => $o->valorTotal,
            ':usuario_id'     => $o->usuarioId,
        ];

        if ($this->temColunaDescontoPercentual()) {
            $bind[':desconto_percentual'] = round($o->descontoPercentual, 2);
        }

        return $bind;
    }

    private function temColunaDescontoPercentual(): bool
    {
        if ($this->hasDescontoPercentualColumn !== null) {
            return $this->hasDescontoPercentualColumn;
        }

        $stmt = $this->pdo->prepare("\n            SELECT EXISTS (\n                SELECT 1\n                FROM information_schema.columns\n                WHERE table_schema = 'public'\n                  AND table_name = 'orcamentos'\n                  AND column_name = 'desconto_percentual'\n            )\n        ");
        $stmt->execute();
        $this->hasDescontoPercentualColumn = (bool)$stmt->fetchColumn();

        return $this->hasDescontoPercentualColumn;
    }

    /** @return array{string, array} [$clausula, $params] */
    private function buildWhere(array $filtros): array
    {
        $conds  = [];
        $params = [];

        if (!empty($filtros['status'])) {
            $conds[]           = 'o.status = :status';
            $params[':status'] = strtoupper($filtros['status']);
        }

        if (!empty($filtros['cliente_id'])) {
            $conds[]              = 'o.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int) $filtros['cliente_id'];
        }

        if (!empty($filtros['busca'])) {
            $conds[]         = "(o.codigo ILIKE :busca OR c.nome ILIKE :busca OR o.observacoes ILIKE :busca)";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        if (!empty($filtros['data_inicio'])) {
            $conds[]                = 'o.data_orcamento >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $conds[]             = 'o.data_orcamento <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }

        $where = empty($conds) ? '' : 'WHERE ' . implode(' AND ', $conds);

        return [$where, $params];
    }
}
