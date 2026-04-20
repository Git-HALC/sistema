-- ============================================================================
-- MIGRATION 2026-04-20 - Dedup de VENDA_COMPETENCIA + pdv_venda_id
--
-- Adiciona coluna pdv_venda_id em movimentacoes e indice unico parcial para
-- impedir duplicacao futura de VENDA_COMPETENCIA por venda.
-- ============================================================================

SET client_encoding = 'UTF8';
BEGIN;

-- 1. Nova coluna
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='movimentacoes' AND column_name='pdv_venda_id') THEN
        ALTER TABLE movimentacoes
            ADD COLUMN pdv_venda_id INTEGER REFERENCES pdv_vendas(id) ON DELETE SET NULL;
    END IF;
END $$;

-- 2. Backfill pdv_venda_id a partir da descricao "Venda PDV #N"
UPDATE movimentacoes m
   SET pdv_venda_id = v.id
  FROM pdv_vendas v
 WHERE m.pdv_venda_id IS NULL
   AND m.descricao ~ 'Venda PDV #[0-9]+'
   AND v.numero = (
       substring(m.descricao FROM 'Venda PDV #([0-9]+)')::int
   );

-- 3. Deduplica VENDA_COMPETENCIA — mantem o registro mais antigo por venda
WITH duplicatas AS (
    SELECT id,
           ROW_NUMBER() OVER (PARTITION BY pdv_venda_id, tipo_origem ORDER BY id) AS rn
      FROM movimentacoes
     WHERE tipo_origem = 'VENDA_COMPETENCIA' AND pdv_venda_id IS NOT NULL
)
UPDATE movimentacoes SET protegido = FALSE
 WHERE id IN (SELECT id FROM duplicatas WHERE rn > 1);

DELETE FROM movimentacoes
 WHERE id IN (
     SELECT id FROM (
         SELECT id,
                ROW_NUMBER() OVER (PARTITION BY pdv_venda_id, tipo_origem ORDER BY id) AS rn
           FROM movimentacoes
          WHERE tipo_origem = 'VENDA_COMPETENCIA' AND pdv_venda_id IS NOT NULL
     ) d WHERE rn > 1
 );

-- 4. Taxa de cartao NAO deve ser protegida (permite estorno sem bloqueio)
UPDATE movimentacoes
   SET protegido = FALSE
 WHERE tipo_origem = 'TAXA_CARTAO';

-- 5. Indice unico parcial: 1 VENDA_COMPETENCIA por venda
CREATE UNIQUE INDEX IF NOT EXISTS uq_mov_venda_competencia
    ON movimentacoes (pdv_venda_id, tipo_origem)
 WHERE pdv_venda_id IS NOT NULL AND tipo_origem = 'VENDA_COMPETENCIA';

-- 6. Registry
INSERT INTO schema_migrations (version, checksum, executed_at)
VALUES ('20260420_mov_venda_id_dedup', MD5('20260420_mov_venda_id_dedup'), NOW())
ON CONFLICT (version) DO NOTHING;

COMMIT;
