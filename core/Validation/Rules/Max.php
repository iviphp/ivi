<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Max
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value or length does not exceed a maximum.
 *
 * The `Max` rule checks numeric, string, and countable values.  
 * - Numbers are compared directly.  
 * - Strings are compared by length.  
 * - Arrays are compared by element count.
 *
 * ### Example
 * ```php
 * 'title' => 'string|max:255'
 * 'quantity' => 'integer|max:100'
 * ```
 */
final class Max implements Rule
{
    public function __construct(private readonly float|int $max) {}

    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        if (is_numeric($value)) return (float)$value <= (float)$this->max;
        if (is_string($value)) return mb_strlen($value) <= (int)$this->max;
        if (is_countable($value)) return count($value) <= (int)$this->max;
        return false;
    }

    public function message(string $field): string
    {
        return 'The :attribute may not be greater than :params.';
    }
}
