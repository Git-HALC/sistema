-- ============================================================================
-- cliente-base.sql (CONSOLIDADO 2026-04-18)
-- Sistema DM - Template de banco para NOVO cliente (PostgreSQL 13+)
-- ============================================================================
-- Este arquivo eh executado automaticamente pelo config/database.php quando
-- o banco do tenant nao possui a tabela 'usuarios'. Cria toda a estrutura
-- em um banco vazio. Para bancos existentes, use as migrations em
-- database/migrations/ (controladas via tabela schema_migrations).
--
-- Secoes:
--   1. Extensoes (uuid-ossp, pg_trgm)
--   2. Tabelas e tipos (em ordem de dependencia de FKs)
--   3. Indices
--   4. Funcoes e triggers
--   5. Views
--   6. Dados iniciais (seeds)
--   7. Usuario admin padrao
-- ============================================================================

--
-- PostgreSQL database dump
--

\restrict mkfFVUslMTV9U8wzgvHs9PzCTIoYV23DfLBPxabUbOh2xBbxOBBnSFY6CDgKVNM

-- Dumped from database version 13.22
-- Dumped by pg_dump version 13.22

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: uuid-ossp; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS "uuid-ossp" WITH SCHEMA public;


--
-- Name: EXTENSION "uuid-ossp"; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION "uuid-ossp" IS 'generate universally unique identifiers (UUIDs)';


--
-- Name: atualizar_saldo_conta_trigger(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.atualizar_saldo_conta_trigger() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.afeta_saldo THEN
            IF NEW.tipo = 'Entrada' THEN
                UPDATE contas SET saldo_atual = saldo_atual + NEW.valor WHERE id = NEW.conta_id;
            ELSE
                UPDATE contas SET saldo_atual = saldo_atual - NEW.valor WHERE id = NEW.conta_id;
            END IF;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF OLD.afeta_saldo THEN
            IF OLD.tipo = 'Entrada' THEN
                UPDATE contas SET saldo_atual = saldo_atual - OLD.valor WHERE id = OLD.conta_id;
            ELSE
                UPDATE contas SET saldo_atual = saldo_atual + OLD.valor WHERE id = OLD.conta_id;
            END IF;
        END IF;
    END IF;

    IF TG_OP = 'INSERT' THEN
        RETURN NEW;
    END IF;
    RETURN NULL;
END;
$$;


--
-- Name: FUNCTION atualizar_saldo_conta_trigger(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.atualizar_saldo_conta_trigger() IS 'Trigger para atualizar saldo_atual da conta automaticamente ao inserir/deletar movimentacoes.';


--
-- Name: bloquear_delete_cr_protegida(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_delete_cr_protegida() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Conta a receber protegida (origem PDV). Nao pode ser excluida.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$;


--
-- Name: bloquear_delete_movimentacao_protegida(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_delete_movimentacao_protegida() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Movimentacao protegida (origem PDV). Cancele a venda em vez de excluir.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$;


--
-- Name: bloquear_delete_protegido(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_delete_protegido() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.protegido = TRUE THEN
        RAISE EXCEPTION 'Este registro e protegido e nao pode ser excluido.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$;


--
-- Name: bloquear_delete_update_cr_com_nfe_autorizada(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_delete_update_cr_com_nfe_autorizada() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_pedido_id UUID;
    v_numero_nfe BIGINT;
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_pedido_id := OLD.pedido_id;
    ELSE
        v_pedido_id := COALESCE(NEW.pedido_id, OLD.pedido_id);
    END IF;

    IF v_pedido_id IS NULL THEN
        IF TG_OP = 'DELETE' THEN
            RETURN OLD;
        END IF;
        RETURN NEW;
    END IF;

    IF TG_OP = 'DELETE'
       OR (
           TG_OP = 'UPDATE'
           AND NEW.status = 'CANCELADO'
           AND COALESCE(OLD.status, '') <> 'CANCELADO'
       ) THEN
        SELECT pn.numero_nfe
          INTO v_numero_nfe
          FROM pedido_nfe pn
         WHERE pn.pedido_id = v_pedido_id
           AND pn.status = 'AUTORIZADA'
         ORDER BY pn.data_emissao DESC NULLS LAST, pn.numero_nfe DESC
         LIMIT 1;

        IF v_numero_nfe IS NOT NULL THEN
            RAISE EXCEPTION
                'Nao e possivel excluir: este lancamento esta vinculado a NF-e autorizada n. %. Cancele a NF-e antes de excluir.',
                v_numero_nfe
                USING ERRCODE = '23000';
        END IF;
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$$;


--
-- Name: FUNCTION bloquear_delete_update_cr_com_nfe_autorizada(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.bloquear_delete_update_cr_com_nfe_autorizada() IS 'Bloqueia exclusao de contas_receber e cancelamento de status quando houver pedido vinculado com NF-e AUTORIZADA.';


--
-- Name: bloquear_delete_venda_protegida(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_delete_venda_protegida() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.protegido THEN
        RAISE EXCEPTION 'Venda PDV protegida. Use cancelamento (status=cancelado) em vez de DELETE.'
              USING ERRCODE = '23000';
    END IF;
    RETURN OLD;
END;
$$;


--
-- Name: bloquear_licenca_vencida(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_licenca_vencida() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.dias_restantes = GREATEST(0, CAST(NEW.licenca_fim - CURRENT_DATE AS INT));
    IF NEW.licenca_fim < CURRENT_DATE AND NEW.status = 'ativa' THEN
        NEW.status = 'bloqueada';
        RAISE NOTICE 'Licenca bloqueada automaticamente por vencimento.';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: bloquear_venda_caixa_fechado(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.bloquear_venda_caixa_fechado() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    SELECT status INTO v_status FROM pdv_caixas WHERE id = NEW.caixa_id;
    IF v_status = 'fechado' THEN
        RAISE EXCEPTION 'Caixa #% esta fechado. Abra um novo caixa para registrar vendas.', NEW.caixa_id
              USING ERRCODE = '23000';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: calcular_validade_orcamento(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.calcular_validade_orcamento() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.data_validade := (NEW.data_orcamento::date + make_interval(days => NEW.validade_dias));
    RETURN NEW;
END;
$$;


--
-- Name: FUNCTION calcular_validade_orcamento(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.calcular_validade_orcamento() IS 'Funcao trigger para calcular automaticamente data_validade baseada em data_orcamento + validade_dias.';


--
-- Name: update_licenca_updated_at(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_licenca_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    NEW.ultimo_check = CURRENT_TIMESTAMP;
    NEW.dias_restantes = GREATEST(0, CAST(NEW.licenca_fim - CURRENT_DATE AS INT));
    RETURN NEW;
END;
$$;


--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


--
-- Name: FUNCTION update_updated_at_column(); Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON FUNCTION public.update_updated_at_column() IS 'Funcao trigger para atualizar automaticamente o campo updated_at quando um registro e modificado.';


--
-- Name: verificar_limite_usuarios(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.verificar_limite_usuarios() RETURNS TABLE(plano_slug character varying, max_usuarios integer, total_usuarios bigint, pode_criar boolean, mensagem text)
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_plano     VARCHAR;
    v_max       INT;
    v_total     BIGINT;
BEGIN
    SELECT l.plano_slug, l.max_usuarios
      INTO v_plano, v_max
      FROM public.licenca l
     ORDER BY l.id
     LIMIT 1;

    IF v_plano IS NULL THEN
        v_plano := 'profissional';
    END IF;

    IF v_plano = 'profissional' AND v_max IS NULL THEN
        v_max := 1;
    END IF;

    SELECT COUNT(*)
      INTO v_total
      FROM public.usuarios
     WHERE ativo = TRUE
       AND NOT (
           LOWER(email) = LOWER('admin@suporte.com')
           AND nivel_acesso_id = 1
       );

    RETURN QUERY
    SELECT
        v_plano,
        v_max,
        v_total,
        CASE
            WHEN v_max IS NULL THEN TRUE
            WHEN v_total < v_max THEN TRUE
            ELSE FALSE
        END,
        CASE
            WHEN v_max IS NULL THEN 'Plano Master: usuarios ilimitados'
            WHEN v_total < v_max THEN
                format('Plano Profissional: %s de %s usuarios adicionais utilizados', v_total, v_max)
            ELSE
                format('Limite atingido: plano Profissional permite apenas %s usuarios adicionais (alem do admin padrao). '
                       || 'Entre em contato para upgrade para o plano Master.', v_max)
        END;
END;
$$;


SET default_table_access_method = heap;

--
-- Name: auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria (
    id integer NOT NULL,
    usuario_id integer,
    acao character varying(100) NOT NULL,
    tabela character varying(100) NOT NULL,
    registro_id integer,
    dados_antigos jsonb,
    dados_novos jsonb,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: auditoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_id_seq OWNED BY public.auditoria.id;


--
-- Name: auditoria_usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.auditoria_usuarios (
    id integer NOT NULL,
    usuario_id integer,
    usuario_nome character varying(150) NOT NULL,
    modulo character varying(60) NOT NULL,
    acao character varying(40) NOT NULL,
    entidade character varying(60) NOT NULL,
    entidade_id integer,
    descricao text,
    dados jsonb,
    ip character varying(45),
    user_agent character varying(255),
    created_at timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: auditoria_usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.auditoria_usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: auditoria_usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.auditoria_usuarios_id_seq OWNED BY public.auditoria_usuarios.id;


--
-- Name: categorias_dre; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias_dre (
    id integer NOT NULL,
    nome character varying(255) NOT NULL,
    tipo character varying(50) NOT NULL,
    descricao text,
    ordem integer DEFAULT 0,
    ativo boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    codigo character varying(10),
    CONSTRAINT categorias_dre_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['Receita'::character varying, 'Despesa'::character varying, 'Deducao'::character varying, 'CPV'::character varying, 'Despesa Operacional'::character varying, 'Despesa Financeira'::character varying, 'Tributo'::character varying, 'Outras'::character varying])::text[])))
);

COMMENT ON COLUMN public.categorias_dre.codigo IS
    'Codigo contabil (01=Vendas Produtos, 02=Vendas Servicos, 41=Descontos Concedidos, 51=Taxas Bancarias)';


--
-- Name: categorias_dre_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_dre_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_dre_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_dre_id_seq OWNED BY public.categorias_dre.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id integer NOT NULL,
    uuid uuid DEFAULT public.uuid_generate_v4(),
    nome character varying(100) NOT NULL,
    cpf_cnpj character varying(20) NOT NULL,
    email character varying(100) NOT NULL,
    telefone character varying(20),
    eh_cliente boolean DEFAULT true NOT NULL,
    eh_fornecedor boolean DEFAULT false NOT NULL,
    endereco text,
    cidade character varying(100),
    estado character(2),
    cep character varying(10),
    ativo boolean DEFAULT true,
    usuario_id integer,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    numero_endereco character varying(20),
    codigo_municipio character varying(7),
    ie character varying(20),
    ind_ie_dest character(1) DEFAULT '9'::bpchar,
    CONSTRAINT clientes_ind_ie_dest_check CHECK ((ind_ie_dest = ANY (ARRAY['1'::bpchar, '2'::bpchar, '9'::bpchar])))
);


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: configuracoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(100) NOT NULL,
    valor text,
    descricao text,
    tipo character varying(50) DEFAULT 'text'::character varying,
    categoria character varying(50) DEFAULT 'Geral'::character varying,
    ordem integer DEFAULT 0,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: configuracoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuracoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;


--
-- Name: contas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contas (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    tipo character varying(20) NOT NULL,
    banco character varying(100),
    agencia character varying(20),
    numero_conta character varying(30),
    saldo_inicial numeric(15,4) DEFAULT 0,
    saldo_atual numeric(15,4) DEFAULT 0,
    ativo boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_contas_tipo CHECK (((tipo)::text = ANY ((ARRAY['Banco'::character varying, 'Caixa'::character varying, 'Poupanca'::character varying, 'Investimento'::character varying])::text[])))
);


--
-- Name: contas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contas_id_seq OWNED BY public.contas.id;


--
-- Name: contas_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contas_pagar (
    id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    fornecedor character varying(255),
    categoria_dre_id integer,
    valor numeric(15,4) NOT NULL,
    data_vencimento date NOT NULL,
    data_pagamento date,
    valor_pago numeric(15,4),
    desconto numeric(15,4) DEFAULT 0,
    status character varying(20) DEFAULT 'PENDENTE'::character varying,
    observacoes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    cliente_id integer,
    protegido boolean DEFAULT false NOT NULL,
    CONSTRAINT chk_contas_pagar_status CHECK (((status)::text = ANY ((ARRAY['PENDENTE'::character varying, 'PAGO'::character varying, 'VENCIDO'::character varying, 'CANCELADO'::character varying])::text[])))
);


--
-- Name: contas_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contas_pagar_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contas_pagar_id_seq OWNED BY public.contas_pagar.id;


--
-- Name: contas_receber; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contas_receber (
    id integer NOT NULL,
    descricao character varying(255) NOT NULL,
    cliente_id integer,
    forma_pagamento_id integer,
    categoria_dre_id integer,
    valor numeric(15,4) NOT NULL,
    data_vencimento date NOT NULL,
    data_pagamento date,
    valor_pago numeric(15,4),
    desconto numeric(15,4) DEFAULT 0,
    status character varying(20) DEFAULT 'PENDENTE'::character varying,
    observacoes text,
    pedido_id uuid,
    origem character varying(50),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    protegido boolean DEFAULT false NOT NULL,
    pdv_venda_id integer,
    CONSTRAINT chk_contas_receber_status CHECK (((status)::text = ANY ((ARRAY['PENDENTE'::character varying, 'PAGO'::character varying, 'VENCIDO'::character varying, 'CANCELADO'::character varying])::text[]))),
    CONSTRAINT chk_cr_origem CHECK (((origem)::text = ANY ((ARRAY['PDV'::character varying, 'MANUAL'::character varying, 'PEDIDO'::character varying, 'SERVICO'::character varying, 'ORCAMENTO'::character varying])::text[])))
);


--
-- Name: COLUMN contas_receber.pedido_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.contas_receber.pedido_id IS 'FK para pedidos quando origem = PEDIDO.';


--
-- Name: COLUMN contas_receber.origem; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.contas_receber.origem IS 'Origem do lancamento: PEDIDO, PDV, MANUAL, IMPORTACAO.';


--
-- Name: contas_receber_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contas_receber_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contas_receber_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contas_receber_id_seq OWNED BY public.contas_receber.id;


--
-- Name: empresa_fiscal_servico; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresa_fiscal_servico (
    id integer NOT NULL,
    cnpj character varying(14) NOT NULL,
    razao_social character varying(150) NOT NULL,
    inscricao_municipal character varying(20) NOT NULL,
    codigo_municipio_ibge character varying(7) NOT NULL,
    aliquota_iss_padrao numeric(5,2) DEFAULT 5.00 NOT NULL,
    url_webservice_homologacao text,
    url_webservice_producao text,
    usuario_webservice character varying(100),
    senha_webservice character varying(255),
    ambiente character varying(20) DEFAULT 'homologacao'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_empresa_fiscal_servico_aliquota CHECK ((aliquota_iss_padrao >= (0)::numeric)),
    CONSTRAINT chk_empresa_fiscal_servico_ambiente CHECK (((ambiente)::text = ANY ((ARRAY['homologacao'::character varying, 'producao'::character varying])::text[])))
);


--
-- Name: TABLE empresa_fiscal_servico; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.empresa_fiscal_servico IS 'Configuracoes do emitente para integracao de NFS-e.';


--
-- Name: empresa_fiscal_servico_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresa_fiscal_servico_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresa_fiscal_servico_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresa_fiscal_servico_id_seq OWNED BY public.empresa_fiscal_servico.id;


--
-- Name: empresa_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresa_local (
    id integer NOT NULL,
    cnpj character varying(18) NOT NULL,
    nome character varying(255) NOT NULL,
    contato character varying(255),
    email character varying(255),
    telefone character varying(20),
    logradouro character varying(255),
    numero character varying(20),
    complemento character varying(255),
    bairro character varying(120),
    cidade character varying(120),
    uf character(2),
    cep character varying(10),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    inscricao_estadual character varying(20),
    inscricao_municipal character varying(20),
    codigo_municipio character varying(7),
    regime_tributario character(1) DEFAULT '1'::bpchar,
    ambiente_nfe character(1) DEFAULT '2'::bpchar,
    serie_nfe character varying(3) DEFAULT '001'::character varying,
    proximo_numero_nfe bigint DEFAULT 1,
    certificado_path character varying(500),
    certificado_senha character varying(255),
    CONSTRAINT chk_empresa_local_cep CHECK (((cep IS NULL) OR (char_length(regexp_replace((cep)::text, '\D'::text, ''::text, 'g'::text)) = 8))),
    CONSTRAINT chk_empresa_local_cnpj CHECK ((char_length(regexp_replace((cnpj)::text, '\D'::text, ''::text, 'g'::text)) = ANY (ARRAY[11, 14]))),
    CONSTRAINT chk_empresa_local_uf CHECK (((uf IS NULL) OR (uf ~ '^[A-Z]{2}$'::text))),
    CONSTRAINT empresa_local_ambiente_nfe_check CHECK ((ambiente_nfe = ANY (ARRAY['1'::bpchar, '2'::bpchar]))),
    CONSTRAINT empresa_local_regime_tributario_check CHECK ((regime_tributario = ANY (ARRAY['1'::bpchar, '2'::bpchar, '3'::bpchar])))
);


--
-- Name: empresa_local_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresa_local_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresa_local_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresa_local_id_seq OWNED BY public.empresa_local.id;


--
-- Name: formas_pagamento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.formas_pagamento (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    tipo character varying(3) NOT NULL,
    descricao text,
    adquirente_id integer,
    taxa numeric(10,4) DEFAULT 0 NOT NULL,
    prazo_dias integer DEFAULT 0 NOT NULL,
    conta_id integer,
    ativo boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_formas_pagamento_tipo CHECK (((tipo)::text = ANY ((ARRAY['D'::character varying, 'PIX'::character varying, 'TB'::character varying, 'CC'::character varying, 'CD'::character varying, 'BOL'::character varying, 'AF'::character varying])::text[])))
);


--
-- Name: formas_pagamento_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.formas_pagamento_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: formas_pagamento_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.formas_pagamento_id_seq OWNED BY public.formas_pagamento.id;


--
-- Name: licenca; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.licenca (
    id integer NOT NULL,
    chave_licenca character varying(64) NOT NULL,
    empresa_nome character varying(255) NOT NULL,
    empresa_cnpj character varying(18) NOT NULL,
    licenca_tipo character varying(10) DEFAULT 'mensal'::character varying NOT NULL,
    licenca_inicio date DEFAULT CURRENT_DATE NOT NULL,
    licenca_fim date NOT NULL,
    dias_restantes integer DEFAULT 0 NOT NULL,
    dias_aviso integer DEFAULT 7 NOT NULL,
    status character varying(15) DEFAULT 'ativa'::character varying NOT NULL,
    ultimo_check timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    plano_slug character varying(30) DEFAULT 'profissional'::character varying NOT NULL,
    max_usuarios integer DEFAULT 1,
    CONSTRAINT chk_licenca_status CHECK (((status)::text = ANY ((ARRAY['ativa'::character varying, 'bloqueada'::character varying, 'trial'::character varying, 'cancelada'::character varying])::text[]))),
    CONSTRAINT chk_licenca_tipo CHECK (((licenca_tipo)::text = ANY ((ARRAY['mensal'::character varying, 'anual'::character varying, 'trial'::character varying])::text[])))
);


--
-- Name: TABLE licenca; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.licenca IS 'Controle de licenca do sistema. Apenas 1 registro ativo por banco.';


--
-- Name: COLUMN licenca.dias_restantes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.licenca.dias_restantes IS 'Atualizado automaticamente pelo trigger a cada INSERT/UPDATE.';


--
-- Name: COLUMN licenca.dias_aviso; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.licenca.dias_aviso IS 'Quantos dias antes do vencimento o sistema exibe aviso ao usuario.';


--
-- Name: licenca_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.licenca_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: licenca_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.licenca_id_seq OWNED BY public.licenca.id;


--
-- Name: modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modulos (
    id integer NOT NULL,
    slug character varying(50) NOT NULL,
    nome character varying(100) NOT NULL,
    icone character varying(50),
    ordem integer DEFAULT 0
);


--
-- Name: modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modulos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modulos_id_seq OWNED BY public.modulos.id;


--
-- Name: movimentacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimentacoes (
    id integer NOT NULL,
    conta_id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    valor numeric(15,4) NOT NULL,
    data_movimentacao timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    descricao text,
    desconto numeric(15,4) DEFAULT 0,
    categoria_dre_id integer,
    forma_pagamento_id integer,
    conta_receber_id integer,
    conta_pagar_id integer,
    pedido_id uuid,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    tipo_origem character varying(30) DEFAULT 'MANUAL'::character varying,
    afeta_saldo boolean DEFAULT true NOT NULL,
    afeta_dre boolean DEFAULT true NOT NULL,
    protegido boolean DEFAULT false NOT NULL,
    CONSTRAINT chk_movimentacoes_tipo CHECK (((tipo)::text = ANY ((ARRAY['Entrada'::character varying, 'Saida'::character varying])::text[]))),
    CONSTRAINT chk_movimentacoes_valor CHECK ((valor > (0)::numeric)),
    CONSTRAINT chk_movimento_tipo_origem CHECK (((tipo_origem)::text = ANY ((ARRAY['MANUAL'::character varying, 'RECEBIMENTO'::character varying, 'PAGAMENTO'::character varying, 'ESTORNO'::character varying, 'VENDA_COMPETENCIA'::character varying, 'TAXA_CARTAO'::character varying, 'DEDUCAO'::character varying, 'TRIBUTO_VENDA'::character varying, 'CPV'::character varying, 'PDV'::character varying, 'VENDA'::character varying])::text[])))
);

COMMENT ON COLUMN public.movimentacoes.afeta_dre IS
    'TRUE = lancamento aparece no DRE. Recebimentos/pagamentos de CR/CP tem afeta_dre=FALSE (apenas patrimonial).';


--
-- Name: COLUMN movimentacoes.protegido; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.movimentacoes.protegido IS 'TRUE quando movimentacao foi gerada pelo PDV e nao pode ser excluida manualmente.';


--
-- Name: movimentacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimentacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimentacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimentacoes_id_seq OWNED BY public.movimentacoes.id;


--
-- Name: niveis_acesso; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.niveis_acesso (
    id integer NOT NULL,
    nome character varying(50) NOT NULL,
    descricao text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: niveis_acesso_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.niveis_acesso_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: niveis_acesso_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.niveis_acesso_id_seq OWNED BY public.niveis_acesso.id;


--
-- Name: nota_fiscal_servico; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nota_fiscal_servico (
    id integer NOT NULL,
    servico_id uuid,
    numero_nota character varying(20),
    serie character varying(5) DEFAULT 'RPS'::character varying NOT NULL,
    numero_rps integer NOT NULL,
    status character varying(20) DEFAULT 'pendente'::character varying NOT NULL,
    xml_enviado text,
    xml_retorno text,
    protocolo character varying(100),
    data_emissao timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    data_competencia date NOT NULL,
    valor_servico numeric(12,2) NOT NULL,
    aliquota_iss numeric(5,2) NOT NULL,
    valor_iss numeric(12,2) NOT NULL,
    codigo_servico_lc116 character varying(10),
    descricao_servico text NOT NULL,
    tomador_nome character varying(150),
    tomador_cpf_cnpj character varying(14),
    tomador_email character varying(150),
    tomador_logradouro character varying(200),
    tomador_municipio character varying(100),
    tomador_uf character varying(2),
    erro_mensagem text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_nota_fiscal_servico_aliquota_iss CHECK ((aliquota_iss >= (0)::numeric)),
    CONSTRAINT chk_nota_fiscal_servico_status CHECK (((status)::text = ANY ((ARRAY['pendente'::character varying, 'enviada'::character varying, 'cancelada'::character varying, 'erro'::character varying])::text[]))),
    CONSTRAINT chk_nota_fiscal_servico_valor_iss CHECK ((valor_iss >= (0)::numeric)),
    CONSTRAINT chk_nota_fiscal_servico_valor_servico CHECK ((valor_servico >= (0)::numeric))
);


--
-- Name: TABLE nota_fiscal_servico; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.nota_fiscal_servico IS 'Reservado para NFS-e futura (mini-modulo de servicos).';


--
-- Name: COLUMN nota_fiscal_servico.servico_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.nota_fiscal_servico.servico_id IS 'Placeholder para vinculo futuro com nova tabela de servicos (mini-modulo).';


--
-- Name: COLUMN nota_fiscal_servico.numero_rps; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.nota_fiscal_servico.numero_rps IS 'Sequencial do RPS usado antes da autorizacao municipal.';


--
-- Name: nota_fiscal_servico_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nota_fiscal_servico_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nota_fiscal_servico_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nota_fiscal_servico_id_seq OWNED BY public.nota_fiscal_servico.id;


--
-- Name: notificacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notificacoes (
    id integer NOT NULL,
    usuario_id integer NOT NULL,
    titulo character varying(255) NOT NULL,
    mensagem text NOT NULL,
    tipo character varying(50),
    link character varying(255),
    lida boolean DEFAULT false,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: notificacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notificacoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notificacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notificacoes_id_seq OWNED BY public.notificacoes.id;


--
-- Name: pdv_caixas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pdv_caixas (
    id integer NOT NULL,
    usuario_abertura_id integer NOT NULL,
    numero_caixa integer NOT NULL,
    status character varying(20) DEFAULT 'aberto'::character varying NOT NULL,
    valor_suprimento numeric(12,2) DEFAULT 0 NOT NULL,
    data_abertura timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    data_fechamento timestamp with time zone,
    fechamento_dinheiro numeric(12,2),
    fechamento_cartao numeric(12,2),
    fechamento_pix numeric(12,2),
    fechamento_faturar numeric(12,2),
    sistema_dinheiro numeric(12,2),
    sistema_cartao numeric(12,2),
    sistema_pix numeric(12,2),
    sistema_faturar numeric(12,2),
    diferenca_dinheiro numeric(12,2),
    diferenca_cartao numeric(12,2),
    diferenca_pix numeric(12,2),
    diferenca_faturar numeric(12,2),
    diferenca_total numeric(12,2),
    observacao text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    total_vendas numeric(15,2) DEFAULT 0 NOT NULL,
    qtd_vendas integer DEFAULT 0 NOT NULL,
    conferencia_concluida boolean DEFAULT false NOT NULL,
    conferencia_em timestamp with time zone,
    conferencia_usuario_id integer,
    CONSTRAINT pdv_caixas_status_check CHECK (((status)::text = ANY ((ARRAY['aberto'::character varying, 'fechado'::character varying])::text[])))
);


--
-- Name: TABLE pdv_caixas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pdv_caixas IS 'Sessao de caixa do PDV com abertura, fechamento cego e conferencia.';


--
-- Name: COLUMN pdv_caixas.total_vendas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pdv_caixas.total_vendas IS 'Soma acumulada de pdv_lancamentos.valor_total neste caixa.';


--
-- Name: COLUMN pdv_caixas.qtd_vendas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pdv_caixas.qtd_vendas IS 'Quantidade de lancamentos registrados neste caixa.';


--
-- Name: pdv_caixas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_caixas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_caixas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_caixas_id_seq OWNED BY public.pdv_caixas.id;


--
-- Name: pdv_conferencia_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pdv_conferencia_itens (
    id integer NOT NULL,
    caixa_id integer NOT NULL,
    forma_pagamento_id integer NOT NULL,
    valor_sistema numeric(12,2) DEFAULT 0 NOT NULL,
    valor_informado numeric(12,2) DEFAULT 0 NOT NULL,
    diferenca numeric(12,2) GENERATED ALWAYS AS ((valor_informado - valor_sistema)) STORED,
    conferido_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: pdv_conferencia_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_conferencia_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_conferencia_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_conferencia_itens_id_seq OWNED BY public.pdv_conferencia_itens.id;


--
-- Name: pdv_lancamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pdv_lancamentos (
    id integer NOT NULL,
    caixa_id integer NOT NULL,
    usuario_id integer NOT NULL,
    tipo character varying(20) NOT NULL,
    pedido_id uuid NOT NULL,
    valor_total numeric(12,2) NOT NULL,
    forma_pagamento character varying(20) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT pdv_lancamentos_forma_pagamento_check CHECK (((forma_pagamento)::text = ANY ((ARRAY['dinheiro'::character varying, 'cartao'::character varying, 'pix'::character varying, 'a_faturar'::character varying])::text[]))),
    CONSTRAINT pdv_lancamentos_tipo_check CHECK (((tipo)::text = 'pedido'::text)),
    CONSTRAINT pdv_lancamentos_valor_total_check CHECK ((valor_total >= (0)::numeric))
);


--
-- Name: TABLE pdv_lancamentos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pdv_lancamentos IS 'Lancamentos de pedidos vinculados a um caixa do PDV.';


--
-- Name: COLUMN pdv_lancamentos.pedido_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pdv_lancamentos.pedido_id IS 'FK real para pedidos.id (substitui referencia_id UUID generico).';


--
-- Name: pdv_lancamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_lancamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_lancamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_lancamentos_id_seq OWNED BY public.pdv_lancamentos.id;


--
-- Name: pdv_venda_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pdv_venda_itens (
    id integer NOT NULL,
    venda_id integer NOT NULL,
    tipo_item character varying(20) NOT NULL,
    produto_id integer,
    servico_id integer,
    nome_item character varying(255) NOT NULL,
    quantidade numeric(15,4) NOT NULL,
    valor_unitario numeric(15,4) NOT NULL,
    valor_total_item numeric(15,4) NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_pdv_venda_item_qtd CHECK ((quantidade > (0)::numeric)),
    CONSTRAINT chk_pdv_venda_item_ref CHECK (((((tipo_item)::text = 'PRODUTO'::text) AND (servico_id IS NULL)) OR (((tipo_item)::text = 'SERVICO'::text) AND (produto_id IS NULL)))),
    CONSTRAINT chk_pdv_venda_item_tipo CHECK (((tipo_item)::text = ANY ((ARRAY['PRODUTO'::character varying, 'SERVICO'::character varying])::text[]))),
    CONSTRAINT chk_pdv_venda_item_vt CHECK ((valor_total_item >= (0)::numeric)),
    CONSTRAINT chk_pdv_venda_item_vu CHECK ((valor_unitario >= (0)::numeric))
);


--
-- Name: TABLE pdv_venda_itens; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pdv_venda_itens IS 'Itens (produto ou servico) de cada venda PDV. Snapshot no nome_item.';


--
-- Name: pdv_venda_itens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_venda_itens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_venda_itens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_venda_itens_id_seq OWNED BY public.pdv_venda_itens.id;


--
-- Name: pdv_vendas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pdv_vendas (
    id integer NOT NULL,
    caixa_id integer NOT NULL,
    usuario_id integer NOT NULL,
    cliente_id integer,
    numero bigint NOT NULL,
    status character varying(20) DEFAULT 'faturado'::character varying NOT NULL,
    forma_pagamento_id integer,
    desconto_tipo character varying(20),
    desconto_valor numeric(15,2) DEFAULT 0 NOT NULL,
    valor_total numeric(15,4) NOT NULL,
    observacoes text,
    origem character varying(20) DEFAULT 'rapida'::character varying NOT NULL,
    protegido boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    cancelado_por_id integer,
    cancelado_em timestamp with time zone,
    motivo_cancelamento text,
    faturada_em timestamp with time zone,
    conferida boolean DEFAULT false NOT NULL,
    caixa_conferencia_id integer,
    CONSTRAINT chk_pdv_vendas_desc_tipo CHECK (((desconto_tipo IS NULL) OR ((desconto_tipo)::text = ANY ((ARRAY['VALOR'::character varying, 'PERCENTUAL'::character varying])::text[])))),
    CONSTRAINT chk_pdv_vendas_desc_val CHECK ((desconto_valor >= (0)::numeric)),
    CONSTRAINT chk_pdv_vendas_forma_obrig CHECK ((((status)::text <> 'faturado'::text) OR (forma_pagamento_id IS NOT NULL))),
    CONSTRAINT chk_pdv_vendas_origem CHECK (((origem)::text = ANY ((ARRAY['rapida'::character varying, 'fluxo'::character varying])::text[]))),
    CONSTRAINT chk_pdv_vendas_status CHECK (((status)::text = ANY ((ARRAY['pendente'::character varying, 'em_processo'::character varying, 'concluido'::character varying, 'faturado'::character varying, 'cancelado'::character varying])::text[]))),
    CONSTRAINT chk_pdv_vendas_valor CHECK ((valor_total >= (0)::numeric))
);


--
-- Name: TABLE pdv_vendas; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pdv_vendas IS 'Vendas efetivadas no PDV. Substitui uso direto de pedidos para venda rapida.';


--
-- Name: COLUMN pdv_vendas.origem; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pdv_vendas.origem IS 'rapida = modo terminal; fluxo = fluxo completo (nao implementado).';


--
-- Name: COLUMN pdv_vendas.protegido; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pdv_vendas.protegido IS 'TRUE: nao pode ser excluida, apenas cancelada (status=cancelado).';


--
-- Name: pdv_vendas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_vendas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_vendas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_vendas_id_seq OWNED BY public.pdv_vendas.id;


--
-- Name: pdv_vendas_numero_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pdv_vendas_numero_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pdv_vendas_numero_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pdv_vendas_numero_seq OWNED BY public.pdv_vendas.numero;


--
-- Name: pedido_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedido_itens (
    id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    pedido_id uuid NOT NULL,
    produto_id integer,
    nome_produto character varying(255) NOT NULL,
    quantidade numeric(15,4) NOT NULL,
    valor_unitario numeric(15,4) NOT NULL,
    valor_total_item numeric(15,4) NOT NULL,
    observacoes text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_pedido_itens_quantidade CHECK ((quantidade > (0)::numeric)),
    CONSTRAINT chk_pedido_itens_valor_total_item CHECK ((valor_total_item >= (0)::numeric)),
    CONSTRAINT chk_pedido_itens_valor_unitario CHECK ((valor_unitario >= (0)::numeric))
);


--
-- Name: TABLE pedido_itens; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pedido_itens IS 'LEGADO HISTORICO. Itens dos pedidos descontinuados.';


--
-- Name: COLUMN pedido_itens.nome_produto; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pedido_itens.nome_produto IS 'Snapshot do nome do produto no momento do pedido.';


--
-- Name: pedido_nfe; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedido_nfe (
    id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    pedido_id uuid NOT NULL,
    numero_nfe bigint NOT NULL,
    chave_acesso character varying(44) NOT NULL,
    status character varying(20) DEFAULT 'PENDENTE'::character varying NOT NULL,
    data_emissao timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    xml_nfe text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    n_prot character varying(15),
    CONSTRAINT chk_pedido_nfe_chave_acesso CHECK ((char_length((chave_acesso)::text) = 44)),
    CONSTRAINT chk_pedido_nfe_numero CHECK ((numero_nfe > 0)),
    CONSTRAINT chk_pedido_nfe_status CHECK (((status)::text = ANY ((ARRAY['PENDENTE'::character varying, 'EMITIDA'::character varying, 'AUTORIZADA'::character varying, 'CANCELADA'::character varying, 'REJEITADA'::character varying, 'INUTILIZADA'::character varying])::text[]))),
    CONSTRAINT chk_pedido_nfe_xml CHECK (((xml_nfe IS NULL) OR (length(xml_nfe) > 0)))
);


--
-- Name: COLUMN pedido_nfe.n_prot; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pedido_nfe.n_prot IS 'Numero do protocolo de autorizacao SEFAZ.
Necessario para cancelamento dentro de 24h.';


--
-- Name: pedidos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedidos (
    id uuid DEFAULT public.uuid_generate_v4() NOT NULL,
    numero bigint NOT NULL,
    cliente_id integer,
    usuario_id integer,
    data_pedido timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    status character varying(20) DEFAULT 'PENDENTE'::character varying NOT NULL,
    valor_total numeric(15,4) DEFAULT 0 NOT NULL,
    desconto_tipo character varying(20),
    desconto_valor numeric(15,2) DEFAULT 0 NOT NULL,
    observacoes text,
    data_faturamento timestamp with time zone,
    data_entrega_prevista date,
    data_entrega_realizada date,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    nfe_emitida boolean DEFAULT false NOT NULL,
    CONSTRAINT chk_pedidos_desconto_tipo CHECK (((desconto_tipo IS NULL) OR ((desconto_tipo)::text = ANY ((ARRAY['VALOR'::character varying, 'PERCENTUAL'::character varying])::text[])))),
    CONSTRAINT chk_pedidos_desconto_valor CHECK ((desconto_valor >= (0)::numeric)),
    CONSTRAINT chk_pedidos_status CHECK (((status)::text = ANY ((ARRAY['RASCUNHO'::character varying, 'PENDENTE'::character varying, 'EM_PROCESSO'::character varying, 'APROVADO'::character varying, 'FATURADO'::character varying, 'CANCELADO'::character varying, 'CONCLUIDO'::character varying])::text[]))),
    CONSTRAINT chk_pedidos_valor_total CHECK ((valor_total >= (0)::numeric))
);


--
-- Name: TABLE pedidos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.pedidos IS 'LEGADO HISTORICO. Gestao_Pedidos removido em 2026-04-18. Vendas novas em pdv_vendas.';


--
-- Name: COLUMN pedidos.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pedidos.status IS 'Ciclo de vida: RASCUNHO, PENDENTE, EM_PROCESSO, APROVADO, FATURADO, CANCELADO, CONCLUIDO.
Kanban visual usa 4 colunas: PENDENTE (agrupa RASCUNHO+PENDENTE), EM_PROCESSO (agrupa EM_PROCESSO+APROVADO), 
CONCLUIDO, FATURADO (read-only). Transicoes via drag-drop validadas em PedidoService::atualizarStatusKanban().';


--
-- Name: COLUMN pedidos.ativo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.pedidos.ativo IS 'Soft delete logico do pedido.';


--
-- Name: pedidos_numero_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pedidos_numero_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pedidos_numero_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pedidos_numero_seq OWNED BY public.pedidos.numero;


--
-- Name: permissoes_nivel; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissoes_nivel (
    nivel_acesso_id integer NOT NULL,
    modulo_id integer NOT NULL
);


--
-- Name: permissoes_personalizadas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissoes_personalizadas (
    usuario_id integer NOT NULL,
    pode_operar_pdv boolean DEFAULT false NOT NULL,
    pode_conferir_caixa boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: permissoes_usuario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissoes_usuario (
    usuario_id integer NOT NULL,
    modulo_id integer NOT NULL
);


--
-- Name: produto_estoque_movimentacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produto_estoque_movimentacoes (
    id bigint NOT NULL,
    produto_id integer NOT NULL,
    usuario_id integer,
    tipo character varying(20) NOT NULL,
    origem character varying(30) DEFAULT 'MANUAL'::character varying NOT NULL,
    referencia_tipo character varying(30),
    referencia_id character varying(64),
    quantidade numeric(15,4) NOT NULL,
    estoque_anterior numeric(15,4) NOT NULL,
    estoque_posterior numeric(15,4) NOT NULL,
    observacao text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_produto_estoque_mov_qtd CHECK ((quantidade > (0)::numeric)),
    CONSTRAINT chk_produto_estoque_mov_saldo CHECK (((estoque_anterior >= (0)::numeric) AND (estoque_posterior >= (0)::numeric))),
    CONSTRAINT chk_produto_estoque_mov_tipo CHECK (((tipo)::text = ANY ((ARRAY['ENTRADA'::character varying, 'SAIDA'::character varying])::text[])))
);


--
-- Name: produto_estoque_movimentacoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produto_estoque_movimentacoes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: produto_estoque_movimentacoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produto_estoque_movimentacoes_id_seq OWNED BY public.produto_estoque_movimentacoes.id;


--
-- Name: produto_fiscal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produto_fiscal (
    id integer NOT NULL,
    produto_id integer NOT NULL,
    ncm character varying(8) NOT NULL,
    cest character varying(7),
    cfop character varying(4) NOT NULL,
    origem character(1) DEFAULT '0'::bpchar NOT NULL,
    csosn_cst character varying(3),
    aliquota_icms numeric(8,4) DEFAULT 0 NOT NULL,
    aliquota_ipi numeric(8,4) DEFAULT 0 NOT NULL,
    aliquota_pis numeric(8,4) DEFAULT 0 NOT NULL,
    aliquota_cofins numeric(8,4) DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    cst_pis character varying(2),
    cst_cofins character varying(2),
    modalidade_bc_icms character(1),
    aliquota_icms_st numeric(8,4) DEFAULT 0,
    reducao_bc_icms numeric(8,4) DEFAULT 0,
    codigo_beneficio_fiscal character varying(10),
    ind_escala character(1) DEFAULT 'S'::bpchar,
    cnpj_fabricante character varying(18),
    CONSTRAINT chk_pf_aliq_cofins CHECK (((aliquota_cofins >= (0)::numeric) AND (aliquota_cofins <= (100)::numeric))),
    CONSTRAINT chk_pf_aliq_icms CHECK (((aliquota_icms >= (0)::numeric) AND (aliquota_icms <= (100)::numeric))),
    CONSTRAINT chk_pf_aliq_ipi CHECK (((aliquota_ipi >= (0)::numeric) AND (aliquota_ipi <= (100)::numeric))),
    CONSTRAINT chk_pf_aliq_pis CHECK (((aliquota_pis >= (0)::numeric) AND (aliquota_pis <= (100)::numeric))),
    CONSTRAINT chk_pf_cest_len CHECK (((cest IS NULL) OR (char_length((cest)::text) = 7))),
    CONSTRAINT chk_pf_cfop_len CHECK ((char_length((cfop)::text) = 4)),
    CONSTRAINT chk_pf_ncm_len CHECK ((char_length((ncm)::text) = 8)),
    CONSTRAINT chk_pf_origem CHECK ((origem = ANY (ARRAY['0'::bpchar, '1'::bpchar, '2'::bpchar, '3'::bpchar, '4'::bpchar, '5'::bpchar, '6'::bpchar, '7'::bpchar, '8'::bpchar]))),
    CONSTRAINT produto_fiscal_ind_escala_check CHECK ((ind_escala = ANY (ARRAY['S'::bpchar, 'N'::bpchar]))),
    CONSTRAINT produto_fiscal_modalidade_bc_icms_check CHECK ((modalidade_bc_icms = ANY (ARRAY['0'::bpchar, '1'::bpchar, '2'::bpchar, '3'::bpchar])))
);


--
-- Name: TABLE produto_fiscal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.produto_fiscal IS '1 produto para 0..1 registro fiscal. Separado para NF-e.';


--
-- Name: COLUMN produto_fiscal.cst_pis; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.cst_pis IS 'Codigo CST do PIS usado na composicao dos tributos da NF-e.';


--
-- Name: COLUMN produto_fiscal.cst_cofins; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.cst_cofins IS 'Codigo CST do COFINS usado na composicao dos tributos da NF-e.';


--
-- Name: COLUMN produto_fiscal.modalidade_bc_icms; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.modalidade_bc_icms IS 'Modalidade de determinacao da base de calculo do ICMS na NF-e.';


--
-- Name: COLUMN produto_fiscal.aliquota_icms_st; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.aliquota_icms_st IS 'Aliquota de ICMS ST informada na NF-e quando houver substituicao tributaria.';


--
-- Name: COLUMN produto_fiscal.reducao_bc_icms; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.reducao_bc_icms IS 'Percentual de reducao da base de calculo do ICMS destacado na NF-e.';


--
-- Name: COLUMN produto_fiscal.codigo_beneficio_fiscal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.codigo_beneficio_fiscal IS 'Codigo de beneficio fiscal vinculado ao item para emissao da NF-e.';


--
-- Name: COLUMN produto_fiscal.ind_escala; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.ind_escala IS 'Indicador de relevancia em escala industrial do fabricante para a NF-e.';


--
-- Name: COLUMN produto_fiscal.cnpj_fabricante; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.produto_fiscal.cnpj_fabricante IS 'CNPJ do fabricante exigido na NF-e quando aplicavel ao item.';


--
-- Name: produto_fiscal_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produto_fiscal_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: produto_fiscal_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produto_fiscal_id_seq OWNED BY public.produto_fiscal.id;


--
-- Name: produtos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produtos (
    id integer NOT NULL,
    uuid uuid DEFAULT public.uuid_generate_v4(),
    codigo character varying(50),
    nome character varying(255) NOT NULL,
    descricao text,
    unidade character varying(10) DEFAULT 'UN'::character varying NOT NULL,
    preco_custo numeric(15,4) DEFAULT 0 NOT NULL,
    preco_venda numeric(15,4) DEFAULT 0 NOT NULL,
    estoque_atual numeric(15,4) DEFAULT 0 NOT NULL,
    estoque_minimo numeric(15,4) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_produtos_estoque CHECK ((estoque_atual >= (0)::numeric)),
    CONSTRAINT chk_produtos_preco_custo CHECK ((preco_custo >= (0)::numeric)),
    CONSTRAINT chk_produtos_preco_venda CHECK ((preco_venda >= (0)::numeric)),
    CONSTRAINT chk_produtos_unidade CHECK (((unidade)::text = ANY ((ARRAY['UN'::character varying, 'KG'::character varying, 'L'::character varying, 'M'::character varying, 'CX'::character varying, 'PC'::character varying, 'MT'::character varying, 'M2'::character varying, 'M3'::character varying, 'PR'::character varying])::text[])))
);


--
-- Name: TABLE produtos; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.produtos IS 'Catalogo de produtos. Dados fiscais em produto_fiscal (0..1).';


--
-- Name: produtos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produtos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: produtos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produtos_id_seq OWNED BY public.produtos.id;


--
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_migrations (
    version character varying(255) NOT NULL,
    checksum character(40) NOT NULL,
    executed_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: servicos_catalogo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.servicos_catalogo (
    id integer NOT NULL,
    nome character varying(255) NOT NULL,
    descricao text,
    valor_base numeric(15,4) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_servicos_catalogo_valor_base CHECK ((valor_base >= (0)::numeric))
);


--
-- Name: TABLE servicos_catalogo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.servicos_catalogo IS 'Cadastro mestre dos servicos oferecidos pela empresa (mini-modulo).';


--
-- Name: servicos_catalogo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.servicos_catalogo_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: servicos_catalogo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.servicos_catalogo_id_seq OWNED BY public.servicos_catalogo.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    uuid uuid DEFAULT public.uuid_generate_v4(),
    nome character varying(100) NOT NULL,
    email character varying(100) NOT NULL,
    senha character varying(255) NOT NULL,
    telefone character varying(20),
    nivel_acesso_id integer DEFAULT 4 NOT NULL,
    ativo boolean DEFAULT true,
    foto_perfil character varying(255),
    ultimo_acesso timestamp with time zone,
    token_reset_senha character varying(255),
    token_expiracao timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: vw_dre; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_dre AS
 SELECT cd.id AS categoria_id,
    cd.nome AS categoria,
    cd.tipo,
    cd.ordem,
    COALESCE(cr.total, (0)::numeric) AS total_receber,
    COALESCE(cp.total, (0)::numeric) AS total_pagar,
    COALESCE(mov.total_entrada, (0)::numeric) AS total_mov_entrada,
    COALESCE(mov.total_saida, (0)::numeric) AS total_mov_saida
   FROM (((public.categorias_dre cd
     LEFT JOIN ( SELECT contas_receber.categoria_dre_id,
            sum(contas_receber.valor_pago) AS total
           FROM public.contas_receber
          WHERE ((contas_receber.status)::text = 'PAGO'::text)
          GROUP BY contas_receber.categoria_dre_id) cr ON ((cr.categoria_dre_id = cd.id)))
     LEFT JOIN ( SELECT contas_pagar.categoria_dre_id,
            sum(contas_pagar.valor_pago) AS total
           FROM public.contas_pagar
          WHERE ((contas_pagar.status)::text = 'PAGO'::text)
          GROUP BY contas_pagar.categoria_dre_id) cp ON ((cp.categoria_dre_id = cd.id)))
     LEFT JOIN ( SELECT movimentacoes.categoria_dre_id,
            sum(
                CASE
                    WHEN ((movimentacoes.tipo)::text = 'Entrada'::text) THEN movimentacoes.valor
                    ELSE (0)::numeric
                END) AS total_entrada,
            sum(
                CASE
                    WHEN ((movimentacoes.tipo)::text = 'Saída'::text) THEN movimentacoes.valor
                    ELSE (0)::numeric
                END) AS total_saida
           FROM public.movimentacoes
          WHERE (movimentacoes.afeta_saldo = true)
          GROUP BY movimentacoes.categoria_dre_id) mov ON ((mov.categoria_dre_id = cd.id)))
  WHERE (cd.ativo = true)
  ORDER BY cd.ordem;


--
-- Name: VIEW vw_dre; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON VIEW public.vw_dre IS 'Visao agregada de categorias DRE x totais pagos/recebidos/movimentados (sem filtro de periodo).';


--
-- Name: vw_permissoes_usuario; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_permissoes_usuario AS
 SELECT u.id AS usuario_id,
    u.nivel_acesso_id,
    m.slug AS modulo_slug
   FROM ((public.usuarios u
     JOIN public.permissoes_nivel pn ON ((pn.nivel_acesso_id = u.nivel_acesso_id)))
     JOIN public.modulos m ON ((m.id = pn.modulo_id)))
  WHERE (u.nivel_acesso_id <> 4)
UNION ALL
 SELECT u.id AS usuario_id,
    u.nivel_acesso_id,
    m.slug AS modulo_slug
   FROM ((public.usuarios u
     JOIN public.permissoes_usuario pu ON ((pu.usuario_id = u.id)))
     JOIN public.modulos m ON ((m.id = pu.modulo_id)))
  WHERE (u.nivel_acesso_id = 4);


--
-- Name: vw_produtos_sem_fiscal; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_produtos_sem_fiscal AS
 SELECT p.id,
    p.codigo,
    p.nome,
    p.ativo,
        CASE
            WHEN (pf.id IS NULL) THEN 'SEM_REGISTRO'::text
            ELSE 'COM_REGISTRO'::text
        END AS tem_fiscal,
    ((pf.ncm IS NOT NULL) AND (pf.cfop IS NOT NULL) AND (pf.csosn_cst IS NOT NULL) AND (pf.cst_pis IS NOT NULL) AND (pf.cst_cofins IS NOT NULL) AND (pf.modalidade_bc_icms IS NOT NULL)) AS fiscal_completo_nfe
   FROM (public.produtos p
     LEFT JOIN public.produto_fiscal pf ON ((pf.produto_id = p.id)))
  WHERE (p.ativo = true)
  ORDER BY ((pf.ncm IS NOT NULL) AND (pf.cfop IS NOT NULL) AND (pf.csosn_cst IS NOT NULL) AND (pf.cst_pis IS NOT NULL) AND (pf.cst_cofins IS NOT NULL) AND (pf.modalidade_bc_icms IS NOT NULL)), p.nome;


--
-- Name: vw_status_plano; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_status_plano AS
 SELECT l.plano_slug,
    l.max_usuarios,
    l.status AS licenca_status,
    l.licenca_fim,
    GREATEST(0, (l.licenca_fim - CURRENT_DATE)) AS dias_restantes,
    u.total_ativos AS total_usuarios_ativos,
    u.total_ativos,
        CASE
            WHEN (l.max_usuarios IS NULL) THEN true
            WHEN (u.total_ativos < l.max_usuarios) THEN true
            ELSE false
        END AS pode_criar_usuario,
        CASE
            WHEN (l.max_usuarios IS NULL) THEN true
            WHEN (u.total_ativos < l.max_usuarios) THEN true
            ELSE false
        END AS pode_criar
   FROM (public.licenca l
     CROSS JOIN LATERAL ( SELECT count(*) AS total_ativos
           FROM public.usuarios
          WHERE ((usuarios.ativo = true) AND (NOT ((lower((usuarios.email)::text) = lower('admin@suporte.com'::text)) AND (usuarios.nivel_acesso_id = 1))))) u)
 LIMIT 1;


--
-- Name: auditoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria ALTER COLUMN id SET DEFAULT nextval('public.auditoria_id_seq'::regclass);


--
-- Name: auditoria_usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_usuarios ALTER COLUMN id SET DEFAULT nextval('public.auditoria_usuarios_id_seq'::regclass);


--
-- Name: categorias_dre id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_dre ALTER COLUMN id SET DEFAULT nextval('public.categorias_dre_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: configuracoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);


--
-- Name: contas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas ALTER COLUMN id SET DEFAULT nextval('public.contas_id_seq'::regclass);


--
-- Name: contas_pagar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar ALTER COLUMN id SET DEFAULT nextval('public.contas_pagar_id_seq'::regclass);


--
-- Name: contas_receber id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber ALTER COLUMN id SET DEFAULT nextval('public.contas_receber_id_seq'::regclass);


--
-- Name: empresa_fiscal_servico id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_fiscal_servico ALTER COLUMN id SET DEFAULT nextval('public.empresa_fiscal_servico_id_seq'::regclass);


--
-- Name: empresa_local id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_local ALTER COLUMN id SET DEFAULT nextval('public.empresa_local_id_seq'::regclass);


--
-- Name: formas_pagamento id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.formas_pagamento ALTER COLUMN id SET DEFAULT nextval('public.formas_pagamento_id_seq'::regclass);


--
-- Name: licenca id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licenca ALTER COLUMN id SET DEFAULT nextval('public.licenca_id_seq'::regclass);


--
-- Name: modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos ALTER COLUMN id SET DEFAULT nextval('public.modulos_id_seq'::regclass);


--
-- Name: movimentacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes ALTER COLUMN id SET DEFAULT nextval('public.movimentacoes_id_seq'::regclass);


--
-- Name: niveis_acesso id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveis_acesso ALTER COLUMN id SET DEFAULT nextval('public.niveis_acesso_id_seq'::regclass);


--
-- Name: nota_fiscal_servico id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nota_fiscal_servico ALTER COLUMN id SET DEFAULT nextval('public.nota_fiscal_servico_id_seq'::regclass);


--
-- Name: notificacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacoes ALTER COLUMN id SET DEFAULT nextval('public.notificacoes_id_seq'::regclass);


--
-- Name: pdv_caixas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_caixas ALTER COLUMN id SET DEFAULT nextval('public.pdv_caixas_id_seq'::regclass);


--
-- Name: pdv_conferencia_itens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_conferencia_itens ALTER COLUMN id SET DEFAULT nextval('public.pdv_conferencia_itens_id_seq'::regclass);


--
-- Name: pdv_lancamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_lancamentos ALTER COLUMN id SET DEFAULT nextval('public.pdv_lancamentos_id_seq'::regclass);


--
-- Name: pdv_venda_itens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_venda_itens ALTER COLUMN id SET DEFAULT nextval('public.pdv_venda_itens_id_seq'::regclass);


--
-- Name: pdv_vendas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas ALTER COLUMN id SET DEFAULT nextval('public.pdv_vendas_id_seq'::regclass);


--
-- Name: pdv_vendas numero; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas ALTER COLUMN numero SET DEFAULT nextval('public.pdv_vendas_numero_seq'::regclass);


--
-- Name: pedidos numero; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos ALTER COLUMN numero SET DEFAULT nextval('public.pedidos_numero_seq'::regclass);


--
-- Name: produto_estoque_movimentacoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_estoque_movimentacoes ALTER COLUMN id SET DEFAULT nextval('public.produto_estoque_movimentacoes_id_seq'::regclass);


--
-- Name: produto_fiscal id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_fiscal ALTER COLUMN id SET DEFAULT nextval('public.produto_fiscal_id_seq'::regclass);


--
-- Name: produtos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos ALTER COLUMN id SET DEFAULT nextval('public.produtos_id_seq'::regclass);


--
-- Name: servicos_catalogo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.servicos_catalogo ALTER COLUMN id SET DEFAULT nextval('public.servicos_catalogo_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Name: auditoria auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_pkey PRIMARY KEY (id);


--
-- Name: auditoria_usuarios auditoria_usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria_usuarios
    ADD CONSTRAINT auditoria_usuarios_pkey PRIMARY KEY (id);


--
-- Name: categorias_dre categorias_dre_nome_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_dre
    ADD CONSTRAINT categorias_dre_nome_unique UNIQUE (nome);


--
-- Name: categorias_dre categorias_dre_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_dre
    ADD CONSTRAINT categorias_dre_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_cpf_cnpj_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_cpf_cnpj_key UNIQUE (cpf_cnpj);


--
-- Name: clientes clientes_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_email_key UNIQUE (email);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: configuracoes configuracoes_chave_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_chave_key UNIQUE (chave);


--
-- Name: configuracoes configuracoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);


--
-- Name: contas_pagar contas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_pkey PRIMARY KEY (id);


--
-- Name: contas contas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas
    ADD CONSTRAINT contas_pkey PRIMARY KEY (id);


--
-- Name: contas_receber contas_receber_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber
    ADD CONSTRAINT contas_receber_pkey PRIMARY KEY (id);


--
-- Name: empresa_fiscal_servico empresa_fiscal_servico_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_fiscal_servico
    ADD CONSTRAINT empresa_fiscal_servico_pkey PRIMARY KEY (id);


--
-- Name: empresa_local empresa_local_cnpj_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_local
    ADD CONSTRAINT empresa_local_cnpj_key UNIQUE (cnpj);


--
-- Name: empresa_local empresa_local_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_local
    ADD CONSTRAINT empresa_local_pkey PRIMARY KEY (id);


--
-- Name: formas_pagamento formas_pagamento_nome_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.formas_pagamento
    ADD CONSTRAINT formas_pagamento_nome_key UNIQUE (nome);


--
-- Name: formas_pagamento formas_pagamento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.formas_pagamento
    ADD CONSTRAINT formas_pagamento_pkey PRIMARY KEY (id);


--
-- Name: licenca licenca_chave_licenca_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licenca
    ADD CONSTRAINT licenca_chave_licenca_key UNIQUE (chave_licenca);


--
-- Name: licenca licenca_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licenca
    ADD CONSTRAINT licenca_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pkey PRIMARY KEY (id);


--
-- Name: modulos modulos_slug_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_slug_key UNIQUE (slug);


--
-- Name: movimentacoes movimentacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_pkey PRIMARY KEY (id);


--
-- Name: niveis_acesso niveis_acesso_nome_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveis_acesso
    ADD CONSTRAINT niveis_acesso_nome_key UNIQUE (nome);


--
-- Name: niveis_acesso niveis_acesso_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.niveis_acesso
    ADD CONSTRAINT niveis_acesso_pkey PRIMARY KEY (id);


--
-- Name: nota_fiscal_servico nota_fiscal_servico_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nota_fiscal_servico
    ADD CONSTRAINT nota_fiscal_servico_pkey PRIMARY KEY (id);


--
-- Name: notificacoes notificacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacoes
    ADD CONSTRAINT notificacoes_pkey PRIMARY KEY (id);


--
-- Name: pdv_caixas pdv_caixas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_caixas
    ADD CONSTRAINT pdv_caixas_pkey PRIMARY KEY (id);


--
-- Name: pdv_conferencia_itens pdv_conferencia_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_conferencia_itens
    ADD CONSTRAINT pdv_conferencia_itens_pkey PRIMARY KEY (id);


--
-- Name: pdv_lancamentos pdv_lancamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_lancamentos
    ADD CONSTRAINT pdv_lancamentos_pkey PRIMARY KEY (id);


--
-- Name: pdv_venda_itens pdv_venda_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_venda_itens
    ADD CONSTRAINT pdv_venda_itens_pkey PRIMARY KEY (id);


--
-- Name: pdv_vendas pdv_vendas_numero_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_numero_key UNIQUE (numero);


--
-- Name: pdv_vendas pdv_vendas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_pkey PRIMARY KEY (id);


--
-- Name: pedido_itens pedido_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_itens
    ADD CONSTRAINT pedido_itens_pkey PRIMARY KEY (id);


--
-- Name: pedido_nfe pedido_nfe_chave_acesso_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_nfe
    ADD CONSTRAINT pedido_nfe_chave_acesso_key UNIQUE (chave_acesso);


--
-- Name: pedido_nfe pedido_nfe_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_nfe
    ADD CONSTRAINT pedido_nfe_pkey PRIMARY KEY (id);


--
-- Name: pedidos pedidos_numero_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_numero_key UNIQUE (numero);


--
-- Name: pedidos pedidos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_pkey PRIMARY KEY (id);


--
-- Name: permissoes_nivel permissoes_nivel_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_nivel
    ADD CONSTRAINT permissoes_nivel_pkey PRIMARY KEY (nivel_acesso_id, modulo_id);


--
-- Name: permissoes_personalizadas permissoes_personalizadas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_personalizadas
    ADD CONSTRAINT permissoes_personalizadas_pkey PRIMARY KEY (usuario_id);


--
-- Name: permissoes_usuario permissoes_usuario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_usuario
    ADD CONSTRAINT permissoes_usuario_pkey PRIMARY KEY (usuario_id, modulo_id);


--
-- Name: produto_estoque_movimentacoes produto_estoque_movimentacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_estoque_movimentacoes
    ADD CONSTRAINT produto_estoque_movimentacoes_pkey PRIMARY KEY (id);


--
-- Name: produto_fiscal produto_fiscal_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_fiscal
    ADD CONSTRAINT produto_fiscal_pkey PRIMARY KEY (id);


--
-- Name: produtos produtos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_pkey PRIMARY KEY (id);


--
-- Name: produtos produtos_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos
    ADD CONSTRAINT produtos_uuid_key UNIQUE (uuid);


--
-- Name: schema_migrations schema_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.schema_migrations
    ADD CONSTRAINT schema_migrations_pkey PRIMARY KEY (version);


--
-- Name: servicos_catalogo servicos_catalogo_nome_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.servicos_catalogo
    ADD CONSTRAINT servicos_catalogo_nome_key UNIQUE (nome);


--
-- Name: servicos_catalogo servicos_catalogo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.servicos_catalogo
    ADD CONSTRAINT servicos_catalogo_pkey PRIMARY KEY (id);


--
-- Name: pdv_conferencia_itens uq_pdv_conferencia_caixa_forma; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_conferencia_itens
    ADD CONSTRAINT uq_pdv_conferencia_caixa_forma UNIQUE (caixa_id, forma_pagamento_id);


--
-- Name: produto_fiscal uq_produto_fiscal_produto; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_fiscal
    ADD CONSTRAINT uq_produto_fiscal_produto UNIQUE (produto_id);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: idx_auditoria_created_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_created_at ON public.auditoria USING btree (created_at DESC);


--
-- Name: idx_auditoria_modulo_acao; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_modulo_acao ON public.auditoria_usuarios USING btree (modulo, acao);


--
-- Name: idx_auditoria_tabela; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_tabela ON public.auditoria USING btree (tabela);


--
-- Name: idx_auditoria_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_usuario ON public.auditoria USING btree (usuario_id);


--
-- Name: idx_auditoria_usuario_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_auditoria_usuario_id ON public.auditoria_usuarios USING btree (usuario_id);


--
-- Name: idx_categorias_dre_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_dre_ativo ON public.categorias_dre USING btree (ativo);


--
-- Name: idx_categorias_dre_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_categorias_dre_tipo ON public.categorias_dre USING btree (tipo);


--
-- Name: idx_clientes_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_ativo ON public.clientes USING btree (ativo);


--
-- Name: idx_clientes_cpf_cnpj; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_cpf_cnpj ON public.clientes USING btree (cpf_cnpj);


--
-- Name: idx_clientes_email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_email ON public.clientes USING btree (email);


--
-- Name: idx_clientes_nome_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_nome_trgm ON public.clientes USING gin (nome public.gin_trgm_ops);


--
-- Name: INDEX idx_clientes_nome_trgm; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_clientes_nome_trgm IS 'Indice GIN para busca ILIKE otimizada. Performance: 400ms para 40ms para 10.000 registros';


--
-- Name: idx_clientes_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_clientes_usuario ON public.clientes USING btree (usuario_id);


--
-- Name: idx_contas_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_ativo ON public.contas USING btree (ativo);


--
-- Name: idx_contas_pagar_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_pagar_categoria ON public.contas_pagar USING btree (categoria_dre_id);


--
-- Name: idx_contas_pagar_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_pagar_status ON public.contas_pagar USING btree (status);


--
-- Name: idx_contas_pagar_vencimento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_pagar_vencimento ON public.contas_pagar USING btree (data_vencimento);


--
-- Name: idx_contas_receber_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_categoria ON public.contas_receber USING btree (categoria_dre_id);


--
-- Name: idx_contas_receber_cliente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_cliente ON public.contas_receber USING btree (cliente_id);


--
-- Name: idx_contas_receber_forma_pagamento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_forma_pagamento ON public.contas_receber USING btree (forma_pagamento_id) WHERE (forma_pagamento_id IS NOT NULL);


--
-- Name: idx_contas_receber_origem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_origem ON public.contas_receber USING btree (origem) WHERE (origem IS NOT NULL);


--
-- Name: idx_contas_receber_pdv_venda; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_pdv_venda ON public.contas_receber USING btree (pdv_venda_id) WHERE (pdv_venda_id IS NOT NULL);


--
-- Name: idx_contas_receber_pedido_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_pedido_id ON public.contas_receber USING btree (pedido_id) WHERE (pedido_id IS NOT NULL);


--
-- Name: idx_contas_receber_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_status ON public.contas_receber USING btree (status);


--
-- Name: idx_contas_receber_vencimento; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_receber_vencimento ON public.contas_receber USING btree (data_vencimento);


--
-- Name: idx_contas_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_contas_tipo ON public.contas USING btree (tipo);


--
-- Name: idx_formas_pagamento_adquirente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_formas_pagamento_adquirente ON public.formas_pagamento USING btree (adquirente_id);


--
-- Name: idx_formas_pagamento_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_formas_pagamento_ativo ON public.formas_pagamento USING btree (ativo);


--
-- Name: idx_formas_pagamento_conta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_formas_pagamento_conta ON public.formas_pagamento USING btree (conta_id);


--
-- Name: idx_licenca_fim; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_licenca_fim ON public.licenca USING btree (licenca_fim);


--
-- Name: idx_licenca_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_licenca_status ON public.licenca USING btree (status);


--
-- Name: idx_movimentacoes_categoria; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_categoria ON public.movimentacoes USING btree (categoria_dre_id);


--
-- Name: idx_movimentacoes_conta; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_conta ON public.movimentacoes USING btree (conta_id);


--
-- Name: idx_movimentacoes_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_data ON public.movimentacoes USING btree (data_movimentacao DESC);


--
-- Name: idx_movimentacoes_pedido; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_pedido ON public.movimentacoes USING btree (pedido_id) WHERE (pedido_id IS NOT NULL);


--
-- Name: idx_movimentacoes_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_movimentacoes_tipo ON public.movimentacoes USING btree (tipo);


--
-- Name: idx_nota_fiscal_servico_data_emissao; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_nota_fiscal_servico_data_emissao ON public.nota_fiscal_servico USING btree (data_emissao);


--
-- Name: idx_nota_fiscal_servico_numero_rps; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_nota_fiscal_servico_numero_rps ON public.nota_fiscal_servico USING btree (numero_rps);


--
-- Name: idx_nota_fiscal_servico_servico_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_nota_fiscal_servico_servico_id ON public.nota_fiscal_servico USING btree (servico_id);


--
-- Name: idx_nota_fiscal_servico_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_nota_fiscal_servico_status ON public.nota_fiscal_servico USING btree (status);


--
-- Name: idx_notificacoes_lida; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificacoes_lida ON public.notificacoes USING btree (lida);


--
-- Name: idx_notificacoes_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificacoes_usuario ON public.notificacoes USING btree (usuario_id);


--
-- Name: idx_pdv_caixas_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_caixas_data ON public.pdv_caixas USING btree (data_abertura);


--
-- Name: idx_pdv_caixas_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_caixas_status ON public.pdv_caixas USING btree (status);


--
-- Name: idx_pdv_caixas_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_caixas_usuario ON public.pdv_caixas USING btree (usuario_abertura_id);


--
-- Name: idx_pdv_conferencia_caixa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_conferencia_caixa ON public.pdv_conferencia_itens USING btree (caixa_id);


--
-- Name: idx_pdv_lancamentos_caixa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_lancamentos_caixa ON public.pdv_lancamentos USING btree (caixa_id);


--
-- Name: idx_pdv_lancamentos_pedido; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_lancamentos_pedido ON public.pdv_lancamentos USING btree (pedido_id);


--
-- Name: idx_pdv_venda_itens_venda; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_venda_itens_venda ON public.pdv_venda_itens USING btree (venda_id);


--
-- Name: idx_pdv_vendas_caixa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_caixa ON public.pdv_vendas USING btree (caixa_id);


--
-- Name: idx_pdv_vendas_cancelado_por; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_cancelado_por ON public.pdv_vendas USING btree (cancelado_por_id) WHERE (cancelado_por_id IS NOT NULL);


--
-- Name: idx_pdv_vendas_conferida; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_conferida ON public.pdv_vendas USING btree (caixa_id, conferida) WHERE ((conferida = false) AND ((status)::text = 'faturado'::text));


--
-- Name: idx_pdv_vendas_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_data ON public.pdv_vendas USING btree (created_at DESC);


--
-- Name: idx_pdv_vendas_kanban; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_kanban ON public.pdv_vendas USING btree (caixa_id, status, created_at DESC) WHERE (((origem)::text = 'fluxo'::text) AND ((status)::text <> 'cancelado'::text));


--
-- Name: idx_pdv_vendas_numero; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_numero ON public.pdv_vendas USING btree (numero);


--
-- Name: idx_pdv_vendas_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pdv_vendas_status ON public.pdv_vendas USING btree (status);


--
-- Name: idx_pedido_itens_pedido_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedido_itens_pedido_id ON public.pedido_itens USING btree (pedido_id);


--
-- Name: idx_pedido_itens_produto_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedido_itens_produto_id ON public.pedido_itens USING btree (produto_id) WHERE (produto_id IS NOT NULL);


--
-- Name: idx_pedidos_cliente_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_cliente_id ON public.pedidos USING btree (cliente_id) WHERE (ativo = true);


--
-- Name: INDEX idx_pedidos_cliente_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_pedidos_cliente_id IS 'Indice parcial para JOINs com clientes. Ignora pedidos inativos para economizar espaco.';


--
-- Name: idx_pedidos_data_pedido; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_data_pedido ON public.pedidos USING btree (data_pedido DESC);


--
-- Name: INDEX idx_pedidos_data_pedido; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_pedidos_data_pedido IS 'Indice para ordenacao cronologica DESC (mais recente primeiro). Usado em listagens gerais.';


--
-- Name: idx_pedidos_kanban_principal; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_kanban_principal ON public.pedidos USING btree (ativo, data_pedido DESC, status) WHERE (ativo = true);


--
-- Name: INDEX idx_pedidos_kanban_principal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_pedidos_kanban_principal IS 'Indice composto otimizado para view Kanban. Reduz tempo de query de 1.2s para 120ms (90% melhoria).
Ordem dos campos: ativo (filtro WHERE), data_pedido DESC (ordenacao), status (agrupamento Kanban).';


--
-- Name: idx_pedidos_observacoes_trgm; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_observacoes_trgm ON public.pedidos USING gin (observacoes public.gin_trgm_ops);


--
-- Name: INDEX idx_pedidos_observacoes_trgm; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_pedidos_observacoes_trgm IS 'Indice GIN para busca ILIKE em observacoes. Performance: 800ms para 80ms para 10.000 registros.';


--
-- Name: idx_pedidos_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_status ON public.pedidos USING btree (status) WHERE (ativo = true);


--
-- Name: INDEX idx_pedidos_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON INDEX public.idx_pedidos_status IS 'Indice parcial para filtros por status. Usado em queries da Lista de Pedidos.';


--
-- Name: idx_pedidos_usuario_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_pedidos_usuario_id ON public.pedidos USING btree (usuario_id) WHERE (usuario_id IS NOT NULL);


--
-- Name: idx_permissoes_personalizadas_conferir; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_permissoes_personalizadas_conferir ON public.permissoes_personalizadas USING btree (pode_conferir_caixa);


--
-- Name: idx_permissoes_personalizadas_operar; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_permissoes_personalizadas_operar ON public.permissoes_personalizadas USING btree (pode_operar_pdv);


--
-- Name: idx_prod_estoque_mov_produto_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_prod_estoque_mov_produto_data ON public.produto_estoque_movimentacoes USING btree (produto_id, created_at DESC);


--
-- Name: idx_prod_estoque_mov_tipo_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_prod_estoque_mov_tipo_data ON public.produto_estoque_movimentacoes USING btree (tipo, created_at DESC);


--
-- Name: idx_produto_fiscal_produto; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_produto_fiscal_produto ON public.produto_fiscal USING btree (produto_id);


--
-- Name: idx_produtos_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_produtos_ativo ON public.produtos USING btree (ativo);


--
-- Name: idx_produtos_codigo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_produtos_codigo ON public.produtos USING btree (codigo);


--
-- Name: idx_usuarios_ativo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_ativo ON public.usuarios USING btree (ativo);


--
-- Name: idx_usuarios_email; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_email ON public.usuarios USING btree (email);


--
-- Name: idx_usuarios_nivel_acesso; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_usuarios_nivel_acesso ON public.usuarios USING btree (nivel_acesso_id);


--
-- Name: uq_pdv_lancamentos_pedido_unico; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_pdv_lancamentos_pedido_unico ON public.pdv_lancamentos USING btree (pedido_id);


--
-- Name: uq_pedido_nfe_pedido_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_pedido_nfe_pedido_id ON public.pedido_nfe USING btree (pedido_id);


--
-- Name: contas_pagar trg_bloquear_delete_cp_protegido; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_delete_cp_protegido BEFORE DELETE ON public.contas_pagar FOR EACH ROW EXECUTE FUNCTION public.bloquear_delete_protegido();


--
-- Name: contas_receber trg_bloquear_delete_cr_com_nfe; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_delete_cr_com_nfe BEFORE DELETE OR UPDATE ON public.contas_receber FOR EACH ROW EXECUTE FUNCTION public.bloquear_delete_update_cr_com_nfe_autorizada();


--
-- Name: TRIGGER trg_bloquear_delete_cr_com_nfe ON contas_receber; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TRIGGER trg_bloquear_delete_cr_com_nfe ON public.contas_receber IS 'Impede DELETE e mudanca de status para CANCELADO em contas_receber quando o pedido vinculado possui NF-e AUTORIZADA.';


--
-- Name: contas_receber trg_bloquear_delete_cr_protegido; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_delete_cr_protegido BEFORE DELETE ON public.contas_receber FOR EACH ROW EXECUTE FUNCTION public.bloquear_delete_protegido();


--
-- Name: movimentacoes trg_bloquear_delete_mov_protegido; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_delete_mov_protegido BEFORE DELETE ON public.movimentacoes FOR EACH ROW EXECUTE FUNCTION public.bloquear_delete_protegido();


--
-- Name: pdv_vendas trg_bloquear_delete_pdv_venda; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_delete_pdv_venda BEFORE DELETE ON public.pdv_vendas FOR EACH ROW EXECUTE FUNCTION public.bloquear_delete_venda_protegida();


--
-- Name: licenca trg_bloquear_licenca_vencida; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_licenca_vencida BEFORE INSERT OR UPDATE ON public.licenca FOR EACH ROW EXECUTE FUNCTION public.bloquear_licenca_vencida();


--
-- Name: pdv_vendas trg_bloquear_venda_caixa_fechado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bloquear_venda_caixa_fechado BEFORE INSERT ON public.pdv_vendas FOR EACH ROW EXECUTE FUNCTION public.bloquear_venda_caixa_fechado();


--
-- Name: categorias_dre trg_categorias_dre_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_categorias_dre_updated_at BEFORE UPDATE ON public.categorias_dre FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: clientes trg_clientes_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_clientes_updated_at BEFORE UPDATE ON public.clientes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: configuracoes trg_configuracoes_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_configuracoes_updated_at BEFORE UPDATE ON public.configuracoes FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: contas_pagar trg_contas_pagar_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_contas_pagar_updated_at BEFORE UPDATE ON public.contas_pagar FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: contas_receber trg_contas_receber_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_contas_receber_updated_at BEFORE UPDATE ON public.contas_receber FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: contas trg_contas_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_contas_updated_at BEFORE UPDATE ON public.contas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: empresa_fiscal_servico trg_empresa_fiscal_servico_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_empresa_fiscal_servico_updated_at BEFORE UPDATE ON public.empresa_fiscal_servico FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: formas_pagamento trg_formas_pagamento_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_formas_pagamento_updated_at BEFORE UPDATE ON public.formas_pagamento FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: licenca trg_licenca_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_licenca_updated_at BEFORE UPDATE ON public.licenca FOR EACH ROW EXECUTE FUNCTION public.update_licenca_updated_at();


--
-- Name: niveis_acesso trg_niveis_acesso_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_niveis_acesso_updated_at BEFORE UPDATE ON public.niveis_acesso FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: nota_fiscal_servico trg_nota_fiscal_servico_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_nota_fiscal_servico_updated_at BEFORE UPDATE ON public.nota_fiscal_servico FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: pdv_caixas trg_pdv_caixas_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_pdv_caixas_updated_at BEFORE UPDATE ON public.pdv_caixas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: pdv_vendas trg_pdv_vendas_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_pdv_vendas_updated_at BEFORE UPDATE ON public.pdv_vendas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: pedido_itens trg_pedido_itens_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_pedido_itens_updated_at BEFORE UPDATE ON public.pedido_itens FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: pedidos trg_pedidos_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_pedidos_updated_at BEFORE UPDATE ON public.pedidos FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: permissoes_personalizadas trg_permissoes_personalizadas_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_permissoes_personalizadas_updated_at BEFORE UPDATE ON public.permissoes_personalizadas FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: produto_fiscal trg_produto_fiscal_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_produto_fiscal_updated_at BEFORE UPDATE ON public.produto_fiscal FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: produtos trg_produtos_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_produtos_updated_at BEFORE UPDATE ON public.produtos FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: usuarios trg_usuarios_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_usuarios_updated_at BEFORE UPDATE ON public.usuarios FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: movimentacoes trigger_atualizar_saldo_conta; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trigger_atualizar_saldo_conta AFTER INSERT OR DELETE ON public.movimentacoes FOR EACH ROW EXECUTE FUNCTION public.atualizar_saldo_conta_trigger();


--
-- Name: TRIGGER trigger_atualizar_saldo_conta ON movimentacoes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TRIGGER trigger_atualizar_saldo_conta ON public.movimentacoes IS 'Atualiza saldo_atual da conta ao inserir/deletar movimentacoes. 
UPDATE manual para consistencia.';


--
-- Name: auditoria auditoria_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.auditoria
    ADD CONSTRAINT auditoria_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: clientes clientes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: contas_pagar contas_pagar_categoria_dre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_categoria_dre_id_fkey FOREIGN KEY (categoria_dre_id) REFERENCES public.categorias_dre(id) ON DELETE SET NULL;


--
-- Name: contas_pagar contas_pagar_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_pagar
    ADD CONSTRAINT contas_pagar_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: contas_receber contas_receber_categoria_dre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber
    ADD CONSTRAINT contas_receber_categoria_dre_id_fkey FOREIGN KEY (categoria_dre_id) REFERENCES public.categorias_dre(id) ON DELETE SET NULL;


--
-- Name: contas_receber contas_receber_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber
    ADD CONSTRAINT contas_receber_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: contas_receber contas_receber_forma_pagamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber
    ADD CONSTRAINT contas_receber_forma_pagamento_id_fkey FOREIGN KEY (forma_pagamento_id) REFERENCES public.formas_pagamento(id) ON DELETE SET NULL;


--
-- Name: contas_receber contas_receber_pdv_venda_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contas_receber
    ADD CONSTRAINT contas_receber_pdv_venda_id_fkey FOREIGN KEY (pdv_venda_id) REFERENCES public.pdv_vendas(id) ON DELETE SET NULL;


--
-- Name: formas_pagamento formas_pagamento_adquirente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.formas_pagamento
    ADD CONSTRAINT formas_pagamento_adquirente_id_fkey FOREIGN KEY (adquirente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: formas_pagamento formas_pagamento_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.formas_pagamento
    ADD CONSTRAINT formas_pagamento_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas(id) ON DELETE SET NULL;


--
-- Name: movimentacoes movimentacoes_categoria_dre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_categoria_dre_id_fkey FOREIGN KEY (categoria_dre_id) REFERENCES public.categorias_dre(id) ON DELETE SET NULL;


--
-- Name: movimentacoes movimentacoes_conta_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_conta_id_fkey FOREIGN KEY (conta_id) REFERENCES public.contas(id) ON DELETE CASCADE;


--
-- Name: movimentacoes movimentacoes_conta_pagar_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_conta_pagar_id_fkey FOREIGN KEY (conta_pagar_id) REFERENCES public.contas_pagar(id) ON DELETE SET NULL;


--
-- Name: movimentacoes movimentacoes_conta_receber_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_conta_receber_id_fkey FOREIGN KEY (conta_receber_id) REFERENCES public.contas_receber(id) ON DELETE SET NULL;


--
-- Name: movimentacoes movimentacoes_forma_pagamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimentacoes
    ADD CONSTRAINT movimentacoes_forma_pagamento_id_fkey FOREIGN KEY (forma_pagamento_id) REFERENCES public.formas_pagamento(id) ON DELETE SET NULL;


--
-- Name: notificacoes notificacoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacoes
    ADD CONSTRAINT notificacoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: pdv_caixas pdv_caixas_conferencia_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_caixas
    ADD CONSTRAINT pdv_caixas_conferencia_usuario_id_fkey FOREIGN KEY (conferencia_usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pdv_caixas pdv_caixas_usuario_abertura_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_caixas
    ADD CONSTRAINT pdv_caixas_usuario_abertura_id_fkey FOREIGN KEY (usuario_abertura_id) REFERENCES public.usuarios(id);


--
-- Name: pdv_conferencia_itens pdv_conferencia_itens_caixa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_conferencia_itens
    ADD CONSTRAINT pdv_conferencia_itens_caixa_id_fkey FOREIGN KEY (caixa_id) REFERENCES public.pdv_caixas(id) ON DELETE CASCADE;


--
-- Name: pdv_conferencia_itens pdv_conferencia_itens_forma_pagamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_conferencia_itens
    ADD CONSTRAINT pdv_conferencia_itens_forma_pagamento_id_fkey FOREIGN KEY (forma_pagamento_id) REFERENCES public.formas_pagamento(id);


--
-- Name: pdv_lancamentos pdv_lancamentos_caixa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_lancamentos
    ADD CONSTRAINT pdv_lancamentos_caixa_id_fkey FOREIGN KEY (caixa_id) REFERENCES public.pdv_caixas(id) ON DELETE CASCADE;


--
-- Name: pdv_lancamentos pdv_lancamentos_pedido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_lancamentos
    ADD CONSTRAINT pdv_lancamentos_pedido_id_fkey FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE RESTRICT;


--
-- Name: pdv_lancamentos pdv_lancamentos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_lancamentos
    ADD CONSTRAINT pdv_lancamentos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pdv_venda_itens pdv_venda_itens_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_venda_itens
    ADD CONSTRAINT pdv_venda_itens_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE SET NULL;


--
-- Name: pdv_venda_itens pdv_venda_itens_servico_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_venda_itens
    ADD CONSTRAINT pdv_venda_itens_servico_id_fkey FOREIGN KEY (servico_id) REFERENCES public.servicos_catalogo(id) ON DELETE SET NULL;


--
-- Name: pdv_venda_itens pdv_venda_itens_venda_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_venda_itens
    ADD CONSTRAINT pdv_venda_itens_venda_id_fkey FOREIGN KEY (venda_id) REFERENCES public.pdv_vendas(id) ON DELETE CASCADE;


--
-- Name: pdv_vendas pdv_vendas_caixa_conferencia_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_caixa_conferencia_id_fkey FOREIGN KEY (caixa_conferencia_id) REFERENCES public.pdv_caixas(id);


--
-- Name: pdv_vendas pdv_vendas_caixa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_caixa_id_fkey FOREIGN KEY (caixa_id) REFERENCES public.pdv_caixas(id);


--
-- Name: pdv_vendas pdv_vendas_cancelado_por_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_cancelado_por_id_fkey FOREIGN KEY (cancelado_por_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: pdv_vendas pdv_vendas_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: pdv_vendas pdv_vendas_forma_pagamento_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_forma_pagamento_id_fkey FOREIGN KEY (forma_pagamento_id) REFERENCES public.formas_pagamento(id);


--
-- Name: pdv_vendas pdv_vendas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pdv_vendas
    ADD CONSTRAINT pdv_vendas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: pedido_itens pedido_itens_pedido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_itens
    ADD CONSTRAINT pedido_itens_pedido_id_fkey FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE CASCADE;


--
-- Name: pedido_itens pedido_itens_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_itens
    ADD CONSTRAINT pedido_itens_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE SET NULL;


--
-- Name: pedido_nfe pedido_nfe_pedido_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_nfe
    ADD CONSTRAINT pedido_nfe_pedido_id_fkey FOREIGN KEY (pedido_id) REFERENCES public.pedidos(id) ON DELETE CASCADE;


--
-- Name: pedidos pedidos_cliente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_cliente_id_fkey FOREIGN KEY (cliente_id) REFERENCES public.clientes(id) ON DELETE SET NULL;


--
-- Name: pedidos pedidos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos
    ADD CONSTRAINT pedidos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: permissoes_nivel permissoes_nivel_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_nivel
    ADD CONSTRAINT permissoes_nivel_modulo_id_fkey FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: permissoes_nivel permissoes_nivel_nivel_acesso_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_nivel
    ADD CONSTRAINT permissoes_nivel_nivel_acesso_id_fkey FOREIGN KEY (nivel_acesso_id) REFERENCES public.niveis_acesso(id) ON DELETE CASCADE;


--
-- Name: permissoes_personalizadas permissoes_personalizadas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_personalizadas
    ADD CONSTRAINT permissoes_personalizadas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: permissoes_usuario permissoes_usuario_modulo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_usuario
    ADD CONSTRAINT permissoes_usuario_modulo_id_fkey FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: permissoes_usuario permissoes_usuario_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissoes_usuario
    ADD CONSTRAINT permissoes_usuario_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: produto_estoque_movimentacoes produto_estoque_movimentacoes_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_estoque_movimentacoes
    ADD CONSTRAINT produto_estoque_movimentacoes_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE CASCADE;


--
-- Name: produto_estoque_movimentacoes produto_estoque_movimentacoes_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_estoque_movimentacoes
    ADD CONSTRAINT produto_estoque_movimentacoes_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: produto_fiscal produto_fiscal_produto_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_fiscal
    ADD CONSTRAINT produto_fiscal_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id) ON DELETE CASCADE;


--
-- Name: usuarios usuarios_nivel_acesso_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_nivel_acesso_id_fkey FOREIGN KEY (nivel_acesso_id) REFERENCES public.niveis_acesso(id);


--
-- PostgreSQL database dump complete
--

\unrestrict mkfFVUslMTV9U8wzgvHs9PzCTIoYV23DfLBPxabUbOh2xBbxOBBnSFY6CDgKVNM


-- ============================================================================
-- DADOS INICIAIS (SEEDS)
-- ============================================================================
SET search_path = public, pg_catalog;
--
-- PostgreSQL database dump
--

\restrict giuOXlsfFKLxKAHkeeyOZaSbhCrrWLQRHPQOzuiOF9tuBdRPf3uW4mIMxwN2SVi

-- Dumped from database version 13.22
-- Dumped by pg_dump version 13.22


--
-- Data for Name: categorias_dre; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.categorias_dre VALUES (1, 'Vendas de Produtos', 'Receita', 'Receita bruta com vendas de produtos', 1, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (2, 'Vendas de Servicos', 'Receita', 'Receita bruta com prestacao de servicos', 2, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (3, 'Receitas Financeiras', 'Receita', 'Juros e rendimentos financeiros', 3, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (4, 'Outras Receitas Operacionais', 'Receita', 'Outras receitas brutas operacionais', 4, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (5, 'ICMS sobre Vendas', 'Deducao', 'Imposto ICMS incidente sobre vendas', 10, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (6, 'IPI sobre Vendas', 'Deducao', 'Imposto IPI incidente sobre vendas', 11, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (7, 'PIS sobre Vendas', 'Deducao', 'PIS incidente sobre faturamento', 12, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (8, 'COFINS sobre Vendas', 'Deducao', 'COFINS incidente sobre faturamento', 13, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (9, 'ISS sobre Servicos', 'Deducao', 'ISS incidente sobre prestacao de servicos', 14, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (10, 'Devolucoes de Vendas', 'Deducao', 'Devolucoes de produtos vendidos', 15, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (11, 'Abatimentos Comerciais', 'Deducao', 'Abatimentos e descontos comerciais', 16, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (12, 'Descontos obtidos', 'Deducao', 'Descontos obtidos', 17, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (13, 'Custo de Mercadorias Vendidas - CMV', 'CPV', 'Custo das mercadorias vendidas no periodo', 20, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (14, 'Custo de Produtos Vendidos - CPV', 'CPV', 'Custo dos produtos fabricados e vendidos', 21, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (15, 'Materia-Prima', 'CPV', 'Custo com materia-prima para producao', 22, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (16, 'Embalagens', 'CPV', 'Custo com embalagens para produtos', 23, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (17, 'Frete de Compras', 'CPV', 'Frete e transporte de compras', 24, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (18, 'Compras de Mercadoria', 'CPV', 'Compras de mercadoria para revenda', 25, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (19, 'Salarios - Vendedores', 'Despesa Operacional', 'Salarios e comissoes da equipe de vendas', 30, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (20, 'Marketing e Publicidade', 'Despesa Operacional', 'Despesas com marketing e publicidade', 31, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (21, 'Propaganda e Promocao', 'Despesa Operacional', 'Despesas com propaganda e promocoes', 32, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (22, 'Comissoes sobre Vendas', 'Despesa Operacional', 'Comissoes pagas sobre vendas', 33, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (23, 'Salarios - Administracao', 'Despesa Operacional', 'Salarios da equipe administrativa', 40, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (24, 'Aluguel de Imoveis', 'Despesa Operacional', 'Aluguel de imoveis comerciais e industriais', 41, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (25, 'Agua e Esgoto', 'Despesa Operacional', 'Despesas com Agua e esgoto', 42, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (26, 'Energia Eletrica', 'Despesa Operacional', 'Despesas com energia eletrica', 43, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (27, 'Telefone e Internet', 'Despesa Operacional', 'Despesas com telefonia e internet', 44, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (28, 'Material de Escritorio', 'Despesa Operacional', 'Material de escritorio e suprimentos', 45, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (29, 'Servicos de Terceiros', 'Despesa Operacional', 'Servicos contratados de terceiros', 46, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (30, 'Honorarios Contabeis', 'Despesa Operacional', 'Honorarios de contador e advocacia', 47, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (31, 'Seguros', 'Despesa Operacional', 'Premios de seguros diversos', 48, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (32, 'Depreciacao de Ativos', 'Despesa Operacional', 'Depreciacao de moveis e utensilios', 49, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (33, 'Despesas de Viagem (Hotel)', 'Despesa Operacional', 'Despesas com hospedagem em viagens', 26, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (34, 'Despesas de Viagem (Cafe da Manha)', 'Despesa Operacional', 'Despesas com cafe da manha em viagens', 27, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (35, 'Despesas de Viagem (Abastecimentos)', 'Despesa Operacional', 'Despesas com abastecimento de veiculos em viagens', 28, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (36, 'Uso e Consumo', 'Despesa Operacional', 'Despesas com uso e consumo de materiais', 29, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (37, 'Juros Passivos', 'Despesa Financeira', 'Juros pagos sobre emprastimos e financiamentos', 50, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (38, 'Taxas BancÃ¡rias', 'Despesa Financeira', 'Taxas e tarifas BancÃ¡rias', 51, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (39, 'Variacoes Cambiais', 'Despesa Financeira', 'Perdas com variacao cambial', 52, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (40, 'Descontos Cedidos em Vendas', 'Despesa Operacional', 'Descontos concedidos em operacoes de venda', 37, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (41, 'Imposto de Renda - PJ', 'Tributo', 'Imposto de Renda Pessoa Juridica', 60, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (42, 'Contribuicao Social - CSLL', 'Tributo', 'Contribuicao Social sobre Lucro Liquido', 61, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (43, 'Provisoes', 'Outras', 'Provisoes diversas', 70, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.categorias_dre VALUES (44, 'Resultados Nao Operacionais', 'Outras', 'Resultados de transacoes nao operacionais', 71, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');


--
-- Data for Name: configuracoes; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.configuracoes VALUES (1, 'nome_sistema', 'Sistema de Chamados', 'Nome do sistema exibido no cabecalho', 'text', 'Geral', 1, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (2, 'logo_sistema', 'logo.png', 'Logo do sistema', 'image', 'Aparencia', 2, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (3, 'cor_primaria', '#4361ee', 'Cor primaria do sistema', 'color', 'Aparencia', 3, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (4, 'itens_por_pagina', '10', 'Numero de itens por pagina nas listagens', 'number', 'Sistema', 4, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (5, 'manutencao', 'false', 'Ativar modo manutencao', 'boolean', 'Sistema', 5, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (6, 'email_notificacao', 'suporte@empresa.com', 'E-mail para notificacoes', 'email', 'E-mail', 6, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (7, 'smtp_host', 'smtp.empresa.com', 'Servidor SMTP', 'text', 'E-mail', 7, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (8, 'smtp_porta', '587', 'Porta SMTP', 'number', 'E-mail', 8, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (9, 'smtp_usuario', 'usuario@empresa.com', 'Usuario SMTP', 'text', 'E-mail', 9, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (10, 'smtp_senha', '', 'Senha SMTP', 'password', 'E-mail', 10, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (11, 'endereco_empresa', 'Rua Exemplo, 123', 'Endereco da empresa', 'text', 'Empresa', 11, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.configuracoes VALUES (12, 'telefone_contato', '(11) 1234-5678', 'Telefone para contato', 'text', 'Empresa', 12, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');


--
-- Data for Name: formas_pagamento; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.formas_pagamento VALUES (1, 'Dinheiro', 'D', 'Pagamento em dinheiro', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.formas_pagamento VALUES (2, 'Cartão de Débito', 'CD', 'Cartão de Débito', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.614791-03');
INSERT INTO public.formas_pagamento VALUES (3, 'Cartão de Crédito', 'CC', 'Cartão de Crédito', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.617959-03');
INSERT INTO public.formas_pagamento VALUES (4, 'PIX', 'PIX', 'TransferÃªncia via PIX', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.618957-03');
INSERT INTO public.formas_pagamento VALUES (5, 'Boleto', 'BOL', 'Boleto bancario', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.618957-03');
INSERT INTO public.formas_pagamento VALUES (6, 'Transferência Bancaría', 'TB', 'Transferencia bancaria', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.618957-03');
INSERT INTO public.formas_pagamento VALUES (7, 'A faturar', 'AF', 'Pagamento a prazo', NULL, 0.0000, 0, NULL, true, '2026-04-17 21:18:30.954396-03', '2026-04-18 17:01:00.618957-03');


--
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.modulos VALUES (1, 'dashboard', 'Dashboard', 'fa-gauge', 1);
INSERT INTO public.modulos VALUES (2, 'clientes', 'Clientes', 'fa-users', 2);
INSERT INTO public.modulos VALUES (3, 'produtos', 'Produtos', 'fa-boxes', 3);
INSERT INTO public.modulos VALUES (6, 'financeiro', 'Financeiro', 'fa-chart-line', 6);
INSERT INTO public.modulos VALUES (7, 'rel_pedidos', 'Relatorios de Pedidos', 'fa-file-alt', 7);
INSERT INTO public.modulos VALUES (8, 'rel_financeiro', 'Relatorios Financeiros', 'fa-file-chart-line', 8);
INSERT INTO public.modulos VALUES (9, 'fiscal', 'Fiscal', 'fa-file-invoice-dollar', 9);
INSERT INTO public.modulos VALUES (10, 'servicos', 'Servicos', 'fa-tools', 8);
INSERT INTO public.modulos VALUES (28, 'pdv', 'PDV', 'fa-cash-register', 10);
INSERT INTO public.modulos VALUES (29, 'usuarios', 'Usuarios', 'fa-users-gear', 11);
INSERT INTO public.modulos VALUES (30, 'relatorios', 'Relatorios', 'fa-file-alt', 12);


--
-- Data for Name: niveis_acesso; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.niveis_acesso VALUES (1, 'Administrador', 'Acesso total ao sistema', '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.niveis_acesso VALUES (2, 'Suporte', 'Acesso as funcionalidades de suporte', '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.niveis_acesso VALUES (3, 'Financeiro', 'Acesso ao modulo financeiro', '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.niveis_acesso VALUES (4, 'Personalizado', 'Acesso configurado individualmente por modulo', '2026-04-17 21:18:30.954396-03', '2026-04-17 21:18:30.954396-03');
INSERT INTO public.niveis_acesso VALUES (5, 'Cliente', 'Acesso restrito ao proprio perfil e chamados', '2026-04-18 17:00:45.519468-03', '2026-04-18 17:00:45.519468-03');


--
-- Data for Name: permissoes_nivel; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.permissoes_nivel VALUES (1, 1);
INSERT INTO public.permissoes_nivel VALUES (1, 2);
INSERT INTO public.permissoes_nivel VALUES (1, 3);
INSERT INTO public.permissoes_nivel VALUES (1, 6);
INSERT INTO public.permissoes_nivel VALUES (1, 7);
INSERT INTO public.permissoes_nivel VALUES (1, 8);
INSERT INTO public.permissoes_nivel VALUES (2, 1);
INSERT INTO public.permissoes_nivel VALUES (2, 2);
INSERT INTO public.permissoes_nivel VALUES (2, 3);
INSERT INTO public.permissoes_nivel VALUES (2, 7);
INSERT INTO public.permissoes_nivel VALUES (3, 1);
INSERT INTO public.permissoes_nivel VALUES (3, 6);
INSERT INTO public.permissoes_nivel VALUES (3, 8);
INSERT INTO public.permissoes_nivel VALUES (1, 9);
INSERT INTO public.permissoes_nivel VALUES (1, 10);
INSERT INTO public.permissoes_nivel VALUES (1, 28);
INSERT INTO public.permissoes_nivel VALUES (1, 29);
INSERT INTO public.permissoes_nivel VALUES (1, 30);


--
-- Name: categorias_dre_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--



--
-- Name: configuracoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--



--
-- Name: formas_pagamento_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--



--
-- Name: modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--



--
-- Name: niveis_acesso_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--



--
-- PostgreSQL database dump complete
--

\unrestrict giuOXlsfFKLxKAHkeeyOZaSbhCrrWLQRHPQOzuiOF9tuBdRPf3uW4mIMxwN2SVi


-- ============================================================================
-- GRUPOS/SUBGRUPOS DE PRODUTO + CAMPOS FISCAIS ESTENDIDOS (auditoria 2026-04-18)
-- Blocos idempotentes para clientes existentes
-- ============================================================================

CREATE TABLE IF NOT EXISTS produto_grupos (
    id              SERIAL PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    descricao       TEXT,
    ativo           BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_produto_grupos_nome UNIQUE (nome)
);

CREATE TABLE IF NOT EXISTS produto_subgrupos (
    id              SERIAL PRIMARY KEY,
    grupo_id        INTEGER NOT NULL REFERENCES produto_grupos(id) ON DELETE RESTRICT,
    nome            VARCHAR(100) NOT NULL,
    descricao       TEXT,
    ativo           BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_produto_subgrupos_grupo_nome UNIQUE (grupo_id, nome)
);

CREATE INDEX IF NOT EXISTS idx_produto_subgrupos_grupo_id ON produto_subgrupos(grupo_id);

ALTER TABLE produtos
    ADD COLUMN IF NOT EXISTS grupo_id         INTEGER REFERENCES produto_grupos(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS subgrupo_id      INTEGER REFERENCES produto_subgrupos(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS ncm              VARCHAR(10),
    ADD COLUMN IF NOT EXISTS cest             VARCHAR(9),
    ADD COLUMN IF NOT EXISTS cfop             VARCHAR(5),
    ADD COLUMN IF NOT EXISTS origem_fiscal    SMALLINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_cst         VARCHAR(3),
    ADD COLUMN IF NOT EXISTS icms_aliquota    NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_base_calc   NUMERIC(5,2) DEFAULT 100,
    ADD COLUMN IF NOT EXISTS icms_st_aliquota NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS icms_st_mva      NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS pis_cst          VARCHAR(3),
    ADD COLUMN IF NOT EXISTS pis_aliquota     NUMERIC(5,4) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS cofins_cst       VARCHAR(3),
    ADD COLUMN IF NOT EXISTS cofins_aliquota  NUMERIC(5,4) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ipi_cst          VARCHAR(3),
    ADD COLUMN IF NOT EXISTS ipi_aliquota     NUMERIC(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS peso_bruto       NUMERIC(10,3) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS peso_liquido     NUMERIC(10,3) DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_produtos_grupo_id    ON produtos(grupo_id);
CREATE INDEX IF NOT EXISTS idx_produtos_subgrupo_id ON produtos(subgrupo_id);
CREATE INDEX IF NOT EXISTS idx_produtos_ncm         ON produtos(ncm) WHERE ncm IS NOT NULL;

CREATE OR REPLACE FUNCTION produto_grupos_atualizar_em()
RETURNS TRIGGER AS $$
BEGIN
    NEW.atualizado_em := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_produto_grupos_updated    ON produto_grupos;
DROP TRIGGER IF EXISTS trg_produto_subgrupos_updated ON produto_subgrupos;
CREATE TRIGGER trg_produto_grupos_updated
    BEFORE UPDATE ON produto_grupos
    FOR EACH ROW EXECUTE FUNCTION produto_grupos_atualizar_em();
CREATE TRIGGER trg_produto_subgrupos_updated
    BEFORE UPDATE ON produto_subgrupos
    FOR EACH ROW EXECUTE FUNCTION produto_grupos_atualizar_em();

-- Grupos e subgrupos NAO sao populados no template: cada cliente cadastra
-- os seus conforme necessidade (tabelas ficam vazias apos CREATE).

-- clientes.prazo_faturamento_dias (auditoria 2026-04-18)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='clientes' AND column_name='prazo_faturamento_dias') THEN
        ALTER TABLE clientes
            ADD COLUMN prazo_faturamento_dias INTEGER NOT NULL DEFAULT 0
                CONSTRAINT chk_clientes_prazo_faturamento CHECK (prazo_faturamento_dias >= 0);
    END IF;
END $$;

-- View fallback para tabela `servicos` (removida na Fase 1, mas queries legadas
-- do Dashboard ainda referenciam). Retorna zero linhas.
DROP VIEW IF EXISTS servicos;
CREATE VIEW servicos AS
SELECT NULL::uuid AS id, NULL::integer AS numero, NULL::integer AS cliente_id,
       NULL::varchar AS nome_cliente, NULL::varchar AS status,
       NULL::timestamptz AS data_servico, NULL::numeric AS valor_total,
       FALSE AS ativo, NULL::timestamptz AS updated_at,
       NULL::varchar AS servico_nome, NULL::varchar AS produto_nome,
       NULL::integer AS produto_id, NULL::numeric AS produto_quantidade,
       NULL::numeric AS produto_valor_unitario, NULL::numeric AS servico_valor,
       NULL::varchar AS desconto_tipo, NULL::numeric AS desconto_valor,
       NULL::timestamptz AS data_faturamento, NULL::integer AS orcamento_id
WHERE FALSE;

-- ============================================================================
-- MODULO FISCAL (NFC-e + NFS-e Nacional)  — migration 20260418_fiscal_module
-- ============================================================================

CREATE TABLE IF NOT EXISTS perfil_tributario (
    id                      SERIAL PRIMARY KEY,
    empresa_local_id        INTEGER NOT NULL DEFAULT 1,
    regime_tributario       VARCHAR(20) NOT NULL DEFAULT 'simples_nacional'
                            CHECK (regime_tributario IN ('simples_nacional','lucro_presumido','lucro_real')),
    csc_id_homologacao      VARCHAR(10),
    csc_token_homologacao   VARCHAR(64),
    csc_id_producao         VARCHAR(10),
    csc_token_producao      VARCHAR(64),
    serie_nfce              SMALLINT DEFAULT 1,
    numero_nfce_atual       INTEGER DEFAULT 0,
    ambiente_nfce           SMALLINT DEFAULT 2 CHECK (ambiente_nfce IN (1,2)),
    nfse_modo_auth          VARCHAR(20) DEFAULT 'usuario_senha'
                            CHECK (nfse_modo_auth IN ('usuario_senha','certificado','govbr')),
    nfse_usuario            VARCHAR(100),
    nfse_senha_cifrada      TEXT,
    nfse_token              TEXT,
    nfse_token_expira_em    TIMESTAMP,
    nfse_certificado_path   VARCHAR(255),
    nfse_certificado_senha_cifrada TEXT,
    serie_rps               VARCHAR(5)  DEFAULT 'RPS',
    numero_rps_atual        INTEGER     DEFAULT 0,
    ambiente_nfse           VARCHAR(15) DEFAULT 'homologacao'
                            CHECK (ambiente_nfse IN ('homologacao','producao')),
    ativo                   BOOLEAN DEFAULT TRUE,
    created_at              TIMESTAMPTZ DEFAULT NOW(),
    updated_at              TIMESTAMPTZ DEFAULT NOW(),
    CONSTRAINT uq_perfil_empresa UNIQUE (empresa_local_id)
);

COMMENT ON TABLE perfil_tributario IS
    'Perfil tributario 1:1 com empresa_local. Senhas cifradas com chave do .env (FISCAL_ENCRYPTION_KEY).';

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

CREATE OR REPLACE FUNCTION fiscal_touch_updated_at() RETURNS trigger AS $fiscal_touch$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$fiscal_touch$ LANGUAGE plpgsql;

DO $fiscal_triggers$
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
END $fiscal_triggers$;

-- Seed: perfil_tributario singleton
INSERT INTO perfil_tributario (empresa_local_id, regime_tributario)
SELECT 1, 'simples_nacional'
WHERE NOT EXISTS (SELECT 1 FROM perfil_tributario WHERE empresa_local_id = 1);

-- Seed: tributacao_por_estado com todas as 27 UFs (aliquota inter default)
DO $fiscal_seed$
DECLARE
    perfilId   INTEGER;
    ufEmit     CHAR(2);
    aliqInter  NUMERIC(5,2);
    rec        RECORD;
BEGIN
    SELECT id INTO perfilId FROM perfil_tributario WHERE empresa_local_id = 1 LIMIT 1;
    SELECT UPPER(COALESCE(uf, 'SP'))::CHAR(2) INTO ufEmit FROM empresa_local WHERE id = 1;
    IF perfilId IS NULL THEN RETURN; END IF;
    IF ufEmit IS NULL OR ufEmit = '' THEN ufEmit := 'SP'; END IF;
    FOR rec IN
        SELECT uf FROM (VALUES
            ('AC'),('AL'),('AM'),('AP'),('BA'),('CE'),('DF'),('ES'),('GO'),('MA'),
            ('MG'),('MS'),('MT'),('PA'),('PB'),('PE'),('PI'),('PR'),('RJ'),('RN'),
            ('RO'),('RR'),('RS'),('SC'),('SE'),('SP'),('TO')
        ) AS t(uf)
    LOOP
        aliqInter := 12.0;
        IF ufEmit IN ('SP','RJ','MG','RS','SC','PR','ES') AND
           rec.uf NOT IN ('SP','RJ','MG','RS','SC','PR','ES') THEN
            aliqInter := 7.0;
        END IF;
        IF ufEmit = rec.uf THEN aliqInter := 0; END IF;
        INSERT INTO tributacao_por_estado
            (perfil_id, uf_origem, uf_destino, icms_aliquota_inter)
        VALUES (perfilId, ufEmit, rec.uf, aliqInter)
        ON CONFLICT (perfil_id, uf_origem, uf_destino) DO NOTHING;
    END LOOP;
END $fiscal_seed$;

-- ============================================================================
-- DRE POR REGIME DE COMPETENCIA (2026-04-19) — seeds e normalizacoes
-- Receita reconhecida na venda, taxas cartao como Despesa Financeira,
-- recebimentos NAO afetam DRE (afeta_dre=FALSE).
-- ============================================================================

-- Re-tipagem: "Descontos Cedidos" eh Deducao, nao Despesa Operacional
UPDATE categorias_dre
   SET tipo = 'Deducao'
 WHERE UPPER(nome) LIKE '%DESCONTO%CEDIDO%'
    OR UPPER(nome) LIKE '%DESCONTO%CONCEDIDO%';

-- Seed de codigos contabeis (ignora categorias ja codificadas)
UPDATE categorias_dre SET codigo = '01' WHERE codigo IS NULL AND LOWER(nome) = 'vendas de produtos';
UPDATE categorias_dre SET codigo = '02' WHERE codigo IS NULL AND LOWER(nome) = 'vendas de servicos';
UPDATE categorias_dre SET codigo = '03' WHERE codigo IS NULL AND LOWER(nome) = 'receitas financeiras';
UPDATE categorias_dre SET codigo = '04' WHERE codigo IS NULL AND LOWER(nome) = 'outras receitas operacionais';
UPDATE categorias_dre SET codigo = '05' WHERE codigo IS NULL AND LOWER(nome) = 'icms sobre vendas';
UPDATE categorias_dre SET codigo = '06' WHERE codigo IS NULL AND LOWER(nome) = 'ipi sobre vendas';
UPDATE categorias_dre SET codigo = '07' WHERE codigo IS NULL AND LOWER(nome) = 'pis sobre vendas';
UPDATE categorias_dre SET codigo = '08' WHERE codigo IS NULL AND LOWER(nome) = 'cofins sobre vendas';
UPDATE categorias_dre SET codigo = '09' WHERE codigo IS NULL AND LOWER(nome) = 'iss sobre servicos';
UPDATE categorias_dre SET codigo = '10' WHERE codigo IS NULL AND LOWER(nome) = 'devolucoes de vendas';
UPDATE categorias_dre SET codigo = '11' WHERE codigo IS NULL AND LOWER(nome) = 'abatimentos comerciais';
UPDATE categorias_dre SET codigo = '13' WHERE codigo IS NULL AND LOWER(nome) = 'custo de mercadorias vendidas - cmv';
UPDATE categorias_dre SET codigo = '14' WHERE codigo IS NULL AND LOWER(nome) = 'custo de produtos vendidos - cpv';
UPDATE categorias_dre SET codigo = '41' WHERE codigo IS NULL AND (UPPER(nome) LIKE '%DESCONTO%CEDIDO%' OR UPPER(nome) LIKE '%DESCONTO%CONCEDIDO%');
UPDATE categorias_dre SET codigo = '51' WHERE codigo IS NULL AND (LOWER(nome) LIKE 'taxas bancarias%' OR LOWER(nome) LIKE 'taxas banc%');
UPDATE categorias_dre SET codigo = '52' WHERE codigo IS NULL AND LOWER(nome) = 'juros passivos';
UPDATE categorias_dre SET codigo = '53' WHERE codigo IS NULL AND LOWER(nome) = 'variacoes cambiais';
UPDATE categorias_dre SET codigo = '90' WHERE codigo IS NULL AND LOWER(nome) = 'imposto de renda - pj';
UPDATE categorias_dre SET codigo = '91' WHERE codigo IS NULL AND LOWER(nome) = 'contribuicao social - csll';

-- Sincroniza sequences apos INSERTs posicionais (cliente-base usa IDs explicitos)
SELECT setval(pg_get_serial_sequence('categorias_dre','id'),
              COALESCE((SELECT MAX(id) FROM categorias_dre), 1));

-- Garante existencia das categorias contabeis chave
INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Vendas de Produtos', 'Receita', '01', 'Receita bruta reconhecida na venda de produtos (competencia)', 1, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) = 'vendas de produtos');

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Vendas de Servicos', 'Receita', '02', 'Receita bruta reconhecida na venda de servicos (competencia)', 2, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) = 'vendas de servicos');

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Descontos Concedidos', 'Deducao', '41', 'Descontos concedidos ao cliente no ato da venda', 41, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM categorias_dre
     WHERE codigo = '41'
        OR UPPER(nome) LIKE '%DESCONTO%CEDIDO%'
        OR UPPER(nome) LIKE '%DESCONTO%CONCEDIDO%'
);

INSERT INTO categorias_dre (nome, tipo, codigo, descricao, ordem, ativo)
SELECT 'Taxas Bancarias', 'Despesa Financeira', '51', 'Taxas cobradas por operadoras de cartao e bancos', 51, TRUE
WHERE NOT EXISTS (SELECT 1 FROM categorias_dre WHERE LOWER(nome) LIKE 'taxas bancarias%' OR LOWER(nome) LIKE 'taxas banc%');

-- Indices parciais para o DRE competencia
CREATE INDEX IF NOT EXISTS idx_mov_dre_periodo
    ON public.movimentacoes (data_movimentacao)
 WHERE afeta_dre = TRUE;
CREATE INDEX IF NOT EXISTS idx_mov_categoria_periodo
    ON public.movimentacoes (categoria_dre_id, data_movimentacao)
 WHERE afeta_dre = TRUE;

-- ============================================================================
-- REGISTRY DE MIGRATIONS APLICADAS (referencial para futuros upgrades)
-- ============================================================================
INSERT INTO schema_migrations (version, checksum, executed_at) VALUES
    ('20260418_audit_remove_orcamento_servicos_fiscalservico', MD5('20260418_audit'), NOW()),
    ('20260418_auditoria_final',                               MD5('20260418_auditoria_final'), NOW()),
    ('20260418_clientes_prazo_faturamento',                    MD5('20260418_clientes_prazo_faturamento'), NOW()),
    ('20260418_financeiro_protegido',                          MD5('20260418_financeiro_protegido'), NOW()),
    ('20260418_fiscal_module',                                 MD5('20260418_fiscal_module'), NOW()),
    ('20260418_pdv_conferencia',                               MD5('20260418_pdv_conferencia'), NOW()),
    ('20260418_pdv_fluxo_vendas',                              MD5('20260418_pdv_fluxo_vendas'), NOW()),
    ('20260418_pdv_vendas_kanban',                             MD5('20260418_pdv_vendas_kanban'), NOW()),
    ('20260418_pdv_vendas_module',                             MD5('20260418_pdv_vendas_module'), NOW()),
    ('20260418_produto_grupos_fiscal',                         MD5('20260418_produto_grupos_fiscal'), NOW()),
    ('20260418_view_servicos_fallback',                        MD5('20260418_view_servicos_fallback'), NOW()),
    ('20260418_vw_dre',                                        MD5('20260418_vw_dre'), NOW()),
    ('20260419_dre_competencia',                               MD5('20260419_dre_competencia'), NOW())
ON CONFLICT (version) DO NOTHING;

-- ============================================================================
-- USUARIO ADMIN PADRAO (senha: admin123)
-- ============================================================================
INSERT INTO usuarios (nome, email, senha, nivel_acesso_id, ativo)
VALUES ('Administrador', 'admin@suporte.com',
        '$2y$10$wB0zrwdGRYvik1hTLMMVcuimbgaJpT7g.3CPBm8MmAL/LIvsdriOy', 1, TRUE)
ON CONFLICT (email) DO NOTHING;

-- ============================================================================
-- MENSAGEM FINAL
-- ============================================================================
DO $dm_final$
BEGIN
    RAISE NOTICE '================================================================';
    RAISE NOTICE 'Banco de dados do Sistema DM criado com sucesso.';
    RAISE NOTICE 'Usuario admin: admin@suporte.com  /  Senha: admin123';
    RAISE NOTICE '================================================================';
END $dm_final$;
