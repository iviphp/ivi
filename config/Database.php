<?php

namespace Ivi\Config;

use PDO;
use PDOException;

class Database
{
    private string $dbname;
    private string $host;
    private string $username;
    private string $password;
    private ?PDO $pdo = null;

    private static ?Database $instance = null;

    private function __construct(string $dbname, string $host, string $username, string $password)
    {
        $this->dbname = $dbname;
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @param string $dbname
     * @param string $host
     * @param string $username
     * @param string $password
     * @return Database
     */
    public static function getInstance(string $dbname, string $host, string $username, string $password): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database($dbname, $host, $username, $password);
        }
        return self::$instance;
    }

    /**
     * Retourne l'instance PDO.
     * @return PDO
     * @throws PDOException
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:dbname={$this->dbname};host={$this->host};charset=utf8mb4";
                $this->pdo = new PDO($dsn, $this->username, $this->password);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $this->pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_general_ci'");
            } catch (PDOException $e) {
                throw new PDOException("Erreur de connexion à la base de données : " . $e->getMessage());
            }
        }
        return $this->pdo;
    }

    public function closeConnection(): void
    {
        $this->pdo = null;
    }

    private function __clone() {}

    public function __wakeup() {}
}
