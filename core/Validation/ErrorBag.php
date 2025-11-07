<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

/**
 * Class ErrorBag
 *
 * @package Ivi\Core\Validation
 *
 * @brief Container for storing and accessing validation error messages.
 *
 * The `ErrorBag` is a lightweight, framework-agnostic data structure
 * that holds validation errors grouped by field name. It is used internally
 * by the `Validator` and included in `ValidationException` to provide
 * detailed feedback to the caller.
 *
 * ### Responsibilities
 * - Store error messages for each invalid field.
 * - Provide convenient access methods such as `first()` and `get()`.
 * - Return a structured associative array suitable for rendering in views or JSON responses.
 *
 * ### Example
 * ```php
 * $errors = new ErrorBag();
 * $errors->add('email', 'The email field is required.');
 * $errors->add('email', 'The email format is invalid.');
 *
 * $errors->get('email');   // ['The email field is required.', 'The email format is invalid.']
 * $errors->first('email'); // 'The email field is required.'
 * ```
 *
 * ### Design Notes
 * - Stores multiple messages per field.
 * - Returns an empty array when no messages exist for a given key.
 * - Used by `ValidationException` to provide consistent error reporting.
 *
 * @see \Ivi\Core\Validation\ValidationException
 * @see \Ivi\Core\Validation\Validator
 */
final class ErrorBag
{
    /** @var array<string,string[]> Map of field names to their validation error messages. */
    private array $errors = [];

    /** Add a validation error message for a specific field. */
    public function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /** Retrieve all validation errors. */
    public function all(): array
    {
        return $this->errors;
    }

    /** Check whether the bag is empty (no validation errors). */
    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    /** Retrieve all messages for a specific field. */
    public function get(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /** Retrieve the first validation message for a specific field, if any. */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
