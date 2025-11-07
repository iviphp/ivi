<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class StringRule
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a given field value is a string.
 *
 * The `StringRule` passes if the value is either `null` or a valid string.
 * Empty strings are allowed unless combined with the `required` rule.
 *
 * ### Example
 * ```php
 * 'username' => 'required|string|min:3'
 * ```
 */
final class StringRule implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        return $value === null || is_string($value);
    }

    public function message(string $field): string
    {
        return 'The :attribute must be a string.';
    }
}
