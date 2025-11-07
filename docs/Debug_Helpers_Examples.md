# Ivi.php — Debug Helpers Examples

This document explains how to use Ivi.php’s **global debugging helpers** for clear, structured inspection of data
across development and production environments.

---

## 1. Overview

Ivi.php provides global debugging functions inspired by Laravel’s `dump()` and `dd()` helpers,
but extended with themed HTML output, contextual payloads, and graceful fallbacks when no logger is available.

| Function                         | Description                                          |
| -------------------------------- | ---------------------------------------------------- |
| `dump($data, $options = [])`     | Pretty-prints variable contents                      |
| `dd($data, $options = [])`       | Dumps and terminates execution                       |
| `ivi_dump($data, $options = [])` | Internal version (always uses Ivi\Core\Debug\Logger) |
| `ivi_dd($data, $options = [])`   | Internal variant of `dd()`                           |

---

## 2. Basic Examples

```php
// Dump a simple variable
dump(['user' => 'Gaspard', 'role' => 'admin']);

// Dump with a title
dump($config, ['title' => 'App Config Snapshot']);
```

Output (in browser):

```
🟩 ivi.php Debug Console
────────────────────────────
App Config Snapshot:
{
    "app_name": "IviPHP",
    "env": "local",
    "debug": true
}
```

---

## 3. Dump & Die (dd)

```php
dd(['error' => 'Unexpected null value']);
```

This stops execution immediately after showing the dump (useful during debugging).

---

## 4. Internal Helpers (ivi_dump / ivi_dd)

Use these only when you want to **bypass custom App\Debug\Logger** and always use the
core Ivi logger:

```php
ivi_dump($_ENV, ['title' => 'Environment Variables']);
ivi_dd(['fatal' => 'Database connection lost']);
```

---

## 5. Customization Options

The `dump()` and `dd()` helpers accept an associative `$options` array for more control.

| Option      | Type   | Default    | Description                                  |
| ----------- | ------ | ---------- | -------------------------------------------- |
| `title`     | string | `'Dump'`   | Custom title shown above the output          |
| `theme`     | string | `'light'`  | Theme for the console (`light` / `dark`)     |
| `verbosity` | string | `'normal'` | Output verbosity level                       |
| `exit`      | bool   | `false`    | Whether to exit after dump (enabled by `dd`) |

Example:

```php
dump($user, [
    'title' => 'Current Authenticated User',
    'theme' => 'dark',
    'verbosity' => 'minimal'
]);
```

---

## 6. Fallback Behavior

If neither `App\Debug\Logger` nor `Ivi\Core\Debug\Logger` is available,
the helpers fall back to plain text output:

```
Dump:
Array
(
    [id] => 42
    [name] => Alice
    [email] => alice@example.com
)
```

---

## 7. Use Cases

- ✅ Inspecting models, queries, or request payloads.
- ✅ Debugging controllers or repositories.
- ✅ Quick variable introspection in CLI or HTTP.
- ✅ Logging dumps in a unified, styled format during development.

---

## 8. Example Integration in Controller

```php
use Ivi\Http\Request;

final class UserController
{
    public function show(Request $request, int $id)
    {
        $user = User::find($id);

        if (!$user) {
            dd(['error' => 'User not found', 'id' => $id]);
        }

        dump($user->toArray(), ['title' => 'User Details']);
        return view('user.show', ['user' => $user]);
    }
}
```

---

## 9. Advanced: Using a Custom App Logger

If your app defines a custom logger:

```php
namespace App\Debug;

final class Logger
{
    public static function dump(string $title, mixed $data, array $options = []): void
    {
        // Custom UI, file logging, or JSON encoding logic
        echo "<h3>{$title}</h3>";
        echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
    }
}
```

Then all `dump()` / `dd()` calls will automatically use it instead of the core Ivi logger.

---

## 10. Summary

| Helper       | Logger used | Stops Execution | Output Type |
| ------------ | ----------- | --------------- | ----------- |
| `dump()`     | App or Core | ❌              | HTML / CLI  |
| `dd()`       | App or Core | ✅              | HTML / CLI  |
| `ivi_dump()` | Core only   | ❌              | HTML / CLI  |
| `ivi_dd()`   | Core only   | ✅              | HTML / CLI  |

---

© Ivi.php Framework — Simple. Modern. Expressive.
