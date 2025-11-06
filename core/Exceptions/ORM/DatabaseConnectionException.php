<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class DatabaseConnectionException extends ORMException
{
    public function __construct(string $dsn, ?\Throwable $previous = null)
    {
        parent::__construct("Failed to connect to database (DSN: {$dsn})", 0, $previous);
    }
}
