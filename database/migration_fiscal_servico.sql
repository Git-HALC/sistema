BEGIN;

-- ============================================================
-- MÓDULO: FISCAL DE SERVIÇOS (NFS-e)
-- Observação:
-- - No projeto atual a NF-e de produto é persistida em pedido_nfe
-- - servicos.id é UUID, então o vínculo fiscal também precisa ser UUID
-- ============================================================

-- 1. Vínculo opcional da NF-e de produto com o serviço faturado
ALTER TABLE pedido_nfe
    ALTER COLUMN pedido_id DROP NOT NULL;

ALTER TABLE pedido_nfe
    DROP CONSTRAINT IF EXISTS pedido_nfe_pedido_id_key;

ALTER TABLE pedido_nfe
    ADD COLUMN IF NOT EXISTS servico_id UUID REFERENCES servicos(id) ON DELETE SET NULL;

COMMENT ON COLUMN pedido_nfe.servico_id IS
'Vinculo opcional com servico faturado quando a NF-e de produto for originada do modulo de servicos.';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM pg_constraint
         WHERE conname = 'chk_pedido_nfe_origem_unica'
           AND conrelid = 'pedido_nfe'::regclass
    ) THEN
        ALTER TABLE pedido_nfe
            ADD CONSTRAINT chk_pedido_nfe_origem_unica
            CHECK (num_nonnulls(pedido_id, servico_id) = 1);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_pedido_nfe_servico_id
    ON pedido_nfe(servico_id)
    WHERE servico_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_pedido_nfe_pedido_id
    ON pedido_nfe(pedido_id)
    WHERE pedido_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_pedido_nfe_servico_id
    ON pedido_nfe(servico_id)
    WHERE servico_id IS NOT NULL;

-- 2. Configurações do emitente para NFS-e
CREATE TABLE IF NOT EXISTS empresa_fiscal_servico (
    id                         SERIAL PRIMARY KEY,
    cnpj                       VARCHAR(14) NOT NULL,
    razao_social               VARCHAR(150) NOT NULL,
    inscricao_municipal        VARCHAR(20) NOT NULL,
    codigo_municipio_ibge      VARCHAR(7) NOT NULL,
    aliquota_iss_padrao        NUMERIC(5,2) NOT NULL DEFAULT 5.00,
    url_webservice_homologacao TEXT,
    url_webservice_producao    TEXT,
    usuario_webservice         VARCHAR(100),
    senha_webservice           VARCHAR(255),
    ambiente                   VARCHAR(20) NOT NULL DEFAULT 'homologacao',
    created_at                 TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at                 TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_empresa_fiscal_servico_ambiente
        CHECK (ambiente IN ('homologacao', 'producao')),
    CONSTRAINT chk_empresa_fiscal_servico_aliquota
        CHECK (aliquota_iss_padrao >= 0)
);

COMMENT ON TABLE empresa_fiscal_servico IS 'Configuracoes do emitente para integracao de NFS-e.';

-- 3. Notas fiscais de serviço (NFS-e)
CREATE TABLE IF NOT EXISTS nota_fiscal_servico (
    id                   SERIAL PRIMARY KEY,
    servico_id           UUID NOT NULL REFERENCES servicos(id) ON DELETE CASCADE,
    numero_nota          VARCHAR(20),
    serie                VARCHAR(5) NOT NULL DEFAULT 'RPS',
    numero_rps           INTEGER NOT NULL,
    status               VARCHAR(20) NOT NULL DEFAULT 'pendente',
    xml_enviado          TEXT,
    xml_retorno          TEXT,
    protocolo            VARCHAR(100),
    data_emissao         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    data_competencia     DATE NOT NULL,
    valor_servico        NUMERIC(12,2) NOT NULL,
    aliquota_iss         NUMERIC(5,2) NOT NULL,
    valor_iss            NUMERIC(12,2) NOT NULL,
    codigo_servico_lc116 VARCHAR(10),
    descricao_servico    TEXT NOT NULL,
    tomador_nome         VARCHAR(150),
    tomador_cpf_cnpj     VARCHAR(14),
    tomador_email        VARCHAR(150),
    tomador_logradouro   VARCHAR(200),
    tomador_municipio    VARCHAR(100),
    tomador_uf           VARCHAR(2),
    erro_mensagem        TEXT,
    created_at           TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_nota_fiscal_servico_status
        CHECK (status IN ('pendente', 'enviada', 'cancelada', 'erro')),
    CONSTRAINT chk_nota_fiscal_servico_valores
        CHECK (valor_servico >= 0 AND aliquota_iss >= 0 AND valor_iss >= 0)
);

COMMENT ON TABLE nota_fiscal_servico IS 'Notas fiscais de servico geradas a partir de servicos faturados.';
COMMENT ON COLUMN nota_fiscal_servico.servico_id IS 'Servico faturado que originou a NFS-e.';
COMMENT ON COLUMN nota_fiscal_servico.numero_rps IS 'Sequencial do RPS usado antes da autorizacao municipal.';

CREATE INDEX IF NOT EXISTS idx_nota_fiscal_servico_servico_id
    ON nota_fiscal_servico(servico_id);

CREATE INDEX IF NOT EXISTS idx_nota_fiscal_servico_status
    ON nota_fiscal_servico(status);

CREATE INDEX IF NOT EXISTS idx_nota_fiscal_servico_numero_rps
    ON nota_fiscal_servico(numero_rps);

CREATE INDEX IF NOT EXISTS idx_nota_fiscal_servico_data_emissao
    ON nota_fiscal_servico(data_emissao);

-- 4. Trigger de updated_at para ambas as tabelas
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM pg_trigger
         WHERE tgname = 'trg_empresa_fiscal_servico_updated_at'
    ) THEN
        CREATE TRIGGER trg_empresa_fiscal_servico_updated_at
            BEFORE UPDATE ON empresa_fiscal_servico
            FOR EACH ROW
            EXECUTE FUNCTION set_updated_at();
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM pg_trigger
         WHERE tgname = 'trg_nota_fiscal_servico_updated_at'
    ) THEN
        CREATE TRIGGER trg_nota_fiscal_servico_updated_at
            BEFORE UPDATE ON nota_fiscal_servico
            FOR EACH ROW
            EXECUTE FUNCTION set_updated_at();
    END IF;
END $$;

COMMIT;
