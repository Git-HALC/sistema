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
 * Escreve linhas de log em arquivo diário.
 */
function escreverLog(string $arquivo, string $mensagem): void
{
    $linha = sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $mensagem, PHP_EOL);
    file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
}

try {
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir) && !mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
        throw new RuntimeException('Não foi possível criar diretório de logs: ' . $logsDir);
    }

    $logFile = $logsDir . '/bloqueio_' . date('Y-m-d') . '.log';
    $pdo = MasterDatabase::getInstance()->getConnection();

    $stmt = $pdo->query(
        "SELECT id, nome, banco_dados, licenca_fim
           FROM empresas
          WHERE licenca_fim < CURRENT_DATE
            AND status = 'ativa'
          ORDER BY licenca_fim ASC"
    );
    $empresas = $stmt ? ($stmt->fetchAll() ?: []) : [];

    if ($empresas === []) {
        escreverLog($logFile, 'Nenhuma empresa ativa vencida encontrada.');
        echo "[OK] Nenhuma empresa para bloquear.\n";
        exit(0);
    }

    $updateMaster = $pdo->prepare(
        "UPDATE empresas
            SET status = 'bloqueada'
          WHERE id = :id
            AND status = 'ativa'"
    );

    $totalProcessadas = 0;
    $totalBloqueadasMaster = 0;
    $totalBloqueadasCliente = 0;
    $totalErrosCliente = 0;

    foreach ($empresas as $empresa) {
        $totalProcessadas++;
        $empresaId = (int)($empresa['id'] ?? 0);
        $nome = (string)($empresa['nome'] ?? 'Empresa sem nome');
        $bancoDados = (string)($empresa['banco_dados'] ?? '');
        $licencaFim = (string)($empresa['licenca_fim'] ?? '');

        if ($empresaId <= 0 || $bancoDados === '') {
            escreverLog($logFile, "Empresa ignorada (dados inválidos): id={$empresaId}, banco={$bancoDados}");
            continue;
        }

        $updateMaster->execute([':id' => $empresaId]);
        if ($updateMaster->rowCount() > 0) {
            $totalBloqueadasMaster++;
            escreverLog(
                $logFile,
                "Master bloqueado: empresa_id={$empresaId}, nome=\"{$nome}\", banco=\"{$bancoDados}\", licenca_fim={$licencaFim}"
            );
        } else {
            escreverLog($logFile, "Sem alteração no master (já bloqueada?): empresa_id={$empresaId}");
            continue;
        }

        try {
            $clientePdo = MasterDatabase::createPdo($bancoDados);
            $updateCliente = $clientePdo->prepare("UPDATE licenca SET status = 'bloqueada'");
            $updateCliente->execute();

            $totalBloqueadasCliente++;
            escreverLog(
                $logFile,
                "Cliente bloqueado: empresa_id={$empresaId}, banco=\"{$bancoDados}\", linhas_afetadas={$updateCliente->rowCount()}"
            );
        } catch (Throwable $e) {
            $totalErrosCliente++;
            escreverLog(
                $logFile,
                "ERRO cliente: empresa_id={$empresaId}, banco=\"{$bancoDados}\", erro=\"{$e->getMessage()}\""
            );
            error_log('Cron bloquear-vencidas erro no banco cliente: ' . $e->getMessage());
        }
    }

    escreverLog(
        $logFile,
        "Resumo: processadas={$totalProcessadas}, master_bloqueadas={$totalBloqueadasMaster}, cliente_bloqueadas={$totalBloqueadasCliente}, erros_cliente={$totalErrosCliente}"
    );

    echo "[OK] Bloqueio concluído. Processadas: {$totalProcessadas}; Master: {$totalBloqueadasMaster}; Cliente: {$totalBloqueadasCliente}; Erros cliente: {$totalErrosCliente}\n";
} catch (Throwable $e) {
    error_log('Cron bloquear-vencidas erro fatal: ' . $e->getMessage());
    echo '[ERRO] ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
