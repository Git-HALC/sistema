<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errorMessage = $_SESSION['error_message'] ?? 'Erro de conexao com o banco de dados.';
unset($_SESSION['error_message']);
$loginUrl = function_exists('tenantUrl') ? tenantUrl('login.php') : '/login.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; color: #1f2937; margin: 0; }
        .wrap { max-width: 640px; margin: 10vh auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; }
        h1 { margin-top: 0; font-size: 1.4rem; }
        p { line-height: 1.5; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Nao foi possivel carregar o sistema</h1>
        <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Voltar para o login</a></p>
    </div>
</body>
</html>
