<?php

declare(strict_types=1);

namespace Ivi\Core\Bootstrap;

use Ivi\Http\Request;
use Ivi\Http\Response;
use Ivi\Http\TextResponse;
use Ivi\Http\JsonResponse;
use Ivi\Core\Router\Router;
use Ivi\Core\Exceptions\ExceptionHandler;

/**
 * -----------------------------------------------------------------------------
 * Class Kernel
 * -----------------------------------------------------------------------------
 * 
 * The central HTTP kernel for the Ivi.php framework.
 *
 * This class orchestrates the full request lifecycle:
 *  1. Intercepts requests targeting static assets (e.g., `/assets/...`)
 *     and serves them directly from `/public` with proper headers
 *     (Content-Type, Cache-Control, ETag, Last-Modified).
 *  2. Delegates all other requests to the router for dynamic dispatch.
 *  3. Converts non-Response return values (string, array, etc.)
 *     into proper Response objects.
 *  4. Handles any uncaught exceptions through the registered ExceptionHandler.
 *
 * @package Ivi\Core\Bootstrap
 * @since 1.0.0
 */
final class Kernel
{
    public function __construct(
        private readonly ExceptionHandler $exceptions
    ) {}

    /**
     * Handles an incoming HTTP request and produces a response.
     *
     * Execution flow:
     *  1. If the request path points to a valid static file under `/public`,
     *     the file is served immediately.
     *  2. Otherwise, the request is passed to the router.
     *  3. Any thrown exception is caught and handled gracefully.
     *
     * @param Router  $router  The application router instance.
     * @param Request $request The current HTTP request.
     * @return Response The final HTTP response.
     */
    public function handle(Router $router, Request $request): Response
    {
        try {
            // Step 1: Serve static files before routing
            $path = $this->requestPath($request);
            if ($this->isStaticPath($path)) {
                return $this->serveStaticFile($path);
            }

            // Step 2: Delegate to router
            $result = $router->dispatch($request);
            return $this->normalizeToResponse($result);
        } catch (\Throwable $e) {
            // Step 3: Centralized exception handling
            return $this->exceptions->handle($e, $request);
        }
    }

    /**
     * Sends the HTTP response to the client.
     *
     * If headers were already sent (for example by a non-blocking dump),
     * the method gracefully outputs the response body only.
     *
     * @param Response $response
     */
    public function terminate(Response $response): void
    {
        if (!headers_sent()) {
            $response->send();
            return;
        }
        echo $response->content();
    }

    /**
     * Normalizes various return types into a Response object.
     *
     * Supported conversions:
     *  - Response → unchanged
     *  - string   → TextResponse
     *  - any other value (array, object, etc.) → JsonResponse
     *
     * @param mixed $result
     * @return Response
     */
    private function normalizeToResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return new TextResponse($result);
        }
        return new JsonResponse($result);
    }

    /* --------------------------------------------------------------------- */
    /*                         Static File Handling                          */
    /* --------------------------------------------------------------------- */

    /**
     * Retrieves the current request path in a tolerant way.
     *
     * This method supports multiple request implementations by checking
     * for `getPath()` or `path()` methods before falling back to $_SERVER.
     *
     * @param Request $request
     * @return string The normalized request path.
     */
    private function requestPath(Request $request): string
    {
        if (method_exists($request, 'getPath')) {
            return (string) $request->path();
        }
        if (method_exists($request, 'path')) {
            return (string) $request->path();
        }
        return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }

    /**
     * Determines whether a given path points to a static file
     * inside the public directory (default: `/assets/...`).
     *
     * @param string $path
     * @return bool True if the path corresponds to a static file.
     */
    private function isStaticPath(string $path): bool
    {
        // Restrict to /assets/... for safety and clarity
        if (!str_starts_with($path, '/assets/')) {
            return false;
        }

        $public = realpath(__DIR__ . '/../../public') ?: '';
        $candidate = realpath($public . $path);

        // Prevent directory traversal and verify file existence
        return $candidate !== false
            && str_starts_with($candidate, $public)
            && is_file($candidate);
    }

    /**
     * Serves a static file with proper HTTP headers and caching support.
     *
     * Includes:
     *  - MIME type detection
     *  - ETag and Last-Modified headers
     *  - Conditional 304 responses
     *
     * @param string $path The requested asset path (relative to /public).
     * @return Response The response containing the static file or a 404.
     */
    private function serveStaticFile(string $path): Response
    {
        $fullPath = realpath(__DIR__ . '/../../public' . $path);
        if ($fullPath === false || !is_file($fullPath)) {
            return new TextResponse('Not Found', 404);
        }

        $mime = $this->detectMimeType($fullPath);
        $mtime = filemtime($fullPath) ?: time();
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
        $etag = '"' . md5($fullPath . '|' . $mtime . '|' . filesize($fullPath)) . '"';

        // Handle conditional requests (ETag / Last-Modified)
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

        if ($ifNoneMatch === $etag || $ifModifiedSince === $lastModified) {
            return new Response('', 304, [
                'ETag'           => $etag,
                'Last-Modified'  => $lastModified,
                'Cache-Control'  => 'public, max-age=2592000, immutable',
                'Content-Type'   => $mime,
            ]);
        }

        $contents = file_get_contents($fullPath) ?: '';
        return new Response($contents, 200, [
            'Content-Type'   => $mime,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control'  => 'public, max-age=2592000, immutable',
            'ETag'           => $etag,
            'Last-Modified'  => $lastModified,
        ]);
    }

    /**
     * Detects the MIME type of a file based on its extension.
     *
     * Covers common file types such as CSS, JS, JSON, images, and fonts.
     * Falls back to `application/octet-stream` for unknown extensions.
     *
     * @param string $file
     * @return string MIME type with charset where applicable.
     */
    private function detectMimeType(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return match ($ext) {
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'json'  => 'application/json; charset=utf-8',
            'svg'   => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            default => 'application/octet-stream',
        };
    }
}
