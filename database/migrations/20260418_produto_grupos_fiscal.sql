-- ============================================================================
-- MIGRATION 2026-04-18 - Grupos/Subgrupos de produto + campos fiscais estendidos
-- ============================================================================

-- 1. Tabela produto_grupos
CREATE TABLE IF NOT EXISTS produto_grupos (
    id              SERIAL PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    descricao       TEXT,
    ativo           BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_produto_grupos_nome UNIQUE (nome)
);
COMMENT ON TABLE produto_grupos IS 'Grupos de classificacao de produtos (ex: Alimentos, Bebidas, Limpeza).';

-- 2. Tabela produto_subgrupos
CREATE TABLE IF NOT EXISTS produto_subgrupos (
    id              SERIAL PRIMARY KEY,
    grupo_id        INTEGER NOT NULL REFERENCES produto_grupos(id) ON DELETE RESTRICT,
    nome            VARCHAR(100) NOT NULL,
    descricao       TEXT,
    ativo           BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_produto_subgrupos_grupo_nome UNIQUE (grupo_id, nome)
);
COMMENT ON TABLE produto_subgrupos IS 'Subgrupos vinculados a produto_grupos.';
CREATE INDEX IF NOT EXISTS idx_produto_subgrupos_grupo_id ON produto_subgrupos(grupo_id);

-- 3. Adicionar colunas grupo/subgrupo + fiscais na tabela produtos (idempotente)
ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS grupo_id         INTEGER REFERENCES produto_grupos(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS subgrupo_id      INTEGER REFERENCES produto_subgrupos(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS ncm              VARCHAR(10),
    ADD COLUMN IF NOT EXISTS cest             VARCHAR(9),
    ADD COLUMN IF NOT EXISTS cfop             VARCHAR(5),
    ADD COLUMN IF NOT EXISTS origem_fiscal    SMALLINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_cst         VARCHAR(3),
    ADD COLUMN IF NOT EXISTS icms_aliquota    NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_base_calc   NUMERIC(5,2) DEFAULT 100,
    ADD COLUMN IF NOT EXISTS icms_st_aliquota NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_st_mva      NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS pis_cst          VARCHAR(3),
    ADD COLUMN IF NOT EXISTS pis_aliquota     NUMERIC(5,4) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cofins_cst       VARCHAR(3),
    ADD COLUMN IF NOT EXISTS cofins_aliquota  NUMERIC(5,4) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ipi_cst          VARCHAR(3),
    ADD COLUMN IF NOT EXISTS ipi_aliquota     NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS peso_bruto       NUMERIC(10,3) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS peso_liquido     NUMERIC(10,3) DEFAULT 0;

-- NOTA: produtos.unidade ja existe (VARCHAR(10) com CHECK); nao criamos unidade_medida paralela.

CREATE INDEX IF NOT EXISTS idx_produtos_grupo_id    ON produtos(grupo_id);
CREATE INDEX IF NOT EXISTS idx_produtos_subgrupo_id ON produtos(subgrupo_id);
CREATE INDEX IF NOT EXISTS idx_produtos_ncm         ON produtos(ncm) WHERE ncm IS NOT NULL;

-- 4. Trigger de atualizado_em em produto_grupos/subgrupos (reaproveita funcao ja existente)
DROP TRIGGER IF EXISTS trg_produto_grupos_updated    ON produto_grupos;
DROP TRIGGER IF EXISTS trg_produto_subgrupos_updated ON produto_subgrupos;

CREATE OR REPLACE FUNCTION produto_grupos_atualizar_em()
RETURNS TRIGGER AS $$
BEGIN
    NEW.atualizado_em := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_produto_grupos_updated
    BEFORE UPDATE ON produto_grupos
    FOR EACH ROW EXECUTE FUNCTION produto_grupos_atualizar_em();

CREATE TRIGGER trg_produto_subgrupos_updated
    BEFORE UPDATE ON produto_subgrupos
    FOR EACH ROW EXECUTE FUNCTION produto_grupos_atualizar_em();

-- 5. Seeds: grupos basicos + subgrupos de Alimentos
INSERT INTO produto_grupos (nome, descricao, ativo) VALUES
    ('Alimentos',   'Produtos alimenticios em geral',       TRUE),
    ('Bebidas',     'Bebidas alcoolicas e nao alcoolicas',  TRUE),
    ('Limpeza',     'Produtos de limpeza e higiene',        TRUE),
    ('Eletronicos', 'Equipamentos e acessorios eletronicos', TRUE),
    ('Vestuario',   'Roupas, calcados e acessorios',        TRUE)
ON CONFLICT (nome) DO NOTHING;

INSERT INTO produto_subgrupos (grupo_id, nome, ativo)
SELECT g.id, s.nome, TRUE
  FROM produto_grupos g
  CROSS JOIN (VALUES
      ('Frios e Laticinios'),
      ('Hortifruti'),
      ('Mercearia'),
      ('Padaria')
  ) AS s(nome)
 WHERE g.nome = 'Alimentos'
ON CONFLICT (grupo_id, nome) DO NOTHING;
