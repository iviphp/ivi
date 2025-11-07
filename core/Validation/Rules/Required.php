<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Required
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Ensures that a field is present and not empty.
 *
 * The `Required` rule fails if:
 * - the field is missing from input,
 * - its value is `null`, or
 * - its value is an empty string.
 *
 * ### Example
 * ```php
 * 'email' => 'required|email'
 * ```
 */
final class Required implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) return false;
        if ($value === null) return false;
        if (is_string($value) && trim($value) === '') return false;
        return true;
    }

    public function message(string $field): string
    {
        return 'The :attribute field is required.';
    }
}
