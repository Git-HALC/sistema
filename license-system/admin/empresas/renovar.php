<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
$suportaPlanoEmpresas = false;
$planos = [];
$planosPorId = [];
$defaultPlanoId = 1;

try {
    $colunaPlanoStmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1
              FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'empresas'
               AND column_name = 'plano_id'
        )"
    );
    $colunaPlanoStmt->execute();
    $suportaPlanoEmpresas = (bool)$colunaPlanoStmt->fetchColumn();

    if ($suportaPlanoEmpresas) {
        $pdo->exec(
            "UPDATE planos
                SET nome = 'Profissional',
                    descricao = '1 usuario adicional alem do admin padrao',
                    max_usuarios = 1
              WHERE slug = 'profissional';
             UPDATE planos
                SET nome = 'Master',
                    descricao = 'Usuarios ilimitados, sem restricao de quantidade',
                    max_usuarios = NULL
              WHERE slug = 'master';"
        );

        $sqlPlanosAtivos = "SELECT id, slug, nome, descricao, max_usuarios
               FROM planos
              WHERE ativo = TRUE
           ORDER BY
                CASE
                    WHEN slug = 'profissional' THEN 0
                    WHEN slug = 'master' THEN 1
                    ELSE 2
                END,
                nome ASC";

        $planosStmt = $pdo->query($sqlPlanosAtivos);
        $planos = $planosStmt ? ($planosStmt->fetchAll() ?: []) : [];

        if ($planos === []) {
            $pdo->exec(
                "INSERT INTO planos (slug, nome, descricao, max_usuarios)
                 VALUES
                    ('profissional', 'Profissional', '1 usuario adicional alem do admin padrao', 1),
                    ('master', 'Master', 'Usuarios ilimitados, sem restricao de quantidade', NULL)
                 ON CONFLICT (slug) DO NOTHING"
            );
            $planosStmt = $pdo->query($sqlPlanosAtivos);
            $planos = $planosStmt ? ($planosStmt->fetchAll() ?: []) : [];
        }

        foreach ($planos as $plano) {
            $planosPorId[(int)$plano['id']] = $plano;
        }

        if (!isset($planosPorId[$defaultPlanoId])) {
            $planoProfissional = array_values(array_filter(
                $planos,
                static fn(array $p): bool => (string)($p['slug'] ?? '') === 'profissional'
            ));
            if ($planoProfissional !== []) {
                $defaultPlanoId = (int)$planoProfissional[0]['id'];
            } elseif ($planos !== []) {
                $defaultPlanoId = (int)$planos[0]['id'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao carregar planos ativos na renovacao: ' . $e->getMessage());
}

$empresaId = (int)($_GET['id'] ?? 0);
if ($empresaId <= 0) {
    header('Location: ' . adminUrl('admin/empresas/listar.php') . '?msg=Empresa%20invalida');
    exit;
}

$empresaStmt = $pdo->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
$empresaStmt->execute([':id' => $empresaId]);
$empresa = $empresaStmt->fetch();
if (!is_array($empresa)) {
    header('Location: ' . adminUrl('admin/empresas/listar.php') . '?msg=Empresa%20nao%20encontrada');
    exit;
}

$planoAtualId = (int)($empresa['plano_id'] ?? $defaultPlanoId);
if (!isset($planosPorId[$planoAtualId])) {
    $planoAtualId = $defaultPlanoId;
}
$empresa['plano_id'] = $planoAtualId;
$planoAtual = $planosPorId[$planoAtualId] ?? null;

$erro = '';

function sincronizarLicencaCliente(array $empresa, string $tipo, string $fim, string $status, ?array $planoData): void
{
    $clientePdo = MasterDatabase::createPdo((string)$empresa['banco_dados']);
    $update = $clientePdo->prepare(
        'UPDATE licenca
            SET licenca_tipo = :tipo,
                licenca_inicio = CURRENT_DATE,
                licenca_fim = :fim,
                status = :status,
                dias_aviso = :dias_aviso
          WHERE id = (SELECT id FROM licenca ORDER BY id ASC LIMIT 1)'
    );
    $update->execute([
        ':tipo' => $tipo,
        ':fim' => $fim,
        ':status' => $status,
        ':dias_aviso' => (int)$empresa['dias_aviso'],
    ]);

    if (is_array($planoData)) {
        $syncPlano = $clientePdo->prepare(
            'UPDATE licenca
                SET plano_slug = :plano_slug,
                    max_usuarios = :max_usuarios
              WHERE TRUE'
        );
        $syncPlano->bindValue(':plano_slug', (string)$planoData['slug'], PDO::PARAM_STR);
        if ($planoData['max_usuarios'] === null) {
            $syncPlano->bindValue(':max_usuarios', null, PDO::PARAM_NULL);
        } else {
            $syncPlano->bindValue(':max_usuarios', (int)$planoData['max_usuarios'], PDO::PARAM_INT);
        }
        $syncPlano->execute();

        aplicarLimiteUsuariosPlanoProfissional(
            $clientePdo,
            (string)($planoData['slug'] ?? ''),
            $planoData['max_usuarios'] ?? null
        );
    }
}

function aplicarLimiteUsuariosPlanoProfissional(PDO $clientePdo, string $planoSlug, mixed $maxUsuarios): void
{
    $slug = strtolower(trim($planoSlug));
    if ($slug !== 'profissional') {
        return;
    }

    if ($maxUsuarios === null) {
        return;
    }

    $limite = max(0, (int)$maxUsuarios);

    $sql = "
        WITH ordenados AS (
            SELECT
                u.id,
                ROW_NUMBER() OVER (
                    ORDER BY
                        CASE u.nivel_acesso_id
                            WHEN 1 THEN 1
                            WHEN 2 THEN 2
                            WHEN 3 THEN 3
                            WHEN 4 THEN 4
                            ELSE 5
                        END ASC,
                        u.created_at ASC NULLS LAST,
                        u.id ASC
                ) AS rn
            FROM usuarios u
            WHERE u.ativo = TRUE
              AND NOT (
                  LOWER(u.email) = LOWER('admin@suporte.com')
                  AND u.nivel_acesso_id = 1
              )
        ),
        desativar AS (
            SELECT id
            FROM ordenados
            WHERE rn > :limite
        )
        UPDATE usuarios u
           SET ativo = FALSE
          FROM desativar d
         WHERE u.id = d.id
    ";

    $stmt = $clientePdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Token CSRF invalido.';
    } else {
        $novoTipo = strtolower(trim((string)($_POST['licenca_tipo'] ?? 'mensal')));
        $novaDataInformada = trim((string)($_POST['licenca_fim'] ?? ''));
        $novoPlanoId = (int)($_POST['plano_id'] ?? $planoAtualId);

        if (!$suportaPlanoEmpresas) {
            $erro = 'Banco master sem suporte a plano_id. Execute o arquivo database/master.sql.';
        } elseif ($planos === []) {
            $erro = 'Nenhum plano ativo encontrado no banco master.';
        } elseif (!isset($planosPorId[$novoPlanoId])) {
            $erro = 'Plano invalido.';
        } elseif (!in_array($novoTipo, ['mensal', 'anual', 'trial'], true)) {
            $erro = 'Tipo de licenca invalido.';
        } else {
            $novaDataFim = $novaDataInformada !== '' ? $novaDataInformada : calcularLicencaFim($novoTipo);
            $dateCheck = DateTimeImmutable::createFromFormat('Y-m-d', $novaDataFim);
            if (!$dateCheck || $dateCheck->format('Y-m-d') !== $novaDataFim) {
                $erro = 'Data final invalida.';
            } else {
                $novoStatus = $novoTipo === 'trial' ? 'trial' : 'ativa';
                $planoSelecionado = $planosPorId[$novoPlanoId];
                $planoAnterior = $planosPorId[$planoAtualId] ?? null;
                $planoAlterado = $novoPlanoId !== $planoAtualId;

                try {
                    $pdo->beginTransaction();
                    $update = $pdo->prepare(
                        'UPDATE empresas
                            SET licenca_tipo = :tipo,
                                licenca_inicio = CURRENT_DATE,
                                licenca_fim = :fim,
                                status = :status,
                                plano_id = :plano_id
                          WHERE id = :id'
                    );
                    $update->execute([
                        ':tipo' => $novoTipo,
                        ':fim' => $novaDataFim,
                        ':status' => $novoStatus,
                        ':plano_id' => $novoPlanoId,
                        ':id' => $empresaId,
                    ]);

                    if ($planoAlterado) {
                        $historicoPlano = $pdo->prepare(
                            'INSERT INTO licenca_historico (
                                empresa_id,
                                admin_id,
                                acao,
                                status_anterior,
                                status_novo,
                                observacao
                            ) VALUES (
                                :empresa_id,
                                :admin_id,
                                :acao,
                                :status_anterior,
                                :status_novo,
                                :observacao
                            )'
                        );
                        $historicoPlano->execute([
                            ':empresa_id' => $empresaId,
                            ':admin_id' => (int)($_SESSION['admin_id'] ?? 0) ?: null,
                            ':acao' => 'ALTERACAO_TIPO',
                            ':status_anterior' => (string)$empresa['status'],
                            ':status_novo' => $novoStatus,
                            ':observacao' => sprintf(
                                'Plano alterado de %s (%s) para %s (%s)',
                                (string)($planoAnterior['nome'] ?? 'N/A'),
                                (string)($planoAnterior['slug'] ?? 'n/a'),
                                (string)$planoSelecionado['nome'],
                                (string)$planoSelecionado['slug']
                            ),
                        ]);
                    }
                    $pdo->commit();

                    sincronizarLicencaCliente($empresa, $novoTipo, $novaDataFim, $novoStatus, [
                        'slug' => (string)$planoSelecionado['slug'],
                        'max_usuarios' => $planoSelecionado['max_usuarios'] !== null
                            ? (int)$planoSelecionado['max_usuarios']
                            : null,
                    ]);
                    header('Location: ' . adminUrl('admin/empresas/ver.php') . '?id=' . $empresaId);
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Erro ao renovar licenca: ' . $e->getMessage());
                    $erro = 'Falha ao renovar licenca.';
                }
            }
        }
    }
}

$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Renovar Licenca</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(adminUrl('admin/assets/css/style.css')); ?>">
</head>
<body class="admin-shell">
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-head">
            <div class="logo-mark"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="logo-text">
                <span class="logo-title">LicenseSystem</span>
                <span class="logo-sub">Master Panel</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="sidebar-link is-active" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-building"></i> Empresas</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#ultimas-acoes"><i class="fa-solid fa-clock-rotate-left"></i> Historico</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#configuracoes"><i class="fa-solid fa-gear"></i> Configuracoes</a>
        </nav>
        <div class="sidebar-foot">
            <div class="admin-id">
                <div class="admin-avatar"><?php echo e($adminInicial); ?></div>
                <div class="admin-meta">
                    <span class="admin-name"><?php echo e($adminNome); ?></span>
                    <span class="admin-role">Administrador</span>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-wrap">
        <header class="topbar-fixed">
            <div class="topbar-left">
                <button type="button" class="mobile-nav-toggle" data-nav-toggle aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
                <span>Painel</span>
                <i class="fa-solid fa-angle-right"></i>
                <span>Empresas</span>
                <i class="fa-solid fa-angle-right"></i>
                <span class="crumb-current">Renovar Licenca</span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-bolt"></i> <?php echo e((string)$empresa['nome']); ?></span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel" style="max-width:700px; margin:0 auto;">
                <h1 class="page-title">Renovar Licenca</h1>
                <p class="page-subtitle"><?php echo e((string)$empresa['nome']); ?></p>
                <p class="page-subtitle">Plano atual: <strong><?php echo e((string)($planoAtual['nome'] ?? 'Profissional')); ?></strong></p>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-error" style="margin-top:12px"><?php echo e($erro); ?></div>
                <?php endif; ?>

                <form method="post" class="stack" autocomplete="off" data-renew-form data-loading-submit style="margin-top:14px;">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">

                    <div class="form-grid">
                        <div class="float-field">
                            <select name="licenca_tipo" id="licenca_tipo">
                                <option value="mensal" <?php echo $empresa['licenca_tipo'] === 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                                <option value="anual" <?php echo $empresa['licenca_tipo'] === 'anual' ? 'selected' : ''; ?>>Anual</option>
                                <option value="trial" <?php echo $empresa['licenca_tipo'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            </select>
                            <label for="licenca_tipo">Tipo da licenca</label>
                        </div>

                        <div class="float-field">
                            <input type="date" name="licenca_fim" id="licenca_fim" value="">
                            <label for="licenca_fim">Data final (opcional)</label>
                        </div>
                    </div>

                    <div class="field">
                        <span>Plano</span>
                        <input type="hidden" name="plano_id" id="plano_id" value="<?php echo (int)$empresa['plano_id']; ?>">
                        <div class="license-plan-grid" data-plano-cards>
                            <?php foreach ($planos as $plano): ?>
                                <?php
                                $planoId = (int)$plano['id'];
                                $slug = (string)$plano['slug'];
                                $isSelected = $planoId === (int)$empresa['plano_id'];
                                $isProfissional = $slug === 'profissional';
                                $isMaster = $slug === 'master';
                                $maxUsuarios = $plano['max_usuarios'] !== null ? (int)$plano['max_usuarios'] : null;
                                ?>
                                <article
                                    class="license-plan <?php echo $isSelected ? 'is-selected selected' : ''; ?>"
                                    data-plano-id="<?php echo $planoId; ?>"
                                    role="button"
                                    tabindex="0"
                                    aria-pressed="<?php echo $isSelected ? 'true' : 'false'; ?>"
                                >
                                    <div class="plan-title">
                                        <?php if ($isMaster): ?>
                                            <i class="fa-solid fa-infinity"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-users"></i>
                                        <?php endif; ?>
                                        <?php echo e((string)$plano['nome']); ?>
                                    </div>
                                    <?php if ($isProfissional): ?>
                                        <div class="plan-sub">1 usuario adicional</div>
                                        <div class="plan-sub">Admin padrao nao conta no limite</div>
                                    <?php elseif ($isMaster): ?>
                                        <div class="plan-sub">Usuarios ilimitados</div>
                                        <div class="plan-sub">Sem restricao de quantidade</div>
                                    <?php else: ?>
                                        <div class="plan-sub"><?php echo e((string)($plano['descricao'] ?? '')); ?></div>
                                        <div class="plan-sub">
                                            <?php echo $maxUsuarios === null ? 'Usuarios ilimitados' : ('Ate ' . $maxUsuarios . ' usuarios'); ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="compare-grid">
                        <article class="compare-card">
                            <h4>Atual</h4>
                            <p>Tipo: <?php echo e((string)$empresa['licenca_tipo']); ?></p>
                            <p data-current-fim>Fim: <?php echo e((string)$empresa['licenca_fim']); ?></p>
                        </article>
                        <article class="compare-card">
                            <h4>Novo</h4>
                            <p>Tipo: <span data-compare-tipo><?php echo e((string)$empresa['licenca_tipo']); ?></span></p>
                            <p>Fim: <span data-preview-fim><?php echo e((string)$empresa['licenca_fim']); ?></span></p>
                        </article>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" data-open-renew-modal>
                            <span class="btn-text"><i class="fa-solid fa-floppy-disk"></i> Salvar renovacao</span>
                            <span class="spinner"></span>
                        </button>
                        <a class="btn btn-ghost" href="<?php echo e(adminUrl('admin/empresas/ver.php')); ?>?id=<?php echo $empresaId; ?>"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>

<div class="modal" id="confirmRenewModal" aria-hidden="true">
    <div class="modal-card">
        <h3 class="modal-title">Confirmar renovacao</h3>
        <p class="modal-text">Deseja aplicar esta renovacao de licenca para a empresa selecionada?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-close-renew-modal>Cancelar</button>
            <button type="button" class="btn btn-primary" data-confirm-renew>Confirmar</button>
        </div>
    </div>
</div>

<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
<script>
(function () {
    const wrap = document.querySelector('[data-plano-cards]');
    const hiddenInput = document.getElementById('plano_id');
    if (!wrap || !hiddenInput) {
        return;
    }

    const cards = Array.from(wrap.querySelectorAll('[data-plano-id]'));
    if (!cards.length) {
        return;
    }

    const sync = function (value) {
        cards.forEach(function (card) {
            const selected = card.getAttribute('data-plano-id') === String(value);
            card.classList.toggle('is-selected', selected);
            card.classList.toggle('selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    };

    cards.forEach(function (card) {
        const activate = function () {
            const planoId = card.getAttribute('data-plano-id');
            if (!planoId) {
                return;
            }
            hiddenInput.value = planoId;
            sync(planoId);
        };

        card.addEventListener('click', activate);
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    sync(hiddenInput.value);
})();
</script>
</body>
</html>
