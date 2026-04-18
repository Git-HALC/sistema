<?php
/**
 * Layout Base — sistema_dm
 *
 * Este arquivo documenta a estrutura HTML gerada pela combinação de:
 *   public/includes/header.php  →  abre <html>, <head>, layout, sidebar, top-header
 *   public/includes/footer.php  →  fecha layout, footer, scripts, </html>
 *
 * Para usar como layout direto em novas views (sem os includes legados):
 *
 *   <?php
 *   $page_title = 'Minha Página';
 *   $logoPath   = '/sistema_dm/public/assets/img/logo.png';
 *   require_once __DIR__ . '/layout.php';
 *   ?>
 *   <!-- conteúdo da página aqui -->
 *   <?php require_once __DIR__ . '/../../../public/includes/footer.php'; ?>
 *
 * Estrutura gerada:
 *
 * <html data-theme="light|dark">
 * <head>
 *   Bootstrap 5.3 · Font Awesome 6 · app.css · chat.css (se role 1|2)
 *   jQuery 3.6 · Bootstrap JS · Bootstrap Datepicker · SweetAlert2
 * </head>
 * <body>
 *   <div class="app-layout">
 *     <aside id="sidebar">          ← sidebar.php
 *       .sidebar-header
 *       .sidebar-nav
 *       .sidebar-footer (user + theme toggle)
 *     </aside>
 *
 *     <div class="main-wrapper">
 *       <header class="top-header"> ← header.php (component)
 *         .header-left (title)
 *         .header-right (user + logout)
 *       </header>
 *
 *       <div class="page-body">
 *         <div class="container-fluid">
 *           <!-- CONTEÚDO DA VIEW AQUI -->
 *         </div>
 *       </div><!-- .page-body -->
 *     </div><!-- .main-wrapper -->
 *   </div><!-- .app-layout -->
 *
 *   <footer class="app-footer">...</footer>
 *   scripts...
 * </body>
 * </html>
 */

// Alias para uso direto como layout
$logoPath      = $logoPath ?? '/sistema_dm/public/assets/img/logo.png';
$menuHoverColor = $menuHoverColor ?? null;

include __DIR__ . '/../../../public/includes/header.php';
