-- ============================================================================
-- MIGRATION 2026-04-18 - PDV: fluxo de vendas (listagem + cancelamento)
-- + remove modulo obsoleto 'pedidos' do menu (Gestao_Pedidos ja removido)
-- Tabelas pedidos / pedido_itens: MANTIDAS como legado historico.
-- ============================================================================

-- 1. Colunas de cancelamento em pdv_vendas
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='cancelado_por_id') THEN
        ALTER TABLE pdv_vendas
            ADD COLUMN cancelado_por_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='cancelado_em') THEN
        ALTER TABLE pdv_vendas ADD COLUMN cancelado_em TIMESTAMP WITH TIME ZONE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='pdv_vendas' AND column_name='motivo_cancelamento') THEN
        ALTER TABLE pdv_vendas ADD COLUMN motivo_cancelamento TEXT;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_pdv_vendas_cancelado_por ON pdv_vendas(cancelado_por_id)
    WHERE cancelado_por_id IS NOT NULL;

-- 2. Remover modulo 'pedidos' (Gestao_Pedidos removido na Fase 1, menu legado pendente)
DO $$
DECLARE
    v_id INT;
BEGIN
    SELECT id INTO v_id FROM modulos WHERE slug='pedidos';
    IF v_id IS NOT NULL THEN
        DELETE FROM permissoes_usuario WHERE modulo_id = v_id;
        DELETE FROM permissoes_nivel   WHERE modulo_id = v_id;
        DELETE FROM modulos            WHERE id = v_id;
    END IF;
END $$;

-- 3. Comentario de legado em pedidos / pedido_itens
COMMENT ON TABLE pedidos IS
    'LEGADO HISTORICO. Gestao_Pedidos removido em 2026-04-18. Vendas novas em pdv_vendas.';
COMMENT ON TABLE pedido_itens IS
    'LEGADO HISTORICO. Itens dos pedidos descontinuados.';
