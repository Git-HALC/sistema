<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function registrarValidacao(
    PDO $pdo,
    ?int $empresaId,
    string $chaveLicenca,
    string $ipAddress,
    string $resultado,
    ?int $diasRestantes
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO validacoes_log (empresa_id, chave_licenca, ip_address, resultado, dias_restantes)
         VALUES (:empresa_id, :chave_licenca, :ip_address, :resultado, :dias_restantes)'
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':chave_licenca' => $chaveLicenca,
        ':ip_address' => $ipAddress,
        ':resultado' => $resultado,
        ':dias_restantes' => $diasRestantes,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'valida' => false,
        'status' => 'invalida',
        'licenca_fim' => null,
        'dias_restantes' => 0,
        'plano_slug' => null,
        'max_usuarios' => null,
        'mensagem' => 'Metodo nao permitido.',
    ], 405);
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$chaveLicenca = trim((string)($input['chave_licenca'] ?? ''));
$bancoDados = sanitizeDatabaseName((string)($input['banco_dados'] ?? ''));
$ipAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');

if ($chaveLicenca === '' || $bancoDados === null) {
    jsonResponse([
        'valida' => false,
        'status' => 'invalida',
        'licenca_fim' => null,
        'dias_restantes' => 0,
        'plano_slug' => null,
        'max_usuarios' => null,
        'mensagem' => 'Parametros obrigatorios invalidos.',
    ], 400);
}

try {
    $pdo = MasterDatabase::getInstance()->getConnection();

    // Rate limit por chave: 20 requisicoes por hora
    $rateStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM validacoes_log
          WHERE chave_licenca = :chave
            AND created_at >= (CURRENT_TIMESTAMP - INTERVAL '1 hour')"
    );
    $rateStmt->execute([':chave' => $chaveLicenca]);
    $requestsLastHour = (int)$rateStmt->fetchColumn();
    if ($requestsLastHour >= 20) {
        registrarValidacao($pdo, null, $chaveLicenca, $ipAddress, 'invalida', null);
        jsonResponse([
            'valida' => false,
            'status' => 'invalida',
            'licenca_fim' => null,
            'dias_restantes' => 0,
            'plano_slug' => null,
            'max_usuarios' => null,
            'mensagem' => 'Rate limit excedido para esta chave.',
        ], 429);
    }

    $empresa = null;
    try {
        $empresaStmt = $pdo->prepare(
            'SELECT e.id, e.banco_dados, e.status, e.licenca_fim,
                    p.slug AS plano_slug, p.max_usuarios
               FROM empresas e
          LEFT JOIN planos p ON p.id = e.plano_id
              WHERE e.chave_licenca = :chave
              LIMIT 1'
        );
        $empresaStmt->execute([':chave' => $chaveLicenca]);
        $empresa = $empresaStmt->fetch();
    } catch (PDOException $ignored) {
        // Fallback para schema antigo sem plano_id
        $fallbackStmt = $pdo->prepare(
            'SELECT id, banco_dados, status, licenca_fim
               FROM empresas
              WHERE chave_licenca = :chave
              LIMIT 1'
        );
        $fallbackStmt->execute([':chave' => $chaveLicenca]);
        $empresa = $fallbackStmt->fetch();
    }

    if (!is_array($empresa)) {
        registrarValidacao($pdo, null, $chaveLicenca, $ipAddress, 'invalida', null);
        jsonResponse([
            'valida' => false,
            'status' => 'invalida',
            'licenca_fim' => null,
            'dias_restantes' => 0,
            'plano_slug' => null,
            'max_usuarios' => null,
            'mensagem' => 'Chave de licenca nao encontrada.',
        ]);
    }

    $empresaId = (int)$empresa['id'];
    if ((string)$empresa['banco_dados'] !== $bancoDados) {
        registrarValidacao($pdo, $empresaId, $chaveLicenca, $ipAddress, 'invalida', null);
        jsonResponse([
            'valida' => false,
            'status' => 'invalida',
            'licenca_fim' => null,
            'dias_restantes' => 0,
            'plano_slug' => null,
            'max_usuarios' => null,
            'mensagem' => 'Banco informado nao corresponde a licenca.',
        ]);
    }

    $statusAtual = (string)$empresa['status'];
    $licencaFim = (string)$empresa['licenca_fim'];
    $planoSlug = (string)($empresa['plano_slug'] ?? 'profissional');
    $maxUsuarios = array_key_exists('max_usuarios', $empresa)
        ? ($empresa['max_usuarios'] !== null ? (int)$empresa['max_usuarios'] : null)
        : 2;

    $dias = diasRestantes($licencaFim);

    $hoje = new DateTimeImmutable('today');
    $fimObj = DateTimeImmutable::createFromFormat('Y-m-d', $licencaFim) ?: new DateTimeImmutable($licencaFim);
    $vencida = $fimObj < $hoje;

    if ($vencida && in_array($statusAtual, ['ativa', 'trial'], true)) {
        $upd = $pdo->prepare('UPDATE empresas SET status = :status WHERE id = :id');
        $upd->execute([
            ':status' => 'bloqueada',
            ':id' => $empresaId,
        ]);
        $statusAtual = 'bloqueada';
    }

    $valida = false;
    $resultado = 'invalida';
    $mensagem = 'Licenca invalida.';

    if ($statusAtual === 'bloqueada' || $statusAtual === 'cancelada') {
        $resultado = 'bloqueada';
        $mensagem = 'Licenca bloqueada/cancelada.';
    } elseif ($vencida) {
        $resultado = 'vencida';
        $mensagem = 'Licenca vencida.';
    } else {
        $resultado = 'ok';
        $mensagem = 'Licenca valida';
        $valida = true;
    }

    registrarValidacao($pdo, $empresaId, $chaveLicenca, $ipAddress, $resultado, $dias);

    jsonResponse([
        'valida' => $valida,
        'status' => $statusAtual,
        'licenca_fim' => $licencaFim,
        'dias_restantes' => $dias,
        'plano_slug' => $planoSlug,
        'max_usuarios' => $maxUsuarios,
        'mensagem' => $mensagem,
    ]);
} catch (Throwable $e) {
    error_log('API validar-licenca erro: ' . $e->getMessage());
    jsonResponse([
        'valida' => false,
        'status' => 'invalida',
        'licenca_fim' => null,
        'dias_restantes' => 0,
        'plano_slug' => null,
        'max_usuarios' => null,
        'mensagem' => 'Erro interno ao validar licenca.',
    ], 500);
}