<?php

/** @var App\Models\User $user */
$u = $user->toArray();
$id = (int)($u['id'] ?? 0);
?>

<h1>User #<?= $id ?></h1>

<div class="alert alert-danger d-none" role="alert" id="user-show-global-error"></div>

<div id="user-show-shell" data-user-id="<?= $id ?>">
    <div class="text-muted">Loading user…</div>
</div>

<p class="mt-3">
    <a class="btn btn-outline-primary btn-sm" data-spa href="/users/<?= $id ?>/edit">Edit</a>
    <a class="btn btn-outline-secondary btn-sm" data-spa href="/users">Back</a>
    <button
        class="btn btn-outline-danger btn-sm"
        data-action="user.delete"
        data-user-id="<?= $id ?>">
        Delete
    </button>
</p>