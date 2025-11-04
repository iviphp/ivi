<?php

declare(strict_types=1);

namespace App\Controllers;

use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;
use Ivi\Http\JsonResponse;
use Ivi\Http\TextResponse;
use Ivi\Http\RedirectResponse;
use Ivi\Core\View\ViewNotFoundException;

abstract class Controller
{
    protected string $layout = 'base.php';

    protected function render(string $path, ?array $params = null, ?Request $request = null, ?string $layoutOverride = null): HtmlResponse
    {
        $viewsDir = $this->viewsBasePath();
        $filePath = $viewsDir . $this->dotToPath($path) . '.php';

        if (!is_file($filePath)) {
            throw new ViewNotFoundException($filePath);
        }

        $content = $this->capture(function () use ($filePath, $params) {
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            require $filePath;
        });

        if ($this->isAjax($request)) {
            return new HtmlResponse($content, 200);
        }

        $layout = $layoutOverride ?? $this->layout;
        $layoutPath = $viewsDir . $layout;

        if (!is_file($layoutPath)) {
            return new HtmlResponse($content, 200);
        }

        $full = $this->capture(function () use ($layoutPath, $content, $params) {
            if (is_array($params)) {
                extract($params, EXTR_SKIP);
            }
            require $layoutPath;
        });

        return new HtmlResponse($full, 200);
    }

    /**
     * Raccourci : vue avec le layout par défaut.
     * Équivalent de ton `view('path', params)`.
     */
    protected function view(string $path, ?array $params = null, ?Request $request = null): HtmlResponse
    {
        return $this->render($path, $params, $request, null);
    }

    /**
     * Fixe le layout par défaut (ex: "layouts/main.php")
     */
    protected function setLayout(string $layoutFile): static
    {
        $this->layout = $layoutFile;
        return $this;
    }

    // ---------------------
    // Helpers de réponses
    // ---------------------

    protected function html(string $html, int $status = 200): HtmlResponse
    {
        return new HtmlResponse($html, $status);
    }

    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    protected function text(string $text, int $status = 200): TextResponse
    {
        return new TextResponse($text, $status);
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    // ---------------------
    // Internals
    // ---------------------

    protected function isAjax(?Request $request): bool
    {
        // 1) Si Request est fourni (recommandé)
        if ($request) {
            $xrw = strtolower($request->header('x-requested-with', ''));
            if ($xrw === 'xmlhttprequest') return true;

            // JSON-first UX
            if ($request->wantsJson()) return true;
        }

        // 2) Fallback via superglobales (si Request non passé)
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xhr) === 'xmlhttprequest') return true;

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains(strtolower($accept), 'application/json');
    }

    /** Transforme "dir.file" en "dir/file" */
    protected function dotToPath(string $path): string
    {
        return str_replace('.', DIRECTORY_SEPARATOR, $path);
    }

    /** Détermine le dossier des vues */
    protected function viewsBasePath(): string
    {
        if (defined('VIEWS')) {
            $base = rtrim((string) VIEWS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } else {
            // fallback par défaut : <project>/views/
            $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        }
        return $base;
    }

    /** Capture le buffer d’un callable et retourne la chaîne rendue */
    protected function capture(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $out = ob_get_clean();
        }
        return $out ?: '';
    }
}
