# Ivi.php ORM — `Pagination` Quick Examples

This guide shows how to build paginated listings with the `Pagination` DTO in Ivi.php.

---

## 1) Basic Pagination (Controller)

```php
use Ivi\Core\ORM\Pagination;
use App\Models\User; // your concrete model

$q       = $request->query();
$page    = max(1, (int)($q['page'] ?? 1));
$perPage = max(1, (int)($q['per_page'] ?? 10));
$offset  = ($page - 1) * $perPage;

// Total
$row   = User::query()->select('COUNT(*) AS c')->first();
$total = (int)($row['c'] ?? 0);

// Items for current page
$rows = User::query()
    ->orderBy('id DESC')
    ->limit($perPage)
    ->offset($offset)
    ->get();

// Hydrate to models if you want
$items = array_map(fn($r) => new User($r), $rows);

$pageDto = new Pagination($items, $total, $perPage, $page);
return $this->view('user.index', ['page' => $pageDto]);
```

---

## 2) Rendering Links in HTML

```php
<?php /** @var Ivi\Core\ORM\Pagination $page */ ?>
<nav class="pager">
  <?php if ($page->hasPrev()): ?>
    <a href="?page=1&per_page=<?= $page->perPage ?>">« First</a>
    <a href="?page=<?= $page->prevPage() ?>&per_page=<?= $page->perPage ?>">‹ Prev</a>
  <?php endif; ?>

  <span>Page <?= $page->currentPage ?> / <?= $page->lastPage ?></span>

  <?php if ($page->hasNext()): ?>
    <a href="?page=<?= $page->nextPage() ?>&per_page=<?= $page->perPage ?>">Next ›</a>
    <a href="?page=<?= $page->lastPage ?>&per_page=<?= $page->perPage ?>">Last »</a>
  <?php endif; ?>
</nav>
```

---

## 3) JSON API Example

```php
$pageDto = new Pagination($items, $total, $perPage, $page);
return $this->json($pageDto->toArray(), 200);
```

### Example Output

```json
{
  "items": [
    { "id": 1, "name": "Jane" },
    { "id": 2, "name": "John" }
  ],
  "total": 42,
  "per_page": 10,
  "current_page": 3,
  "last_page": 5,
  "has_next": true,
  "has_prev": true
}
```

---

## 4) SEO & UX Tips

- Keep `per_page` stable across pages to avoid duplicate content.
- Add `rel="prev"` / `rel="next"` link tags where applicable.
- Show total pages and current bounds (e.g., “21–30 of 142”).

```php
$start = ($pageDto->currentPage - 1) * $pageDto->perPage + 1;
$end   = min($pageDto->total, $pageDto->currentPage * $pageDto->perPage);
// e.g., "Showing 21–30 of 142"
```

---

## 5) Integrating with Search & Filters

When adding filters (e.g., `status`, `q`), always propagate them to links:

```php
$status = urlencode($q['status'] ?? '');
$term   = urlencode($q['q'] ?? '');

// Links should include current filters:
<a href="?page=<?= $page->nextPage() ?>&per_page=<?= $page->perPage ?>&status=<?= $status ?>&q=<?= $term ?>">Next</a>
```

---

## 6) Edge Cases & Defensive Defaults

- If `currentPage > lastPage`, `Pagination` clamps it to `lastPage`.
- `total` is clamped to `>= 0`; `perPage` is clamped to `>= 1`.
- `hasNext()` / `hasPrev()` reflect computed bounds accurately.
