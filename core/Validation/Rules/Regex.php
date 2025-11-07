<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Regex
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value matches a given regular expression.
 *
 * The `Regex` rule passes if the field is empty (optional fields) or if
 * the provided value matches the specified regex pattern.
 *
 * ### Example
 * ```php
 * 'username' => ['required', new Regex('/^[A-Za-z0-9_]+$/')]
 * ```
 */
final class Regex implements Rule
{
    public function __construct(private readonly string $pattern) {}

    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return (bool)preg_match($this->pattern, (string)$value);
    }

    public function message(string $field): string
    {
        return 'The :attribute format is invalid.';
    }
}
