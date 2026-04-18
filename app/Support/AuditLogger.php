<?php

namespace App\Support;

use PDO;
use Throwable;

class AuditLogger
{
    private static bool $tabelaOk = false;

    public function __construct(private readonly PDO $pdo)
    {
        $this->garantirTabela();
    }

    public function registrar(
        string $modulo,
        string $acao,
        string $entidade,
        ?int $entidadeId = null,
        ?string $descricao = null,
        array $dados = []
    ): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $usuarioId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $usuarioNome = trim((string)($_SESSION['user_name'] ?? $_SESSION['user_nome'] ?? ''));
            if ($usuarioNome === '') {
                $usuarioNome = 'Usuário #' . (string)($usuarioId ?? 0);
            }

            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

            $sql = "
                INSERT INTO auditoria_usuarios
                    (usuario_id, usuario_nome, modulo, acao, entidade, entidade_id, descricao, dados, ip, user_agent)
                VALUES
                    (:usuario_id, :usuario_nome, :modulo, :acao, :entidade, :entidade_id, :descricao, :dados, :ip, :user_agent)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':usuario_nome' => $usuarioNome,
                ':modulo' => substr($modulo, 0, 60),
                ':acao' => substr($acao, 0, 40),
                ':entidade' => substr($entidade, 0, 60),
                ':entidade_id' => $entidadeId,
                ':descricao' => $descricao,
                ':dados' => !empty($dados) ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
                ':ip' => $ip,
                ':user_agent' => $userAgent,
            ]);
        } catch (Throwable $e) {
            error_log('AuditLogger registrar erro: ' . $e->getMessage());
        }
    }

    public function buscar(array $filtros = [], int $limite = 500): array
    {
        $this->garantirTabela();

        $conds = [];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $conds[] = 'usuario_id = :usuario_id';
            $params[':usuario_id'] = (int)$filtros['usuario_id'];
        }

        if (!empty($filtros['modulo'])) {
            $conds[] = 'modulo = :modulo';
            $params[':modulo'] = (string)$filtros['modulo'];
        }

        if (!empty($filtros['acao'])) {
            $conds[] = 'acao = :acao';
            $params[':acao'] = (string)$filtros['acao'];
        }

        if (!empty($filtros['data_inicio'])) {
            $conds[] = 'created_at >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filtros['data_fim'])) {
            $conds[] = 'created_at <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }

        if (!empty($filtros['busca'])) {
            $conds[] = '(usuario_nome ILIKE :busca OR descricao ILIKE :busca OR entidade ILIKE :busca)';
            $params[':busca'] = '%' . trim((string)$filtros['busca']) . '%';
        }

        $where = empty($conds) ? '' : 'WHERE ' . implode(' AND ', $conds);

        $sql = "
            SELECT
                id,
                usuario_id,
                usuario_nome,
                modulo,
                acao,
                entidade,
                entidade_id,
                descricao,
                dados,
                ip,
                created_at
            FROM auditoria_usuarios
            {$where}
            ORDER BY created_at DESC
            LIMIT :limite
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', max(1, min(2000, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarUsuarios(): array
    {
        $this->garantirTabela();

        $sql = "
            SELECT usuario_id AS id, MAX(usuario_nome) AS nome
            FROM auditoria_usuarios
            WHERE usuario_id IS NOT NULL
            GROUP BY usuario_id
            ORDER BY nome ASC
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function garantirTabela(): void
    {
        if (self::$tabelaOk) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS auditoria_usuarios (
                id SERIAL PRIMARY KEY,
                usuario_id INTEGER NULL,
                usuario_nome VARCHAR(150) NOT NULL,
                modulo VARCHAR(60) NOT NULL,
                acao VARCHAR(40) NOT NULL,
                entidade VARCHAR(60) NOT NULL,
                entidade_id INTEGER NULL,
                descricao TEXT NULL,
                dados JSONB NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            );

            CREATE INDEX IF NOT EXISTS idx_auditoria_created_at ON auditoria_usuarios(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_auditoria_usuario_id ON auditoria_usuarios(usuario_id);
            CREATE INDEX IF NOT EXISTS idx_auditoria_modulo_acao ON auditoria_usuarios(modulo, acao);
        ";

        try {
            $this->pdo->exec($sql);
            self::$tabelaOk = true;
        } catch (Throwable $e) {
            error_log('AuditLogger tabela erro: ' . $e->getMessage());
        }
    }
}
