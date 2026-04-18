<?php
/**
 * Componente: Top Header Bar
 * Barra superior fixa com título da página, toggle mobile e dropdown do usuário.
 * Incluído por public/includes/header.php
 */
$_pageTitle = $page_title ?? $titulo ?? 'Sistema DM';
?>
<header class="top-header" id="topHeader">

    <div class="header-left">
        <!-- Toggle mobile -->
        <button class="header-menu-btn d-md-none" id="sidebarCollapseMobile" aria-label="Abrir menu">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Título da página -->
        <h1 class="page-title"><?php echo htmlspecialchars($_pageTitle); ?></h1>
    </div>

    <div class="header-right">
        <div class="dropdown">
            <button class="btn-header-user dropdown-toggle" type="button" id="userDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    title="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                <i class="fas fa-user-circle"></i>
                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-user me-2 text-secondary"></i> Meu Perfil
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(tenantUrl('logout.php')); ?>">
                        <i class="fas fa-sign-out-alt me-2"></i> Sair
                    </a>
                </li>
            </ul>
        </div>
    </div>

</header>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
