-- ============================================================================
-- MIGRATION 2026-04-18 - PDV: Conferencia de Caixa
-- Tabela pdv_conferencia_itens, colunas em pdv_caixas/pdv_vendas/contas_receber,
-- trigger bloqueando INSERT em pdv_vendas para caixa ja fechado.
-- ============================================================================

-- 1. Tabela pdv_conferencia_itens
CREATE TABLE IF NOT EXISTS pdv_conferencia_itens (
    id                 SERIAL PRIMARY KEY,
    caixa_id           INTEGER NOT NULL REFERENCES pdv_caixas(id) ON DELETE CASCADE,
    forma_pagamento_id INTEGER NOT NULL REFERENCES formas_pagamento(id),
    valor_sistema      NUMERIC(12,2) NOT NULL DEFAULT 0,
    valor_informado    NUMERIC(12,2) NOT NULL DEFAULT 0,
    diferenca          NUMERIC(12,2) GENERATED ALWAYS AS (valor_informado - valor_sistema) STORED,
    conferido_em       TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_pdv_conferencia_caixa_forma UNIQUE (caixa_id, forma_pagamento_id)
);

CREATE INDEX IF NOT EXISTS idx_pdv_conferencia_caixa ON pdv_conferencia_itens(caixa_id);

-- 2. Colunas em pdv_caixas
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_caixas' AND column_name='conferencia_concluida') THEN
        ALTER TABLE pdv_caixas ADD COLUMN conferencia_concluida BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_caixas' AND column_name='conferencia_em') THEN
        ALTER TABLE pdv_caixas ADD COLUMN conferencia_em TIMESTAMP WITH TIME ZONE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_caixas' AND column_name='conferencia_usuario_id') THEN
        ALTER TABLE pdv_caixas ADD COLUMN conferencia_usuario_id INTEGER REFERENCES usuarios(id);
    END IF;
END $$;

-- 3. Colunas em pdv_vendas
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='conferida') THEN
        ALTER TABLE pdv_vendas ADD COLUMN conferida BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='caixa_conferencia_id') THEN
        ALTER TABLE pdv_vendas ADD COLUMN caixa_conferencia_id INTEGER REFERENCES pdv_caixas(id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_pdv_vendas_conferida ON pdv_vendas(caixa_id, conferida)
    WHERE conferida = FALSE AND status = 'faturado';

-- 4. Colunas em contas_receber
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='contas_receber' AND column_name='protegido') THEN
        ALTER TABLE contas_receber ADD COLUMN protegido BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='contas_receber' AND column_name='pdv_venda_id') THEN
        ALTER TABLE contas_receber ADD COLUMN pdv_venda_id INTEGER REFERENCES pdv_vendas(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_contas_receber_pdv_venda ON contas_receber(pdv_venda_id)
    WHERE pdv_venda_id IS NOT NULL;

-- 5. Trigger: bloquear INSERT em pdv_vendas quando caixa ja fechado
CREATE OR REPLACE FUNCTION bloquear_venda_caixa_fechado()
RETURNS TRIGGER AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    SELECT status INTO v_status FROM pdv_caixas WHERE id = NEW.caixa_id;
    IF v_status = 'fechado' THEN
        RAISE EXCEPTION 'Caixa #% esta fechado. Abra um novo caixa para registrar vendas.', NEW.caixa_id
              USING ERRCODE = '23000';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bloquear_venda_caixa_fechado ON pdv_vendas;
CREATE TRIGGER trg_bloquear_venda_caixa_fechado
    BEFORE INSERT ON pdv_vendas
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_venda_caixa_fechado();

-- 6. Bloquear DELETE em contas_receber protegidas (analogo ao de movimentacoes)
CREATE OR REPLACE FUNCTION bloquear_delete_cr_protegida()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Conta a receber protegida (origem PDV). Nao pode ser excluida.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bloquear_delete_cr_protegida ON contas_receber;
CREATE TRIGGER trg_bloquear_delete_cr_protegida
    BEFORE DELETE ON contas_receber
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_cr_protegida();
