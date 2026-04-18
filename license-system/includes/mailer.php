<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function registrarEmail(PDO $pdo, int $empresaId, string $tipo, string $destinatario, string $assunto, string $status, ?string $erro = null): void
{
    $sql = 'INSERT INTO emails_enviados (empresa_id, tipo, destinatario, assunto, status, erro_mensagem)
            VALUES (:empresa_id, :tipo, :destinatario, :assunto, :status, :erro)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':tipo' => $tipo,
        ':destinatario' => $destinatario,
        ':assunto' => $assunto,
        ':status' => $status,
        ':erro' => $erro,
    ]);
}

function enviarEmailBoasVindas(PDO $pdo, array $empresa, string $chaveLicenca, string $licencaFim): bool
{
    $empresaId = (int)($empresa['id'] ?? 0);
    $email = filter_var((string)($empresa['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if ($empresaId <= 0 || !$email) {
        return false;
    }

    $nome = trim((string)($empresa['nome'] ?? 'Cliente'));
    $bancoDados = trim((string)($empresa['banco_dados'] ?? ''));
    $urlAcesso = trim((string)($empresa['url_acesso'] ?? ''));
    if ($urlAcesso === '' && $nome !== '') {
        $urlAcesso = gerarUrlAcessoEmpresa($nome);
    }

    $assunto = 'Boas-vindas - sua licenca foi criada';
    $mensagem = "Ola {$nome},\n\n" .
        "Seu ambiente foi provisionado com sucesso.\n" .
        "URL de acesso: {$urlAcesso}\n" .
        "Banco de dados: {$bancoDados}\n" .
        "Chave da licenca: {$chaveLicenca}\n" .
        "Licenca valida ate: {$licencaFim}\n\n" .
        "Guarde esta chave em local seguro.\n";

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    try {
        $enviado = @mail($email, str_replace(["\r", "\n"], '', $assunto), $mensagem, implode("\r\n", $headers));
        registrarEmail(
            $pdo,
            $empresaId,
            'BOAS_VINDAS',
            $email,
            $assunto,
            $enviado ? 'enviado' : 'erro',
            $enviado ? null : 'mail() retornou false'
        );

        return $enviado;
    } catch (Throwable $e) {
        registrarEmail($pdo, $empresaId, 'BOAS_VINDAS', $email, $assunto, 'erro', $e->getMessage());
        return false;
    }
}
