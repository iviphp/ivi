<?php

/** @var App\Models\User $user */
/** @var \Ivi\Validation\ErrorBag|null $errors */
/** @var array<string,mixed>|null $old */

$u = $user->toArray();
$old = $old ?? [];
$errors = $errors ?? null;

// Préférence aux valeurs old (si validation échouée), sinon valeurs actuelles
$val = function (string $field, $fallback = '') use ($old, $u) {
    if (array_key_exists($field, $old)) return $old[$field];
    return $u[$field] ?? $fallback;
};
$firstError = function (string $field) use ($errors): ?string {
    return $errors ? $errors->first($field) : null;
};

// Checkbox 'active'
$activeChecked = array_key_exists('active', $old)
    ? true
    : (!empty($u['active']));
?>
<h1>Edit user #<?= (int)($u['id'] ?? 0) ?></h1>

<?php if ($errors && !$errors->isEmpty()): ?>
    <div style="background:#fee;border:1px solid #f99;padding:10px;margin:10px 0;">
        <strong>There were some problems with your input:</strong>
        <ul style="margin:8px 0 0 16px;">
            <?php foreach ($errors->all() as $field => $messages): ?>
                <?php foreach ($messages as $m): ?>
                    <li><?= htmlspecialchars("{$field}: {$m}", ENT_QUOTES) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="/users/<?= (int)($u['id'] ?? 0) ?>" method="post" novalidate>
    <div style="margin-bottom:10px;">
        <label>Name</label><br>
        <input
            type="text" name="name"
            value="<?= htmlspecialchars((string)$val('name', ''), ENT_QUOTES) ?>"
            required>
        <?php if ($e = $firstError('name')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:10px;">
        <label>Email</label><br>
        <input
            type="email" name="email"
            value="<?= htmlspecialchars((string)$val('email', ''), ENT_QUOTES) ?>"
            required>
        <?php if ($e = $firstError('email')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:10px;">
        <label>New password (optional)</label><br>
        <input type="password" name="password" placeholder="Leave blank to keep current">
        <?php if ($e = $firstError('password')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:14px;">
        <label>
            <input type="checkbox" name="active" value="1" <?= $activeChecked ? 'checked' : '' ?>> Active
        </label>
        <?php if ($e = $firstError('active')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit">Update</button>
    <a href="/users">Cancel</a>
</form>