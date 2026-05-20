<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

<!-- Preload fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- App CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">

<link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png">
</head>
<body class="auth-body">

<?php
// Flash messages (shown inside the right panel)
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$registerErrors = $_SESSION['register_errors'] ?? [];
$registerOld    = $_SESSION['register_old']    ?? [];
unset($_SESSION['register_errors'], $_SESSION['register_old']);
?>

<div class="auth-wrapper">
  <!-- ── LEFT PANEL ───────────────────────────────────────── -->
  <div class="auth-left">
    <div class="auth-dots"></div>
    <div class="auth-orb auth-orb-1"></div>
    <div class="auth-orb auth-orb-2"></div>
    <div class="auth-orb auth-orb-3"></div>

    <div class="auth-brand">
      <div class="auth-brand-icon">
        <i class="fas fa-briefcase" aria-hidden="true"></i>
      </div>
      <div class="auth-brand-text">
        <h1><?= APP_NAME ?></h1>
        <p>Public Employment Service Office · Balayan</p>
      </div>
    </div>

    <h2 class="auth-hero">
      Smart Job <span>Matching</span><br>&amp; Referral System
    </h2>
    <p class="auth-sub">
      Automated qualification filtering and decision support system for
      streamlined employment services in Balayan, Batangas.
    </p>

    <div class="auth-features">
      <div class="auth-feature">
        <div class="auth-feature-icon afi-blue">
          <i class="fas fa-robot" aria-hidden="true"></i>
        </div>
        <div>
          <h4>AI-Powered Matching</h4>
          <p>Skills · Education · Experience · Location scoring</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon afi-yellow">
          <i class="fas fa-bolt" aria-hidden="true"></i>
        </div>
        <div>
          <h4>Automated Status Tracking</h4>
          <p>Real-time applicant &amp; job status updates</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon afi-green">
          <i class="fas fa-shield-halved" aria-hidden="true"></i>
        </div>
        <div>
          <h4>Secure &amp; Compliant System</h4>
          <p>NSRP forms · Role-based access · Encrypted data</p>
        </div>
      </div>
    </div>

    <div class="auth-stats">
      <div class="auth-stat">
        <span class="auth-stat-num" data-target="1240">0</span>
        <span class="auth-stat-label">Applicants</span>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <span class="auth-stat-num" data-target="86">0</span>
        <span class="auth-stat-label">Employers</span>
      </div>
      <div class="auth-stat-divider"></div>
      <div class="auth-stat">
        <span class="auth-stat-num" data-target="94">0</span>
        <span class="auth-stat-label">% Match Rate</span>
      </div>
    </div>
  </div>

  <!-- ── RIGHT PANEL (inner view content) ─────────────────── -->
  <div class="auth-right">
    <?php if (!empty($flash)): ?>
    <div class="auth-alert auth-alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <i class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i>
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <?= $content ?>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
