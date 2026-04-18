<?php

namespace App\Support;

use DateTimeImmutable;
use PDO;
use Throwable;

class LicenseValidator
{
    private PDO $pdo;
    private string $apiUrl;
    private ?array $cached = null;
    private ?array $cachedPlan = null;

    public function __construct(PDO $pdo, string $apiUrl)
    {
        $this->pdo = $pdo;
        $this->apiUrl = trim($apiUrl);
    }

    // Verifica localmente na tabela licenca do banco do cliente
    // Retorna array: ['valida'=>bool, 'dias_restantes'=>int, 'status'=>string, 'licenca_fim'=>string, 'dias_aviso'=>int]
    public function checkLocal(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        try {
            $row = $this->fetchLicenseRow();
            if ($row === null) {
                return $this->cached = [
                    'valida' => false,
                    'dias_restantes' => 0,
                    'status' => 'nao_configurada',
                    'licenca_fim' => '',
                    'dias_aviso' => 7,
                ];
            }

            $licencaFim = $this->normalizeDate((string)($row['licenca_fim'] ?? ''));
            $diasRestantes = $this->calculateDaysRemaining($licencaFim);
            $diasAviso = max(0, (int)($row['dias_aviso'] ?? 7));
            $status = $this->normalizeStatus((string)($row['status'] ?? ''));

            if ($this->isDateExpired($licencaFim) && in_array($status, ['ativa', 'trial'], true)) {
                $status = 'bloqueada';
            }

            $update = $this->pdo->prepare(
                'UPDATE licenca SET dias_restantes = :dias_restantes, status = :status WHERE id = :id'
            );
            $update->execute([
                ':dias_restantes' => $diasRestantes,
                ':status' => $status,
                ':id' => (int)$row['id'],
            ]);

            $valida = in_array($status, ['ativa', 'trial'], true) && !$this->isDateExpired($licencaFim);

            return $this->cached = [
                'valida' => $valida,
                'dias_restantes' => $diasRestantes,
                'status' => $status,
                'licenca_fim' => $licencaFim,
                'dias_aviso' => $diasAviso,
            ];
        } catch (Throwable $e) {
            error_log('LicenseValidator::checkLocal erro: ' . $e->getMessage());

            return $this->cached = [
                'valida' => false,
                'dias_restantes' => 0,
                'status' => 'erro_verificacao',
                'licenca_fim' => '',
                'dias_aviso' => 7,
            ];
        }
    }

    // Sincroniza com o servidor master 1x por dia
    // So executa se ultimo_check < CURRENT_DATE
    public function syncWithMaster(): void
    {
        if ($this->apiUrl === '') {
            return;
        }

        try {
            $row = $this->fetchLicenseRow();
            if ($row === null) {
                return;
            }

            if (!$this->shouldSync((string)($row['ultimo_check'] ?? ''))) {
                return;
            }

            $databaseName = $this->resolveCurrentDatabaseName();
            if ($databaseName === '') {
                return;
            }

            $payload = [
                'chave_licenca' => (string)($row['chave_licenca'] ?? ''),
                'banco_dados' => $databaseName,
            ];

            $remote = $this->callMasterApi($payload);
            if (!is_array($remote)) {
                $this->touchLastCheck((int)$row['id']);
                return;
            }

            $licencaFim = $this->normalizeDate((string)($remote['licenca_fim'] ?? $row['licenca_fim'] ?? ''));
            if ($licencaFim === '') {
                $licencaFim = $this->normalizeDate((string)($row['licenca_fim'] ?? ''));
            }

            $status = $this->normalizeStatus((string)($remote['status'] ?? $row['status'] ?? 'bloqueada'));
            if ($this->isDateExpired($licencaFim) && in_array($status, ['ativa', 'trial'], true)) {
                $status = 'bloqueada';
            }

            $diasAviso = isset($remote['dias_aviso'])
                ? max(0, (int)$remote['dias_aviso'])
                : max(0, (int)($row['dias_aviso'] ?? 7));

            $diasRestantes = $this->calculateDaysRemaining($licencaFim);

            $update = $this->pdo->prepare(
                'UPDATE licenca
                    SET licenca_fim = :licenca_fim,
                        status = :status,
                        dias_aviso = :dias_aviso,
                        dias_restantes = :dias_restantes,
                        ultimo_check = CURRENT_TIMESTAMP
                  WHERE id = :id'
            );

            $update->execute([
                ':licenca_fim' => $licencaFim,
                ':status' => $status,
                ':dias_aviso' => $diasAviso,
                ':dias_restantes' => $diasRestantes,
                ':id' => (int)$row['id'],
            ]);

            // Sincroniza dados de plano no mesmo ciclo diario de sincronizacao.
            $this->syncPlanoWithMaster($remote, (int)$row['id']);

            $this->cached = null;
            $this->cachedPlan = null;
        } catch (Throwable $e) {
            error_log('LicenseValidator::syncWithMaster erro: ' . $e->getMessage());
        }
    }

    /**
     * Retorna informacoes do plano atual.
     *
     * @return array{
     *   plano_slug: string,
     *   max_usuarios: int|null,
     *   pode_criar: bool,
     *   total_ativos: int,
     *   mensagem: string
     * }
     */
    public function getPlanInfo(): array
    {
        if ($this->cachedPlan !== null) {
            return $this->cachedPlan;
        }

        $default = [
            'plano_slug' => 'profissional',
            'max_usuarios' => 1,
            'pode_criar' => true,
            'total_ativos' => 0,
            'mensagem' => 'Plano Profissional: 0 de 1 usuarios adicionais utilizados',
        ];

        try {
            $stmt = $this->pdo->query('SELECT * FROM vw_status_plano LIMIT 1');
            if ($stmt === false) {
                return $this->cachedPlan = $default;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $this->cachedPlan = $default;
            }

            $planoSlug = strtolower(trim((string)($row['plano_slug'] ?? 'profissional')));
            if ($planoSlug === '') {
                $planoSlug = 'profissional';
            }

            $maxUsuarios = array_key_exists('max_usuarios', $row) && $row['max_usuarios'] !== null
                ? (int)$row['max_usuarios']
                : null;

            $totalAtivos = $this->countActiveAdditionalUsers();
            $podeCriar = $maxUsuarios === null
                ? true
                : $totalAtivos < $maxUsuarios;

            if ($maxUsuarios === null) {
                $mensagem = 'Plano Master: usuarios ilimitados';
            } elseif ($podeCriar) {
                $mensagem = sprintf(
                    'Plano Profissional: %d de %d usuarios adicionais utilizados',
                    $totalAtivos,
                    $maxUsuarios
                );
            } else {
                $mensagem = sprintf(
                    'Limite atingido: plano Profissional permite apenas %d usuarios adicionais (alem do admin padrao). Entre em contato para upgrade para o plano Master.',
                    $maxUsuarios
                );
            }

            return $this->cachedPlan = [
                'plano_slug' => $planoSlug,
                'max_usuarios' => $maxUsuarios,
                'pode_criar' => $podeCriar,
                'total_ativos' => $totalAtivos,
                'mensagem' => $mensagem,
            ];
        } catch (Throwable $e) {
            error_log('LicenseValidator::getPlanInfo erro: ' . $e->getMessage());
            return $this->cachedPlan = $default;
        }
    }

    /**
     * Sincroniza dados de plano com o master.
     * Se $remoteData for informado, evita nova chamada HTTP.
     */
    public function syncPlanoWithMaster(?array $remoteData = null, ?int $licenseId = null): void
    {
        try {
            $row = null;
            if ($remoteData === null || $licenseId === null) {
                if ($this->apiUrl === '') {
                    return;
                }

                $row = $this->fetchLicenseRow();
                if ($row === null) {
                    return;
                }

                if (!$this->shouldSync((string)($row['ultimo_check'] ?? ''))) {
                    return;
                }

                $databaseName = $this->resolveCurrentDatabaseName();
                if ($databaseName === '') {
                    return;
                }

                $payload = [
                    'chave_licenca' => (string)($row['chave_licenca'] ?? ''),
                    'banco_dados' => $databaseName,
                ];

                $remoteData = $this->callMasterApi($payload);
                if (!is_array($remoteData)) {
                    return;
                }

                $licenseId = (int)$row['id'];
            }

            if (!is_array($remoteData) || !array_key_exists('plano_slug', $remoteData) || !array_key_exists('max_usuarios', $remoteData)) {
                return;
            }

            $planoSlug = strtolower(trim((string)$remoteData['plano_slug']));
            if ($planoSlug === '') {
                return;
            }

            $maxUsuarios = $remoteData['max_usuarios'];
            if ($maxUsuarios !== null) {
                $maxUsuarios = (int)$maxUsuarios;
            }

            $update = $this->pdo->prepare(
                'UPDATE licenca
                    SET plano_slug = :plano_slug,
                        max_usuarios = :max_usuarios
                  WHERE TRUE'
            );
            $update->bindValue(':plano_slug', $planoSlug, PDO::PARAM_STR);
            if ($maxUsuarios === null) {
                $update->bindValue(':max_usuarios', null, PDO::PARAM_NULL);
            } else {
                $update->bindValue(':max_usuarios', $maxUsuarios, PDO::PARAM_INT);
            }
            $update->execute();

            $this->enforceProfessionalUserLimit($planoSlug, $maxUsuarios);

            $this->cachedPlan = null;
        } catch (Throwable $e) {
            error_log('LicenseValidator::syncPlanoWithMaster erro: ' . $e->getMessage());
        }
    }

    public function isExpired(): bool
    {
        return !$this->checkLocal()['valida'];
    }

    public function getDaysRemaining(): int
    {
        return (int)$this->checkLocal()['dias_restantes'];
    }

    public function getStatus(): string
    {
        return (string)$this->checkLocal()['status'];
    }

    // dias_restantes <= dias_aviso
    public function isNearExpiry(): bool
    {
        $result = $this->checkLocal();
        return $result['valida'] && ((int)$result['dias_restantes'] <= (int)$result['dias_aviso']);
    }

    private function fetchLicenseRow(): ?array
    {
        $stmt = $this->pdo->query(
            'SELECT id, chave_licenca, licenca_fim, dias_restantes, dias_aviso, status, ultimo_check
               FROM licenca
              ORDER BY id ASC
              LIMIT 1'
        );

        if ($stmt === false) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function countActiveAdditionalUsers(): int
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*)
                   FROM usuarios
                  WHERE ativo = TRUE
                    AND NOT (
                        LOWER(email) = LOWER('admin@suporte.com')
                        AND nivel_acesso_id = 1
                    )"
            );
            if ($stmt === false) {
                return 0;
            }

            return max(0, (int)$stmt->fetchColumn());
        } catch (Throwable) {
            return 0;
        }
    }

    private function enforceProfessionalUserLimit(string $planoSlug, ?int $maxUsuarios): void
    {
        $slug = strtolower(trim($planoSlug));
        if ($slug !== 'profissional') {
            return;
        }

        if ($maxUsuarios === null) {
            return;
        }

        $limite = max(0, $maxUsuarios);

        $sql = "
            WITH ordenados AS (
                SELECT
                    u.id,
                    ROW_NUMBER() OVER (
                        ORDER BY
                            CASE u.nivel_acesso_id
                                WHEN 1 THEN 1
                                WHEN 2 THEN 2
                                WHEN 3 THEN 3
                                WHEN 4 THEN 4
                                ELSE 5
                            END ASC,
                            u.created_at ASC NULLS LAST,
                            u.id ASC
                    ) AS rn
                FROM usuarios u
                WHERE u.ativo = TRUE
                  AND NOT (
                      LOWER(u.email) = LOWER('admin@suporte.com')
                      AND u.nivel_acesso_id = 1
                  )
            ),
            desativar AS (
                SELECT id
                FROM ordenados
                WHERE rn > :limite
            )
            UPDATE usuarios u
               SET ativo = FALSE
              FROM desativar d
             WHERE u.id = d.id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function touchLastCheck(int $id): void
    {
        $update = $this->pdo->prepare('UPDATE licenca SET ultimo_check = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([':id' => $id]);
        $this->cached = null;
        $this->cachedPlan = null;
    }

    private function shouldSync(string $ultimoCheck): bool
    {
        if (trim($ultimoCheck) === '') {
            return true;
        }

        try {
            $lastCheck = new DateTimeImmutable($ultimoCheck);
        } catch (Throwable) {
            return true;
        }

        $today = new DateTimeImmutable('today');
        return $lastCheck->format('Y-m-d') < $today->format('Y-m-d');
    }

    private function callMasterApi(array $payload): ?array
    {
        $response = $this->requestByCurl($payload);
        if (is_array($response)) {
            return $response;
        }

        $response = $this->requestByFileGetContents($payload);
        if (is_array($response)) {
            return $response;
        }

        return $this->requestByGet($payload);
    }

    private function requestByCurl(array $payload): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $httpCode >= 400) {
            return null;
        }

        return $this->decodeJson($raw);
    }

    private function requestByFileGetContents(array $payload): ?array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 8,
            ],
        ]);

        $raw = @file_get_contents($this->apiUrl, false, $context);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return $this->decodeJson($raw);
    }

    private function requestByGet(array $payload): ?array
    {
        $separator = str_contains($this->apiUrl, '?') ? '&' : '?';
        $url = $this->apiUrl . $separator . http_build_query([
            'chave_licenca' => (string)($payload['chave_licenca'] ?? ''),
            'banco_dados' => (string)($payload['banco_dados'] ?? ''),
        ]);

        $raw = @file_get_contents($url);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return $this->decodeJson($raw);
    }

    private function decodeJson(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function calculateDaysRemaining(string $licencaFim): int
    {
        if ($licencaFim === '') {
            return 0;
        }

        try {
            $today = new DateTimeImmutable('today');
            $endDate = new DateTimeImmutable($licencaFim);
            $days = (int)$today->diff($endDate)->format('%r%a');
            return max(0, $days);
        } catch (Throwable) {
            return 0;
        }
    }

    private function isDateExpired(string $licencaFim): bool
    {
        if ($licencaFim === '') {
            return true;
        }

        try {
            $today = new DateTimeImmutable('today');
            $endDate = new DateTimeImmutable($licencaFim);
            return $endDate < $today;
        } catch (Throwable) {
            return true;
        }
    }

    private function normalizeDate(string $date): string
    {
        if (trim($date) === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($date))->format('Y-m-d');
        } catch (Throwable) {
            return '';
        }
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $allowed = ['ativa', 'bloqueada', 'trial', 'cancelada'];
        if (in_array($status, $allowed, true)) {
            return $status;
        }

        return 'bloqueada';
    }

    private function resolveCurrentDatabaseName(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT current_database()');
            if ($stmt === false) {
                return '';
            }

            $name = $stmt->fetchColumn();
            return is_string($name) ? trim($name) : '';
        } catch (Throwable) {
            return '';
        }
    }
}
