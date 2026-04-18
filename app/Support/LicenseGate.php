<?php

namespace App\Support;

use PDO;

class LicenseGate
{
    // Metodo estatico chamado uma unica vez no header
    public static function check(PDO $pdo): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Lista de paginas excluidas do bloqueio
        $excluded = ['login.php', 'auth.php', 'logout.php', 'landing.php', 'orcamento_pdf_publico.php', 'blocked.php'];
        $currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
        if (in_array($currentPage, $excluded, true)) {
            return;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (str_contains($requestUri, '/install/')) {
            return;
        }

        $licenseConfigPath = __DIR__ . '/../../config/license.php';
        if (is_file($licenseConfigPath)) {
            require_once $licenseConfigPath;
        }

        // Le configuracao do .env/config
        $apiUrl = defined('LICENSE_API_URL') ? (string) LICENSE_API_URL : '';

        $validator = new LicenseValidator($pdo, $apiUrl);
        $result = $validator->checkLocal();
        $validator->syncWithMaster(); // so executa 1x por dia internamente
        $result = $validator->checkLocal();

        if (!$result['valida']) {
            $_SESSION['license_block'] = [
                'status' => (string)$result['status'],
                'licenca_fim' => (string)$result['licenca_fim'],
                'dias_restantes' => (int)$result['dias_restantes'],
            ];

            $blockedPath = function_exists('tenantPath')
                ? \tenantPath('/sistema_dm/public/blocked.php')
                : '/sistema_dm/public/blocked.php';
            header('Location: ' . $blockedPath);
            exit;
        }

        if ((int)$result['dias_restantes'] <= (int)$result['dias_aviso']) {
            $_SESSION['license_warning'] = (int)$result['dias_restantes'];
        } else {
            unset($_SESSION['license_warning']);
        }
    }

    /**
     * @return array{
     *   plano_slug: string,
     *   max_usuarios: int|null,
     *   pode_criar: bool,
     *   total_ativos: int,
     *   mensagem: string
     * }
     */
    public static function getPlanInfo(): array
    {
        $fallback = [
            'plano_slug' => 'profissional',
            'max_usuarios' => 1,
            'pode_criar' => true,
            'total_ativos' => 0,
            'mensagem' => 'Plano Profissional: 0 de 1 usuarios adicionais utilizados',
        ];

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $licenseConfigPath = __DIR__ . '/../../config/license.php';
            if (is_file($licenseConfigPath)) {
                require_once $licenseConfigPath;
            }

            $apiUrl = defined('LICENSE_API_URL') ? (string) LICENSE_API_URL : '';

            $pdo = null;
            if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
                $pdo = $GLOBALS['db'];
            } elseif (class_exists('Database')) {
                $candidate = \Database::getInstance()->getConnection();
                if ($candidate instanceof PDO) {
                    $pdo = $candidate;
                }
            }

            if (!$pdo instanceof PDO) {
                return $fallback;
            }

            $validator = new LicenseValidator($pdo, $apiUrl);
            return $validator->getPlanInfo();
        } catch (\Throwable $e) {
            error_log('LicenseGate::getPlanInfo erro: ' . $e->getMessage());
            return $fallback;
        }
    }
}
