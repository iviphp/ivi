<?php

declare(strict_types=1);

/**
 * Ultra-early handlers. Aucun namespace ici.
 * Essaie d'utiliser \Ivi\Core\Debug\Logger si dispo, sinon fallback minimal (CLI/HTML).
 */

(function () {
    if (defined('__IVI_EARLY_ERRORS__')) return;
    define('__IVI_EARLY_ERRORS__', true);

    $lock = false;
    $isCli = (PHP_SAPI === 'cli');

    // Base du projet (pas besoin de BASE_PATH ici)
    $base = rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR);

    // Reconstitute the real controller handler frame (class@method) from Route::invokeMethod args
    $findUserHandler = function (array $trace): ?array {
        foreach ($trace as $f) {
            $cls  = $f['class']    ?? '';
            $func = $f['function'] ?? '';
            if ($cls === 'Ivi\\Router\\Route' && $func === 'invokeMethod') {
                $args = $f['args'] ?? [];

                // Case A: [$controllerObj, 'method']
                if (isset($args[0], $args[1]) && is_object($args[0]) && is_string($args[1])) {
                    $controllerObj = $args[0];
                    $method        = $args[1];
                    $ctrlClass     = get_class($controllerObj);
                    if (str_starts_with($ctrlClass, 'App\\')) {
                        try {
                            $rm   = new \ReflectionMethod($ctrlClass, $method);
                            $file = $rm->getFileName() ?: '';
                            $line = $rm->getStartLine() ?: 0;
                            if ($file !== '') {
                                return [
                                    'file'     => $file,
                                    'line'     => $line,
                                    'class'    => $ctrlClass,
                                    'function' => $method,
                                    '__synthetic' => true,
                                ];
                            }
                        } catch (\Throwable $__) {
                        }
                    }
                }

                // Case B: a string like 'App\Controllers\X@method' somewhere in args
                foreach ($args as $a) {
                    if (is_string($a) && ($p = strpos($a, '@')) !== false) {
                        $ctrlClass = substr($a, 0, $p);
                        $method    = substr($a, $p + 1);
                        if ($ctrlClass && $method && class_exists($ctrlClass)) {
                            try {
                                $rm   = new \ReflectionMethod($ctrlClass, $method);
                                $file = $rm->getFileName() ?: '';
                                $line = $rm->getStartLine() ?: 0;
                                if ($file !== '') {
                                    return [
                                        'file'     => $file,
                                        'line'     => $line,
                                        'class'    => $ctrlClass,
                                        'function' => $method,
                                        '__synthetic' => true,
                                    ];
                                }
                            } catch (\Throwable $__) {
                            }
                        }
                    }
                }
            }
        }
        return null;
    };

    // Filtre "balanced": garde le 1er frame App\ (cause), puis filtre pour ne garder que Ivi\* et exclure /src/
    $filterFrames = function (array $trace) use ($base, $findUserHandler): array {
        $frames = [];

        // Always inject the real user handler (HomeController::home) first if we can reconstruct it
        $user = $findUserHandler($trace);
        if ($user) {
            $frames[] = $user;
        }

        $keptFirstApp = false;
        foreach ($trace as $f) {
            $cls  = $f['class'] ?? '';
            $file = $f['file']  ?? '';

            // Avoid duplicating the injected user frame
            if ($user && isset($f['class'], $f['function'], $f['file'], $f['line'])) {
                if (
                    $f['class'] === ($user['class'] ?? '') &&
                    $f['function'] === ($user['function'] ?? '') &&
                    $f['file'] === ($user['file'] ?? '') &&
                    $f['line'] === ($user['line'] ?? '')
                ) {
                    continue;
                }
            }

            // 1) Keep the first App\ frame (direct cause), even if under /src/
            if (!$keptFirstApp && $cls !== '' && str_starts_with($cls, 'App\\')) {
                $frames[] = $f;
                $keptFirstApp = true;
                continue;
            }

            // 2) Then keep framework frames only, and exclude app /src/
            if ($cls !== '' && !str_starts_with($cls, 'Ivi\\')) continue;
            if ($file !== '' && str_starts_with($file, $base . '/src/')) continue;

            $frames[] = $f;
        }

        return $frames;
    };

    // Rendre les chemins relatifs et propres
    $relPath = function (?string $file) use ($base): string {
        if (!$file) return '[internal]';
        if (str_starts_with($file, $base)) {
            return substr($file, strlen($base) + 1);
        }
        return $file;
    };


    /**
     * Tente d’invoquer Ivi\Core\Debug\Logger::exception($e, $context, ['exit'=>false])
     * Charge vendor/autoload.php si nécessaire et présent.
     */
    $tryLogger = function (\Throwable $e, array $context = []) use (&$lock): bool {
        if ($lock) return false;
        $lock = true;

        if (!class_exists(\Ivi\Core\Debug\Logger::class)) {
            $root = dirname(__DIR__);
            $autoload = $root . '/vendor/autoload.php';
            if (is_file($autoload)) {
                try {
                    require_once $autoload;
                } catch (\Throwable $__) {
                }
            }
        }

        if (class_exists(\Ivi\Core\Debug\Logger::class)) {
            try {
                \Ivi\Core\Debug\Logger::exception($e, $context, ['exit' => false]);
                $lock = false;
                return true;
            } catch (\Throwable $__) {
            }
        }

        $lock = false;
        return false;
    };

    /** Fallback CLI minimal */
    $printCli = function (\Throwable $e, array $context = []) use ($filterFrames, $relPath, $base) {
        $acc = "\033[38;5;34m";
        $mut = "\033[0;37m";
        $rst = "\033[0m";
        $ts = date('Y-m-d H:i:s');

        $all    = $e->getTrace();
        $frames = $filterFrames($all);              // ← utilise le filtre balanced
        $hidden = max(0, count($all) - count($frames));
        $frames = array_slice($frames, 0, 10);

        // Résumé basé sur le 1er frame filtré si dispo, sinon sur l’exception
        $sumFile = $relPath($frames[0]['file'] ?? $e->getFile());
        $sumLine = (string)($frames[0]['line'] ?? $e->getLine());

        fwrite(STDERR, "{$acc}ivi.php Early Error{$rst} — {$mut}{$ts}{$rst}\n");
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() .
            ' at ' . $sumFile . ':' . $sumLine . "\n\n");

        if ($frames) {
            fwrite(STDERR, "{$acc}Stack trace (top 10):{$rst}\n");
            foreach ($frames as $i => $f) {
                $file = $relPath($f['file'] ?? null);
                $line = $f['line'] ?? '-';
                $func = $f['function'] ?? '';
                fwrite(STDERR, "  #$i $file:$line $func()\n");
            }
            if ($hidden > 0) {
                fwrite(STDERR, "  (+{$hidden} frames masqués)\n");
            }
        }

        if (!empty($context)) {
            fwrite(STDERR, "\n{$acc}Context{$rst}\n");
            fwrite(STDERR, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }
    };

    $printHtml = function (\Throwable $e, array $context = []) use ($filterFrames, $relPath) {
        $all    = $e->getTrace();
        $frames = $filterFrames($all);
        $hidden = max(0, count($all) - count($frames));
        $frames = array_slice($frames, 0, 10);

        $sumFile = htmlspecialchars($relPath($frames[0]['file'] ?? $e->getFile()), ENT_QUOTES);
        $sumLine = htmlspecialchars((string)($frames[0]['line'] ?? $e->getLine()), ENT_QUOTES);
        $summary = htmlspecialchars(get_class($e) . ': ' . $e->getMessage(), ENT_QUOTES)
            . ' at ' . $sumFile . ':' . $sumLine
            . '  (thrown in ' . htmlspecialchars($relPath($e->getFile()), ENT_QUOTES) . ':' . (int)$e->getLine() . ')';


        // Trace filtrée
        $trace = '';
        foreach ($frames as $i => $f) {
            $file = htmlspecialchars($relPath($f['file'] ?? null), ENT_QUOTES);
            $line = htmlspecialchars((string)($f['line'] ?? '-'), ENT_QUOTES);
            $func = htmlspecialchars((string)($f['function'] ?? ''), ENT_QUOTES);
            $trace .= "<div>#{$i} <strong>{$file}</strong>:<span style=\"color:#008037;font-weight:700;\">{$line}</span> {$func}()</div>";
        }
        if ($hidden > 0) {
            $trace = "<div style=\"opacity:.7\">(+{$hidden} frames masqués)</div>" . $trace;
        }

        $ctxJson = htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES);

        echo <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>ivi.php Early Error</title>
<style>/* styles inchangés */</style></head><body>
<header>ivi.php Early Error</header>
<main>
  <section class="panel"><div class="head">Fatal / Bootstrap exception</div><div class="body"><pre>{$summary}</pre></div></section>
  <section class="panel"><div class="head">Stack trace (top 10)</div><div class="body">{$trace}</div></section>
  <section class="panel"><div class="head">Context</div><div class="body"><pre>{$ctxJson}</pre></div></section>
</main></body></html>
HTML;
    };


    // Convertit warnings/notices en exceptions (sauf @-silenced)
    set_error_handler(function (int $severity, string $message, ?string $file = null, ?int $line = null) {
        if (!(error_reporting() & $severity)) return false; // respecter @
        throw new ErrorException($message, 0, $severity, $file ?? 'unknown', $line ?? 0);
    });

    // Exceptions non-capturées
    set_exception_handler(function (\Throwable $e) use ($isCli, $tryLogger, $printCli, $printHtml) {
        $context = [
            '_SERVER' => [
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
                'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? null,
                'HTTP_HOST'      => $_SERVER['HTTP_HOST'] ?? null,
                'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'REMOTE_ADDR'    => $_SERVER['REMOTE_ADDR'] ?? null,
            ],
            '_GET'  => $_GET  ?? [],
            '_POST' => $_POST ?? [],
        ];

        // Essai Logger (sans exit)
        if ($tryLogger($e, $context)) return;

        // Fallback
        if ($isCli) $printCli($e, $context);
        else {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            $printHtml($e, $context);
        }
    });

    // Fatals (parse/require/memory, etc.)
    register_shutdown_function(function () use ($isCli, $tryLogger, $printCli, $printHtml) {
        $err = error_get_last();
        if (!$err) return;

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'] ?? 0, $fatalTypes, true)) return;

        $e = new ErrorException(
            $err['message'] ?? 'Fatal error',
            0,
            $err['type'] ?? E_ERROR,
            $err['file'] ?? 'unknown',
            $err['line'] ?? 0
        );

        $context = [
            '_SERVER' => [
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
                'REQUEST_URI'    => $_SERVER['REQUEST_URI'] ?? null,
                'HTTP_HOST'      => $_SERVER['HTTP_HOST'] ?? null,
                'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'REMOTE_ADDR'    => $_SERVER['REMOTE_ADDR'] ?? null,
            ],
            '_GET'  => $_GET  ?? [],
            '_POST' => $_POST ?? [],
        ];

        // Essai Logger (sans exit)
        if ($tryLogger($e, $context)) return;

        // Fallback
        if ($isCli) $printCli($e, $context);
        else {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            $printHtml($e, $context);
        }
    });
})();
