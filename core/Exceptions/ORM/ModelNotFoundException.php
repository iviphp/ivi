<?php

declare(strict_types=1);

namespace Ivi\Core\Exceptions\ORM;

final class ModelNotFoundException extends ORMException
{
    public function __construct(string $model, string $key, string|int $value)
    {
        parent::__construct("Model not found: {$model} where {$key} = {$value}");
    }
}
