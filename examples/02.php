<?php
require __DIR__ . '/vendor/autoload.php';

use Ivi\Core\Bootstrap\App;
use Ivi\Core\View\View;
use Ivi\Http\Request;

// Create app instance
$app = new App(__DIR__);

// Example route rendering a view
$app->router->get('/', function () {
    // Renders /views/product/home.php
    return View::make('product/home', [
        'title' => 'Welcome to ivi.php!',
        'message' => 'Your minimalist PHP framework.'
    ]);
});

// Example route receiving POST data
$app->router->post('/contact', function (Request $req) {
    $data = $req->json();
    return View::make('contact/thanks', [
        'name' => $data['name'] ?? 'Anonymous'
    ]);
});

$app->run();
