<?php

namespace Ivi\Core\Debug;

final class Logger
{
    /** @var array<string,mixed> */
    private static array $config = [
        'theme' => 'light',
        // accent par défaut (Ivi)
        'accent' => '#008037',
        'max_trace' => 10,
        'exit' => true,
        'label' => null,
        'verbosity' => 'normal',
        'show_payload' => true,
        'show_trace' => true,
        'show_context' => true,

        // ====== NEW: branding ======
        // 'brand' = 'ivi' | 'softadastra'
        'brand'       => 'ivi',
        'brand_name'  => 'Ivi.php',
        'brand_logo'  => '/assets/logo/ivi.png',   // remplace si besoin
        // ===========================

        // Trace
        'trace_strategy' => 'balanced',
        'trace_exclude_namespaces' => [],
        'trace_exclude_paths'      => [],
        'trace_only_namespaces'    => [],
        'app_namespaces'           => ['Ivi\\Controllers\\', 'App\\'],
    ];

    /** Presets de marque */
    private const BRAND_PRESETS = [
        'ivi' => [
            'brand_name' => 'Ivi.php',
            'accent'     => '#008037',
            'brand_logo' => '/assets/logo/ivi.png',
            'title'      => 'ivi.php Debug Console',
        ],
        'softadastra' => [
            'brand_name' => 'Softadastra',
            'accent'     => '#ff9900',
            'brand_logo' => '/assets/logo/ivi.png',
            'title'      => 'Softadastra Debug Console',
        ],
    ];

    /** Merge config + applique preset de marque */
    public static function configure(array $cfg): void
    {
        self::$config = array_replace(self::$config, $cfg);
        self::applyBrand();
    }

    /** NEW: permet de changer rapidement de marque */
    public static function configureBrand(string $brand, ?string $accent = null, ?string $logo = null): void
    {
        self::$config['brand'] = $brand;
        if ($accent !== null) self::$config['accent'] = $accent;
        if ($logo !== null)   self::$config['brand_logo'] = $logo;
        self::applyBrand();
    }

    private static function applyBrand(): void
    {
        $brand = strtolower((string)(self::$config['brand'] ?? 'ivi'));
        $preset = self::BRAND_PRESETS[$brand] ?? self::BRAND_PRESETS['ivi'];

        // Accent: priorité à l'override utilisateur
        if (empty(self::$config['accent'])) {
            self::$config['accent'] = $preset['accent'];
        }
        // Nom & logo: on set si non fournis
        if (empty(self::$config['brand_name'])) {
            self::$config['brand_name'] = $preset['brand_name'];
        }
        if (empty(self::$config['brand_logo'])) {
            self::$config['brand_logo'] = $preset['brand_logo'];
        }
        // Titre par défaut pour la page HTML
        if (empty(self::$config['page_title'])) {
            self::$config['page_title'] = $preset['title'];
        }
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

    /** Build theme CSS vars from config */
    private static function themeCss(array $cfg): string
    {
        $accent = (string)($cfg['accent'] ?? '#008037'); // vert par défaut
        $theme  = (string)($cfg['theme']  ?? 'light');

        $light = [
            '--bg'     => '#ffffff',
            '--fg'     => '#111111',
            '--muted'  => '#555555',
            '--panel'  => '#ffffff',
            '--border' => '#e5e7eb',
            '--code'   => '#fff8f0', // légèrement chaud, va bien avec #ff9900
        ];
        $dark = [
            '--bg'     => '#0f1115',
            '--fg'     => '#e6edf3',
            '--muted'  => '#9aa4ad',
            '--panel'  => '#0f141a',
            '--border' => '#1f252c',
            '--code'   => '#19140e', // chaud sombre pour #ff9900
        ];
        $vars = ($theme === 'dark') ? $dark : $light;

        return ':root{--accent:' . $accent . ';' .
            implode('', array_map(fn($k, $v) => "$k:$v;", array_keys($vars), $vars)) .
            '}';
    }

    /** Wraps content into the shared HTML shell */
    private static function htmlShell(string $title, string $mainHtml, array $cfg): string
    {
        $root  = self::themeCss($cfg);
        $theme = (string)($cfg['theme'] ?? 'light');

        // NEW: branding dynamiques
        $brandName = (string)($cfg['brand_name'] ?? 'Ivi.php');
        $brandLogo = (string)($cfg['brand_logo'] ?? '/assets/logo/ivi.png');
        $pageTitle = (string)($cfg['page_title'] ?? $title);

        ob_start(); ?>
        <!doctype html>
        <html lang="en">

        <head>
            <meta charset="utf-8" />
            <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
            <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>">
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
                    background: #fff;
                    border-radius: 50%;
                    padding: 4px
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

                .name {
                    font-family: "Segoe UI", Roboto, "SF Pro Display", system-ui, sans-serif;
                    font-weight: 600;
                    font-size: 17px;
                    letter-spacing: .4px;
                    color: #fff;
                    display: flex;
                    align-items: baseline;
                    gap: 6px
                }

                .name strong {
                    font-weight: 800
                }

                .subtitle {
                    font-size: 13px;
                    font-weight: 500;
                    opacity: .95;
                    margin-left: 4px;
                    letter-spacing: .2px
                }
            </style>
        </head>

        <body>
            <header>
                <div class="brand">
                    <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>" class="logo">
                    <span class="name"><strong><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></strong> <span class="subtitle">Debug Console</span></span>
                </div>
                <span class="badge"><?= htmlspecialchars(strtoupper($theme), ENT_QUOTES, 'UTF-8') ?></span>
            </header>
            <main><?= $mainHtml ?></main>
        </body>

        </html>
<?php
        return (string)ob_get_clean();
    }

    /** Build a simple trace HTML from a backtrace array */
    private static function backtraceHtml(array $trace, int $max): string
    {
        $trace = array_slice($trace, 0, max(1, $max));
        $out = '';
        foreach ($trace as $i => $f) {
            $file = htmlspecialchars(self::cleanFile($f['file'] ?? '[internal]'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $line = htmlspecialchars((string)($f['line'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out .= "<div class=\"frame\">#$i <span class=\"file\">{$file}</span>:<span class=\"line\">{$line}</span></div>";
        }
        return $out ?: '<div class="frame"><em>No stack frames available.</em></div>';
    }

    /* ================= WEB ================= */
    private static function renderHtmlException(\Throwable $e, array $context, array $cfg): string
    {
        $title = 'ivi.php Debug Console';

        // --- Summary (inchangé de ton code, raccourci ici)
        $thrownFile = self::cleanFile($e->getFile());
        $thrownLine = (string)$e->getLine();
        $summary = htmlspecialchars($e::class . ': ' . $e->getMessage() . ' at ' . $thrownFile . ':' . $thrownLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // --- Trace
        $traceHtml = '';
        if (!empty($cfg['show_trace'])) {
            // filtre & limite comme tu le faisais (ou simple backtraceToHtml)
            $traceHtml = self::buildTraceHtml($e, (int)($cfg['max_trace'] ?? 10));
            $traceHtml = '<div class="trace">' . $traceHtml . '</div>';
        }

        // --- Contexte
        $contextHtml = !empty($cfg['show_context']) ? self::buildContextHtml($context) : '';

        // --- Assemble panels
        $main  = self::panel('Kernel Exception', '<pre class="code">' . $summary . '</pre>');
        if ($traceHtml !== '')  $main .= self::panel('Stack trace (top ' . (int)($cfg['max_trace'] ?? 10) . ')', $traceHtml);
        if ($contextHtml !== '') $main .= self::panel('Context', $contextHtml);

        return self::htmlShell($title, $main, $cfg);
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

    private static function titleForCli(string $title): string
    {
        // Supprime le HTML/SVG éventuel
        $plain = trim(strip_tags($title));
        // Si le titre reste vide (ex: que du SVG), on met un fallback
        return $plain !== '' ? $plain : '[log]';
    }

    /** Dumper générique (titre + payload) en CLI ou HTML */
    public static function dump(string $title, mixed $payload = null, array $options = []): void
    {
        $cfg = array_replace(self::$config, $options);

        // ================= CLI =================
        if (PHP_SAPI === 'cli') {
            $accent = "\033[38;5;34m";
            $muted  = "\033[0;37m";
            $reset  = "\033[0m";

            // IMPORTANT: en CLI, on retire le SVG/HTML
            $titleCli = self::titleForCli($title);

            $json = self::encodePretty($payload);

            echo "{$accent}{$titleCli}{$reset}\n";
            if ($json !== null) {
                echo $json . "\n";
            } else {
                print_r($payload);
                echo "\n";
            }

            if (!empty($cfg['show_trace'])) {
                echo "{$muted}Trace:{$reset}\n";
                echo strip_tags(self::backtraceHtml(
                    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                    (int)($cfg['max_trace'] ?? 10)
                )) . "\n";
            }
            if (!empty($cfg['exit'])) exit(0);
            return;
        }

        // ================= WEB (HTML) =================
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $json = self::encodePretty($payload);
        $safe = $json !== null
            ? htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            : htmlspecialchars(var_export($payload, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Si on a un titre qui contient du SVG/HTML, on passe title_is_html=true
        $headIsHtml = (bool)($cfg['title_is_html'] ?? false);

        // 1) Panel principal
        $main  = self::panel($title, '<pre class="code">' . $safe . '</pre>', $headIsHtml);

        // 2) Panel trace optionnel
        if (!empty($cfg['show_trace'])) {
            $traceHtml = self::backtraceHtml(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
                (int)($cfg['max_trace'] ?? 10)
            );
            $main .= self::panel('Trace', '<div class="trace">' . $traceHtml . '</div>');
        }

        echo self::htmlShell('ivi.php dump', $main, $cfg);
        if (!empty($cfg['exit'])) exit(0);
    }

    /** Raccourcis de niveau */
    public static function info(string $message, array $context = [], array $options = []): void
    {
        $icon = self::iconSvg('info');
        self::dump(self::titleWithIcon($icon, $message), $context, $options + ['title_is_html' => true]);
    }
    public static function debug(string $message, array $context = [], array $options = []): void
    {
        $icon = self::iconSvg('bug');
        self::dump(self::titleWithIcon($icon, $message), $context, $options + ['title_is_html' => true]);
    }
    public static function error(string $message, array $context = [], array $options = []): void
    {
        $icon = self::iconSvg('error');
        self::dump(self::titleWithIcon($icon, $message), $context, $options + ['title_is_html' => true]);
    }

    /**
     * Renvoie un petit SVG inline (taille 16x16) selon le type demandé.
     * @param string $type 'info' | 'bug' | 'error'
     */
    private static function iconSvg(string $type): string
    {
        return match ($type) {
            'info' => <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
     stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;">
    <circle cx="12" cy="12" r="10"></circle>
    <line x1="12" y1="16" x2="12" y2="12"></line>
    <line x1="12" y1="8" x2="12.01" y2="8"></line>
</svg>
SVG,
            'bug' => <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" 
     stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;">
    <path d="M19 7l-3 2"></path><path d="M5 7l3 2"></path>
    <path d="M12 20a7 7 0 0 0 7-7H5a7 7 0 0 0 7 7z"></path>
    <path d="M12 4a4 4 0 0 1 4 4v1H8V8a4 4 0 0 1 4-4z"></path>
</svg>
SVG,
            'error' => <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;">
    <circle cx="12" cy="12" r="10"></circle>
    <line x1="15" y1="9" x2="9" y2="15"></line>
    <line x1="9" y1="9" x2="15" y2="15"></line>
</svg>
SVG,
            default => '',
        };
    }

    // 1) Helper: titre = SVG brut + message échappé
    private static function titleWithIcon(string $iconSvg, string $message): string
    {
        $msg = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // le SVG est "trusted", on ne l’échappe pas
        return $iconSvg . ' ' . $msg;
    }

    // 2) Panel commun avec option HTML pour la tête
    private static function panel(string $head, string $bodyHtml, bool $headIsHtml = false): string
    {
        $headHtml = $headIsHtml
            ? $head
            : htmlspecialchars($head, ENT_QUOTES, 'UTF-8');

        return '<section class="panel">'
            .   '<div class="head">' . $headHtml . '</div>'
            .   '<div class="body">' . $bodyHtml . '</div>'
            . '</section>';
    }


    /** JSON pretty helper (retourne null si non sérialisable proprement) */
    private static function encodePretty(mixed $payload): ?string
    {
        // Normalisation récursive "lisible"
        $normalize = function (mixed $v) use (&$normalize) {
            // Null / scalaire: direct
            if ($v === null || is_scalar($v)) return $v;

            // Date/heure → ISO 8601
            if ($v instanceof \DateTimeInterface) {
                return $v->format(\DateTimeInterface::ATOM);
            }

            // Traversable → array
            if ($v instanceof \Traversable) {
                $tmp = [];
                foreach ($v as $k => $vv) $tmp[$k] = $normalize($vv);
                return $tmp;
            }

            // Objet → essayer toArray/jsonSerialize/public props
            if (is_object($v)) {
                // a) toArray()
                if (method_exists($v, 'toArray')) {
                    try {
                        return $normalize($v->toArray());
                    } catch (\Throwable) {
                    }
                }
                // b) JsonSerializable
                if ($v instanceof \JsonSerializable) {
                    try {
                        return $normalize($v->jsonSerialize());
                    } catch (\Throwable) {
                    }
                }
                // c) Public props
                $props = get_object_vars($v); // seulement publics
                if (!empty($props)) {
                    $out = [];
                    foreach ($props as $k => $vv) $out[$k] = $normalize($vv);
                    return $out;
                }
                // d) Fallback: stringable ?
                if (method_exists($v, '__toString')) {
                    try {
                        return (string)$v;
                    } catch (\Throwable) {
                    }
                }
                // e) Dernier recours: on laisse l'objet tel quel (print_r plus tard)
                return $v;
            }

            // Array
            if (is_array($v)) {
                $out = [];
                foreach ($v as $k => $vv) $out[$k] = $normalize($vv);
                return $out;
            }

            // Type exotique (ressource…) → string
            if (is_resource($v)) {
                return sprintf('resource(%s)', get_resource_type($v));
            }

            return $v;
        };

        // 1) Tentative JSON pretty
        try {
            $normalized = $normalize($payload);
            $json = json_encode(
                $normalized,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($json !== false) {
                // Si objet non sérialisable donnait {} ou [] -> forcer fallback texte
                if (is_object($payload) && ($json === '{}' || $json === '[]')) {
                    throw new \RuntimeException('Empty JSON for object');
                }
                return $json;
            }
        } catch (\Throwable) {
            // ignore → fallback texte
        }

        // 2) Fallback texte garanti et lisible
        try {
            ob_start();
            print_r($payload);
            return (string)ob_get_clean();
        } catch (\Throwable) {
            return null;
        }
    }
}
