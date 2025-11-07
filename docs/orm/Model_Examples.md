# Ivi.php ORM — `Model` Quick Examples

This guide shows practical, copy‑pasteable examples for the **ActiveRecord‑style `Model`** in Ivi.php.

> Prereqs: your DB connection is configured; `Connection::instance()` works; and `QueryBuilder` is available.

---

## 1) Define a Model

```php
<?php

use Ivi\Core\ORM\Model;

final class User extends Model
{
    // Optional: override table and pk if needed
    // protected static ?string $table = 'users';
    // protected static string $primaryKey = 'id';

    // Control mass assignment
    protected static array $fillable = ['name', 'email', 'password', 'active'];
}
```

---

## 2) Create (INSERT)

```php
$user = User::create([
    'name'     => 'Jane Doe',
    'email'    => 'jane@example.com',
    'password' => password_hash('secret', PASSWORD_BCRYPT),
    'active'   => 1,
]);

// Created id
$id = $user->toArray()['id'] ?? null;
```

**Alternative:** build then `save()`

```php
$u = new User([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
]);
$u->password = password_hash('secret', PASSWORD_BCRYPT);
$u->active = 1;
$u->save();
```

---

## 3) Read (SELECT)

```php
// All users (be careful in production)
$all = User::all(); // returns User[]

// Find by PK
$maybe = User::find(10); // null if not found

// Fail if missing
$u = User::findOrFail(10); // throws ModelNotFoundException on missing
```

---

## 4) Update (UPDATE)

```php
$u = User::findOrFail(10);
$u->name = 'Updated Name';
$u->save(); // auto-detects UPDATE because PK is set
```

**Mass assign with `fill()`** (respects `$fillable`):

```php
$u = User::findOrFail(10);
$u->fill(['name' => 'Alice', 'email' => 'alice@example.com'])->save();
```

---

## 5) Delete (DELETE)

```php
$u = User::find(15);
if ($u) {
    $ok = $u->delete(); // bool
}
```

---

## 6) Refresh (re‑read from DB)

```php
$u = User::findOrFail(10);
// ... another process updates row #10 ...
$u->refresh(); // reloads attributes (throws if row is gone)
```

---

## 7) QueryBuilder bridge (`Model::query()`)

```php
$rows = User::query()
    ->select('*')              // optional, default is *
    ->where('active = ?', 1)
    ->orderBy('id DESC')
    ->limit(10)
    ->offset(0)
    ->get(); // array<int, array<string, mixed>>

// Hydrate into models (helper)
$rows = User::query()->where('active = ?', 1)->get();
$users = array_map(fn($r) => new User($r), $rows);
```

**Common patterns**

```php
// Count total
$total = (int)(User::query()->select('COUNT(*) AS c')->first()['c'] ?? 0);

// Exist check
$exists = (bool)(User::query()->select('1')->where('email = ?', 'jane@example.com')->first());
```

---

## 8) Custom table / primary key

```php
final class LegacyUser extends Model
{
    protected static ?string $table = 'legacy_users';
    protected static string $primaryKey = 'user_id';
    protected static array $fillable = ['user_id', 'full_name']; // etc.
}
```

---

## 9) Tips & Gotchas

- If `$fillable` is **empty**, mass assignment is **permissive** (accepts all keys).
- `save()` **no‑ops** if the payload resolves to an empty array (nothing to save).
- `findOrFail()` throws `ModelNotFoundException` — useful in controllers.
- `toArray()` returns raw attributes; format/transform in a presenter if needed.
- Use validation (e.g. Ivi Validation) **before** persisting for clean data.
- For bulk operations, prefer `QueryBuilder` methods for performance.
