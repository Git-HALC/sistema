<?php
declare(strict_types=1);

namespace App\Modules\Produtos;

use PDO;

final class ProdutoGrupoRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function listar(string $busca = '', ?bool $ativo = null, int $pagina = 1, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;

        $where = [];
        $params = [];
        if ($busca !== '') {
            $where[] = '(nome ILIKE :busca OR COALESCE(descricao,\'\') ILIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($ativo !== null) {
            $where[] = 'ativo = :ativo';
            $params[':ativo'] = $ativo ? 'true' : 'false';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM produto_grupos $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT g.*,
                       (SELECT COUNT(*) FROM produto_subgrupos WHERE grupo_id = g.id) AS qtd_subgrupos,
                       (SELECT COUNT(*) FROM produtos WHERE grupo_id = g.id) AS qtd_produtos
                  FROM produto_grupos g $whereSql
                 ORDER BY ativo DESC, nome ASC
                 LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'paginas' => (int)max(1, ceil($total / $porPagina)),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM produto_grupos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function existsNome(string $nome, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM produto_grupos WHERE LOWER(nome) = LOWER(:nome)';
        $params = [':nome' => $nome];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public function create(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO produto_grupos (nome, descricao, ativo) VALUES (:nome, :desc, :ativo) RETURNING id'
        );
        $stmt->execute([
            ':nome' => $dados['nome'],
            ':desc' => $dados['descricao'] ?? null,
            ':ativo' => !empty($dados['ativo']) ? 'true' : 'false',
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function update(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE produto_grupos SET nome = :nome, descricao = :desc, ativo = :ativo WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':nome' => $dados['nome'],
            ':desc' => $dados['descricao'] ?? null,
            ':ativo' => !empty($dados['ativo']) ? 'true' : 'false',
        ]);
    }

    public function ativosParaSelect(): array
    {
        return $this->pdo->query(
            'SELECT id, nome FROM produto_grupos WHERE ativo = TRUE ORDER BY nome'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
