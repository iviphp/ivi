<?php

/** @var App\Models\User $user */ $u = $user->toArray(); ?>
<h1>User #<?= (int)$u['id'] ?></h1>
<ul>
    <li><strong>Name:</strong> <?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES) ?></li>
    <li><strong>Email:</strong> <?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES) ?></li>
    <li><strong>Active:</strong> <?= !empty($u['active']) ? 'yes' : 'no' ?></li>
</ul>
<p>
    <a href="/users/<?= (int)$u['id'] ?>/edit">Edit</a> ·
    <a href="/users">Back</a>
</p>