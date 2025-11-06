<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class QueryException extends ORMException
{
    /** @var array<string,mixed> */
    protected array $context;

    /**
     * @param array<string,mixed> $context  e.g. ['sql' => '...', 'bindings' => [...]]
     */
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
