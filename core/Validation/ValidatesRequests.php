<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

use Ivi\Http\Request;

/**
 * Trait ValidatesRequests
 *
 * @package Ivi\Core\Validation
 *
 * @brief Provides convenient validation capabilities to controllers.
 *
 * The `ValidatesRequests` trait offers a simple and expressive way to
 * validate incoming HTTP request data directly within controller methods.
 * It acts as a lightweight bridge between the `Request` object and the
 * core `Validator` engine.
 *
 * By including this trait, any controller can call `$this->validate($request, $rules)`
 * to automatically perform validation, retrieve sanitized data, and handle
 * validation errors consistently.
 *
 * ### Example Usage
 * ```php
 * use Ivi\Core\Validation\ValidatesRequests;
 * use Ivi\Core\Validation\ValidationException;
 *
 * final class UserController extends Controller
 * {
 *     use ValidatesRequests;
 *
 *     public function store(Request $request): Response
 *     {
 *         try {
 *             $validated = $this->validate($request, [
 *                 'name'     => 'required|string|min:3|max:50',
 *                 'email'    => 'required|email',
 *                 'password' => 'required|min:6',
 *             ]);
 *
 *             User::create($validated);
 *             return Response::redirect('/users');
 *         } catch (ValidationException $e) {
 *             return $this->view('user.create', [
 *                 'errors' => $e->errors(),
 *                 'old'    => $request->post(),
 *             ], $request, 422);
 *         }
 *     }
 * }
 * ```
 *
 * ### Responsibilities
 * - Automatically delegates validation to the `Validator` class
 * - Extracts all input data from the current `Request`
 * - Returns sanitized, validated data on success
 * - Throws `ValidationException` on failure
 *
 * ### Design Notes
 * - Keeps controllers clean and declarative by avoiding manual `Validator` instantiation.
 * - Intended for use in any class that has access to an `Ivi\Http\Request` instance.
 * - Fully framework-agnostic; may be reused in custom components or modules.
 *
 * @method array validate(Request $request, array $rules)
 * @throws ValidationException if validation fails
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ValidationException
 * @see \Ivi\Core\Validation\ErrorBag
 */
trait ValidatesRequests
{
    /**
     * Validate the given HTTP request against a set of rules.
     *
     * This method automatically retrieves all input data from the
     * provided `Request` object, passes it through the `Validator`,
     * and returns an array of validated fields.
     *
     * If any rule fails, a `ValidationException` will be thrown,
     * allowing the controller to gracefully handle and display errors.
     *
     * @param Request              $request The incoming HTTP request instance.
     * @param array<string, mixed> $rules   Validation rules for each field.
     *
     * @return array<string, mixed> The validated and sanitized input data.
     *
     * @throws ValidationException If validation fails for any field.
     */
    public function validate(Request $request, array $rules): array
    {
        $validator = new Validator($request->all(), $rules);
        return $validator->validate();
    }
}
