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
        }

        $isProd = (\defined('APP_ENV') ? APP_ENV : 'prod') === 'prod';
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
        return str_contains($accept, 'application/json');
    }
}
