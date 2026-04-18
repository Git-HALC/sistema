-- ============================================================================
-- BANCO MASTER ? licencas_master
-- Sistema de controle de licen?as SaaS
-- PostgreSQL 13+ | Executar como superuser
-- ============================================================================
-- Vers?o: 1.1 ? Trigger de hist?rico autom?tico adicionado
-- Este banco ? seu (do dono do sistema) ? nenhum cliente tem acesso a ele
-- ============================================================================

-- Criar banco (execute separadamente se necess?rio)
-- CREATE DATABASE licencas_master ENCODING 'UTF8' LC_COLLATE='C' LC_CTYPE='C' TEMPLATE template0;
-- \c licencas_master;

-- Extens?es
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ============================================================================
-- TABELAS
-- ============================================================================

-- Tabela: admins
CREATE TABLE IF NOT EXISTS admins (
    id                  SERIAL          PRIMARY KEY,
    uuid                UUID            DEFAULT uuid_generate_v4() UNIQUE,
    nome                VARCHAR(100)    NOT NULL,
    email               VARCHAR(100)    NOT NULL UNIQUE,
    senha               VARCHAR(255)    NOT NULL,
    ativo               BOOLEAN         DEFAULT TRUE,
    ultimo_acesso       TIMESTAMP WITH TIME ZONE,
    token_reset_senha   VARCHAR(255),
    token_expiracao     TIMESTAMP WITH TIME ZONE,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: planos
CREATE TABLE IF NOT EXISTS planos (
    id                  SERIAL          PRIMARY KEY,
    slug                VARCHAR(30)     NOT NULL UNIQUE,
    nome                VARCHAR(100)    NOT NULL,
    descricao           TEXT,
    max_usuarios        INT             DEFAULT 1,
    ativo               BOOLEAN         DEFAULT TRUE,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: empresas
CREATE TABLE IF NOT EXISTS empresas (
    id                  SERIAL          PRIMARY KEY,
    uuid                UUID            DEFAULT uuid_generate_v4() UNIQUE,
    cnpj                VARCHAR(18)     NOT NULL UNIQUE,
    nome                VARCHAR(255)    NOT NULL,
    razao_social        VARCHAR(255),
    contato             VARCHAR(255),
    email               VARCHAR(255)    NOT NULL,
    telefone            VARCHAR(20),
    banco_dados         VARCHAR(100)    NOT NULL UNIQUE,
    licenca_tipo        VARCHAR(10)     NOT NULL DEFAULT 'mensal'
                            CONSTRAINT chk_empresas_licenca_tipo CHECK (licenca_tipo IN ('mensal', 'anual', 'trial')),
    licenca_inicio      DATE            NOT NULL DEFAULT CURRENT_DATE,
    licenca_fim         DATE            NOT NULL,
    dias_aviso          INT             NOT NULL DEFAULT 7,
    chave_licenca       VARCHAR(64)     NOT NULL UNIQUE,
    status              VARCHAR(15)     NOT NULL DEFAULT 'ativa'
                            CONSTRAINT chk_empresas_status CHECK (status IN ('ativa', 'bloqueada', 'trial', 'cancelada')),
    observacoes         TEXT,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON COLUMN empresas.banco_dados    IS 'Nome do banco PostgreSQL do cliente. Ex: suporte_empresax';
COMMENT ON COLUMN empresas.chave_licenca  IS 'SHA256(cnpj+timestamp+secret). Enviada ao cliente na instala??o.';
COMMENT ON COLUMN empresas.dias_aviso     IS 'Dias antes do vencimento para disparar e-mail de aviso.';

-- Coluna plano_id em empresas (idempotente) + FK + backfill
DO $$
DECLARE
    v_plano_profissional_id INT;
BEGIN
    SELECT id INTO v_plano_profissional_id
      FROM planos
     WHERE slug = 'profissional'
     ORDER BY id
     LIMIT 1;

    IF v_plano_profissional_id IS NULL THEN
        INSERT INTO planos (slug, nome, descricao, max_usuarios)
        VALUES
            ('profissional', 'Profissional', '1 usuario adicional alem do admin padrao', 1),
            ('master', 'Master', 'Usuarios ilimitados, sem restricao de quantidade', NULL)
        ON CONFLICT (slug) DO NOTHING;

        SELECT id INTO v_plano_profissional_id
          FROM planos
         WHERE slug = 'profissional'
         ORDER BY id
         LIMIT 1;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name = 'empresas'
           AND column_name = 'plano_id'
    ) THEN
        ALTER TABLE empresas ADD COLUMN plano_id INT;
    END IF;

    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.table_constraints
         WHERE table_schema = 'public'
           AND table_name = 'empresas'
           AND constraint_name = 'fk_empresas_plano_id'
    ) THEN
        ALTER TABLE empresas
            ADD CONSTRAINT fk_empresas_plano_id
            FOREIGN KEY (plano_id) REFERENCES planos(id);
    END IF;

    IF v_plano_profissional_id IS NOT NULL THEN
        EXECUTE format('ALTER TABLE empresas ALTER COLUMN plano_id SET DEFAULT %s', v_plano_profissional_id);
        UPDATE empresas
           SET plano_id = v_plano_profissional_id
         WHERE plano_id IS NULL;
    END IF;

    UPDATE planos
       SET nome = 'Profissional',
           descricao = '1 usuario adicional alem do admin padrao',
           max_usuarios = 1
     WHERE slug = 'profissional'
       AND (
           nome IS DISTINCT FROM 'Profissional'
           OR descricao IS DISTINCT FROM '1 usuario adicional alem do admin padrao'
           OR max_usuarios IS DISTINCT FROM 1
       );

    UPDATE planos
       SET nome = 'Master',
           descricao = 'Usuarios ilimitados, sem restricao de quantidade',
           max_usuarios = NULL
     WHERE slug = 'master'
       AND (
           nome IS DISTINCT FROM 'Master'
           OR descricao IS DISTINCT FROM 'Usuarios ilimitados, sem restricao de quantidade'
           OR max_usuarios IS NOT NULL
       );
END $$;

-- Tabela: licenca_historico
CREATE TABLE IF NOT EXISTS licenca_historico (
    id                      SERIAL          PRIMARY KEY,
    empresa_id              INT             NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    admin_id                INT             REFERENCES admins(id) ON DELETE SET NULL,
    acao                    VARCHAR(50)     NOT NULL
                                CONSTRAINT chk_historico_acao CHECK (acao IN (
                                    'CRIACAO','RENOVACAO','BLOQUEIO','DESBLOQUEIO',
                                    'CANCELAMENTO','ALTERACAO_TIPO','REATIVACAO'
                                )),
    licenca_tipo_anterior   VARCHAR(10),
    licenca_tipo_novo       VARCHAR(10),
    licenca_fim_anterior    DATE,
    licenca_fim_novo        DATE,
    status_anterior         VARCHAR(15),
    status_novo             VARCHAR(15),
    observacao              TEXT,
    ip_address              VARCHAR(45),
    created_at              TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: emails_enviados
CREATE TABLE IF NOT EXISTS emails_enviados (
    id                  SERIAL          PRIMARY KEY,
    empresa_id          INT             NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo                VARCHAR(30)     NOT NULL
                            CONSTRAINT chk_emails_tipo CHECK (tipo IN (
                                'AVISO_7_DIAS','AVISO_3_DIAS','AVISO_1_DIA',
                                'LICENCA_VENCIDA','LICENCA_RENOVADA','BOAS_VINDAS'
                            )),
    destinatario        VARCHAR(255)    NOT NULL,
    assunto             VARCHAR(255),
    status              VARCHAR(10)     DEFAULT 'enviado'
                            CONSTRAINT chk_emails_status CHECK (status IN ('enviado','erro','pendente')),
    erro_mensagem       TEXT,
    enviado_em          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabela: validacoes_log
CREATE TABLE IF NOT EXISTS validacoes_log (
    id                  SERIAL          PRIMARY KEY,
    empresa_id          INT             REFERENCES empresas(id) ON DELETE SET NULL,
    chave_licenca       VARCHAR(64)     NOT NULL,
    ip_address          VARCHAR(45),
    resultado           VARCHAR(10)     NOT NULL
                            CONSTRAINT chk_validacoes_resultado CHECK (resultado IN ('ok','vencida','invalida','bloqueada')),
    dias_restantes      INT,
    created_at          TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- updated_at gen?rico
CREATE OR REPLACE FUNCTION master_update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_admins_updated_at ON admins;
CREATE TRIGGER trg_admins_updated_at
    BEFORE UPDATE ON admins
    FOR EACH ROW EXECUTE FUNCTION master_update_updated_at();

DROP TRIGGER IF EXISTS trg_empresas_updated_at ON empresas;
CREATE TRIGGER trg_empresas_updated_at
    BEFORE UPDATE ON empresas
    FOR EACH ROW EXECUTE FUNCTION master_update_updated_at();

DROP TRIGGER IF EXISTS trg_planos_updated_at ON planos;
CREATE TRIGGER trg_planos_updated_at
    BEFORE UPDATE ON planos
    FOR EACH ROW EXECUTE FUNCTION master_update_updated_at();

-- Bloqueio autom?tico por vencimento
CREATE OR REPLACE FUNCTION master_bloquear_licenca_vencida()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.licenca_fim < CURRENT_DATE AND NEW.status = 'ativa' THEN
        NEW.status = 'bloqueada';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_empresas_bloquear_vencida ON empresas;
CREATE TRIGGER trg_empresas_bloquear_vencida
    BEFORE INSERT OR UPDATE ON empresas
    FOR EACH ROW EXECUTE FUNCTION master_bloquear_licenca_vencida();

-- Hist?rico autom?tico a cada INSERT ou UPDATE relevante
CREATE OR REPLACE FUNCTION master_registrar_historico()
RETURNS TRIGGER AS $$
DECLARE
    v_acao VARCHAR(50);
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_acao := 'CRIACAO';
    ELSIF OLD.status != NEW.status THEN
        IF    NEW.status = 'bloqueada'                                THEN v_acao := 'BLOQUEIO';
        ELSIF NEW.status = 'ativa'  AND OLD.status = 'bloqueada'     THEN v_acao := 'DESBLOQUEIO';
        ELSIF NEW.status = 'ativa'  AND OLD.status = 'cancelada'     THEN v_acao := 'REATIVACAO';
        ELSIF NEW.status = 'cancelada'                                THEN v_acao := 'CANCELAMENTO';
        ELSE v_acao := 'ALTERACAO_TIPO';
        END IF;
    ELSIF OLD.licenca_fim != NEW.licenca_fim THEN
        v_acao := 'RENOVACAO';
    ELSE
        RETURN NEW;
    END IF;

    INSERT INTO licenca_historico (
        empresa_id, acao,
        licenca_tipo_anterior, licenca_tipo_novo,
        licenca_fim_anterior,  licenca_fim_novo,
        status_anterior,       status_novo
    ) VALUES (
        NEW.id, v_acao,
        CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE OLD.licenca_tipo END, NEW.licenca_tipo,
        CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE OLD.licenca_fim  END, NEW.licenca_fim,
        CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE OLD.status       END, NEW.status
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_empresas_historico ON empresas;
CREATE TRIGGER trg_empresas_historico
    AFTER INSERT OR UPDATE ON empresas
    FOR EACH ROW EXECUTE FUNCTION master_registrar_historico();

-- ============================================================================
-- ?NDICES
-- ============================================================================

CREATE INDEX IF NOT EXISTS idx_empresas_status      ON empresas(status);
CREATE INDEX IF NOT EXISTS idx_empresas_licenca_fim ON empresas(licenca_fim);
CREATE INDEX IF NOT EXISTS idx_empresas_cnpj        ON empresas(cnpj);
CREATE INDEX IF NOT EXISTS idx_empresas_chave       ON empresas(chave_licenca);
CREATE INDEX IF NOT EXISTS idx_historico_empresa    ON licenca_historico(empresa_id);
CREATE INDEX IF NOT EXISTS idx_emails_empresa       ON emails_enviados(empresa_id);
CREATE INDEX IF NOT EXISTS idx_validacoes_chave     ON validacoes_log(chave_licenca);
CREATE INDEX IF NOT EXISTS idx_validacoes_empresa   ON validacoes_log(empresa_id);
CREATE INDEX IF NOT EXISTS idx_empresas_aviso       ON empresas(licenca_fim, status)
    WHERE status IN ('ativa', 'trial');
CREATE INDEX IF NOT EXISTS idx_empresas_plano       ON empresas(plano_id);

-- ============================================================================
-- DADOS INICIAIS
-- ============================================================================

INSERT INTO planos (slug, nome, descricao, max_usuarios)
VALUES
    ('profissional', 'Profissional', '1 usuario adicional alem do admin padrao', 1),
    ('master', 'Master', 'Usuarios ilimitados, sem restricao de quantidade', NULL)
ON CONFLICT (slug) DO UPDATE
SET nome = EXCLUDED.nome,
    descricao = EXCLUDED.descricao,
    max_usuarios = EXCLUDED.max_usuarios,
    ativo = TRUE,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO admins (nome, email, senha) VALUES
    ('admin', 'admin@sistema.local', '$2y$10$cwZ1NM9xkgyIgDljjP7b/eY5AWhV16shKkZ24hBlTXb46034XDSmK')
ON CONFLICT (email) DO UPDATE
SET nome = EXCLUDED.nome,
    senha = EXCLUDED.senha,
    ativo = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- VIEWS
-- ============================================================================

DROP VIEW IF EXISTS vw_licencas_vencendo;
CREATE VIEW vw_licencas_vencendo AS
SELECT
    e.id,
    e.nome,
    e.cnpj,
    e.email,
    e.telefone,
    e.banco_dados,
    e.licenca_tipo,
    e.licenca_inicio,
    e.licenca_fim,
    e.status,
    e.dias_aviso,
    p.slug AS plano_slug,
    p.nome AS plano_nome,
    p.max_usuarios,
    GREATEST(0, CAST(e.licenca_fim - CURRENT_DATE AS INT)) AS dias_restantes,
    CASE
        WHEN e.licenca_fim < CURRENT_DATE                                    THEN 'VENCIDA'
        WHEN GREATEST(0, CAST(e.licenca_fim - CURRENT_DATE AS INT)) <= 1    THEN 'URGENTE'
        WHEN GREATEST(0, CAST(e.licenca_fim - CURRENT_DATE AS INT)) <= 3    THEN 'CRITICO'
        WHEN GREATEST(0, CAST(e.licenca_fim - CURRENT_DATE AS INT)) <= 7    THEN 'AVISO'
        ELSE 'OK'
    END AS alerta
FROM empresas e
LEFT JOIN planos p ON p.id = e.plano_id
ORDER BY e.licenca_fim ASC;

COMMENT ON VIEW vw_licencas_vencendo IS 'Dashboard ADM: todas as empresas ordenadas por vencimento.';

DROP VIEW IF EXISTS vw_dashboard_resumo;
CREATE VIEW vw_dashboard_resumo AS
SELECT
    COUNT(*)                                                                        AS total_empresas,
    COUNT(*) FILTER (WHERE e.status = 'ativa')                                      AS ativas,
    COUNT(*) FILTER (WHERE e.status = 'bloqueada')                                  AS bloqueadas,
    COUNT(*) FILTER (WHERE e.status = 'trial')                                      AS trial,
    COUNT(*) FILTER (WHERE e.status = 'cancelada')                                  AS canceladas,
    COUNT(*) FILTER (WHERE e.licenca_fim < CURRENT_DATE)                            AS vencidas,
    COUNT(*) FILTER (WHERE CAST(e.licenca_fim - CURRENT_DATE AS INT) BETWEEN 0 AND 7
                      AND e.status IN ('ativa','trial'))                            AS vencendo_7_dias,
    COUNT(*) FILTER (WHERE CAST(e.licenca_fim - CURRENT_DATE AS INT) BETWEEN 0 AND 3
                      AND e.status IN ('ativa','trial'))                            AS vencendo_3_dias,
    COUNT(*) FILTER (WHERE p.slug = 'profissional')                                 AS plano_profissional,
    COUNT(*) FILTER (WHERE p.slug = 'master')                                       AS plano_master
FROM empresas e
LEFT JOIN planos p ON p.id = e.plano_id;

COMMENT ON VIEW vw_dashboard_resumo IS 'Contadores para o dashboard do painel ADM.';

-- ============================================================================
-- BLOCO PARA license-system.sql
-- Adiciona colunas de endereco na tabela empresas, sem alterar colunas existentes
-- ============================================================================

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS razao_social VARCHAR(255),
    ADD COLUMN IF NOT EXISTS logradouro VARCHAR(255),
    ADD COLUMN IF NOT EXISTS numero VARCHAR(20),
    ADD COLUMN IF NOT EXISTS complemento VARCHAR(255),
    ADD COLUMN IF NOT EXISTS bairro VARCHAR(120),
    ADD COLUMN IF NOT EXISTS cidade VARCHAR(120),
    ADD COLUMN IF NOT EXISTS uf CHAR(2),
    ADD COLUMN IF NOT EXISTS cep VARCHAR(10);

ALTER TABLE empresas
    ADD CONSTRAINT chk_empresas_uf
    CHECK (uf IS NULL OR uf ~ '^[A-Z]{2}$');


-- ============================================================================
-- FINALIZA??O
-- ============================================================================

DO $$
BEGIN
    RAISE NOTICE '';
    RAISE NOTICE '================================================================';
    RAISE NOTICE '? BANCO MASTER v1.1 CRIADO COM SUCESSO';
    RAISE NOTICE '================================================================';
    RAISE NOTICE '  ? admins              ? usu?rios do painel ADM';
    RAISE NOTICE '  ? empresas            ? clientes com licen?a';
    RAISE NOTICE '  ? licenca_historico   ? auditoria autom?tica via trigger';
    RAISE NOTICE '  ? emails_enviados     ? controle de avisos';
    RAISE NOTICE '  ? validacoes_log      ? log de valida??es dos clientes';
    RAISE NOTICE '  ? vw_licencas_vencendo  ? view do painel principal';
    RAISE NOTICE '  ? vw_dashboard_resumo   ? contadores do dashboard';
    RAISE NOTICE '  ? Trigger bloqueio autom?tico por vencimento';
    RAISE NOTICE '  ? Trigger hist?rico autom?tico (INSERT/UPDATE)';
    RAISE NOTICE '  ? ?ndices otimizados';
    RAISE NOTICE '';
    RAISE NOTICE 'ATEN??O: Altere a senha do admin antes de usar em produ??o!';
    RAISE NOTICE 'Pr?ximo passo: criar o painel ADM em PHP';
    RAISE NOTICE '================================================================';
END $$;
