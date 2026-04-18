<?php
/**
 * Sidebar exclusivo do PDV.
 */
$active = (string) ($GLOBALS['__dm_pdv_active'] ?? 'abertura');
$caixaResumo = $GLOBALS['__dm_pdv_caixa_resumo'] ?? null;
$multiplosCaixas = (bool) ($GLOBALS['__dm_pdv_multiplos_abertos'] ?? false);
$clean = static fn(string $path = ''): string => tenantCleanUrl($path);
$isAtivo = static fn(string $item): bool => $active === $item;
?>
<aside id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo htmlspecialchars($clean('pdv')); ?>" class="sidebar-logo" title="PDV">
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="logo-img">
        </a>
        <button id="sidebarCollapse" class="sidebar-toggle d-none d-md-flex" title="Recolher menu" aria-label="Recolher menu">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <div class="px-3 pb-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3">
                <div class="text-uppercase small text-muted mb-1">Caixa ativo</div>
                <?php if (is_array($caixaResumo)): ?>
                    <div class="fw-semibold">Caixa #<?php echo (int) ($caixaResumo['numero_caixa'] ?? 0); ?></div>
                    <div class="small"><?php echo htmlspecialchars((string) ($caixaResumo['operador_nome'] ?? '')); ?></div>
                    <div class="small text-muted">
                        Aberto em <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($caixaResumo['data_abertura'] ?? 'now')))); ?>
                    </div>
                <?php else: ?>
                    <div class="small text-muted">Nenhum caixa selecionado.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($multiplosCaixas): ?>
            <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                Há mais de um caixa aberto agora. Confira o caixa selecionado antes de lançar.
            </div>
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($clean('pdv')); ?>" class="menu-link <?php echo $isAtivo('abertura') ? 'active' : ''; ?>" title="Abertura e Fechamento">
                    <i class="fas fa-cash-register menu-icon"></i>
                    <span class="menu-text">Abertura e Fechamento</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($clean('pdv/clientes')); ?>" class="menu-link <?php echo $isAtivo('clientes') ? 'active' : ''; ?>" title="Clientes">
                    <i class="fas fa-address-book menu-icon"></i>
                    <span class="menu-text">Clientes</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($clean('pdv/produtos')); ?>" class="menu-link <?php echo $isAtivo('produtos') ? 'active' : ''; ?>" title="Produtos">
                    <i class="fas fa-box menu-icon"></i>
                    <span class="menu-text">Produtos</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($clean('pdv/pedidos?action=kanban')); ?>" class="menu-link <?php echo $isAtivo('pedidos') ? 'active' : ''; ?>" title="Pedidos">
                    <i class="fas fa-clipboard-list menu-icon"></i>
                    <span class="menu-text">Pedidos</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="<?php echo htmlspecialchars($clean('pdv/servicos?action=kanban')); ?>" class="menu-link <?php echo $isAtivo('servicos') ? 'active' : ''; ?>" title="Serviços">
                    <i class="fas fa-tools menu-icon"></i>
                    <span class="menu-text">Serviços</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo htmlspecialchars($clean()); ?>" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-arrow-left me-1"></i> Sair do PDV
        </a>
        <button class="theme-toggle" id="themeToggle" aria-label="Alternar tema" title="Alternar tema">
            <i class="fas fa-sun theme-icon sun"></i>
            <i class="fas fa-moon theme-icon moon"></i>
            <span class="menu-text theme-text">Alternar tema</span>
        </button>
    </div>
</aside>
