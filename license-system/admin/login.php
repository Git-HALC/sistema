<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isAdminAuthenticated()) {
    header('Location: ' . adminUrl('admin/index.php'));
    exit;
}

$pdo = MasterDatabase::getInstance()->getConnection();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Token CSRF invalido.';
    } else {
        $email = filter_var((string)($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = (string)($_POST['senha'] ?? '');

        if (!$email || $senha === '') {
            $erro = 'Informe e-mail e senha validos.';
        } else {
            $stmt = $pdo->prepare('SELECT id, nome, email, senha, ativo FROM admins WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            $senhaOk = is_array($admin) && password_verify($senha, (string)$admin['senha']);
            $ativo = is_array($admin) && (bool)$admin['ativo'] === true;

            if ($senhaOk && $ativo) {
                $upd = $pdo->prepare('UPDATE admins SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = :id');
                $upd->execute([':id' => (int)$admin['id']]);
                loginAdmin($admin);
                header('Location: ' . adminUrl('admin/index.php'));
                exit;
            }

            $erro = 'Credenciais invalidas.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel de Licencas - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(adminUrl('admin/assets/css/style.css')); ?>">
</head>
<body class="admin-auth-body">
    <section class="auth-card">
        <div class="auth-logo"><i class="fa-solid fa-shield-halved"></i></div>
        <h1 class="auth-title">LicenseSystem Admin</h1>
        <p class="auth-sub">Acesse o painel master para gerenciar licencas e empresas.</p>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-error" style="margin-bottom:12px"><?php echo e($erro); ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="stack" data-loading-submit>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">

            <div class="float-field">
                <input id="email" name="email" type="email" required>
                <label for="email">E-mail</label>
            </div>

            <div class="float-field">
                <input id="senha" name="senha" type="password" required>
                <label for="senha">Senha</label>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <span class="btn-text"><i class="fa-solid fa-right-to-bracket"></i> Entrar no painel</span>
                <span class="spinner"></span>
            </button>
        </form>

        <a href="#" class="auth-link"><i class="fa-regular fa-circle-question" style="margin-right:6px"></i> Esqueci a senha</a>
    </section>

<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
</body>
</html>
