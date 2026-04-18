<?php
declare(strict_types=1);

namespace App\Modules\Financeiro;

use PDO;

final class FluxoCaixaProjetadoController
{
    private FluxoCaixaProjetadoService $service;

    public function __construct(PDO $pdo)
    {
        $this->service = new FluxoCaixaProjetadoService(new FluxoCaixaProjetadoRepository($pdo));
        $this->assertLogado();
    }

    public function handleRequest(): void
    {
        $agrupamento = (string)($_GET['agrupamento'] ?? 'diario');
        if (!in_array($agrupamento, ['diario', 'semanal', 'mensal'], true)) {
            $agrupamento = 'diario';
        }

        $projecao = $this->service->projetar($agrupamento);

        $dados = [
            'page_title' => 'Fluxo de Caixa Projetado',
            'projecao' => $projecao,
            'agrupamento' => $agrupamento,
        ];

        extract($dados, EXTR_SKIP);
        require __DIR__ . '/../../../public/includes/header.php';
        require __DIR__ . '/../../views/financeiro/fluxo-caixa-projetado/index.php';
        require __DIR__ . '/../../../public/includes/footer.php';
    }

    private function assertLogado(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . tenantUrl('login.php'));
            exit();
        }
        if (!in_array((int)($_SESSION['user_role'] ?? 0), [1, 3], true)) {
            $_SESSION['mensagem'] = ['tipo' => 'error', 'texto' => 'Acesso restrito.'];
            header('Location: ' . tenantUrl('admin/dashboard.php'));
            exit();
        }
    }
}
