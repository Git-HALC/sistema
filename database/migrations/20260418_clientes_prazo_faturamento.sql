-- ============================================================================
-- MIGRATION 2026-04-18 - clientes.prazo_faturamento_dias
-- Usado nas contas a receber 'A Faturar' (AF): vencimento = hoje + prazo.
-- Zero = fallback de 30 dias aplicado pelo Service.
-- ============================================================================

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='clientes' AND column_name='prazo_faturamento_dias') THEN
        ALTER TABLE clientes
            ADD COLUMN prazo_faturamento_dias INTEGER NOT NULL DEFAULT 0
                CONSTRAINT chk_clientes_prazo_faturamento CHECK (prazo_faturamento_dias >= 0);
        COMMENT ON COLUMN clientes.prazo_faturamento_dias IS
            'Prazo padrão (dias) para vencimento de CR origem PDV A Faturar. 0 = usa 30 como fallback.';
    END IF;
END $$;
