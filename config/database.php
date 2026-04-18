<?php
// PSR-4 autoloader carregado uma unica vez aqui porque todos os dispatchers
// fazem require_once deste arquivo antes de qualquer outra coisa.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/tenant.php';
tenantBootstrap();

class Database {
    private $host = 'localhost';
    private $username = 'postgres';
    private $password = 'masterkey';
    private $port = '5432';
    private $defaultDbName = 'suporte';
    private $dbname = 'suporte';
    private $conn;
    private static $instance = null;
    private $lastError = null;

    // Padrao Singleton para garantir uma unica instancia da conexao
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->dbname = $this->resolveDatabaseName();
        $this->connect();
    }

    private function resolveDatabaseName() {
        $tenantSlug = tenantCurrentSlug();
        if (!is_string($tenantSlug) || $tenantSlug === '') {
            return $this->defaultDbName;
        }


        $tenantDb = $this->resolveTenantDatabaseName($tenantSlug);
        if (!is_string($tenantDb) || $tenantDb === '') {
            return $this->defaultDbName;
        }

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tenant_slug'] = $tenantSlug;
            $_SESSION['tenant_dbname'] = $tenantDb;
        }

        return $tenantDb;
    }

    private function resolveTenantDatabaseName(string $tenantSlug): ?string
    {
        $configPath = __DIR__ . '/../license-system/config/config.php';
        if (!is_file($configPath)) {
            return null;
        }

        require_once $configPath;

        if (!defined('MASTER_DB_HOST') || !defined('MASTER_DB_PORT') || !defined('MASTER_DB_NAME') || !defined('MASTER_DB_USER') || !defined('MASTER_DB_PASS')) {
            return null;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                MASTER_DB_HOST,
                MASTER_DB_PORT,
                MASTER_DB_NAME
            );

            $masterPdo = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $hasUrlSlugColumn = false;
            $columnStmt = $masterPdo->query(
                "SELECT 1
                   FROM information_schema.columns
                  WHERE table_schema = 'public'
                    AND table_name = 'empresas'
                    AND column_name = 'url_slug'
                  LIMIT 1"
            );
            if ($columnStmt !== false) {
                $hasUrlSlugColumn = (bool)$columnStmt->fetchColumn();
            }

            if ($hasUrlSlugColumn) {
                $slugStmt = $masterPdo->prepare(
                    'SELECT banco_dados
                       FROM empresas
                      WHERE lower(url_slug) = :slug
                      LIMIT 1'
                );
                $slugStmt->execute([':slug' => strtolower($tenantSlug)]);
                $row = $slugStmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row) && !empty($row['banco_dados'])) {
                    return (string)$row['banco_dados'];
                }
            }

            $stmt = $masterPdo->query('SELECT nome, banco_dados FROM empresas ORDER BY id ASC');
            if ($stmt === false) {
                return null;
            }

            $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidatos as $empresa) {
                $nomeSlug = tenantSlugify((string)($empresa['nome'] ?? ''));
                $bancoSlug = tenantSlugify((string)($empresa['banco_dados'] ?? ''));
                if ($tenantSlug === $nomeSlug || $tenantSlug === $bancoSlug) {
                    $dbName = (string)($empresa['banco_dados'] ?? '');
                    return $dbName !== '' ? $dbName : null;
                }
            }
        } catch (Throwable $e) {
            error_log('Falha ao resolver tenant para banco: ' . $e->getMessage());
        }

        return null;
    }

    private function connect() {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname};user={$this->username};password={$this->password}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 5
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->conn->exec("SET NAMES 'UTF8'");
            $this->conn->exec("SET timezone TO 'America/Sao_Paulo'");

            $this->checkDatabaseStructure();
            $this->lastError = null;

        } catch (PDOException $e) {
            error_log('Erro de conexao com o banco de dados: ' . $e->getMessage());

            tenantEnsureSession();
            if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['error_message'] = 'Nao foi possivel conectar ao banco de dados. Por favor, tente novamente mais tarde.';
            }

            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                header('Location: ' . tenantUrl('erro.php'));
                exit();
            }

            die('Erro critico: Nao foi possivel conectar ao banco de dados.');
        }
    }

    public function getConnection() {
        try {
            if ($this->conn === null || !$this->isConnected()) {
                $this->connect();
            }
            return $this->conn;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log('Erro na conexao com o banco de dados: ' . $e->getMessage());
            throw $e;
        }
    }

    private function isConnected() {
        try {
            return $this->conn !== null && $this->conn->query('SELECT 1')->fetchColumn() === '1';
        } catch (PDOException $e) {
            return false;
        }
    }

    private function normalizeSqlRemovingBom(string $sql): string
    {
        if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
            $sql = substr($sql, 3);
        }

        $withoutFeff = preg_replace('/^\x{FEFF}/u', '', $sql);
        if (is_string($withoutFeff)) {
            $sql = $withoutFeff;
        }

        return $sql;
    }

    private function readSqlFile(string $path): ?string
    {
        $sql = @file_get_contents($path);
        if (!is_string($sql)) {
            return null;
        }

        $sql = $this->normalizeSqlRemovingBom($sql);
        return trim($sql) === '' ? null : $sql;
    }

    private function checkDatabaseStructure() {
        try {
            $stmt = $this->conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'usuarios')");
            if (!$stmt->fetchColumn()) {
                $schemaCandidates = [
                    __DIR__ . '/../database/cliente-base.sql',
                ];

                foreach ($schemaCandidates as $schemaFile) {
                    if (!is_file($schemaFile)) {
                        continue;
                    }

                    $sql = $this->readSqlFile($schemaFile);
                    if ($sql === null) {
                        continue;
                    }

                    $this->conn->exec($sql);
                    break;
                }
            }

            $this->applyPendingMigrations();
        } catch (PDOException $e) {
            error_log('Erro ao verificar/executar estrutura do banco de dados: ' . $e->getMessage());
        }
    }

    private function applyPendingMigrations(): void
    {
        $migrationsDir = __DIR__ . '/../database/migrations';
        if (!is_dir($migrationsDir)) {
            return;
        }

        $this->ensureMigrationsTable();

        $files = glob($migrationsDir . '/*.sql');
        if (!is_array($files) || $files === []) {
            return;
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $filePath) {
            if (!is_string($filePath) || !is_file($filePath)) {
                continue;
            }

            $sql = $this->readSqlFile($filePath);
            if ($sql === null) {
                continue;
            }

            $version = basename($filePath, '.sql');
            $checksum = sha1($sql);

            $stmt = $this->conn->prepare('SELECT checksum FROM public.schema_migrations WHERE version = :version LIMIT 1');
            $stmt->execute([':version' => $version]);
            $storedChecksum = $stmt->fetchColumn();

            if (is_string($storedChecksum) && $storedChecksum === $checksum) {
                continue;
            }

            $this->conn->exec($sql);

            $upsert = $this->conn->prepare('
                INSERT INTO public.schema_migrations (version, checksum, executed_at)
                VALUES (:version, :checksum, CURRENT_TIMESTAMP)
                ON CONFLICT (version) DO UPDATE
                SET checksum = EXCLUDED.checksum,
                    executed_at = CURRENT_TIMESTAMP
            ');
            $upsert->execute([
                ':version' => $version,
                ':checksum' => $checksum,
            ]);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->conn->exec('
            CREATE TABLE IF NOT EXISTS public.schema_migrations (
                version VARCHAR(255) PRIMARY KEY,
                checksum CHAR(40) NOT NULL,
                executed_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception('Nao e possivel desserializar uma conexao com o banco de dados');
    }
}

$database = Database::getInstance();
$db = $database->getConnection();

function beginTransaction() {
    global $db;
    return $db->beginTransaction();
}

function commit() {
    global $db;
    return $db->commit();
}

function rollBack() {
    global $db;
    return $db->rollBack();
}

function lastInsertId($name = null) {
    global $db;
    return $db->lastInsertId($name);
}

function query($sql, $params = []) {
    global $db;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Erro na consulta SQL: ' . $e->getMessage());
        error_log('SQL: ' . $sql);
        error_log('Parametros: ' . print_r($params, true));
        throw $e;
    }
}

function fetchOne($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetch();
}

function fetchAll($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchAll();
}

function insert($table, $data) {
    global $db;

    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));

    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log('Erro ao inserir dados: ' . $e->getMessage());
        throw $e;
    }
}

function update($table, $data, $where, $whereParams = []) {
    global $db;

    $set = [];
    foreach (array_keys($data) as $column) {
        $set[] = "$column = :$column";
    }
    $setClause = implode(', ', $set);

    $sql = "UPDATE $table SET $setClause WHERE $where";

    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute(array_merge($data, $whereParams));
    } catch (PDOException $e) {
        error_log('Erro ao atualizar dados: ' . $e->getMessage());
        throw $e;
    }
}

function exists($table, $column, $value, $excludeId = null) {
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = :value";
    $params = [':value' => $value];

    if ($excludeId) {
        $sql .= ' AND id != :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $result = fetchOne($sql, $params);
    return $result['count'] > 0;
}
?>
