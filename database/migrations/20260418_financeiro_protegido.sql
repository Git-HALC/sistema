-- ============================================================================
-- MIGRATION 2026-04-18 - Financeiro: padronizacao de protegido + origem
-- ============================================================================

-- 1. Garantir colunas em contas_receber (caso rodem em banco sem a Fase 8)
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

-- 2. Garantir coluna em contas_pagar
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='contas_pagar' AND column_name='protegido') THEN
        ALTER TABLE contas_pagar ADD COLUMN protegido BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
END $$;

-- 3. Padronizar chk_cr_origem
DO $$
BEGIN
    -- Limpar origens invalidas antes de aplicar o CHECK
    UPDATE contas_receber
       SET origem = 'MANUAL'
     WHERE origem IS NULL
        OR origem NOT IN ('PDV','MANUAL','PEDIDO','SERVICO','ORCAMENTO');

    -- Remover CHECK antigo (se existir com nome diferente)
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_contas_receber_origem') THEN
        ALTER TABLE contas_receber DROP CONSTRAINT chk_contas_receber_origem;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_cr_origem') THEN
        ALTER TABLE contas_receber
            ADD CONSTRAINT chk_cr_origem
            CHECK (origem IN ('PDV','MANUAL','PEDIDO','SERVICO','ORCAMENTO'));
    END IF;
END $$;

-- 4. Funcao generica de bloqueio de DELETE em registros protegidos
CREATE OR REPLACE FUNCTION bloquear_delete_protegido()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.protegido = TRUE THEN
        RAISE EXCEPTION 'Este registro e protegido e nao pode ser excluido.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

-- 5. Aplicar em contas_receber (substitui o trigger antigo trg_bloquear_delete_cr_protegida)
DROP TRIGGER IF EXISTS trg_bloquear_delete_cr_protegida ON contas_receber;
DROP TRIGGER IF EXISTS trg_bloquear_delete_cr_protegido ON contas_receber;
CREATE TRIGGER trg_bloquear_delete_cr_protegido
    BEFORE DELETE ON contas_receber
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_protegido();

-- 6. Aplicar em contas_pagar
DROP TRIGGER IF EXISTS trg_bloquear_delete_cp_protegido ON contas_pagar;
CREATE TRIGGER trg_bloquear_delete_cp_protegido
    BEFORE DELETE ON contas_pagar
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_protegido();

-- 7. Aplicar em movimentacoes (substitui a funcao anterior especifica)
DROP TRIGGER IF EXISTS trg_bloquear_delete_mov_protegida ON movimentacoes;
DROP TRIGGER IF EXISTS trg_bloquear_delete_mov_protegido ON movimentacoes;
CREATE TRIGGER trg_bloquear_delete_mov_protegido
    BEFORE DELETE ON movimentacoes
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_protegido();
