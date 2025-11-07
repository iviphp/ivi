<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Email
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field contains a valid email address.
 *
 * The `Email` rule uses PHP’s native `FILTER_VALIDATE_EMAIL` for
 * robust and RFC-compliant validation. Empty values automatically
 * pass to allow optional email fields.
 *
 * ### Example
 * ```php
 * 'email' => 'required|email'
 * ```
 */
final class Email implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return filter_var((string)$value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field): string
    {
        return 'The :attribute must be a valid email address.';
    }
}
