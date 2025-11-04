# 🟧 ivi.php

> **Simple. Modern. Expressive.**  
> A new generation PHP framework — designed for clarity, speed, and elegance.

---

## 🚀 Introduction

**ivi.php** is a lightweight, expressive, and modern PHP framework built for developers who love **clarity over complexity**.  
It focuses on **simplicity**, **speed**, and **developer experience**, allowing you to build modern web applications without the weight of traditional frameworks.

Whether you’re creating a small API or a large modular system, ivi.php gives you the **clean structure** and **freedom** to scale your ideas naturally.

---

## ✨ Philosophy

ivi.php is guided by a few simple principles:

- 🧩 **Minimal Core** — Keep the foundation small, fast, and easy to understand.
- ⚙️ **Expressive Syntax** — Beautiful APIs that make code self-explanatory.
- 🚀 **Performance-Oriented** — Every layer is optimized for speed.
- 💡 **Developer Joy** — Designed to make PHP development feel refreshing again.

---

## 🧱 Project Structure (v0.1.0)

```
ivi/
├─ src/               → Core framework (App, Router, Request, Response)
├─ routes/            → Route definitions
├─ public/            → Entry point (index.php)
└─ composer.json      → Autoload configuration
```

- `App` — The main kernel and middleware pipeline
- `Router` — Lightweight, parameterized routing system
- `Request` — Clean HTTP abstraction
- `Response` — JSON / text output with fluent API
- `Logger` — Minimal debug view for development

---

## 🧠 Example

```php
use Ivi\Core\App;
use Ivi\Core\Request;

$app = new App();

$app->get('/', fn() => ['hello' => 'ivi.php']);

$app->get('/user/{name}', function (array $params) {
    return ['hello' => $params['name']];
});

$app->post('/echo', fn(Request $req) => ['you_sent' => $req->json()]);

$app->run();
```

---

## 🧩 Philosophy in One Line

> “Small enough to understand in one sitting.  
> Powerful enough to build anything.”

---

## 📦 Installation (coming soon)

```bash
composer create-project iviphp/ivi myapp
```

or (in the future)

```bash
composer global require iviphp/cli
ivi new myapp
```

---

## 🧭 Roadmap

| Version    | Goal                                          | Status         |
| ---------- | --------------------------------------------- | -------------- |
| **v0.1.0** | Core (App, Router, Request, Response, Logger) | 🟢 in progress |
| **v0.2.0** | Middleware, Error Handling, Config            | 🕓 planned     |
| **v0.3.0** | CLI, ENV Loader, DI Container                 | 🔜 next        |
| **v0.4.0** | ORM & Validation Layer                        | 🔜             |
| **v1.0.0** | Stable release                                | 🔜             |

---

## 🧡 Credits

Created and maintained by [**Gaspard Kirira**](https://github.com/GaspardKirira).  
Part of the **Softadastra ecosystem** — open-source technologies for the next generation of developers.

---

## 📄 License

Released under the **MIT License**.

---

> _“ivi.php — because simplicity is the ultimate sophistication.”_
