<?php

declare(strict_types=1);

namespace Ivi\Http\Exceptions;

class MethodNotAllowedHttpException extends HttpException
{
    public function __construct(array $allowed, string $message = 'Method Not Allowed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, ['Allow' => implode(', ', $allowed)], $previous);
    }
}
