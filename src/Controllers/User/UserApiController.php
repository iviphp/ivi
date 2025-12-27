<?php

namespace App\Controllers\User;

use App\Controllers\Controller;
use App\Models\User;
use Ivi\Http\Request;
use Ivi\Http\Response;
use Ivi\Http\Exceptions\NotFoundHttpException;
use Ivi\Core\Validation\ValidatesRequests;
use Ivi\Core\Validation\ValidationException;
use Ivi\Core\Validation\Validator;

final class UserApiController extends Controller
{
    use ValidatesRequests;

    // GET /api/users?page=1&per_page=5
    public function index(Request $request): Response
    {
        $q       = $request->query();
        $page    = max(1, (int)($q['page'] ?? 1));
        $perPage = max(1, (int)($q['per_page'] ?? 5));
        $offset  = ($page - 1) * $perPage;

        $row   = User::query()->select('COUNT(*) AS c')->first();
        $total = (int)($row['c'] ?? 0);

        $rows = User::query()
            ->orderBy('id DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return Response::json([
            'ok' => true,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / max(1, $perPage)),
            ],
            'data' => $rows, // rows as arrays
        ]);
    }

    // GET /api/users/{id}
    public function show(int $id): Response
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');

        return Response::json([
            'ok' => true,
            'data' => $user->toArray(),
        ]);
    }

    // POST /api/users
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

            return Response::json([
                'ok' => true,
                'message' => 'User created.',
                'data' => $user->toArray(),
                'redirect' => '/users/' . (int)$user->toArray()['id'], // SPA can use this
            ], 201);
        } catch (ValidationException $e) {
            return Response::json([
                'ok' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    // PATCH /api/users/{id}  (ou POST + _method=PATCH)
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
                'name'   => array_key_exists('name', $validated)  ? trim((string)$validated['name'])  : $user->name,
                'email'  => array_key_exists('email', $validated) ? trim((string)$validated['email']) : $user->email,
                'active' => array_key_exists('active', $validated) ? 1 : $user->active,
            ];

            if (array_key_exists('password', $validated)) {
                $data['password'] = password_hash((string)$validated['password'], PASSWORD_BCRYPT);
            }

            $user->fill($data)->save();

            return Response::json([
                'ok' => true,
                'message' => 'User updated.',
                'data' => $user->toArray(),
                'redirect' => '/users/' . $id,
            ]);
        } catch (ValidationException $e) {
            return Response::json([
                'ok' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    // DELETE /api/users/{id} (ou POST + _method=DELETE)
    public function destroy(int $id): Response
    {
        $user = User::find($id);
        if ($user) $user->delete();

        return Response::json([
            'ok' => true,
            'message' => 'User deleted.',
            'redirect' => '/users',
        ]);
    }
}
