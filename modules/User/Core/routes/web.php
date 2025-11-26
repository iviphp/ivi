<?php

use Modules\User\Core\Http\Controllers\HomeController;
use Ivi\Http\JsonResponse;
use Modules\User\Core\Http\Controllers\UserApiController;

/** @var \Ivi\Core\Router\Router $router */
$router->get('/user/home', [HomeController::class, 'index']);
$router->get('/api/user/me', [UserApiController::class, 'me']);
$router->get('/user/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'User/Core'
]));
