<?php
declare(strict_types=1);

/**
 * Configuração do painel master de licenças.
 * IMPORTANTE: troque os valores padrão antes de produção.
 */

if (!defined('MASTER_DB_HOST')) {
    define('MASTER_DB_HOST', getenv('MASTER_DB_HOST') ?: '127.0.0.1');
}

if (!defined('MASTER_DB_PORT')) {
    define('MASTER_DB_PORT', getenv('MASTER_DB_PORT') ?: '5432');
}

if (!defined('MASTER_DB_NAME')) {
    define('MASTER_DB_NAME', getenv('MASTER_DB_NAME') ?: 'licencas_master');
}

if (!defined('MASTER_DB_USER')) {
    define('MASTER_DB_USER', getenv('MASTER_DB_USER') ?: 'postgres');
}

if (!defined('MASTER_DB_PASS')) {
    define('MASTER_DB_PASS', getenv('MASTER_DB_PASS') ?: 'masterkey');
}

if (!defined('MASTER_DB_ADMIN_USER')) {
    define('MASTER_DB_ADMIN_USER', getenv('MASTER_DB_ADMIN_USER') ?: MASTER_DB_USER);
}

if (!defined('MASTER_DB_ADMIN_PASS')) {
    define('MASTER_DB_ADMIN_PASS', getenv('MASTER_DB_ADMIN_PASS') ?: MASTER_DB_PASS);
}

if (!defined('MASTER_DB_ADMIN_DB')) {
    define('MASTER_DB_ADMIN_DB', getenv('MASTER_DB_ADMIN_DB') ?: 'postgres');
}

if (!defined('SECRET_KEY')) {
    define('SECRET_KEY', getenv('LICENSE_SECRET_KEY') ?: 'TROQUE_ESTA_SECRET_KEY_IMEDIATAMENTE');
}

if (!defined('ADMIN_SESSION_NAME')) {
    define('ADMIN_SESSION_NAME', 'license_admin_session');
}

if (!defined('ADMIN_SESSION_TTL')) {
    define('ADMIN_SESSION_TTL', 60 * 60 * 8); // 8 horas
}

if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', getenv('LICENSE_MAIL_FROM') ?: 'no-reply@license-system.local');
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', getenv('LICENSE_MAIL_FROM_NAME') ?: 'License System');
}

if (!defined('CLIENT_LOGIN_BASE_URL')) {
    define('CLIENT_LOGIN_BASE_URL', getenv('CLIENT_LOGIN_BASE_URL') ?: '');
}

if (!defined('SYSTEM_ROOT_PATH')) {
    define('SYSTEM_ROOT_PATH', dirname(__DIR__, 2));
}

if (!defined('SQL_FINAL_PATH')) {
    define('SQL_FINAL_PATH', SYSTEM_ROOT_PATH . '/database/cliente-base.sql');
}

date_default_timezone_set('America/Sao_Paulo');
