<?php
require_once __DIR__ . '/../../config/database.php';
session_start();

// Apenas admins (nivel 1)
if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    header('Location: /sistema_dm/public/login.php');
    exit();
}

$settingsPath = __DIR__ . '/../../config/site_settings.php';
$site_settings = [];
if (file_exists($settingsPath)) {
    $s = include $settingsPath;
    if (is_array($s)) $site_settings = $s;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menuHoverColor = trim($_POST['menu_hover_color'] ?? '');

    // Processar upload de logo
    $logoWebPath = $site_settings['logo'] ?? '/sistema_dm/public/assets/img/logo.png';
    if (!empty($_FILES['logo']['name']) && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            $message = 'Tipo de arquivo não permitido. Use PNG/JPG/SVG.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_logo.' . $ext;
            $dest = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $logoWebPath = '/sistema_dm/public/uploads/settings/' . $fileName;
            } else {
                $message = 'Erro ao mover o arquivo enviado.';
            }
        }
    }

    // Salvar configurações no arquivo PHP
    $newSettings = [
        'logo' => $logoWebPath,
        'menu_hover_color' => $menuHoverColor
    ];

    $export = var_export($newSettings, true);
    $php = "<?php\nreturn " . $export . ";\n";
    if (file_put_contents($settingsPath, $php) !== false) {
        $message = 'Configurações salvas com sucesso.';
        $site_settings = $newSettings;
    } else {
        $message = 'Erro ao salvar configurações.';
    }
}

$page_title = 'Configurações do Sistema';
include __DIR__ . '/../../public/includes/header.php';
?>

<div class="container">
    <div class="row mt-4">
        <div class="col-12">
            <h3>Configurações (Admin)</h3>
            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Logo atual</label>
                    <div>
                        <img src="<?php echo htmlspecialchars($site_settings['logo'] ?? '/sistema_dm/public/assets/img/logo.png'); ?>" alt="Logo" style="max-height:80px;" />
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enviar nova logo (PNG/JPG/SVG)</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>

                <div class="mb-3">
                    <label class="form-label">Cor do hover do menu (CSS)</label>
                    <input type="text" name="menu_hover_color" class="form-control" value="<?php echo htmlspecialchars($site_settings['menu_hover_color'] ?? ''); ?>" placeholder="#f6c23e ou rgba(0,0,0,0.05)">
                    <div class="form-text">Ex: <code>#f6c23e</code> ou <code>rgba(0,0,0,0.05)</code></div>
                </div>

                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../public/includes/footer.php'; ?>
