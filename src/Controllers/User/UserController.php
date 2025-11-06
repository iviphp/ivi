<?php

namespace App\Controllers\User;

use App\Controllers\Controller;
use App\Repositories\UserRepository;
use Ivi\Http\HtmlResponse;

class UserController extends Controller
{
    public function index(): HtmlResponse
    {
        $repo = new UserRepository();
        $users = $repo->all();

        return $this->view('user.index', ['users' => $users]);
    }
}
