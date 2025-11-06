<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class TransactionException extends ORMException
{
    public function __construct(string $message = 'Database transaction failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
