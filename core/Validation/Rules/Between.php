<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Rules;

use Ivi\Core\Validation\Contracts\Rule;

/**
 * Class Between
 *
 * @package Ivi\Core\Validation\Rules
 *
 * @brief Validates that a field’s value or length lies within a specified range.
 *
 * The `Between` rule supports numeric, string, and countable values.  
 * - For numbers, compares the actual numeric value.  
 * - For strings, compares character length.  
 * - For arrays or countables, compares element count.
 *
 * ### Example
 * ```php
 * 'age' => 'integer|between:18,60'
 * 'title' => 'string|between:3,100'
 * ```
 */
final class Between implements Rule
{
    public function __construct(private readonly float|int $min, private readonly float|int $max) {}

    public function passes(mixed $value, array $data, string $field): bool
    {
        if ($value === null || $value === '') return true;

        if (is_numeric($value)) {
            $v = (float)$value;
            return $v >= (float)$this->min && $v <= (float)$this->max;
        }
        if (is_string($value)) {
            $len = mb_strlen($value);
            return $len >= (int)$this->min && $len <= (int)$this->max;
        }
        if (is_countable($value)) {
            $n = count($value);
            return $n >= (int)$this->min && $n <= (int)$this->max;
        }
        return false;
    }

    public function message(string $field): string
    {
        return 'The :attribute must be between :params.';
    }
}
