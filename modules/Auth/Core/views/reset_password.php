<?php

$token = $params['token'] ?? null;
?>
<div class="containere">
    <h2>Password Reset</h2>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="error-message">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <form action="/auth/reset-password-post" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="form-group">
            <label for="new_password">New Password :</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>
        <button type="submit" class="boutton-reset">Reset Password</button>
    </form>

</div>