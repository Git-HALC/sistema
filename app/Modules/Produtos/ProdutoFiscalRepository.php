<?php

namespace App\Modules\Produtos;

use PDO;

/**
 * ProdutoFiscalRepository — acesso à tabela `produto_fiscal`.
 *
 * Responsabilidades:
 *   • Executar queries SQL exclusivamente na tabela `produto_fiscal`.
 *   • Usar um único método bind() para INSERT e UPDATE.
 *   • Não conhece lógica de negócio — só persistência.
 *
 * Transações: gerenciadas pelo ProdutoService (que controla o PDO compartilhado).
 */
class ProdutoFiscalRepository
{
    private string $table = 'produto_fiscal';

    public function __construct(private PDO $pdo) {}

    // ----------------------------------------------------------------
    // Leitura
    // ----------------------------------------------------------------

    public function buscarPorProduto(int $produtoId): ?ProdutoFiscal
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE produto_id = :produto_id LIMIT 1"
        );
        $stmt->execute([':produto_id' => $produtoId]);
        $row = $stmt->fetch();

        return $row ? ProdutoFiscal::fromArray($row) : null;
    }

    // ----------------------------------------------------------------
    // Escrita
    // ----------------------------------------------------------------

    /**
     * INSERT se ainda não existe registro para o produto; UPDATE caso contrário.
     */
    public function salvar(ProdutoFiscal $fiscal): bool
    {
        $existe = $this->buscarPorProduto($fiscal->produto_id) !== null;

        return $existe
            ? $this->atualizar($fiscal)
            : $this->inserir($fiscal);
    }

    /** Remove o registro fiscal de um produto. */
    public function excluirPorProduto(int $produtoId): bool
    {
        return $this->pdo
            ->prepare("DELETE FROM {$this->table} WHERE produto_id = :produto_id")
            ->execute([':produto_id' => $produtoId]);
    }

    // ----------------------------------------------------------------
    // Helpers privados
    // ----------------------------------------------------------------

    private function inserir(ProdutoFiscal $fiscal): bool
    {
        $sql = "INSERT INTO {$this->table}
                    (produto_id, ncm, cest, cfop, origem, csosn_cst,
                     cst_pis, cst_cofins, modalidade_bc_icms,
                     aliquota_icms, aliquota_icms_st, aliquota_ipi, aliquota_pis, aliquota_cofins,
                     reducao_bc_icms, codigo_beneficio_fiscal, ind_escala, cnpj_fabricante)
                VALUES
                    (:produto_id, :ncm, :cest, :cfop, :origem, :csosn_cst,
                     :cst_pis, :cst_cofins, :modalidade_bc_icms,
                     :aliquota_icms, :aliquota_icms_st, :aliquota_ipi, :aliquota_pis, :aliquota_cofins,
                     :reducao_bc_icms, :codigo_beneficio_fiscal, :ind_escala, :cnpj_fabricante)";

        return $this->pdo->prepare($sql)->execute($this->bind($fiscal));
    }

    private function atualizar(ProdutoFiscal $fiscal): bool
    {
        $sql = "UPDATE {$this->table}
                SET ncm             = :ncm,
                    cest            = :cest,
                    cfop            = :cfop,
                    origem          = :origem,
                    csosn_cst       = :csosn_cst,
                    cst_pis         = :cst_pis,
                    cst_cofins      = :cst_cofins,
                    modalidade_bc_icms = :modalidade_bc_icms,
                    aliquota_icms   = :aliquota_icms,
                    aliquota_icms_st = :aliquota_icms_st,
                    aliquota_ipi    = :aliquota_ipi,
                    aliquota_pis    = :aliquota_pis,
                    aliquota_cofins = :aliquota_cofins,
                    reducao_bc_icms = :reducao_bc_icms,
                    codigo_beneficio_fiscal = :codigo_beneficio_fiscal,
                    ind_escala      = :ind_escala,
                    cnpj_fabricante = :cnpj_fabricante,
                    updated_at      = NOW()
                WHERE produto_id    = :produto_id";

        return $this->pdo->prepare($sql)->execute($this->bind($fiscal));
    }

    private function bind(ProdutoFiscal $f): array
    {
        return [
            ':produto_id'      => $f->produto_id,
            ':ncm'             => $f->ncm  !== '' ? $f->ncm  : null,
            ':cest'            => $f->cest !== '' ? $f->cest : null,
            ':cfop'            => $f->cfop !== '' ? $f->cfop : null,
            ':origem'          => $f->origem,
            ':csosn_cst'       => $f->csosn_cst !== '' ? $f->csosn_cst : null,
            ':cst_pis'         => $f->cst_pis !== '' ? $f->cst_pis : null,
            ':cst_cofins'      => $f->cst_cofins !== '' ? $f->cst_cofins : null,
            ':modalidade_bc_icms' => $f->modalidade_bc_icms !== '' ? $f->modalidade_bc_icms : null,
            ':aliquota_icms'   => $f->aliquota_icms,
            ':aliquota_icms_st' => $f->aliquota_icms_st,
            ':aliquota_ipi'    => $f->aliquota_ipi,
            ':aliquota_pis'    => $f->aliquota_pis,
            ':aliquota_cofins' => $f->aliquota_cofins,
            ':reducao_bc_icms' => $f->reducao_bc_icms,
            ':codigo_beneficio_fiscal' => $f->codigo_beneficio_fiscal !== '' ? $f->codigo_beneficio_fiscal : null,
            ':ind_escala'      => $f->ind_escala,
            ':cnpj_fabricante' => $f->cnpj_fabricante !== '' ? $f->cnpj_fabricante : null,
        ];
    }
}
