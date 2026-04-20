<?php
declare(strict_types=1);

namespace App\Modules\Produtos;

use PDO;

final class ProdutoSubgrupoRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function listar(string $busca = '', ?int $grupoId = null, ?bool $ativo = null, int $pagina = 1, int $porPagina = 20): array
    {
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $porPagina;

        $where = [];
        $params = [];
        if ($busca !== '') {
            $where[] = '(sg.nome ILIKE :busca OR COALESCE(sg.descricao,\'\') ILIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($grupoId !== null) {
            $where[] = 'sg.grupo_id = :gid';
            $params[':gid'] = $grupoId;
        }
        if ($ativo !== null) {
            $where[] = 'sg.ativo = :ativo';
            $params[':ativo'] = $ativo ? 'true' : 'false';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM produto_subgrupos sg $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT sg.*, g.nome AS grupo_nome,
                       (SELECT COUNT(*) FROM produtos WHERE subgrupo_id = sg.id) AS qtd_produtos
                  FROM produto_subgrupos sg
                  INNER JOIN produto_grupos g ON g.id = sg.grupo_id
                  $whereSql
                 ORDER BY g.nome, sg.nome
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
        $stmt = $this->pdo->prepare('SELECT * FROM produto_subgrupos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function existsNomeNoGrupo(int $grupoId, string $nome, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM produto_subgrupos WHERE grupo_id = :g AND LOWER(nome) = LOWER(:nome)';
        $params = [':g' => $grupoId, ':nome' => $nome];
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
            'INSERT INTO produto_subgrupos (grupo_id, nome, descricao, ativo)
             VALUES (:g, :nome, :desc, :ativo) RETURNING id'
        );
        $stmt->execute([
            ':g' => $dados['grupo_id'],
            ':nome' => $dados['nome'],
            ':desc' => $dados['descricao'] ?? null,
            ':ativo' => !empty($dados['ativo']) ? 'true' : 'false',
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function update(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE produto_subgrupos SET grupo_id = :g, nome = :nome, descricao = :desc, ativo = :ativo WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $id,
            ':g' => $dados['grupo_id'],
            ':nome' => $dados['nome'],
            ':desc' => $dados['descricao'] ?? null,
            ':ativo' => !empty($dados['ativo']) ? 'true' : 'false',
        ]);
    }

    public function porGrupoAtivos(int $grupoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome FROM produto_subgrupos WHERE grupo_id = :g AND ativo = TRUE ORDER BY nome'
        );
        $stmt->execute([':g' => $grupoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
