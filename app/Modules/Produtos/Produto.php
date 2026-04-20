<?php

namespace App\Modules\Produtos;

/**
 * Produto — modelo de dados (sem dependência de banco).
 *
 * Representa exatamente as colunas da tabela `produtos`.
 * Dados fiscais ficam em ProdutoFiscal (tabela separada).
 */
class Produto
{
    public int     $id             = 0;
    public ?string $codigo         = null;
    public string  $nome           = '';
    public ?string $descricao      = null;
    public string  $unidade        = 'UN';
    public float   $preco_custo    = 0.0;
    public float   $preco_venda    = 0.0;
    public float   $estoque_atual  = 0.0;
    public float   $estoque_minimo = 0.0;
    public bool    $ativo          = true;
    public ?int    $grupo_id       = null;
    public ?int    $subgrupo_id    = null;
    public string  $created_at     = '';
    public string  $updated_at     = '';

    /** Hidrata o modelo a partir de uma linha do banco. */
    public static function fromArray(array $row): self
    {
        $p                 = new self();
        $p->id             = (int)($row['id']             ?? 0);
        $p->codigo         = $row['codigo']               ?: null;
        $p->nome           = $row['nome']                 ?? '';
        $p->descricao      = $row['descricao']            ?: null;
        $p->unidade        = $row['unidade']              ?? 'UN';
        $p->preco_custo    = (float)($row['preco_custo']  ?? 0);
        $p->preco_venda    = (float)($row['preco_venda']  ?? 0);
        $p->estoque_atual  = (float)($row['estoque_atual']  ?? 0);
        $p->estoque_minimo = (float)($row['estoque_minimo'] ?? 0);
        $p->ativo          = filter_var($row['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $p->grupo_id       = isset($row['grupo_id']) && $row['grupo_id'] !== '' ? (int)$row['grupo_id'] : null;
        $p->subgrupo_id    = isset($row['subgrupo_id']) && $row['subgrupo_id'] !== '' ? (int)$row['subgrupo_id'] : null;
        $p->created_at     = $row['created_at']           ?? '';
        $p->updated_at     = $row['updated_at']           ?? '';
        return $p;
    }

    /**
     * Retorna apenas os campos persistíveis (sem id e timestamps).
     * Usado pelo ProdutoRepository nos binds de INSERT e UPDATE.
     */
    public function toArray(): array
    {
        return [
            'codigo'         => $this->codigo,
            'nome'           => $this->nome,
            'descricao'      => $this->descricao,
            'unidade'        => $this->unidade,
            'preco_custo'    => $this->preco_custo,
            'preco_venda'    => $this->preco_venda,
            'estoque_atual'  => $this->estoque_atual,
            'estoque_minimo' => $this->estoque_minimo,
            'ativo'          => $this->ativo,
            'grupo_id'       => $this->grupo_id,
            'subgrupo_id'    => $this->subgrupo_id,
        ];
    }

    /** Unidades de medida válidas (espelhadas na constraint do banco). */
    public const UNIDADES = ['UN', 'KG', 'L', 'M', 'CX', 'PC', 'MT', 'M2', 'M3', 'PR'];
}
