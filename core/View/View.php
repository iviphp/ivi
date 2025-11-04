<?php

declare(strict_types=1);

namespace Ivi\Core\View;

use Ivi\Core\View\ViewNotFoundException;

final class View
{
    /**
     * Render a view file with variables.
     *
     * @param string $name  View name using dot or slash syntax (e.g., "product/home" or "product.home").
     * @param array<string,mixed> $data  Variables to extract into the view.
     */
    public static function make(string $name, array $data = []): string
    {
        $path = self::resolvePath($name);

        if (!is_file($path)) {
            throw new ViewNotFoundException("View not found: {$path}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    /**
     * Resolve the absolute path of the view file.
     */
    private static function resolvePath(string $name): string
    {
        // Allow both "product.home" and "product/home"
        $relative = str_replace(['.', '\\'], DIRECTORY_SEPARATOR, $name) . '.php';
        $base = defined('VIEWS') ? VIEWS : (getcwd() . '/views/');
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    }
}
