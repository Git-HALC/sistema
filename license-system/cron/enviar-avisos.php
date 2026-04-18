<?php
// 0 8 * * * php /caminho/license-system/cron/enviar-avisos.php >> /caminho/cron/logs/avisos.log 2>&1
// 0 1 * * * php /caminho/license-system/cron/bloquear-vencidas.php >> /caminho/cron/logs/bloqueio.log 2>&1
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

/**
 * Resolve o tipo de aviso com base nas regras de negócio.
 */
function resolverTipoAviso(int $diasRestantes, string $status): ?string
{
    return match (true) {
        $diasRestantes === 7 => 'AVISO_7_DIAS',
        $diasRestantes === 3 => 'AVISO_3_DIAS',
        $diasRestantes === 1 => 'AVISO_1_DIA',
        $diasRestantes === 0 && $status === 'bloqueada' => 'LICENCA_VENCIDA',
        default => null,
    };
}

/**
 * Monta assunto e corpo HTML para cada tipo de notificação.
 */
function montarMensagem(string $tipo, array $empresa): array
{
    $nome = (string)($empresa['nome'] ?? 'Cliente');
    $licencaFim = (string)($empresa['licenca_fim'] ?? '');
    $dias = (int)($empresa['dias_restantes'] ?? 0);
    $status = (string)($empresa['status'] ?? '');

    $assunto = match ($tipo) {
        'AVISO_7_DIAS' => 'Sua licença vence em 7 dias',
        'AVISO_3_DIAS' => 'Sua licença vence em 3 dias',
        'AVISO_1_DIA' => 'Sua licença vence amanhã',
        'LICENCA_VENCIDA' => 'Licença vencida - acesso bloqueado',
        default => 'Aviso de licença',
    };

    $mensagem = '<html><body style="font-family:Arial,sans-serif;">'
        . '<h2>Olá, ' . htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>'
        . '<p>Este é um aviso automático sobre sua licença.</p>'
        . '<ul>'
        . '<li><strong>Status:</strong> ' . htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
        . '<li><strong>Data de vencimento:</strong> ' . htmlspecialchars($licencaFim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>'
        . '<li><strong>Dias restantes:</strong> ' . $dias . '</li>'
        . '</ul>'
        . '<p>Entre em contato com o suporte comercial para renovar.</p>'
        . '<hr><small>Mensagem automática do License System.</small>'
        . '</body></html>';

    return [$assunto, $mensagem];
}

/**
 * Envia e-mail HTML usando mail() nativo.
 */
function enviarEmailHtml(string $destinatario, string $assunto, string $html): bool
{
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
    ];

    return @mail($destinatario, str_replace(["\r", "\n"], '', $assunto), $html, implode("\r\n", $headers));
}

/**
 * Registra envio na tabela emails_enviados.
 */
function registrarEmail(PDO $pdo, int $empresaId, string $tipo, string $destinatario, string $assunto, string $status, ?string $erro = null): void
{
    $insert = $pdo->prepare(
        'INSERT INTO emails_enviados (empresa_id, tipo, destinatario, assunto, status, erro_mensagem)
         VALUES (:empresa_id, :tipo, :destinatario, :assunto, :status, :erro)'
    );
    $insert->execute([
        ':empresa_id' => $empresaId,
        ':tipo' => $tipo,
        ':destinatario' => $destinatario,
        ':assunto' => $assunto,
        ':status' => $status,
        ':erro' => $erro,
    ]);
}

try {
    $pdo = MasterDatabase::getInstance()->getConnection();

    $stmt = $pdo->query(
        "SELECT *
           FROM vw_licencas_vencendo
          WHERE alerta IN ('URGENTE','CRITICO','AVISO','VENCIDA')
          ORDER BY licenca_fim ASC"
    );
    $empresas = $stmt ? ($stmt->fetchAll() ?: []) : [];

    $enviados = 0;
    $ignorados = 0;

    foreach ($empresas as $empresa) {
        $empresaId = (int)($empresa['id'] ?? 0);
        $diasRestantes = (int)($empresa['dias_restantes'] ?? 0);
        $status = strtolower((string)($empresa['status'] ?? ''));
        $tipo = resolverTipoAviso($diasRestantes, $status);
        $email = (string)($empresa['email'] ?? '');
        $licencaInicio = (string)($empresa['licenca_inicio'] ?? date('Y-m-d'));

        if ($empresaId <= 0 || $tipo === null) {
            $ignorados++;
            continue;
        }

        // Evita duplicar envio no mesmo ciclo de licença.
        $jaEnviadoStmt = $pdo->prepare(
            'SELECT 1
               FROM emails_enviados
              WHERE empresa_id = :empresa_id
                AND tipo = :tipo
                AND enviado_em::date >= :licenca_inicio
              LIMIT 1'
        );
        $jaEnviadoStmt->execute([
            ':empresa_id' => $empresaId,
            ':tipo' => $tipo,
            ':licenca_inicio' => $licencaInicio,
        ]);
        $jaEnviado = (bool)$jaEnviadoStmt->fetchColumn();
        if ($jaEnviado) {
            $ignorados++;
            continue;
        }

        [$assunto, $html] = montarMensagem($tipo, $empresa);
        $ok = enviarEmailHtml($email, $assunto, $html);

        registrarEmail(
            $pdo,
            $empresaId,
            $tipo,
            $email,
            $assunto,
            $ok ? 'enviado' : 'erro',
            $ok ? null : 'Falha no envio via mail()'
        );

        if ($ok) {
            $enviados++;
        } else {
            $ignorados++;
        }
    }

    echo '[OK] Avisos processados. Enviados: ' . $enviados . ' | Ignorados/Falhas: ' . $ignorados . PHP_EOL;
} catch (Throwable $e) {
    error_log('Cron enviar-avisos erro: ' . $e->getMessage());
    echo '[ERRO] ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
