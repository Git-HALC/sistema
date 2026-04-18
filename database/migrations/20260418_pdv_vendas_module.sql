-- ============================================================================
-- MIGRATION 2026-04-18 - PDV: tabela de vendas propria
-- Cria pdv_vendas + pdv_venda_itens, protege movimentacoes, seed modulo PDV
-- ============================================================================

-- Tabela pdv_vendas
CREATE TABLE IF NOT EXISTS pdv_vendas (
    id                 SERIAL PRIMARY KEY,
    caixa_id           INTEGER NOT NULL REFERENCES pdv_caixas(id),
    usuario_id         INTEGER NOT NULL REFERENCES usuarios(id),
    cliente_id         INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
    numero             BIGSERIAL UNIQUE NOT NULL,
    status             VARCHAR(20) NOT NULL DEFAULT 'faturado'
                           CONSTRAINT chk_pdv_vendas_status CHECK (status IN ('faturado','cancelado')),
    forma_pagamento_id INTEGER NOT NULL REFERENCES formas_pagamento(id),
    desconto_tipo      VARCHAR(20)
                           CONSTRAINT chk_pdv_vendas_desc_tipo CHECK (desconto_tipo IS NULL OR desconto_tipo IN ('VALOR','PERCENTUAL')),
    desconto_valor     NUMERIC(15,2) NOT NULL DEFAULT 0
                           CONSTRAINT chk_pdv_vendas_desc_val CHECK (desconto_valor >= 0),
    valor_total        NUMERIC(15,4) NOT NULL
                           CONSTRAINT chk_pdv_vendas_valor CHECK (valor_total >= 0),
    observacoes        TEXT,
    origem             VARCHAR(20) NOT NULL DEFAULT 'rapida'
                           CONSTRAINT chk_pdv_vendas_origem CHECK (origem IN ('rapida','fluxo')),
    protegido          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE  pdv_vendas IS 'Vendas efetivadas no PDV. Substitui uso direto de pedidos para venda rapida.';
COMMENT ON COLUMN pdv_vendas.protegido IS 'TRUE: nao pode ser excluida, apenas cancelada (status=cancelado).';
COMMENT ON COLUMN pdv_vendas.origem IS 'rapida = modo terminal; fluxo = fluxo completo (nao implementado).';

-- Trigger updated_at
DROP TRIGGER IF EXISTS trg_pdv_vendas_updated_at ON pdv_vendas;
CREATE TRIGGER trg_pdv_vendas_updated_at
    BEFORE UPDATE ON pdv_vendas
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Proteger DELETE de vendas protegidas
CREATE OR REPLACE FUNCTION bloquear_delete_venda_protegida()
RETURNS TRIGGER AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Venda PDV protegida. Use cancelamento (status=cancelado) em vez de DELETE.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_bloquear_delete_pdv_venda ON pdv_vendas;
CREATE TRIGGER trg_bloquear_delete_pdv_venda
    BEFORE DELETE ON pdv_vendas
    FOR EACH ROW
    EXECUTE FUNCTION bloquear_delete_venda_protegida();

-- Tabela pdv_venda_itens
CREATE TABLE IF NOT EXISTS pdv_venda_itens (
    id               SERIAL PRIMARY KEY,
    venda_id         INTEGER NOT NULL REFERENCES pdv_vendas(id) ON DELETE CASCADE,
    tipo_item        VARCHAR(20) NOT NULL
                         CONSTRAINT chk_pdv_venda_item_tipo CHECK (tipo_item IN ('PRODUTO','SERVICO')),
    produto_id       INTEGER REFERENCES produtos(id) ON DELETE SET NULL,
    servico_id       INTEGER REFERENCES servicos_catalogo(id) ON DELETE SET NULL,
    nome_item        VARCHAR(255) NOT NULL,
    quantidade       NUMERIC(15,4) NOT NULL
                         CONSTRAINT chk_pdv_venda_item_qtd CHECK (quantidade > 0),
    valor_unitario   NUMERIC(15,4) NOT NULL
                         CONSTRAINT chk_pdv_venda_item_vu CHECK (valor_unitario >= 0),
    valor_total_item NUMERIC(15,4) NOT NULL
                         CONSTRAINT chk_pdv_venda_item_vt CHECK (valor_total_item >= 0),
    created_at       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE pdv_venda_itens IS 'Itens (produto ou servico) de cada venda PDV. Snapshot no nome_item.';

-- Garantir coerencia: produto_id <-> tipo_item=PRODUTO, servico_id <-> tipo_item=SERVICO
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='chk_pdv_venda_item_ref') THEN
        ALTER TABLE pdv_venda_itens
            ADD CONSTRAINT chk_pdv_venda_item_ref CHECK (
                (tipo_item = 'PRODUTO' AND servico_id IS NULL)
                OR
                (tipo_item = 'SERVICO' AND produto_id IS NULL)
            );
    END IF;
END $$;

-- movimentacoes.protegido (caso nao exista ainda)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='movimentacoes' AND column_name='protegido') THEN
        ALTER TABLE movimentacoes ADD COLUMN protegido BOOLEAN NOT NULL DEFAULT FALSE;
    END IF;
END $$;

-- Indices
CREATE INDEX IF NOT EXISTS idx_pdv_vendas_caixa    ON pdv_vendas(caixa_id);
CREATE INDEX IF NOT EXISTS idx_pdv_vendas_status   ON pdv_vendas(status);
CREATE INDEX IF NOT EXISTS idx_pdv_vendas_numero   ON pdv_vendas(numero);
CREATE INDEX IF NOT EXISTS idx_pdv_vendas_data     ON pdv_vendas(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_pdv_venda_itens_venda ON pdv_venda_itens(venda_id);

-- Seed modulo PDV
INSERT INTO modulos (slug, nome, icone, ordem)
VALUES ('pdv', 'PDV', 'fa-cash-register', 10)
ON CONFLICT (slug) DO UPDATE SET nome='PDV', icone='fa-cash-register', ordem=10;

INSERT INTO permissoes_nivel (nivel_acesso_id, modulo_id)
SELECT 1, id FROM modulos WHERE slug='pdv'
ON CONFLICT DO NOTHING;
