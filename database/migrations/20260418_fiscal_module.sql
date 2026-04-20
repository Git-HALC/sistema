-- ============================================================================
-- MIGRATION 2026-04-18 - Modulo Fiscal completo (NFC-e + NFS-e Nacional)
-- Novo escopo (ancorado em pdv_vendas / pdv_venda_itens):
--   - perfil_tributario   : config empresa (SEFAZ NFC-e + Emissor Nacional NFS-e)
--   - tributacao_por_estado : aliquotas ICMS/FCP/Simples por UF destino
--   - fiscal_nfce         : NFC-e emitidas (produtos)
--   - fiscal_nfse         : NFS-e emitidas (servicos, via Emissor Nacional gov.br)
--   - fiscal_servicos_config : config fiscal por servico (LC 116, ISS, retencoes)
-- ============================================================================

BEGIN;

-- ── perfil_tributario ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS perfil_tributario (
    id                      SERIAL PRIMARY KEY,
    empresa_local_id        INTEGER NOT NULL DEFAULT 1,
    regime_tributario       VARCHAR(20) NOT NULL DEFAULT 'simples_nacional'
                            CHECK (regime_tributario IN ('simples_nacional','lucro_presumido','lucro_real')),
    -- NFC-e / SEFAZ
    csc_id_homologacao      VARCHAR(10),
    csc_token_homologacao   VARCHAR(64),
    csc_id_producao         VARCHAR(10),
    csc_token_producao      VARCHAR(64),
    serie_nfce              SMALLINT DEFAULT 1,
    numero_nfce_atual       INTEGER DEFAULT 0,
    ambiente_nfce           SMALLINT DEFAULT 2 CHECK (ambiente_nfce IN (1,2)),
    -- NFS-e Nacional (nfse.gov.br)
    nfse_modo_auth          VARCHAR(20) DEFAULT 'usuario_senha'
                            CHECK (nfse_modo_auth IN ('usuario_senha','certificado','govbr')),
    nfse_usuario            VARCHAR(100),
    nfse_senha_cifrada      TEXT,   -- openssl_encrypt AES-256-CBC com chave do .env
    nfse_token              TEXT,
    nfse_token_expira_em    TIMESTAMP,
    nfse_certificado_path   VARCHAR(255),
    nfse_certificado_senha_cifrada TEXT,
    serie_rps               VARCHAR(5)  DEFAULT 'RPS',
    numero_rps_atual        INTEGER     DEFAULT 0,
    ambiente_nfse           VARCHAR(15) DEFAULT 'homologacao'
                            CHECK (ambiente_nfse IN ('homologacao','producao')),
    -- Controle
    ativo                   BOOLEAN DEFAULT TRUE,
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT uq_perfil_empresa UNIQUE (empresa_local_id)
);

COMMENT ON TABLE perfil_tributario IS
    'Perfil tributario 1:1 com empresa_local. Singleton por tenant. Senhas cifradas com chave em .env (FISCAL_ENCRYPTION_KEY).';

-- ── tributacao_por_estado ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tributacao_por_estado (
    id                    SERIAL PRIMARY KEY,
    perfil_id             INTEGER NOT NULL REFERENCES perfil_tributario(id) ON DELETE CASCADE,
    uf_origem             CHAR(2) NOT NULL,
    uf_destino            CHAR(2) NOT NULL,
    icms_aliquota         NUMERIC(5,2) DEFAULT 0,
    icms_aliquota_inter   NUMERIC(5,2) DEFAULT 0,
    icms_reducao_bc       NUMERIC(5,2) DEFAULT 0,
    icms_diferimento      NUMERIC(5,2) DEFAULT 0,
    difal_aliquota        NUMERIC(5,2) DEFAULT 0,
    difal_partilha_dest   NUMERIC(5,2) DEFAULT 100,
    fcp_aliquota          NUMERIC(5,2) DEFAULT 0,
    simples_anexo         SMALLINT,
    simples_aliquota      NUMERIC(5,2) DEFAULT 0,
    simples_deducao       NUMERIC(10,2) DEFAULT 0,
    ativo                 BOOLEAN DEFAULT TRUE,
    created_at            TIMESTAMPTZ DEFAULT NOW(),
    updated_at            TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT uq_trib_ufs UNIQUE (perfil_id, uf_origem, uf_destino)
);

CREATE INDEX IF NOT EXISTS idx_trib_perfil_ufs
    ON tributacao_por_estado(perfil_id, uf_origem, uf_destino);

-- ── fiscal_nfce (NFC-e emitidas — ancorada em pdv_vendas) ─────────────────
CREATE TABLE IF NOT EXISTS fiscal_nfce (
    id                  SERIAL PRIMARY KEY,
    venda_id            INTEGER NOT NULL REFERENCES pdv_vendas(id) ON DELETE RESTRICT,
    numero              INTEGER NOT NULL,
    serie               SMALLINT NOT NULL DEFAULT 1,
    chave_acesso        CHAR(44),
    protocolo           VARCHAR(20),
    xml_enviado         TEXT,
    xml_retorno         TEXT,
    xml_autorizado      TEXT,
    status              VARCHAR(20) NOT NULL DEFAULT 'pendente'
                        CHECK (status IN ('pendente','autorizada','cancelada','rejeitada','contingencia')),
    ambiente            SMALLINT NOT NULL DEFAULT 2,
    valor_total         NUMERIC(12,2),
    data_emissao        TIMESTAMPTZ DEFAULT NOW(),
    data_autorizacao    TIMESTAMPTZ,
    motivo_rejeicao     TEXT,
    qrcode_url          TEXT,
    danfe_path          TEXT,
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_fiscal_nfce_venda  ON fiscal_nfce(venda_id);
CREATE INDEX IF NOT EXISTS idx_fiscal_nfce_status ON fiscal_nfce(status);
CREATE INDEX IF NOT EXISTS idx_fiscal_nfce_chave  ON fiscal_nfce(chave_acesso);

-- ── fiscal_nfse (NFS-e Nacional — ancorada em pdv_vendas) ─────────────────
CREATE TABLE IF NOT EXISTS fiscal_nfse (
    id                  SERIAL PRIMARY KEY,
    venda_id            INTEGER NOT NULL REFERENCES pdv_vendas(id) ON DELETE RESTRICT,
    numero_rps          INTEGER NOT NULL,
    serie_rps           VARCHAR(5) DEFAULT 'RPS',
    numero_nfse         VARCHAR(20),
    codigo_verificacao  VARCHAR(50),
    xml_rps_enviado     TEXT,
    xml_nfse_retorno    TEXT,
    status              VARCHAR(20) NOT NULL DEFAULT 'pendente'
                        CHECK (status IN ('pendente','autorizada','cancelada','rejeitada','processando')),
    ambiente            VARCHAR(15) DEFAULT 'homologacao',
    valor_servicos      NUMERIC(12,2),
    valor_iss           NUMERIC(12,2),
    aliquota_iss        NUMERIC(5,2),
    codigo_servico      VARCHAR(10),
    discriminacao       TEXT,
    data_emissao        TIMESTAMPTZ DEFAULT NOW(),
    data_autorizacao    TIMESTAMPTZ,
    motivo_rejeicao     TEXT,
    link_nfse           TEXT,
    pdf_path            TEXT,
    created_at          TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_fiscal_nfse_venda  ON fiscal_nfse(venda_id);
CREATE INDEX IF NOT EXISTS idx_fiscal_nfse_status ON fiscal_nfse(status);

-- ── fiscal_servicos_config (ISS / LC 116 por servico) ─────────────────────
CREATE TABLE IF NOT EXISTS fiscal_servicos_config (
    id                  SERIAL PRIMARY KEY,
    servico_id          INTEGER REFERENCES servicos_catalogo(id) ON DELETE CASCADE,
    codigo_lc116        VARCHAR(10) NOT NULL,
    descricao_lc116     VARCHAR(255),
    cnae                VARCHAR(10),
    codigo_municipio    VARCHAR(10),
    iss_aliquota        NUMERIC(5,2) DEFAULT 0,
    iss_retido          BOOLEAN DEFAULT FALSE,
    pis_aliquota        NUMERIC(5,4) DEFAULT 0,
    cofins_aliquota     NUMERIC(5,4) DEFAULT 0,
    csll_aliquota       NUMERIC(5,4) DEFAULT 0,
    ir_aliquota         NUMERIC(5,4) DEFAULT 0,
    inss_aliquota       NUMERIC(5,4) DEFAULT 0,
    ativo               BOOLEAN DEFAULT TRUE,
    created_at          TIMESTAMPTZ DEFAULT NOW(),
    updated_at          TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT uq_fsc_servico UNIQUE (servico_id)
);

CREATE INDEX IF NOT EXISTS idx_fsc_servico ON fiscal_servicos_config(servico_id);

-- ── Trigger updated_at ────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION fiscal_touch_updated_at() RETURNS trigger AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_perfil_tributario_touch') THEN
        CREATE TRIGGER trg_perfil_tributario_touch BEFORE UPDATE ON perfil_tributario
            FOR EACH ROW EXECUTE FUNCTION fiscal_touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_tributacao_touch') THEN
        CREATE TRIGGER trg_tributacao_touch BEFORE UPDATE ON tributacao_por_estado
            FOR EACH ROW EXECUTE FUNCTION fiscal_touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_fsc_touch') THEN
        CREATE TRIGGER trg_fsc_touch BEFORE UPDATE ON fiscal_servicos_config
            FOR EACH ROW EXECUTE FUNCTION fiscal_touch_updated_at();
    END IF;
END $$;

-- ── Seed: perfil_tributario singleton vinculado a empresa_local(id=1) ─────
INSERT INTO perfil_tributario (empresa_local_id, regime_tributario)
SELECT 1, 'simples_nacional'
WHERE NOT EXISTS (SELECT 1 FROM perfil_tributario WHERE empresa_local_id = 1);

-- ── Seed: tributacao_por_estado default (UF origem = empresa_local.uf) ────
DO $$
DECLARE
    perfilId   INTEGER;
    ufEmit     CHAR(2);
    aliqInter  NUMERIC(5,2);
    rec        RECORD;
BEGIN
    SELECT id INTO perfilId FROM perfil_tributario WHERE empresa_local_id = 1 LIMIT 1;
    SELECT UPPER(COALESCE(uf, 'SP'))::CHAR(2) INTO ufEmit FROM empresa_local WHERE id = 1;

    IF perfilId IS NULL THEN RETURN; END IF;

    FOR rec IN
        SELECT uf FROM (VALUES
            ('AC'),('AL'),('AM'),('AP'),('BA'),('CE'),('DF'),('ES'),('GO'),('MA'),
            ('MG'),('MS'),('MT'),('PA'),('PB'),('PE'),('PI'),('PR'),('RJ'),('RN'),
            ('RO'),('RR'),('RS'),('SC'),('SE'),('SP'),('TO')
        ) AS t(uf)
    LOOP
        aliqInter := 12.0;
        -- Aliquota interestadual: 7% de S/SE para N/NE/CO
        IF ufEmit IN ('SP','RJ','MG','RS','SC','PR','ES') AND
           rec.uf NOT IN ('SP','RJ','MG','RS','SC','PR','ES') THEN
            aliqInter := 7.0;
        END IF;
        -- Origem = destino => interna (default 0, usuario edita)
        IF ufEmit = rec.uf THEN aliqInter := 0; END IF;

        INSERT INTO tributacao_por_estado
            (perfil_id, uf_origem, uf_destino, icms_aliquota_inter)
        VALUES (perfilId, ufEmit, rec.uf, aliqInter)
        ON CONFLICT (perfil_id, uf_origem, uf_destino) DO NOTHING;
    END LOOP;
END $$;

-- ── Registry ──────────────────────────────────────────────────────────────
INSERT INTO schema_migrations (version, checksum, executed_at)
VALUES ('20260418_fiscal_module', MD5('20260418_fiscal_module'), NOW())
ON CONFLICT (version) DO NOTHING;

COMMIT;
