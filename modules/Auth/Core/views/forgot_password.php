<div class="containere">
    <h2>Forgot Password</h2>
    <p>Please enter the email address associated with your account to receive a password reset link.</p>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="message" style="color: #4CAF50;">
            <i class="fas fa-info-circle"></i>
            <?= $_SESSION['message']; ?>
            <?php unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <form action="/auth/forgot-password-post" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="form-group">
            <label for="email">Email address :</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="button-email-forgot">Send reset link</button>
    </form>
</div>