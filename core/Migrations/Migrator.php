<?php

declare(strict_types=1);

namespace Ivi\Core\Migrations;

use Ivi\Core\ORM\Connection;
use PDO;
use Throwable;

/**
 * Class Migrator
 *
 * Handles execution and tracking of raw SQL migration files.
 * It scans a directory for `.sql` files, executes any that have not yet been
 * applied, and records their filename and checksum in the `migrations` table.
 *
 * ### Key Features
 * - Automatically creates the `migrations` table if it does not exist.
 * - Supports MySQL, PostgreSQL, and SQLite.
 * - Prevents re-execution of already applied migrations using SHA-1 checksums.
 * - Detects modified migration files and re-applies them safely.
 * - Provides `migrate`, `status`, and `reset` commands.
 *
 * ### Example
 * ```bash
 * php bin/ivi migrate
 * php bin/ivi migrate:status
 * php bin/ivi migrate:reset
 * ```
 *
 * ### Notes
 * - Each migration file should contain a single SQL batch (CREATE TABLE, etc.).
 * - DDL statements (CREATE/ALTER/DROP) auto-commit in MySQL; therefore,
 *   commits and rollbacks are guarded with `inTransaction()` checks.
 */
final class Migrator
{
    public function __construct(private string $migrationsPath)
    {
        $real = \realpath($this->migrationsPath);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("Migrations directory not found: {$this->migrationsPath}");
        }
        $this->migrationsPath = $real;
    }

    /**
     * Format a colored status badge for terminal output.
     *
     * Example:
     *   $this->badge('OK', 'green');
     *   $this->badge('ERROR', 'red');
     */
    private function badge(string $label, string $color): string
    {
        $colors = [
            'red'    => "\033[1;31m",
            'green'  => "\033[1;32m",
            'yellow' => "\033[1;33m",
            'blue'   => "\033[1;34m",
            'cyan'   => "\033[1;36m",
            'gray'   => "\033[0;37m",
            'reset'  => "\033[0m",
        ];

        $start = $colors[$color] ?? $colors['reset'];
        $end   = $colors['reset'];

        return sprintf("[%s%s%s]", $start, strtoupper($label), $end);
    }

    /**
     * Execute all pending migrations found in the configured directory.
     */
    public function migrate(): void
    {
        $pdo = Connection::instance();
        $this->ensureMigrationsTable($pdo);

        $files = glob($this->migrationsPath . '/*.sql');
        sort($files, SORT_NATURAL);

        if (!$files) {
            echo $this->badge('INFO', 'yellow') . " No migrations found in {$this->migrationsPath}\n";
            return;
        }

        $applied = $this->getAppliedMap($pdo);

        echo $this->badge('RUN', 'cyan') . " Running migrations...\n";

        foreach ($files as $file) {
            $name = basename($file);
            $sql  = file_get_contents($file) ?: '';
            $hash = sha1($sql);

            if (isset($applied[$name]) && $applied[$name] === $hash) {
                echo $this->badge('SKIP', 'gray') . " {$name} (already up-to-date)\n";
                continue;
            }

            echo $this->badge('EXEC', 'blue') . " Executing: {$name}\n";

            // Some databases auto-commit DDL statements.
            // We guard commit/rollback calls with inTransaction().
            $hadTx = $pdo->inTransaction();
            if (!$hadTx) {
                $pdo->beginTransaction();
            }

            try {
                $pdo->exec($sql);

                // Upsert migration record depending on the driver
                $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

                if ($driver === 'sqlite') {
                    $stmt = $pdo->prepare("
                    INSERT INTO migrations(filename, checksum, executed_at)
                    VALUES(:f, :c, CURRENT_TIMESTAMP)
                    ON CONFLICT(filename)
                    DO UPDATE SET checksum = excluded.checksum, executed_at = CURRENT_TIMESTAMP
                ");
                } elseif ($driver === 'pgsql') {
                    $stmt = $pdo->prepare("
                    INSERT INTO migrations(filename, checksum, executed_at)
                    VALUES(:f, :c, NOW())
                    ON CONFLICT (filename)
                    DO UPDATE SET checksum = EXCLUDED.checksum, executed_at = NOW()
                ");
                } else { // MySQL or compatible
                    $stmt = $pdo->prepare("
                    INSERT INTO migrations(filename, checksum, executed_at)
                    VALUES(:f, :c, NOW())
                    ON DUPLICATE KEY UPDATE
                        checksum = VALUES(checksum),
                        executed_at = VALUES(executed_at)
                ");
                }

                $stmt->execute([':f' => $name, ':c' => $hash]);

                if ($hadTx && $pdo->inTransaction()) {
                    $pdo->commit();
                }

                echo $this->badge('OK', 'green') . " {$name} executed successfully.\n";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo $this->badge('ERROR', 'red') . " in {$name}: {$e->getMessage()}\n";
                return; // Stop on first failure for safety
            }
        }

        echo $this->badge('DONE', 'green') . " All migrations executed.\n";
    }


    /**
     * Display applied and pending migrations with colored badges.
     */
    public function status(): void
    {
        $pdo = Connection::instance();
        $this->ensureMigrationsTable($pdo);

        $files = glob($this->migrationsPath . '/*.sql');
        sort($files, SORT_NATURAL);
        $applied = $this->getAppliedMap($pdo);

        $total = count($files);
        if ($total === 0) {
            echo $this->badge('INFO', 'yellow') . " No migrations found in {$this->migrationsPath}\n";
            return;
        }

        // For nice alignment
        $names = array_map(static fn($f) => basename($f), $files);
        $maxLen = max(array_map('strlen', $names));

        echo $this->badge('LIST', 'cyan') . " Migrations in {$this->migrationsPath}\n";

        $countApplied = 0;
        $countPending = 0;

        foreach ($files as $file) {
            $name = basename($file);
            $isApplied = isset($applied[$name]);

            if ($isApplied) {
                $countApplied++;
                $statusBadge = $this->badge('APPLIED', 'green');
            } else {
                $countPending++;
                $statusBadge = $this->badge('PENDING', 'yellow');
            }

            // pretty line: "<badge> filename ...."
            $padded = str_pad($name, $maxLen, ' ', STR_PAD_RIGHT);
            echo "{$statusBadge}  {$padded}\n";
        }

        echo $this->badge('SUMMARY', 'blue') . " total: {$total}, ";
        echo $this->badge('APPLIED', 'green') . " {$countApplied}, ";
        echo $this->badge('PENDING', 'yellow') . " {$countPending}\n";
    }


    /**
     * Clear the migration history without touching user tables.
     * Useful when replaying all migrations from scratch.
     */
    public function reset(): void
    {
        $pdo = Connection::instance();
        $this->ensureMigrationsTable($pdo);
        $pdo->exec("DELETE FROM migrations");
        echo "Migration history cleared. Next 'ivi migrate' will re-run all files.\n";
    }

    /**
     * Ensure the `migrations` tracking table exists.
     */
    private function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    filename    TEXT UNIQUE NOT NULL,
                    checksum    TEXT NOT NULL,
                    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } elseif ($driver === 'pgsql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id          SERIAL PRIMARY KEY,
                    filename    VARCHAR(255) UNIQUE NOT NULL,
                    checksum    VARCHAR(64) NOT NULL,
                    executed_at TIMESTAMP NOT NULL DEFAULT NOW()
                )
            ");
        } else { // MySQL or compatible
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    filename    VARCHAR(255) NOT NULL UNIQUE,
                    checksum    VARCHAR(64) NOT NULL,
                    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Return a map of applied migrations and their checksums.
     *
     * @return array<string,string> filename => checksum
     */
    private function getAppliedMap(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT filename, checksum FROM migrations");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $map  = [];
        foreach ($rows as $r) {
            $map[$r['filename']] = $r['checksum'];
        }
        return $map;
    }
}
