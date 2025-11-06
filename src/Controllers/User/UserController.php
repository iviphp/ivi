<?php

namespace App\Controllers\User;

use App\Controllers\Controller;
use App\Repositories\UserRepository;
use App\Models\User;
use Ivi\Http\Exceptions\NotFoundHttpException;
use Ivi\Http\HtmlResponse;
use Ivi\Http\Response;
use Ivi\Http\Request;
use Ivi\Core\ORM\Pagination;

final class UserController extends Controller
{
    private UserRepository $repo;

    public function __construct()
    {
        $this->repo = new UserRepository();
    }

    // GET /users
    public function index(Request $request): HtmlResponse
    {
        $q       = $request->query();
        $page    = max(1, (int)($q['page']     ?? 1));
        $perPage = max(1, (int)($q['per_page'] ?? 5));
        $offset  = ($page - 1) * $perPage;

        // total
        $row   = User::query()->select('COUNT(*) AS c')->first();
        $total = (int)($row['c'] ?? 0);

        // rows
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

    // GET /users/:id
    public function show(int $id): HtmlResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');
        return $this->view('user.show', ['user' => $user]);
    }

    // GET /users/create
    public function create(): HtmlResponse
    {
        return $this->view('user.create');
    }

    // POST /users
    public function store(Request $request): Response
    {
        $post = $request->post();
        $data = [
            'name'   => trim((string)($post['name']  ?? '')),
            'email'  => trim((string)($post['email'] ?? '')),
            'password' => password_hash((string)($post['password'] ?? ''), PASSWORD_BCRYPT),
            'active' => isset($post['active']) ? 1 : 0,
        ];
        if ($data['name'] === '' || $data['email'] === '' || empty($post['password'])) {
            return \Ivi\Http\Response::redirect('/users/create');
        }

        $user = User::create($data);
        return Response::redirect('/users/' . (int)$user->toArray()['id']);
    }

    // GET /users/:id/edit
    public function edit(int $id): HtmlResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');
        return $this->view('user.edit', ['user' => $user]);
    }

    // POST /users/:id
    public function update(int $id, Request $request): Response
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundHttpException('User not found.');

        $post = $request->post();

        $data = [
            'name'   => array_key_exists('name', $post)   ? trim((string)$post['name'])   : $user->name,
            'email'  => array_key_exists('email', $post)  ? trim((string)$post['email'])  : $user->email,
            'active' => array_key_exists('active', $post) ? 1 : 0,
        ];

        $user->fill($data)->save();

        return Response::redirect('/users/' . $id);
    }

    // POST /users/:id/delete
    public function destroy(int $id): Response
    {
        $user = User::find($id);
        if ($user) $user->delete();
        return Response::redirect('/users');
    }
}
