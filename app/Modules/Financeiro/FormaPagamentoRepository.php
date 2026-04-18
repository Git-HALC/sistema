<?php

namespace App\Modules\Financeiro;

use PDO;

class FormaPagamentoRepository
{
    private const TABLE = 'formas_pagamento';
    /** @var array<string, bool>|null */
    private ?array $availableColumns = null;

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Reads
    // =========================================================================

    public function listar(bool $apenasAtivas = false): array
    {
        $cols = $this->availableColumns();
        $select = ['fp.*'];
        $joins = [];

        if (isset($cols['adquirente_id'])) {
            $select[] = 'cli.nome AS adquirente_nome';
            $joins[] = 'LEFT JOIN clientes cli ON cli.id = fp.adquirente_id';
        } else {
            $select[] = 'NULL::text AS adquirente_nome';
        }

        if (isset($cols['conta_id'])) {
            $select[] = 'c.nome AS conta_nome';
            $joins[] = 'LEFT JOIN contas c ON c.id = fp.conta_id';
        } else {
            $select[] = 'NULL::text AS conta_nome';
        }

        $sql = '
            SELECT ' . implode(",\n                   ", $select) . '
            FROM ' . self::TABLE . ' fp
            ' . implode("\n            ", $joins) . '
        ';
        if ($apenasAtivas) {
            $sql .= ' WHERE fp.ativo = TRUE';
        }
        $sql .= ' ORDER BY fp.nome ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?FormaPagamento
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? FormaPagamento::fromArray($row) : null;
    }

    public function buscarDetalhadaPorId(int $id): ?array
    {
        $cols = $this->availableColumns();
        $select = ['fp.*'];
        $joins = [];

        if (isset($cols['adquirente_id'])) {
            $select[] = 'cli.nome AS adquirente_nome';
            $joins[] = 'LEFT JOIN clientes cli ON cli.id = fp.adquirente_id';
        } else {
            $select[] = 'NULL::text AS adquirente_nome';
        }

        if (isset($cols['conta_id'])) {
            $select[] = 'c.nome AS conta_nome';
            $joins[] = 'LEFT JOIN contas c ON c.id = fp.conta_id';
        } else {
            $select[] = 'NULL::text AS conta_nome';
        }

        $stmt = $this->pdo->prepare('
            SELECT ' . implode(",\n                   ", $select) . '
            FROM ' . self::TABLE . ' fp
            ' . implode("\n            ", $joins) . '
            WHERE fp.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // =========================================================================
    // Writes
    // =========================================================================

    public function criar(FormaPagamento $fp): int
    {
        $cols = ['nome', 'tipo', 'descricao'];
        $values = [':nome', ':tipo', ':descricao'];
        $params = [
            ':nome' => $fp->nome,
            ':tipo' => $fp->tipo,
            ':descricao' => $fp->descricao,
        ];
        $available = $this->availableColumns();

        if (isset($available['adquirente_id'])) {
            $cols[] = 'adquirente_id';
            $values[] = ':adquirente_id';
            $params[':adquirente_id'] = $fp->adquirenteId;
        }
        if (isset($available['taxa'])) {
            $cols[] = 'taxa';
            $values[] = ':taxa';
            $params[':taxa'] = $fp->taxa;
        }
        if (isset($available['prazo_dias'])) {
            $cols[] = 'prazo_dias';
            $values[] = ':prazo_dias';
            $params[':prazo_dias'] = $fp->prazoDias;
        }
        if (isset($available['conta_id'])) {
            $cols[] = 'conta_id';
            $values[] = ':conta_id';
            $params[':conta_id'] = $fp->contaId;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO ' . self::TABLE . ' (' . implode(', ', $cols) . ')
            VALUES (' . implode(', ', $values) . ')
        ');
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId(self::TABLE . '_id_seq');
    }

    public function atualizar(FormaPagamento $fp): bool
    {
        $sets = [
            'nome = :nome',
            'tipo = :tipo',
            'descricao = :descricao',
        ];
        $params = [
            ':id' => $fp->id,
            ':nome' => $fp->nome,
            ':tipo' => $fp->tipo,
            ':descricao' => $fp->descricao,
            ':ativo' => $fp->ativo,
        ];
        $available = $this->availableColumns();

        if (isset($available['adquirente_id'])) {
            $sets[] = 'adquirente_id = :adquirente_id';
            $params[':adquirente_id'] = $fp->adquirenteId;
        }
        if (isset($available['taxa'])) {
            $sets[] = 'taxa = :taxa';
            $params[':taxa'] = $fp->taxa;
        }
        if (isset($available['prazo_dias'])) {
            $sets[] = 'prazo_dias = :prazo_dias';
            $params[':prazo_dias'] = $fp->prazoDias;
        }
        if (isset($available['conta_id'])) {
            $sets[] = 'conta_id = :conta_id';
            $params[':conta_id'] = $fp->contaId;
        }
        $sets[] = 'ativo = :ativo';
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->pdo->prepare('
            UPDATE ' . self::TABLE . ' SET
                ' . implode(",\n                ", $sets) . '
            WHERE id = :id
        ');
        $stmt->execute($params);
        return true;
    }

    public function excluir(int $id): bool
    {
        // Se há movimentações vinculadas, apenas desativa (FK é ON DELETE SET NULL, mas preservamos histórico)
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM movimentacoes WHERE forma_pagamento_id = :id');
        $stmt->execute([':id' => $id]);
        $usadoMov = (int) $stmt->fetchColumn();

        if ($usadoMov > 0) {
            $this->pdo->prepare(
                'UPDATE ' . self::TABLE . ' SET ativo = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute([':id' => $id]);
            return true;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, bool>
     */
    private function availableColumns(): array
    {
        if ($this->availableColumns !== null) {
            return $this->availableColumns;
        }

        $stmt = $this->pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = :table
        ");
        $stmt->execute([':table' => self::TABLE]);

        $this->availableColumns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $this->availableColumns[(string)$col] = true;
        }

        return $this->availableColumns;
    }
}
