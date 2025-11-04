<?php

declare(strict_types=1);

namespace Ivi\Http;

final class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, ['Location' => $url] + $headers);
    }

    public static function to(string $url, int $status = 302): self
    {
        return new self($url, $status);
    }

    public static function permanent(string $url): self
    {
        return new self($url, 301);
    }
}
