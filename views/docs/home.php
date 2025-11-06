<?php

dump($title);
?>

<h1><?= $title ? $title : 'Doc'; ?></h1>

# 🟩 ivi.php

> **Simple. Modern. Expressive.**
> A new-generation PHP framework built for clarity, speed, and developer joy.

---

## ⚡ Quick Example

```php
require __DIR__ . '/vendor/autoload.php';

use Ivi\Core\Bootstrap\App;
use Ivi\Http\Request;

// Initialize the application (sets BASE_PATH, loads .env, etc.)
$app = new App(__DIR__);

// Register routes
$app->router->get('/', fn() => ['hello' => 'ivi.php']);

$app->router->get('/user/{name}', function (array $params) {
return ['hello' => $params['name']];
});

$app->router->post('/echo', fn(Request $req) => [
'you_sent' => $req->json()
]);

// Run the application
$app->run();
```