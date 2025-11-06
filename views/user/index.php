<?php

/** @var \Ivi\Core\ORM\Pagination $page */ ?>
<h1>Users</h1>
<p>
    <a href="/users/create">+ New user</a>
</p>
<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Active</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($page->items as $u): $arr = $u->toArray(); ?>
            <tr>
                <td><?= (int)($arr['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string)($arr['name'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string)($arr['email'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= !empty($arr['active']) ? 'yes' : 'no' ?></td>
                <td>
                    <a href="/users/<?= (int)$arr['id'] ?>">show</a>
                    <a href="/users/<?= (int)$arr['id'] ?>/edit">edit</a>
                    <form action="/users/<?= (int)$arr['id'] ?>/delete" method="post" style="display:inline" onsubmit="return confirm('Delete user?');">
                        <button type="submit">delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p>
    Total: <?= $page->total ?> — Page <?= $page->currentPage ?> / <?= $page->lastPage ?>
</p>
<nav>
    <?php if ($page->hasPrev()): ?>
        <a href="?page=1&per_page=<?= $page->perPage ?>">« First</a>
        <a href="?page=<?= $page->prevPage() ?>&per_page=<?= $page->perPage ?>">‹ Prev</a>
    <?php endif; ?>
    <?php if ($page->hasNext()): ?>
        <a href="?page=<?= $page->nextPage() ?>&per_page=<?= $page->perPage ?>">Next ›</a>
        <a href="?page=<?= $page->lastPage ?>&per_page=<?= $page->perPage ?>">Last »</a>
    <?php endif; ?>
</nav>