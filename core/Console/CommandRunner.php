<?php

declare(strict_types=1);

namespace Ivi\Core\Console;

use Ivi\Core\Migrations\Migrator;

/**
 * --------------------------------------------------------------------------
 * Ivi.php Command Runner
 * --------------------------------------------------------------------------
 * Handles CLI commands:
 *   - migrate, migrate:status, migrate:reset
 *   - test, coverage
 *   - make:module <Name>
 *   - help
 * --------------------------------------------------------------------------
 */
final class CommandRunner
{
    /** Small colored badge */
    private static function badge(string $label, string $color): string
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
            self::help();
            exit(0);
        }

        $migrator = new Migrator(
            migrationsPath: \dirname(__DIR__, 2) . '/scripts/migrations'
        );

        switch ($cmd) {
            /* ----------------------------- Migrations ----------------------------- */
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

            /* ----------------------------- Tests & Coverage ----------------------------- */
            case 'test':
                echo self::badge('TEST', 'blue') . " Running PHPUnit tests...\n\n";
                passthru('composer test', $code);
                exit((int) $code);

            case 'coverage':
                echo self::badge('COVERAGE', 'yellow') . " Running tests with coverage...\n\n";
                passthru('XDEBUG_MODE=coverage vendor/bin/phpunit', $code);
                exit((int) $code);

                /* ----------------------------- Module Generator ----------------------------- */
            case 'make:module':
                $name = $argv[2] ?? null;
                if (!$name) {
                    echo self::badge('ERROR', 'red') . " Missing module name.\n";
                    echo "Usage: ivi make:module <Name>\n";
                    exit(1);
                }
                $this->generateModule($name);
                break;

            default:
                echo self::badge('ERROR', 'red') . " Unknown command: {$cmd}\n\n";
                self::help();
                exit(1);
        }
    }

    /* ---------------------------------------------------------------------- */
    /* HELP OUTPUT                                                            */
    /* ---------------------------------------------------------------------- */
    private static function help(): void
    {
        echo self::badge('IVI', 'cyan') . " Ivi.php CLI\n";
        echo "Usage:\n";
        echo "  ivi new <name>                    Create a new Ivi.php project via Composer\n";
        echo "\n";
        echo "Database:\n";
        echo "  ivi migrate                       Run SQL migrations (global + modules)\n";
        echo "  ivi migrate:status                Show applied & pending migrations\n";
        echo "  ivi migrate:reset                 Forget applied migrations (does NOT drop tables)\n";
        echo "  ivi seed                          Run database seeders (global + modules)\n";
        echo "  ivi db:seed                       Alias of 'seed'\n";
        echo "\n";
        echo "Modules & Assets:\n";
        echo "  ivi make:module <Name>            Generate a new module skeleton\n";
        echo "  ivi modules:publish-assets        Publish each module's public/ into public/modules/\n";
        echo "      --copy                        Copy instead of symlink (default on Windows)\n";
        echo "      --force                       Overwrite existing links/directories\n";
        echo "\n";
        echo "Dev & Tests:\n";
        echo "  ivi test                          Run PHPUnit tests\n";
        echo "  ivi coverage                      Run tests with coverage (requires Xdebug/PCOV)\n";
        echo "  ivi serve:run [--host=H] [--port=P] [--docroot=DIR]\n";
        echo "  ivi serve                         Alias of 'serve:run'\n";
        echo "\n";
        echo "Deploy:\n";
        echo "  ivi deploy [--dev] [--force] [--no-install]\n";
        echo "      --dev                         Install dev dependencies too\n";
        echo "      --force                       Force-republish module assets\n";
        echo "      --no-install                  Skip composer install\n";
        echo "\n";
        echo "Aliases:\n";
        echo "  ivi status                        Alias of 'migrate:status'\n";
        echo "  ivi reset                         Alias of 'migrate:reset'\n";
        echo "  ivi db:seed                       Alias of 'seed'\n";
        echo "  ivi serve                         Alias of 'serve:run'\n";
    }

    /* ---------------------------------------------------------------------- */
    /* MODULE GENERATOR                                                       */
    /* ---------------------------------------------------------------------- */
    private function generateModule(string $rawName): void
    {
        $cwd = getcwd();
        $libBase = \dirname(__DIR__, 2);
        $base = is_dir($cwd) && is_writable($cwd) ? $cwd : $libBase;

        $name = $this->studly($rawName);
        $moduleDir = "{$base}/modules/{$name}";

        if (is_dir($moduleDir)) {
            echo self::badge('WARN', 'yellow') . " Module '{$name}' already exists.\n";
            return;
        }

        // Base structure with lowercase assets
        $dirs = [
            "{$moduleDir}/Core/Services",
            "{$moduleDir}/Core/Tests",
            "{$moduleDir}/Core/Config",
            "{$moduleDir}/Core/Database/Migrations",
            "{$moduleDir}/Core/Database/Seeders",
            "{$moduleDir}/Core/Public/assets/css",
            "{$moduleDir}/Core/Public/assets/js",
            "{$moduleDir}/Core/routes",
            "{$moduleDir}/Core/views",
            "{$moduleDir}/Core/Http/Controllers",
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                echo self::badge('ERROR', 'red') . " Failed to create directory: {$dir}\n";
                exit(1);
            }
        }

        /* ------------------- Service ------------------- */
        $serviceFile = "{$moduleDir}/Core/Services/{$name}Service.php";
        $serviceContent = <<<PHP
<?php
declare(strict_types=1);

namespace Modules\\{$name}\\Core\Services;

final class {$name}Service
{
    public function info(): string
    {
        return 'Module {$name} loaded successfully.';
    }
}
PHP;
        file_put_contents($serviceFile, $serviceContent);

        /* ------------------- Module.php (implements ModuleContract) ------------------- */
        $modulePhp = "{$moduleDir}/Core/Module.php";
        $moduleContent = <<<PHP
<?php

use App\Modules\ModuleContract;
use Ivi\Core\Router\Router;

return new class implements ModuleContract {
    public function name(): string
    {
        return '{$name}/Core';
    }

    public function register(): void
    {
        \$path = __DIR__ . '/Config/' . strtolower('{$name}') . '.php';
        if (is_file(\$path)) {
            \$cfg = require \$path;
            \$current = function_exists('config') ? (array) config(strtolower('{$name}'), []) : (array) (\$GLOBALS['__ivi_config'][strtolower('{$name}')] ?? []);
            \$merged = array_replace_recursive(\$current, (array) \$cfg);
            if (function_exists('config_set')) {
                config_set(strtolower('{$name}'), \$merged);
            } else {
                \$GLOBALS['__ivi_config'][strtolower('{$name}')] = \$merged;
            }
        }
    }

    public function boot(Router \$router): void
    {
        \App\Controllers\Controller::addViewNamespace(strtolower('{$name}'), __DIR__ . '/views');

        \$routes = __DIR__ . '/routes/web.php';
        if (is_file(\$routes)) {
            require \$routes;
        }

        \$mig = __DIR__ . '/Database/Migrations';
        if (is_dir(\$mig)) {
            if (function_exists('migrations')) {
                \$mgr = migrations();
                if (is_object(\$mgr) && method_exists(\$mgr, 'addPath')) {
                    \$mgr->addPath(\$mig);
                } else {
                    \$GLOBALS['__ivi_migration_paths'] ??= [];
                    if (!in_array(\$mig, \$GLOBALS['__ivi_migration_paths'], true)) {
                        \$GLOBALS['__ivi_migration_paths'][] = \$mig;
                    }
                }
            } else {
                \$GLOBALS['__ivi_migration_paths'] ??= [];
                if (!in_array(\$mig, \$GLOBALS['__ivi_migration_paths'], true)) {
                    \$GLOBALS['__ivi_migration_paths'][] = \$mig;
                }
            }
        }

        \$seed = __DIR__ . '/Database/Seeders';
        if (is_dir(\$seed)) {
            \$GLOBALS['__ivi_seeder_paths'] ??= [];
            if (!in_array(\$seed, \$GLOBALS['__ivi_seeder_paths'], true)) {
                \$GLOBALS['__ivi_seeder_paths'][] = \$seed;
            }
        }
    }
};
PHP;
        file_put_contents($modulePhp, $moduleContent);

        /* ------------------- Config ------------------- */
        $configFile = "{$moduleDir}/Core/Config/" . strtolower($name) . ".php";
        $configContent = <<<PHP
<?php
return [
    'title' => 'Ivi {$name}',
];
PHP;
        file_put_contents($configFile, $configContent);

        /* ------------------- Public assets ------------------- */
        $cssFile = "{$moduleDir}/Core/Public/assets/css/style.css";
        $jsFile  = "{$moduleDir}/Core/Public/assets/js/script.js";
        file_put_contents($cssFile, "/* CSS for {$name} module */\nbody { font-family: sans-serif; }\n");
        file_put_contents($jsFile, "// JS for {$name} module\nconsole.log('{$name} module loaded');\n");

        /* ------------------- views ------------------- */
        $viewFile = "{$moduleDir}/Core/views/home.php";
        $viewContent = <<<PHP
<h1>Welcome to {$name} Module</h1>
<p>This is the default home view for the {$name} module.</p>
<p>Message from controller: <?= htmlspecialchars(\$message) ?></p>
<?= \$styles ?? '' ?>
<?= \$scripts ?? '' ?>
PHP;
        file_put_contents($viewFile, $viewContent);

        /* ------------------- Routes ------------------- */
        $routesFile = "{$moduleDir}/Core/routes/web.php";
        $homeControllerClass = "Modules\\{$name}\\Core\\Http\\Controllers\\HomeController";
        $routesContent = <<<PHP
<?php
use {$homeControllerClass};
use Ivi\Http\JsonResponse;

/** @var \Ivi\Core\Router\Router \$router */
\$router->get('/{$this->snake($name)}', [HomeController::class, 'index']);
\$router->get('/{$this->snake($name)}/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => '{$name}/Core'
]));
PHP;
        file_put_contents($routesFile, $routesContent);

        /* ------------------- HomeController ------------------- */
        $controllerFile = "{$moduleDir}/Core/Http/Controllers/HomeController.php";
        $controllerContent = <<<PHP
<?php
namespace Modules\\{$name}\\Core\\Http\\Controllers;

use App\Controllers\Controller;
use Ivi\Http\HtmlResponse;

class HomeController extends Controller
{
    public function index(): HtmlResponse
    {
        \$title = (string) (cfg(strtolower('{$name}') . '.title', 'Softadastra {$name}') ?: 'Softadastra {$name}');
        \$this->setPageTitle(\$title);

        \$message = "Hello from {$name}Controller!";
        \$styles  = '<link rel="stylesheet" href="' . asset("assets/css/style.css") . '">';
        \$scripts = '<script src="' . asset("assets/js/script.js") . '" defer></script>';

        return \$this->view(strtolower('{$name}') . '::home', [
            'title'   => \$title,
            'message' => \$message,
            'styles'  => \$styles,
            'scripts' => \$scripts,
        ]);
    }
}
PHP;
        file_put_contents($controllerFile, $controllerContent);

        /* ------------------- Sample migration & seeder ------------------- */
        $migName = date('YmdHis') . "_create_{$this->snake($name)}_table.sql";
        $migrationFile = "{$moduleDir}/Core/Database/Migrations/{$migName}";
        $migrationSql = <<<SQL
CREATE TABLE IF NOT EXISTS {$this->snake($name)} (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        file_put_contents($migrationFile, $migrationSql);

        $seederFile = "{$moduleDir}/Core/Database/Seeders/{$name}Seeder.php";
        $seederPhp = <<<PHP
<?php
declare(strict_types=1);

namespace Modules\\{$name}\\Core\\Database\\Seeders;

final class {$name}Seeder
{
    public function run(): void
    {
        echo "[seed] {$name} ok\\n";
    }
}

return new {$name}Seeder();
PHP;
        file_put_contents($seederFile, $seederPhp);

        /* ------------------- Update modules config ------------------- */
        $this->ensureModulesConfig($base, $name);

        passthru('composer dump-autoload -o', $code);

        echo self::badge('OK', 'green') . " Module '{$name}' scaffolded in modules/{$name}\n";
        echo self::badge('TIP', 'yellow') . " Next steps:\n";
        echo "  - Run migrations: ivi migrate\n";
        echo "  - Seed (optional): ivi seed\n";
        echo "  - Use service: new Modules\\{$name}\\Core\\{$name}Service()\n";
    }


    /* ---------------------------------------------------------------------- */
    /* UTILS                                                                  */
    /* ---------------------------------------------------------------------- */

    /** Convert "blog_posts" or "blog-posts" to "BlogPosts" */
    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /** Convert "BlogPosts" to "blog_posts" */
    private function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value);
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1' . '_', $value);
        return strtolower((string) $value);
    }

    /**
     * Ensure config/modules.php exists and contains the new module slug.
     * Expected structure:
     *   return ['modules' => ['Market', 'Blog', ...]];
     */
    private function ensureModulesConfig(string $baseDir, string $name): void
    {
        $cfgFile = $baseDir . '/config/modules.php';
        if (!is_file($cfgFile)) {
            @mkdir($baseDir . '/config', 0775, true);
            $tpl = <<<PHP
<?php

declare(strict_types=1);

return [
    // List of enabled modules (folder names under /modules)
    'modules' => [
    ],
];
PHP;
            file_put_contents($cfgFile, $tpl);
        }

        $data = require $cfgFile;
        if (!is_array($data)) {
            $data = ['modules' => []];
        }
        if (!isset($data['modules']) || !is_array($data['modules'])) {
            $data['modules'] = [];
        }

        if (!in_array($name, $data['modules'], true)) {
            $data['modules'][] = $name;
            // write back
            $export = var_export($data, true);
            $php = <<<PHP
<?php

declare(strict_types=1);

return {$export};
PHP;
            file_put_contents($cfgFile, $php);
            echo self::badge('OK', 'green') . " Updated config/modules.php (added '{$name}')\n";
        } else {
            echo self::badge('SKIP', 'gray') . " config/modules.php already contains '{$name}'\n";
        }
    }

    /**
     * Ensure composer.json has PSR-4 entry for "<Name>\Core\" → "modules/<Name>/Core/src/"
     */
    private function ensureComposerPsr4(string $baseDir, string $ns, string $path): void
    {
        $composerFile = $baseDir . '/composer.json';
        if (!is_file($composerFile)) {
            echo self::badge('WARN', 'yellow') . " composer.json not found — skipping PSR-4 autoload update.\n";
            return;
        }

        $json = json_decode((string) file_get_contents($composerFile), true);
        if (!is_array($json)) {
            echo self::badge('WARN', 'yellow') . " Invalid composer.json — skipping PSR-4 autoload update.\n";
            return;
        }

        $json['autoload'] ??= [];
        $json['autoload']['psr-4'] ??= [];

        if (!array_key_exists($ns, $json['autoload']['psr-4'])) {
            $json['autoload']['psr-4'][$ns] = rtrim($path, '/') . '/';

            // Sort for neatness if sort-packages is not enabled
            ksort($json['autoload']['psr-4']);

            $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            file_put_contents($composerFile, $encoded);

            echo self::badge('OK', 'green') . " composer.json PSR-4 autoload updated: \"{$ns}\": \"{$path}/\"\n";
        } else {
            echo self::badge('SKIP', 'gray') . " composer.json already has PSR-4 for {$ns}\n";
        }
    }
}
