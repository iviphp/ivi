<?php

declare(strict_types=1);

namespace Ivi\Core\Seeds;

use Ivi\Core\ORM\Connection;
use PDO;
use Throwable;

/**
 * SeederRunner
 *
 * Runs SQL seed files (*.sql) and PHP seeders (*Seeder.php) inside a directory.
 * PHP seeders must `return` an object with a `run()` method (signature `run()` or `run(PDO $pdo)`).
 *
 * Conventions:
 * - SQL: executed in natural sort order.
 * - PHP: files ending with *Seeder.php and returning an instance with `run`.
 */
final class SeederRunner
{
    public function __construct(private string $seedersPath)
    {
        $real = \realpath($this->seedersPath);
        if ($real === false || !is_dir($real)) {
            throw new \RuntimeException("Seeders directory not found: {$this->seedersPath}");
        }
        $this->seedersPath = $real;
    }

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

    public function run(): void
    {
        $pdo = Connection::instance();
        echo $this->badge('RUN', 'cyan') . " Running seeders in {$this->seedersPath}\n";

        // 1) SQL seeders
        $sqlFiles = glob($this->seedersPath . '/*.sql') ?: [];
        sort($sqlFiles, SORT_NATURAL);

        foreach ($sqlFiles as $file) {
            $name = basename($file);
            echo $this->badge('SQL', 'blue') . " Executing: {$name}\n";
            $sql = file_get_contents($file) ?: '';
            $hadTx = $pdo->inTransaction();
            if (!$hadTx) {
                $pdo->beginTransaction();
            }
            try {
                $pdo->exec($sql);
                if (!$hadTx && $pdo->inTransaction()) {
                    $pdo->commit();
                }
                echo $this->badge('OK', 'green') . " {$name} executed.\n";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo $this->badge('ERROR', 'red') . " in {$name}: {$e->getMessage()}\n";
                return;
            }
        }

        // 2) PHP seeders
        $phpFiles = glob($this->seedersPath . '/*Seeder.php') ?: [];
        sort($phpFiles, SORT_NATURAL);

        foreach ($phpFiles as $file) {
            $name = basename($file);
            echo $this->badge('PHP', 'blue') . " Running: {$name}\n";
            try {
                $seeder = require $file; // must return an instance with run()
                if (!\is_object($seeder) || !\method_exists($seeder, 'run')) {
                    echo $this->badge('SKIP', 'gray') . " {$name} did not return a runnable seeder.\n";
                    continue;
                }

                // Support both signatures: run() and run(PDO $pdo)
                $rm = new \ReflectionMethod($seeder, 'run');
                if ($rm->getNumberOfParameters() >= 1) {
                    $seeder->run($pdo);
                } else {
                    $seeder->run();
                }

                echo $this->badge('OK', 'green') . " {$name} completed.\n";
            } catch (Throwable $e) {
                echo $this->badge('ERROR', 'red') . " in {$name}: {$e->getMessage()}\n";
                return;
            }
        }

        echo $this->badge('DONE', 'green') . " All seeders completed.\n";
    }
}
