<?php

declare(strict_types=1);

namespace Ivi\Http;

final class Request
{
    private string $method;
    private string $path;
    private array $headers = [];
    private array $query = [];
    private array $post = [];
    private array $files = [];
    private string $rawBody;

    public function __construct(
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        array $post = [],
        array $files = [],
        string $rawBody = ''
    ) {
        $this->method  = strtoupper($method);
        $this->path    = '/' . trim($path, '/');
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->query   = $query;
        $this->post    = $post;
        $this->files   = $files;
        $this->rawBody = $rawBody;
    }

    // Factory: crée depuis superglobales PHP
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $rawBody = file_get_contents('php://input') ?: '';
        return new self($method, $uri, $headers, $_GET, $_POST, $_FILES, $rawBody);
    }

    // ---------------------
    // Accessors
    // ---------------------
    public function method(): string
    {
        return $this->method;
    }
    public function path(): string
    {
        $uri  = $_SERVER['REQUEST_URI']    ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Normalise séparateurs
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $baseDir    = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        // Supprime base path si l’app est sous un sous-dossier
        if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }

        // Enlève /index.php
        if ($path === '/index.php') {
            $path = '/';
        }

        // Supprime le slash final (sauf racine) pour éviter les doublons
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if ($path === '') $path = '/';
        return $path;
    }


    public function query(): array
    {
        return $this->query;
    }
    public function post(): array
    {
        return $this->post;
    }
    public function files(): array
    {
        return $this->files;
    }
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    public function json(): array
    {
        $contentType = strtolower($this->header('content-type', ''));
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($this->rawBody, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json());
    }

    // Vérifie si c’est une requête AJAX/JSON
    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');
        return str_contains($accept, 'application/json');
    }
}
