<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

/**
 * Class Messages
 *
 * @package Ivi\Core\Validation
 *
 * @brief Provides the default validation error messages for Ivi.php.
 *
 * The `Messages` class defines the default human-readable messages used by
 * the validation system. Each message template corresponds to a validation
 * rule and may contain dynamic placeholders such as `:attribute` and `:params`,
 * which are automatically replaced by the validator during error formatting.
 *
 * ### Responsibilities
 * - Centralizes all default validation messages.
 * - Ensures consistent and translatable output across the framework.
 * - Allows easy customization or extension for localization.
 *
 * ### Placeholders
 * - `:attribute` → replaced with the field name (e.g. `"email"`).
 * - `:params` → replaced with rule parameters (e.g. `"3,10"` for `between:3,10`).
 *
 * ### Example
 * ```php
 * $messages = Messages::defaults();
 * echo $messages['required']; // "The :attribute field is required."
 * ```
 *
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ValidationException
 */
final class Messages
{
    /**
     * Return the framework’s default validation messages.
     *
     * @return array<string,string> A map of rule names to message templates.
     */
    public static function defaults(): array
    {
        return [
            'required'  => 'The :attribute field is required.',
            'string'    => 'The :attribute must be a string.',
            'integer'   => 'The :attribute must be an integer.',
            'numeric'   => 'The :attribute must be a number.',
            'email'     => 'The :attribute must be a valid email address.',
            'min'       => 'The :attribute must be at least :params.',
            'max'       => 'The :attribute may not be greater than :params.',
            'between'   => 'The :attribute must be between :params.',
            'in'        => 'The :attribute must be one of: :params.',
            'regex'     => 'The :attribute format is invalid.',
            'sometimes' => '', // special case: silent rule, no message
        ];
    }
}
