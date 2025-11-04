<?php

declare(strict_types=1);

/**
 * Global debug helpers.
 * - dump($data, array $opts = [])
 * - dd($data, array $opts = [])  (dump + die)
 *
 * Si tu as mis le Logger côté app (namespace App\Debug), garde la version App\Debug.
 * Si tu as mis le Logger côté framework (Ivi\Core\Debug), décommente l'autre.
 */

if (!function_exists('dump')) {
    function dump(mixed $data, array $options = []): void
    {
        // Priorité: Logger côté app
        if (class_exists(\Ivi\Core\Debug\Logger::class)) {
            \Ivi\Core\Debug\Logger::dump($data, $options);
            return;
        }
        // Sinon: Logger côté framework
        if (class_exists(\Ivi\Core\Debug\Logger::class)) {
            \Ivi\Core\Debug\Logger::dump($data, $options);
            return;
        }
        // Fallback
        header('Content-Type: text/plain; charset=utf-8');
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
