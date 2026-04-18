<?php
declare(strict_types=1);
define('SUPORTE_WHATSAPP', '5577999161412'); // numero com codigo do pais
define('SUPORTE_EMAIL', 'devmarka.oficial@gmail.com');
define('EMPRESA_NOME', 'Sistema DM');

date_default_timezone_set('America/Sao_Paulo');
$isResolveAction = (($_GET['action'] ?? '') === 'resolve_empresa');
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$publicBase = rtrim(str_replace('\\', '/', dirname($scriptName !== '' ? $scriptName : '/public/landing.php')), '/');
if ($publicBase === '' || $publicBase === '.') {
    $publicBase = '/public';
}
$projectBase = preg_replace('#/public$#', '', $publicBase) ?? '';
if ($projectBase === '') {
    $projectBase = '/';
}
if (!$isResolveAction && session_status() === PHP_SESSION_NONE) {
    $sessionSavePath = (string)ini_get('session.save_path');
    if (strpos($sessionSavePath, ';') !== false) {
        $chunks = explode(';', $sessionSavePath);
        $sessionSavePath = (string)end($chunks);
    }
    $sessionSavePath = trim($sessionSavePath);
    $sessionWritable = ($sessionSavePath !== '' && is_dir($sessionSavePath) && is_writable($sessionSavePath));

    if (!$sessionWritable) {
        $fallbackSessionPath = dirname(__DIR__) . '/tmp_sessions';
        if (!is_dir($fallbackSessionPath)) {
            @mkdir($fallbackSessionPath, 0777, true);
        }
        if (is_dir($fallbackSessionPath) && is_writable($fallbackSessionPath)) {
            ini_set('session.save_path', $fallbackSessionPath);
        }
    }

    @session_start();
}

if (!function_exists('landingSlugify')) {
    function landingSlugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }
}

if (!function_exists('landingResolveCompanySlug')) {
    function landingResolveCompanySlug(string $input): ?string
    {
        $slug = landingSlugify($input);
        if ($slug === '') {
            return null;
        }

        $masterConfig = __DIR__ . '/../license-system/config/config.php';
        if (!file_exists($masterConfig)) {
            return null;
        }

        require_once $masterConfig;

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            (string)MASTER_DB_HOST,
            (string)MASTER_DB_PORT,
            (string)MASTER_DB_NAME
        );

        $pdo = new PDO(
            $dsn,
            (string)MASTER_DB_USER,
            (string)MASTER_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $hasUrlSlug = false;
        $colStmt = $pdo->query(
            "SELECT 1
               FROM information_schema.columns
              WHERE table_schema = current_schema()
                AND table_name = 'empresas'
                AND column_name = 'url_slug'
              LIMIT 1"
        );
        if ($colStmt && $colStmt->fetchColumn()) {
            $hasUrlSlug = true;
        }

        if ($hasUrlSlug) {
            $stmt = $pdo->prepare(
                "SELECT url_slug, nome
                   FROM empresas
                  WHERE lower(url_slug) = :slug
                  LIMIT 1"
            );
            $stmt->execute([':slug' => strtolower($slug)]);
            $found = $stmt->fetch();
            if (is_array($found)) {
                $urlSlug = landingSlugify((string)($found['url_slug'] ?? ''));
                if ($urlSlug !== '') {
                    return $urlSlug;
                }

                $nomeSlug = landingSlugify((string)($found['nome'] ?? ''));
                if ($nomeSlug !== '') {
                    return $nomeSlug;
                }
            }
        }

        $stmt = $pdo->query('SELECT nome, banco_dados FROM empresas ORDER BY id ASC');
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        foreach ($rows as $row) {
            $nomeSlug = landingSlugify((string)($row['nome'] ?? ''));
            $bancoSlug = landingSlugify((string)($row['banco_dados'] ?? ''));
            if ($slug === $nomeSlug || $slug === $bancoSlug) {
                return $nomeSlug !== '' ? $nomeSlug : $slug;
            }
        }

        return null;
    }
}

if ($isResolveAction) {
    header('Content-Type: application/json; charset=UTF-8');

    $empresa = trim((string)($_POST['empresa'] ?? $_GET['empresa'] ?? ''));
    if ($empresa === '') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'message' => 'Informe o nome da empresa.',
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $resolvedSlug = landingResolveCompanySlug($empresa);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Nao foi possivel validar a empresa agora. Tente novamente.',
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($resolvedSlug === null || $resolvedSlug === '') {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'message' => 'Empresa nao encontrada. Confira o nome cadastrado na licenca.',
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $loginUrl = $publicBase . '/' . rawurlencode($resolvedSlug) . '/login.php';
    echo json_encode([
        'ok' => true,
        'slug' => $resolvedSlug,
        'login_url' => $loginUrl,
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$contactFlash = null;
if (
    !$isResolveAction
    && isset($_SESSION['contact_form_flash'])
    && is_array($_SESSION['contact_form_flash'])
) {
    $contactFlash = $_SESSION['contact_form_flash'];
    unset($_SESSION['contact_form_flash']);
}

if (
    !$isResolveAction
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['contact_form'] ?? '') === '1'
) {
    $nome = trim((string)($_POST['nome'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $mensagem = trim((string)($_POST['mensagem'] ?? ''));

    $redirectTo = $publicBase . '/landing.php#contato';
    $cleanHeader = static function (string $value): string {
        return trim(str_replace(["\r", "\n"], '', $value));
    };

    if ($nome === '' || $email === '' || $telefone === '' || $mensagem === '') {
        $_SESSION['contact_form_flash'] = [
            'ok' => false,
            'message' => 'Preencha todos os campos do formulario.',
        ];
        header('Location: ' . $redirectTo);
        exit();
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $_SESSION['contact_form_flash'] = [
            'ok' => false,
            'message' => 'Informe um e-mail valido.',
        ];
        header('Location: ' . $redirectTo);
        exit();
    }

    $safeNome = $cleanHeader($nome);
    $safeEmail = $cleanHeader($email);
    $safeTelefone = preg_replace('/[^0-9+\-\s()]/', '', $telefone) ?? '';
    $safeMensagem = trim(preg_replace("/\r\n|\r|\n/", PHP_EOL, $mensagem) ?? '');

    $host = preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?? 'localhost';
    if ($host === '') {
        $host = 'localhost';
    }

    $fromAddress = 'no-reply@' . $host;
    $subject = '[' . EMPRESA_NOME . '] Novo contato de ' . $safeNome;
    $body = implode(PHP_EOL, [
        'Novo contato recebido pelo site:',
        '',
        'Nome: ' . $safeNome,
        'E-mail: ' . $safeEmail,
        'Telefone: ' . $safeTelefone,
        '',
        'Mensagem:',
        $safeMensagem,
        '',
        'Data/Hora: ' . date('d/m/Y H:i:s'),
    ]);

    $headers = [
        'From: ' . EMPRESA_NOME . ' <' . $fromAddress . '>',
        'Reply-To: ' . $safeEmail,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = @mail(SUPORTE_EMAIL, $subject, $body, implode("\r\n", $headers));

    $_SESSION['contact_form_flash'] = [
        'ok' => $sent,
        'message' => $sent
            ? 'Mensagem enviada! Entraremos em contato em breve.'
            : 'Nao foi possivel enviar a mensagem agora. Tente novamente.',
    ];

    header('Location: ' . $redirectTo);
    exit();
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . $publicBase . '/admin/dashboard.php');
    exit();
}

$logoSrc = '';
$settingsPath = __DIR__ . '/../config/site_settings.php';
if (file_exists($settingsPath)) {
    $s = include $settingsPath;
    if (!empty($s['logo'])) {
        $logoSrc = (string)$s['logo'];
        if ($logoSrc !== '' && $logoSrc[0] === '/' && $projectBase !== '/') {
            $posPublic = strpos($logoSrc, '/public/');
            if ($posPublic !== false) {
                $logoSrc = rtrim($projectBase, '/') . substr($logoSrc, $posPublic);
            }
        }
    }
}
$brandLogoFile = __DIR__ . '/assets/images/LOGO_COR_ICONE.png';
$brandLogoUrl = file_exists($brandLogoFile) ? ($publicBase . '/assets/images/LOGO_COR_ICONE.png') : '';
if ($brandLogoUrl === '' && $logoSrc !== '') {
    $brandLogoUrl = $logoSrc;
}
$supportLogoFile = __DIR__ . '/assets/images/LOGO_ESCURA_ICONE.png';
$supportLogoUrl = file_exists($supportLogoFile) ? ($publicBase . '/assets/images/LOGO_ESCURA_ICONE.png') : $brandLogoUrl;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema DM - Gestao Empresarial Inteligente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
        --c1:#667eea; --c2:#764ba2; --c3:#f093fb; --c4:#4facfe;
        --dark:#080b18; --dark2:#0d1025;
        --glass:rgba(255,255,255,.05);
        --glass-b:rgba(255,255,255,.10);
        --txt:#e8eaf6; --muted:#8892a4;
    }

    html { scroll-behavior:smooth; }

    body {
        font-family:'Segoe UI', system-ui, sans-serif;
        background:var(--dark);
        color:var(--txt);
        overflow-x:hidden;
    }

    /* -- CANVAS PARTICLES ------------------------ */
    #canvas-bg {
        position:fixed; inset:0; z-index:0;
        pointer-events:none;
    }

    /* -- CURSOR GLOW ----------------------------- */
    .cursor-glow {
        position:fixed;
        width:400px; height:400px;
        border-radius:50%;
        background:radial-gradient(circle, rgba(102,126,234,.12) 0%, transparent 70%);
        pointer-events:none;
        z-index:1;
        transform:translate(-50%,-50%);
        transition:opacity .3s;
    }

    /* -- SCROLL PROGRESS ------------------------- */
    #scroll-bar {
        position:fixed; top:0; left:0; height:2px; z-index:200;
        background:linear-gradient(90deg,var(--c1),var(--c3));
        width:0%; transition:width .05s linear;
    }

    /* -- WRAPPER ---------------------------------- */
    .wrapper { position:relative; z-index:2; }

    /* -- NAV -------------------------------------- */
    nav {
        display:flex; justify-content:space-between; align-items:center;
        padding:1.2rem 2.5rem;
        border-bottom:1px solid var(--glass-b);
        backdrop-filter:blur(16px);
        -webkit-backdrop-filter:blur(16px);
        background:rgba(8,11,24,.7);
        position:sticky; top:0; z-index:100;
    }

    .nav-brand {
        display:flex; align-items:center; gap:.75rem;
        font-size:1.2rem; font-weight:800; color:#fff; text-decoration:none;
    }
    .brand-icon {
        width:40px; height:40px;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        border-radius:11px;
        display:flex; align-items:center; justify-content:center;
        font-size:1rem; color:#fff;
        box-shadow:0 0 20px rgba(102,126,234,.55);
        animation:icon-pulse 3s ease-in-out infinite;
    }
    @keyframes icon-pulse {
        0%,100%{box-shadow:0 0 20px rgba(102,126,234,.55);}
        50%    {box-shadow:0 0 35px rgba(118,75,162,.8);}
    }
    .brand-logo-img {
        height:42px;
        width:auto;
        max-width:180px;
        object-fit:contain;
        border-radius:10px;
        background:rgba(255,255,255,.04);
        border:1px solid rgba(255,255,255,.12);
        padding:.2rem .35rem;
    }

    .nav-right { display:flex; align-items:center; gap:1rem; }

    .nav-link {
        color:var(--muted); text-decoration:none; font-size:.88rem;
        font-weight:500; transition:color .2s;
    }
    .nav-link:hover { color:#fff; }

    .nav-cta {
        display:inline-flex; align-items:center; gap:.5rem;
        padding:.55rem 1.35rem;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        color:#fff; text-decoration:none; border-radius:50px;
        font-weight:700; font-size:.88rem;
        box-shadow:0 4px 18px rgba(102,126,234,.45);
        transition:transform .2s, box-shadow .2s;
    }
    .nav-cta:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(102,126,234,.65); color:#fff; }

    /* -- HERO -------------------------------------- */
    .hero {
        min-height:92vh;
        display:flex; flex-direction:column;
        align-items:center; justify-content:center;
        text-align:center;
        padding:6rem 2rem 4rem;
        position:relative;
    }

    /* Floating rings */
    .ring {
        position:absolute; border-radius:50%;
        border:1px solid rgba(102,126,234,.18);
        animation:ring-expand linear infinite;
    }
    .ring-1 { width:300px; height:300px; animation-duration:8s; }
    .ring-2 { width:500px; height:500px; animation-duration:12s; animation-delay:-4s; border-color:rgba(118,75,162,.12); }
    .ring-3 { width:700px; height:700px; animation-duration:16s; animation-delay:-8s; border-color:rgba(240,147,251,.08); }

    @keyframes ring-expand {
        0%   { opacity:.8; transform:scale(.9); }
        50%  { opacity:.3; transform:scale(1.05); }
        100% { opacity:.8; transform:scale(.9); }
    }

    .hero-badge {
        display:inline-flex; align-items:center; gap:.5rem;
        background:var(--glass); border:1px solid var(--glass-b);
        border-radius:50px; padding:.4rem 1.1rem;
        font-size:.78rem; color:var(--muted);
        margin-bottom:2rem;
        backdrop-filter:blur(8px);
        animation:badge-in .8s cubic-bezier(.22,1,.36,1) both;
    }
    @keyframes badge-in { from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:none;} }

    .dot-live {
        width:7px; height:7px; background:#4ade80; border-radius:50%;
        animation:live-pulse 1.8s ease-in-out infinite;
    }
    @keyframes live-pulse {
        0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,.6);}
        50%    {box-shadow:0 0 0 6px rgba(74,222,128,0);}
    }

    .hero h1 {
        font-size:clamp(2.6rem,6.5vw,5rem);
        font-weight:900; line-height:1.08;
        margin-bottom:1.5rem;
        animation:hero-in .9s cubic-bezier(.22,1,.36,1) .1s both;
    }
    @keyframes hero-in { from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:none;} }

    .hero h1 .static {
        background:linear-gradient(135deg,#fff 40%,rgba(255,255,255,.7));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }

    .typed-wrap {
        display:block;
        background:linear-gradient(135deg,var(--c1),var(--c3),var(--c4));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        background-size:200% 200%;
        animation:gradient-shift 4s ease-in-out infinite;
    }
    @keyframes gradient-shift {
        0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;}
    }

    .cursor-blink {
        display:inline-block; width:3px; height:.85em;
        background:var(--c3); border-radius:2px; margin-left:3px;
        vertical-align:middle;
        animation:blink .7s step-end infinite;
        -webkit-text-fill-color:var(--c3);
    }
    @keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0;} }

    .hero p {
        font-size:clamp(1rem,2vw,1.18rem);
        color:var(--muted); max-width:520px;
        line-height:1.75; margin-bottom:2.8rem;
        animation:hero-in .9s cubic-bezier(.22,1,.36,1) .25s both;
    }

    .hero-btns {
        display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
        justify-content:center;
        animation:hero-in .9s cubic-bezier(.22,1,.36,1) .4s both;
    }

    .btn-primary-hero {
        position:relative;
        display:inline-flex; align-items:center; gap:.7rem;
        padding:1.05rem 2.6rem;
        color:#fff; text-decoration:none;
        border-radius:60px;
        font-size:1.05rem; font-weight:800;
        z-index:1; overflow:hidden;
        transition:transform .2s, box-shadow .2s;
    }
    .btn-primary-hero::before {
        content:'';
        position:absolute; inset:-2px;
        background:linear-gradient(135deg,var(--c1),var(--c2),var(--c3),var(--c4));
        border-radius:inherit;
        z-index:-1;
        background-size:300% 300%;
        animation:btn-gradient 3s ease infinite;
    }
    .btn-primary-hero::after {
        content:'';
        position:absolute; inset:2px;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        border-radius:55px;
        z-index:-1;
    }
    @keyframes btn-gradient {
        0%,100%{background-position:0% 50%;} 50%{background-position:100% 50%;}
    }
    .btn-primary-hero:hover { transform:translateY(-3px) scale(1.03); color:#fff; box-shadow:0 12px 40px rgba(102,126,234,.6); }
    .arrow-icon { transition:transform .2s; }
    .btn-primary-hero:hover .arrow-icon { transform:translateX(5px); }

    .btn-ghost-hero {
        display:inline-flex; align-items:center; gap:.6rem;
        padding:1rem 2rem;
        color:var(--muted); text-decoration:none;
        border:1px solid var(--glass-b);
        border-radius:60px; font-size:.95rem; font-weight:600;
        backdrop-filter:blur(6px);
        transition:color .2s, border-color .2s, background .2s;
    }
    .btn-ghost-hero:hover { color:#fff; border-color:rgba(255,255,255,.25); background:var(--glass); }

    .hero-scroll-hint {
        position:absolute; bottom:2rem;
        display:flex; flex-direction:column; align-items:center; gap:.4rem;
        color:var(--muted); font-size:.72rem; text-transform:uppercase; letter-spacing:1px;
        animation:bounce-y 2s ease-in-out infinite;
    }
    @keyframes bounce-y { 0%,100%{transform:translateY(0);} 50%{transform:translateY(6px);} }

    /* -- STATS ------------------------------------- */
    .stats {
        display:flex; justify-content:center; flex-wrap:wrap;
        gap:0;
        border-top:1px solid var(--glass-b);
        border-bottom:1px solid var(--glass-b);
        background:rgba(255,255,255,.025);
        backdrop-filter:blur(8px);
    }
    .stat {
        flex:1; min-width:160px;
        padding:2rem 2.5rem; text-align:center;
        border-right:1px solid var(--glass-b);
        position:relative; overflow:hidden;
        transition:background .3s;
    }
    .stat:last-child { border-right:none; }
    .stat::before {
        content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%);
        width:0; height:2px;
        background:linear-gradient(90deg,var(--c1),var(--c3));
        transition:width .4s ease;
    }
    .stat:hover::before { width:80%; }
    .stat:hover { background:rgba(102,126,234,.06); }

    .stat-num {
        font-size:2.2rem; font-weight:900;
        background:linear-gradient(135deg,var(--c1),var(--c3));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        line-height:1;
    }
    .stat-label { font-size:.76rem; color:var(--muted); margin-top:.4rem; text-transform:uppercase; letter-spacing:.5px; }

    /* -- FEATURES ---------------------------------- */
    .section { padding:5rem 2rem; max-width:1140px; margin:0 auto; }

    .section-tag {
        display:inline-flex; align-items:center; gap:.4rem;
        background:linear-gradient(135deg,rgba(102,126,234,.15),rgba(118,75,162,.1));
        border:1px solid rgba(102,126,234,.25);
        border-radius:50px; padding:.3rem .9rem;
        font-size:.75rem; color:var(--c1); font-weight:600;
        text-transform:uppercase; letter-spacing:.5px;
        margin-bottom:1rem;
    }

    .section-title {
        font-size:clamp(1.7rem,3.5vw,2.6rem);
        font-weight:800; color:#fff; line-height:1.2;
        margin-bottom:.75rem;
    }
    .section-title span {
        background:linear-gradient(135deg,var(--c1),var(--c3));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .section-sub { color:var(--muted); font-size:.95rem; max-width:480px; line-height:1.7; margin-bottom:3rem; }

    .cards-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(230px,1fr));
        gap:1.4rem;
    }

    .card {
        background:var(--glass);
        border:1px solid var(--glass-b);
        border-radius:20px; padding:2rem 1.7rem;
        backdrop-filter:blur(10px);
        -webkit-backdrop-filter:blur(10px);
        cursor:default;
        transition:border-color .3s, box-shadow .3s;
        transform-style:preserve-3d;
        will-change:transform;
        /* Stagger via inline style */
    }

    .card:hover {
        border-color:rgba(255,255,255,.2);
        box-shadow:0 24px 48px rgba(0,0,0,.4), 0 0 0 1px var(--glass-b);
    }

    .card-icon {
        width:56px; height:56px; border-radius:15px;
        display:flex; align-items:center; justify-content:center;
        font-size:1.35rem; margin-bottom:1.1rem;
        position:relative; overflow:hidden;
    }
    .card-icon::before {
        content:''; position:absolute; inset:0;
        background:inherit; filter:blur(15px); opacity:.5;
        transform:scale(1.3);
    }
    .card-icon i { position:relative; z-index:1; color:#fff; }

    .card h3 { font-size:1rem; font-weight:700; color:#fff; margin-bottom:.5rem; }
    .card p  { font-size:.82rem; color:var(--muted); line-height:1.65; }

    /* floating animation per card */
    .card:nth-child(1) { animation:float-a 5s ease-in-out infinite; }
    .card:nth-child(2) { animation:float-b 6s ease-in-out infinite; }
    .card:nth-child(3) { animation:float-a 7s ease-in-out infinite .5s; }
    .card:nth-child(4) { animation:float-b 5.5s ease-in-out infinite 1s; }
    .card:nth-child(5) { animation:float-a 6.5s ease-in-out infinite .3s; }
    .card:nth-child(6) { animation:float-b 5s ease-in-out infinite .8s; }

    @keyframes float-a { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-8px);} }
    @keyframes float-b { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-5px);} }

    /* Override tilt from JS */
    .card.tilting { animation:none !important; }

    /* -- WORKFLOW ----------------------------------- */
    .workflow { background:rgba(255,255,255,.02); border-top:1px solid var(--glass-b); border-bottom:1px solid var(--glass-b); padding:5rem 2rem; }

    .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:2rem; max-width:1000px; margin:0 auto; counter-reset:step; }

    .step {
        text-align:center; padding:1.5rem 1rem;
        position:relative;
        animation:step-in .6s both;
    }
    @keyframes step-in { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:none;} }

    .step::before {
        counter-increment:step;
        content:counter(step,'0');
        display:block;
        font-size:3.5rem; font-weight:900;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        line-height:1; margin-bottom:.75rem;
        opacity:.25;
    }

    .step-icon {
        width:58px; height:58px; border-radius:50%;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        display:flex; align-items:center; justify-content:center;
        font-size:1.2rem; color:#fff; margin:0 auto .9rem;
        box-shadow:0 8px 20px rgba(102,126,234,.35);
        position:relative; z-index:1;
    }
    .step h4 { color:#fff; font-size:.95rem; font-weight:700; margin-bottom:.4rem; }
    .step p  { color:var(--muted); font-size:.8rem; line-height:1.6; }

    /* -- CTA BANNER --------------------------------- */
    .cta-banner {
        padding:5rem 2rem; text-align:center;
        max-width:700px; margin:0 auto;
    }
    .cta-banner h2 { font-size:clamp(1.6rem,3.5vw,2.5rem); font-weight:800; color:#fff; margin-bottom:1rem; line-height:1.2; }
    .cta-banner p  { color:var(--muted); margin-bottom:2.5rem; }

    /* -- FOOTER ------------------------------------- */
    footer {
        border-top:1px solid var(--glass-b);
        text-align:center; padding:1.8rem;
        font-size:.76rem; color:var(--muted);
    }

    /* -- LOGIN MODAL ------------------------------- */
    .tenant-login-modal {
        position: fixed;
        inset: 0;
        background: rgba(6, 8, 18, .78);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 300;
        padding: 1rem;
    }
    .tenant-login-modal.is-open { display: flex; }
    .tenant-login-dialog {
        width: 100%;
        max-width: 420px;
        background: linear-gradient(180deg, rgba(18, 22, 44, .97), rgba(10, 13, 28, .97));
        border: 1px solid var(--glass-b);
        border-radius: 18px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
        padding: 1.2rem;
    }
    .tenant-login-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .tenant-login-title {
        font-size: 1.08rem;
        font-weight: 800;
        color: #fff;
    }
    .tenant-close {
        width: 34px;
        height: 34px;
        border: 1px solid var(--glass-b);
        border-radius: 10px;
        background: transparent;
        color: var(--muted);
        cursor: pointer;
        transition: color .2s, border-color .2s, background .2s;
    }
    .tenant-close:hover {
        color: #fff;
        border-color: rgba(255, 255, 255, .26);
        background: rgba(255, 255, 255, .06);
    }
    .tenant-login-sub {
        color: var(--muted);
        font-size: .86rem;
        line-height: 1.6;
        margin-bottom: .9rem;
    }
    .tenant-field-label {
        display: block;
        font-size: .8rem;
        color: #d6daf0;
        margin-bottom: .45rem;
    }
    .tenant-field-input {
        width: 100%;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 12px;
        background: rgba(255, 255, 255, .04);
        color: #fff;
        padding: .8rem .85rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .tenant-field-input:focus {
        border-color: rgba(102, 126, 234, .7);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, .22);
    }
    .tenant-feedback {
        min-height: 1.2rem;
        margin-top: .65rem;
        font-size: .8rem;
        color: #ffb3b3;
    }
    .tenant-feedback.is-success { color: #86efac; }
    .tenant-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1rem;
    }
    .tenant-submit {
        border: 0;
        cursor: pointer;
        border-radius: 999px;
        padding: .72rem 1.2rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--c1), var(--c2));
        box-shadow: 0 8px 24px rgba(102, 126, 234, .35);
        transition: transform .2s, box-shadow .2s, opacity .2s;
    }
    .tenant-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(102, 126, 234, .5);
    }
    .tenant-submit:disabled {
        opacity: .75;
        cursor: wait;
    }

    /* -- NAV ENHANCED ----------------------------- */
    nav {
        transition: background .25s ease, box-shadow .25s ease, backdrop-filter .25s ease;
    }
    nav.scrolled {
        background: rgba(8,11,24,.88);
        backdrop-filter: blur(22px);
        -webkit-backdrop-filter: blur(22px);
        box-shadow: 0 16px 45px rgba(5,8,20,.45), inset 0 0 0 1px rgba(102,126,234,.18);
    }
    .nav-hamburger {
        display:none;
        width:42px; height:42px;
        border:1px solid var(--glass-b);
        border-radius:12px;
        background:rgba(255,255,255,.04);
        color:#fff;
        cursor:pointer;
        align-items:center; justify-content:center;
        transition:transform .2s ease, border-color .2s ease, background .2s ease;
    }
    .nav-hamburger:hover { transform:translateY(-1px); border-color:rgba(255,255,255,.25); background:rgba(255,255,255,.08); }
    .mobile-drawer-backdrop {
        position:fixed; inset:0; z-index:140;
        background:rgba(5,8,18,.62);
        backdrop-filter:blur(3px);
        opacity:0; pointer-events:none;
        transition:opacity .25s ease;
    }
    .mobile-drawer {
        position:fixed; top:0; right:0; height:100vh; width:min(86vw,320px);
        z-index:150;
        background:linear-gradient(180deg, rgba(14,18,40,.98), rgba(8,11,24,.98));
        border-left:1px solid var(--glass-b);
        box-shadow:-20px 0 55px rgba(0,0,0,.45);
        transform:translateX(100%);
        transition:transform .28s ease;
        padding:1.25rem;
        display:flex; flex-direction:column; gap:1rem;
    }
    .mobile-drawer.open { transform:translateX(0); }
    .mobile-drawer-backdrop.open { opacity:1; pointer-events:auto; }
    .drawer-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:.25rem; }
    .drawer-title { font-size:.95rem; font-weight:700; color:#fff; }
    .drawer-close {
        width:34px; height:34px; border-radius:10px;
        border:1px solid var(--glass-b); background:transparent; color:#fff; cursor:pointer;
    }
    .drawer-links { display:flex; flex-direction:column; gap:.35rem; }
    .drawer-link {
        display:flex; align-items:center; gap:.55rem;
        padding:.85rem .9rem;
        border:1px solid var(--glass-b);
        border-radius:12px;
        color:#d9dff7; text-decoration:none; font-weight:600; font-size:.9rem;
        background:rgba(255,255,255,.03);
        transition:transform .2s ease, border-color .2s ease, background .2s ease;
    }
    .drawer-link:hover { transform:translateX(-2px); border-color:rgba(102,126,234,.45); background:rgba(102,126,234,.12); }
    body.drawer-open { overflow:hidden; }

    /* -- HERO SOCIAL PROOF ------------------------ */
    .hero-proof {
        margin-top:1.35rem;
        padding:.78rem 1rem;
        border-radius:999px;
        border:1px solid var(--glass-b);
        background:rgba(255,255,255,.03);
        backdrop-filter:blur(8px);
        display:inline-flex;
        align-items:center;
        gap:.95rem;
        flex-wrap:wrap;
        justify-content:center;
    }
    .proof-avatars { display:flex; align-items:center; }
    .proof-avatar {
        width:30px; height:30px; border-radius:50%;
        border:2px solid rgba(8,11,24,.95);
        margin-left:-8px;
        display:flex; align-items:center; justify-content:center;
        font-size:.68rem; font-weight:800; color:#fff;
        box-shadow:0 6px 14px rgba(0,0,0,.3);
    }
    .proof-avatar:first-child { margin-left:0; }
    .proof-text { font-size:.83rem; color:#d7dcf3; }
    .proof-rating { color:#fbbf24; font-weight:700; font-size:.8rem; letter-spacing:.3px; }

    /* -- PLANOS ----------------------------------- */
    .plans-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(245px,1fr));
        gap:1.35rem;
    }
    .plan-card {
        position:relative;
        background:var(--glass);
        border:1px solid var(--glass-b);
        border-radius:20px;
        padding:1.45rem 1.3rem 1.25rem;
        backdrop-filter:blur(10px);
        overflow:hidden;
        transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .plan-card:hover { transform:translateY(-8px); box-shadow:0 24px 40px rgba(0,0,0,.4); border-color:rgba(255,255,255,.22); }
    .plan-card.popular {
        transform:scale(1.04);
        border-color:transparent;
        box-shadow:0 24px 45px rgba(102,126,234,.2);
    }
    .plan-card.popular::before {
        content:'';
        position:absolute; inset:-1px;
        border-radius:inherit;
        padding:1px;
        background:linear-gradient(135deg,var(--c1),var(--c2),var(--c3),var(--c4));
        background-size:300% 300%;
        animation:btn-gradient 4s ease infinite;
        -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite:xor;
        mask-composite:exclude;
        pointer-events:none;
    }
    .popular-badge {
        position:absolute; top:12px; right:12px;
        font-size:.64rem; font-weight:800; letter-spacing:.6px;
        padding:.32rem .55rem;
        border-radius:999px;
        color:#fff;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        box-shadow:0 8px 18px rgba(102,126,234,.35);
        overflow:hidden;
    }
    .popular-badge::after {
        content:'';
        position:absolute; top:0; left:-40%;
        width:40%; height:100%;
        background:linear-gradient(90deg,transparent,rgba(255,255,255,.7),transparent);
        animation:badge-shine 2.3s linear infinite;
    }
    @keyframes badge-shine { to { left:140%; } }
    .plan-icon {
        width:52px; height:52px; border-radius:14px;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-size:1.15rem; margin-bottom:.9rem;
        box-shadow:0 12px 26px rgba(0,0,0,.25);
    }
    .plan-name { font-size:1.07rem; font-weight:800; color:#fff; }
    .plan-price {
        margin-top:.4rem; font-size:1.65rem; font-weight:900; line-height:1;
        background:linear-gradient(135deg,var(--c1),var(--c3));
        -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .plan-period { color:var(--muted); font-size:.8rem; margin-bottom:1rem; }
    .plan-features { list-style:none; display:grid; gap:.52rem; margin-bottom:1.1rem; }
    .plan-features li { display:flex; align-items:flex-start; gap:.5rem; font-size:.8rem; color:#dfe4f9; }
    .plan-features i.fa-check { color:#22c55e; margin-top:.1rem; }
    .plan-features i.fa-xmark { color:#7f879b; margin-top:.1rem; }
    .plan-features li.is-off { color:#8892a4; }
    .plan-cta {
        display:flex; justify-content:center; align-items:center; gap:.5rem;
        border-radius:999px; text-decoration:none; font-weight:700; font-size:.86rem;
        color:#fff; padding:.75rem 1rem;
        border:1px solid rgba(37,211,102,.35);
        background:linear-gradient(135deg, rgba(37,211,102,.28), rgba(16,185,129,.2));
        transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .plan-cta:hover { transform:translateY(-2px); box-shadow:0 12px 24px rgba(37,211,102,.22); border-color:rgba(37,211,102,.62); color:#fff; }

    /* -- DEPOIMENTOS ------------------------------ */
    .testimonials-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
        gap:1.25rem;
    }
    .testimonial-card {
        position:relative;
        background:var(--glass);
        border:1px solid var(--glass-b);
        border-radius:20px;
        padding:1.25rem 1.2rem 1.15rem;
        backdrop-filter:blur(9px);
        overflow:hidden;
    }
    .testimonial-card::before {
        content:'"';
        position:absolute; right:14px; top:8px;
        font-size:5.2rem; line-height:1;
        color:rgba(255,255,255,.08);
        font-weight:900;
        pointer-events:none;
    }
    .testimonial-head { display:flex; align-items:center; gap:.75rem; margin-bottom:.65rem; }
    .t-avatar {
        width:44px; height:44px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        font-size:.78rem; font-weight:800; color:#fff;
        box-shadow:0 8px 20px rgba(0,0,0,.35);
    }
    .t-name { font-size:.9rem; font-weight:700; color:#fff; }
    .t-role { font-size:.75rem; color:var(--muted); }
    .t-stars { color:#fbbf24; font-size:.78rem; letter-spacing:.2px; margin-bottom:.5rem; }
    .testimonial-card p { font-size:.83rem; color:#d8ddf3; line-height:1.65; }

    /* -- FAQ -------------------------------------- */
    .faq-list { display:grid; gap:.72rem; }
    .faq-item {
        border:1px solid var(--glass-b);
        border-radius:16px;
        background:rgba(255,255,255,.03);
        overflow:hidden;
        transition:border-color .2s ease, box-shadow .2s ease;
    }
    .faq-item.is-open {
        border-left:3px solid transparent;
        border-image:linear-gradient(180deg,var(--c1),var(--c2)) 1;
        border-color:rgba(102,126,234,.35);
        box-shadow:0 12px 24px rgba(12,16,36,.35);
    }
    .faq-question {
        width:100%;
        border:0;
        background:transparent;
        color:#fff;
        text-align:left;
        font-size:.92rem;
        font-weight:700;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:1rem;
        padding:1rem 1rem;
        cursor:pointer;
    }
    .faq-toggle {
        width:26px; height:26px; border-radius:50%;
        border:1px solid var(--glass-b);
        display:flex; align-items:center; justify-content:center;
        color:#dbe2ff; font-size:.92rem; line-height:1;
        transition:transform .2s ease, background .2s ease, border-color .2s ease;
    }
    .faq-item.is-open .faq-toggle {
        transform:rotate(45deg);
        background:rgba(102,126,234,.2);
        border-color:rgba(102,126,234,.45);
    }
    .faq-answer {
        max-height:0;
        overflow:hidden;
        transition:max-height .35s ease;
    }
    .faq-answer p {
        padding:0 1rem 1rem;
        color:#cfd5ec;
        font-size:.84rem;
        line-height:1.7;
    }

    /* -- CONTATO ---------------------------------- */
    .contact-grid {
        display:grid;
        grid-template-columns:40% 1fr;
        gap:1.2rem;
        align-items:stretch;
    }
    .contact-info,
    .contact-form-wrap {
        background:var(--glass);
        border:1px solid var(--glass-b);
        border-radius:20px;
        backdrop-filter:blur(10px);
        padding:1.2rem;
    }
    .contact-info h3 { font-size:1.3rem; margin-bottom:.5rem; color:#fff; }
    .contact-info p  { color:var(--muted); font-size:.88rem; line-height:1.7; margin-bottom:1rem; }
    .contact-methods { display:grid; gap:.65rem; margin-bottom:1rem; }
    .contact-method {
        display:flex; align-items:flex-start; gap:.6rem;
        border:1px solid rgba(255,255,255,.1);
        border-radius:12px;
        padding:.7rem .75rem;
        background:rgba(255,255,255,.03);
    }
    .contact-method i { color:#8ea2ff; margin-top:.15rem; width:18px; text-align:center; }
    .contact-method strong { display:block; font-size:.78rem; color:#fff; margin-bottom:.12rem; }
    .contact-method a,
    .contact-method span { font-size:.8rem; color:#cfd6f2; text-decoration:none; }
    .contact-wa-btn {
        display:inline-flex; align-items:center; gap:.55rem;
        width:100%; justify-content:center;
        text-decoration:none; color:#fff; font-weight:700;
        border-radius:999px; padding:.82rem 1rem;
        background:linear-gradient(135deg,#25D366,#1fb257);
        box-shadow:0 12px 28px rgba(37,211,102,.28);
        transition:transform .2s ease, box-shadow .2s ease;
    }
    .contact-wa-btn:hover { transform:translateY(-2px); box-shadow:0 15px 30px rgba(37,211,102,.36); color:#fff; }
    .contact-wa-btn i { animation:wa-pulse 1.6s ease-in-out infinite; }
    @keyframes wa-pulse {
        0%,100%{ transform:scale(1); }
        50%{ transform:scale(1.15); }
    }
    .contact-form { display:grid; gap:.72rem; }
    .contact-field { display:grid; gap:.35rem; }
    .contact-field label {
        font-size:.78rem; color:#d6daf0; font-weight:600;
    }
    .contact-input,
    .contact-textarea {
        width:100%;
        border:1px solid rgba(255,255,255,.18);
        border-radius:12px;
        background:rgba(255,255,255,.04);
        color:#fff;
        padding:.78rem .82rem;
        outline:none;
        transition:border-color .2s, box-shadow .2s;
        font-family:inherit;
    }
    .contact-input:focus,
    .contact-textarea:focus {
        border-color:rgba(102,126,234,.7);
        box-shadow:0 0 0 3px rgba(102,126,234,.22);
    }
    .contact-textarea { min-height:120px; resize:vertical; }
    .contact-submit {
        margin-top:.25rem;
        border:0; cursor:pointer; border-radius:999px;
        padding:.82rem 1rem;
        font-weight:800;
        color:#fff;
        background:linear-gradient(135deg,var(--c1),var(--c2));
        box-shadow:0 10px 24px rgba(102,126,234,.35);
        transition:transform .2s, box-shadow .2s, opacity .2s;
    }
    .contact-submit:hover { transform:translateY(-1px); box-shadow:0 14px 30px rgba(102,126,234,.48); }
    .contact-submit:disabled { opacity:.78; cursor:wait; }
    .contact-success {
        display:none;
        margin-top:.55rem;
        border:1px solid rgba(34,197,94,.35);
        border-radius:12px;
        background:rgba(34,197,94,.12);
        color:#c8f7d5;
        padding:.7rem .75rem;
        font-size:.8rem;
    }
    .contact-success.show { display:block; }
    .contact-error {
        display:none;
        margin-top:.55rem;
        border:1px solid rgba(239,68,68,.35);
        border-radius:12px;
        background:rgba(239,68,68,.12);
        color:#ffd3d3;
        padding:.7rem .75rem;
        font-size:.8rem;
    }
    .contact-error.show { display:block; }

    /* -- FOOTER COMPLETO -------------------------- */
    footer { text-align:left; padding:2.2rem 2rem 1.5rem; }
    .footer-grid {
        max-width:1140px; margin:0 auto;
        display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:1rem;
    }
    .footer-brand {
        display:flex; align-items:center; gap:.7rem; margin-bottom:.6rem;
        font-size:1rem; font-weight:800; color:#fff;
    }
    .footer-logo {
        width:40px; height:40px; object-fit:contain;
        border-radius:10px; border:1px solid rgba(255,255,255,.15);
        background:rgba(255,255,255,.05); padding:.2rem;
    }
    .footer-text { color:var(--muted); font-size:.82rem; line-height:1.65; max-width:320px; }
    .footer-title { color:#fff; font-size:.9rem; font-weight:700; margin-bottom:.6rem; }
    .footer-links,
    .footer-contact { display:grid; gap:.45rem; }
    .footer-links a,
    .footer-contact a,
    .footer-contact span {
        color:#cfd5ee; text-decoration:none; font-size:.82rem;
    }
    .footer-links a:hover,
    .footer-contact a:hover { color:#fff; }
    .footer-social { display:flex; align-items:center; gap:.55rem; margin-top:.7rem; }
    .footer-social a {
        width:34px; height:34px;
        border-radius:10px;
        border:1px solid rgba(255,255,255,.14);
        display:flex; align-items:center; justify-content:center;
        color:#dbe2ff; text-decoration:none;
        transition:transform .2s, border-color .2s, background .2s;
    }
    .footer-social a:hover { transform:translateY(-2px); border-color:rgba(102,126,234,.45); background:rgba(102,126,234,.14); }
    .footer-bottom {
        max-width:1140px; margin:1.25rem auto 0;
        padding-top:1rem; border-top:1px solid rgba(255,255,255,.08);
        color:var(--muted); font-size:.76rem; text-align:center;
    }

    /* -- WHATSAPP FLOAT --------------------------- */
    .wa-float {
        position:fixed;
        right:24px; bottom:24px;
        width:56px; height:56px;
        border-radius:50%;
        background:#25D366;
        color:#fff; text-decoration:none;
        display:flex; align-items:center; justify-content:center;
        font-size:1.5rem;
        box-shadow:0 16px 30px rgba(37,211,102,.38);
        z-index:999;
        opacity:0;
        transform:translateY(18px) scale(.92);
        pointer-events:none;
        transition:opacity .2s ease, transform .2s ease;
    }
    .wa-float.show {
        opacity:1;
        transform:translateY(0) scale(1);
        pointer-events:auto;
    }
    .wa-float::before {
        content:'Falar com suporte';
        position:absolute;
        right:66px;
        white-space:nowrap;
        font-size:.72rem;
        color:#dff8e7;
        background:rgba(10,14,28,.95);
        border:1px solid rgba(37,211,102,.38);
        border-radius:8px;
        padding:.35rem .5rem;
        opacity:0;
        transform:translateX(6px);
        transition:opacity .2s ease, transform .2s ease;
        pointer-events:none;
    }
    .wa-float:hover::before { opacity:1; transform:translateX(0); }
    .wa-float i { animation:wa-float-pulse 1.8s ease-in-out infinite; }
    @keyframes wa-float-pulse {
        0%,100% { transform:scale(1); box-shadow:0 0 0 0 rgba(37,211,102,.45); }
        50% { transform:scale(1.08); box-shadow:0 0 0 10px rgba(37,211,102,0); }
    }

    /* -- MODAL VISUAL UPGRADE --------------------- */
    .tenant-login-dialog {
        transform:scale(.92);
        opacity:0;
        transition:transform .25s cubic-bezier(.34,1.56,.64,1), opacity .25s cubic-bezier(.34,1.56,.64,1);
    }
    .tenant-login-modal.is-open .tenant-login-dialog {
        transform:scale(1);
        opacity:1;
    }
    .tenant-support {
        margin-top:.65rem;
        padding:.65rem .7rem;
        border-radius:12px;
        border:1px solid rgba(37,211,102,.25);
        background:linear-gradient(135deg, rgba(255,255,255,.95), rgba(245,247,255,.92));
        display:grid;
        gap:.5rem;
    }
    .tenant-support-head {
        display:flex; align-items:center; gap:.5rem;
        color:#1b2338; font-size:.75rem; font-weight:600;
        line-height:1.45;
    }
    .tenant-support-logo {
        width:26px; height:26px; object-fit:contain;
        border-radius:8px;
        border:1px solid rgba(0,0,0,.08);
        background:#fff;
        padding:2px;
        flex:0 0 auto;
    }
    .tenant-support-link {
        display:inline-flex; align-items:center; justify-content:center; gap:.45rem;
        text-decoration:none; font-size:.78rem; font-weight:700; color:#fff;
        border-radius:999px; padding:.52rem .7rem;
        background:linear-gradient(135deg,#25D366,#1fb257);
        box-shadow:0 8px 20px rgba(37,211,102,.28);
    }
    .tenant-feedback:empty + .tenant-support,
    .tenant-feedback.is-success + .tenant-support { display:none; }

    @media(max-width:980px){
        .contact-grid { grid-template-columns:1fr; }
        .footer-grid { grid-template-columns:1fr 1fr; }
        .plan-card.popular { transform:none; }
    }
    @media(max-width:640px){
        .nav-hamburger { display:inline-flex; }
        .nav-right .nav-link { display:none; }
        .hero-proof { border-radius:16px; padding:.8rem; }
        .proof-text { width:100%; text-align:center; }
        .stats { display:grid; grid-template-columns:repeat(2,1fr); }
        .stat { border-right:1px solid var(--glass-b); border-bottom:1px solid var(--glass-b); }
        .stat:nth-child(2n) { border-right:none; }
        .plans-grid,
        .testimonials-grid { grid-template-columns:1fr; }
        .footer-grid { grid-template-columns:1fr; }
        .wa-float { right:16px; bottom:16px; width:52px; height:52px; }
        .wa-float::before { display:none; }
    }

    /* -- REVEAL (scroll) ---------------------------- */
    .reveal { opacity:0; transform:translateY(30px); transition:opacity .7s ease, transform .7s ease; }
    .reveal.visible { opacity:1; transform:none; }

    /* -- RESPONSIVE --------------------------------- */
    @media(max-width:640px){
        nav { padding:1rem 1.25rem; }
        .nav-link { display:none; }
        .hero { padding:4rem 1.25rem 3rem; }
        .ring-2,.ring-3 { display:none; }
        .stat { padding:1.25rem 1rem; min-width:120px; }
        .section { padding:3rem 1.25rem; }
        .workflow { padding:3rem 1.25rem; }
        .cta-banner { padding:3rem 1.25rem; }
    }
    </style>
</head>
<body>
<div id="scroll-bar"></div>
<canvas id="canvas-bg"></canvas>
<div class="cursor-glow" id="cursorGlow"></div>

<div class="wrapper">

    <!-- NAV -->
    <nav id="mainNav">
        <a href="#" class="nav-brand">
            <?php if ($brandLogoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($brandLogoUrl) ?>" alt="Logo <?= htmlspecialchars(EMPRESA_NOME) ?>" class="brand-logo-img">
            <?php else: ?>
                <span class="brand-icon"><i class="fas fa-headset"></i></span>
            <?php endif; ?>
            <?= htmlspecialchars(EMPRESA_NOME) ?>
        </a>
        <div class="nav-right">
            <a href="#features" class="nav-link">Modulos</a>
            <a href="#como-funciona" class="nav-link">Como funciona</a>
            <a href="#planos" class="nav-link">Planos</a>
            <a href="#depoimentos" class="nav-link">Depoimentos</a>
            <a href="#contato" class="nav-link">Contato</a>
            <a href="#" class="nav-cta js-open-tenant-login" data-open-tenant-login="1">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </a>
            <button class="nav-hamburger" id="mobileMenuToggle" aria-label="Abrir menu" aria-controls="mobileDrawer" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    <div class="mobile-drawer-backdrop" id="mobileDrawerBackdrop" aria-hidden="true"></div>
    <aside class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
        <div class="drawer-head">
            <span class="drawer-title"><?= htmlspecialchars(EMPRESA_NOME) ?></span>
            <button type="button" class="drawer-close" id="mobileDrawerClose" aria-label="Fechar menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="drawer-links">
            <a href="#features" class="drawer-link">Modulos</a>
            <a href="#como-funciona" class="drawer-link">Como funciona</a>
            <a href="#planos" class="drawer-link">Planos</a>
            <a href="#depoimentos" class="drawer-link">Depoimentos</a>
            <a href="#faq" class="drawer-link">FAQ</a>
            <a href="#contato" class="drawer-link">Contato</a>
            <a href="#" class="drawer-link js-open-tenant-login" data-open-tenant-login="1"><i class="fas fa-right-to-bracket"></i> Entrar no sistema</a>
        </div>
    </aside>

    <!-- HERO -->
    <section class="hero">
        <div class="ring ring-1"></div>
        <div class="ring ring-2"></div>
        <div class="ring ring-3"></div>

        <div class="hero-badge">
            <span class="dot-live"></span>
            Sistema ativo e operacional
        </div>

        <h1>
            <span class="static">Gestao Empresarial</span><br>
            <span class="typed-wrap"><span id="typed"></span><span class="cursor-blink"></span></span>
        </h1>

        <p>Controle pedidos, financeiro, estoque e clientes em uma plataforma unificada. Mais produtividade, menos trabalho manual.</p>

        <div class="hero-btns">
            <a href="#" class="btn-primary-hero js-open-tenant-login" data-open-tenant-login="1">
                <i class="fas fa-rocket"></i>
                Acessar o Sistema
                <i class="fas fa-arrow-right arrow-icon"></i>
            </a>
            <a href="#features" class="btn-ghost-hero">
                <i class="fas fa-play-circle"></i>
                Ver modulos
            </a>
        </div>
        <div class="hero-proof reveal">
            <div class="proof-avatars">
                <div class="proof-avatar" style="background:linear-gradient(135deg,#667eea,#764ba2);">CM</div>
                <div class="proof-avatar" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">AR</div>
                <div class="proof-avatar" style="background:linear-gradient(135deg,#f093fb,#f5576c);">RS</div>
                <div class="proof-avatar" style="background:linear-gradient(135deg,#43e97b,#38f9d7);">LV</div>
                <div class="proof-avatar" style="background:linear-gradient(135deg,#fa709a,#fee140);">JP</div>
            </div>
            <div class="proof-text">Mais de 50 empresas ja usam o <?= htmlspecialchars(EMPRESA_NOME) ?></div>
            <div class="proof-rating">&#9733;&#9733;&#9733;&#9733;&#9733; 5.0</div>
        </div>

        <div class="hero-scroll-hint">
            <span>Rolar</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </section>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-num" data-target="360" data-suffix=" graus">0 graus</div>
            <div class="stat-label">Visao do negocio</div>
        </div>
        <div class="stat">
            <div class="stat-num" data-target="9" data-suffix=" PDF">0 PDF</div>
            <div class="stat-label">Relatorios exportaveis</div>
        </div>
        <div class="stat">
            <div class="stat-num" data-target="100" data-suffix="%">0%</div>
            <div class="stat-label">Web-based</div>
        </div>
        <div class="stat">
            <div class="stat-num" data-target="24" data-suffix="/7">0/7</div>
            <div class="stat-label">Disponivel sempre</div>
        </div>
        <div class="stat">
            <div class="stat-num" data-target="50" data-suffix="+">0+</div>
            <div class="stat-label">Empresas atendidas</div>
        </div>
        <div class="stat">
            <div class="stat-num">99.9%</div>
            <div class="stat-label">Uptime garantido</div>
        </div>
    </div>

    <!-- FEATURES -->
    <section class="section" id="features">
        <div class="reveal">
            <span class="section-tag"><i class="fas fa-cubes"></i> Modulos</span>
            <h2 class="section-title">Tudo o que voce precisa<br><span>em um so lugar</span></h2>
            <p class="section-sub">Cada modulo foi pensado para simplificar o dia a dia da sua empresa.</p>
        </div>

        <div class="cards-grid">
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>Gestao de Pedidos</h3>
                <p>Acompanhe pedidos em tempo real com quadro Kanban, controle de entregas e historico completo.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#f093fb,#f5576c)">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3>Orcamentos</h3>
                <p>Crie, envie e aprove orcamentos com link publico e assinatura digital do cliente.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Financeiro</h3>
                <p>Contas a pagar e receber, fluxo de caixa, movimentacoes e relatorios DRE completos.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7)">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3>Estoque</h3>
                <p>Controle de entradas e saidas, alertas de reposicao automaticos e valoracao de inventario.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#fa709a,#fee140)">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Clientes & Suporte</h3>
                <p>CRM integrado com historico de atendimento, chamados de suporte e base de conhecimento.</p>
            </div>
            <div class="card reveal">
                <div class="card-icon" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb)">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3>Relatorios PDF</h3>
                <p>Exporte relatorios detalhados com filtros avancados, graficos e layout profissional.</p>
            </div>
        </div>
    </section>

    <!-- PLANOS -->
    <section class="section reveal" id="planos">
        <span class="section-tag"><i class="fas fa-tags"></i> Planos</span>
        <h2 class="section-title">Escolha o plano ideal para sua <span>operacao</span></h2>
        <p class="section-sub">Implantacao rapida, ambiente seguro e suporte para escalar com previsibilidade.</p>

        <div class="plans-grid">
            <article class="plan-card popular reveal">
                <span class="popular-badge">MAIS POPULAR</span>
                <div class="plan-icon" style="background:linear-gradient(135deg,#4facfe,#667eea)">
                    <i class="fas fa-gem"></i>
                </div>
                <h3 class="plan-name">Profissional</h3>
                <div class="plan-price">Consulte</div>
                <div class="plan-period">por mes</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> 1 usuario adicional alem do admin padrao</li>
                    <li><i class="fas fa-check"></i> Gestao de pedidos, orcamentos e clientes</li>
                    <li><i class="fas fa-check"></i> Financeiro completo (DRE e fluxo de caixa)</li>
                    <li><i class="fas fa-check"></i> Relatorios PDF</li>
                    <li><i class="fas fa-check"></i> Controle de acesso por niveis</li>
                    <li class="is-off"><i class="fas fa-xmark"></i> Usuarios ilimitados</li>
                </ul>
                <a class="plan-cta" href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i> Solicitar proposta
                </a>
            </article>

            <article class="plan-card reveal">
                <div class="plan-icon" style="background:linear-gradient(135deg,#764ba2,#f093fb)">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="plan-name">Master</h3>
                <div class="plan-price">Consulte</div>
                <div class="plan-period">por mes</div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Usuarios ilimitados (sem restricao de quantidade)</li>
                    <li><i class="fas fa-check"></i> Tudo do plano Profissional</li>
                    <li><i class="fas fa-check"></i> Todos os modulos liberados</li>
                    <li><i class="fas fa-check"></i> Suporte prioritario</li>
                    <li><i class="fas fa-check"></i> Implantacao assistida</li>
                    <li><i class="fas fa-check"></i> Escalabilidade para operacoes maiores</li>
                </ul>
                <a class="plan-cta" href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i> Falar com consultor
                </a>
            </article>
        </div>
    </section>

    <!-- DEPOIMENTOS -->
    <section class="section reveal" id="depoimentos">
        <span class="section-tag"><i class="fas fa-comment-dots"></i> Depoimentos</span>
        <h2 class="section-title">Resultados reais de quem usa o <span><?= htmlspecialchars(EMPRESA_NOME) ?></span></h2>
        <p class="section-sub">Empresas de diferentes segmentos reduziram retrabalho e ganharam controle operacional.</p>

        <div class="testimonials-grid">
            <article class="testimonial-card reveal">
                <div class="testimonial-head">
                    <div class="t-avatar" style="background:linear-gradient(135deg,#667eea,#764ba2);">CM</div>
                    <div>
                        <div class="t-name">Carlos M.</div>
                        <div class="t-role">Gestor Comercial</div>
                    </div>
                </div>
                <div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p>"Depois que implantamos o sistema, o time comercial ganhou velocidade e o retrabalho caiu drasticamente."</p>
            </article>

            <article class="testimonial-card reveal">
                <div class="testimonial-head">
                    <div class="t-avatar" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">AP</div>
                    <div>
                        <div class="t-name">Ana Paula R.</div>
                        <div class="t-role">Diretora Financeira</div>
                    </div>
                </div>
                <div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p>"O controle de fluxo de caixa e os relatorios em PDF deixaram nossas analises muito mais rapidas e confiaveis."</p>
            </article>

            <article class="testimonial-card reveal">
                <div class="testimonial-head">
                    <div class="t-avatar" style="background:linear-gradient(135deg,#f093fb,#f5576c);">RS</div>
                    <div>
                        <div class="t-name">Roberto S.</div>
                        <div class="t-role">Dono da empresa</div>
                    </div>
                </div>
                <div class="t-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p>"Hoje eu acompanho tudo em um unico painel e tomo decisoes com mais seguranca, mesmo fora da empresa."</p>
            </article>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section reveal" id="faq">
        <span class="section-tag"><i class="fas fa-circle-question"></i> FAQ</span>
        <h2 class="section-title">Perguntas <span>frequentes</span></h2>
        <p class="section-sub">Respostas rapidas para as duvidas mais comuns antes da implantacao.</p>

        <div class="faq-list" id="faqList">
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>O sistema funciona em qualquer dispositivo?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Sim, e 100% web-based, acesse de qualquer navegador moderno.</p>
                </div>
            </article>
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>Preciso instalar algum programa?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Nao. Tudo funciona pelo navegador, sem instalacao.</p>
                </div>
            </article>
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>Meus dados ficam seguros?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Sim. Cada empresa tem seu proprio banco de dados isolado.</p>
                </div>
            </article>
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>Como e feita a cobranca?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Mensalidade ou anuidade com desconto. Consulte nossos planos.</p>
                </div>
            </article>
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>Tem limite de usuarios?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Sim. No Profissional voce tem 1 usuario adicional alem do admin padrao. No Master os usuarios sao ilimitados.</p>
                </div>
            </article>
            <article class="faq-item">
                <button class="faq-question" type="button">
                    <span>Como comeco a usar?</span>
                    <span class="faq-toggle">+</span>
                </button>
                <div class="faq-answer">
                    <p>Entre em contato pelo WhatsApp, configuramos tudo para voce.</p>
                </div>
            </article>
        </div>
    </section>

    <?php // TODO: Implementar envio de e-mail via PHPMailer ?>
    <section class="section reveal" id="contato">
        <span class="section-tag"><i class="fas fa-headset"></i> Contato</span>
        <h2 class="section-title">Fale com a <span>gente</span></h2>
        <p class="section-sub">Tire suas duvidas ou solicite uma demonstracao.</p>

        <div class="contact-grid">
            <div class="contact-info">
                <h3>Fale com a gente</h3>
                <p>Tire suas duvidas ou solicite uma demonstracao personalizada para sua operacao.</p>
                <div class="contact-methods">
                    <div class="contact-method">
                        <i class="fa-brands fa-whatsapp"></i>
                        <div>
                            <strong>WhatsApp</strong>
                            <a href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">+<?= htmlspecialchars(SUPORTE_WHATSAPP) ?></a>
                        </div>
                    </div>
                    <div class="contact-method">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <strong>E-mail</strong>
                            <a href="mailto:<?= htmlspecialchars(SUPORTE_EMAIL) ?>"><?= htmlspecialchars(SUPORTE_EMAIL) ?></a>
                        </div>
                    </div>
                    <div class="contact-method">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Horario</strong>
                            <span>Seg-Sex, 8h as 18h</span>
                        </div>
                    </div>
                </div>
                <a class="contact-wa-btn" href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa-brands fa-whatsapp"></i>
                    Falar agora no WhatsApp
                </a>
            </div>

            <div class="contact-form-wrap">
                <form id="contactForm" class="contact-form" action="<?= htmlspecialchars($publicBase) ?>/landing.php#contato" method="POST">
                    <input type="hidden" name="contact_form" value="1">
                    <div class="contact-field">
                        <label for="contactName">Nome completo</label>
                        <input id="contactName" class="contact-input" name="nome" type="text" required>
                    </div>
                    <div class="contact-field">
                        <label for="contactEmail">E-mail</label>
                        <input id="contactEmail" class="contact-input" name="email" type="email" required>
                    </div>
                    <div class="contact-field">
                        <label for="contactPhone">Telefone</label>
                        <input id="contactPhone" class="contact-input" name="telefone" type="tel" placeholder="(00) 00000-0000" required>
                    </div>
                    <div class="contact-field">
                        <label for="contactMessage">Mensagem</label>
                        <textarea id="contactMessage" class="contact-textarea" name="mensagem" required></textarea>
                    </div>
                    <button id="contactSubmit" class="contact-submit" type="submit">Enviar mensagem</button>
                    <div class="contact-success<?= ($contactFlash !== null && !empty($contactFlash['ok'])) ? ' show' : '' ?>" id="contactSuccess">
                        <?= ($contactFlash !== null && !empty($contactFlash['ok'])) ? htmlspecialchars((string)($contactFlash['message'] ?? 'Mensagem enviada! Entraremos em contato em breve.')) : 'Mensagem enviada! Entraremos em contato em breve.' ?>
                    </div>
                    <div class="contact-error<?= ($contactFlash !== null && empty($contactFlash['ok'])) ? ' show' : '' ?>" id="contactError">
                        <?= ($contactFlash !== null && empty($contactFlash['ok'])) ? htmlspecialchars((string)($contactFlash['message'] ?? 'Nao foi possivel enviar a mensagem agora.')) : 'Nao foi possivel enviar a mensagem agora.' ?>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- COMO FUNCIONA -->
    <div class="workflow" id="como-funciona">
        <div style="max-width:1000px;margin:0 auto;text-align:center;margin-bottom:3rem;" class="reveal">
            <span class="section-tag"><i class="fas fa-route"></i> Fluxo</span>
            <h2 class="section-title">Como <span>funciona</span></h2>
        </div>
        <div class="steps">
            <div class="step reveal">
                <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                <h4>Acesso Seguro</h4>
                <p>Login com e-mail e senha, controle de permissoes por perfil de usuario.</p>
            </div>
            <div class="step reveal">
                <div class="step-icon"><i class="fas fa-cogs"></i></div>
                <h4>Gestao Completa</h4>
                <p>Gerencie pedidos, orcamentos, estoque e financeiro de forma integrada.</p>
            </div>
            <div class="step reveal">
                <div class="step-icon"><i class="fas fa-file-pdf"></i></div>
                <h4>Relatorios</h4>
                <p>Exporte relatorios profissionais em PDF com um clique para apresentacoes.</p>
            </div>
            <div class="step reveal">
                <div class="step-icon"><i class="fas fa-chart-pie"></i></div>
                <h4>Decisoes Precisas</h4>
                <p>Dashboard com indicadores em tempo real para decisoes mais inteligentes.</p>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-banner reveal">
        <h2>Pronto para comecar?</h2>
        <p>Acesse agora e tenha controle total do seu negocio em uma unica plataforma.</p>
        <a href="#" class="btn-primary-hero js-open-tenant-login" data-open-tenant-login="1">
            <i class="fas fa-rocket"></i>
            Acessar o Sistema
            <i class="fas fa-arrow-right arrow-icon"></i>
        </a>
    </div>
    <div class="tenant-login-modal" id="tenantLoginModal" aria-hidden="true">
        <div class="tenant-login-dialog" role="dialog" aria-modal="true" aria-labelledby="tenantLoginTitle">
            <div class="tenant-login-header">
                <h3 class="tenant-login-title" id="tenantLoginTitle">Entrar com empresa</h3>
                <button type="button" class="tenant-close" data-close-tenant-login="1" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="tenant-login-sub">Digite o nome da empresa cadastrada na licenca para abrir a tela de login correta.</p>
            <form id="tenantLoginForm">
                <label class="tenant-field-label" for="tenantCompanyInput">Empresa</label>
                <input
                    id="tenantCompanyInput"
                    name="empresa"
                    type="text"
                    class="tenant-field-input"
                    placeholder="Ex.: Empresa Exemplo"
                    autocomplete="organization"
                    required
                >
                <div class="tenant-feedback" id="tenantLoginFeedback" aria-live="polite"></div>
                <div class="tenant-support">
                    <div class="tenant-support-head">
                        <?php if ($supportLogoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($supportLogoUrl) ?>" alt="Logo suporte" class="tenant-support-logo">
                        <?php endif; ?>
                        <span>Empresa nao cadastrada? Entre em contato com o suporte:</span>
                    </div>
                    <a class="tenant-support-link" href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fa-brands fa-whatsapp"></i>
                        Conversar no WhatsApp
                    </a>
                </div>
                <div class="tenant-actions">
                    <button type="submit" class="tenant-submit" id="tenantLoginSubmit">Continuar</button>
                </div>
            </form>
        </div>
    </div>
    <!-- FOOTER -->
    <footer>
        <div class="footer-grid">
            <div>
                <div class="footer-brand">
                    <?php if ($brandLogoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($brandLogoUrl) ?>" alt="Logo <?= htmlspecialchars(EMPRESA_NOME) ?>" class="footer-logo">
                    <?php else: ?>
                        <span class="brand-icon"><i class="fas fa-headset"></i></span>
                    <?php endif; ?>
                    <?= htmlspecialchars(EMPRESA_NOME) ?>
                </div>
                <p class="footer-text">Gestao integrada para empresas que querem mais controle, velocidade e decisao baseada em dados.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                </div>
            </div>
            <div>
                <h4 class="footer-title">Links rapidos</h4>
                <div class="footer-links">
                    <a href="#features">Modulos</a>
                    <a href="#como-funciona">Como funciona</a>
                    <a href="#planos">Planos</a>
                    <a href="#depoimentos">Depoimentos</a>
                    <a href="#faq">FAQ</a>
                    <a href="#contato">Contato</a>
                </div>
            </div>
            <div>
                <h4 class="footer-title">Contato rapido</h4>
                <div class="footer-contact">
                    <a href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer">WhatsApp: +<?= htmlspecialchars(SUPORTE_WHATSAPP) ?></a>
                    <a href="mailto:<?= htmlspecialchars(SUPORTE_EMAIL) ?>"><?= htmlspecialchars(SUPORTE_EMAIL) ?></a>
                    <span>Horario: Seg-Sex, 8h as 18h</span>
                </div>
            </div>
        </div>
        <div class="footer-bottom"><?= htmlspecialchars(EMPRESA_NOME) ?> &copy; <?= date('Y') ?> | Todos os direitos reservados</div>
    </footer>

</div><!-- /wrapper -->
<a class="wa-float" id="waFloatButton" href="https://wa.me/<?= rawurlencode(SUPORTE_WHATSAPP) ?>" target="_blank" rel="noopener noreferrer" aria-label="Falar com suporte no WhatsApp">
    <i class="fa-brands fa-whatsapp"></i>
</a>

<script>
/* ================================================================
   1. CANVAS PARTICLES
================================================================ */
(function () {
    const canvas = document.getElementById('canvas-bg');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [], mouse = { x: -9999, y: -9999 };
    const N = 90;

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });

    class Particle {
        constructor() { this.reset(true); }
        reset(init) {
            this.x  = init ? Math.random() * W : Math.random() < .5 ? 0 : W;
            this.y  = init ? Math.random() * H : Math.random() * H;
            this.vx = (Math.random() - .5) * .4;
            this.vy = (Math.random() - .5) * .4;
            this.r  = Math.random() * 1.8 + .4;
            this.a  = Math.random() * .5 + .15;
            this.hue = 200 + Math.random() * 80; // blue-purple range
        }
        update() {
            // mouse repulsion
            const dx = this.x - mouse.x, dy = this.y - mouse.y;
            const dist = Math.sqrt(dx*dx + dy*dy);
            if (dist < 100) {
                this.vx += dx / dist * .08;
                this.vy += dy / dist * .08;
            }
            this.x += this.vx;
            this.y += this.vy;
            // damping
            this.vx *= .99; this.vy *= .99;
            if (this.x < 0 || this.x > W || this.y < 0 || this.y > H) this.reset(false);
        }
        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.r, 0, Math.PI*2);
            ctx.fillStyle = `hsla(${this.hue},70%,70%,${this.a})`;
            ctx.fill();
        }
    }

    for (let i = 0; i < N; i++) particles.push(new Particle());

    function connect() {
        const maxDist = 120;
        for (let i = 0; i < particles.length; i++) {
            for (let j = i+1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const d  = Math.sqrt(dx*dx + dy*dy);
                if (d < maxDist) {
                    const alpha = (1 - d/maxDist) * .18;
                    ctx.beginPath();
                    ctx.strokeStyle = `rgba(102,126,234,${alpha})`;
                    ctx.lineWidth = .6;
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }
    }

    function loop() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => { p.update(); p.draw(); });
        connect();
        requestAnimationFrame(loop);
    }
    loop();
})();

/* ================================================================
   2. CURSOR GLOW
================================================================ */
(function () {
    const glow = document.getElementById('cursorGlow');
    let cx = -999, cy = -999, tx = -999, ty = -999;
    document.addEventListener('mousemove', e => { tx = e.clientX; ty = e.clientY; });
    function lerp(a, b, t) { return a + (b - a) * t; }
    function tick() {
        cx = lerp(cx, tx, .08);
        cy = lerp(cy, ty, .08);
        glow.style.left = cx + 'px';
        glow.style.top  = cy + 'px';
        requestAnimationFrame(tick);
    }
    tick();
})();

/* ================================================================
   3. SCROLL PROGRESS BAR
================================================================ */
window.addEventListener('scroll', function () {
    const s  = document.documentElement.scrollTop;
    const h  = document.documentElement.scrollHeight - window.innerHeight;
    document.getElementById('scroll-bar').style.width = (h > 0 ? (s / h * 100) : 0) + '%';
});

/* ================================================================
   4. TYPING ANIMATION
================================================================ */
(function () {
    const el    = document.getElementById('typed');
    const words = ['Completa', 'Inteligente', 'Simplificada', 'Eficiente', 'em Tempo Real'];
    let wi = 0, ci = 0, del = false;

    function tick() {
        const word = words[wi];
        if (del) { ci--; } else { ci++; }
        el.textContent = word.slice(0, ci);

        if (!del && ci === word.length) {
            del = true; setTimeout(tick, 2200); return;
        } else if (del && ci === 0) {
            del = false; wi = (wi + 1) % words.length; setTimeout(tick, 400); return;
        }
        setTimeout(tick, del ? 65 : 110);
    }
    setTimeout(tick, 900);
})();

/* ================================================================
   5. COUNTER ANIMATION (on scroll into view)
================================================================ */
(function () {
    const counters = document.querySelectorAll('.stat-num[data-target]');
    const ease = t => 1 - Math.pow(1 - t, 3);
    const observed = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const el     = e.target;
            const target = +el.dataset.target;
            const suffix = el.dataset.suffix || '';
            const dur    = 1800;
            const start  = Date.now();
            function update() {
                const p = Math.min((Date.now() - start) / dur, 1);
                el.textContent = Math.round(ease(p) * target) + suffix;
                if (p < 1) requestAnimationFrame(update);
            }
            update();
            observed.unobserve(el);
        });
    }, { threshold: .5 });
    counters.forEach(c => observed.observe(c));
})();

/* ================================================================
   6. SCROLL REVEAL
================================================================ */
(function () {
    const els = document.querySelectorAll('.reveal');
    const io  = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: .12 });
    els.forEach((el, i) => {
        el.style.transitionDelay = (i % 6) * 60 + 'ms';
        io.observe(el);
    });
})();

/* ================================================================
   7. 3D CARD TILT
================================================================ */
(function () {
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('mousemove', e => {
            card.classList.add('tilting');
            const r = card.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width  - .5;
            const y = (e.clientY - r.top)  / r.height - .5;
            card.style.transform = `perspective(700px) rotateY(${x*14}deg) rotateX(${-y*14}deg) translateY(-8px) scale(1.02)`;
        });
        card.addEventListener('mouseleave', () => {
            card.classList.remove('tilting');
            card.style.transform = '';
        });
    });
})();

/* ================================================================
   8. MOUSE PARALLAX for rings
================================================================ */
(function () {
    const rings = document.querySelectorAll('.ring');
    document.addEventListener('mousemove', e => {
        const cx = e.clientX / window.innerWidth  - .5;
        const cy = e.clientY / window.innerHeight - .5;
        rings.forEach((r, i) => {
            const f = (i + 1) * 12;
            r.style.transform = `translate(${cx*f}px, ${cy*f}px) scale(${1 + Math.abs(cx)*.03})`;
        });
    });
})();

/* ================================================================
   9. LOGIN POR EMPRESA
=============================================================== */
(function () {
    const modal    = document.getElementById('tenantLoginModal');
    const form     = document.getElementById('tenantLoginForm');
    const input    = document.getElementById('tenantCompanyInput');
    const feedback = document.getElementById('tenantLoginFeedback');
    const submit   = document.getElementById('tenantLoginSubmit');
    if (!modal || !form || !input || !feedback || !submit) return;

    const openers = document.querySelectorAll('.js-open-tenant-login');
    const closer  = modal.querySelector('[data-close-tenant-login]');
    const endpoint = `${window.location.pathname}?action=resolve_empresa`;

    function setFeedback(message, success) {
        feedback.textContent = message || '';
        feedback.classList.toggle('is-success', !!success);
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setFeedback('', false);
        setTimeout(() => input.focus(), 20);
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        setFeedback('', false);
    }

    openers.forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            openModal();
        });
    });

    if (closer) {
        closer.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', e => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const empresa = input.value.trim();
        if (!empresa) {
            setFeedback('Digite o nome da empresa.', false);
            input.focus();
            return;
        }

        const oldLabel = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Validando...';
        setFeedback('', false);

        try {
            const body = new URLSearchParams({ empresa });
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok || !payload.login_url) {
                setFeedback(payload.message || 'Empresa nao encontrada. Verifique o nome cadastrado.', false);
                return;
            }

            setFeedback('Empresa validada. Redirecionando...', true);
            window.location.href = payload.login_url;
        } catch (error) {
            setFeedback('Falha ao validar a empresa. Tente novamente.', false);
        } finally {
            submit.disabled = false;
            submit.textContent = oldLabel;
        }
    });
})();
</script>
<script>
/* ================================================================
   10. NAV SCROLLED + MOBILE DRAWER
=============================================================== */
(function () {
    const nav = document.getElementById('mainNav');
    const toggle = document.getElementById('mobileMenuToggle');
    const drawer = document.getElementById('mobileDrawer');
    const drawerClose = document.getElementById('mobileDrawerClose');
    const backdrop = document.getElementById('mobileDrawerBackdrop');

    if (nav) {
        const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 50);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    if (!toggle || !drawer || !backdrop) return;

    const setDrawer = (open) => {
        drawer.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        document.body.classList.toggle('drawer-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    };

    toggle.addEventListener('click', () => setDrawer(!drawer.classList.contains('open')));
    if (drawerClose) drawerClose.addEventListener('click', () => setDrawer(false));
    backdrop.addEventListener('click', () => setDrawer(false));
    drawer.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setDrawer(false)));

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            setDrawer(false);
        }
    });
})();

/* ================================================================
   11. FAQ ACCORDION
=============================================================== */
(function () {
    const list = document.getElementById('faqList');
    if (!list) return;

    const items = Array.from(list.querySelectorAll('.faq-item'));
    const closeAll = () => items.forEach(item => {
        item.classList.remove('is-open');
        const answer = item.querySelector('.faq-answer');
        if (answer) answer.style.maxHeight = '0px';
    });

    items.forEach(item => {
        const btn = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        if (!btn || !answer) return;

        btn.addEventListener('click', () => {
            const wasOpen = item.classList.contains('is-open');
            closeAll();
            if (!wasOpen) {
                item.classList.add('is-open');
                answer.style.maxHeight = Math.min(answer.scrollHeight, 400) + 'px';
            }
        });
    });
})();

/* ================================================================
   12. CONTATO: MASCARA + FEEDBACK VISUAL
=============================================================== */
(function () {
    const form = document.getElementById('contactForm');
    const phone = document.getElementById('contactPhone');
    const success = document.getElementById('contactSuccess');
    const error = document.getElementById('contactError');
    const submit = document.getElementById('contactSubmit');
    if (!form || !phone || !success || !submit) return;

    phone.addEventListener('input', () => {
        let value = phone.value.replace(/\D/g, '').slice(0, 11);
        if (value.length > 10) {
            value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        } else {
            value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        }
        phone.value = value.replace(/-$/, '');
    });

    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }

        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        success.classList.remove('show');
        if (error) error.classList.remove('show');
    });
})();

/* ================================================================
   13. WHATSAPP FLOAT
=============================================================== */
(function () {
    const btn = document.getElementById('waFloatButton');
    if (!btn) return;

    const onScroll = () => btn.classList.toggle('show', window.scrollY > 200);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
</body>
</html>



