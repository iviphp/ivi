<?php

/** @var App\Models\User $user */ $u = $user->toArray(); ?>
<h1>Edit user #<?= (int)$u['id'] ?></h1>
<form action="/users/<?= (int)$u['id'] ?>" method="post">
    <div>
        <label>Name</label><br>
        <input type="text" name="name" value="<?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES) ?>" required>
    </div>
    <div>
        <label>Email</label><br>
        <input type="email" name="email" value="<?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES) ?>" required>
    </div>
    <div>
        <label><input type="checkbox" name="active" value="1" <?= !empty($u['active']) ? 'checked' : '' ?>> Active</label>
    </div>
    <button type="submit">Update</button>
    <a href="/users">Cancel</a>
</form>