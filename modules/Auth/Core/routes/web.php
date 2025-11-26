<?php

use Ivi\Http\JsonResponse;
use Modules\Auth\Core\Http\Controllers\AuthController;

$router->get('/auth', [AuthController::class, 'home']);

// Login
$router->get('/auth/login', [AuthController::class, 'showLoginForm']);
$router->post('/auth/login', [AuthController::class, 'handleLogin']);

// Registration
$router->get('/auth/register', [AuthController::class, 'showRegistrationForm']);
$router->post('/auth/register', [AuthController::class, 'handleRegistration']);

$router->get('/auth/sync', [AuthController::class, 'showSyncPage']);

// Finalize registration (e.g., email verification / extra info)
$router->get('/auth/finalize-registration', [AuthController::class, 'showFinalizeRegistrationForm']);
$router->post('/auth/finalize-registration', [AuthController::class, 'handleFinalizeRegistration']);

// Route logout
$router->post('/auth/logout', [AuthController::class, 'logout']);

// API routes
$router->get('/auth/api/me', [AuthController::class, 'currentUser']);
$router->get('/auth/google-login-url', [AuthController::class, 'generateGoogleLoginUrl']);

// Google OAuth callback
$router->get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);



$router->get('/auth/ping', fn() => new JsonResponse([
    'ok' => true,
    'module' => 'auth/Core'
]));
