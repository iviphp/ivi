<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Min
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value or length is greater than or equal to a minimum.
 *
 * The `Min` rule checks numeric, string, and countable values.  
 * - Numbers are compared directly.  
 * - Strings are compared by length.  
 * - Arrays are compared by element count.
 *
 * ### Example
 * ```php
 * 'password' => 'required|string|min:6'
 * 'age' => 'integer|min:18'
 * ```
 */
final class Min implements Rule
{
    public function __construct(private readonly float|int $min) {}

    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        if (is_numeric($value)) return (float)$value >= (float)$this->min;
        if (is_string($value)) return mb_strlen($value) >= (int)$this->min;
        if (is_countable($value)) return count($value) >= (int)$this->min;
        return false;
    }

    public function message(string $field): string
    {
        return 'The :attribute must be at least :params.';
    }
}
