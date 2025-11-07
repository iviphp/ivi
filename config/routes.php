<?php

use App\Controllers\Docs\DocsController;
use App\Controllers\Home\HomeController;
use App\Controllers\User\UserController;
use Ivi\Core\View\View;
use Ivi\Http\JsonResponse;
use Ivi\Http\Request;

/* --------------------
 * Web
 * -------------------- */

$router->get('/', [HomeController::class, 'home']);
$router->get('/docs', [DocsController::class, 'index']);
$router->get('/about', fn() => 'About Page');
$router->get('/ping', fn() => new \Ivi\Http\Response('pong'));

/* --------------------
 * API
 * -------------------- */

$router->get('/api/ping', fn() => new JsonResponse(['status' => 'ok', 'framework' => 'ivi.php']));
$router->get('/api/boom', fn() => throw new \RuntimeException('API exploded'));

/* --------------------
 * Demos
 * -------------------- */

$router->get('/test', [HomeController::class, 'test']);
$router->get('/make', fn() => View::make('product/make', [
    'title'   => 'Welcome to ivi.php!',
    'message' => 'Your minimalist PHP framework.',
]));
$router->post('/contact', function (Request $req) {
    $data = $req->json();
    return View::make('contact/thanks', ['name' => $data['name'] ?? 'Anonymous']);
});
$router->get('/boom', fn() => throw new \RuntimeException('Boom from controller'));

/* --------------------
 * Users (CRUD)
 * -------------------- */

// index: GET /users
$router->get('/users', [UserController::class, 'index']);

// create form: GET /users/create
$router->get('/users/create', [UserController::class, 'create']);

// store: POST /users
$router->post('/users', [UserController::class, 'store']);

// show: GET /users/:id
$router->get('/users/:id', [UserController::class, 'show'])
    ->where('id', '\d+');

// edit form: GET /users/:id/edit
$router->get('/users/:id/edit', [UserController::class, 'edit'])
    ->where('id', '\d+');

// update: POST /users/:id   (tu pourras passer à PATCH/PUT plus tard)
$router->post('/users/:id', [UserController::class, 'update'])
    ->where('id', '\d+');

// delete: POST /users/:id/delete   (tu pourras passer à DELETE plus tard)
$router->post('/users/:id/delete', [UserController::class, 'destroy'])
    ->where('id', '\d+');

/* --------------------
 * Sanity / Debug
 * -------------------- */
$router->any('/__alive', function (Request $req) {
    return new JsonResponse([
        'seen_method' => $req->method(),
        'seen_path'   => $req->path(),
    ]);
});
