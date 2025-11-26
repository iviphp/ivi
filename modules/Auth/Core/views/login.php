<?php
$next = isset($_GET['next']) && trim($_GET['next']) !== '' ? trim($_GET['next']) : '/';
?>
<div class="sa-auth">
    <section class="sa-card">
        <!-- Header -->
        <div class="sa-card__head">
            <a href="/auth<?= $next ? '?next=' . urlencode($next) : '' ?>" class="sa-back">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Back</span>
            </a>
            <div class="sa-logo">
                <img src="<?= asset('assets/logo/softadastra.png') ?>" alt="Softadastra Logo">
            </div>
        </div>

        <!-- Body -->
        <div class="sa-card__body">
            <h2 class="sa-title">Sign in</h2>
            <form id="loginForm" method="post" class="sa-form">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>" id="nextParam">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf_token ?? \Ivi\Core\Security\Csrf::generateToken(false)) ?>">

                <!-- Email -->
                <div class="sa-field">
                    <label class="sa-label" for="email">Email</label>
                    <input type="email"
                        class="sa-input"
                        id="email"
                        name="email"
                        value="<?= isset($_SESSION['existing_email']) ? htmlspecialchars($_SESSION['existing_email']) : ''; ?>"
                        required>
                </div>

                <!-- Password -->
                <div class="sa-field">
                    <label class="sa-label" for="password">Password</label>
                    <div class="sa-password">
                        <input type="password" class="sa-input" id="password" name="password" required>
                        <button type="button"
                            class="sa-password__toggle"
                            id="togglePassword"
                            aria-label="Show password">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="sa-actions">
                    <button type="submit"
                        id="custom-login-login"
                        class="sa-btn sa-btn--primary"
                        aria-live="polite">
                        <span class="btn-text">Continue</span>
                        <span class="btn-spinner" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>

            <!-- Forgot password -->
            <a href="/auth/forgot-password<?= $next ? '?next=' . urlencode($next) : '' ?>" class="sa-link sa-forgot" data-spa>Forgot your password?</a>

            <!-- Footer / Register -->
            <div class="sa-footer">
                <p>New to Softadastra?</p>
                <a href="/auth/register<?= $next ? '?next=' . urlencode($next) : '' ?>" class="sa-btn sa-btn--secondary" data-spa>Create my account</a>
            </div>
        </div>
    </section>
</div>

<!-- Bottom-sheet popup pour showMessage -->
<div id="shop-popup" class="shop-popup" style="display:none;">
    <div class="shop-popup-backdrop"></div>
    <div class="shop-popup-sheet" role="dialog" aria-live="polite" aria-modal="true">
        <div class="shop-popup-grabber" aria-hidden="true"></div>
        <div class="shop-popup-header">
            <span id="popup-icon" class="popup-icon" aria-hidden="true"></span>
            <h3 id="popup-title" class="popup-title">Message</h3>
        </div>
        <div class="shop-popup-body">
            <p id="popup-text" class="popup-text"></p>
            <a id="popup-link" class="popup-link" href="#" style="display:none;">Open</a>
        </div>
        <button type="button" class="shop-popup-close" aria-label="Close">&times;</button>
    </div>
</div>
<script>
    window.SA_USER_ID = null;

    window.addEventListener("DOMContentLoaded", () => {
        if (window.SA && typeof SA.event === "function") {
            SA.event("auth_login_view", {
                method: "email_password"
            });
        }
    });
</script>