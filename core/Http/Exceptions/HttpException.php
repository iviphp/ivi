<?php

declare(strict_types=1);

namespace Ivi\Http\Exceptions;

class HttpException extends \RuntimeException
{
    protected int $statusCode = 500;
    protected array $headers = [];

    public function __construct(string $message = '', int $statusCode = 500, array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
