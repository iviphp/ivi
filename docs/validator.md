# Ivi.php Validation Examples

This document provides practical examples of how to use the **Ivi.php Validation System**,
including direct `Validator` usage, the `ValidatesRequests` trait, and `FormRequest` integration.

---

## 🧩 Basic Example — Using the Validator Directly

```php
use Ivi\Core\Validation\Validator;
use Ivi\Core\Validation\ValidationException;

$data = [
    'name' => 'Gaspard kirira',
    'email' => 'gaspardkirira@outlook.com',
    'age' => 19,
];

$rules = [
    'name'  => 'required|string|min:3|max:50',
    'email' => 'required|email',
    'age'   => 'required|integer|min:18|max:99',
];

try {
    $validated = (new Validator($data, $rules))->validate();
    var_dump($validated);
} catch (ValidationException $e) {
    print_r($e->errors()->all());
}
```

---

## 🧠 Example — Using the `ValidatesRequests` Trait

```php
use Ivi\Http\Request;
use Ivi\Core\Validation\ValidatesRequests;
use Ivi\Core\Validation\ValidationException;

final class UserController extends Controller
{
    use ValidatesRequests;

    public function store(Request $request)
    {
        try {
            $validated = $this->validate($request, [
                'username' => 'required|string|min:3|max:30',
                'email'    => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            // Handle user creation...
        } catch (ValidationException $e) {
            return $this->view('user.create', [
                'errors' => $e->errors(),
                'old'    => $request->post(),
            ], $request, 422);
        }
    }
}
```

---

## 📦 Example — Using a Custom FormRequest

```php
use Ivi\Core\Validation\FormRequest;

final class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|min:3|max:50',
            'email'    => 'required|email|max:120',
            'password' => 'required|string|min:6',
        ];
    }
}

// In a controller:
public function store(CreateUserRequest $request)
{
    $validated = $request->validated();
    User::create($validated);
    return Response::redirect('/users');
}
```

---

## ⚙️ Example — Custom Rule

```php
use Ivi\Core\Validation\Contracts\Rule;

final class AlphaDash implements Rule
{
    public function passes(mixed $value, array $data, string $field): bool
    {
        return $value === null || preg_match('/^[A-Za-z0-9_-]+$/', (string)$value);
    }

    public function message(string $field): string
    {
        return 'The :attribute may only contain letters, numbers, dashes, and underscores.';
    }
}

// Usage
$validator = new Validator(['username' => 'John_Doe'], [
    'username' => [new AlphaDash(), 'required'],
]);

$validated = $validator->validate();
```

---

## 🧪 Example — Displaying Errors in a View

```php
<?php if ($errors && !$errors->isEmpty()): ?>
<div style="background:#fee;border:1px solid #f99;padding:10px;">
    <strong>There were some validation errors:</strong>
    <ul>
        <?php foreach ($errors->all() as $field => $messages): ?>
            <?php foreach ($messages as $msg): ?>
                <li><?= htmlspecialchars("{$field}: {$msg}") ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
```

---

### ✅ Summary

- Use `Validator` for manual validation.
- Use `ValidatesRequests` in controllers for convenience.
- Use `FormRequest` for reusable and self-contained validation logic.
- Extend `Rule` to create custom validation constraints.
