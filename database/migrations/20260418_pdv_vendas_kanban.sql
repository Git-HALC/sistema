-- ============================================================================
-- MIGRATION 2026-04-18 - PDV: estados Kanban em pdv_vendas
-- Expande status: +pendente,+em_processo,+concluido
-- forma_pagamento_id agora opcional (obrigatorio apenas quando status=faturado)
-- ============================================================================

-- 1. Expandir CHECK de status
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_pdv_vendas_status') THEN
        ALTER TABLE pdv_vendas DROP CONSTRAINT chk_pdv_vendas_status;
    END IF;
    ALTER TABLE pdv_vendas
        ADD CONSTRAINT chk_pdv_vendas_status
        CHECK (status IN ('pendente','em_processo','concluido','faturado','cancelado'));
END $$;

-- 2. forma_pagamento_id opcional com regra condicional
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='pdv_vendas' AND column_name='forma_pagamento_id'
                 AND is_nullable='NO') THEN
        ALTER TABLE pdv_vendas ALTER COLUMN forma_pagamento_id DROP NOT NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_pdv_vendas_forma_obrig') THEN
        ALTER TABLE pdv_vendas
            ADD CONSTRAINT chk_pdv_vendas_forma_obrig
            CHECK (status <> 'faturado' OR forma_pagamento_id IS NOT NULL);
    END IF;
END $$;

-- 3. Data de faturamento (momento em que saiu do Kanban para faturado)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='faturada_em') THEN
        ALTER TABLE pdv_vendas ADD COLUMN faturada_em TIMESTAMP WITH TIME ZONE;
    END IF;
END $$;

-- 4. Indice composto para Kanban (filtrar por caixa+status+origem)
CREATE INDEX IF NOT EXISTS idx_pdv_vendas_kanban
    ON pdv_vendas(caixa_id, status, created_at DESC)
    WHERE origem = 'fluxo' AND status <> 'cancelado';
