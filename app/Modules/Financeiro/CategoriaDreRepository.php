<?php

namespace App\Modules\Financeiro;

use PDO;

class CategoriaDreRepository
{
    private const TABLE = 'categorias_dre';

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Reads
    // =========================================================================

    /**
     * Lista categorias.
     *
     * @param string|string[]|null $tipo   null = todos; string = tipo único; array = múltiplos tipos
     * @param bool                 $apenasAtivas
     */
    public function listar(string|array|null $tipo = null, bool $apenasAtivas = false): array
    {
        $conditions = [];
        $params     = [];

        if ($tipo !== null) {
            if (is_array($tipo)) {
                $placeholders  = implode(',', array_fill(0, count($tipo), '?'));
                $conditions[]  = "tipo IN ({$placeholders})";
                $params        = array_values($tipo);
            } else {
                $conditions[]    = 'tipo = ?';
                $params[]        = $tipo;
            }
        }

        if ($apenasAtivas) {
            $conditions[] = 'ativo = TRUE';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $sql   = 'SELECT * FROM ' . self::TABLE . " {$where} ORDER BY tipo, ordem, nome ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorId(int $id): ?CategoriaDre
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? CategoriaDre::fromArray($row) : null;
    }

    // =========================================================================
    // Writes
    // =========================================================================

    public function criar(CategoriaDre $cat): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO ' . self::TABLE . ' (nome, tipo, descricao, ordem)
            VALUES (:nome, :tipo, :descricao, :ordem)
        ');
        $stmt->execute([
            ':nome'     => $cat->nome,
            ':tipo'     => $cat->tipo,
            ':descricao'=> $cat->descricao,
            ':ordem'    => $cat->ordem,
        ]);
        return (int) $this->pdo->lastInsertId(self::TABLE . '_id_seq');
    }

    public function atualizar(CategoriaDre $cat): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE ' . self::TABLE . ' SET
                nome      = :nome,
                tipo      = :tipo,
                descricao = :descricao,
                ordem     = :ordem,
                ativo     = :ativo,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->bindValue(':id', $cat->id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $cat->nome, PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $cat->tipo, PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $cat->descricao, $cat->descricao === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ordem', $cat->ordem, PDO::PARAM_INT);
        $stmt->bindValue(':ativo', $cat->ativo, PDO::PARAM_BOOL);

        return $stmt->execute();
    }

    public function excluir(int $id): bool
    {
        // Se há uso em contas a receber ou pagar, apenas desativa
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contas_receber WHERE categoria_dre_id = :id');
        $stmt->execute([':id' => $id]);
        $usadoReceber = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contas_pagar WHERE categoria_dre_id = :id');
        $stmt->execute([':id' => $id]);
        $usadoPagar = (int) $stmt->fetchColumn();

        if ($usadoReceber > 0 || $usadoPagar > 0) {
            $this->pdo->prepare(
                'UPDATE ' . self::TABLE . ' SET ativo = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            )->execute([':id' => $id]);
            return true;
        }

        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
