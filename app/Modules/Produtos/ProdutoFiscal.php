<?php

namespace App\Modules\Produtos;

/**
 * ProdutoFiscal — modelo de dados (sem dependência de banco).
 *
 * Representa exatamente as colunas da tabela `produto_fiscal`.
 * Relacionamento: 1 Produto → 0..1 ProdutoFiscal.
 */
class ProdutoFiscal
{
    public int     $id              = 0;
    public int     $produto_id      = 0;
    public string  $ncm             = '';
    public ?string $cest            = null;
    public string  $cfop            = '';
    public string  $origem          = '0';
    public ?string $csosn_cst       = null;
    public ?string $cst_pis         = null;
    public ?string $cst_cofins      = null;
    public ?string $modalidade_bc_icms = null;
    public float   $aliquota_icms   = 0.0;
    public float   $aliquota_icms_st = 0.0;
    public float   $aliquota_ipi    = 0.0;
    public float   $aliquota_pis    = 0.0;
    public float   $aliquota_cofins = 0.0;
    public float   $reducao_bc_icms = 0.0;
    public ?string $codigo_beneficio_fiscal = null;
    public string  $ind_escala      = 'S';
    public ?string $cnpj_fabricante = null;
    public string  $created_at      = '';
    public string  $updated_at      = '';

    /** Hidrata o modelo a partir de uma linha do banco. */
    public static function fromArray(array $row): self
    {
        $f                  = new self();
        $f->id              = (int)($row['id']             ?? 0);
        $f->produto_id      = (int)($row['produto_id']     ?? 0);
        $f->ncm             = $row['ncm']                  ?? '';
        $f->cest            = $row['cest']                 ?: null;
        $f->cfop            = $row['cfop']                 ?? '';
        $f->origem          = $row['origem']               ?? '0';
        $f->csosn_cst       = $row['csosn_cst']            ?: null;
        $f->cst_pis         = $row['cst_pis']              ?: null;
        $f->cst_cofins      = $row['cst_cofins']           ?: null;
        $f->modalidade_bc_icms = $row['modalidade_bc_icms'] ?: null;
        $f->aliquota_icms   = (float)($row['aliquota_icms']   ?? 0);
        $f->aliquota_icms_st = (float)($row['aliquota_icms_st'] ?? 0);
        $f->aliquota_ipi    = (float)($row['aliquota_ipi']    ?? 0);
        $f->aliquota_pis    = (float)($row['aliquota_pis']    ?? 0);
        $f->aliquota_cofins = (float)($row['aliquota_cofins'] ?? 0);
        $f->reducao_bc_icms = (float)($row['reducao_bc_icms'] ?? 0);
        $f->codigo_beneficio_fiscal = $row['codigo_beneficio_fiscal'] ?: null;
        $f->ind_escala      = $row['ind_escala']            ?? 'S';
        $f->cnpj_fabricante = $row['cnpj_fabricante']       ?: null;
        $f->created_at      = $row['created_at']           ?? '';
        $f->updated_at      = $row['updated_at']           ?? '';
        return $f;
    }

    /**
     * Retorna apenas os campos persistíveis (sem id e timestamps).
     * Usado pelo ProdutoFiscalRepository nos binds de INSERT e UPDATE.
     */
    public function toArray(): array
    {
        return [
            'produto_id'      => $this->produto_id,
            'ncm'             => $this->ncm,
            'cest'            => $this->cest,
            'cfop'            => $this->cfop,
            'origem'          => $this->origem,
            'csosn_cst'       => $this->csosn_cst,
            'cst_pis'         => $this->cst_pis,
            'cst_cofins'      => $this->cst_cofins,
            'modalidade_bc_icms' => $this->modalidade_bc_icms,
            'aliquota_icms'   => $this->aliquota_icms,
            'aliquota_icms_st' => $this->aliquota_icms_st,
            'aliquota_ipi'    => $this->aliquota_ipi,
            'aliquota_pis'    => $this->aliquota_pis,
            'aliquota_cofins' => $this->aliquota_cofins,
            'reducao_bc_icms' => $this->reducao_bc_icms,
            'codigo_beneficio_fiscal' => $this->codigo_beneficio_fiscal,
            'ind_escala'      => $this->ind_escala,
            'cnpj_fabricante' => $this->cnpj_fabricante,
        ];
    }

    /**
     * Indica se os dados fiscais não devem ser persistidos.
     * NCM é o campo-gatilho: sem NCM, o bloco inteiro é descartado.
     * Garante que nunca se tente INSERT com ncm = NULL (coluna NOT NULL no banco).
     */
    public function vazio(): bool
    {
        return $this->ncm === '';
    }

    // ----------------------------------------------------------------
    // Tabelas de domínio fiscal (usadas nas views)
    // ----------------------------------------------------------------

    public const ORIGENS = [
        '0' => '0 – Nacional',
        '1' => '1 – Estrangeiro (importação direta)',
        '2' => '2 – Estrangeiro (mercado interno)',
        '3' => '3 – Nacional (conteúdo import. > 40% e ≤ 70%)',
        '4' => '4 – Nacional (processos produtivos básicos)',
        '5' => '5 – Nacional (conteúdo import. ≤ 40%)',
        '6' => '6 – Estrangeiro (import. direta, sem similar)',
        '7' => '7 – Estrangeiro (mercado interno, sem similar)',
        '8' => '8 – Nacional (conteúdo import. > 70%)',
    ];

    /**
     * Opções unificadas de CSOSN (Simples Nacional) + CST ICMS (Lucro Real/Presumido).
     * O campo csosn_cst armazena qualquer um deles dependendo do regime tributário.
     */
    public const CSOSN_CST = [
        '— Simples Nacional (CSOSN) —' => [
            '101' => '101 – Tributada c/ crédito',
            '102' => '102 – Tributada s/ crédito',
            '103' => '103 – Isenção (faixa de receita)',
            '201' => '201 – Tributada c/ crédito + ST',
            '202' => '202 – Tributada s/ crédito + ST',
            '203' => '203 – Isenção + ST',
            '300' => '300 – Imune',
            '400' => '400 – Não tributada',
            '500' => '500 – ICMS cobrado anteriorm. por ST',
            '900' => '900 – Outros (Simples)',
        ],
        '— Lucro Real / Presumido (CST) —' => [
            '00'  => '00 – Tributada integralmente',
            '10'  => '10 – Tributada + cobrança ST',
            '20'  => '20 – Com redução de BC',
            '30'  => '30 – Isenta/não-trib. + cobrança ST',
            '40'  => '40 – Isenta',
            '41'  => '41 – Não tributada',
            '50'  => '50 – Suspensão',
            '51'  => '51 – Diferimento',
            '60'  => '60 – ICMS cobrado anteriorm. por ST',
            '70'  => '70 – Com red. BC + cobrança ST',
            '90'  => '90 – Outras (Lucro Real)',
        ],
    ];

    public const CST_PIS_COFINS = [
        '01' => '01 – Operacao tributavel com aliquota basica',
        '02' => '02 – Operacao tributavel com aliquota diferenciada',
        '03' => '03 – Operacao tributavel por quantidade',
        '04' => '04 – Operacao tributavel monofasica - revenda a aliquota zero',
        '05' => '05 – Operacao tributavel por ST',
        '06' => '06 – Operacao tributavel a aliquota zero',
        '07' => '07 – Operacao isenta',
        '08' => '08 – Operacao sem incidencia',
        '09' => '09 – Operacao com suspensao',
        '49' => '49 – Outras operacoes de saida',
        '50' => '50 – Operacao com direito a credito - vinculada exclusivamente a receita tributada',
        '51' => '51 – Operacao com direito a credito - vinculada exclusivamente a receita nao tributada',
        '52' => '52 – Operacao com direito a credito - vinculada exclusivamente a receita de exportacao',
        '53' => '53 – Operacao com direito a credito - vinculada a receitas mistas',
        '54' => '54 – Operacao com direito a credito - vinculada a receitas mistas e de exportacao',
        '55' => '55 – Operacao com direito a credito - presumido',
        '56' => '56 – Operacao com direito a credito - aquisicao para revenda monofasica',
        '60' => '60 – Credito presumido - outras operacoes',
        '61' => '61 – Operacao tributada monofasica - revenda a aliquota zero',
        '62' => '62 – Operacao tributada monofasica - revenda',
        '63' => '63 – Operacao tributada monofasica - revenda com aliquota diferenciada',
        '64' => '64 – Operacao tributada por ST',
        '65' => '65 – Operacao de aquisicao sem direito a credito',
        '66' => '66 – Operacao de aquisicao com direito a credito vinculado a receita tributada',
        '67' => '67 – Operacao de aquisicao com direito a credito vinculado a receita nao tributada',
        '70' => '70 – Operacao de aquisicao sem direito a credito',
        '71' => '71 – Operacao de aquisicao com isencao',
        '72' => '72 – Operacao de aquisicao com suspensao',
        '73' => '73 – Operacao de aquisicao a aliquota zero',
        '74' => '74 – Operacao de aquisicao sem incidencia',
        '75' => '75 – Operacao de aquisicao por substituicao tributaria',
        '98' => '98 – Outras operacoes de entrada',
        '99' => '99 – Outras operacoes',
    ];

    public const MODALIDADES_BC_ICMS = [
        '0' => '0 – Margem Valor Agregado (%)',
        '1' => '1 – Pauta (valor)',
        '2' => '2 – Preco tabelado maximo (valor)',
        '3' => '3 – Valor da operacao',
    ];

    public const IND_ESCALA = [
        'S' => 'S – Produzido em escala relevante',
        'N' => 'N – Produzido em escala nao relevante',
    ];
}
