-- ============================================================================
-- MIGRATION 2026-04-18 - DRE: view vw_dre
-- Agrega receitas pagas, pagamentos pagos, movimentacoes por categoria.
-- Nao filtra periodo; o periodo e aplicado pelo PHP via placeholders em WHERE.
-- NOTA: como esta view nao recebe parametros, o PHP preferira SQL inline
-- com placeholders de periodo; a view fica como fallback/consulta rapida.
-- ============================================================================

DROP VIEW IF EXISTS vw_dre;

CREATE OR REPLACE VIEW vw_dre AS
SELECT
    cd.id          AS categoria_id,
    cd.nome        AS categoria,
    cd.tipo,
    cd.ordem,
    COALESCE(cr.total, 0)        AS total_receber,
    COALESCE(cp.total, 0)        AS total_pagar,
    COALESCE(mov.total_entrada, 0) AS total_mov_entrada,
    COALESCE(mov.total_saida, 0)   AS total_mov_saida
FROM categorias_dre cd
LEFT JOIN (
    SELECT categoria_dre_id, SUM(valor_pago) AS total
      FROM contas_receber
     WHERE status = 'PAGO'
     GROUP BY categoria_dre_id
) cr ON cr.categoria_dre_id = cd.id
LEFT JOIN (
    SELECT categoria_dre_id, SUM(valor_pago) AS total
      FROM contas_pagar
     WHERE status = 'PAGO'
     GROUP BY categoria_dre_id
) cp ON cp.categoria_dre_id = cd.id
LEFT JOIN (
    SELECT categoria_dre_id,
           SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END) AS total_entrada,
           SUM(CASE WHEN tipo = 'Saída'   THEN valor ELSE 0 END) AS total_saida
      FROM movimentacoes
     WHERE afeta_saldo = TRUE
     GROUP BY categoria_dre_id
) mov ON mov.categoria_dre_id = cd.id
WHERE cd.ativo = TRUE
ORDER BY cd.ordem;

COMMENT ON VIEW vw_dre IS 'Visao agregada de categorias DRE x totais pagos/recebidos/movimentados (sem filtro de periodo).';
