-- ============================================================================
-- MIGRATION 2026-04-18 - AUDITORIA FINAL
-- Correcoes de consistencia identificadas no passo final da auditoria:
-- 1. Indice duplicado em categorias_dre (nome)
-- 2. Modulos faltantes na tabela modulos: usuarios, relatorios
-- ============================================================================

-- 1. Remover indice duplicado (mantem categorias_dre_nome_unique criado
--    explicitamente; _key eh auto-gerado por uma constraint antiga)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname='categorias_dre_nome_key' AND conrelid='categorias_dre'::regclass) THEN
        ALTER TABLE categorias_dre DROP CONSTRAINT categorias_dre_nome_key;
    END IF;
END $$;

-- 2. Adicionar modulos usuarios e relatorios (agregador)
INSERT INTO modulos (slug, nome, icone, ordem)
VALUES
    ('usuarios',   'Usuarios',   'fa-users-gear',  11),
    ('relatorios', 'Relatorios', 'fa-file-alt',    12)
ON CONFLICT (slug) DO NOTHING;

-- Dar permissao ao nivel admin (id=1)
INSERT INTO permissoes_nivel (nivel_acesso_id, modulo_id)
SELECT 1, id FROM modulos WHERE slug IN ('usuarios', 'relatorios')
ON CONFLICT DO NOTHING;
