<?php
// on récupère l'id depuis $user si tu continues à le passer,
// sinon tu peux passer $id directement.
$u = $user->toArray();
$id = (int)($u['id'] ?? 0);
?>

<h1>Edit user #<?= $id ?></h1>

<div class="alert alert-danger d-none" role="alert" id="user-edit-global-error"></div>

<!-- Loader / container -->
<div id="user-edit-shell" data-user-id="<?= $id ?>">
    <div class="text-muted">Loading user…</div>
</div>