<?php

declare(strict_types=1);

namespace Ivi\Core\Validation\Contracts;

/**
 * Interface Rule
 *
 * @package Ivi\Core\Validation\Contracts
 *
 * @brief Contract for all validation rules in the Ivi.php framework.
 *
 * The `Rule` interface defines the minimal structure that every validation
 * rule must implement. It ensures that each rule can determine whether a
 * given field value passes validation and can return a human-readable
 * error message when it fails.
 *
 * ### Responsibilities
 * - Encapsulate the logic for a single validation constraint.
 * - Expose a consistent API for checking (`passes()`) and describing (`message()`).
 * - Remain independent of any specific request or model context.
 *
 * ### Design Principles
 * - Rules are **stateless** and can be reused across multiple validations.
 * - Rules should return `true` for valid values, and `false` for invalid ones.
 * - Error messages may contain placeholders (`:attribute`, `:params`)
 *   that the `Validator` will automatically replace with contextual data.
 *
 * ### Example — Custom Rule
 * ```php
 * use Ivi\Core\Validation\Contracts\Rule;
 *
 * final class AlphaDash implements Rule
 * {
 *     public function passes(mixed $value, array $data, string $field): bool
 *     {
 *         return $value === null || preg_match('/^[A-Za-z0-9_-]+$/', (string)$value);
 *     }
 *
 *     public function message(string $field): string
 *     {
 *         return 'The :attribute may only contain letters, numbers, dashes and underscores.';
 *     }
 * }
 * ```
 *
 * ### Example — Integration with Validator
 * ```php
 * $validator = new Validator($data, [
 *     'username' => [new AlphaDash(), 'required', 'min:3'],
 * ]);
 * $validated = $validator->validate();
 * ```
 *
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ValidationException
 * @see \Ivi\Core\Validation\Messages
 */
interface Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param mixed               $value The value of the field being validated.
     * @param array<string,mixed> $data  All input data (for cross-field dependencies).
     * @param string              $field The name of the field being validated.
     *
     * @return bool True if the rule passes, false otherwise.
     */
    public function passes(mixed $value, array $data, string $field): bool;

    /**
     * Return the validation error message for this rule.
     *
     * May contain placeholders such as:
     * - `:attribute` → replaced by the field name.
     * - `:params` → replaced by rule parameters (e.g. min/max values).
     *
     * @param string $field The name of the field.
     * @return string The formatted error message template.
     */
    public function message(string $field): string;
}
