<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
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

$planoAtual = null;
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

    if ($suportaPlanoEmpresas && isset($empresa['plano_id'])) {
        $planoId = (int)$empresa['plano_id'];
        if ($planoId > 0) {
            $planoStmt = $pdo->prepare(
                'SELECT id, slug, nome, descricao, max_usuarios
                   FROM planos
                  WHERE id = :id
                  LIMIT 1'
            );
            $planoStmt->execute([':id' => $planoId]);
            $planoRow = $planoStmt->fetch();
            if (is_array($planoRow)) {
                $planoAtual = $planoRow;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Falha ao buscar plano atual da empresa: ' . $e->getMessage());
}

$historicoStmt = $pdo->prepare(
    'SELECT h.*, a.nome AS admin_nome
       FROM licenca_historico h
  LEFT JOIN admins a ON a.id = h.admin_id
      WHERE h.empresa_id = :id
   ORDER BY h.created_at DESC
      LIMIT 100'
);
$historicoStmt->execute([':id' => $empresaId]);
$historico = $historicoStmt->fetchAll() ?: [];

$emailsStmt = $pdo->prepare(
    'SELECT *
       FROM emails_enviados
      WHERE empresa_id = :id
   ORDER BY enviado_em DESC
      LIMIT 30'
);
$emailsStmt->execute([':id' => $empresaId]);
$emails = $emailsStmt->fetchAll() ?: [];

function statusBadgeClassView(string $status): string
{
    return match (strtolower($status)) {
        'ativa' => 'status-ativa',
        'bloqueada' => 'status-bloqueada',
        'trial' => 'status-trial',
        'cancelada' => 'status-cancelada',
        default => 'status-cancelada',
    };
}

function historicoIconView(string $acao): string
{
    $norm = strtolower($acao);
    if (str_contains($norm, 'bloq')) {
        return 'fa-lock';
    }
    if (str_contains($norm, 'renov')) {
        return 'fa-rotate';
    }
    if (str_contains($norm, 'cri')) {
        return 'fa-circle-plus';
    }

    return 'fa-clock';
}

function descricaoMudancaPlanoHistorico(array $item): ?string
{
    $slugAnterior = trim((string)($item['plano_slug_anterior'] ?? ''));
    $slugNovo = trim((string)($item['plano_slug_novo'] ?? ''));
    if ($slugAnterior !== '' || $slugNovo !== '') {
        return sprintf(
            'slug %s -> %s',
            $slugAnterior !== '' ? $slugAnterior : '-',
            $slugNovo !== '' ? $slugNovo : '-'
        );
    }

    $nomeAnterior = trim((string)($item['plano_nome_anterior'] ?? ''));
    $nomeNovo = trim((string)($item['plano_nome_novo'] ?? ''));
    if ($nomeAnterior !== '' || $nomeNovo !== '') {
        return sprintf(
            '%s -> %s',
            $nomeAnterior !== '' ? $nomeAnterior : '-',
            $nomeNovo !== '' ? $nomeNovo : '-'
        );
    }

    $idAnterior = trim((string)($item['plano_id_anterior'] ?? ''));
    $idNovo = trim((string)($item['plano_id_novo'] ?? ''));
    if ($idAnterior !== '' || $idNovo !== '') {
        return sprintf(
            'id %s -> %s',
            $idAnterior !== '' ? $idAnterior : '-',
            $idNovo !== '' ? $idNovo : '-'
        );
    }

    $observacao = trim((string)($item['observacao'] ?? ''));
    if ($observacao !== '' && str_contains(strtolower($observacao), 'plano')) {
        return $observacao;
    }

    return null;
}

$diasRestantesEmpresa = diasRestantes((string)$empresa['licenca_fim']);
$labelAcaoBloqueio = ((string)$empresa['status'] === 'bloqueada') ? 'Desbloquear' : 'Bloquear';
$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empresa #<?php echo $empresaId; ?> - Painel Master</title>
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
                <span class="crumb-current"><?php echo e((string)$empresa['nome']); ?></span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-hourglass-half"></i> Dias restantes: <?php echo (int)$diasRestantesEmpresa; ?></span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel">
                <div class="toolbar-inline">
                    <div>
                        <h1 class="page-title"><?php echo e((string)$empresa['nome']); ?></h1>
                        <p class="page-subtitle">Razao social <?php echo e((string)($empresa['razao_social'] ?? $empresa['nome'])); ?> · <span class="badge <?php echo e(statusBadgeClassView((string)$empresa['status'])); ?>"><?php echo e((string)$empresa['status']); ?></span></p>
                    </div>
                    <div class="actions-inline">
                        <a class="btn btn-ghost" href="<?php echo e(adminUrl('admin/empresas/renovar.php')); ?>?id=<?php echo $empresaId; ?>"><i class="fa-solid fa-layer-group"></i> Editar Plano</a>
                        <a class="btn btn-ghost" href="<?php echo e(adminUrl('admin/empresas/renovar.php')); ?>?id=<?php echo $empresaId; ?>"><i class="fa-solid fa-rotate"></i> Renovar</a>
                        <a class="btn btn-danger" href="<?php echo e(adminUrl('admin/empresas/bloquear.php')); ?>?id=<?php echo $empresaId; ?>"><i class="fa-solid fa-lock"></i> <?php echo e($labelAcaoBloqueio); ?></a>
                    </div>
                </div>
            </section>

            <section class="two-col">
                <article class="panel">
                    <h2 class="panel-title">Dados da Empresa e Licenca</h2>
                    <div class="info-grid">
                        <div class="info-item"><span>ID</span><strong><?php echo (int)$empresa['id']; ?></strong></div>
                        <div class="info-item"><span>Status</span><strong><span class="badge <?php echo e(statusBadgeClassView((string)$empresa['status'])); ?>"><?php echo e((string)$empresa['status']); ?></span></strong></div>
                        <div class="info-item"><span>Nome da empresa</span><div><?php echo e((string)$empresa['nome']); ?></div></div>
                        <div class="info-item"><span>CNPJ</span><div><?php echo e((string)$empresa['cnpj']); ?></div></div>
                        <div class="info-item"><span>Razao social</span><div><?php echo e((string)($empresa['razao_social'] ?? $empresa['nome'])); ?></div></div>
                        <div class="info-item"><span>E-mail</span><div><?php echo e((string)($empresa['email'] ?? '')); ?></div></div>
                        <div class="info-item"><span>Tipo da licenca</span><div><?php echo e((string)$empresa['licenca_tipo']); ?></div></div>
                        <div class="info-item"><span>Inicio</span><div><?php echo e((string)$empresa['licenca_inicio']); ?></div></div>
                        <div class="info-item"><span>Fim</span><div><?php echo e((string)$empresa['licenca_fim']); ?></div></div>
                        <div class="info-item"><span>Dias restantes</span><strong><?php echo (int)$diasRestantesEmpresa; ?></strong></div>
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <span>Plano Atual</span>
                            <div><strong>Nome:</strong> <?php echo e((string)($planoAtual['nome'] ?? 'Profissional')); ?></div>
                            <div><strong>Max usuarios adicionais:</strong> <?php echo isset($planoAtual['max_usuarios']) && $planoAtual['max_usuarios'] !== null ? (int)$planoAtual['max_usuarios'] : 'Ilimitado'; ?></div>
                            <div><small>Admin padrao nao entra no limite.</small></div>
                            <div><strong>Slug:</strong> <?php echo e((string)($planoAtual['slug'] ?? 'profissional')); ?></div>
                        </div>
                        <div class="info-item" style="grid-column: 1 / -1;"><span>Chave da licenca</span><div><code><?php echo e((string)$empresa['chave_licenca']); ?></code></div></div>
                    </div>
                </article>

                <article class="panel">
                    <div class="countdown-card">
                        <div class="countdown-title">Contador de Licenca</div>
                        <div class="countdown-value"><?php echo (int)$diasRestantesEmpresa; ?> dias</div>
                    </div>

                    <h3 class="panel-title" style="margin-top:14px;">Timeline de Historico</h3>
                    <div class="timeline">
                        <?php foreach ($historico as $item): ?>
                            <?php $mudancaPlano = descricaoMudancaPlanoHistorico((array)$item); ?>
                            <article class="timeline-item">
                                <div class="timeline-icon"><i class="fa-solid <?php echo e(historicoIconView((string)$item['acao'])); ?>"></i></div>
                                <div>
                                    <div class="timeline-head">
                                        <span class="timeline-title"><?php echo e((string)$item['acao']); ?></span>
                                        <span class="timeline-date"><?php echo e((string)$item['created_at']); ?></span>
                                    </div>
                                    <p class="timeline-sub">Admin: <?php echo e((string)($item['admin_nome'] ?? 'Sistema')); ?> - <?php echo e((string)($item['status_anterior'] ?? '-')); ?> -> <?php echo e((string)($item['status_novo'] ?? '-')); ?></p>
                                    <?php if ($mudancaPlano !== null): ?>
                                        <p class="timeline-sub">Plano: <?php echo e($mudancaPlano); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$historico): ?>
                            <div class="timeline-item"><div class="timeline-icon"><i class="fa-solid fa-clock"></i></div><div class="timeline-sub">Sem historico.</div></div>
                        <?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="panel">
                <h2 class="panel-title">E-mails enviados</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Destinatario</th>
                            <th>Status</th>
                            <th>Erro</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($emails as $mail): ?>
                            <tr>
                                <td><?php echo e((string)$mail['enviado_em']); ?></td>
                                <td><?php echo e((string)$mail['tipo']); ?></td>
                                <td><?php echo e((string)$mail['destinatario']); ?></td>
                                <td><?php echo e((string)$mail['status']); ?></td>
                                <td><?php echo e((string)($mail['erro_mensagem'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$emails): ?>
                            <tr><td colspan="5">Nenhum e-mail registrado.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>
<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
</body>
</html>
