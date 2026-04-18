<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';

requireAdminAuth();

$pdo = MasterDatabase::getInstance()->getConnection();
$pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS razao_social VARCHAR(255)");
$pdo->exec("UPDATE empresas SET razao_social = nome WHERE (razao_social IS NULL OR razao_social = '') AND nome IS NOT NULL AND nome <> ''");
$erro = '';
$sucesso = null;

$form = [
    'cnpj' => '',
    'nome' => '',
    'razao_social' => '',
    'contato' => '',
    'email' => '',
    'telefone' => '',
    'logradouro' => '',
    'numero' => '',
    'complemento' => '',
    'bairro' => '',
    'cidade' => '',
    'uf' => '',
    'cep' => '',
    'banco_dados' => '',
    'licenca_tipo' => 'mensal',
    'dias_aviso' => 7,
    'observacoes' => '',
    'plano_id' => 1,
];

$suportaPlanoEmpresas = false;
$planos = [];
$planosPorId = [];
$defaultPlanoId = 1;

try {
    $colunaPlanoStmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1
              FROM information_schema.columns
             WHERE table_schema = 'public'
               AND table_name = 'empresas'
               AND column_name = 'plano_id'
        )"
    );
    $colunaPlanoStmt->execute();
    $suportaPlanoEmpresas = (bool)$colunaPlanoStmt->fetchColumn();

    if ($suportaPlanoEmpresas) {
        $pdo->exec(
            "UPDATE planos
                SET nome = 'Profissional',
                    descricao = '1 usuario adicional alem do admin padrao',
                    max_usuarios = 1
              WHERE slug = 'profissional';
             UPDATE planos
                SET nome = 'Master',
                    descricao = 'Usuarios ilimitados, sem restricao de quantidade',
                    max_usuarios = NULL
              WHERE slug = 'master';"
        );

        $sqlPlanosAtivos = "SELECT id, slug, nome, descricao, max_usuarios
               FROM planos
              WHERE ativo = TRUE
           ORDER BY
                CASE
                    WHEN slug = 'profissional' THEN 0
                    WHEN slug = 'master' THEN 1
                    ELSE 2
                END,
                nome ASC";

        $planosStmt = $pdo->query($sqlPlanosAtivos);
        $planos = $planosStmt ? ($planosStmt->fetchAll() ?: []) : [];

        if ($planos === []) {
            $pdo->exec(
                "INSERT INTO planos (slug, nome, descricao, max_usuarios)
                 VALUES
                    ('profissional', 'Profissional', '1 usuario adicional alem do admin padrao', 1),
                    ('master', 'Master', 'Usuarios ilimitados, sem restricao de quantidade', NULL)
                 ON CONFLICT (slug) DO NOTHING"
            );
            $planosStmt = $pdo->query($sqlPlanosAtivos);
            $planos = $planosStmt ? ($planosStmt->fetchAll() ?: []) : [];
        }

        foreach ($planos as $plano) {
            $planosPorId[(int)$plano['id']] = $plano;
        }

        if (!isset($planosPorId[$defaultPlanoId])) {
            $planoProfissional = array_values(array_filter(
                $planos,
                static fn(array $p): bool => (string)($p['slug'] ?? '') === 'profissional'
            ));
            if ($planoProfissional !== []) {
                $defaultPlanoId = (int)$planoProfissional[0]['id'];
            } elseif ($planos !== []) {
                $defaultPlanoId = (int)$planos[0]['id'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao carregar planos ativos: ' . $e->getMessage());
}

$form['plano_id'] = $defaultPlanoId;

function pgQuoteIdentifier(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function bancoJaExisteNoServidor(PDO $adminPdo, string $dbName): bool
{
    $stmt = $adminPdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name LIMIT 1');
    $stmt->execute([':name' => $dbName]);
    return (bool)$stmt->fetchColumn();
}

function dropDatabaseComSeguranca(PDO $adminPdo, string $dbName): void
{
    if (!bancoJaExisteNoServidor($adminPdo, $dbName)) {
        return;
    }

    $quotedName = pgQuoteIdentifier($dbName);
    $adminPdo->prepare('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :db AND pid <> pg_backend_pid()')
        ->execute([':db' => $dbName]);
    $adminPdo->exec('DROP DATABASE IF EXISTS ' . $quotedName);
}

function garantirPermissoesAppNoBancoCliente(PDO $adminPdo, PDO $clientePdo, string $dbName): void
{
    if (MASTER_DB_ADMIN_USER === MASTER_DB_USER) {
        return;
    }

    $quotedDb = pgQuoteIdentifier($dbName);
    $quotedRole = pgQuoteIdentifier(MASTER_DB_USER);

    $adminPdo->exec('GRANT CONNECT ON DATABASE ' . $quotedDb . ' TO ' . $quotedRole);

    $clientePdo->exec('GRANT USAGE, CREATE ON SCHEMA public TO ' . $quotedRole);
    $clientePdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON ALL TABLES IN SCHEMA public TO ' . $quotedRole);
    $clientePdo->exec('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO ' . $quotedRole);
    $clientePdo->exec('GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO ' . $quotedRole);

    $clientePdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE, REFERENCES, TRIGGER ON TABLES TO ' . $quotedRole);
    $clientePdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO ' . $quotedRole);
    $clientePdo->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT EXECUTE ON FUNCTIONS TO ' . $quotedRole);
}

function normalizarMensagemProvisionamento(Throwable $e, string $dbName): string
{
    $msg = $e->getMessage();
    $sqlState = ($e instanceof PDOException && is_array($e->errorInfo ?? null)) ? (string)($e->errorInfo[0] ?? '') : '';

    if (str_contains($msg, 'permission denied to create database') || $sqlState === '42501') {
        return 'O usuario configurado nao possui permissao CREATEDB no PostgreSQL.';
    }

    if (str_contains($msg, 'database "') && str_contains($msg, '" already exists') || $sqlState === '42P04') {
        return 'O banco "' . $dbName . '" ja existe no servidor. Use outro nome de banco.';
    }

    if (str_contains($msg, 'could not open extension control file')) {
        return 'Falha ao criar extensoes do sql_final.sql. Verifique se extensoes do PostgreSQL estao instaladas.';
    }

    if (str_contains($msg, 'Falha na conexao com banco master')) {
        return 'Falha de conexao com o PostgreSQL. Revise MASTER_DB_* no config.';
    }

    return 'Falha ao provisionar empresa: ' . $msg;
}

function normalizarSqlRemovendoBom(string $sql): string
{
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        $sql = substr($sql, 3);
    }

    $sqlSemFeff = preg_replace('/^\x{FEFF}/u', '', $sql);
    if (is_string($sqlSemFeff)) {
        $sql = $sqlSemFeff;
    }

    return $sql;
}

function carregarSqlArquivo(string $path): ?string
{
    $sql = @file_get_contents($path);
    if (!is_string($sql)) {
        return null;
    }

    $sql = normalizarSqlRemovendoBom($sql);
    return trim($sql) === '' ? null : $sql;
}

function executarMigracoesCliente(PDO $clientePdo): void
{
    $migrationsDir = SYSTEM_ROOT_PATH . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        return;
    }

    $migrationFiles = glob($migrationsDir . '/*.sql') ?: [];
    if ($migrationFiles === []) {
        return;
    }

    sort($migrationFiles, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($migrationFiles as $migrationFile) {
        if (!is_string($migrationFile) || !is_file($migrationFile)) {
            continue;
        }

        $sql = carregarSqlArquivo($migrationFile);
        if ($sql === null) {
            continue;
        }

        $clientePdo->exec($sql);
    }
}

function validarEstruturaPermissoesCliente(PDO $clientePdo): void
{
    $requiredTables = ['modulos', 'permissoes_nivel', 'permissoes_usuario'];
    foreach ($requiredTables as $tableName) {
        $stmt = $clientePdo->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = 'public'
                  AND table_name = :table_name
            )"
        );
        $stmt->execute([':table_name' => $tableName]);
        if (!(bool)$stmt->fetchColumn()) {
            throw new RuntimeException('Tabela obrigatoria de permissoes ausente: ' . $tableName);
        }
    }

    $viewStmt = $clientePdo->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.views
            WHERE table_schema = 'public'
              AND table_name = 'vw_permissoes_usuario'
        )"
    );
    $viewStmt->execute();
    if (!(bool)$viewStmt->fetchColumn()) {
        throw new RuntimeException('View obrigatoria ausente: vw_permissoes_usuario');
    }

    $modulosCount = (int)$clientePdo->query('SELECT COUNT(*) FROM public.modulos')->fetchColumn();
    if ($modulosCount < 8) {
        throw new RuntimeException('Quantidade invalida de modulos apos provisionamento: ' . $modulosCount);
    }

    $nivel4Nome = (string)($clientePdo->query('SELECT nome FROM public.niveis_acesso WHERE id = 4')->fetchColumn() ?: '');
    if ($nivel4Nome !== 'Personalizado') {
        throw new RuntimeException('Nivel 4 nao foi renomeado para Personalizado.');
    }
}

function provisionarCliente(array $empresaData, array $planoData): void
{
    $bancoDados = (string)$empresaData['banco_dados'];
    $quotedDb = pgQuoteIdentifier($bancoDados);
    $masterAdminPdo = MasterDatabase::createAdminPdo(MASTER_DB_ADMIN_DB);

    if (bancoJaExisteNoServidor($masterAdminPdo, $bancoDados)) {
        throw new RuntimeException('O banco "' . $bancoDados . '" ja existe no servidor. Use outro nome de banco.');
    }

    $dbCriado = false;

    try {
        $masterAdminPdo->exec('CREATE DATABASE ' . $quotedDb);
        $dbCriado = true;

        $clientePdo = MasterDatabase::createAdminPdo($bancoDados);
        $sqlFinal = carregarSqlArquivo(SQL_FINAL_PATH);
        if ($sqlFinal === null) {
            throw new RuntimeException('Arquivo sql_final.sql nao encontrado ou vazio.');
        }
        $clientePdo->exec($sqlFinal);
        executarMigracoesCliente($clientePdo);
        validarEstruturaPermissoesCliente($clientePdo);
        garantirPermissoesAppNoBancoCliente($masterAdminPdo, $clientePdo, $bancoDados);

        $insertLicenca = $clientePdo->prepare(
            'INSERT INTO licenca (
                chave_licenca, empresa_nome, empresa_cnpj, licenca_tipo, licenca_inicio, licenca_fim, dias_aviso, status
            ) VALUES (
                :chave_licenca, :empresa_nome, :empresa_cnpj, :licenca_tipo, :licenca_inicio, :licenca_fim, :dias_aviso, :status
            )'
        );
        $insertLicenca->execute([
            ':chave_licenca' => $empresaData['chave_licenca'],
            ':empresa_nome' => $empresaData['nome'],
            ':empresa_cnpj' => $empresaData['cnpj'],
            ':licenca_tipo' => $empresaData['licenca_tipo'],
            ':licenca_inicio' => $empresaData['licenca_inicio'],
            ':licenca_fim' => $empresaData['licenca_fim'],
            ':dias_aviso' => (int)$empresaData['dias_aviso'],
            ':status' => $empresaData['status'],
        ]);

        $empresaLocalPayload = [
            ':nome' => $empresaData['razao_social'] !== '' ? $empresaData['razao_social'] : $empresaData['nome'],
            ':cnpj' => $empresaData['cnpj'],
            ':contato' => $empresaData['contato'] !== '' ? $empresaData['contato'] : null,
            ':email' => $empresaData['email'] !== '' ? $empresaData['email'] : null,
            ':telefone' => $empresaData['telefone'] !== '' ? $empresaData['telefone'] : null,
            ':logradouro' => $empresaData['logradouro'] !== '' ? $empresaData['logradouro'] : null,
            ':numero' => $empresaData['numero'] !== '' ? $empresaData['numero'] : null,
            ':complemento' => $empresaData['complemento'] !== '' ? $empresaData['complemento'] : null,
            ':bairro' => $empresaData['bairro'] !== '' ? $empresaData['bairro'] : null,
            ':cidade' => $empresaData['cidade'] !== '' ? $empresaData['cidade'] : null,
            ':uf' => $empresaData['uf'] !== '' ? $empresaData['uf'] : null,
            ':cep' => $empresaData['cep'] !== '' ? $empresaData['cep'] : null,
        ];

        $empresaLocalAtual = $clientePdo->query('SELECT id FROM empresa_local ORDER BY id ASC LIMIT 1');
        $empresaLocalId = $empresaLocalAtual !== false ? (int)($empresaLocalAtual->fetchColumn() ?: 0) : 0;

        if ($empresaLocalId > 0) {
            $empresaLocalPayload[':id'] = $empresaLocalId;
            $upsertEmpresaLocal = $clientePdo->prepare(
                'UPDATE empresa_local
                    SET nome = :nome,
                        cnpj = :cnpj,
                        contato = :contato,
                        email = :email,
                        telefone = :telefone,
                        logradouro = :logradouro,
                        numero = :numero,
                        complemento = :complemento,
                        bairro = :bairro,
                        cidade = :cidade,
                        uf = :uf,
                        cep = :cep,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id'
            );
        } else {
            $upsertEmpresaLocal = $clientePdo->prepare(
                'INSERT INTO empresa_local (
                    nome, cnpj, contato, email, telefone, logradouro, numero, complemento,
                    bairro, cidade, uf, cep, created_at, updated_at
                ) VALUES (
                    :nome, :cnpj, :contato, :email, :telefone, :logradouro, :numero, :complemento,
                    :bairro, :cidade, :uf, :cep, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )'
            );
        }

        $upsertEmpresaLocal->execute($empresaLocalPayload);

        $syncPlanoLicenca = $clientePdo->prepare(
            'UPDATE licenca
                SET plano_slug = :plano_slug,
                    max_usuarios = :max_usuarios
              WHERE TRUE'
        );
        $syncPlanoLicenca->bindValue(':plano_slug', (string)$planoData['slug'], PDO::PARAM_STR);
        if ($planoData['max_usuarios'] === null) {
            $syncPlanoLicenca->bindValue(':max_usuarios', null, PDO::PARAM_NULL);
        } else {
            $syncPlanoLicenca->bindValue(':max_usuarios', (int)$planoData['max_usuarios'], PDO::PARAM_INT);
        }
        $syncPlanoLicenca->execute();
    } catch (Throwable $e) {
        if ($dbCriado) {
            try {
                dropDatabaseComSeguranca($masterAdminPdo, $bancoDados);
            } catch (Throwable $dropErr) {
                error_log('Falha ao limpar banco apos erro de provisionamento: ' . $dropErr->getMessage());
            }
        }

        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $erro = 'Token CSRF invalido.';
    } else {
        $form['cnpj'] = preg_replace('/\D+/', '', (string)($_POST['cnpj'] ?? '')) ?? '';
        $form['nome'] = trim((string)($_POST['nome'] ?? ''));
        $form['razao_social'] = trim((string)($_POST['razao_social'] ?? ''));
        $form['contato'] = trim((string)($_POST['contato'] ?? ''));
        $form['email'] = trim((string)($_POST['email'] ?? ''));
        $form['telefone'] = trim((string)($_POST['telefone'] ?? ''));
        $form['logradouro'] = trim((string)($_POST['logradouro'] ?? ''));
        $form['numero'] = trim((string)($_POST['numero'] ?? ''));
        $form['complemento'] = trim((string)($_POST['complemento'] ?? ''));
        $form['bairro'] = trim((string)($_POST['bairro'] ?? ''));
        $form['cidade'] = trim((string)($_POST['cidade'] ?? ''));
        $form['uf'] = strtoupper(substr(trim((string)($_POST['uf'] ?? '')), 0, 2));
        $form['cep'] = preg_replace('/\D+/', '', (string)($_POST['cep'] ?? '')) ?? '';
        $form['banco_dados'] = trim((string)($_POST['banco_dados'] ?? ''));
        $form['licenca_tipo'] = strtolower(trim((string)($_POST['licenca_tipo'] ?? 'mensal')));
        $form['dias_aviso'] = (int)($_POST['dias_aviso'] ?? 7);
        $form['observacoes'] = trim((string)($_POST['observacoes'] ?? ''));
        $form['plano_id'] = (int)($_POST['plano_id'] ?? $defaultPlanoId);

        if (!$suportaPlanoEmpresas) {
            $erro = 'Banco master sem suporte a plano_id. Execute o arquivo database/master.sql.';
        } elseif ($planos === []) {
            $erro = 'Nenhum plano ativo encontrado no banco master.';
        } elseif (!isset($planosPorId[(int)$form['plano_id']])) {
            $erro = 'Plano invalido.';
        } elseif (!validarCNPJ($form['cnpj'])) {
            $erro = 'CNPJ invalido.';
        } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail invalido.';
        } elseif ($form['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $form['uf'])) {
            $erro = 'UF invalida.';
        } elseif ($form['cep'] !== '' && strlen($form['cep']) !== 8) {
            $erro = 'CEP invalido.';
        } elseif (!in_array($form['licenca_tipo'], ['mensal', 'anual', 'trial'], true)) {
            $erro = 'Tipo de licenca invalido.';
        } elseif ($form['nome'] === '') {
            $erro = 'Nome da empresa e obrigatorio.';
        } elseif ($form['razao_social'] === '') {
            $erro = 'Razao social e obrigatoria.';
        } elseif ($form['dias_aviso'] < 1 || $form['dias_aviso'] > 90) {
            $erro = 'Dias de aviso deve estar entre 1 e 90.';
        } else {
            $dbName = sanitizeDatabaseName($form['banco_dados']);
            if ($dbName === null) {
                $erro = 'Nome do banco invalido. Use letras minusculas, numeros e underscore.';
            } else {
                $form['banco_dados'] = $dbName;
            }
        }

        if ($erro === '') {
            $duplicidade = $pdo->prepare(
                "SELECT id, cnpj, banco_dados
                   FROM empresas
                  WHERE regexp_replace(cnpj, '[^0-9]', '', 'g') = :cnpj_digits
                     OR banco_dados = :banco_dados
                  LIMIT 1"
            );
            $duplicidade->execute([
                ':cnpj_digits' => $form['cnpj'],
                ':banco_dados' => $form['banco_dados'],
            ]);
            $dup = $duplicidade->fetch();
            if (is_array($dup)) {
                $erro = 'Ja existe empresa com este CNPJ ou banco de dados.';
            }
        }

        if ($erro === '') {
            $novoSlug = gerarSlugEmpresa($form['nome']);
            $slugStmt = $pdo->query('SELECT nome FROM empresas');
            $nomesExistentes = $slugStmt ? ($slugStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
            foreach ($nomesExistentes as $nomeExistente) {
                if ($novoSlug === gerarSlugEmpresa((string)$nomeExistente)) {
                    $erro = 'Ja existe empresa com URL semelhante. Ajuste o nome da empresa para gerar URL unica.';
                    break;
                }
            }
        }

        if ($erro === '') {
            $chaveLicenca = gerarChaveLicenca($form['cnpj']);
            $licencaInicio = (new DateTimeImmutable('today'))->format('Y-m-d');
            $licencaFim = calcularLicencaFim($form['licenca_tipo']);
            $status = $form['licenca_tipo'] === 'trial' ? 'trial' : 'ativa';
            $planoSelecionado = $planosPorId[(int)$form['plano_id']];
            $empresaId = null;

            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO empresas (
                        cnpj, nome, razao_social, contato, email, telefone, logradouro, numero, complemento,
                        bairro, cidade, uf, cep, banco_dados, licenca_tipo, licenca_inicio,
                        licenca_fim, dias_aviso, chave_licenca, status, observacoes, plano_id
                    ) VALUES (
                        :cnpj, :nome, :razao_social, :contato, :email, :telefone, :logradouro, :numero, :complemento,
                        :bairro, :cidade, :uf, :cep, :banco_dados, :licenca_tipo, :licenca_inicio,
                        :licenca_fim, :dias_aviso, :chave_licenca, :status, :observacoes, :plano_id
                    ) RETURNING id'
                );
                $insert->execute([
                    ':cnpj' => $form['cnpj'],
                    ':nome' => $form['nome'],
                    ':razao_social' => $form['razao_social'],
                    ':contato' => $form['contato'] !== '' ? $form['contato'] : null,
                    ':email' => $form['email'],
                    ':telefone' => $form['telefone'] !== '' ? $form['telefone'] : null,
                    ':logradouro' => $form['logradouro'] !== '' ? $form['logradouro'] : null,
                    ':numero' => $form['numero'] !== '' ? $form['numero'] : null,
                    ':complemento' => $form['complemento'] !== '' ? $form['complemento'] : null,
                    ':bairro' => $form['bairro'] !== '' ? $form['bairro'] : null,
                    ':cidade' => $form['cidade'] !== '' ? $form['cidade'] : null,
                    ':uf' => $form['uf'] !== '' ? $form['uf'] : null,
                    ':cep' => $form['cep'] !== '' ? $form['cep'] : null,
                    ':banco_dados' => $form['banco_dados'],
                    ':licenca_tipo' => $form['licenca_tipo'],
                    ':licenca_inicio' => $licencaInicio,
                    ':licenca_fim' => $licencaFim,
                    ':dias_aviso' => $form['dias_aviso'],
                    ':chave_licenca' => $chaveLicenca,
                    ':status' => $status,
                    ':observacoes' => $form['observacoes'] !== '' ? $form['observacoes'] : null,
                    ':plano_id' => (int)$form['plano_id'],
                ]);
                $empresaId = (int)$insert->fetchColumn();
                $pdo->commit();

                provisionarCliente([
                    'banco_dados' => $form['banco_dados'],
                    'chave_licenca' => $chaveLicenca,
                    'nome' => $form['nome'],
                    'razao_social' => $form['razao_social'],
                    'cnpj' => $form['cnpj'],
                    'contato' => $form['contato'],
                    'email' => $form['email'],
                    'telefone' => $form['telefone'],
                    'logradouro' => $form['logradouro'],
                    'numero' => $form['numero'],
                    'complemento' => $form['complemento'],
                    'bairro' => $form['bairro'],
                    'cidade' => $form['cidade'],
                    'uf' => $form['uf'],
                    'cep' => $form['cep'],
                    'licenca_tipo' => $form['licenca_tipo'],
                    'licenca_inicio' => $licencaInicio,
                    'licenca_fim' => $licencaFim,
                    'dias_aviso' => $form['dias_aviso'],
                    'status' => $status,
                ], [
                    'slug' => (string)$planoSelecionado['slug'],
                    'max_usuarios' => $planoSelecionado['max_usuarios'] !== null
                        ? (int)$planoSelecionado['max_usuarios']
                        : null,
                ]);

                enviarEmailBoasVindas($pdo, [
                    'id' => $empresaId,
                    'nome' => $form['nome'],
                    'email' => $form['email'],
                    'banco_dados' => $form['banco_dados'],
                    'url_acesso' => gerarUrlAcessoEmpresa($form['nome']),
                ], $chaveLicenca, $licencaFim);

                $sucesso = [
                    'empresa_id' => $empresaId,
                    'chave_licenca' => $chaveLicenca,
                    'banco_dados' => $form['banco_dados'],
                    'url_acesso' => gerarUrlAcessoEmpresa($form['nome']),
                    'licenca_fim' => $licencaFim,
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (is_int($empresaId) && $empresaId > 0) {
                    $delete = $pdo->prepare('DELETE FROM empresas WHERE id = :id');
                    $delete->execute([':id' => $empresaId]);
                }

                error_log('Erro ao criar empresa no painel master: ' . $e->getMessage());
                $erro = normalizarMensagemProvisionamento($e, $form['banco_dados']);
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar Empresa - Painel Master</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(adminUrl('admin/assets/css/style.css')); ?>">
</head>
<?php
$adminNome = (string)($_SESSION['admin_nome'] ?? 'Administrador');
$adminInicial = strtoupper(substr($adminNome, 0, 1) ?: 'A');
?>
<body class="admin-shell">
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-head">
            <div class="logo-mark"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="logo-text">
                <span class="logo-title">LicenseSystem</span>
                <span class="logo-sub">Master Panel</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a class="sidebar-link is-active" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-building"></i> Empresas</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#ultimas-acoes"><i class="fa-solid fa-clock-rotate-left"></i> Historico</a>
            <a class="sidebar-link" href="<?php echo e(adminUrl('admin/index.php')); ?>#configuracoes"><i class="fa-solid fa-gear"></i> Configuracoes</a>
        </nav>
        <div class="sidebar-foot">
            <div class="admin-id">
                <div class="admin-avatar"><?php echo e($adminInicial); ?></div>
                <div class="admin-meta">
                    <span class="admin-name"><?php echo e($adminNome); ?></span>
                    <span class="admin-role">Administrador</span>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-wrap">
        <header class="topbar-fixed">
            <div class="topbar-left">
                <button type="button" class="mobile-nav-toggle" data-nav-toggle aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
                <span>Painel</span>
                <i class="fa-solid fa-angle-right"></i>
                <span>Empresas</span>
                <i class="fa-solid fa-angle-right"></i>
                <span class="crumb-current">Nova Empresa</span>
            </div>
            <div class="topbar-right">
                <span class="badge-notify"><i class="fa-solid fa-sparkles"></i> Provisionamento Automatico</span>
                <a class="btn btn-sm btn-ghost" href="<?php echo e(adminUrl('admin/logout.php')); ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </header>

        <main class="content-area">
            <section class="panel" style="max-width: 700px; margin: 0 auto;">
                <h1 class="page-title">Cadastrar Nova Empresa</h1>
                <p class="page-subtitle">Preencha os dados para gerar ambiente, licenca e acesso automaticamente.</p>

                <?php if ($erro !== ''): ?>
                    <div class="alert alert-error" style="margin-top:12px"><?php echo e($erro); ?></div>
                <?php endif; ?>

                <?php if (is_array($sucesso)): ?>
                    <div class="success-wrap" style="margin-top:14px;">
                        <div class="alert alert-success">Empresa criada com sucesso. Banco <strong><?php echo e((string)$sucesso['banco_dados']); ?></strong>, validade ate <strong><?php echo e((string)$sucesso['licenca_fim']); ?></strong>.</div>

                        <div class="key-box">
                            <div class="field">
                                <span>Chave da licenca</span>
                                <p id="license-key" class="key-mono"><?php echo e((string)$sucesso['chave_licenca']); ?></p>
                                <span id="license-feedback" class="copy-feedback">✓ Copiado</span>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-primary" data-copy-target="license-key" data-copy-feedback="license-feedback"><i class="fa-regular fa-copy"></i> Copiar chave</button>
                            </div>
                        </div>

                        <div class="key-box">
                            <div class="field">
                                <span>URL de acesso</span>
                                <p id="access-url" class="key-mono"><?php echo e((string)$sucesso['url_acesso']); ?></p>
                                <span id="url-feedback" class="copy-feedback">✓ Copiado</span>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-ghost" data-copy-target="access-url" data-copy-feedback="url-feedback"><i class="fa-regular fa-copy"></i> Copiar URL</button>
                                <a class="btn btn-success" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-list"></i> Ir para empresas</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" autocomplete="off" class="stack" data-loading-submit style="margin-top:14px;">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">

                        <div class="form-grid">
                            <div class="float-field">
                                <input type="text" id="cnpj" name="cnpj" value="<?php echo e((string)$form['cnpj']); ?>" required>
                                <label for="cnpj">CNPJ</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="nome" name="nome" value="<?php echo e((string)$form['nome']); ?>" required>
                                <label for="nome">Nome da empresa</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="razao_social" name="razao_social" value="<?php echo e((string)$form['razao_social']); ?>" required>
                                <label for="razao_social">Razao social</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="contato" name="contato" value="<?php echo e((string)$form['contato']); ?>">
                                <label for="contato">Contato</label>
                            </div>

                            <div class="float-field">
                                <input type="email" id="email" name="email" value="<?php echo e((string)$form['email']); ?>" required>
                                <label for="email">E-mail</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="telefone" name="telefone" value="<?php echo e((string)$form['telefone']); ?>">
                                <label for="telefone">Telefone</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="logradouro" name="logradouro" value="<?php echo e((string)$form['logradouro']); ?>">
                                <label for="logradouro">Logradouro</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="numero" name="numero" value="<?php echo e((string)$form['numero']); ?>">
                                <label for="numero">Numero</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="complemento" name="complemento" value="<?php echo e((string)$form['complemento']); ?>">
                                <label for="complemento">Complemento</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="bairro" name="bairro" value="<?php echo e((string)$form['bairro']); ?>">
                                <label for="bairro">Bairro</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="cidade" name="cidade" value="<?php echo e((string)$form['cidade']); ?>">
                                <label for="cidade">Cidade</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="uf" name="uf" maxlength="2" value="<?php echo e((string)$form['uf']); ?>">
                                <label for="uf">UF</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="cep" name="cep" value="<?php echo e((string)$form['cep']); ?>">
                                <label for="cep">CEP</label>
                            </div>

                            <div class="float-field">
                                <input type="text" id="banco_dados" name="banco_dados" value="<?php echo e((string)$form['banco_dados']); ?>" required>
                                <label for="banco_dados">Banco de dados do cliente</label>
                            </div>

                            <div class="float-field">
                                <input type="number" id="dias_aviso" name="dias_aviso" min="1" max="90" value="<?php echo (int)$form['dias_aviso']; ?>">
                                <label for="dias_aviso">Dias de aviso</label>
                            </div>

                            <div class="float-field">
                                <textarea id="observacoes" name="observacoes" rows="3"><?php echo e((string)$form['observacoes']); ?></textarea>
                                <label for="observacoes">Observacoes</label>
                            </div>
                        </div>

                        <div class="field">
                            <span>Tipo de licenca</span>
                            <div class="license-plan-grid" data-license-plans>
                                <article class="license-plan" data-plan="trial">
                                    <div class="plan-title"><i class="fa-regular fa-calendar-days"></i> Trial (15 dias)</div>
                                    <div class="plan-sub">Entrada rapida para testes.</div>
                                </article>
                                <article class="license-plan" data-plan="mensal">
                                    <div class="plan-title"><i class="fa-regular fa-calendar"></i> Mensal (30 dias)</div>
                                    <div class="plan-sub">Renovacao flexivel mensal.</div>
                                </article>
                                <article class="license-plan" data-plan="anual">
                                    <span class="plan-badge">Melhor valor</span>
                                    <div class="plan-title"><i class="fa-solid fa-calendar-check"></i> Anual (365 dias)</div>
                                    <div class="plan-sub">Maior estabilidade para o cliente.</div>
                                </article>
                            </div>
                            <select name="licenca_tipo" style="display:none">
                                <option value="mensal" <?php echo $form['licenca_tipo'] === 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                                <option value="anual" <?php echo $form['licenca_tipo'] === 'anual' ? 'selected' : ''; ?>>Anual</option>
                                <option value="trial" <?php echo $form['licenca_tipo'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            </select>
                        </div>

                        <div class="field">
                            <span>Plano</span>
                            <input type="hidden" name="plano_id" id="plano_id" value="<?php echo (int)$form['plano_id']; ?>">
                            <div class="license-plan-grid" data-plano-cards>
                                <?php foreach ($planos as $plano): ?>
                                    <?php
                                    $planoId = (int)$plano['id'];
                                    $slug = (string)$plano['slug'];
                                    $isSelected = $planoId === (int)$form['plano_id'];
                                    $isProfissional = $slug === 'profissional';
                                    $isMaster = $slug === 'master';
                                    $maxUsuarios = $plano['max_usuarios'] !== null ? (int)$plano['max_usuarios'] : null;
                                    ?>
                                    <article
                                        class="license-plan <?php echo $isSelected ? 'is-selected selected' : ''; ?>"
                                        data-plano-id="<?php echo $planoId; ?>"
                                        role="button"
                                        tabindex="0"
                                        aria-pressed="<?php echo $isSelected ? 'true' : 'false'; ?>"
                                    >
                                        <div class="plan-title">
                                            <?php if ($isMaster): ?>
                                                <i class="fa-solid fa-infinity"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-users"></i>
                                            <?php endif; ?>
                                            <?php echo e((string)$plano['nome']); ?>
                                        </div>
                                        <?php if ($isProfissional): ?>
                                            <div class="plan-sub">1 usuario adicional</div>
                                            <div class="plan-sub">Admin padrao nao conta no limite</div>
                                        <?php elseif ($isMaster): ?>
                                            <div class="plan-sub">Usuarios ilimitados</div>
                                            <div class="plan-sub">Sem restricao de quantidade</div>
                                        <?php else: ?>
                                            <div class="plan-sub"><?php echo e((string)($plano['descricao'] ?? '')); ?></div>
                                            <div class="plan-sub">
                                                <?php echo $maxUsuarios === null ? 'Usuarios ilimitados' : ('Ate ' . $maxUsuarios . ' usuarios'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-text"><i class="fa-solid fa-bolt"></i> Criar empresa e provisionar</span>
                                <span class="spinner"></span>
                            </button>
                            <a class="btn btn-ghost" href="<?php echo e(adminUrl('admin/empresas/listar.php')); ?>"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<script src="<?php echo e(adminUrl('admin/assets/js/admin.js')); ?>"></script>
<script>
(function () {
    const wrap = document.querySelector('[data-plano-cards]');
    const hiddenInput = document.getElementById('plano_id');
    if (!wrap || !hiddenInput) {
        return;
    }

    const cards = Array.from(wrap.querySelectorAll('[data-plano-id]'));
    if (!cards.length) {
        return;
    }

    const sync = function (value) {
        cards.forEach(function (card) {
            const selected = card.getAttribute('data-plano-id') === String(value);
            card.classList.toggle('is-selected', selected);
            card.classList.toggle('selected', selected);
            card.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    };

    cards.forEach(function (card) {
        const activate = function () {
            const planoId = card.getAttribute('data-plano-id');
            if (!planoId) {
                return;
            }
            hiddenInput.value = planoId;
            sync(planoId);
        };

        card.addEventListener('click', activate);
        card.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });

    sync(hiddenInput.value);
})();
</script>
</body>
</html>
