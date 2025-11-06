<?php

declare(strict_types=1);

namespace Ivi\Core\Console;

use Ivi\Core\Migrations\Migrator;

final class CommandRunner
{
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

    public function run(array $argv): void
    {
        $cmd = $argv[1] ?? null;

        if ($cmd === null || in_array($cmd, ['-h', '--help', 'help'], true)) {
            $this->help();
            return;
        }

        $migrator = new Migrator(
            migrationsPath: \dirname(__DIR__, 2) . '/scripts/migrations'
        );

        switch ($cmd) {
            case 'migrate':
                $migrator->migrate();
                break;

            case 'migrate:status':
            case 'status':
                $migrator->status();
                break;

            case 'migrate:reset':
            case 'reset':
                $migrator->reset();
                break;

            default:
                echo $this->badge('ERROR', 'red') . " Unknown command: {$cmd}\n";
                $this->help();
        }
    }

    private function help(): void
    {
        echo $this->badge('IVI', 'cyan') . " Ivi.php CLI\n";
        echo "Usage:\n";
        echo "  ivi migrate         Run SQL migrations (new ones only)\n";
        echo "  ivi migrate:status  Show applied & pending migrations\n";
        echo "  ivi migrate:reset   Forget applied migrations (does NOT drop tables)\n";
        echo "\nAliases:\n";
        echo "  ivi status          (alias of migrate:status)\n";
        echo "  ivi reset           (alias of migrate:reset)\n";
    }
}
