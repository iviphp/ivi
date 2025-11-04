<?php

declare(strict_types=1);

namespace Ivi\Http;

final class TextResponse extends Response
{
    public function __construct(string $text = '', int $status = 200, array $headers = [])
    {
        parent::__construct($text, $status, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
    }

    public static function from(string $text, int $status = 200): self
    {
        return new self($text, $status);
    }
}
