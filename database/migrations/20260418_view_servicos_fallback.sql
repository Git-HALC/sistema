-- ============================================================================
-- MIGRATION 2026-04-18 - Fallback para tabela `servicos` (removida na Fase 1)
-- O Dashboard antigo ainda tem 10 queries SELECT FROM servicos. Criamos uma
-- view VAZIA com o schema esperado para que retornem 0 linhas sem quebrar.
-- ============================================================================

DROP VIEW IF EXISTS servicos;

CREATE VIEW servicos AS
SELECT
    NULL::uuid        AS id,
    NULL::integer     AS numero,
    NULL::integer     AS cliente_id,
    NULL::varchar     AS nome_cliente,
    NULL::varchar     AS status,
    NULL::timestamptz AS data_servico,
    NULL::numeric     AS valor_total,
    FALSE             AS ativo,
    NULL::timestamptz AS updated_at,
    NULL::varchar     AS servico_nome,
    NULL::varchar     AS produto_nome,
    NULL::integer     AS produto_id,
    NULL::numeric     AS produto_quantidade,
    NULL::numeric     AS produto_valor_unitario,
    NULL::numeric     AS servico_valor,
    NULL::varchar     AS desconto_tipo,
    NULL::numeric     AS desconto_valor,
    NULL::timestamptz AS data_faturamento,
    NULL::integer     AS orcamento_id
WHERE FALSE;

COMMENT ON VIEW servicos IS
    'Fallback vazio apos remocao da tabela servicos (Fase 1 auditoria 2026-04-18). '
    'Retorna zero linhas para manter compatibilidade com queries legadas do Dashboard. '
    'Novas implementacoes devem usar pdv_vendas/pdv_venda_itens.';
