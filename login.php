<?php
/**
 * PESO Balayan – Login View
 * File: app/views/auth/login.php
 * Layout: auth
 */
?>
<div class="auth-form-box">

  <div class="auth-form-icon">
    <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
  </div>

  <h2 class="auth-form-title">Welcome Back</h2>
  <p class="auth-form-sub">Sign in to your <?= APP_NAME ?> account</p>

  <!-- Role tabs -->
  <div class="auth-role-tabs" role="tablist" aria-label="Select account type">
    <button class="auth-role-tab active" data-role="applicant" type="button" role="tab" aria-selected="true">
      <i class="fas fa-user" aria-hidden="true"></i> Applicant
    </button>
    <button class="auth-role-tab" data-role="employer" type="button" role="tab" aria-selected="false">
      <i class="fas fa-building" aria-hidden="true"></i> Employer
    </button>
    <button class="auth-role-tab" data-role="admin" type="button" role="tab" aria-selected="false">
      <i class="fas fa-user-shield" aria-hidden="true"></i> Admin
    </button>
  </div>

  <!-- Login form -->
  <form id="loginForm" action="<?= BASE_URL ?>/login" method="POST" novalidate>
    <?= $csrfField ?>
    <input type="hidden" name="selected_role" id="selectedRoleInput" value="applicant">

    <!-- Email -->
    <div class="auth-field">
      <label for="email" class="auth-label">Email Address</label>
      <div class="auth-input-wrap">
        <i class="fas fa-envelope auth-icon-left" aria-hidden="true"></i>
        <input
          type="email"
          id="email"
          name="email"
          class="auth-input"
          placeholder="Enter your email address"
          autocomplete="email"
          required
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >
      </div>
    </div>

    <!-- Password -->
    <div class="auth-field">
      <div class="auth-label-row">
        <label for="password" class="auth-label">Password</label>
        <a href="<?= BASE_URL ?>/forgot-password" class="auth-forgot">Forgot password?</a>
      </div>
      <div class="auth-input-wrap">
        <i class="fas fa-lock auth-icon-left" aria-hidden="true"></i>
        <input
          type="password"
          id="password"
          name="password"
          class="auth-input"
          placeholder="Enter your password"
          autocomplete="current-password"
          required
        >
        <button type="button" class="auth-toggle-pass" aria-label="Toggle password visibility">
          <i class="fas fa-eye" id="passEyeIcon" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <!-- Remember me -->
    <div class="auth-remember">
      <label class="auth-check-label">
        <input type="checkbox" name="remember_me" value="1">
        <span>Remember me for 30 days</span>
      </label>
    </div>

    <!-- Submit -->
    <button type="submit" class="auth-btn-submit" id="loginSubmitBtn">
      <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
      <span class="btn-label">Sign In</span>
      <span class="btn-spinner" aria-hidden="true" style="display:none;">
        <i class="fas fa-spinner fa-spin"></i>
      </span>
    </button>
  </form>

  <!-- Security note -->
  <p class="auth-secure-note">
    <i class="fas fa-circle-check" aria-hidden="true"></i>
    Your data is securely encrypted and protected
  </p>

  <!-- Register link -->
  <p class="auth-alt-link">
    New applicant? <a href="<?= BASE_URL ?>/register">Register here</a>
  </p>
</div>
