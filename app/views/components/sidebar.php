<?php
/**
 * Componente: Sidebar de navegacao
 * Incluido por public/includes/header.php
 * Variaveis disponiveis: $logoPath, $menuHoverColor (do header.php pai)
 */
$can = static fn(string $slug): bool => \App\Support\PermissionGate::can($slug);
$url = static fn(string $path = ''): string => tenantUrl($path);
$canRelPedidos = $can('rel_pedidos');
$canRelFinanceiro = $can('rel_financeiro');
$canRelProdutos = $can('produtos');
$showRelatorios = $canRelPedidos || $canRelFinanceiro || $canRelProdutos;
$isAdmin = ((int)($_SESSION['user_role'] ?? 0) === 1);
$canOperarPdv = $pdvPermissao?->usuarioAtualPodeOperarPdv() ?? false;
$canConferirCaixa = $pdvPermissao?->usuarioAtualPodeConferirCaixas() ?? false;
$cleanUrl = static fn(string $path = ''): string => function_exists('tenantCleanUrl') ? tenantCleanUrl($path) : tenantUrl($path);
?>
<aside id="sidebar">

    <div class="sidebar-header">
        <a href="<?php echo htmlspecialchars($url()); ?>" class="sidebar-logo" title="Inicio">
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="logo-img">
        </a>
        <button id="sidebarCollapse" class="sidebar-toggle d-none d-md-flex" title="Recolher menu" aria-label="Recolher menu">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-menu">

            <?php if ($can('dashboard')): ?>
            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($url('admin/dashboard.php')); ?>" class="menu-link" title="Dashboard">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <li class="menu-item has-submenu">
                <a href="#usuariosSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Usuarios">
                    <i class="fas fa-users menu-icon"></i>
                    <span class="menu-text">Usuarios</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="usuariosSubmenu">
                    <li><a href="<?php echo htmlspecialchars($url('admin/usuarios.php?action=novo')); ?>" class="submenu-link"><i class="fas fa-plus"></i> Novo Usuario</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/usuarios.php')); ?>" class="submenu-link"><i class="fas fa-list"></i> Lista de Usuarios</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($can('clientes') || $can('produtos') || $can('servicos')): ?>
            <li class="menu-item has-submenu">
                <a href="#cadastroSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Cadastro">
                    <i class="fas fa-folder-open menu-icon"></i>
                    <span class="menu-text">Cadastro</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="cadastroSubmenu">
                    <?php if ($can('clientes')): ?>
                    <li class="submenu-group-label"><i class="fas fa-address-book me-1"></i> Clientes</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/clientes.php?action=novo')); ?>" class="submenu-link"><i class="fas fa-plus"></i> Novo Cliente</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/clientes.php')); ?>" class="submenu-link"><i class="fas fa-list"></i> Listar Clientes</a></li>
                    <?php endif; ?>

                    <?php if ($can('produtos')): ?>
                    <li class="submenu-group-label"><i class="fas fa-box me-1"></i> Produtos</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/produtos.php?action=novo')); ?>" class="submenu-link"><i class="fas fa-plus"></i> Novo Produto</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/produtos.php')); ?>" class="submenu-link"><i class="fas fa-list"></i> Listar Produtos</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/produto-grupos.php')); ?>" class="submenu-link"><i class="fas fa-folder"></i> Grupos de Produtos</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/produto-subgrupos.php')); ?>" class="submenu-link"><i class="fas fa-folder-tree"></i> Subgrupos de Produtos</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/produtos.php?action=inventario')); ?>" class="submenu-link"><i class="fas fa-boxes"></i> Inventário de Estoque</a></li>
                    <?php endif; ?>

                    <?php if ($can('servicos')): ?>
                    <li class="submenu-group-label"><i class="fas fa-tools me-1"></i> Serviços</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/servicos.php?action=novo')); ?>" class="submenu-link"><i class="fas fa-plus"></i> Novo Serviço</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/servicos.php')); ?>" class="submenu-link"><i class="fas fa-list"></i> Listar Serviços</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($canOperarPdv): ?>
            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($cleanUrl('pdv')); ?>" class="menu-link" title="PDV">
                    <i class="fas fa-cash-register menu-icon"></i>
                    <span class="menu-text">PDV</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($canConferirCaixa): ?>
            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($cleanUrl('gerencial/caixas')); ?>" class="menu-link" title="Conferência de Caixas">
                    <i class="fas fa-scale-balanced menu-icon"></i>
                    <span class="menu-text">Conferência de Caixas</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if ($can('fiscal')): ?>
            <li class="menu-item has-submenu">
                <a href="#fiscalSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Fiscal">
                    <i class="fas fa-file-invoice-dollar menu-icon"></i>
                    <span class="menu-text">Fiscal</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="fiscalSubmenu">
                    <li><a href="<?php echo htmlspecialchars($url('admin/fiscal/emitir.php')); ?>" class="submenu-link"><i class="fas fa-file-invoice-dollar"></i> Vendas sem Fiscal</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/fiscal.php?action=listar')); ?>" class="submenu-link"><i class="fas fa-list"></i> NF-e (pedidos legado)</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/fiscal.php?action=homologacao')); ?>" class="submenu-link"><i class="fas fa-vial"></i> Homologação</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($can('financeiro')): ?>
            <li class="menu-item has-submenu">
                <a href="#financeiroSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Financeiro">
                    <i class="fas fa-dollar-sign menu-icon"></i>
                    <span class="menu-text">Financeiro</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="financeiroSubmenu">
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/dashboard.php')); ?>" class="submenu-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/contas-receber.php')); ?>" class="submenu-link"><i class="fas fa-arrow-circle-down"></i> Contas a Receber</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/contas-pagar.php')); ?>" class="submenu-link"><i class="fas fa-arrow-circle-up"></i> Contas a Pagar</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/movimentacoes.php')); ?>" class="submenu-link"><i class="fas fa-exchange-alt"></i> Movimentacoes</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/dre.php')); ?>" class="submenu-link"><i class="fas fa-chart-line"></i> DRE</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/fluxo-caixa-projetado.php')); ?>" class="submenu-link"><i class="fas fa-chart-area"></i> Fluxo de Caixa Projetado</a></li>
                    <li class="submenu-group-label">Configuracoes</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/formas-pagamento.php')); ?>" class="submenu-link"><i class="fas fa-credit-card"></i> Formas de Pagamento</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/contas.php')); ?>" class="submenu-link"><i class="fas fa-university"></i> Contas</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/financeiro/categorias-dre.php')); ?>" class="submenu-link"><i class="fas fa-tags"></i> Categorias DRE</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($showRelatorios): ?>
            <li class="menu-item has-submenu">
                <a href="#relatoriosSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Relatorios">
                    <i class="fas fa-chart-bar menu-icon"></i>
                    <span class="menu-text">Relatorios</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="relatoriosSubmenu">
                    <?php if ($canRelFinanceiro): ?>
                    <li class="submenu-group-label">Financeiros</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_despesas_receitas.php')); ?>" class="submenu-link"><i class="fas fa-balance-scale"></i> Despesas vs Receitas</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_movimentacoes_usuario.php')); ?>" class="submenu-link"><i class="fas fa-user-check"></i> Mov. por Usuario</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_historico_saldo.php')); ?>" class="submenu-link"><i class="fas fa-history"></i> Historico de Saldo</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_contas_receber_status.php')); ?>" class="submenu-link"><i class="fas fa-file-invoice-dollar"></i> CR por Status</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_contas_pagar_status.php')); ?>" class="submenu-link"><i class="fas fa-file-invoice"></i> CP por Status</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_vendas_pdv.php')); ?>" class="submenu-link"><i class="fas fa-cash-register"></i> Vendas PDV</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/financeiros/relatorio_vendas_pdv_por_item.php')); ?>" class="submenu-link"><i class="fas fa-ranking-star"></i> Vendas PDV por Item</a></li>
                    <?php endif; ?>

                    <?php if ($canRelPedidos): ?>
                    <?php if ($isAdmin): ?>
                    <li class="submenu-group-label">Usuarios</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/usuarios/atividade.php')); ?>" class="submenu-link"><i class="fas fa-user-clock"></i> Auditoria</a></li>
                    <?php endif; ?>
                    <li class="submenu-group-label">Pedidos</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/pedidos/relatorio_pedidos.php')); ?>" class="submenu-link"><i class="fas fa-clipboard-list"></i> Relatorio de Pedidos</a></li>
                    <?php endif; ?>

                    <?php if ($canRelProdutos): ?>
                    <li class="submenu-group-label">Produto</li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/produto/relatorio_produtos.php')); ?>" class="submenu-link"><i class="fas fa-box-open"></i> Inventario de Estoque</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/produto/relatorio_entradas.php')); ?>" class="submenu-link"><i class="fas fa-arrow-down"></i> Relatorio de Entradas</a></li>
                    <li><a href="<?php echo htmlspecialchars($url('admin/relatorios/produto/relatorio_saidas.php')); ?>" class="submenu-link"><i class="fas fa-arrow-up"></i> Relatorio de Saidas</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <li class="menu-item has-submenu">
                <a href="#configuracoesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="menu-link submenu-toggle" title="Configuracoes">
                    <i class="fas fa-cog menu-icon"></i>
                    <span class="menu-text">Configuracoes</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu collapse" id="configuracoesSubmenu">
                    <li><a href="<?php echo htmlspecialchars($url('admin/dados-empresa.php')); ?>" class="submenu-link"><i class="fas fa-building"></i> Dados da Empresa</a></li>
                    <?php if ($can('fiscal')): ?>
                    <li><a href="<?php echo htmlspecialchars($url('admin/fiscal/perfil.php')); ?>" class="submenu-link"><i class="fas fa-sliders-h"></i> Perfil Tributário</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo htmlspecialchars($url('admin/settings.php')); ?>" class="submenu-link"><i class="fas fa-cogs"></i> Sistema</a></li>
                </ul>
            </li>
            <?php endif; ?>

        </ul>
    </nav>

    <div class="sidebar-footer">
        <button class="theme-toggle" id="themeToggle" aria-label="Alternar tema" title="Alternar tema">
            <i class="fas fa-sun theme-icon sun"></i>
            <i class="fas fa-moon theme-icon moon"></i>
            <span class="menu-text theme-text">Alternar tema</span>
        </button>
    </div>

</aside>


