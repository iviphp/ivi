<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Sometimes
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Marks a field as conditionally validated only when present.
 *
 * The `Sometimes` rule indicates that a field should only be validated
 * if it exists in the request data. This rule acts as a **marker** and
 * is handled internally by the `Validator` — it does not perform any
 * active validation itself.
 *
 * ### Example
 * ```php
 * 'password' => 'sometimes|string|min:6'
 * ```
 */
final class Sometimes implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        return true; // handled internally by the Validator
    }

    public function message(string $field): string
    {
        return '';
    }
}
