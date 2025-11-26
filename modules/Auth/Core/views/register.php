<?php
$next = isset($_GET['next']) ? trim($_GET['next']) : '';
?>
<div class="sa-auth">
    <section class="sa-card">
        <header class="sa-card__head">
            <a href="/auth/login"
                class="sa-back"
                data-sa-event="auth_login_back"
                data-label="from_register" data-spa>
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                <span>Login</span>
            </a>
            <div class="sa-logo">
                <img src="<?= asset('assets/logo/softadastra.png') ?>"
                    alt="Softadastra Logo"
                    data-sa-event="auth_logo_click"
                    data-label="register_screen">
            </div>
        </header>

        <div class="sa-card__body">
            <h2 class="sa-title">Create an account</h2>

            <form id="registerForm" method="post" class="sa-form">
                <input type="hidden" name="csrf_token" value="<?= isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
                <input type="hidden" name="next" value="<?= htmlspecialchars($next); ?>">

                <!-- Fullname -->
                <div class="sa-field">
                    <label class="sa-label" for="fullname">Full Name</label>
                    <input type="text" class="sa-input" id="fullname" name="fullname" spellcheck="false" required>
                </div>

                <!-- Email -->
                <div class="sa-field" id="email-group">
                    <label class="sa-label" for="email">Email</label>
                    <input type="email" class="sa-input" id="email" name="email" spellcheck="false" required>
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
                            <i class="fa fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <small class="sa-help">8–20 chars, upper & lower case, a digit & a symbol.</small>
                </div>

                <div class="sa-actions">
                    <button type="submit" class="sa-btn sa-btn--primary">
                        <span class="btn-text"><i class="fas fa-check"></i> Continue</span>
                    </button>
                </div>
            </form>


            <!-- Popup -->
            <div id="popupMessage" class="sa-popup" role="alert" aria-live="polite">
                <button id="closePopup" class="sa-popup__close" aria-label="Close">&times;</button>
                <p id="popupText"></p>
            </div>
        </div>
    </section>
</div>

<script>
    window.SA_API_BASE = window.SA_API_BASE || "http://localhost:3000";
    window.SA_USER_ID = null;

    window.addEventListener("DOMContentLoaded", () => {
        if (window.SA && typeof SA.event === "function") {
            SA.event("auth_register_view", {
                method: "email+password"
            });
        }
    });
</script>

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