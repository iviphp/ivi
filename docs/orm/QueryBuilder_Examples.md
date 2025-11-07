# Ivi.php ORM — `QueryBuilder` Quick Examples

This guide demonstrates practical examples of the **QueryBuilder** class in Ivi.php.

---

## 1) Basic SELECT

```php
use Ivi\Core\ORM\QueryBuilder;

// Select all columns
$rows = QueryBuilder::table('users')->get();

// Select specific columns
$rows = QueryBuilder::table('users')
    ->select('id', 'name', 'email')
    ->orderBy('id DESC')
    ->limit(5)
    ->get();
```

---

## 2) WHERE Clauses

```php
$rows = QueryBuilder::table('users')
    ->where('email = ?', 'john@example.com')
    ->get();

// Multiple WHERE
$rows = QueryBuilder::table('users')
    ->where('active = ?', 1)
    ->where('age > ?', 18)
    ->orderBy('name ASC')
    ->get();
```

---

## 3) OR WHERE

```php
$rows = QueryBuilder::table('users')
    ->where('country = ?', 'UG')
    ->orWhere('country = ?', 'CD')
    ->get();
```

Produces SQL similar to:

```sql
SELECT * FROM users WHERE (country = 'UG') OR country = 'CD';
```

---

## 4) WHERE IN

```php
$rows = QueryBuilder::table('products')
    ->whereIn('category_id', [1, 3, 5])
    ->orderBy('id DESC')
    ->get();
```

---

## 5) WHERE LIKE (search)

```php
$rows = QueryBuilder::table('users')
    ->whereLike('name', '%john%')
    ->get();
```

Equivalent SQL:

```sql
SELECT * FROM users WHERE name LIKE '%john%';
```

---

## 6) INSERT Example

```php
$id = QueryBuilder::table('users')->insert([
    'name'  => 'Jane Doe',
    'email' => 'jane@example.com',
    'active' => 1,
]);

echo "Inserted user with ID: $id";
```

---

## 7) UPDATE Example

```php
$affected = QueryBuilder::table('users')
    ->where('id = ?', 10)
    ->update(['active' => 0, 'email' => 'new@mail.com']);

echo "Rows updated: $affected";
```

---

## 8) DELETE Example

```php
$deleted = QueryBuilder::table('users')
    ->where('id = ?', 10)
    ->delete();

echo "Rows deleted: $deleted";
```

---

## 9) COUNT Example

```php
$total = QueryBuilder::table('users')
    ->where('active = ?', 1)
    ->count();

echo "Active users: $total";
```

---

## 10) Pagination Example

```php
$page = 2;
$perPage = 10;

$rows = QueryBuilder::table('users')
    ->orderBy('id DESC')
    ->limit($perPage)
    ->offset(($page - 1) * $perPage)
    ->get();
```

---

## 11) RAW Query (Advanced Use)

```php
$rows = QueryBuilder::table('users')->raw(
    'SELECT name, COUNT(*) as c FROM users WHERE active = ? GROUP BY name HAVING c > ?',
    [1, 2]
);
```

---

## 12) Chaining for Clean Code

```php
$rows = QueryBuilder::table('orders')
    ->select('id', 'total', 'status')
    ->where('status = ?', 'pending')
    ->whereLike('customer_name', '%doe%')
    ->orderBy('id DESC')
    ->limit(10)
    ->get();
```

---

## 13) Tips & Best Practices

✅ Use `?` placeholders — QueryBuilder automatically binds values safely.  
✅ Always combine with validation for user input.  
✅ Chain methods for readability and consistent logic.  
✅ Use `count()` for fast aggregations instead of fetching all rows.  
✅ Use `raw()` only when absolutely necessary.

---

## 14) Example Debug Output (Conceptual)

Example of how a call is compiled internally:

```php
$query = QueryBuilder::table('users')
    ->where('active = ?', 1)
    ->whereLike('email', '%@example.com')
    ->orderBy('id DESC')
    ->limit(3);

[$sql, $params] = (new ReflectionMethod($query, 'toSelectSql'))
    ->invoke($query);

print_r($sql);
print_r($params);
```

Output:

```text
SELECT * FROM users WHERE active = :w1 AND email LIKE :w2 ORDER BY id DESC LIMIT 3
Array
(
    [:w1] => 1
    [:w2] => %@example.com
)
```

---

## 15) Integration with Model

```php
$rows = User::query()
    ->where('active = ?', 1)
    ->orderBy('id DESC')
    ->limit(10)
    ->get();

$users = array_map(fn($r) => new User($r), $rows);
```

---

### ✅ Summary

| Operation | Method              | Example                               |
| --------- | ------------------- | ------------------------------------- |
| SELECT    | `get()` / `first()` | `->where('id = ?', 1)->first()`       |
| INSERT    | `insert()`          | `->insert([...])`                     |
| UPDATE    | `update()`          | `->where('id = ?', 1)->update([...])` |
| DELETE    | `delete()`          | `->where('id = ?', 1)->delete()`      |
| COUNT     | `count()`           | `->where('active = ?', 1)->count()`   |
| RAW       | `raw()`             | `->raw('SELECT * FROM users')`        |

---

© Ivi.php ORM — Simplicity. Performance. Expressiveness.
