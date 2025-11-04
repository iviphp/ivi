<?php

declare(strict_types=1);

namespace Ivi\Http;

final class HtmlResponse extends Response
{
    public function __construct(string $html = '', int $status = 200, array $headers = [])
    {
        parent::__construct($html, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public static function fromView(string $html): self
    {
        return new self($html, 200);
    }
}
