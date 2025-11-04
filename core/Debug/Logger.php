<?php

namespace Ivi\Core\Debug;

final class Logger
{
    /** @var array<string,mixed> */
    private static array $config = [
        'theme' => 'light',
        'accent' => '#008037',
        'max_trace' => 10,
        'exit' => true,
        'label' => null,
        'verbosity' => 'normal',
        'show_payload' => true,
        'show_trace' => true,
        'show_context' => true,
        // NEW / CHANGE:
        'trace_strategy' => 'balanced',
        'trace_exclude_namespaces' => [],
        'trace_exclude_paths'      => [],   // <– vide, pas de BASE_PATH ici
        'trace_only_namespaces'    => [],
        // NEW: namespaces applicatifs (où vivent tes contrôleurs/userland)
        'app_namespaces'           => ['Ivi\\Controllers\\', 'App\\'],
    ];

    public static function configure(array $cfg): void
    {
        self::$config = array_replace(self::$config, $cfg);
    }

    private static function basePath(): string
    {
        static $root = null;
        if ($root !== null) return $root;

        // Si la constante globale existe, on l'utilise
        if (\defined('BASE_PATH')) {
            return $root = \BASE_PATH;
        }

        // Fallback: on remonte depuis /core/Debug -> / (racine projet)
        // __DIR__ = .../core/Debug
        $guess = \realpath(\dirname(__DIR__, 2)); // ../../
        if ($guess === false) {
            $guess = \dirname(__DIR__, 2);
        }
        return $root = $guess;
    }

    /**
     * Premier frame App\ vraiment utile (évite Controller::render/view).
     * Si absent dans la trace (ex: Router masque), on reconstruit depuis Route::invokeMethod().
     */
    private static function firstUserAppFrame(array $trace): ?array
    {
        $skipClasses  = []; // on ignore par suffixe \Controller
        $skipMethods  = ['render', 'view', 'capture', 'dotToPath', 'viewsBasePath'];
        $appNamespaces = (array)(self::$config['app_namespaces'] ?? ['App\\']);

        // 1) Cherche un vrai frame App\* (ou Ivi\Controllers\*) dans la trace
        foreach ($trace as $f) {
            $cls  = $f['class']    ?? '';
            $func = $f['function'] ?? '';
            $file = $f['file']     ?? '';

            if ($cls !== '') {
                $isApp = false;
                foreach ($appNamespaces as $ns) {
                    if (str_starts_with($cls, $ns)) {
                        $isApp = true;
                        break;
                    }
                }
                if ($isApp) {
                    // ignorer le Controller de base et ses helpers
                    if ((str_ends_with($cls, '\\Controller') || in_array($cls, $skipClasses, true))
                        && ($func === '' || in_array($func, $skipMethods, true))
                    ) {
                        continue;
                    }
                    return $f; // frame utile
                }
            }

            // fallback: fichier userland sans classe sous /src/
            if ($cls === '' && $file !== '' && str_contains($file, DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR)) {
                return $f;
            }
        }

        // 2) Fallback: Route::invokeMethod peut porter l'objet contrôleur et la méthode
        foreach ($trace as $f) {
            $cls  = $f['class']    ?? '';
            $func = $f['function'] ?? '';
            if ($cls === 'Ivi\\Router\\Route' && $func === 'invokeMethod') {
                $args = $f['args'] ?? [];

                // Cas 1: [$controllerObj, 'method']
                if (isset($args[0], $args[1]) && is_object($args[0]) && is_string($args[1])) {
                    $controllerObj = $args[0];
                    $method        = $args[1];
                    $ctrlClass     = get_class($controllerObj);

                    $isApp = false;
                    foreach ($appNamespaces as $ns) {
                        if (str_starts_with($ctrlClass, $ns)) {
                            $isApp = true;
                            break;
                        }
                    }
                    if ($isApp) {
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
                                ];
                            }
                        } catch (\Throwable $__) {
                        }
                    }
                }

                // Cas 2: "Class@method"
                foreach ($args as $a) {
                    if (is_string($a) && ($pos = strpos($a, '@')) !== false) {
                        $ctrlClass = substr($a, 0, $pos);
                        $method    = substr($a, $pos + 1);

                        $isApp = false;
                        foreach ($appNamespaces as $ns) {
                            if (str_starts_with($ctrlClass, $ns)) {
                                $isApp = true;
                                break;
                            }
                        }
                        if ($isApp && $ctrlClass && $method && class_exists($ctrlClass)) {
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
    }

    /** Fallback générique : premier frame App\ (peut pointer sur Controller::render) */
    private static function firstAppFrame(array $trace): ?array
    {
        $appNamespaces = (array)(self::$config['app_namespaces'] ?? ['App\\']);

        foreach ($trace as $f) {
            $cls  = $f['class'] ?? '';
            $file = $f['file']  ?? '';

            if ($cls !== '') {
                foreach ($appNamespaces as $ns) {
                    if (str_starts_with($cls, $ns)) return $f;
                }
            }
            if ($file !== '' && str_contains($file, DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR)) {
                return $f;
            }
        }
        return null;
    }

    // helper interne
    private static function frameIsVisible(array $f): bool
    {
        $cls  = $f['class'] ?? '';
        $file = $f['file']  ?? '';

        // Exclusions par namespace
        foreach (self::$config['trace_exclude_namespaces'] as $ns) {
            if ($cls !== '' && str_starts_with($cls, $ns)) return false;
        }
        // Exclusions par chemin
        foreach (self::$config['trace_exclude_paths'] as $p) {
            if ($file !== '' && str_starts_with($file, $p)) return false;
        }

        // Exclusions par chemin
        foreach (self::$config['trace_exclude_paths'] as $p) {
            if ($file !== '') {
                $fileNorm = str_replace('\\', '/', $file);
                $pNorm    = rtrim(str_replace('\\', '/', $p), '/');
                if ($pNorm !== '' && str_starts_with($fileNorm, $pNorm)) {
                    return false;
                }
            }
        }

        // Si on a "only", on garde seulement ces namespaces
        $only = self::$config['trace_only_namespaces'] ?? [];
        if (!empty($only)) {
            $ok = false;
            foreach ($only as $ns) {
                if (($cls !== '' && str_starts_with($cls, $ns)) || $cls === '') {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) return false;
        }
        return true;
    }

    private static function cleanFile(string $file): string
    {
        if ($file === '') return $file;

        $root = self::basePath();

        // Normaliser pour éviter les surprises
        $fileNorm = str_replace('\\', '/', $file);
        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');

        if (str_starts_with($fileNorm, $rootNorm)) {
            $rel = substr($fileNorm, strlen($rootNorm) + 1);
            return $rel !== false ? $rel : $file;
        }
        return $file;
    }

    public static function exception(\Throwable $e, array $context = [], array $options = []): void
    {
        $cfg = array_replace(self::$config, $options);

        // Mode CLI
        if (PHP_SAPI === 'cli') {
            self::renderCliException($e, $context, $cfg);
            if ($cfg['exit']) exit(1);
            return;
        }

        // Mode Web
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        echo self::renderHtmlException($e, $context, $cfg);
        if ($cfg['exit']) exit(1);
    }

    /* ================= CLI ================= */
    private static function renderCliException(\Throwable $e, array $context, array $cfg): void
    {
        $accent = "\033[38;5;34m";
        $muted  = "\033[0;37m";
        $reset  = "\033[0m";

        $thrownFile = self::cleanFile($e->getFile());
        $thrownLine = (string)$e->getLine();

        // <<< priorité au callsite
        if ($cs = self::callsiteOrNull()) {
            $appFile = self::cleanFile($cs['file']);
            $appLine = (string)($cs['line'] ?? '-');
            echo "{$muted}" . get_class($e) . "{$reset}: {$e->getMessage()} ({$appFile}:{$appLine})";
            echo "  (thrown in {$thrownFile}:{$thrownLine})\n\n";
        } else {
            echo "{$muted}" . get_class($e) . "{$reset}: {$e->getMessage()} ({$thrownFile}:{$thrownLine})\n\n";
        }
        // >>>

        echo "{$accent}--- ivi.php Debug ---{$reset}\n";

        if (!empty($cfg['show_trace'])) {
            echo "{$accent}Trace:{$reset}\n";

            $all    = $e->getTrace();
            $frames = array_values(array_filter($all, [self::class, 'frameIsVisible']));

            // Injecter le callsite comme frame #0 s'il n'est pas déjà là
            if ($cs = self::callsiteOrNull()) {
                $already = false;
                foreach ($frames as $f) {
                    if (($f['file'] ?? null) === $cs['file'] && ($f['line'] ?? null) === $cs['line']) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    array_unshift($frames, [
                        'file' => $cs['file'],
                        'line' => $cs['line'],
                        'class' => $cs['class'] ?? '',
                        'function' => $cs['method'] ?? '',
                    ]);
                }
            }

            $hidden = max(0, count($all) - count($frames));
            $frames = array_slice($frames, 0, (int)($cfg['max_trace'] ?? 10));

            foreach ($frames as $i => $f) {
                $file = self::cleanFile($f['file'] ?? '[internal]');
                $line = $f['line'] ?? '-';
                echo "  #$i $file:$line\n";
            }
            if ($hidden > 0) echo "  (+{$hidden} frames masqués)\n";
            echo "\n";
        }

        if (!empty($cfg['show_context'])) {
            echo "{$accent}Context:{$reset}\n";
            print_r($context);
        }
    }


    private static function callsiteOrNull(): ?array
    {
        try {
            if (class_exists(\Ivi\Core\Debug\Callsite::class)) {
                $cs = \Ivi\Core\Debug\Callsite::get();
                if (is_array($cs) && !empty($cs['file'])) return $cs;
            }
        } catch (\Throwable $_) {
        }
        return null;
    }

    /* ================= WEB ================= */
    private static function renderHtmlException(\Throwable $e, array $context, array $cfg): string
    {
        $accent = (string)($cfg['accent'] ?? '#008037');
        $theme  = (string)($cfg['theme']  ?? 'light');
        $title  = 'ivi.php Debug Console';

        $light = [
            '--bg'     => '#ffffff',
            '--fg'     => '#111111',
            '--muted'  => '#555555',
            '--panel'  => '#ffffff',
            '--border' => '#e5e7eb',
            '--code'   => '#f8fff9',
        ];
        $dark = [
            '--bg'     => '#0f1115',
            '--fg'     => '#e6edf3',
            '--muted'  => '#9aa4ad',
            '--panel'  => '#0f141a',
            '--border' => '#1f252c',
            '--code'   => '#122016',
        ];
        $vars = ($theme === 'dark') ? $dark : $light;
        $root = ':root{--accent:' . $accent . ';' .
            implode('', array_map(fn($k, $v) => "$k:$v;", array_keys($vars), $vars)) .
            '}';

        $trace        = $e->getTrace();
        $appFrameUser = self::firstUserAppFrame($trace);
        $appFrame     = $appFrameUser ?: self::firstAppFrame($trace);
        $thrownFile   = self::cleanFile($e->getFile());
        $thrownLine   = (string)$e->getLine();

        if ($cs = self::callsiteOrNull()) {
            $appFile = self::cleanFile($cs['file']);
            $appLine = (string)($cs['line'] ?? '-');
            $summary = htmlspecialchars(
                $e::class . ': ' . $e->getMessage() .
                    ' at ' . $appFile . ':' . $appLine .
                    '  (thrown in ' . $thrownFile . ':' . $thrownLine . ')',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        } else if ($appFrame) {
            $appFile = self::cleanFile($appFrame['file'] ?? '');
            $appLine = (string)($appFrame['line'] ?? '-');
            $appCls  = (string)($appFrame['class'] ?? '');
            $appFn   = (string)($appFrame['function'] ?? '');
            $at      = trim(($appCls !== '' ? $appCls . '::' : '') . ($appFn !== '' ? $appFn : ''));
            if ($at === '') {
                $at = $appFile;
            }
            $summary = htmlspecialchars(
                $e::class . ': ' . $e->getMessage() .
                    ' at ' . $at . ' (' . $appFile . ':' . $appLine . ')' .
                    '  (thrown in ' . $thrownFile . ':' . $thrownLine . ')',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        } else {
            $summary = htmlspecialchars(
                $e::class . ': ' . $e->getMessage() .
                    ' at ' . $thrownFile . ':' . $thrownLine,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        $traceHtml = '';
        if (!empty($cfg['show_trace'])) {
            // On génère les frames filtrées
            $all = $e->getTrace();
            $frames = array_values(array_filter($all, [self::class, 'frameIsVisible']));

            // Injecter le callsite en tête
            if ($cs = self::callsiteOrNull()) {
                $already = false;
                foreach ($frames as $f) {
                    if (($f['file'] ?? null) === $cs['file'] && ($f['line'] ?? null) === $cs['line']) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    array_unshift($frames, [
                        'file' => $cs['file'],
                        'line' => $cs['line'],
                        'class' => $cs['class'] ?? '',
                        'function' => $cs['method'] ?? '',
                    ]);
                }
            }

            // Limiter + render simple (même markup que buildTraceHtml)
            $max = (int)($cfg['max_trace'] ?? 10);
            $hidden = max(0, count($all) - count($frames));
            $frames = array_slice($frames, 0, $max);

            $out = '';
            foreach ($frames as $i => $f) {
                $file = htmlspecialchars(self::cleanFile($f['file'] ?? '[internal]'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $line = htmlspecialchars((string)($f['line'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $func = htmlspecialchars((string)($f['function'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $out .= "<div class=\"frame\">#$i <span class=\"file\">{$file}</span>:<span class=\"line\">{$line}</span> <span class=\"func\">{$func}()</span></div>";
            }
            if ($hidden > 0) {
                $out = '<div class="frame" style="opacity:.7">(+' . $hidden . ' frames masqués)</div>' . $out;
            }
            $traceHtml = $out;
        }


        // Trace + Contexte
        $traceHtml   = !empty($cfg['show_trace'])   ? self::buildTraceHtml($e, (int)($cfg['max_trace'] ?? 10)) : '';
        $contextHtml = !empty($cfg['show_context']) ? self::buildContextHtml($context) : '';

        ob_start(); ?>
        <!doctype html>
        <html lang="en">

        <head>
            <meta charset="utf-8">
            <title><?= $title ?></title>
            <style>
                <?= $root ?>* {
                    box-sizing: border-box
                }

                body {
                    margin: 0;
                    background: var(--bg);
                    color: var(--fg);
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif
                }

                header {
                    background: var(--accent);
                    color: #fff;
                    padding: 14px 20px;
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    justify-content: space-between
                }

                .brand {
                    display: flex;
                    align-items: center;
                    gap: 10px
                }

                .logo {
                    height: 26px;
                    width: auto;
                    vertical-align: middle;
                    filter: drop-shadow(0 1px 1px rgba(0, 0, 0, .15));
                    background: #fff;
                    border-radius: 50%;
                    padding: 4px
                }

                .name {
                    font-size: 16px;
                    font-weight: 700;
                    letter-spacing: .3px
                }

                .badge {
                    background: #fff;
                    color: var(--accent);
                    font-size: 12px;
                    font-weight: 700;
                    padding: 2px 10px;
                    border-radius: 999px
                }

                main {
                    padding: 20px;
                    display: grid;
                    gap: 16px
                }

                .panel {
                    background: var(--panel);
                    border: 1px solid var(--border);
                    border-radius: 10px;
                    overflow: hidden
                }

                .panel .head {
                    background: var(--code);
                    padding: 10px 14px;
                    border-bottom: 1px solid var(--border);
                    font-weight: 700;
                    color: var(--accent)
                }

                .panel .body {
                    padding: 14px
                }

                pre.code {
                    background: var(--code);
                    padding: 14px;
                    border-radius: 8px;
                    white-space: pre-wrap;
                    font-size: 13px;
                    color: var(--fg);
                    margin: 0
                }

                .trace .frame {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                    font-size: 13px;
                    color: var(--muted)
                }

                .trace .file {
                    color: var(--fg);
                    font-weight: 600
                }

                .trace .line {
                    color: var(--accent);
                    font-weight: 700
                }

                .trace .func {
                    color: var(--muted)
                }

                .kv {
                    display: grid;
                    grid-template-columns: 180px 1fr;
                    gap: 8px
                }

                .kv .k {
                    color: var(--muted)
                }

                .kv .v code {
                    background: var(--code);
                    padding: 2px 6px;
                    border-radius: 6px
                }
            </style>
        </head>

        <body>
            <header>
                <div class="brand">
                    <img src="/assets/logo/ivi.png" alt="ivi.php" class="logo">
                    <span class="name">ivi.php Debug Console</span>
                </div>
                <span class="badge"><?= htmlspecialchars(strtoupper($theme), ENT_QUOTES, 'UTF-8') ?></span>
            </header>

            <main>
                <section class="panel">
                    <div class="head">Kernel Exception</div>
                    <div class="body">
                        <pre class="code"><?= $summary ?></pre>
                    </div>
                </section>

                <?php if (!empty($cfg['show_trace'])): ?>
                    <section class="panel">
                        <div class="head"><strong>Stack trace (top <?= (int)($cfg['max_trace'] ?? 10) ?>)</strong></div>
                        <div class="body trace">
                            <?= $traceHtml ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($cfg['show_context'])): ?>
                    <section class="panel">
                        <div class="head"><strong>Context</strong></div>
                        <div class="body">
                            <?= $contextHtml ?>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </body>

        </html>
<?php
        return (string)ob_get_clean();
    }


    /**
     * Construit le HTML de la stack trace (sécurisé).
     */
    private static function buildTraceHtml(\Throwable $e, int $max): string
    {
        $max = max(1, $max);
        $all = $e->getTrace();

        $strategy = self::$config['trace_strategy'] ?? 'balanced';
        $frames = [];

        if ($strategy === 'full') {
            $frames = $all;
        } else if ($strategy === 'framework_only') {
            // Ancien comportement: ne garder que Ivi\*
            $frames = array_values(array_filter($all, [self::class, 'frameIsVisible']));
        } else { // balanced
            $keptFirstApp = false;
            $appNamespaces = (array)(self::$config['app_namespaces'] ?? []);

            foreach ($all as $f) {
                $cls  = $f['class'] ?? '';
                $file = $f['file'] ?? '';

                // 1) Conserver toujours le premier frame app configuré
                if (!$keptFirstApp && $cls !== '') {
                    foreach ($appNamespaces as $ns) {
                        if (str_starts_with($cls, $ns)) {
                            $frames[] = $f;
                            $keptFirstApp = true;
                            continue 2;
                        }
                    }
                }
                // 1bis) fallback: premier fichier userland sous /src/
                if (!$keptFirstApp && $file !== '' && str_contains($file, DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR)) {
                    $frames[] = $f;
                    $keptFirstApp = true;
                    continue;
                }

                // 2) Puis filtrage normal (Ivi\*, etc.)
                if (self::frameIsVisible($f)) {
                    $frames[] = $f;
                }
            }
        }

        $hiddenCount = max(0, count($all) - count($frames));
        $frames = array_slice($frames, 0, $max);

        $out = '';
        foreach ($frames as $i => $f) {
            $file = htmlspecialchars(self::cleanFile($f['file'] ?? '[internal]'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $line = htmlspecialchars((string)($f['line'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $func = htmlspecialchars((string)($f['function'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= "<div class=\"frame\">#$i <span class=\"file\">{$file}</span>:<span class=\"line\">{$line}</span> <span class=\"func\">{$func}()</span></div>";
        }

        if ($out === '') {
            $out = '<div class="frame"><em>No stack frames available.</em></div>';
        }
        if ($hiddenCount > 0) {
            $out = '<div class="frame" style="opacity:.7">(+' . $hiddenCount . ' frames masqués)</div>' . $out;
        }
        return $out;
    }

    /**
     * Construit le HTML du contexte (GET/POST/headers, etc.) + contexte fourni.
     * $context param garde la priorité (ce que tu passes depuis le Kernel).
     */
    private static function buildContextHtml(array $context): string
    {
        // Contexte par défaut à exposer (safe & utile)
        $server = [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'REQUEST_URI'    => $_SERVER['REQUEST_URI']    ?? null,
            'HTTP_HOST'      => $_SERVER['HTTP_HOST']      ?? null,
            'HTTP_ACCEPT'    => $_SERVER['HTTP_ACCEPT']    ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'REMOTE_ADDR'    => $_SERVER['REMOTE_ADDR']    ?? null,
        ];

        $base = [
            '_GET'    => $_GET  ?? [],
            '_POST'   => $_POST ?? [],
            '_SERVER' => $server,
        ];

        // Merge: ce que tu passes depuis le Kernel écrase/complète
        $merged = array_replace($base, $context);

        // Pretty JSON (sécurisé)
        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"error":"Failed to encode context"}';
        }
        $safe = htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<pre class="code">' . $safe . '</pre>';
    }
}
