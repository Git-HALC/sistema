<?php

namespace App\Modules\Servicos;

use PDO;

class ServicoCatalogoRepository
{
    private const TABLE = 'servicos_catalogo';

    public function __construct(private readonly PDO $pdo) {}

    public function listar(bool $somenteAtivos = false): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE;
        if ($somenteAtivos) {
            $sql .= ' WHERE ativo = TRUE';
        }
        $sql .= ' ORDER BY nome';

        return array_map(
            static fn(array $row): ServicoCatalogo => ServicoCatalogo::fromArray($row),
            $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function salvar(array $dados): int
    {
        $id = isset($dados['id']) ? (int)$dados['id'] : 0;

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::TABLE . ' SET nome = :nome, descricao = :descricao, valor_base = :valor_base, ativo = :ativo, updated_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':nome' => $dados['nome'],
                ':descricao' => $dados['descricao'] ?? null,
                ':valor_base' => (float)($dados['valor_base'] ?? 0),
                ':ativo' => filter_var($dados['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            ]);

            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (nome, descricao, valor_base, ativo) VALUES (:nome, :descricao, :valor_base, :ativo)'
        );
        $stmt->execute([
            ':nome' => $dados['nome'],
            ':descricao' => $dados['descricao'] ?? null,
            ':valor_base' => (float)($dados['valor_base'] ?? 0),
            ':ativo' => filter_var($dados['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        ]);

        return (int)$this->pdo->lastInsertId(self::TABLE . '_id_seq');
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ativo = FALSE, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
