<?php
/**
 * Forgot Password View
 * Matches the existing animated split-screen login design.
 */
?>

<div class="auth-page">

    <!-- LEFT PANEL -->
    <div class="auth-page__panel auth-page__panel--left">
        <div class="auth-brand">
            <div class="auth-brand__logo">
                <i class="fas fa-briefcase"></i>
            </div>
            <h1 class="auth-brand__name">PESO Balayan</h1>
            <p class="auth-brand__tagline">Automated Qualification Filtering &amp; Decision Support System</p>
        </div>

        <div class="auth-features">
            <div class="auth-features__item">
                <i class="fas fa-shield-alt"></i>
                <span>Secure System</span>
            </div>
            <div class="auth-features__item">
                <i class="fas fa-bolt"></i>
                <span>Smart Matching</span>
            </div>
            <div class="auth-features__item">
                <i class="fas fa-chart-line"></i>
                <span>Real-time Reports</span>
            </div>
        </div>

        <div class="auth-page__floating-shapes" aria-hidden="true">
            <span class="shape shape--1"></span>
            <span class="shape shape--2"></span>
            <span class="shape shape--3"></span>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="auth-page__panel auth-page__panel--right">
        <div class="auth-form-wrap">

            <div class="auth-form-wrap__icon">
                <i class="fas fa-lock-open"></i>
            </div>

            <h2 class="auth-form-wrap__title">Forgot Password?</h2>
            <p class="auth-form-wrap__sub">
                Enter your registered email and we'll send you a secure reset link.
            </p>

            <?php \App\Helpers\FlashHelper::render(); ?>

            <form
                action="/forgot-password/send"
                method="POST"
                class="auth-form"
                id="forgotPasswordForm"
                novalidate
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-envelope input-icon"></i>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="Enter your registered email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn--primary btn--full btn--lg" id="submitBtn">
                    <i class="fas fa-paper-plane"></i>
                    Send Reset Link
                </button>

                <div class="auth-form__links">
                    <a href="/login" class="auth-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>
