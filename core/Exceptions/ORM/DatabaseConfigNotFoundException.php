<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class DatabaseConfigNotFoundException extends ORMException
{
    public function __construct(string $path, ?\Throwable $previous = null)
    {
        parent::__construct("Database config not found at: {$path}", 0, $previous);
    }
}
