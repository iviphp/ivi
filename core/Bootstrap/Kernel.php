<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Http\Response;
use Ivi\Router\Router;

final class Kernel
{
    public function handle(Router $router, Request $request): mixed
    {
        return $router->dispatch($request);
    }

    public function terminate(mixed $response): void
    {
        if (\Ivi\Core\Debug\State::$outputStarted) return;

        if ($response instanceof Response) {
            $response->send();
            return;
        }

        if (is_string($response)) {
            echo $response;
            return;
        }

        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function handleException(\Throwable $e): void
    {
        $status  = 500;
        $headers = [];

        if ($e instanceof \Ivi\Http\Exceptions\HttpException) {
            $status  = $e->getStatusCode();
            $headers = $e->getHeaders();
        }

        foreach ($headers as $k => $v) {
            header("$k: $v", true);
        }
        http_response_code($status);

        $isProd    = (\defined('APP_ENV') ? APP_ENV : 'prod') === 'prod';
        $wantsJson = $this->wantsJson();

        // ⚠️ Si API/JSON attendu → on renvoie du JSON et on NE PASSE PAS par le Logger HTML
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');

            if ($isProd) {
                echo json_encode([
                    'error'  => $e->getMessage(),
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Mode dev: détailler proprement
            $trace = array_map(function ($f) {
                $file = $f['file'] ?? '[internal]';
                $line = $f['line'] ?? null;
                $func = $f['function'] ?? null;
                $cls  = $f['class'] ?? null;
                return array_filter([
                    'file' => $file,
                    'line' => $line,
                    'call' => $cls ? ($cls . '::' . $func) : $func,
                ], fn($v) => $v !== null);
            }, array_slice($e->getTrace(), 0, 10));

            echo json_encode([
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'status'    => $status,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $trace,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Sinon (HTML attendu) → Logger HTML
        try {
            \Ivi\Core\Debug\Logger::exception($e, [], [
                'verbosity'    => 'minimal',
                'show_payload' => false,
                'show_trace'   => true,
                'show_context' => true,
                'max_trace'    => 10,
                'exit'         => false,
            ]);
            return;
        } catch (\Throwable $__) {
            // Fallback brutal si le Logger lui-même échoue
        }

        if ($isProd) {
            if ($this->wantsJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => $e->getMessage(), 'status' => $status]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo "<h1>Error {$status}</h1>";
            }
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo get_class($e) . ': ' . $e->getMessage() . "\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n";
    }


    private function wantsJson(): bool
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $ctype  = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Overrides manuels
        $force = (
            (($_GET['__json'] ?? '') === '1') ||
            (strtolower($_SERVER['HTTP_X_EXPECT'] ?? '') === 'json') ||
            (strtolower($_SERVER['HTTP_X_IVI_EXPECT'] ?? '') === 'json')
        );

        // Le client demande explicitement du HTML ?
        $prefersHtml = str_contains($accept, 'text/html') && !str_contains($accept, 'application/json');

        return
            $force
            // Accept JSON explicite
            || str_contains($accept, 'application/json')
            // Corps JSON (POST/PUT/PATCH avec JSON)
            || str_contains($ctype, 'application/json')
            // Convention d’URL: /api/*
            || str_starts_with($uri, '/api')
            // Requêtes AJAX
            || $xhr === 'xmlhttprequest'
            // Heuristique : toute requête non-GET est traitée en JSON,
            // sauf si le client demande explicitement text/html.
            || (($method !== 'GET') && !$prefersHtml);
    }
}
