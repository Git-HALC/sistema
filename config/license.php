<?php

/**
 * CONFIGURACAO DE LICENCA
 * Preencher apos instalar via painel ADM.
 * Voce pode sobrescrever essas constantes por variavel de ambiente.
 */

if (!defined('LICENSE_API_URL')) {
    define('LICENSE_API_URL', getenv('LICENSE_API_URL') ?: 'http://localhost/license-system/api/validar-licenca.php');
}

if (!defined('LICENSE_CHAVE')) {
    define('LICENSE_CHAVE', getenv('LICENSE_CHAVE') ?: 'chave_gerada_no_painel_adm');
}

if (!defined('LICENSE_CONTATO_EMAIL')) {
    define('LICENSE_CONTATO_EMAIL', getenv('LICENSE_CONTATO_EMAIL') ?: 'financeiro@seu-dominio.com');
}

if (!defined('LICENSE_CONTATO_TELEFONE')) {
    define('LICENSE_CONTATO_TELEFONE', getenv('LICENSE_CONTATO_TELEFONE') ?: '(00) 00000-0000');
}

if (!defined('SUPORTE_WHATSAPP')) {
    define('SUPORTE_WHATSAPP', getenv('SUPORTE_WHATSAPP') ?: '');
}
