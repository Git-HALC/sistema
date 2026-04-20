<?php
declare(strict_types=1);

namespace App\Security;

use PDO;

final class PdvPermissao
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function podeAbrirCaixa(int $usuarioId): bool
    {
        return $this->podeOperarPdv($usuarioId);
    }

    public function podeLancarNoCaixa(int $usuarioId): bool
    {
        return $this->podeOperarPdv($usuarioId);
    }

    public function podeFecharCaixa(int $usuarioId, int $caixaId): bool
    {
        $nivel = $this->getNivelAcessoId($usuarioId);
        if ($nivel === 1) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM pdv_caixas
              WHERE id = :id
                AND usuario_abertura_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute([
            ':id' => $caixaId,
            ':usuario_id' => $usuarioId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function podeConferirCaixas(int $usuarioId): bool
    {
        $nivel = $this->getNivelAcessoId($usuarioId);

        return match ($nivel) {
            1, 3 => true,
            2 => false,
            4 => $this->getFlagsPersonalizadas($usuarioId)['pode_conferir_caixa'],
            default => false,
        };
    }

    public function podeAcessarRelatorio(int $usuarioId, int $caixaId): bool
    {
        if ($this->podeConferirCaixas($usuarioId)) {
            return true;
        }

        // Operador pode ver o relatório do proprio caixa, aberto ou fechado.
        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM pdv_caixas
              WHERE id = :id
                AND usuario_abertura_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute([
            ':id' => $caixaId,
            ':usuario_id' => $usuarioId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function temCaixaAberto(int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
               FROM pdv_caixas
              WHERE usuario_abertura_id = :usuario_id
                AND status = :status
              LIMIT 1'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':status' => 'aberto',
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function getCaixaAbertoDoUsuario(int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, u.nome AS operador_nome
               FROM pdv_caixas c
               INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
              WHERE c.usuario_abertura_id = :usuario_id
                AND c.status = :status
              ORDER BY c.data_abertura DESC, c.id DESC
              LIMIT 1'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':status' => 'aberto',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getCaixasAbertos(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT c.*, u.nome AS operador_nome
              FROM pdv_caixas c
              INNER JOIN usuarios u ON u.id = c.usuario_abertura_id
             WHERE c.status = 'aberto'
             ORDER BY c.data_abertura ASC, c.id ASC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function usuarioAtualId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    public function usuarioAtualPodeOperarPdv(): bool
    {
        $usuarioId = $this->usuarioAtualId();
        return $usuarioId > 0 && $this->podeOperarPdv($usuarioId);
    }

    public function usuarioAtualPodeConferirCaixas(): bool
    {
        $usuarioId = $this->usuarioAtualId();
        return $usuarioId > 0 && $this->podeConferirCaixas($usuarioId);
    }

    public function getFlagsPersonalizadas(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pode_operar_pdv, pode_conferir_caixa
               FROM permissoes_personalizadas
              WHERE usuario_id = :usuario_id
              LIMIT 1'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'pode_operar_pdv' => false,
                'pode_conferir_caixa' => false,
            ];
        }

        return [
            'pode_operar_pdv' => (bool) ($row['pode_operar_pdv'] ?? false),
            'pode_conferir_caixa' => (bool) ($row['pode_conferir_caixa'] ?? false),
        ];
    }

    private function podeOperarPdv(int $usuarioId): bool
    {
        $nivel = $this->getNivelAcessoId($usuarioId);

        return match ($nivel) {
            1 => true,
            2, 3 => false,
            4 => $this->getFlagsPersonalizadas($usuarioId)['pode_operar_pdv'],
            default => false,
        };
    }

    private function getNivelAcessoId(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT nivel_acesso_id
               FROM usuarios
              WHERE id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $usuarioId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}
