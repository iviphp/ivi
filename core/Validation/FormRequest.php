<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

use Ivi\Http\Request;

/**
 * Class FormRequest
 *
 * @package Ivi\Core\Validation
 *
 * @brief Base class for self-validating form request objects.
 *
 * The `FormRequest` class extends the core `Request` and provides
 * built-in validation capabilities. It allows developers to define
 * validation rules directly inside dedicated request classes, keeping
 * controllers clean and declarative.
 *
 * ### Purpose
 * - Encapsulate validation logic in reusable request objects.
 * - Automatically validate input data before reaching the controller.
 * - Provide a convenient `validated()` method returning clean data.
 *
 * ### Example Usage
 * ```php
 * final class CreateUserRequest extends FormRequest
 * {
 *     public function rules(): array
 *     {
 *         return [
 *             'name'     => 'required|string|min:3|max:50',
 *             'email'    => 'required|email|max:120',
 *             'password' => 'required|min:6',
 *         ];
 *     }
 * }
 *
 * // Controller example:
 * public function store(CreateUserRequest $request): Response
 * {
 *     $data = $request->validated();
 *     User::create($data);
 *     return Response::redirect('/users');
 * }
 * ```
 *
 * ### Design Notes
 * - `rules()` must be implemented by subclasses.
 * - `validated()` executes validation immediately when called.
 * - Throws `ValidationException` on failure.
 * - Integrates seamlessly with dependency injection and controller methods.
 *
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ValidationException
 */
abstract class FormRequest extends Request
{
    /**
     * Define validation rules for this request.
     *
     * Must be implemented by subclasses to specify the validation rules
     * applicable to their particular request context.
     *
     * @return array<string,mixed> The rule definitions per field.
     */
    abstract public function rules(): array;

    /**
     * Validate the current request data using the rules() definition.
     *
     * Automatically creates a `Validator` instance, runs all rules,
     * and returns the sanitized, validated data array. Throws
     * `ValidationException` if any rule fails.
     *
     * @return array<string,mixed> The validated and sanitized data.
     *
     * @throws ValidationException If validation fails.
     */
    public function validated(): array
    {
        $validator = new Validator($this->all(), $this->rules());
        return $validator->validate();
    }
}
