<?php
/**
 * Reset Password View
 * @var string $token - The raw reset token (passed from controller)
 * @var string $email - The email address being reset
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

        <div class="auth-tips">
            <h3 class="auth-tips__title">Password Requirements</h3>
            <ul class="auth-tips__list">
                <li id="tip-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                <li id="tip-upper"><i class="fas fa-circle"></i> One uppercase letter (A–Z)</li>
                <li id="tip-lower"><i class="fas fa-circle"></i> One lowercase letter (a–z)</li>
                <li id="tip-number"><i class="fas fa-circle"></i> One number (0–9)</li>
                <li id="tip-special"><i class="fas fa-circle"></i> One special character</li>
            </ul>
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
                <i class="fas fa-key"></i>
            </div>

            <h2 class="auth-form-wrap__title">Set New Password</h2>
            <p class="auth-form-wrap__sub">
                Create a strong password for
                <strong><?= htmlspecialchars($email ?? '') ?></strong>
            </p>

            <?php \App\Helpers\FlashHelper::render(); ?>

            <form
                action="/reset-password/update"
                method="POST"
                class="auth-form"
                id="resetPasswordForm"
                novalidate
            >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <input type="hidden" name="token"      value="<?= htmlspecialchars($token    ?? '') ?>">

                <!-- New Password -->
                <div class="form-group">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter new password"
                            required
                            autocomplete="new-password"
                            minlength="8"
                        >
                        <button type="button" class="input-toggle-pw" data-target="password" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="pw-strength" id="pwStrength" aria-live="polite">
                        <div class="pw-strength__bar">
                            <span class="pw-strength__fill" id="pwStrengthFill"></span>
                        </div>
                        <span class="pw-strength__label" id="pwStrengthLabel"></span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-input"
                            placeholder="Repeat new password"
                            required
                            autocomplete="new-password"
                        >
                        <button type="button" class="input-toggle-pw" data-target="password_confirmation" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-match-msg" id="matchMsg"></p>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn--primary btn--full btn--lg" id="submitBtn">
                    <i class="fas fa-check-circle"></i>
                    Reset Password
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
