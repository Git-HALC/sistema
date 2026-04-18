<?php

namespace App\Modules\Servicos;

use PDO;

class ServicoCatalogoRepository
{
    private const TABLE = 'servicos_catalogo';

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @return array{dados: array<int, ServicoCatalogo>, total: int, paginas: int}
     */
    public function listar(string $busca, ?bool $ativo, int $pagina, int $porPagina): array
    {
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;

        $where = [];
        $params = [];

        if ($busca !== '') {
            $where[] = '(nome ILIKE :busca OR COALESCE(descricao, \'\') ILIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        if ($ativo !== null) {
            $where[] = 'ativo = :ativo';
            $params[':ativo'] = $ativo ? 'true' : 'false';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sqlTotal = "SELECT COUNT(*) FROM " . self::TABLE . " {$whereSql}";
        $stmt = $this->pdo->prepare($sqlTotal);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT * FROM " . self::TABLE . " {$whereSql}
                ORDER BY ativo DESC, nome ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dados = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dados[] = ServicoCatalogo::fromArray($row);
        }

        return [
            'dados' => $dados,
            'total' => $total,
            'paginas' => (int)max(1, ceil($total / $porPagina)),
        ];
    }

    public function findById(int $id): ?ServicoCatalogo
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ServicoCatalogo::fromArray($row) : null;
    }

    public function existsByNome(string $nome, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM ' . self::TABLE . ' WHERE LOWER(nome) = LOWER(:nome)';
        $params = [':nome' => $nome];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE . ' (nome, descricao, valor_base, ativo, created_at, updated_at)
             VALUES (:nome, :descricao, :valor_base, :ativo, NOW(), NOW())
             RETURNING id'
        );
        $stmt->execute([
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':valor_base' => $data['valor_base'],
            ':ativo' => !empty($data['ativo']) ? 'true' : 'false',
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . '
                SET nome = :nome,
                    descricao = :descricao,
                    valor_base = :valor_base,
                    ativo = :ativo,
                    updated_at = NOW()
              WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':nome' => $data['nome'],
            ':descricao' => $data['descricao'] ?? null,
            ':valor_base' => $data['valor_base'],
            ':ativo' => !empty($data['ativo']) ? 'true' : 'false',
        ]);
    }

    public function setAtivo(int $id, bool $ativo): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ativo = :ativo, updated_at = NOW() WHERE id = :id'
        );
        return $stmt->execute([':id' => $id, ':ativo' => $ativo ? 'true' : 'false']);
    }
}
