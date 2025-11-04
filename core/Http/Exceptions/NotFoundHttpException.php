<?php

declare(strict_types=1);

namespace Ivi\Http\Exceptions;

class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Not Found', array $headers = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $headers, $previous);
    }
}
