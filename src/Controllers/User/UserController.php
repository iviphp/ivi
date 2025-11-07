<?php

namespace App\Controllers\User;

use App\Controllers\Controller;
use App\Models\User;
use Ivi\Http\Exceptions\NotFoundHttpException;
use Ivi\Http\HtmlResponse;
use Ivi\Http\Response;
use Ivi\Http\Request;
use Ivi\Core\ORM\Pagination;
use Ivi\Core\Validation\ValidatesRequests;
use Ivi\Core\Validation\ValidationException;
use Ivi\Core\Validation\Validator;

/**
 * Class UserController
 *
 * @package App\Controllers\User
 *
 * @brief Handles all CRUD operations for the `User` resource.
 *
 * This controller provides a complete RESTful interface for managing users
 * within an Ivi.php application. It demonstrates the recommended patterns
 * for working with the built-in ORM, validation system, and view rendering.
 *
 * ### Key Responsibilities
 * - Display paginated lists of users (`index`)
 * - Show a single user's details (`show`)
 * - Render forms for creating or editing users (`create`, `edit`)
 * - Validate and persist new or updated user data (`store`, `update`)
 * - Delete users (`destroy`)
 *
 * ### Validation Workflow
 * The controller leverages the internal validation system (`Validator` and
 * `ValidatesRequests` trait) to ensure data integrity before interacting
 * with the database. All validation rules are declaratively defined using
 * a Laravel-like syntax (e.g. `required|string|min:3`), and errors are
 * automatically propagated back to the views.
 *
 * ### Conventions
 * - Each method corresponds to a specific HTTP verb and route pattern.
 * - Views are rendered through the base `Controller::view()` method, which
 *   automatically applies layouts and passes contextual data.
 * - Validation errors are returned with HTTP status code **422 (Unprocessable Entity)**.
 * - Responses are strictly typed using the framework’s response classes:
 *   `HtmlResponse`, `JsonResponse`, or `RedirectResponse`.
 *
 * ### Example Routes
 * ```
 * GET    /users             → index()
 * GET    /users/{id}        → show()
 * GET    /users/create      → create()
 * POST   /users             → store()
 * GET    /users/{id}/edit   → edit()
 * POST   /users/{id}        → update()
 * POST   /users/{id}/delete → destroy()
 * ```
 *
 * @see \Ivi\Core\Validation\Validator
 * @see \Ivi\Core\Validation\ValidationException
 * @see \App\Models\User
 */
final class UserController extends Controller
{
    use ValidatesRequests;

    /** Display a paginated list of all users. */
    public function index(Request $request): HtmlResponse
    {
        $q       = $request->query();
        $page    = max(1, (int)($q['page']     ?? 1));
        $perPage = max(1, (int)($q['per_page'] ?? 5));
        $offset  = ($page - 1) * $perPage;

        $row   = User::query()->select('COUNT(*) AS c')->first();
        $total = (int)($row['c'] ?? 0);

        $rows = User::query()
            ->orderBy('id DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        /** @var User[] $users */
        $users = array_map(fn($r) => new User($r), $rows);
        $pageDto = new Pagination($users, $total, $perPage, $page);

        return $this->view('user.index', ['page' => $pageDto]);
    }

    /** Show details of a specific user by ID. */
    public function show(int $id): HtmlResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');
        return $this->view('user.show', ['user' => $user]);
    }

    /** Render the "create user" form. */
    public function create(): HtmlResponse
    {
        return $this->view('user.create');
    }

    /**
     * Handle form submission for creating a new user.
     *
     * Validates the input, hashes the password, and persists the record.
     * On validation failure, the form is re-rendered with contextual error messages.
     */
    public function store(Request $request): Response
    {
        try {
            $validated = $this->validate($request, [
                'name'     => 'required|string|min:3|max:50',
                'email'    => 'required|email|max:120',
                'password' => 'required|string|min:6',
                'active'   => 'sometimes',
            ]);

            $user = User::create([
                'name'     => trim((string)$validated['name']),
                'email'    => trim((string)$validated['email']),
                'password' => password_hash((string)$validated['password'], PASSWORD_BCRYPT),
                'active'   => array_key_exists('active', $validated) ? 1 : 0,
            ]);

            return Response::redirect('/users/' . (int)$user->toArray()['id']);
        } catch (ValidationException $e) {
            return $this->view('user.create', [
                'errors' => $e->errors(),
                'old'    => $request->post(),
            ], $request, 422);
        }
    }

    /** Render the "edit user" form. */
    public function edit(int $id): HtmlResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');
        return $this->view('user.edit', ['user' => $user]);
    }

    /**
     * Handle update submission for an existing user.
     *
     * Supports partial updates: any field not submitted will remain unchanged.
     * Password updates are optional and will be ignored if the input is empty.
     * Validation errors are displayed inline in the edit view.
     */
    public function update(int $id, Request $request): Response
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');

        $post = $request->post();
        if (array_key_exists('password', $post) && trim((string)$post['password']) === '') {
            unset($post['password']);
        }

        try {
            $validated = (new Validator($post, [
                'name'     => 'sometimes|required|string|min:3|max:50',
                'email'    => 'sometimes|required|email|max:120',
                'password' => 'sometimes|string|min:6',
                'active'   => 'sometimes',
            ]))->validate();

            $data = [
                'name'   => array_key_exists('name', $validated)  ? trim((string)$validated['name'])   : $user->name,
                'email'  => array_key_exists('email', $validated) ? trim((string)$validated['email'])  : $user->email,
                'active' => array_key_exists('active', $validated) ? 1 : $user->active,
            ];

            if (array_key_exists('password', $validated)) {
                $data['password'] = password_hash((string)$validated['password'], PASSWORD_BCRYPT);
            }

            $user->fill($data)->save();
            return Response::redirect('/users/' . $id);
        } catch (ValidationException $e) {
            return $this->view('user.edit', [
                'user'   => $user,
                'errors' => $e->errors(),
                'old'    => $post,
            ], $request, 422);
        }
    }

    /** Delete the specified user and redirect back to the user list. */
    public function destroy(int $id): Response
    {
        $user = User::find($id);
        if ($user) $user->delete();
        return Response::redirect('/users');
    }
}
