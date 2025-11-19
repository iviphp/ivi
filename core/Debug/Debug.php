<?php

namespace Ivi\Core\Debug;

class Debug
{
    private const DEFAULT_LEVEL = 'info';
    private const MAX_LOG_AGE_DAYS = 30;  // logs plus vieux que 30 jours seront supprimés
    private const CONSOLE_MIN_LEVEL = 'info'; // 'debug', 'info', 'warning', 'error'

    public static function log(
        mixed   $value,
        ?string $label = null,
        string  $level = self::DEFAULT_LEVEL,
        bool    $json = false,
        bool    $trace = false
    ): void {
        $caller = self::getCallerInfo();
        $time = date('Y-m-d H:i:s');

        $entry = [
            'time'  => $time,
            'level' => strtoupper($level),
            'file'  => $caller['file'],
            'line'  => $caller['line'],
            'label' => $label,
            'value' => $value
        ];

        if ($trace) {
            $entry['trace'] = self::getSafeTrace();
        }

        if ($json) {
            $message = self::encodeJson($entry);
        } else {
            $valueStr   = self::stringify($value);
            $badgeLevel = self::badge(strtoupper($level), self::colorByLevel($level));
            $badgeTime  = self::badge($time, 'gray');
            $badgeCall  = self::badge(basename($caller['file']) . ':' . $caller['line'], 'blue');
            $badgeLabel = $label ? self::badge($label, 'cyan') : '';
            $message = sprintf("%s %s %s %s %s", $badgeLevel, $badgeTime, $badgeCall, $badgeLabel, $valueStr);
        }

        self::write($message);
    }

    // ----------------------------------------------------------
    // Badge system
    // ----------------------------------------------------------
    private static function badge(string $label, string $color): string
    {
        if (!self::supportsColor()) {
            return "[" . $label . "]";
        }

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

    private static function colorByLevel(string $level): string
    {
        return match (strtolower($level)) {
            'error'   => 'red',
            'warning' => 'yellow',
            'debug'   => 'cyan',
            default   => 'green'
        };
    }

    private static function supportsColor(): bool
    {
        return PHP_SAPI === 'cli';
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    private static function getCallerInfo(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[1] ?? $trace[0] ?? [];

        return [
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? '??',
        ];
    }

    private static function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }
        return trim(print_r($value, true));
    }

    private static function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } catch (\Throwable) {
            return '{"error":"JSON_ENCODE_FAILED"}';
        }
    }

    private static function getSafeTrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as &$step) {
            unset($step['args']);
        }
        return $trace;
    }

    // ----------------------------------------------------------
    // File logging with daily rotation and cleanup
    // ----------------------------------------------------------
    private static function getLogFile(): string
    {
        // 1) Essaie la constante IVI_LOG_FILE
        if (defined('IVI_LOG_FILE')) {
            $file = IVI_LOG_FILE;
        } else {
            // 2) Sinon, fallback sur BASE_PATH
            $file = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/debug.log';
        }

        // rotation journalière
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $base = rtrim($file, '.' . $ext);
        $dateSuffix = date('Y-m-d');

        return $base . '-' . $dateSuffix . '.' . $ext;
    }

    private static function ensureDirectory(string $file): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private static function cleanupOldLogs(string $file): void
    {
        $dir = dirname($file);
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $now = time();

        $files = glob($dir . '/*.' . $ext);
        foreach ($files as $f) {
            if (!is_file($f)) continue;
            if ($now - filemtime($f) > self::MAX_LOG_AGE_DAYS * 86400) {
                @unlink($f);
            }
        }
    }

    private static function levelWeight(string $level): int
    {
        return match (strtolower($level)) {
            'debug'   => 1,
            'info'    => 2,
            'warning' => 3,
            'error'   => 4,
            default   => 2,
        };
    }

    public static function write(string $message, string $level = self::DEFAULT_LEVEL): void
    {
        $file = self::getLogFile();
        self::ensureDirectory($file);
        self::cleanupOldLogs($file);

        // Écrire dans le fichier
        file_put_contents($file, $message . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Afficher en console si niveau >= CONSOLE_MIN_LEVEL
        if (self::supportsColor() && self::levelWeight($level) >= self::levelWeight(self::CONSOLE_MIN_LEVEL)) {
            echo $message . PHP_EOL;
        }
    }


    // ----------------------------------------------------------
    // Convenience shortcuts
    // ----------------------------------------------------------
    public static function info(mixed $v, ?string $label = null): void
    {
        self::log($v, $label, 'info');
    }

    public static function debug(mixed $v, ?string $label = null): void
    {
        self::log($v, $label, 'debug');
    }

    public static function warning(mixed $v, ?string $label = null): void
    {
        self::log($v, $label, 'warning');
    }

    public static function error(mixed $v, ?string $label = null): void
    {
        self::log($v, $label, 'error');
    }
}
