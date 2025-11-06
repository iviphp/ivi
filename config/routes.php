<?php
// $router est dispo depuis App::registerRoutes()

use App\Controllers\Doc\DocsController;
use App\Controllers\Product\HomeController;
use App\Controllers\User\UserController;
use Ivi\Core\View\View;
use Ivi\Http\JsonResponse;
use Ivi\Http\Request;

// Web
$router->get('/', [HomeController::class, 'home']);
$router->get('/docs', [DocsController::class, 'doc']);
$router->get('/users', [UserController::class, 'index']);

$router->get('/ping', fn() => new \Ivi\Http\Response('pong'));

// API
$router->get('/api/ping', fn() => new JsonResponse([
    'status' => 'ok',
    'framework' => 'ivi.php',
]));

$router->get('/test', [HomeController::class, "test"]);

$router->get('/make', function () {
    // Renders /views/product/home.php
    return View::make('product/make', [
        'title' => 'Welcome to ivi.php!',
        'message' => 'Your minimalist PHP framework.'
    ]);
});

// Example route receiving POST data
$router->post('/contact', function (Request $req) {
    $data = $req->json();
    return View::make('contact/thanks', [
        'name' => $data['name'] ?? 'Anonymous'
    ]);
});

$router->get('/boom', function () {
    throw new \RuntimeException("Boom from controller");
});

$router->get('/api/boom', function () {
    throw new \RuntimeException('API exploded');
});

$router->get('/api/users', function () {
    $repo = new \Ivi\Core\ORM\Repository(\App\Models\User::class);
    return $repo->all();
});
