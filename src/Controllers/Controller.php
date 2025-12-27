<?php

declare(strict_types=1);

namespace App\Controllers;

use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;
use Ivi\Http\JsonResponse;
use Ivi\Http\TextResponse;
use Ivi\Http\RedirectResponse;
use Ivi\Core\View\ViewNotFoundException;

/**
 * Class Controller
 *
 * @package App\Controllers
 *
 * @brief Base controller class for all HTTP controllers in an Ivi.php application.
 *
 * This abstract base class defines a unified interface for rendering views,
 * returning responses, and managing layouts. All application controllers
 * should extend this class to ensure consistent behavior and predictable
 * response handling across the framework.
 *
 * ### Design Philosophy
 * - **Minimal & Explicit** — Keeps logic simple and transparent.
 * - **Consistent Response Types** — Always returns typed responses
 *   (`HtmlResponse`, `JsonResponse`, `TextResponse`, `RedirectResponse`).
 * - **Framework Integration** — Provides built-in helpers that interact
 *   seamlessly with the Ivi.php HTTP and View layers.
 *
 * ### Responsibilities
 * - Render view templates (`render()` / `view()`)
 * - Manage layout inheritance (`setLayout()`)
 * - Provide helper methods for HTML, JSON, plain text, and redirects
 * - Detect and handle AJAX/JSON requests automatically
 * - Abstract away filesystem and buffer management for views
 *
 * ### Rendering Flow
 * 1. The `view()` method resolves a dot-notation path (e.g., `"user.index"`)
 *    into a PHP file under `/views/`.
 * 2. The view is rendered with the provided parameters and optionally wrapped
 *    in a layout (default: `base.php`).
 * 3. The final HTML is returned as an `HtmlResponse` instance.
 *
 * ### Example
 * ```php
 * class HomeController extends Controller
 * {
 *     public function index(Request $request): HtmlResponse
 *     {
 *         return $this->view('home.index', ['title' => 'Welcome']);
 *     }
 *
 *     public function about(): HtmlResponse
 *     {
 *         return $this->view('pages.about');
 *     }
 * }
 * ```
 *
 * ### Customization
 * - Use `setLayout('layouts/main.php')` to override the layout for the controller.
 * - Pass an explicit status code to `view()` (e.g., `view('user.create', [...], $req, 422)`).
 * - The `isAjax()` helper ensures that JSON/XHR requests bypass layout wrapping.
 *
 * @see \Ivi\Http\HtmlResponse
 * @see \Ivi\Core\View\ViewNotFoundException
 */
abstract class Controller
{
    /** @var string Default layout file (relative to /views directory). */
    protected string $layout = 'base.php';
    protected static array $layoutVars = [];
    protected static array $viewNamespaces = [];

    /** Définit une variable de layout disponible dans base.php (ex: 'title') */
    protected function setLayoutVar(string $key, mixed $value): void
    {
        self::$layoutVars[$key] = $value;
    }

    /**
     * Shortcut to set the page title safely.
     * 
     * @param string|null $title Page title. If null, a default will be used.
     */
    protected function setPageTitle(?string $title): void
    {
        // fallback si null
        $safeTitle = $title ?? 'Softadastra';

        // assure que c'est bien une string
        $this->setLayoutVar('title', (string)$safeTitle);
    }

    /**
     * Render a view file into a full HTML response.
     *
     * This method handles:
     * - Resolving dot-notation view paths (e.g. "user.index" → "views/user/index.php")
     * - Extracting parameters into local scope
     * - Wrapping rendered content with a layout (if it exists)
     * - Automatically skipping layouts for AJAX/JSON requests
     *
     * @param string               $path            The dot-notation path to the view file.
     * @param array<string,mixed>|null $params      Data to be passed into the view.
     * @param Request|null         $request         The current HTTP request (optional).
     * @param string|null          $layoutOverride  Custom layout file (optional).
     * @param int                  $status          HTTP status code for the response.
     *
     * @throws ViewNotFoundException if the view file cannot be found.
     * @return HtmlResponse
     */
    protected function render(
        string $path,
        ?array $params = null,
        ?Request $request = null,
        ?string $layoutOverride = null,
        int $status = 200
    ): HtmlResponse {
        [$baseForView, $fileRel] = $this->resolveViewPath($path);
        $filePath = $baseForView . $fileRel;

        if (!is_file($filePath)) {
            throw new ViewNotFoundException($filePath);
        }

        $params = $params ?? [];

        // Render view content (fragment)
        $content = $this->capture(function () use ($filePath, $params) {
            if (is_array($params)) extract($params, EXTR_SKIP);
            require $filePath;
        });

        $isAjax = $this->isAjax($request);

        // IMPORTANT:
        // If it's an AJAX navigation, we MUST return fragment + headers
        // even if 'spa' flag was not manually set.
        $isSPA = (bool)($params['spa'] ?? false);
        if ($isAjax) {
            $isSPA = true;
        }

        if ($isSPA && $isAjax) {
            $pageTitle = (string)(self::$layoutVars['title'] ?? ($params['title'] ?? 'Softadastra'));
            $pageId    = (string)($params['page_id'] ?? $path);

            $response = new HtmlResponse($content, $status);

            // Required SPA headers
            $response->header('X-Page-Title', $pageTitle);
            $response->header('X-Page-Id', $pageId);

            // Optional assets: { js:[], css:[] }
            $scripts = $params['spa_scripts'] ?? [];
            $styles  = $params['spa_styles'] ?? [];

            if (!empty($scripts) || !empty($styles)) {
                $response->header(
                    'X-Page-Assets',
                    json_encode(
                        [
                            'js'  => array_values((array)$scripts),
                            'css' => array_values((array)$styles),
                        ],
                        JSON_UNESCAPED_SLASHES
                    )
                );
            }

            // Nice for dev (avoid weird caching)
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');

            return $response;
        }

        // Normal full page rendering (layout)
        $layout = $layoutOverride ?? $this->layout;
        $layoutPath = $this->viewsBasePath() . $layout;

        if (!is_file($layoutPath)) {
            return new HtmlResponse($content, $status);
        }

        $__csrf_token = \Ivi\Core\Security\Csrf::generateToken(false);

        $full = $this->capture(function () use ($layoutPath, $content, $params, $__csrf_token) {
            $title = self::$layoutVars['title'] ?? ($params['title'] ?? 'Softadastra');

            if (!empty(self::$layoutVars)) extract(self::$layoutVars, EXTR_OVERWRITE);
            if (is_array($params)) extract($params, EXTR_OVERWRITE);

            // NOTE: you don't need this line normally; layout already sets __SPA__.
            // Keeping it is OK but it's redundant.
            echo '<script>window.__SPA__ = true;</script>';

            require $layoutPath;
        });

        return new HtmlResponse($full, $status);
    }


    /**
     * Shortcut for rendering a view using the default layout.
     *
     * Equivalent to calling `render($path, $params, $request, null, $status)`.
     * Commonly used for controller endpoints that return standard HTML pages.
     *
     * @param string               $path     The dot-notation view path.
     * @param array<string,mixed>|null $params Data passed to the view.
     * @param Request|null         $request  Optional HTTP request object.
     * @param int                  $status   HTTP status code.
     *
     * @return HtmlResponse
     */
    protected function view(
        string $path,
        ?array $params = null,
        ?Request $request = null,
        int $status = 200
    ): HtmlResponse {

        if ($request && $this->isAjax($request)) {
            $params = $params ?? [];
            $params['spa'] = true;
        }

        return $this->render($path, $params, $request, null, $status);
    }

    /**
     * Change the default layout for subsequent views.
     *
     * @param string $layoutFile Relative path of the layout file (from `/views/`).
     * @return static
     */
    protected function setLayout(string $layoutFile): static
    {
        $this->layout = $layoutFile;
        return $this;
    }

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

    /**
     * Detect whether the current request was made via AJAX or expects JSON.
     *
     * Used to automatically disable layouts and return raw HTML for XHR requests.
     *
     * @param Request|null $request The current request (optional).
     * @return bool True if AJAX or JSON expected; false otherwise.
     */
    protected function isAjax(?Request $request): bool
    {
        if ($request) {
            $xrw = strtolower($request->header('x-requested-with', ''));
            if ($xrw === 'xmlhttprequest') return true;
            if ($request->wantsJson()) return true;
        }

        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xhr) === 'xmlhttprequest') return true;

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains(strtolower($accept), 'application/json');
    }

    /** Convert dot-notation paths to filesystem paths. */
    protected function dotToPath(string $path): string
    {
        return str_replace('.', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Determine the base directory for all view templates.
     *
     * @return string Absolute path to the views directory.
     */
    protected function viewsBasePath(): string
    {
        if (defined('VIEWS')) {
            $base = rtrim((string)VIEWS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } else {
            $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        }
        return $base;
    }

    /**
     * Capture the output buffer of a callback and return its rendered content.
     *
     * This is used internally to safely evaluate PHP templates and return their
     * output as strings without polluting global output buffers.
     *
     * @param callable $fn The function to capture output from.
     * @return string Rendered output as a string.
     */
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

    public static function addViewNamespace(string $ns, string $path): void
    {
        self::$viewNamespaces[$ns] = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    protected function resolveViewPath(string $path): array
    {
        if (strpos($path, '::') !== false) {
            [$ns, $rel] = explode('::', $path, 2);
            if (isset(self::$viewNamespaces[$ns])) {
                $base = self::$viewNamespaces[$ns];
                $file = str_replace('.', DIRECTORY_SEPARATOR, $rel) . '.php';
                return [$base, $file];
            }
        }

        $base = $this->viewsBasePath();
        $file = $this->dotToPath($path) . '.php';
        return [$base, $file];
    }

    protected function resolveLayoutPath(string $layout): array
    {
        if (strpos($layout, '::') !== false) {
            [$ns, $rel] = explode('::', $layout, 2);
            if (isset(self::$viewNamespaces[$ns])) {
                $base = self::$viewNamespaces[$ns];
                $rel = str_ends_with($rel, '.php') ? $rel : ($rel . '.php');
                $file = str_replace('.', DIRECTORY_SEPARATOR, $rel);
                return [$base, $file];
            }
        }

        $base = $this->viewsBasePath();
        $file = $layout;
        return [$base, $file];
    }
}
