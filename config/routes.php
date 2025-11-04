<?php
// $router est dispo depuis App::registerRoutes()

use App\Controllers\Product\HomeController;
use Ivi\Http\JsonResponse;

// Web
$router->get('/', [HomeController::class, 'home']);

$router->get('/ping', fn() => new \Ivi\Http\Response('pong'));

// API
$router->get('/api/ping', fn() => new JsonResponse([
    'status' => 'ok',
    'framework' => 'ivi.php',
]));

$router->get('/test', [HomeController::class, "test"]);
