<?php

declare(strict_types=1);

namespace Ivi\Http;

final class RedirectResponse extends Response
{
    /** @var ?callable */
    private static $handler = null;

    private string $url;
    private int $status;

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        $this->url = $url;
        $this->status = $status;
        parent::__construct('', $status, ['Location' => $url] + $headers);
    }

    public function send(): void
    {
        if (self::$handler) {
            ($h = self::$handler)($this->url);
        } else {
            if (!headers_sent()) {
                header('Location: ' . $this->url, true, $this->status);
                exit;
            }
        }
    }

    public static function to(string $url, int $status = 302): self
    {
        return new self($url, $status);
    }

    public static function permanent(string $url): self
    {
        return new self($url, 301);
    }

    public static function setHandler(?callable $handler): void
    {
        self::$handler = $handler;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
