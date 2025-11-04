<?php

declare(strict_types=1);

namespace Ivi\Http;

class Response
{
    private int $status;
    private array $headers = [];
    private string $content;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status  = $status;
        $this->headers = $headers;
    }

    // ---------------------
    // Static factories
    // ---------------------
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function text(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    // ---------------------
    // Output
    // ---------------------
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $key => $value) {
                header("$key: $value", true);
            }
        }
        echo $this->content;
    }

    // ---------------------
    // Accessors
    // ---------------------
    public function status(): int
    {
        return $this->status;
    }
    public function headers(): array
    {
        return $this->headers;
    }
    public function content(): string
    {
        return $this->content;
    }

    // ---------------------
    // Chainable methods
    // ---------------------
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }
}
