<?php
use App\Support\LicenseGate;
use App\Support\PermissionGate;
use App\Security\PdvPermissao;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$skipHeaderAuthRedirect = (bool)($GLOBALS['__dm_skip_header_auth_redirect'] ?? false);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/license.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$pdoForGuards = null;
if (isset($db) && $db instanceof PDO) {
    $pdoForGuards = $db;
} elseif (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
    $pdoForGuards = $GLOBALS['db'];
} elseif (class_exists('Database')) {
    $pdoForGuards = Database::getInstance()->getConnection();
}

// Só executa se usuário estiver logado
if (isset($_SESSION['user_id']) && $pdoForGuards instanceof PDO) {
    PermissionGate::init($pdoForGuards);
    LicenseGate::check($pdoForGuards);
}

$pdvPermissao = $pdoForGuards instanceof PDO ? new PdvPermissao($pdoForGuards) : null;
$layoutMode = (string)($GLOBALS['__dm_layout_mode'] ?? 'admin');

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR.utf8', 'portuguese');

if (!isset($_SESSION['user_id']) && !$skipHeaderAuthRedirect) {
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

$flashMessage = $_SESSION['mensagem'] ?? null;
if (isset($_SESSION['mensagem'])) {
    unset($_SESSION['mensagem']);
}

// Configurações do site (logo, hover color etc.)
$site_settings  = [];
$settingsPath   = __DIR__ . '/../../config/site_settings.php';
if (file_exists($settingsPath)) {
    $s = include $settingsPath;
    if (is_array($s)) $site_settings = $s;
}
$logoPath       = tenantPath((string)($site_settings['logo'] ?? tenantUrl('assets/img/logo.png')));
$menuHoverColor = $site_settings['menu_hover_color'] ?? null;
$appCssVersion = @filemtime(__DIR__ . '/../assets/css/app.css') ?: time();
$chatCssVersion = @filemtime(__DIR__ . '/../assets/css/chat.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? $titulo ?? 'Sistema DM'); ?></title>

    <!-- Evitar FOUC: aplica tema antes de renderizar -->
    <script>
        (function(){
            var t = localStorage.getItem('theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <!-- Design System -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tenantUrl('assets/css/app.css')); ?>?v=<?php echo $appCssVersion; ?>">
    <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], [1, 2], true)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(tenantUrl('assets/css/chat.css')); ?>?v=<?php echo $chatCssVersion; ?>">
    <?php endif; ?>

    <!-- jQuery (antes do Bootstrap JS) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Datepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.pt-BR.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (!empty($menuHoverColor)): ?>
    <style>
        #sidebar .menu-link:hover,
        #sidebar .submenu-link:hover {
            background-color: <?php echo htmlspecialchars($menuHoverColor); ?> !important;
        }
    </style>
    <?php endif; ?>

    <?php if (!empty($flashMessage) && is_array($flashMessage)): ?>
    <script>
        window.__flashMessage = {
            tipo: <?php echo json_encode((string)($flashMessage['tipo'] ?? 'info'), JSON_UNESCAPED_UNICODE); ?>,
            texto: <?php echo json_encode((string)($flashMessage['texto'] ?? ''), JSON_UNESCAPED_UNICODE); ?>
        };
    </script>
    <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($layoutMode === 'pdv' ? 'layout-pdv' : 'layout-admin'); ?>">

<div class="app-layout">

    <?php
    $sidebarPath = $layoutMode === 'pdv'
        ? __DIR__ . '/../../app/views/components/sidebar_pdv.php'
        : __DIR__ . '/../../app/views/components/sidebar.php';
    include $sidebarPath;
    ?>

    <div class="main-wrapper" id="mainWrapper">

        <?php include __DIR__ . '/../../app/views/components/header.php'; ?>
        <?php
        if (isset($_SESSION['license_warning'])) {
            require_once __DIR__ . '/../../app/views/components/license_warning.php';
        }
        ?>

        <div class="page-body">
            <div class="container-fluid">
                <!-- Datepicker PT-BR init -->
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof $.fn.datepicker !== 'undefined') {
                        if (!$.fn.datepicker.dates['pt-BR']) {
                            $.fn.datepicker.dates['pt-BR'] = {
                                days: ["Domingo","Segunda","Terça","Quarta","Quinta","Sexta","Sábado"],
                                daysShort: ["Dom","Seg","Ter","Qua","Qui","Sex","Sáb"],
                                daysMin: ["Do","Se","Te","Qu","Qu","Se","Sa"],
                                months: ["Janeiro","Fevereiro","Março","Abril","Maio","Junho","Julho","Agosto","Setembro","Outubro","Novembro","Dezembro"],
                                monthsShort: ["Jan","Fev","Mar","Abr","Mai","Jun","Jul","Ago","Set","Out","Nov","Dez"],
                                today: "Hoje", clear: "Limpar",
                                format: "dd/mm/yyyy", titleFormat: "MM yyyy", weekStart: 0
                            };
                        }
                        $('[data-datepicker="1"], .datepicker').datepicker({
                            language: 'pt-BR', format: 'dd/mm/yyyy',
                            autoclose: true, todayHighlight: true, orientation: 'bottom auto'
                        });
                    }
                });
                </script>


