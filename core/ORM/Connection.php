<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

use PDO;
use PDOException;
use Ivi\Core\Exceptions\ORM\DatabaseConfigNotFoundException;
use Ivi\Core\Exceptions\ORM\DatabaseDriverNotSupportedException;
use Ivi\Core\Exceptions\ORM\DatabaseConnectionException;
use Ivi\Core\Exceptions\ORM\TransactionException;

/**
 * Class Connection
 *
 * Manages the database connection layer for the ORM.
 * Provides a singleton PDO instance configured for high reliability and full UTF-8 (emoji) support.
 *
 * Responsibilities:
 *  - Load configuration from `config/database.php`
 *  - Create and cache a PDO connection
 *  - Ensure correct charset (`utf8mb4_unicode_ci`) for full emoji support
 *  - Handle transaction management with rollback safety
 *  - Allow closing the shared connection when needed (e.g., CLI scripts, tests)
 *
 * Usage example:
 * ```php
 * $pdo = Connection::instance();
 * $stmt = $pdo->query("SELECT * FROM users");
 * $rows = $stmt->fetchAll();
 *
 * // Run safely inside a transaction
 * Connection::transaction(function (PDO $pdo) {
 *     $pdo->exec("INSERT INTO logs (message) VALUES ('App started 🚀')");
 * });
 *
 * // Close when done (CLI or testing)
 * Connection::close();
 * ```
 *
 * @package Ivi\Core\ORM
 */
final class Connection
{
    /** @var PDO|null Shared PDO instance */
    private static ?PDO $pdo = null;

    /**
     * Get the shared PDO instance.
     *
     * @throws DatabaseConfigNotFoundException if the configuration file is missing.
     * @throws DatabaseDriverNotSupportedException if the configured driver is not supported.
     * @throws DatabaseConnectionException if the connection fails.
     */
    public static function instance(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $cfgPath = \dirname(__DIR__, 2) . '/config/database.php';
        if (!is_file($cfgPath)) {
            throw new DatabaseConfigNotFoundException($cfgPath);
        }

        /** @var array<string,mixed> $cfg */
        $cfg = require $cfgPath;
        $driver = (string)($cfg['driver'] ?? 'mysql');

        // Build DSN depending on the driver
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

        // Enforce safe defaults and emoji compatibility
        $opt += [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $opt);

            // Ensure emoji-safe charset even if server config differs
            self::$pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
        } catch (PDOException $e) {
            throw new DatabaseConnectionException($dsn, $e);
        }

        return self::$pdo;
    }

    /**
     * Execute a function within a safe database transaction.
     *
     * Automatically commits on success, or rolls back on failure.
     * Throws TransactionException if rollback fails.
     *
     * Example:
     * ```php
     * Connection::transaction(function (PDO $pdo) {
     *     $pdo->exec("INSERT INTO users (name) VALUES ('John 🚀')");
     * });
     * ```
     *
     * @param callable $fn A callback receiving the PDO instance.
     * @return mixed The callback’s return value.
     * @throws TransactionException on rollback or nested failure.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::instance();
        $pdo->beginTransaction();

        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                try {
                    $pdo->rollBack();
                } catch (\Throwable $rollback) {
                    throw new TransactionException('Rollback failed', $rollback);
                }
            }
            throw new TransactionException(previous: $e);
        }
    }

    /**
     * Close the current PDO connection.
     *
     * Useful for CLI commands, testing environments,
     * or when manually resetting the connection pool.
     *
     * Example:
     * ```php
     * Connection::close();
     * ```
     */
    public static function close(): void
    {
        if (self::$pdo !== null) {
            self::$pdo = null;
        }
    }
}
