# 🟩 ivi.php

> **Simple. Modern. Expressive.**  
> A new-generation PHP framework built for clarity, speed, and developer joy.

---

## 🚀 Introduction

**ivi.php** is a lightweight and expressive PHP framework designed for developers who value **clarity over complexity**.  
It focuses on **simplicity**, **performance**, and an enjoyable **developer experience**, allowing you to build modern APIs and web applications effortlessly.

Whether you’re crafting a small REST API or a modular enterprise system, ivi.php provides the **clean structure**, **predictable design**, and **scalability** to evolve naturally with your project.

---

## ✨ Philosophy

ivi.php is guided by a few key principles:

- 🧩 **Minimal Core** — A small, fast foundation that’s easy to understand.
- ⚙️ **Expressive Syntax** — Code that reads like English and feels natural.
- 🚀 **Performance First** — Every line is designed with speed in mind.
- 💡 **Developer Joy** — PHP development should feel simple, fun, and productive again.

---

## 🧱 Project Structure

```
ivi/
├─ core/               → Internal engine (Bootstrap, View, Debug)
├─ src/                → Application controllers & logic
├─ views/              → HTML/PHP templates
├─ public/             → Entry point (index.php)
└─ composer.json       → Autoload & dependencies
```

**Core Components:**

| Component  | Description                             |
| ---------- | --------------------------------------- |
| `App`      | The main kernel & bootstrap system      |
| `Router`   | Lightweight, parameterized routing      |
| `Request`  | Clean HTTP abstraction                  |
| `Response` | JSON / text response builder            |
| `Logger`   | Elegant debug console for development   |
| `View`     | Simple view renderer for HTML templates |

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

---

## 🎨 With View Rendering

```php
require __DIR__ . '/vendor/autoload.php';

use Ivi\Core\Bootstrap\App;
use Ivi\Core\View\View;
use Ivi\Http\Request;

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
```

---

## 🧩 Notes

- `View::make('folder/file', [...])` looks for the view in your `views/` directory.  
  Example → `views/product/home.php`
- You can freely mix **JSON APIs** and **HTML views** — ivi.php automatically detects the response type.
- Recommended folder layout:

```
ivi/
├── core/
├── src/
├── views/
│   └── product/
│       └── home.php
└── public/index.php
```

---

## 🧭 Roadmap

| Version    | Goal                                          | Status         |
| ---------- | --------------------------------------------- | -------------- |
| **v0.1.0** | Core (App, Router, Request, Response, Logger) | ✅ Released    |
| **v0.2.0** | Middleware, Error Handling, Config            | 🕓 In progress |
| **v0.3.0** | CLI, ENV Loader, DI Container                 | 🔜 Planned     |
| **v0.4.0** | ORM & Validation Layer                        | 🔜 Planned     |
| **v1.0.0** | Stable Release                                | 🚀 Upcoming    |

---

## 📦 Installation (Coming Soon)

```bash
composer create-project iviphp/ivi myapp
```

or in the future:

```bash
composer global require iviphp/cli
ivi new myapp
```

---

## 🧡 Credits

Created and maintained by [**Gaspard Kirira**](https://github.com/GaspardKirira).  
Part of the **Softadastra ecosystem** — open-source technologies for the next generation of developers.

---

## 📄 License

Released under the **MIT License**.

---

> _“ivi.php — because simplicity is the ultimate sophistication.”_
