<?php
/**
 * PESO Balayan IMIS — Topbar Component
 * File: app/views/components/topbar.php
 *
 * Usage: include inside main.php layout
 * Requires: $_SESSION['user_name'], $_SESSION['role'], $_SESSION['avatar']
 */

$userName       = htmlspecialchars($_SESSION['user_name']    ?? 'User',   ENT_QUOTES, 'UTF-8');
$role           = $_SESSION['role']  ?? 'applicant';
$avatarFile     = $_SESSION['avatar'] ?? null;
$notifCount     = intval($_SESSION['notif_count'] ?? 0);

/** Initials helper (reuse if sidebar included; re-declare only if not already defined) */
if (!function_exists('tbInitials')) {
    function tbInitials(string $name): string {
        $parts = array_filter(explode(' ', trim($name)));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return strtoupper(mb_substr($name, 0, 2));
    }
}

$initials = tbInitials($userName);

/** Role-based notification URL */
$notifUrl = match($role) {
    'employer'    => '/employer/notifications',
    'admin'       => '/admin/notifications',
    'super_admin' => '/superadmin/notifications',
    default       => '/applicant/notifications',
};

/** Role-based profile URL */
$profileUrl = match($role) {
    'employer'    => '/employer/profile',
    'admin'       => '/admin/settings',
    'super_admin' => '/superadmin/settings',
    default       => '/applicant/profile',
};
?>

<header class="topbar" role="banner">

    <!-- Left: system status -->
    <div class="topbar-left">
        <div class="system-status">
            <span class="status-dot" aria-hidden="true"></span>
            <span class="status-label">System Online</span>
        </div>
    </div>

    <!-- Right: datetime, AI, bell, user -->
    <div class="topbar-right">

        <span class="topbar-datetime" id="topbar-datetime" aria-live="polite"></span>

        <!-- Ask AI button -->
        <a href="/ai-assistant" class="btn-ask-ai" aria-label="Open AI assistant">
            <i class="ti ti-robot" aria-hidden="true"></i>
            Ask AI
        </a>

        <!-- Notification bell -->
        <a href="<?= htmlspecialchars($notifUrl, ENT_QUOTES, 'UTF-8') ?>"
           class="topbar-icon-btn" aria-label="Notifications<?= $notifCount > 0 ? ' (' . $notifCount . ' unread)' : '' ?>">
            <i class="ti ti-bell" aria-hidden="true"></i>
            <?php if ($notifCount > 0): ?>
                <span class="topbar-notif-count" aria-hidden="true">
                    <?= $notifCount > 99 ? '99+' : $notifCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- User chip -->
        <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"
           class="topbar-user" aria-label="Your profile">
            <div class="topbar-avatar">
                <?php if (!empty($avatarFile) && file_exists(BASE_PATH . '/uploads/avatars/' . $avatarFile)): ?>
                    <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($avatarFile, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= $userName ?>">
                <?php else: ?>
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div class="topbar-user-info">
                <div class="greeting" id="topbar-greeting" aria-live="polite">GOOD DAY</div>
                <div class="uname"><?= $userName ?></div>
            </div>
        </a>

    </div>
</header>

<script>
(function () {
    var days    = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function pad(n) { return n < 10 ? '0' + n : n; }

    function update() {
        var now  = new Date();
        var h    = now.getHours();
        var min  = now.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12  = h % 12 || 12;

        var greeting = h < 12 ? 'GOOD MORNING' : h < 17 ? 'GOOD AFTERNOON' : 'GOOD EVENING';

        var dtStr = days[now.getDay()] + ', ' +
                    months[now.getMonth()] + ' ' + now.getDate() + ', ' +
                    now.getFullYear() + ' · ' + h12 + ':' + pad(min) + ' ' + ampm;

        var dtEl = document.getElementById('topbar-datetime');
        var grEl = document.getElementById('topbar-greeting');
        if (dtEl) dtEl.textContent = dtStr;
        if (grEl) grEl.textContent = greeting;
    }

    update();
    setInterval(update, 30000);
})();
</script>
