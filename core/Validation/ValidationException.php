<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

use Exception;

/**
 * Class ValidationException
 *
 * @package Ivi\Core\Validation
 *
 * @brief Exception thrown when validation fails.
 *
 * The `ValidationException` represents a standardized error state for
 * failed input validation within the Ivi.php framework. It is thrown
 * automatically by the `Validator` class when one or more rules fail.
 *
 * This exception carries an instance of `ErrorBag`, which contains all
 * validation errors grouped by field name. It enables controllers, middleware,
 * or global exception handlers to respond consistently to validation errors,
 * whether rendering a view or returning a JSON response.
 *
 * ### Typical Usage
 * ```php
 * try {
 *     $validated = (new Validator($request->post(), [
 *         'email' => 'required|email',
 *         'password' => 'required|min:6',
 *     ]))->validate();
 * } catch (ValidationException $e) {
 *     return $this->view('user.create', [
 *         'errors' => $e->errors(),
 *         'old'    => $request->post(),
 *     ], $request, 422);
 * }
 * ```
 *
 * ### Responsibilities
 * - Encapsulates all validation errors inside an `ErrorBag`
 * - Provides a unified exception type for failed validation
 * - Default HTTP-like status code `422` (Unprocessable Entity)
 * - Integrates seamlessly with controllers and view rendering
 *
 * ### Design Notes
 * - `errors()` returns the same `ErrorBag` used internally by the validator.
 * - The exception message defaults to `"The given data was invalid."`.
 * - Intended for both HTML and API (JSON) error reporting layers.
 *
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ErrorBag
 */
final class ValidationException extends Exception
{
    /**
     * Create a new ValidationException instance.
     *
     * @param ErrorBag $errors  The collection of validation errors.
     * @param string   $message Optional exception message (default: "The given data was invalid.").
     * @param int      $code    Optional status code, defaults to 422 (Unprocessable Entity).
     */
    public function __construct(
        private readonly ErrorBag $errors,
        string $message = 'The given data was invalid.',
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Retrieve the ErrorBag instance containing all validation errors.
     *
     * @return ErrorBag
     */
    public function errors(): ErrorBag
    {
        return $this->errors;
    }
}
