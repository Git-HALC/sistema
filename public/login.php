<?php
require_once __DIR__ . '/../config/tenant.php';
tenantBootstrap();
tenantEnsureSession();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Support/PermissionManager.php';

$landingUrl = tenantCurrentSlug() !== null ? '../landing.php' : 'landing.php';
$authUrl = tenantUrl('auth.php');
$dashboardUrl = tenantUrl('admin/dashboard.php');
$hadInvalidSession = false;

if (isset($_SESSION['user_id'])) {
    $role = (int)($_SESSION['user_role'] ?? 0);

    if ($role > 0 && isset($db) && $db instanceof PDO) {
        $permissionManager = new \App\Support\PermissionManager($db, (int)$_SESSION['user_id'], $role);
        $redirectUrl = $permissionManager->getRedirectUrl();
        $redirectPath = (string)(parse_url($redirectUrl, PHP_URL_PATH) ?? '');
        $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

        if ($redirectPath !== '' && basename($redirectPath) !== 'login.php' && $redirectPath !== $currentPath) {
            header('Location: ' . $redirectUrl);
            exit();
        }
    }

    // Evita loop login <-> dashboard quando a sessao esta inconsistente.
    $_SESSION = [];
    $hadInvalidSession = true;
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
} elseif ($hadInvalidSession) {
    $error_message = 'Sessao expirada, inconsistente ou sem modulo inicial disponivel. Faca login novamente.';
}

$loginErrorCode = (string)($_GET['error'] ?? $_GET['erro'] ?? '');

$logoSrc = '';
$settingsPath = __DIR__ . '/../config/site_settings.php';
if (file_exists($settingsPath)) {
    $s = include $settingsPath;
    if (!empty($s['logo'])) {
        $logoSrc = $s['logo'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema DM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --c1: #667eea;
            --c2: #764ba2;
            --c3: #f093fb;
            --dark: #0d0f1a;
            --glass: rgba(255,255,255,0.06);
            --glass-border: rgba(255,255,255,0.12);
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        .left-panel {
            flex: 1;
            background: var(--dark);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 40%, rgba(102,126,234,0.3) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 80%, rgba(118,75,162,0.25) 0%, transparent 55%);
            z-index: 0;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(55px);
            z-index: 0;
        }

        .orb-a { width: 320px; height: 320px; background: rgba(102,126,234,0.4); top: -80px; left: -80px; animation: drift 20s ease-in-out infinite; }
        .orb-b { width: 260px; height: 260px; background: rgba(118,75,162,0.35); bottom: -60px; right: -60px; animation: drift 25s ease-in-out infinite reverse; }
        .orb-c { width: 180px; height: 180px; background: rgba(240,147,251,0.2); top: 50%; left: 55%; animation: drift 18s ease-in-out infinite; opacity: 0.7; }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0); }
            33%       { transform: translate(20px, -25px); }
            66%       { transform: translate(-15px, 18px); }
        }

        .left-content { position: relative; z-index: 1; }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--c1), var(--c2));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            box-shadow: 0 4px 20px rgba(102,126,234,0.5);
            flex-shrink: 0;
        }

        .brand-logo-img { height: 40px; border-radius: 8px; }

        .brand-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
        }

        .left-hero {
            position: relative;
            z-index: 1;
        }

        .left-hero h2 {
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            font-weight: 800;
            line-height: 1.15;
            color: white;
            margin-bottom: 1rem;
        }

        .left-hero h2 span {
            background: linear-gradient(135deg, var(--c1), var(--c3));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .left-hero p {
            color: rgba(255,255,255,0.55);
            font-size: 0.95rem;
            line-height: 1.65;
            margin-bottom: 2rem;
            max-width: 380px;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            color: rgba(255,255,255,0.65);
            font-size: 0.9rem;
        }

        .feature-list li .fi {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .left-bottom {
            position: relative;
            z-index: 1;
        }

        .left-bottom a {
            color: rgba(255,255,255,0.35);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .left-bottom a:hover { color: rgba(255,255,255,0.65); }

        .right-panel {
            width: 420px;
            min-width: 420px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            position: relative;
            overflow-y: auto;
        }

        .right-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 1px;
            height: 100%;
            background: linear-gradient(to bottom, transparent, rgba(102,126,234,0.3), transparent);
        }

        .login-box {
            width: 100%;
            max-width: 340px;
            animation: slide-in 0.5s cubic-bezier(.22,1,.36,1) both;
        }

        @keyframes slide-in {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .login-box h3 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 0.4rem;
        }

        .login-box .subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.87rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            border: 1px solid;
        }

        .alert-danger  { background: #fff5f5; color: #c53030; border-color: #fed7d7; }
        .alert-warning { background: #fffaf0; color: #b45309; border-color: #fcd9a7; }

        .form-group { margin-bottom: 1rem; }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 0.45rem;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: color 0.2s;
        }

        .form-group input {
            width: 100%;
            height: 48px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 0 44px 0 38px;
            font-size: 0.95rem;
            color: #111827;
            transition: border-color .2s, box-shadow .2s;
            background: #fff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,.14);
        }

        .form-group input:focus + i,
        .form-group input.is-invalid + i { color: #667eea; }

        .form-group input.is-invalid {
            border-color: #ef4444;
            background: #fff5f5;
        }

        .invalid-msg {
            margin-top: .4rem;
            color: #ef4444;
            font-size: .8rem;
            display: flex;
            align-items: center;
            gap: .35rem;
        }

        .pwd-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            padding: 6px;
            line-height: 0;
        }

        .pwd-toggle:hover { color: #6b7280; }

        .btn-login {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--c1), var(--c2));
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: .35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 26px rgba(102,126,234,.45);
            transition: transform .2s, box-shadow .2s, opacity .2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102,126,234,.55);
        }

        .btn-login:active { transform: translateY(0); }

        .btn-login.loading .btn-text { opacity: 0; }

        .spinner {
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            opacity: 0;
            animation: spin .7s linear infinite;
        }

        .btn-login.loading .spinner { opacity: 1; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .form-links {
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-links a {
            color: #6b7280;
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .form-links a:hover { color: var(--c2); }

        .back-link {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            color: #9ca3af !important;
            font-size: 0.82rem !important;
        }

        .back-link:hover { color: #6b7280 !important; }

        @media (max-width: 768px) {
            body { flex-direction: column; overflow: auto; }
            .left-panel { min-height: 260px; flex: none; padding: 2rem 1.5rem; }
            .left-hero p, .feature-list { display: none; }
            .left-hero h2 { font-size: 1.7rem; margin-bottom: 0.5rem; }
            .right-panel { width: 100%; min-width: 0; padding: 2rem 1.5rem; flex: 1; }
            .left-bottom { display: none; }
        }
    </style>
</head>
<body>
    <div class="left-panel">
        <div class="orb orb-a"></div>
        <div class="orb orb-b"></div>
        <div class="orb orb-c"></div>

        <div class="left-content brand">
            <?php if ($logoSrc): ?>
                <img src="<?= htmlspecialchars($logoSrc) ?>" alt="Logo" class="brand-logo-img">
            <?php else: ?>
                <div class="brand-icon"><i class="fas fa-headset"></i></div>
            <?php endif; ?>
            <span class="brand-name">Sistema DM</span>
        </div>

        <div class="left-content left-hero">
            <h2>Bem-vindo<br>de <span>volta</span>!</h2>
            <p>Acesse sua plataforma de gestao empresarial e tenha controle total do seu negocio.</p>

            <ul class="feature-list">
                <li>
                    <div class="fi" style="background:rgba(102,126,234,0.2)">
                        <i class="fas fa-clipboard-list" style="color:#667eea"></i>
                    </div>
                    Gestao completa de pedidos e orcamentos
                </li>
                <li>
                    <div class="fi" style="background:rgba(74,222,128,0.15)">
                        <i class="fas fa-chart-line" style="color:#4ade80"></i>
                    </div>
                    Controle financeiro e fluxo de caixa
                </li>
                <li>
                    <div class="fi" style="background:rgba(251,191,36,0.15)">
                        <i class="fas fa-boxes" style="color:#fbbf24"></i>
                    </div>
                    Estoque com alertas de reposicao
                </li>
                <li>
                    <div class="fi" style="background:rgba(248,113,113,0.15)">
                        <i class="fas fa-file-pdf" style="color:#f87171"></i>
                    </div>
                    Relatorios exportaveis em PDF
                </li>
            </ul>
        </div>

        <div class="left-bottom">
            <a href="<?= htmlspecialchars($landingUrl) ?>">
                <i class="fas fa-arrow-left" style="margin-right:4px;font-size:.7rem"></i> Voltar para a pagina inicial
            </a>
        </div>
    </div>

    <div class="right-panel">
        <div class="login-box">
            <h3>Entrar</h3>
            <p class="subtitle">Use suas credenciais para acessar o painel.</p>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php elseif ($loginErrorCode !== ''): ?>
            <div class="alert alert-warning">
                <i class="fas fa-triangle-exclamation" style="margin-top:2px;flex-shrink:0"></i>
                <span><?php
                    $msgs = [
                        'invalid_credentials' => 'E-mail ou senha incorretos. Tente novamente.',
                        'access_denied' => 'Acesso negado. Voce nao tem permissao para esta area.',
                        'session_expired' => 'Sessao expirada. Por favor, faca login novamente.',
                        'licenca_vencida' => 'Licenca vencida ou invalida. Regularize para acessar o sistema.',
                        '1' => 'Erro ao processar sua solicitacao.',
                    ];
                    echo $msgs[$loginErrorCode] ?? 'Ocorreu um erro inesperado. Tente novamente.';
                ?></span>
            </div>
            <?php endif; ?>

            <form id="loginForm" action="<?= htmlspecialchars($authUrl) ?>" method="POST" novalidate>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="input-wrap">
                        <input type="email" id="email" name="email" placeholder="seu@email.com" autocomplete="email" autofocus>
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <div class="input-wrap">
                        <input type="password" id="senha" name="senha" placeholder="********" autocomplete="current-password">
                        <i class="fas fa-lock"></i>
                        <button type="button" class="pwd-toggle" id="pwdToggle" aria-label="Mostrar senha">
                            <i class="fas fa-eye" id="pwdIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Entrar na conta</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="form-links">
                <a href="<?= htmlspecialchars($landingUrl) ?>" class="back-link">
                    <i class="fas fa-home"></i> Inicio
                </a>
                <a href="#">Esqueceu a senha?</a>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const form = document.getElementById('loginForm');
        const emailEl = document.getElementById('email');
        const senhaEl = document.getElementById('senha');
        const btn = document.getElementById('btnLogin');

        function setError(el, msg) {
            el.classList.add('is-invalid');
            const existing = el.parentElement.nextElementSibling;
            if (existing && existing.classList.contains('invalid-msg')) existing.remove();
            const div = document.createElement('div');
            div.className = 'invalid-msg';
            div.innerHTML = '<i class="fas fa-circle-exclamation"></i>' + msg;
            el.parentElement.insertAdjacentElement('afterend', div);
        }

        function clearError(el) {
            el.classList.remove('is-invalid');
            const sib = el.parentElement.nextElementSibling;
            if (sib && sib.classList.contains('invalid-msg')) sib.remove();
        }

        [emailEl, senhaEl].forEach(el => {
            el.addEventListener('input', () => clearError(el));
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            let ok = true;

            const emailVal = emailEl.value.trim();
            const senhaVal = senhaEl.value.trim();

            if (!emailVal) {
                setError(emailEl, 'Por favor, insira seu e-mail.');
                ok = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                setError(emailEl, 'Insira um e-mail valido.');
                ok = false;
            } else {
                clearError(emailEl);
            }

            if (!senhaVal) {
                setError(senhaEl, 'Por favor, insira sua senha.');
                ok = false;
            } else if (senhaVal.length < 6) {
                setError(senhaEl, 'A senha deve ter pelo menos 6 caracteres.');
                ok = false;
            } else {
                clearError(senhaEl);
            }

            if (ok) {
                btn.classList.add('loading');
                form.submit();
            }
        });

        const toggle = document.getElementById('pwdToggle');
        const pwdIcon = document.getElementById('pwdIcon');
        toggle.addEventListener('click', function () {
            const show = senhaEl.type === 'password';
            senhaEl.type = show ? 'text' : 'password';
            pwdIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    })();
    </script>
</body>
</html>
