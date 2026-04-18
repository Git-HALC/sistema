<?php
// Configuracao de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/../logs/error.log');

require_once __DIR__ . '/../config/tenant.php';
tenantBootstrap();
tenantEnsureSession();
require_once '../config/database.php';
require_once __DIR__ . '/../app/Support/PermissionManager.php';

use App\Support\LicenseValidator;

function redirectWithError($message): void
{
    $_SESSION['error_message'] = $message;
    header('Location: ' . tenantUrl('login.php?error=1'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Tentativa de acesso direto ao auth.php');
    header('Location: ' . tenantUrl('login.php'));
    exit();
}

error_log('Tentativa de login - IP: ' . $_SERVER['REMOTE_ADDR'] . ', Email: ' . ($_POST['email'] ?? 'nao informado'));

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    error_log('Campos de email ou senha vazios');
    redirectWithError('Por favor, preencha todos os campos.');
}

try {
    $query = 'SELECT id, nome, email, senha, nivel_acesso_id FROM usuarios WHERE email = :email LIMIT 1';
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();

        error_log('Usuario encontrado: ' . json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'nivel_acesso' => $user['nivel_acesso_id'],
        ]));

        if (password_verify($senha, $user['senha'])) {
            error_log('Login valido para o usuario ID: ' . $user['id']);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = (int)$user['nivel_acesso_id'];

            require_once __DIR__ . '/../config/license.php';

            $validator = new LicenseValidator($db, LICENSE_API_URL);
            $result = $validator->checkLocal();

            if (!$result['valida']) {
                $_SESSION = [];
                session_destroy();
                header('Location: ' . tenantUrl('login.php?erro=licenca_vencida'));
                exit();
            }

            $role = (int)($_SESSION['user_role'] ?? 0);
            if ($role <= 0) {
                // Evita loop para sessao inconsistente/sem nivel.
                $_SESSION = [];
                session_destroy();
                header('Location: ' . tenantUrl('login.php?error=access_denied'));
                exit();
            }

            $permissionManager = new \App\Support\PermissionManager(
                $db,
                (int) $_SESSION['user_id'],
                $role
            );

            header('Location: ' . $permissionManager->getRedirectUrl());
            exit();
        }
    }

    error_log('Falha na autenticacao - Credenciais invalidas para o email: ' . $email);
    redirectWithError('Credenciais invalidas. Por favor, tente novamente.');

} catch (PDOException $e) {
    error_log('Erro de banco de dados: ' . $e->getMessage());
    error_log('SQL: ' . ($query ?? 'nao definido'));
    error_log('Email: ' . ($email ?? 'nao definido'));

    redirectWithError('Ocorreu um erro ao processar sua solicitacao. Por favor, tente novamente mais tarde.');
}

function logLoginAttempt($email, $success, $ip = null, $userId = null): void
{
    $logMessage = sprintf(
        "[%s] %s: %s - IP: %s, UserID: %s\n",
        date('Y-m-d H:i:s'),
        $success ? 'SUCESSO' : 'FALHA',
        $email,
        $ip ?: $_SERVER['REMOTE_ADDR'],
        $userId ?: 'N/A'
    );

    file_put_contents(
        dirname(__DIR__) . '/../logs/auth.log',
        $logMessage,
        FILE_APPEND
    );
}
