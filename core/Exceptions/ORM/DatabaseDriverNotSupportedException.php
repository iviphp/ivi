<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class DatabaseDriverNotSupportedException extends ORMException
{
    public function __construct(string $driver, ?\Throwable $previous = null)
    {
        parent::__construct("Database driver not supported: {$driver}", 0, $previous);
    }
}
