<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class In
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value is included in a predefined set.
 *
 * The `In` rule checks if a given value exists within a provided list of
 * allowed values. Null or empty values automatically pass.
 *
 * ### Example
 * ```php
 * 'status' => ['required', new In('active', 'pending', 'archived')]
 * ```
 */
final class In implements Rule
{
    /** @var string[] */
    private array $set;

    public function __construct(string ...$values)
    {
        $this->set = $values;
    }

    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;
        return in_array((string)$value, $this->set, true);
    }

    public function message(string $field): string
    {
        return 'The :attribute must be one of: :params.';
    }
}
