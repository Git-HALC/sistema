<?php

namespace App\Modules\Produtos;

use PDO;

/**
 * ProdutoRepository ? acesso ? tabela `produtos`.
 *
 * Responsabilidades:
 *   ? Executar queries SQL da tabela `produtos` (sem colunas fiscais).
 *   ? Retornar modelos Produto j? hidratados.
 *   ? Usar um ?nico m?todo bind() para INSERT e UPDATE.
 *
 * Dados fiscais s?o gerenciados exclusivamente por ProdutoFiscalRepository.
 */
class ProdutoRepository
{
    private string $table = 'produtos';

    public function __construct(private PDO $pdo) {}

    // ----------------------------------------------------------------
    // Escrita
    // ----------------------------------------------------------------

    /** Insere um novo produto e retorna o ID gerado. */
    public function criar(Produto $produto): int
    {
        $sql = "INSERT INTO {$this->table}
                    (codigo, nome, descricao, unidade,
                     preco_custo, preco_venda, estoque_atual, estoque_minimo, ativo,
                     grupo_id, subgrupo_id)
                VALUES
                    (:codigo, :nome, :descricao, :unidade,
                     :preco_custo, :preco_venda, :estoque_atual, :estoque_minimo, :ativo,
                     :grupo_id, :subgrupo_id)";

        $this->pdo->prepare($sql)->execute($this->bind($produto));

        return (int)$this->pdo->lastInsertId("{$this->table}_id_seq");
    }

    /** Atualiza um produto existente. */
    public function atualizar(Produto $produto): bool
    {
        $sql = "UPDATE {$this->table}
                SET codigo         = :codigo,
                    nome           = :nome,
                    descricao      = :descricao,
                    unidade        = :unidade,
                    preco_custo    = :preco_custo,
                    preco_venda    = :preco_venda,
                    estoque_atual  = :estoque_atual,
                    estoque_minimo = :estoque_minimo,
                    ativo          = :ativo,
                    grupo_id       = :grupo_id,
                    subgrupo_id    = :subgrupo_id,
                    updated_at     = NOW()
                WHERE id = :id";

        $params        = $this->bind($produto);
        $params[':id'] = $produto->id;

        return $this->pdo->prepare($sql)->execute($params);
    }

    /** Remove um produto pelo ID (cascade deleta produto_fiscal). */
    public function excluir(int $id): bool
    {
        return $this->pdo
            ->prepare("DELETE FROM {$this->table} WHERE id = :id")
            ->execute([':id' => $id]);
    }

    // ----------------------------------------------------------------
    // Leitura
    // ----------------------------------------------------------------

    public function buscarPorId(int $id): ?Produto
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? Produto::fromArray($row) : null;
    }

    /**
     * Listagem paginada.
     * LEFT JOIN com produto_fiscal para exibir indicador `tem_fiscal` na view.
     *
     * @return array[]  Linhas cruas (n?o hidratadas em Produto ? leve para listagem).
     */
    public function listar(string $busca = '', int $pagina = 1, int $porPagina = 15): array
    {
        $offset = max(0, ($pagina - 1) * $porPagina);
        $params = [];
        $where  = '';

        if ($busca !== '') {
            $where            = "WHERE p.nome ILIKE :busca OR p.codigo ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        $sql = "SELECT p.id, p.codigo, p.nome, p.unidade,
                       p.preco_venda, p.estoque_atual, p.ativo,
                       (pf.produto_id IS NOT NULL) AS tem_fiscal
                FROM {$this->table} p
                LEFT JOIN produto_fiscal pf ON pf.produto_id = p.id
                {$where}
                ORDER BY p.nome ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function totalRegistros(string $busca = ''): int
    {
        $where  = '';
        $params = [];

        if ($busca !== '') {
            $where            = "WHERE nome ILIKE :busca OR codigo ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verifica se um SKU (c?digo) j? est? em uso por outro produto.
     *
     * @param string $sku       C?digo a verificar.
     * @param int    $ignorarId ID do pr?prio produto na edi??o (evita falso positivo).
     */
    public function existsBySku(string $sku, int $ignorarId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE codigo = :codigo AND id <> :id"
        );
        $stmt->execute([':codigo' => $sku, ':id' => $ignorarId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** @deprecated Use existsBySku(). Mantido para compatibilidade. */
    public function codigoExiste(string $codigo, int $ignorarId = 0): bool
    {
        return $this->existsBySku($codigo, $ignorarId);
    }

    /**
     * Retorna o pr?ximo SKU num?rico sequencial (MAX + 1).
     *
     * Deve ser chamado DENTRO de uma transa??o aberta. O LOCK TABLE
     * bloqueia INSERT/UPDATE concorrentes na tabela at? o COMMIT,
     * garantindo que dois processos simult?neos n?o gerem o mesmo SKU.
     */
    public function getNextSku(): string
    {
        $this->pdo->exec('LOCK TABLE produtos IN SHARE ROW EXCLUSIVE MODE');

        $stmt = $this->pdo->query(
            "SELECT COALESCE(MAX(codigo::BIGINT), 0) + 1
               FROM {$this->table}
              WHERE codigo ~ '^\d+$'"
        );

        return (string)(int)$stmt->fetchColumn();
    }

    // ----------------------------------------------------------------
    // Bind ?nico ? compartilhado por criar() e atualizar()
    // ----------------------------------------------------------------

    private function bind(Produto $p): array
    {
        return [
            ':codigo'         => $p->codigo,
            ':nome'           => $p->nome,
            ':descricao'      => $p->descricao,
            ':unidade'        => $p->unidade,
            ':preco_custo'    => $p->preco_custo,
            ':preco_venda'    => $p->preco_venda,
            ':estoque_atual'  => $p->estoque_atual,
            ':estoque_minimo' => $p->estoque_minimo,
            ':ativo'          => $p->ativo ? 'true' : 'false',
            ':grupo_id'       => $p->grupo_id,
            ':subgrupo_id'    => $p->subgrupo_id,
        ];
    }
}
