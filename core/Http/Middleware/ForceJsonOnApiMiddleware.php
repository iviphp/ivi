<?php

declare(strict_types=1);

namespace Ivi\Core\Http\Middleware;

use Ivi\Http\Request;
use Ivi\Core\Contracts\Middleware;

final class ForceJsonOnApiMiddleware implements Middleware
{
    public function handle(Request $request): Request
    {
        $uri = $request->path();
        if (\str_starts_with($uri, '/api')) {
            // Indice pour wantsJson()
            $_SERVER['HTTP_ACCEPT'] = ($_SERVER['HTTP_ACCEPT'] ?? '');
            if (!str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json')) {
                $_SERVER['HTTP_ACCEPT'] .= (($_SERVER['HTTP_ACCEPT'] ?? '') ? ',' : '') . 'application/json';
            }
            // Petit header maison lu par wantsJson()
            $_SERVER['HTTP_X_IVI_EXPECT'] = 'json';
        }
        return $request;
    }
}
