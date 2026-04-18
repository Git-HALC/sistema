<?php

namespace App\Modules\Usuarios;

use PDO;

/**
 * UsuarioRepository — acesso à tabela `usuarios`.
 *
 * Responsabilidades: SQL puro, sem validação de negócio.
 */
class UsuarioRepository
{
    private const TABLE = 'usuarios';
    public const DEFAULT_ADMIN_EMAIL = 'admin@suporte.com';

    public function __construct(private readonly PDO $pdo) {}

    // =========================================================================
    // Leitura
    // =========================================================================

    public function listar(int $pagina, int $porPagina): array
    {
        $offset = max(0, ($pagina - 1) * $porPagina);

        $stmt = $this->pdo->prepare("
            SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.nivel_acesso_id,
                u.ativo,
                u.ultimo_acesso,
                u.created_at,
                CASE
                    WHEN LOWER(u.email) = LOWER('" . self::DEFAULT_ADMIN_EMAIL . "')
                         AND u.nivel_acesso_id = 1
                    THEN TRUE
                    ELSE FALSE
                END AS admin_padrao,
                COALESCE(na.nome, 'Nível ' || u.nivel_acesso_id::text) AS nivel_nome,
                CASE
                    WHEN u.nivel_acesso_id = 4 THEN COALESCE((
                        SELECT string_agg(m.nome, ', ' ORDER BY m.ordem, m.id)
                        FROM permissoes_usuario pu
                        INNER JOIN modulos m ON m.id = pu.modulo_id
                        WHERE pu.usuario_id = u.id
                    ), '')
                    ELSE ''
                END AS modulos_personalizados
            FROM " . self::TABLE . " u
            LEFT JOIN niveis_acesso na ON na.id = u.nivel_acesso_id
            WHERE NOT (
                LOWER(u.email) = LOWER('" . self::DEFAULT_ADMIN_EMAIL . "')
                AND u.nivel_acesso_id = 1
            )
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalRegistros(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*)
               FROM " . self::TABLE . " u
              WHERE NOT (
                    LOWER(u.email) = LOWER('" . self::DEFAULT_ADMIN_EMAIL . "')
                    AND u.nivel_acesso_id = 1
              )"
        )->fetchColumn();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, email, telefone, nivel_acesso_id, ativo FROM " . self::TABLE . " WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function emailExiste(string $email, int $ignorarId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM " . self::TABLE . " WHERE email = :email AND id <> :id LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':id' => $ignorarId]);
        return (bool) $stmt->fetch();
    }

    public function listarNiveisAcesso(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome
            FROM niveis_acesso
            ORDER BY id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarModulos(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nome, icone, ordem
            FROM modulos
            ORDER BY ordem, id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarPermissoesPersonalizadas(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pode_operar_pdv, pode_conferir_caixa
            FROM permissoes_personalizadas
            WHERE usuario_id = :usuario_id
            LIMIT 1
        ");
        $stmt->execute([':usuario_id' => $usuarioId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'pode_operar_pdv' => false,
                'pode_conferir_caixa' => false,
            ];
        }

        return [
            'pode_operar_pdv' => (bool)($row['pode_operar_pdv'] ?? false),
            'pode_conferir_caixa' => (bool)($row['pode_conferir_caixa'] ?? false),
        ];
    }

    // =========================================================================
    // Escrita
    // =========================================================================

    public function criar(string $nome, string $email, string $senhaHash,
                          ?string $telefone, int $nivelAcessoId, bool $ativo): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . self::TABLE . " (nome, email, senha, telefone, nivel_acesso_id, ativo)
            VALUES (:nome, :email, :senha, :telefone, :nivel, :ativo)
            RETURNING id
        ");
        $stmt->execute([
            ':nome'     => $nome,
            ':email'    => $email,
            ':senha'    => $senhaHash,
            ':telefone' => $telefone,
            ':nivel'    => $nivelAcessoId,
            ':ativo'    => $ativo ? 1 : 0,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['id'] ?? 0);
    }

    public function atualizar(int $id, string $nome, string $email, ?string $senhaHash,
                               ?string $telefone, int $nivelAcessoId, bool $ativo): void
    {
        if ($senhaHash !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET nome = :nome, email = :email, senha = :senha, telefone = :telefone,
                    nivel_acesso_id = :nivel, ativo = :ativo
                WHERE id = :id
            ");
            $stmt->execute([
                ':nome'     => $nome,
                ':email'    => $email,
                ':senha'    => $senhaHash,
                ':telefone' => $telefone,
                ':nivel'    => $nivelAcessoId,
                ':ativo'    => $ativo ? 1 : 0,
                ':id'       => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE " . self::TABLE . "
                SET nome = :nome, email = :email, telefone = :telefone,
                    nivel_acesso_id = :nivel, ativo = :ativo
                WHERE id = :id
            ");
            $stmt->execute([
                ':nome'     => $nome,
                ':email'    => $email,
                ':telefone' => $telefone,
                ':nivel'    => $nivelAcessoId,
                ':ativo'    => $ativo ? 1 : 0,
                ':id'       => $id,
            ]);
        }
    }

    public function excluir(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function salvarPermissoesPdv(int $usuarioId, bool $podeOperarPdv, bool $podeConferirCaixa): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO permissoes_personalizadas (
                usuario_id,
                pode_operar_pdv,
                pode_conferir_caixa
            ) VALUES (
                :usuario_id,
                :pode_operar_pdv,
                :pode_conferir_caixa
            )
            ON CONFLICT (usuario_id) DO UPDATE
            SET pode_operar_pdv = EXCLUDED.pode_operar_pdv,
                pode_conferir_caixa = EXCLUDED.pode_conferir_caixa,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':pode_operar_pdv' => $podeOperarPdv,
            ':pode_conferir_caixa' => $podeConferirCaixa,
        ]);
    }

    public function removerPermissoesPdv(int $usuarioId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM permissoes_personalizadas WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $usuarioId]);
    }

    public function isAdminPadrao(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM " . self::TABLE . "
            WHERE id = :id
              AND nivel_acesso_id = 1
              AND LOWER(email) = LOWER(:email)
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id,
            ':email' => self::DEFAULT_ADMIN_EMAIL,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
