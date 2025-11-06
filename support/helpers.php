<?php

declare(strict_types=1);

/**
 * Global debug helpers.
 * - dump(mixed $data, array $opts = [])
 * - dd(mixed $data, array $opts = [])  (dump + die)
 *
 * Priorité: App\Debug\Logger si présent, sinon Ivi\Core\Debug\Logger.
 */

if (!function_exists('dump')) {
    function dump(mixed $data, array $options = []): void
    {
        // Titre optionnel (fallback "Dump")
        $title = $options['title'] ?? 'Dump';
        unset($options['title']);

        // 1) Logger côté app s'il existe
        // if (class_exists(\App\Debug\Logger::class)) {
        //     \App\Debug\Logger::dump($title, $data, $options);
        //     return;
        // }
        // 2) Sinon: Logger côté framework
        if (class_exists(\Ivi\Core\Debug\Logger::class)) {
            \Ivi\Core\Debug\Logger::dump($title, $data, $options);
            return;
        }
        // 3) Fallback texte
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $title . ":\n";
        print_r($data);
    }
}

if (!function_exists('dd')) {
    function dd(mixed $data, array $options = []): never
    {
        $options['exit'] = true;
        dump($data, $options);
        exit;
    }
}

if (!function_exists('ivi_dump')) {
    function ivi_dump(mixed $data, array $options = []): void
    {
        $title = $options['title'] ?? 'Dump';
        \Ivi\Core\Debug\Logger::dump($title, $data, $options);
    }
}

if (!function_exists('ivi_dd')) {
    function ivi_dd(mixed $data, array $options = []): never
    {
        $options['exit'] = true;
        ivi_dump($data, $options);
        exit;
    }
}
