<?php

/** @var \Ivi\Validation\ErrorBag|null $errors */
/** @var array<string,mixed>|null $old */
$old = $old ?? [];
$errors = $errors ?? null;

$firstError = function (string $field) use ($errors): ?string {
    return $errors ? $errors->first($field) : null;
};
?>
<h1>New user</h1>

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

<form action="/users" method="post" novalidate>
    <div style="margin-bottom:10px;">
        <label>Name</label><br>
        <input
            type="text" name="name"
            value="<?= htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES) ?>"
            required>
        <?php if ($e = $firstError('name')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:10px;">
        <label>Email</label><br>
        <input
            type="email" name="email"
            value="<?= htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES) ?>"
            required>
        <?php if ($e = $firstError('email')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:10px;">
        <label>Password</label><br>
        <input type="password" name="password" required>
        <?php if ($e = $firstError('password')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:14px;">
        <label>
            <input type="checkbox" name="active" value="1"
                <?= array_key_exists('active', $old ?? []) ? 'checked' : '' ?>> Active
        </label>
        <?php if ($e = $firstError('active')): ?>
            <div style="color:#c00;font-size:0.9em;"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit">Create</button>
    <a href="/users">Cancel</a>
</form>