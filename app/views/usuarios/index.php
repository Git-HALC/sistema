<?php
// Incluir o cabecalho
$titulo_pagina = $titulo ?? 'Usuarios';

$renderNivelHtml = static function (array $usuario): string {
    $nivelNome = trim((string) ($usuario['nivel_nome'] ?? ''));
    if ($nivelNome === '') {
        $nivelNome = 'Nivel ' . (int) ($usuario['nivel_acesso_id'] ?? 0);
    }

    $html = htmlspecialchars($nivelNome, ENT_QUOTES, 'UTF-8');

    if ((int) ($usuario['nivel_acesso_id'] ?? 0) === 4) {
        $tooltip = trim((string) ($usuario['modulos_personalizados'] ?? ''));
        if ($tooltip === '') {
            $tooltip = 'Nenhum modulo liberado.';
        }

        $html .= ' <span class="badge text-bg-info ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="'
            . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8')
            . '">Personalizado</span>';
    }

    return $html;
};

$isAdminPadrao = static function (array $usuario): bool {
    $raw = $usuario['admin_padrao'] ?? false;

    if (is_bool($raw)) {
        return $raw;
    }

    if (is_int($raw)) {
        return $raw === 1;
    }

    if (is_string($raw)) {
        return in_array(strtolower($raw), ['1', 't', 'true', 'yes', 'y'], true);
    }

    return false;
};

$planInfo = is_array($planInfo ?? null) ? $planInfo : [];
$planSlug = strtolower(trim((string) ($planInfo['plano_slug'] ?? 'profissional')));
if ($planSlug === '') {
    $planSlug = 'profissional';
}

$maxUsuarios = array_key_exists('max_usuarios', $planInfo) && $planInfo['max_usuarios'] !== null
    ? max(0, (int) $planInfo['max_usuarios'])
    : null;
$totalAtivos = max(0, (int) ($planInfo['total_ativos'] ?? 0));
$podeCriarUsuario = (bool) ($planInfo['pode_criar'] ?? true);
$planoMaster = $planSlug === 'master' || $maxUsuarios === null;
$limiteAtingido = !$podeCriarUsuario;
$showLimitModal = isset($_GET['limite_usuarios']) && (string) $_GET['limite_usuarios'] === '1';

$upgradeUrl = trim((string) ($upgradeUrl ?? ''));
if ($upgradeUrl === '') {
    $upgradeUrl = 'mailto:financeiro@seu-dominio.com';
}

if ($planoMaster) {
    $textoPlanoBadge = 'Plano Master - Usuarios ilimitados';
    $textoModalLimite = 'Plano Master ativo: usuarios ilimitados.';
    $progressPercent = 0;
} else {
    $limitePlano = max(1, (int) $maxUsuarios);
    $utilizadosPlano = min($totalAtivos, $limitePlano);
    $progressPercent = (int) min(100, round(($utilizadosPlano / $limitePlano) * 100));
    $textoPlanoBadge = sprintf('Plano Profissional - %d de %d usuarios adicionais', $totalAtivos, (int) $maxUsuarios);
    $textoModalLimite = sprintf(
        'Seu plano Profissional permite ate %d usuario%s adicional%s alem do admin padrao. Voce ja possui %d usuario%s ativo%s adicional%s.',
        (int) $maxUsuarios,
        (int) $maxUsuarios === 1 ? '' : 's',
        (int) $maxUsuarios === 1 ? '' : 'is',
        $totalAtivos,
        $totalAtivos === 1 ? '' : 's',
        $totalAtivos === 1 ? '' : 's',
        $totalAtivos === 1 ? '' : 'is'
    );
}
?>

<div class="container-fluid">

    <!-- Cabecalho da Pagina -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($titulo_pagina); ?></h1>
        <?php if ($podeCriarUsuario): ?>
            <a href="/sistema_dm/public/admin/usuarios.php?action=novo" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Novo Usuario
            </a>
        <?php else: ?>
            <span class="d-inline-block" id="btnNovoUsuarioBloqueadoWrap" tabindex="0"
                  data-bs-toggle="tooltip" data-bs-placement="left" title="Limite de usuarios atingido">
                <button type="button" class="btn btn-primary btn-sm opacity-50" style="cursor: not-allowed;" disabled>
                    <i class="fas fa-plus me-1"></i> Novo Usuario
                </button>
            </span>
        <?php endif; ?>
    </div>

    <!-- Mensagens Flash -->
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['error_message']); // Limpa a mensagem apos exibir ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
        <?php unset($_SESSION['success_message']); // Limpa a mensagem apos exibir ?>
    <?php endif; ?>

        <!-- Status do Plano -->
    <div class="mb-3">
        <?php if ($planoMaster): ?>
            <span class="badge rounded-pill text-bg-success px-3 py-2">
                <?php echo htmlspecialchars($textoPlanoBadge, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        <?php else: ?>
            <div class="rounded border border-primary-subtle bg-primary-subtle p-3">
                <div class="mb-2">
                    <span class="badge rounded-pill text-bg-primary px-3 py-2">
                        <?php echo htmlspecialchars($textoPlanoBadge, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="progress" style="height: 7px; max-width: 280px;" aria-label="Uso de usuarios do plano">
                    <div class="progress-bar bg-primary" role="progressbar"
                         style="width: <?php echo $progressPercent; ?>%;"
                         aria-valuenow="<?php echo $totalAtivos; ?>"
                         aria-valuemin="0"
                         aria-valuemax="<?php echo (int) $maxUsuarios; ?>"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<!-- Listagem de Usuarios -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">

            <?php if (empty($usuarios)): ?>
                <div class="p-4 text-center text-muted">Nenhum usuario encontrado.</div>
            <?php else: ?>

                <!-- Tabela para Desktop -->
                <div class="d-none d-md-block">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Nivel</th>
                                    <th>Ativo</th>
                                    <th>Criado em</th>
                                    <th class="text-center">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($u['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['telefone'] ?? '-'); ?></td>
                                        <td><?php echo $renderNivelHtml($u); ?></td>
                                        <td>
                                            <?php echo !empty($u['ativo']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>'; ?>
                                        </td>
                                        <td><?php echo !empty($u['created_at']) ? date('d/m/Y H:i', strtotime($u['created_at'])) : '-'; ?></td>
                                        <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="/sistema_dm/public/admin/usuarios.php?action=editar&id=<?php echo $u['id']; ?>"
                                                       class="btn btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if (!$isAdminPadrao($u)): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-excluir-usuario"
                                                                data-id="<?php echo $u['id']; ?>"
                                                                data-nome="<?php echo htmlspecialchars($u['nome']); ?>"
                                                                title="Excluir">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-secondary" disabled title="Admin padrao protegido">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cards para Mobile -->
                <div class="d-md-none p-3">
                    <?php foreach ($usuarios as $u): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($u['nome']); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo $renderNivelHtml($u); ?></small>
                                    </div>
                                    <div>
                                        <?php echo !empty($u['ativo']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>'; ?>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3 small">
                                    <div class="col-12">
                                        <span class="text-muted d-block">E-mail</span>
                                        <strong><?php echo htmlspecialchars($u['email'] ?? '-'); ?></strong>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted d-block">Telefone</span>
                                        <strong><?php echo htmlspecialchars($u['telefone'] ?? '-'); ?></strong>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted d-block">Criado em</span>
                                        <strong><?php echo !empty($u['created_at']) ? date('d/m/Y H:i', strtotime($u['created_at'])) : '-'; ?></strong>
                                    </div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="/sistema_dm/public/admin/usuarios.php?action=editar&id=<?php echo $u['id']; ?>"
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if (!$isAdminPadrao($u)): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-excluir-usuario flex-fill"
                                                data-id="<?php echo $u['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($u['nome']); ?>">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" disabled>
                                            <i class="fas fa-lock"></i> Protegido
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginacao -->
                <?php
                    // Garante que as variaveis de paginacao estejam definidas
                    $totalUsuarios = $totalUsuarios ?? 0; // Usara o valor existente ou 0
                    $totalPaginas = $totalPaginas ?? 1;   // Usara o valor existente ou 1
                    $paginaAtual = $pagina ?? 1;         // Usara o valor existente ou 1
                ?>
                <?php if ($totalPaginas > 1 || $totalUsuarios > 0): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <small class="text-muted">
                            <?php echo $totalUsuarios; ?> usuario<?php echo ($totalUsuarios !== 1) ? 's' : ''; ?> encontrado<?php echo ($totalUsuarios !== 1) ? 's' : ''; ?>
                        </small>
                        <?php if ($totalPaginas > 1): ?>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($paginaAtual > 1): ?>
                                        <li class="page-item">
                                            <?php
                                            // Constroi a query string mantendo outros filtros, se houver
                                            $filtrosUrl = $_GET; 
                                            unset($filtrosUrl['pagina']);
                                            $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                            ?>
                                            <a class="page-link" href="?pagina=<?php echo $paginaAtual - 1; ?><?php echo $queryString; ?>">&lsaquo;</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                                        <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                                            <?php
                                            // Constroi a query string mantendo outros filtros, se houver
                                            $filtrosUrl = $_GET; 
                                            unset($filtrosUrl['pagina']);
                                            $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                            ?>
                                            <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $queryString; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($paginaAtual < $totalPaginas): ?>
                                        <li class="page-item">
                                            <?php
                                            // Constroi a query string mantendo outros filtros, se houver
                                            $filtrosUrl = $_GET; 
                                            unset($filtrosUrl['pagina']);
                                            $queryString = !empty($filtrosUrl) ? '&' . http_build_query($filtrosUrl) : '';
                                            ?>
                                            <a class="page-link" href="?pagina=<?php echo $paginaAtual + 1; ?><?php echo $queryString; ?>">&rsaquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($limiteAtingido): ?>
    <div class="modal fade" id="modalLimiteUsuarios" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <div class="display-6 mb-2"><i class="fas fa-users-slash"></i></div>
                    <h5 class="mb-2">Limite de usuarios atingido</h5>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($textoModalLimite, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="<?php echo htmlspecialchars($upgradeUrl, ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-primary" target="_blank" rel="noopener">
                            Solicitar Upgrade
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Incluir o rodape
include __DIR__ . '/../../../public/includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const limiteAtingido = <?php echo $limiteAtingido ? 'true' : 'false'; ?>;
    const mostrarModalLimite = <?php echo $showLimitModal ? 'true' : 'false'; ?>;

    function abrirModalLimiteUsuarios() {
        const modalEl = document.getElementById('modalLimiteUsuarios');
        if (!modalEl) {
            return;
        }

        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    if (window.bootstrap && typeof window.bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new window.bootstrap.Tooltip(el);
        });
    }

    const btnNovoUsuarioBloqueadoWrap = document.getElementById('btnNovoUsuarioBloqueadoWrap');
    if (btnNovoUsuarioBloqueadoWrap && limiteAtingido) {
        btnNovoUsuarioBloqueadoWrap.addEventListener('click', function (e) {
            e.preventDefault();
            abrirModalLimiteUsuarios();
        });

        btnNovoUsuarioBloqueadoWrap.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            e.preventDefault();
            abrirModalLimiteUsuarios();
        });
    }

    if (limiteAtingido && mostrarModalLimite) {
        abrirModalLimiteUsuarios();
    }

    document.querySelectorAll('.btn-excluir-usuario').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const id = this.dataset.id;
            const nome = this.dataset.nome;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Excluir usuario?',
                    text: `Tem certeza que deseja excluir o usuario "${nome}"?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Excluir',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ef4444'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    excluirUsuario(id);
                });
                return;
            }

            if (confirm(`Tem certeza que deseja excluir o usuario "${nome}"?`)) {
                excluirUsuario(id);
            }
        });
    });

    function excluirUsuario(id) {
        fetch('/sistema_dm/public/admin/usuarios.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=excluir&id=${encodeURIComponent(id)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        timer: 1800,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    alert(data.message);
                    location.reload();
                }
                return;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire('Erro', data.message || 'Nao foi possivel excluir o usuario.', 'error');
            } else {
                alert(data.message || 'Nao foi possivel excluir o usuario.');
            }
        })
        .catch(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Erro', 'Falha de comunicacao ao excluir usuario.', 'error');
            } else {
                alert('Falha de comunicacao ao excluir usuario.');
            }
        });
    }
});
</script>
