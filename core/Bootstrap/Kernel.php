<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Http\Response;
use Ivi\Http\TextResponse;
use Ivi\Http\JsonResponse;
use Ivi\Router\Router;
use Ivi\Core\Exceptions\ExceptionHandler;

final class Kernel
{
    public function __construct(
        private readonly ExceptionHandler $exceptions
    ) {}

    /**
     * Exécute le router et renvoie toujours une Response.
     * - Attrape toute exception et la transforme via ExceptionHandler.
     * - Normalise les retours non-Response (string/array/etc.) en Response.
     */
    public function handle(Router $router, Request $request): Response
    {
        try {
            $result = $router->dispatch($request);
            return $this->normalizeToResponse($result);
        } catch (\Throwable $e) {
            return $this->exceptions->handle($e, $request);
        }
    }

    /**
     * Envoie la réponse au client (HTTP).
     */
    public function terminate(Response $response): void
    {
        // Évite le double envoi si tu utilises un flag global côté Debug/Output.
        if (class_exists(\Ivi\Core\Debug\State::class) && \Ivi\Core\Debug\State::$outputStarted) {
            return;
        }

        $response->send();
    }

    /**
     * Convertit toute valeur en Response :
     * - Response → inchangé
     * - string  → TextResponse
     * - autre   → JsonResponse
     */
    private function normalizeToResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return new TextResponse($result);
        }

        // Tout le reste (array/stdClass/booleans/etc.) → JSON
        return new JsonResponse($result);
    }
}
