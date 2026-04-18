-- ============================================================================
-- MIGRATION DE AUDITORIA 2026-04-18
-- Remove modulos: Orcamento, FiscalServico (mantem tabelas vazias), Gestao_Pedidos
-- (PHP), Servicos (PHP). Mantem tabelas: pedidos, pedido_itens, servicos_catalogo,
-- empresa_fiscal_servico, nota_fiscal_servico.
-- Corrige PDV: protegido em movimentacoes, CHECK em contas_receber.origem,
-- total_vendas em pdv_caixas, FK real em pdv_lancamentos.
-- ============================================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- FASE 0 - Safety pre-checks (idempotente)
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    v_nfe_srv_auth INT;
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pedido_nfe' AND column_name='servico_id') THEN
        EXECUTE 'SELECT COUNT(*) FROM pedido_nfe WHERE servico_id IS NOT NULL AND status = ''AUTORIZADA'''
          INTO v_nfe_srv_auth;
        IF v_nfe_srv_auth > 0 THEN
            RAISE EXCEPTION 'Abortando: existem % NF-e AUTORIZADA vinculadas a servicos.', v_nfe_srv_auth;
        END IF;
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- FASE 1 - Nulificar referencias e remover FKs/colunas auxiliares
-- ---------------------------------------------------------------------------

-- 1.1 contas_receber: remover orcamento_id e servico_id
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='contas_receber' AND column_name='orcamento_id') THEN
        UPDATE contas_receber SET orcamento_id = NULL WHERE orcamento_id IS NOT NULL;
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='contas_receber_orcamento_id_fkey') THEN
            ALTER TABLE contas_receber DROP CONSTRAINT contas_receber_orcamento_id_fkey;
        END IF;
        DROP INDEX IF EXISTS idx_contas_receber_orcamento;
        ALTER TABLE contas_receber DROP COLUMN orcamento_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='contas_receber' AND column_name='servico_id') THEN
        UPDATE contas_receber SET servico_id = NULL WHERE servico_id IS NOT NULL;
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='contas_receber_servico_id_fkey') THEN
            ALTER TABLE contas_receber DROP CONSTRAINT contas_receber_servico_id_fkey;
        END IF;
        ALTER TABLE contas_receber DROP COLUMN servico_id;
    END IF;
END $$;

-- 1.2 movimentacoes: remover servico_id (mantem pedido_id)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='movimentacoes' AND column_name='servico_id') THEN
        UPDATE movimentacoes SET servico_id = NULL WHERE servico_id IS NOT NULL;
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='movimentacoes_servico_id_fkey') THEN
            ALTER TABLE movimentacoes DROP CONSTRAINT movimentacoes_servico_id_fkey;
        END IF;
        DROP INDEX IF EXISTS idx_movimentacoes_servico;
        ALTER TABLE movimentacoes DROP COLUMN servico_id;
    END IF;
END $$;

-- 1.3 pedidos: remover orcamento_id
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pedidos' AND column_name='orcamento_id') THEN
        UPDATE pedidos SET orcamento_id = NULL WHERE orcamento_id IS NOT NULL;
        DROP INDEX IF EXISTS uq_pedidos_orcamento_ativo;
        DROP INDEX IF EXISTS idx_pedidos_orcamento_id;
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='pedidos_orcamento_id_fkey') THEN
            ALTER TABLE pedidos DROP CONSTRAINT pedidos_orcamento_id_fkey;
        END IF;
        ALTER TABLE pedidos DROP COLUMN orcamento_id;
    END IF;
END $$;

-- 1.4 pedido_nfe: remover servico_id + constraint de origem unica
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint
               WHERE conname='chk_pedido_nfe_origem_unica'
                 AND conrelid='pedido_nfe'::regclass) THEN
        ALTER TABLE pedido_nfe DROP CONSTRAINT chk_pedido_nfe_origem_unica;
    END IF;
    DROP INDEX IF EXISTS idx_pedido_nfe_servico_id;
    DROP INDEX IF EXISTS uq_pedido_nfe_servico_id;
    DROP INDEX IF EXISTS uq_pedido_nfe_pedido_id;

    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pedido_nfe' AND column_name='servico_id') THEN
        IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='pedido_nfe_servico_id_fkey') THEN
            ALTER TABLE pedido_nfe DROP CONSTRAINT pedido_nfe_servico_id_fkey;
        END IF;
        ALTER TABLE pedido_nfe DROP COLUMN servico_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pedido_nfe' AND column_name='pedido_id'
                 AND is_nullable='YES') THEN
        EXECUTE 'ALTER TABLE pedido_nfe ALTER COLUMN pedido_id SET NOT NULL';
    END IF;

    CREATE UNIQUE INDEX IF NOT EXISTS uq_pedido_nfe_pedido_id
        ON pedido_nfe(pedido_id);
END $$;

-- 1.5 orcamento_itens: remover FK para servicos_catalogo (sera removida com orcamento_itens)
-- 1.6 servicos: dependencias ja listadas abaixo; servicos_catalogo e MANTIDA.

-- ---------------------------------------------------------------------------
-- FASE 2 - DROP tabelas removiveis
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS servico_itens CASCADE;
DROP TABLE IF EXISTS servicos CASCADE;
DROP TABLE IF EXISTS orcamento_itens CASCADE;
DROP TABLE IF EXISTS orcamentos CASCADE;

-- Tabelas preservadas (D1): empresa_fiscal_servico, nota_fiscal_servico, servicos_catalogo
-- Porem nota_fiscal_servico.servico_id ficaria orfao sem a tabela servicos.
-- Como D1 = manter para NFS-e futura, limpamos os dados e removemos a FK quebrada:

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables
               WHERE table_name='nota_fiscal_servico') THEN
        TRUNCATE TABLE nota_fiscal_servico RESTART IDENTITY;
        IF EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conname='nota_fiscal_servico_servico_id_fkey') THEN
            ALTER TABLE nota_fiscal_servico DROP CONSTRAINT nota_fiscal_servico_servico_id_fkey;
        END IF;
        ALTER TABLE nota_fiscal_servico ALTER COLUMN servico_id DROP NOT NULL;
        COMMENT ON COLUMN nota_fiscal_servico.servico_id IS
            'Placeholder para vinculo futuro com nova tabela de servicos (mini-modulo).';
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- FASE 3 - Limpar seeds de modulos e permissoes
-- ---------------------------------------------------------------------------

DO $$
DECLARE
    v_ids INT[];
BEGIN
    SELECT COALESCE(array_agg(id), ARRAY[]::INT[])
      INTO v_ids
      FROM modulos
     WHERE slug IN ('orcamentos', 'fiscal_servico');

    IF array_length(v_ids, 1) IS NOT NULL THEN
        DELETE FROM permissoes_usuario WHERE modulo_id = ANY(v_ids);
        DELETE FROM permissoes_nivel   WHERE modulo_id = ANY(v_ids);
        DELETE FROM modulos            WHERE id = ANY(v_ids);
    END IF;
END $$;

-- ---------------------------------------------------------------------------
-- FASE 4 - Correcoes estruturais do PDV
-- ---------------------------------------------------------------------------

-- 4.1 movimentacoes.protegido + trigger
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='movimentacoes' AND column_name='protegido') THEN
        ALTER TABLE movimentacoes
            ADD COLUMN protegido BOOLEAN NOT NULL DEFAULT FALSE;
        COMMENT ON COLUMN movimentacoes.protegido IS
            'TRUE quando movimentacao foi gerada pelo PDV e nao pode ser excluida manualmente.';
    END IF;
END $$;

CREATE OR REPLACE FUNCTION bloquear_delete_movimentacao_protegida()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Movimentacao protegida (origem PDV). Cancele a venda em vez de excluir.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bloquear_delete_mov_protegida ON movimentacoes;
CREATE TRIGGER trg_bloquear_delete_mov_protegida
    BEFORE DELETE ON movimentacoes
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_movimentacao_protegida();

-- 4.2 contas_receber.origem CHECK
DO $$
BEGIN
    UPDATE contas_receber SET origem = 'MANUAL' WHERE origem IS NULL OR origem = '';
    UPDATE contas_receber SET origem = 'MANUAL' WHERE origem = 'ORCAMENTO';
    UPDATE contas_receber SET origem = 'MANUAL' WHERE origem = 'SERVICO';
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_contas_receber_origem') THEN
        ALTER TABLE contas_receber DROP CONSTRAINT chk_contas_receber_origem;
    END IF;
    ALTER TABLE contas_receber
        ADD CONSTRAINT chk_contas_receber_origem
        CHECK (origem IS NULL OR origem IN ('MANUAL','PEDIDO','PDV','IMPORTACAO'));
END $$;

-- 4.3 pdv_caixas: totais de vendas
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_caixas' AND column_name='total_vendas') THEN
        ALTER TABLE pdv_caixas
            ADD COLUMN total_vendas NUMERIC(15,2) NOT NULL DEFAULT 0,
            ADD COLUMN qtd_vendas INTEGER NOT NULL DEFAULT 0;
        COMMENT ON COLUMN pdv_caixas.total_vendas IS
            'Soma acumulada de pdv_lancamentos.valor_total neste caixa.';
        COMMENT ON COLUMN pdv_caixas.qtd_vendas IS
            'Quantidade de lancamentos registrados neste caixa.';
    END IF;
END $$;

-- 4.4 pdv_lancamentos: remover fluxo de servico e criar FK real para pedidos
DO $$
BEGIN
    -- Remove lancamentos de servicos (modulo descontinuado)
    DELETE FROM pdv_lancamentos WHERE tipo = 'servico';

    -- Redefinir CHECK do tipo
    IF EXISTS (SELECT 1 FROM pg_constraint
               WHERE conname LIKE 'pdv_lancamentos_tipo_check'
                  OR conname = 'pdv_lancamentos_tipo_check1') THEN
        ALTER TABLE pdv_lancamentos DROP CONSTRAINT IF EXISTS pdv_lancamentos_tipo_check;
    END IF;
    ALTER TABLE pdv_lancamentos
        ADD CONSTRAINT pdv_lancamentos_tipo_check CHECK (tipo IN ('pedido'));

    -- Drop indices antigos
    DROP INDEX IF EXISTS uq_pdv_lancamentos_referencia;
    DROP INDEX IF EXISTS idx_pdv_lancamentos_ref;

    -- Renomear referencia_id -> pedido_id
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pdv_lancamentos' AND column_name='referencia_id')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns
                       WHERE table_name='pdv_lancamentos' AND column_name='pedido_id') THEN
        ALTER TABLE pdv_lancamentos RENAME COLUMN referencia_id TO pedido_id;
    END IF;

    -- Criar FK real
    IF NOT EXISTS (SELECT 1 FROM pg_constraint
                   WHERE conname='pdv_lancamentos_pedido_id_fkey') THEN
        ALTER TABLE pdv_lancamentos
            ADD CONSTRAINT pdv_lancamentos_pedido_id_fkey
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE RESTRICT;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_pdv_lancamentos_pedido
    ON pdv_lancamentos(pedido_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_pdv_lancamentos_pedido_unico
    ON pdv_lancamentos(pedido_id);

-- ---------------------------------------------------------------------------
-- FASE 5 - Validacao
-- ---------------------------------------------------------------------------

DO $$
BEGIN
    ASSERT NOT EXISTS (SELECT 1 FROM information_schema.tables
                       WHERE table_name IN ('orcamentos','orcamento_itens','servicos','servico_itens')),
           'Tabelas removiveis ainda presentes';
    ASSERT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='movimentacoes' AND column_name='protegido'),
           'Coluna protegido nao criada';
    ASSERT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_lancamentos' AND column_name='pedido_id'),
           'Coluna pedido_id nao renomeada';
    RAISE NOTICE 'Migration aplicada com sucesso.';
END $$;

COMMIT;
