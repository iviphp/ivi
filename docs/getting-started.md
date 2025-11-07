# Getting Started

Welcome to **ivi.php** — a simple, modern, expressive PHP framework focused on clarity, speed, and a great developer experience.

This guide helps you boot a fresh project, understand the folder layout, create a first route/controller/view, and connect to a database.

---

## Requirements

- PHP **8.2+**
- PDO + driver (e.g. `pdo_mysql` or `pdo_sqlite`)
- Composer
- Recommended: `php -S localhost:8000 -t public` for local dev

---

## 1) Installation

### A. Create a project

```bash
composer create-project iviphp/ivi my-app
cd my-app
```

> If you cloned the repo directly, run `composer install`.

### B. Project structure (overview)

```bash
.
├─ bootstrap/          # app boot strap & helpers
├─ config/             # app, routes, database config
├─ core/               # ivi.php framework core (Bootstrap, Http, ORM, ...)
├─ public/             # web root (index.php)
├─ src/                # your application code (Controllers, Models, ...)
├─ views/              # PHP templates
├─ scripts/            # migrations, seeds, dev scripts
├─ docs/               # documentation
└─ vendor/
```

---

## 2) First Run

Serve the app:

```bash
php -S localhost:8000 -t public
```

Open: <http://localhost:8000>

You should see the default page or a basic route response (see next section).

---

## 3) Routing

Routes are declared in `config/routes.php`.

```php
<?php

use Ivi\Router\Router;
use App\Controllers\HomeController;
use App\Controllers\User\UserController;

/** @var Router $router */

$router->get('/', function () {
    return 'Hello ivi.php!';
});

$router->get('/users', [UserController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
```

---

## 4) Controllers

```php
<?php

namespace App\Controllers;

use Ivi\Http\Request;
use Ivi\Http\HtmlResponse;

final class HomeController extends Controller
{
    public function index(Request $request): HtmlResponse
    {
        return $this->view('home', [
            'title' => 'Welcome to ivi.php',
            'message' => 'Fast & expressive.',
        ], $request);
    }
}
```

---

## 5) Views

```php
<!-- views/home.php -->
<?php $this->layout('base', ['title' => $title ?? 'ivi.php']); ?>

<section class="section container">
  <h1><?= htmlspecialchars($title ?? 'Welcome') ?></h1>
  <p><?= htmlspecialchars($message ?? '') ?></p>
</section>
```

Layout example:

```php
<!-- views/base.php -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'ivi.php') ?></title>
  <link href="<?= asset('assets/css/app.css') ?>" rel="stylesheet">
  <?= $styles ?? '' ?>
</head>
<body>
  <nav class="nav"><a href="/">ivi.php</a></nav>
  <main><?= $this->section('content') ?></main>
  <?= $scripts ?? '' ?>
</body>
</html>
```

---

## 6) Markdown Docs

```php
$router->get('/docs', [\App\Controllers\Docs\DocsController::class, 'index']);
```

View: `views/docs/page.php`

```php
<section class="docs-hero">
  <div class="container">
    <h1>Documentation</h1>
    <p class="lead">Build fast and expressive apps with <strong>ivi.php</strong>.</p>
  </div>
</section>

<main class="docs-content container markdown-body">
  <?= $content ?>
</main>
```

---

## 7) Environment & Config

```ini
APP_ENV=local
APP_DEBUG=true
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=iviphp
DB_USER=root
DB_PASS=secret
```

```php
return [
  'default' => $_ENV['DB_DRIVER'] ?? 'mysql',
  'connections' => [
    'mysql' => [
      'driver' => 'mysql',
      'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
      'database' => $_ENV['DB_NAME'] ?? 'iviphp',
      'username' => $_ENV['DB_USER'] ?? 'root',
      'password' => $_ENV['DB_PASS'] ?? '',
    ],
  ],
];
```

---

## 8) ORM Quickstart

```php
<?php

namespace App\Models;

use Ivi\Core\ORM\Model;

final class User extends Model
{
    protected string $table = 'users';
}
```

Usage:

```php
use App\Models\User;

$user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
$found = User::find(1);
$found->update(['name' => 'Ada Lovelace']);
$found->delete();
```

---

## 9) Migrations CLI

```bash
php bin/ivi migrate
php bin/ivi migrate:status
php bin/ivi migrate:reset
```

Example SQL:

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120),
  email VARCHAR(190) UNIQUE
);
```

---

## 10) Validation

```php
use Ivi\Http\Request;
use Ivi\Validation\Validator;

$validator = Validator::make($request->all(), [
  'name' => 'required|min:2|max:120',
  'email' => 'required|email',
]);

if ($validator->fails()) {
  return response()->json(['errors' => $validator->errors()], 422);
}
```

---

## 11) Responses

```php
use Ivi\Http\JsonResponse;
use Ivi\Http\HtmlResponse;

return new JsonResponse(['ok' => true]);
return new HtmlResponse('<h1>Hello</h1>');
```

---

## 12) Production Tips

- Set `APP_ENV=production`
- Use `APP_DEBUG=false`
- Configure opcache
- Serve from `public/`
- Minify assets

---

Happy building with **ivi.php** 🚀
