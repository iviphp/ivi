<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use PDO;
use PDOException;
use Ivi\Core\Exceptions\ORM\DatabaseConfigNotFoundException;
use Ivi\Core\Exceptions\ORM\DatabaseDriverNotSupportedException;
use Ivi\Core\Exceptions\ORM\DatabaseConnectionException;
use Ivi\Core\Exceptions\ORM\TransactionException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function instance(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $cfgPath = \dirname(__DIR__, 2) . '/config/database.php';
        if (!is_file($cfgPath)) {
            throw new DatabaseConfigNotFoundException($cfgPath);
        }

        /** @var array<string,mixed> $cfg */
        $cfg = require $cfgPath;
        $driver = (string)($cfg['driver'] ?? 'mysql');

        // Build DSN
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($cfg['database'] ?? ':memory:');
        } elseif ($driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int)($cfg['port'] ?? 5432),
                $cfg['database'] ?? ''
            );
        } elseif ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int)($cfg['port'] ?? 3306),
                $cfg['database'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
        } else {
            throw new DatabaseDriverNotSupportedException($driver);
        }

        $user = (string)($cfg['username'] ?? '');
        $pass = (string)($cfg['password'] ?? '');
        $opt  = (array)($cfg['options'] ?? []);
        // Forcer les options minimales sûres
        $opt += [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $opt);
        } catch (PDOException $e) {
            // On ne log pas le mot de passe; DSN sans credentials suffit
            throw new DatabaseConnectionException($dsn, $e);
        }

        return self::$pdo;
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::instance();
        $pdo->beginTransaction();
        try {
            $ret = $fn($pdo);
            $pdo->commit();
            return $ret;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\Throwable $rollback) {
                    // En cas d’échec du rollback, on chaîne l’info
                    throw new TransactionException('Rollback failed', $rollback);
                }
            }
            // Rejeter une TransactionException enveloppante
            throw new TransactionException(previous: $e);
        }
    }
}
