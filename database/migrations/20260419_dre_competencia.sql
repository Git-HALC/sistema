-- ============================================================================
-- MIGRATION 2026-04-19 - DRE por regime de competencia
--
-- Regras contabeis enforcadas:
--   R1: Receita reconhecida na VENDA (nao no recebimento)
--   R2: Venda cartao = Receita Bruta (total) + Despesa Financeira (taxa) + CR (liquido)
--   R3: Recebimento (caixa/banco) NAO impacta DRE — apenas saldo patrimonial
--   R4: Deducoes (descontos, devolucoes, impostos s/ venda) nao misturam com desp. operacional
--   R5: Taxas bancarias/cartao, juros -> Despesa Financeira
--   R6: Estrutura DRE: Receita Bruta -> Deducoes -> Rec. Liquida -> CPV -> Lucro Bruto
--       -> Desp. Operacional -> Resultado Op. -> Resultado Financeiro -> LAIR -> IR/CSLL -> Lucro Liquido
--
-- Mudancas estruturais:
--   movimentacoes.afeta_dre  (BOOLEAN)  — separa eixo resultado do eixo saldo
--   categorias_dre.codigo    (VARCHAR)  — codigo contabil (01, 02, 41, 42, 51)
--   "Descontos Cedidos em Vendas" re-tipado de Despesa Operacional -> Deducao
-- ============================================================================

SET client_encoding = 'UTF8';

BEGIN;

-- ── 1. Colunas novas ──────────────────────────────────────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='movimentacoes' AND column_name='afeta_dre') THEN
        ALTER TABLE movimentacoes
            ADD COLUMN afeta_dre BOOLEAN NOT NULL DEFAULT TRUE;
        COMMENT ON COLUMN movimentacoes.afeta_dre IS
            'TRUE = lancamento aparece no DRE. Recebimentos de CR e movimentacoes puramente patrimoniais = FALSE.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='categorias_dre' AND column_name='codigo') THEN
        ALTER TABLE categorias_dre
            ADD COLUMN codigo VARCHAR(10);
        COMMENT ON COLUMN categorias_dre.codigo IS 'Codigo contabil (01=Vendas Produtos, 02=Vendas Servicos, 41=Descontos, 51=Taxas Bancarias)';
    END IF;
END $$;

-- ── 2. Re-tipar categoria Descontos Cedidos -> Deducao ────────────────────
UPDATE categorias_dre
   SET tipo = 'Deducao'
 WHERE UPPER(nome) LIKE '%DESCONTO%CEDIDO%'
    OR UPPER(nome) LIKE '%DESCONTO%CONCEDIDO%';

-- ── 3. Seed de codigos contabeis nas categorias existentes ────────────────
UPDATE categorias_dre SET codigo = '01' WHERE LOWER(nome) = 'vendas de produtos';
UPDATE categorias_dre SET codigo = '02' WHERE LOWER(nome) = 'vendas de servicos';
UPDATE categorias_dre SET codigo = '03' WHERE LOWER(nome) = 'receitas financeiras';
UPDATE categorias_dre SET codigo = '04' WHERE LOWER(nome) = 'outras receitas operacionais';
UPDATE categorias_dre SET codigo = '05' WHERE LOWER(nome) = 'icms sobre vendas';
UPDATE categorias_dre SET codigo = '06' WHERE LOWER(nome) = 'ipi sobre vendas';
UPDATE categorias_dre SET codigo = '07' WHERE LOWER(nome) = 'pis sobre vendas';
UPDATE categorias_dre SET codigo = '08' WHERE LOWER(nome) = 'cofins sobre vendas';
UPDATE categorias_dre SET codigo = '09' WHERE LOWER(nome) = 'iss sobre servicos';
UPDATE categorias_dre SET codigo = '10' WHERE LOWER(nome) = 'devolucoes de vendas';
UPDATE categorias_dre SET codigo = '11' WHERE LOWER(nome) = 'abatimentos comerciais';
UPDATE categorias_dre SET codigo = '13' WHERE LOWER(nome) = 'custo de mercadorias vendidas - cmv';
UPDATE categorias_dre SET codigo = '14' WHERE LOWER(nome) = 'custo de produtos vendidos - cpv';
UPDATE categorias_dre SET codigo = '41' WHERE UPPER(nome) LIKE '%DESCONTO%CEDIDO%' OR UPPER(nome) LIKE '%DESCONTO%CONCEDIDO%';
UPDATE categorias_dre SET codigo = '51' WHERE LOWER(nome) LIKE 'taxas bancarias%' OR LOWER(nome) LIKE 'taxas banc%';
UPDATE categorias_dre SET codigo = '52' WHERE LOWER(nome) = 'juros passivos';
UPDATE categorias_dre SET codigo = '53' WHERE LOWER(nome) = 'variacoes cambiais';
UPDATE categorias_dre SET codigo = '90' WHERE LOWER(nome) = 'imposto de renda - pj';
UPDATE categorias_dre SET codigo = '91' WHERE LOWER(nome) = 'contribuicao social - csll';

-- ── 4. Garante existencia das categorias chave (idempotente) ──────────────
INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Vendas de Produtos', 'Receita', '01', 'Receita bruta reconhecida na venda de produtos (competencia)', 1, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) = 'vendas de produtos');

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Vendas de Servicos', 'Receita', '02', 'Receita bruta reconhecida na venda de servicos (competencia)', 2, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) = 'vendas de servicos');

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Descontos Concedidos', 'Deducao', '41', 'Descontos concedidos ao cliente no ato da venda', 41, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) = 'descontos concedidos');

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Taxas Bancarias', 'Despesa Financeira', '51', 'Taxas cobradas por operadoras de cartao e bancos', 51, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) LIKE 'taxas bancarias%' OR LOWER(nome) LIKE 'taxas banc%');

-- ── 4.4. Corrige constraint chk_movimentacoes_tipo (tinha 'Saida' mal-encodada) ───
ALTER TABLE movimentacoes DROP CONSTRAINT IF EXISTS chk_movimentacoes_tipo;
-- Normaliza dados existentes: qualquer variacao de 'Sai*' vira 'Saida'
UPDATE movimentacoes SET tipo = 'Saida'   WHERE tipo LIKE 'Sa%' AND tipo <> 'Entrada';
UPDATE movimentacoes SET tipo = 'Entrada' WHERE tipo = 'Entrada';
ALTER TABLE movimentacoes
    ADD CONSTRAINT chk_movimentacoes_tipo
    CHECK (tipo IN ('Entrada', 'Saida'));

-- ── 4.5. Estender tipo_origem para incluir novos lancamentos contabeis ────
ALTER TABLE movimentacoes DROP CONSTRAINT IF EXISTS chk_movimento_tipo_origem;
ALTER TABLE movimentacoes
    ADD CONSTRAINT chk_movimento_tipo_origem
    CHECK (tipo_origem IN (
        'MANUAL', 'RECEBIMENTO', 'PAGAMENTO', 'ESTORNO',
        'VENDA_COMPETENCIA', 'TAXA_CARTAO', 'DEDUCAO',
        'TRIBUTO_VENDA', 'CPV', 'PDV', 'VENDA'
    ));

-- ── 5. Indices de performance no DRE ──────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_mov_dre_periodo
    ON movimentacoes (data_movimentacao)
 WHERE afeta_dre = TRUE;
CREATE INDEX IF NOT EXISTS idx_mov_categoria_periodo
    ON movimentacoes (categoria_dre_id, data_movimentacao)
 WHERE afeta_dre = TRUE;

-- ── 6. BACKFILL RETROATIVO ────────────────────────────────────────────────
-- Passo 6.1: TODA movimentacao existente vira afeta_dre=FALSE (zera, depois reconstroi)
UPDATE movimentacoes SET afeta_dre = FALSE;

-- Passo 6.2: Recebimentos de CR ja ficam marcados (patrimonial apenas)
-- (nada a fazer — ja estao FALSE)

-- Passo 6.3: Reconhecer receita competencia para toda venda PDV faturada
-- Monta 1 movimentacao de Receita por venda conferida, marcando afeta_dre=TRUE
-- Tipo (Produto vs Servico) pelo tipo_item majoritario dos itens da venda
DO $$
DECLARE
    catProdutoId INTEGER;
    catServicoId INTEGER;
    catTaxaId    INTEGER;
    contaDefault INTEGER;
    venda        RECORD;
    predominante TEXT;
    totalProd    NUMERIC;
    totalServ    NUMERIC;
    valorBruto   NUMERIC;
    valorTaxa    NUMERIC;
    taxaPercent  NUMERIC;
    obs          TEXT;
BEGIN
    SELECT id INTO catProdutoId FROM categorias_dre WHERE codigo='01' LIMIT 1;
    SELECT id INTO catServicoId FROM categorias_dre WHERE codigo='02' LIMIT 1;
    SELECT id INTO catTaxaId    FROM categorias_dre WHERE codigo='51' LIMIT 1;
    SELECT id INTO contaDefault FROM contas WHERE ativo = TRUE ORDER BY id LIMIT 1;
    IF contaDefault IS NULL THEN contaDefault := 1; END IF;

    FOR venda IN
        SELECT v.id, v.numero, v.valor_total, v.created_at, v.forma_pagamento_id,
               fp.tipo AS fp_tipo, COALESCE(fp.taxa, 0) AS fp_taxa
          FROM pdv_vendas v
          INNER JOIN pdv_caixas c ON c.id = v.caixa_id
          INNER JOIN formas_pagamento fp ON fp.id = v.forma_pagamento_id
         WHERE v.status = 'faturado'
           AND c.conferencia_concluida = TRUE
    LOOP
        SELECT
            COALESCE(SUM(CASE WHEN UPPER(tipo_item)='PRODUTO' THEN valor_total_item ELSE 0 END),0),
            COALESCE(SUM(CASE WHEN UPPER(tipo_item)='SERVICO' THEN valor_total_item ELSE 0 END),0)
          INTO totalProd, totalServ
          FROM pdv_venda_itens WHERE venda_id = venda.id;

        IF totalProd >= totalServ THEN
            predominante := 'PRODUTO';
        ELSE
            predominante := 'SERVICO';
        END IF;

        -- Receita (competencia) — afeta_dre=TRUE, afeta_saldo=FALSE (nao mexe caixa)
        INSERT INTO movimentacoes
            (conta_id, tipo, valor, data_movimentacao, descricao,
             categoria_dre_id, forma_pagamento_id, tipo_origem, afeta_saldo, afeta_dre, protegido, created_at)
        VALUES
            (contaDefault, 'Entrada', venda.valor_total, venda.created_at,
             'Receita competencia — Venda PDV #' || venda.numero,
             CASE WHEN predominante='PRODUTO' THEN catProdutoId ELSE catServicoId END,
             venda.forma_pagamento_id, 'VENDA_COMPETENCIA', FALSE, TRUE, TRUE, NOW())
        ON CONFLICT DO NOTHING;

        -- Cartao: taxa como Despesa Financeira
        IF venda.fp_tipo IN ('CC','CD') AND venda.fp_taxa > 0 AND catTaxaId IS NOT NULL THEN
            valorTaxa := ROUND(venda.valor_total * venda.fp_taxa / 100, 2);
            INSERT INTO movimentacoes
                (conta_id, tipo, valor, data_movimentacao, descricao,
                 categoria_dre_id, forma_pagamento_id, tipo_origem, afeta_saldo, afeta_dre, protegido, created_at)
            VALUES
                (contaDefault, 'Saida', valorTaxa, venda.created_at,
                 'Taxa cartao ' || venda.fp_taxa || '% — Venda PDV #' || venda.numero,
                 catTaxaId, venda.forma_pagamento_id, 'TAXA_CARTAO', FALSE, TRUE, TRUE, NOW());
        END IF;
    END LOOP;
END $$;

-- ── 7. Registry ───────────────────────────────────────────────────────────
INSERT INTO schema_migrations (version, checksum, executed_at)
VALUES ('20260419_dre_competencia', MD5('20260419_dre_competencia'), NOW())
ON CONFLICT (version) DO NOTHING;

COMMIT;
