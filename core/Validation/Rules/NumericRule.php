<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class NumericRule
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field contains a numeric value.
 *
 * The `NumericRule` allows integers or floats represented as strings.
 * Null or empty values automatically pass (for optional fields).
 *
 * ### Example
 * ```php
 * 'price' => 'required|numeric|min:0'
 * ```
 */
final class NumericRule implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return is_numeric($value);
    }

    public function message(string $field): string
    {
        return 'The :attribute must be a number.';
    }
}
