<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class IntegerRule
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value is an integer.
 *
 * The `IntegerRule` accepts integer types or numeric strings that can be
 * converted into integers. Optional fields pass automatically if empty.
 *
 * ### Example
 * ```php
 * 'age' => 'required|integer|min:18'
 * ```
 */
final class IntegerRule implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function message(string $field): string
    {
        return 'The :attribute must be an integer.';
    }
}
