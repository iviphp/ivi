<?php

use Softadastra\Application\Meta\ManagerHeadPage;

?>
<div class="containere">
    <h2>Email Verification</h2>
    <p style="background-color: #e7f5ff;color: #007185;padding:5px;">Please enter the verification code sent to your email address.</p>
    <?php if (isset($_SESSION['error_otp'])): ?>
        <div class="error error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?= $_SESSION['error_otp']; ?>
            <?php unset($_SESSION['error_otp']); ?>
        </div>
    <?php endif; ?>
    <form id="otp-form" action="/auth/verify-email-post" method="POST">
        <div class="form-group">
            <label for="otp">6-Digit Verification Code :</label>
            <input type="text" id="otp" name="otp" required maxlength="6" pattern="\d*">
        </div>
        <button type="submit">Verify</button>
    </form>
</div>
<script>
    document.getElementById('otp-form').addEventListener('submit', function(event) {
        var otpField = document.getElementById('otp');
        var otpValue = otpField.value;
        if (otpValue.length > 6 || !/^\d+$/.test(otpValue)) {
            event.preventDefault();
            otpField.focus();
            alert("Please enter a valid 6-digit OTP code.");
        }
    });
    document.getElementById('otp').addEventListener('input', function(event) {
        var value = event.target.value;
        event.target.value = value.replace(/[^0-9]/g, '');
    });
</script>