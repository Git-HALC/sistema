<?php
declare(strict_types=1);

namespace App\Support;

class PermissionManager
{
    private \PDO $pdo;
    private int $userId;
    private int $nivelId;
    private array $modulos = [];

    public function __construct(\PDO $pdo, int $userId, int $nivelId)
    {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->nivelId = $nivelId;
        $this->load();
    }

    private function load(): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT modulo_slug FROM vw_permissoes_usuario WHERE usuario_id = :uid'
        );
        $stmt->execute([':uid' => $this->userId]);
        $this->modulos = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    public function can(string $slug): bool
    {
        if ($this->nivelId === 1) {
            return true;
        }

        return in_array($slug, $this->modulos, true);
    }

    public function require(string $slug, bool $jsonResponse = false): void
    {
        if ($this->can($slug)) {
            return;
        }

        if ($jsonResponse || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Acesso negado.', 'code' => 403], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $this->getRedirectUrl('erro=sem_permissao'));
        exit;
    }

    public function getRedirectUrl(string $query = ''): string
    {
        $destino = $this->getRedirectPath();
        if ($query !== '') {
            $separador = str_contains($destino, '?') ? '&' : '?';
            $destino .= $separador . ltrim($query, '?&');
        }

        return function_exists('tenantUrl') ? tenantUrl($destino) : '/public/' . ltrim($destino, '/');
    }

    public function getRedirectPath(): string
    {
        if ($this->can('dashboard')) {
            return 'admin/dashboard.php';
        }

        foreach ($this->getOrderedModulos() as $slug) {
            $path = self::pathForModuloSlug($slug);
            if ($path !== null) {
                return $path;
            }
        }

        return 'login.php?error=access_denied';
    }

    public function getModulos(): array
    {
        if ($this->nivelId === 1) {
            $stmt = $this->pdo->query('SELECT slug FROM modulos ORDER BY ordem');
            return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        }

        return $this->modulos;
    }

    private function getOrderedModulos(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.slug
               FROM vw_permissoes_usuario v
               INNER JOIN modulos m ON m.slug = v.modulo_slug
              WHERE v.usuario_id = :uid
              GROUP BY m.slug, m.ordem, m.id
              ORDER BY m.ordem ASC, m.id ASC'
        );
        $stmt->execute([':uid' => $this->userId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    private static function pathForModuloSlug(string $slug): ?string
    {
        return match ($slug) {
            'dashboard' => 'admin/dashboard.php',
            'clientes' => 'admin/clientes.php',
            'produtos' => 'admin/produtos.php',
            'orcamentos' => 'admin/orcamentos.php',
            'pedidos' => 'admin/pedidos.php?action=kanban',
            'servicos' => 'admin/servicos.php?action=kanban',
            'fiscal' => 'admin/fiscal.php?action=listar',
            'financeiro' => 'admin/financeiro/dashboard.php',
            'rel_financeiro' => 'admin/relatorios/financeiros/relatorio_despesas_receitas.php',
            'rel_pedidos' => 'admin/relatorios/pedidos/relatorio_pedidos.php',
            default => null,
        };
    }

    public function getAllModulosComStatus(int $targetUserId): array
    {
        $stmt = $this->pdo->query('SELECT id, slug, nome, icone, ordem FROM modulos ORDER BY ordem');
        $todos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $stmt2 = $this->pdo->prepare(
            'SELECT modulo_id FROM permissoes_usuario WHERE usuario_id = :uid'
        );
        $stmt2->execute([':uid' => $targetUserId]);
        $ativos = array_map('intval', $stmt2->fetchAll(\PDO::FETCH_COLUMN) ?: []);

        foreach ($todos as &$m) {
            $moduloId = (int)($m['id'] ?? 0);
            $m['ativo'] = in_array($moduloId, $ativos, true);
        }
        unset($m);

        return $todos;
    }

    public function salvarPermissoesPersonalizado(int $targetUserId, array $moduloIds): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM permissoes_usuario WHERE usuario_id = :uid');
            $del->execute([':uid' => $targetUserId]);

            if (!empty($moduloIds)) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO permissoes_usuario (usuario_id, modulo_id)
                     VALUES (:uid, :mid) ON CONFLICT DO NOTHING'
                );

                foreach ($moduloIds as $mid) {
                    $moduloId = (int)$mid;
                    if ($moduloId <= 0) {
                        continue;
                    }
                    $ins->execute([':uid' => $targetUserId, ':mid' => $moduloId]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
