<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions;

use Throwable;
use Ivi\Core\Debug\Logger;
use Ivi\Http\Request;
use Ivi\Http\Response;
use Ivi\Http\HtmlResponse;
use Ivi\Http\JsonResponse;
use Ivi\Http\TextResponse;
use Ivi\Http\Exceptions\HttpException;

// ORM typed exceptions
use Ivi\Core\Exceptions\ORM\ORMException;
use Ivi\Core\Exceptions\ORM\ModelNotFoundException;
use Ivi\Core\Exceptions\ORM\QueryException;
use Ivi\Core\Exceptions\ORM\DatabaseConnectionException;
use Ivi\Core\Exceptions\ORM\DatabaseConfigNotFoundException;
use Ivi\Core\Exceptions\ORM\DatabaseDriverNotSupportedException;

final class ExceptionHandler
{
    /** @var array<string,mixed> */
    private array $appConfig;

    /** @param array<string,mixed> $appConfig ex: ['debug' => true, 'env' => 'local'] */
    public function __construct(array $appConfig = [])
    {
        $this->appConfig = $appConfig + [
            'debug' => false,
            'env'   => 'production',
        ];
    }

    /**
     * Point d'entrée unique pour transformer une exception en réponse.
     * - HTTP (Request fourni) → Html/Json
     * - CLI (pas de Request et SAPI=cli) → TextResponse
     */
    public function handle(Throwable $e, ?Request $request = null): Response
    {
        // CLI pur
        if (PHP_SAPI === 'cli' && !$request) {
            return $this->renderCli($e);
        }

        // HTTP
        return $this->renderHttp($e, $request);
    }

    private function detailLevel(): string
    {
        $lvl = (string)($this->appConfig['error_detail'] ?? 'safe');
        return in_array($lvl, ['none', 'safe', 'full'], true) ? $lvl : 'safe';
    }

    /** @return array{0:int,1:array<string,string>} … (inchangé) */

    /** Redacte DSN / SQL / bindings si on n’est pas en FULL. */
    private function redactIfNeeded(mixed $v, bool $full): mixed
    {
        if ($full) return $v;

        $mask = '[redacted]';

        // Redaction sur string simple
        $scrub = static function (string $s) use ($mask): string {
            // DSN mysql/pgsql/sqlite
            $s = preg_replace('~\b(mysql|pgsql|sqlite):[^\\s"\']+~i', $mask, $s) ?? $s;
            // host=..., dbname=..., user=..., password=...
            $s = preg_replace('~(host|dbname|user(name)?|password|charset)\s*=\s*[^;,\s]+~i', '$1=' . $mask, $s) ?? $s;
            return $s;
        };

        // tableaux : on retire clés sensibles
        $scrubArray = static function (array $a) use ($mask, $scrub): array {
            foreach ($a as $k => $val) {
                $key = strtolower((string)$k);
                if (in_array($key, ['dsn', 'sql', 'bindings', 'password', 'pass'], true)) {
                    $a[$k] = $mask;
                    continue;
                }
                if (is_string($val)) {
                    $a[$k] = $scrub($val);
                } elseif (is_array($val)) {
                    $a[$k] = call_user_func(__FUNCTION__, $val); // récursif
                }
            }
            return $a;
        };

        if (is_string($v))  return $scrub($v);
        if (is_array($v))   return $scrubArray($v);
        return $v;
    }

    /** @return array<string,mixed> — ne met pas file/line/trace si non FULL */
    private function safePayload(\Throwable $e, bool $debug): array
    {
        $level = $this->detailLevel(); // none|safe|full
        $full  = $debug && ($level === 'full');

        $base = [
            'error'   => $this->titleFor($e),
            'message' => ($full ? $e->getMessage() : $this->publicMessageFor($e)),
        ];

        // En 'safe', on ajoute un simple type technique (utile pour Google)
        if (!$full && $level === 'safe') {
            $base['type'] = (new \ReflectionClass($e))->getShortName();
        }

        if ($full) {
            $base['exception'] = get_class($e);
            $base['file']      = $e->getFile();
            $base['line']      = $e->getLine();
            $base['trace']     = $e->getTrace();
        }

        // Contexte QueryException
        if ($e instanceof \Ivi\Core\Exceptions\ORM\QueryException) {
            $ctx = $e->context();
            if (!$full) {
                unset($ctx['sql'], $ctx['bindings']);
            }
            $base['query'] = $ctx;
        }

        // Redaction finale si non FULL
        if (!$full) {
            foreach ($base as $k => $v) {
                $base[$k] = $this->redactIfNeeded($v, $full);
            }
        }

        return $base;
    }

    private function renderHttp(\Throwable $e, ?\Ivi\Http\Request $request): \Ivi\Http\Response
    {
        $debug  = (bool)($this->appConfig['debug'] ?? false);
        $level  = $this->detailLevel(); // none|safe|full
        $wants  = $this->wantsJson($request);

        [$status, $headers] = $this->statusAndHeadersFor($e);
        $payload = $this->safePayload($e, $debug);

        // Log riche uniquement en FULL
        if ($debug && $level === 'full') {
            $this->logException($e, $payload);
        }

        if ($wants) {
            return $this->jsonError(
                // JSON minimal si non FULL
                ($level === 'full' ? $payload : [
                    'error'  => $payload['error']   ?? 'Error',
                    'message' => $payload['message'] ?? 'An error occurred.',
                    'status' => $status,
                    // petit bonus utile en SAFE: code lisible
                    ...($level === 'safe' ? ['type' => $payload['type'] ?? null] : []),
                ]),
                $status,
                $headers
            );
        }

        // HTML : console debug seulement en FULL
        if ($debug && $level === 'full') {
            return $this->htmlDebug($payload, $status, $headers);
        }

        return $this->htmlGeneric($payload, $status, $headers);
    }


    // ---------------------------
    // HTTP rendering
    // ---------------------------

    private function wantsJson(?Request $request): bool
    {
        if ($request) {
            if ($request->wantsJson()) return true;
            $xrw = strtolower($request->header('x-requested-with', ''));
            if ($xrw === 'xmlhttprequest') return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains(strtolower($accept), 'application/json');
    }

    /** @return array{0:int,1:array<string,string>} */
    private function statusAndHeadersFor(Throwable $e): array
    {
        // HttpException → on respecte status/headers
        if ($e instanceof HttpException) {
            return [$e->getStatusCode(), $e->getHeaders()];
        }

        // ORM mappées
        if ($e instanceof ModelNotFoundException) {
            return [404, []];
        }
        if (
            $e instanceof DatabaseConnectionException
            || $e instanceof DatabaseDriverNotSupportedException
            || $e instanceof DatabaseConfigNotFoundException
        ) {
            return [500, []]; // ou 503 si tu préfères (Service Unavailable)
        }
        if ($e instanceof QueryException) {
            return [500, []];
        }
        if ($e instanceof ORMException) {
            return [500, []];
        }

        // Fallback
        return [500, []];
    }

    private function titleFor(Throwable $e): string
    {
        return match (true) {
            $e instanceof HttpException               => 'HTTP Error',
            $e instanceof ModelNotFoundException      => 'Resource Not Found',
            $e instanceof DatabaseConnectionException => 'Database Connection Error',
            $e instanceof DatabaseConfigNotFoundException => 'Database Configuration Error',
            $e instanceof DatabaseDriverNotSupportedException => 'Database Driver Not Supported',
            $e instanceof QueryException              => 'Database Query Error',
            $e instanceof ORMException                => 'Database Error',
            default                                   => 'Server Error',
        };
    }

    private function publicMessageFor(Throwable $e): string
    {
        // Messages sobres en prod
        return match (true) {
            $e instanceof ModelNotFoundException      => 'The requested resource was not found.',
            $e instanceof DatabaseConnectionException => 'Unable to connect to the database.',
            $e instanceof DatabaseConfigNotFoundException => 'Database is not configured.',
            $e instanceof DatabaseDriverNotSupportedException => 'Database driver not supported.',
            $e instanceof QueryException              => 'A database error occurred.',
            $e instanceof ORMException                => 'A database error occurred.',
            $e instanceof HttpException               => $e->getMessage() ?: 'HTTP error.',
            default                                   => 'An unexpected error occurred.',
        };
    }

    private function jsonError(array $payload, int $status, array $headers): JsonResponse
    {
        return new JsonResponse($payload, $status, $headers);
    }

    private function htmlDebug(array $payload, int $status, array $headers): HtmlResponse
    {
        // Si tu as une vue dédiée d’erreur debug, tu peux la rendre ici.
        // Fallback: on s’appuie sur Logger pour un rendu clair.
        ob_start();
        Logger::configure(['verbosity' => 'normal', 'show_payload' => true, 'show_trace' => true]);
        Logger::dump('⚠️ Exception (debug)', $payload);
        $html = (string) ob_get_clean();

        return new HtmlResponse($html, $status, $headers);
    }

    private function htmlGeneric(array $payload, int $status, array $headers): \Ivi\Http\HtmlResponse
    {
        // 1) Essayez une vue dédiée si disponible
        $viewFile = null;
        $candidates = [
            'errors/' . $status, // ex: errors/404
            'errors/error',      // fallback générique
        ];

        foreach ($candidates as $name) {
            $path = $this->resolveViewPath($name);
            if (is_file($path)) {
                $viewFile = $path;
                break;
            }
        }

        if ($viewFile) {
            ob_start();
            $payload['status'] = $status; // pour la vue
            /** @var array $payload */
            $data = ['payload' => $payload];
            extract($data, EXTR_SKIP);
            require $viewFile;
            $html = (string)ob_get_clean();
            return new \Ivi\Http\HtmlResponse($html, $status, $headers);
        }

        // 2) Fallback HTML minimal (ancien code)
        $title = htmlspecialchars((string)($payload['error'] ?? 'Error'), ENT_QUOTES, 'UTF-8');
        $msg   = htmlspecialchars((string)($payload['message'] ?? ''), ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$status} {$title}</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;margin:0;padding:2rem;background:#fafafa;color:#222}
.card{max-width:720px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.06);padding:1.25rem 1.5rem}
h1{font-size:1.125rem;margin:.25rem 0}
p{opacity:.85}
small{opacity:.6}
</style>
</head>
<body>
  <div class="card">
    <h1>{$title}</h1>
    <p>{$msg}</p>
    <small>Status: {$status}</small>
  </div>
</body>
</html>
HTML;

        return new \Ivi\Http\HtmlResponse($html, $status, $headers);
    }

    /** Résout le chemin absolu d’une vue sans le forcer à la classe View */
    private function resolveViewPath(string $name): string
    {
        $relative = str_replace(['.', '\\'], DIRECTORY_SEPARATOR, $name) . '.php';
        $base = \defined('VIEWS') ? VIEWS : (\getcwd() . '/views/');
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    }


    // ---------------------------
    // CLI rendering
    // ---------------------------

    private function renderCli(Throwable $e): TextResponse
    {
        $debug = (bool)($this->appConfig['debug'] ?? false);

        $lines = [];
        $lines[] = '✖ Exception: ' . get_class($e);
        $lines[] = '  Message : ' . $e->getMessage();

        if ($debug) {
            $lines[] = '  File    : ' . $e->getFile() . ':' . $e->getLine();
            $lines[] = '  Trace   :';
            foreach ($e->getTrace() as $i => $t) {
                $fn = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
                $file = $t['file'] ?? 'unknown';
                $line = $t['line'] ?? 0;
                $lines[] = sprintf("    #%d %s (%s:%s)", $i, $fn, $file, (string)$line);
            }
        } else {
            $lines[] = '  (Run with debug=true for more details)';
        }

        return new TextResponse(implode(PHP_EOL, $lines), 1);
    }

    // ---------------------------
    // Logging
    // ---------------------------

    /** @param array<string,mixed> $payload */
    private function logException(Throwable $e, array $payload): void
    {
        try {
            Logger::configure(['verbosity' => 'normal', 'show_trace' => true, 'show_payload' => true]);
            Logger::error('Unhandled exception', [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            // Ne jamais faire échouer le handler à cause du logging
        }
    }
}
