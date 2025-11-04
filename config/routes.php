<?php
// $router est dispo depuis App::registerRoutes()

use App\Controllers\Product\HomeController;
use Ivi\Core\View\View;
use Ivi\Http\JsonResponse;
use Ivi\Http\Request;

// Web
$router->get('/', [HomeController::class, 'home']);

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
