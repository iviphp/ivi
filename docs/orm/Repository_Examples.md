# Ivi.php ORM — `Repository` Pattern Examples

This document provides practical examples of how to use the **Repository** class
to abstract and organize data access logic in Ivi.php applications.

---

## 1) Basic Repository Definition

```php
use Ivi\Core\ORM\Repository;
use App\Models\User;

final class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }
}
```

---

## 2) Basic Usage

```php
$repo = new UserRepository();

// Retrieve all users
$users = $repo->all();

// Find a specific user
$user = $repo->find(5);

// Create a new user
$new = $repo->create([
    'name'  => 'Jane Doe',
    'email' => 'jane@example.com',
]);
```

---

## 3) Extending with Custom Queries

Repositories can include **domain-specific methods** using the underlying
`QueryBuilder` or model static methods.

```php
final class UserRepository extends Repository
{
    protected function modelClass(): string
    {
        return User::class;
    }

    /** @return User[] */
    public function getActiveUsers(): array
    {
        $cls = $this->modelClass();
        return $cls::query()->where('active = ?', 1)->orderBy('id DESC')->get();
    }

    /** @return User|null */
    public function findByEmail(string $email): ?User
    {
        $cls = $this->modelClass();
        $row = $cls::query()->where('email = ?', $email)->first();
        return $row ? new $cls($row) : null;
    }
}
```

Usage:

```php
$repo = new UserRepository();

$active = $repo->getActiveUsers();
$jane = $repo->findByEmail('jane@example.com');
```

---

## 4) Example Controller Integration

```php
use App\Repositories\UserRepository;
use Ivi\Core\Http\Response;

final class UserController
{
    public function __construct(private UserRepository $repo) {}

    public function index(): Response
    {
        $users = $this->repo->all();
        return Response::json(['users' => $users]);
    }

    public function store(Request $req): Response
    {
        $data = $req->only(['name', 'email']);
        $user = $this->repo->create($data);
        return Response::json(['created' => $user]);
    }
}
```

---

## 5) Example Repository for Products

```php
use Ivi\Core\ORM\Repository;
use App\Models\Product;

final class ProductRepository extends Repository
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    /** @return Product[] */
    public function topRated(int $minRating = 4): array
    {
        $cls = $this->modelClass();
        return $cls::query()
            ->where('rating >= ?', $minRating)
            ->orderBy('rating DESC')
            ->limit(10)
            ->get();
    }
}
```

---

## 6) Example Advanced Filtering

```php
$repo = new ProductRepository();

$best = $repo->topRated(4);
$cheap = Product::query()
    ->where('price < ?', 100)
    ->orderBy('price ASC')
    ->get();
```

---

## 7) Benefits of the Repository Pattern

| Benefit                   | Description                                          |
| ------------------------- | ---------------------------------------------------- |
| ✅ Separation of Concerns | Keeps database logic out of controllers              |
| ✅ Reusability            | Same repository can be injected in multiple services |
| ✅ Testability            | Easy to mock repository during unit testing          |
| ✅ Maintainability        | Consistent data access methods across models         |
| ✅ Extensibility          | Add business-specific methods (e.g. `getTopUsers()`) |

---

## 8) Best Practices

- Always use **repositories** in controllers/services, not direct model queries.
- Define **custom domain methods** inside repositories for clarity.
- Keep your **repositories small** and focused per aggregate (User, Order, etc).
- Combine with **FormRequest validation** before repository calls.

---

## 9) Example for Dependency Injection

```php
final class UserService
{
    public function __construct(private UserRepository $repo) {}

    public function activateUser(int $id): void
    {
        $user = $this->repo->find($id);
        if (!$user) {
            throw new \RuntimeException("User not found.");
        }

        $user->active = 1;
        $user->save();
    }
}
```

---

## 10) Summary

| Operation       | Method                               | Description                        |
| --------------- | ------------------------------------ | ---------------------------------- |
| `all()`         | Get all records                      | Returns an array of models         |
| `find($id)`     | Find by ID                           | Returns a single model or null     |
| `create($data)` | Create record                        | Instantiates and saves a new model |
| Custom Methods  | e.g. `findByEmail`, `getActiveUsers` | Domain-specific logic              |

---

© Ivi.php ORM — Elegant, expressive, and structured data access.
