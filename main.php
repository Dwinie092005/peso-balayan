<?php
/**
 * PESO Balayan IMIS — Main Dashboard Layout
 * File: app/views/layouts/main.php
 *
 * Usage (in any controller view):
 *   $layout = 'main';
 *   $title  = 'Dashboard';
 *   include VIEW_PATH . '/layouts/main.php';
 *
 * Alternatively the BaseController can call:
 *   $this->render('admin/dashboard', ['title' => 'Dashboard'], 'main');
 *
 * Expects:
 *   $title   (string)  — <title> and page heading
 *   $content (string)  — rendered inner view HTML (when using output buffering)
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__, 3));
defined('BASE_URL')  || define('BASE_URL',  rtrim($_ENV['APP_URL'] ?? '', '/'));

// Guard: require authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    header('Location: /login');
    exit;
}

$pageTitle = htmlspecialchars($title ?? 'Dashboard', ENT_QUOTES, 'UTF-8');
$role      = $_SESSION['role'] ?? 'applicant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> — PESO Balayan IMIS</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tabler Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <!-- Project styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/dashboard.css">

    <!-- Page-specific styles (optional — set $extraCss in controller) -->
    <?php if (!empty($extraCss)): ?>
        <?php foreach ((array)$extraCss as $css): ?>
            <link rel="stylesheet"
                  href="<?= htmlspecialchars(BASE_URL . $css, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="dashboard-body">

<div class="dashboard-wrapper">

    <!-- ── Sidebar ───────────────────────────────────────── -->
    <?php include VIEW_PATH . '/components/sidebar.php'; ?>

    <!-- ── Topbar ────────────────────────────────────────── -->
    <?php include VIEW_PATH . '/components/topbar.php'; ?>

    <!-- ── Content area ──────────────────────────────────── -->
    <main class="content-area" id="main-content" role="main" tabindex="-1">

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash'])): ?>
            <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
                <div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
                     role="alert" id="flash-alert">
                    <i class="ti <?= $type === 'success' ? 'ti-circle-check' : ($type === 'danger' ? 'ti-alert-circle' : 'ti-info-circle') ?>"
                       aria-hidden="true"></i>
                    <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                    <button class="alert-close" onclick="this.parentElement.remove()"
                            aria-label="Dismiss message">
                        <i class="ti ti-x" aria-hidden="true"></i>
                    </button>
                </div>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <!-- Inner page view -->
        <?php
        if (!empty($content)) {
            // Output buffering mode: controller passed rendered string
            echo $content;
        } elseif (!empty($view)) {
            // Direct include mode: controller set $view path
            $viewFile = VIEW_PATH . '/' . ltrim($view, '/') . '.php';
            if (file_exists($viewFile)) {
                include $viewFile;
            } else {
                echo '<p style="color:#dc2626;">View not found: ' .
                     htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8') . '</p>';
            }
        }
        ?>

    </main>

</div><!-- /.dashboard-wrapper -->

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<!-- Global JS -->
<script src="<?= BASE_URL ?>/js/dashboard.js" defer></script>

<!-- Page-specific scripts (optional) -->
<?php if (!empty($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
        <script src="<?= htmlspecialchars(BASE_URL . $js, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Flash auto-dismiss after 5 s
(function () {
    var el = document.getElementById('flash-alert');
    if (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity    = '0';
            setTimeout(function () { el.remove(); }, 420);
        }, 5000);
    }
})();
</script>

</body>
</html>
