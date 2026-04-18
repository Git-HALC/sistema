<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/license.php';

use App\Support\LicenseValidator;

$licenseData = [
    'status' => 'nao_configurada',
    'licenca_fim' => '',
    'dias_restantes' => 0,
    'dias_aviso' => 7,
];

try {
    $pdo = Database::getInstance()->getConnection();
    $validator = new LicenseValidator($pdo, defined('LICENSE_API_URL') ? (string) LICENSE_API_URL : '');
    $licenseData = $validator->checkLocal();
} catch (Throwable $e) {
    error_log('blocked.php erro ao ler licenca: ' . $e->getMessage());
    $sessionBlock = $_SESSION['license_block'] ?? null;
    if (is_array($sessionBlock)) {
        $licenseData = array_merge($licenseData, $sessionBlock);
    }
}

$licencaFimIso = (string)($licenseData['licenca_fim'] ?? '');
$licencaFimFmt = 'Nao informada';
$diasVencida = 0;

if ($licencaFimIso !== '') {
    try {
        $today = new DateTimeImmutable('today');
        $licencaFimDate = new DateTimeImmutable($licencaFimIso);
        $licencaFimFmt = $licencaFimDate->format('d/m/Y');
        if ($licencaFimDate < $today) {
            $diasVencida = (int)$licencaFimDate->diff($today)->format('%a');
        }
    } catch (Throwable) {
        $licencaFimFmt = $licencaFimIso;
    }
}

$status = strtolower((string)($licenseData['status'] ?? 'nao_configurada'));
$statusLabel = match ($status) {
    'ativa' => 'Ativa',
    'trial' => 'Trial',
    'bloqueada' => 'Bloqueada',
    'cancelada' => 'Cancelada',
    'nao_configurada' => 'Nao configurada',
    'erro_verificacao' => 'Erro de verificacao',
    default => ucfirst($status),
};

$contatoEmail = defined('LICENSE_CONTATO_EMAIL') ? (string) LICENSE_CONTATO_EMAIL : 'financeiro@seu-dominio.com';
$contatoTelefone = defined('LICENSE_CONTATO_TELEFONE') ? (string) LICENSE_CONTATO_TELEFONE : '(00) 00000-0000';

$logoSrc = '';
$settingsPath = __DIR__ . '/../config/site_settings.php';
if (file_exists($settingsPath)) {
    $s = include $settingsPath;
    if (!empty($s['logo'])) {
        $logoSrc = (string)$s['logo'];
    }
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/public/blocked.php'));
$publicBase = rtrim(dirname($scriptName), '/');
if ($publicBase === '' || $publicBase === '.') {
    $publicBase = '/public';
}
$logoutUrl = $publicBase . '/logout.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenca bloqueada - Sistema DM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #0f1117;
            --card: #1a1d27;
            --accent: #6c63ff;
            --ok: #00d4aa;
            --text: #eef0ff;
            --soft: #aab2d6;
            --line: rgba(255,255,255,.11);
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }

        body {
            font-family: Inter, 'Segoe UI', system-ui, sans-serif;
            background: radial-gradient(circle at 15% 15%, rgba(108,99,255,.22), transparent 36%),
                        radial-gradient(circle at 80% 0%, rgba(0,212,170,.14), transparent 32%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 16px;
        }

        .blocked-card {
            width: min(560px, 100%);
            background: linear-gradient(180deg, var(--card) 0%, #161924 100%);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 28px 55px rgba(0, 0, 0, .45);
            text-align: center;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #4f58f2);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 30px rgba(108,99,255,.4);
        }

        .logo-img { height: 40px; border-radius: 8px; }
        .brand-name { font-weight: 700; letter-spacing: .02em; }

        .lock-wrap {
            width: 86px;
            height: 86px;
            margin: 6px auto 12px;
            border-radius: 50%;
            border: 1px solid rgba(255,95,122,.35);
            background: rgba(255,95,122,.08);
            display: grid;
            place-items: center;
            color: #ff8ca3;
            font-size: 2rem;
            animation: pulseLock 1.8s ease-in-out infinite;
        }

        @keyframes pulseLock {
            0%,100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,95,122,.3); }
            50% { transform: scale(1.04); box-shadow: 0 0 0 16px rgba(255,95,122,0); }
        }

        h1 {
            margin: 0;
            font-size: clamp(1.4rem, 3vw, 2rem);
            line-height: 1.2;
        }

        .subtitle {
            margin: 8px auto 0;
            color: var(--soft);
            max-width: 430px;
            font-size: .95rem;
            line-height: 1.5;
        }

        .status-grid {
            margin-top: 16px;
            display: grid;
            gap: 8px;
            text-align: left;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255,255,255,.02);
            padding: 10px 12px;
            font-size: .88rem;
        }

        .status-row span { color: var(--soft); }
        .status-row strong { color: #fff; }

        .contact-box {
            margin-top: 14px;
            border: 1px solid rgba(0,212,170,.35);
            background: rgba(0,212,170,.07);
            border-radius: 12px;
            padding: 12px;
            text-align: left;
        }

        .contact-title {
            margin: 0 0 8px;
            color: #9fffe9;
            font-weight: 700;
            font-size: .88rem;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .contact-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #d4fff4;
            text-decoration: none;
            margin-bottom: 6px;
            font-size: .9rem;
        }

        .contact-link:last-child { margin-bottom: 0; }

        .btn-logout {
            margin-top: 14px;
            width: 100%;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #4f58f2);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 46px;
            box-shadow: 0 14px 32px rgba(108,99,255,.35);
            transition: transform .2s ease;
        }

        .btn-logout:hover { transform: translateY(-1px); }
    </style>
</head>
<body>
    <section class="blocked-card">
        <div class="brand">
            <?php if ($logoSrc): ?>
                <img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo" class="logo-img">
            <?php else: ?>
                <span class="logo-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <?php endif; ?>
            <span class="brand-name">Sistema DM</span>
        </div>

        <div class="lock-wrap"><i class="fa-solid fa-lock"></i></div>
        <h1>Licenca vencida ou invalida</h1>
        <p class="subtitle">Seu ambiente foi bloqueado por seguranca. Regularize a licenca para restaurar o acesso imediatamente.</p>

        <div class="status-grid">
            <div class="status-row"><span>Status</span><strong><?= htmlspecialchars($statusLabel) ?></strong></div>
            <div class="status-row"><span>Vencimento</span><strong><?= htmlspecialchars($licencaFimFmt) ?></strong></div>
            <div class="status-row"><span>Dias vencida</span><strong><?= (int)$diasVencida ?></strong></div>
        </div>

        <div class="contact-box">
            <p class="contact-title">Contato para renovacao</p>
            <a class="contact-link" href="mailto:<?= htmlspecialchars($contatoEmail) ?>"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($contatoEmail) ?></a>
            <a class="contact-link" href="tel:<?= htmlspecialchars(preg_replace('/\D+/', '', $contatoTelefone) ?: $contatoTelefone) ?>"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($contatoTelefone) ?></a>
        </div>

        <a href="<?= htmlspecialchars($logoutUrl) ?>" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Sair do sistema</a>
    </section>
</body>
</html>
