<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class MasterDatabase
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $this->connection = self::createPdo(MASTER_DB_NAME);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public static function createPdo(string $dbName): PDO
    {
        return self::createPdoWithCredentials($dbName, MASTER_DB_USER, MASTER_DB_PASS);
    }

    public static function createAdminPdo(string $dbName): PDO
    {
        return self::createPdoWithCredentials($dbName, MASTER_DB_ADMIN_USER, MASTER_DB_ADMIN_PASS);
    }

    private static function createPdoWithCredentials(string $dbName, string $dbUser, string $dbPass): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            MASTER_DB_HOST,
            MASTER_DB_PORT,
            $dbName
        );

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec("SET NAMES 'UTF8'");
            $pdo->exec("SET timezone TO 'America/Sao_Paulo'");

            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException('Falha na conexão com banco master.', (int)$e->getCode(), $e);
        }
    }
}
