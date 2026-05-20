<?php
/**
 * PESO Balayan IMIS — Sidebar Component
 * File: app/views/components/sidebar.php
 *
 * Usage: include inside main.php layout
 * Requires: $_SESSION['role'], $_SESSION['user_name'], $_SESSION['avatar']
 */

$role        = $_SESSION['role']      ?? 'applicant';
$userName    = htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$avatarFile  = $_SESSION['avatar']    ?? null;
$currentUri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/**
 * Derive initials from full name for avatar fallback.
 */
function sbInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

/**
 * Emit a single nav link.
 *
 * @param string $href    Route path e.g. /admin/dashboard
 * @param string $icon    Tabler icon name e.g. ti-users
 * @param string $label   Display text
 * @param int    $badge   Optional notification count (0 = hidden)
 */
function navLink(string $href, string $icon, string $label, int $badge = 0): void {
    $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $active     = (strpos($currentUri, $href) === 0) ? ' active' : '';
    $badgeHtml  = ($badge > 0)
        ? '<span class="nav-badge">' . intval($badge) . '</span>'
        : '';
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="nav-link' . $active . '">';
    echo '<i class="ti ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
    echo '<span class="nav-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo $badgeHtml;
    echo '</a>';
}

/** Role label map */
$roleLabels = [
    'applicant'   => 'APPLICANT',
    'employer'    => 'EMPLOYER',
    'admin'       => 'ADMIN',
    'super_admin' => 'SUPER ADMIN',
];

$roleLabel = $roleLabels[$role] ?? strtoupper($role);
$initials  = sbInitials($userName);
?>

<aside class="sidebar" id="sidebar" aria-label="Main navigation">

    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            <i class="ti ti-shield-check" aria-hidden="true"></i>
        </div>
        <div class="sidebar-logo-text">
            <span class="sys-name">PESO Balayan</span>
            <span class="sys-sub">Balayan, Batangas</span>
        </div>
    </div>

    <!-- User profile -->
    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php if (!empty($avatarFile) && file_exists(BASE_PATH . '/uploads/avatars/' . $avatarFile)): ?>
                <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($avatarFile, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= $userName ?>'s profile picture">
            <?php else: ?>
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </div>
        <div class="sidebar-user-name"><?= $userName ?></div>
        <span class="sidebar-role-badge"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="Sidebar navigation">

        <?php if ($role === 'applicant'): ?>
            <div class="nav-section-label">Main Menu</div>
            <?php navLink('/applicant/dashboard', 'ti-layout-dashboard', 'Dashboard'); ?>
            <?php navLink('/applicant/jobs',      'ti-briefcase',        'Job Vacancies'); ?>
            <?php navLink('/applicant/applications', 'ti-file-description', 'My Applications'); ?>
            <?php navLink('/applicant/profile',   'ti-user-circle',     'My Profile'); ?>
            <div class="nav-section-label">Account</div>
            <?php navLink('/applicant/settings',  'ti-settings',        'Settings'); ?>

        <?php elseif ($role === 'employer'): ?>
            <div class="nav-section-label">Main Menu</div>
            <?php navLink('/employer/dashboard',   'ti-layout-dashboard',  'Dashboard'); ?>
            <?php navLink('/employer/jobs',         'ti-briefcase',         'Job Postings'); ?>
            <?php navLink('/employer/referrals',    'ti-users',             'Referred Applicants'); ?>
            <?php navLink('/employer/notifications','ti-bell',              'Notifications',
                           intval($_SESSION['notif_count'] ?? 0)); ?>
            <div class="nav-section-label">Account</div>
            <?php navLink('/employer/profile',     'ti-building-store',    'Company Profile'); ?>
            <?php navLink('/employer/settings',    'ti-settings',          'Settings'); ?>

        <?php elseif ($role === 'admin'): ?>
            <div class="nav-section-label">Main Menu</div>
            <?php navLink('/admin/dashboard',    'ti-layout-dashboard', 'Dashboard'); ?>
            <?php navLink('/admin/applicants',   'ti-users',            'Applicant Registry'); ?>
            <?php navLink('/admin/jobs',         'ti-briefcase',        'Job Vacancies'); ?>
            <?php navLink('/admin/matching',     'ti-arrows-shuffle',   'Referrals & Matching'); ?>
            <?php navLink('/admin/employers',    'ti-building-store',   'Employer Verification'); ?>
            <?php navLink('/admin/reports',      'ti-chart-bar',        'Reports'); ?>
            <div class="nav-section-label">Account</div>
            <?php navLink('/admin/settings',     'ti-settings',         'Settings'); ?>

        <?php elseif ($role === 'super_admin'): ?>
            <div class="nav-section-label">Main Menu</div>
            <?php navLink('/superadmin/dashboard',  'ti-layout-dashboard', 'System Dashboard'); ?>
            <?php navLink('/superadmin/applicants', 'ti-users',            'Applicant Registry'); ?>
            <?php navLink('/superadmin/jobs',       'ti-briefcase',        'Job Vacancies'); ?>
            <?php navLink('/superadmin/matching',   'ti-arrows-shuffle',   'Referrals & Matching'); ?>
            <?php navLink('/superadmin/employers',  'ti-building-store',   'Employer Verification'); ?>
            <?php navLink('/superadmin/reports',    'ti-chart-bar',        'Reports'); ?>
            <div class="nav-section-label">System</div>
            <?php navLink('/superadmin/audit',      'ti-history',          'Audit Logs'); ?>
            <?php navLink('/superadmin/users',      'ti-user-cog',         'Manage Users'); ?>
            <?php navLink('/superadmin/settings',   'ti-settings',         'Settings'); ?>
        <?php endif; ?>

        <!-- Logout — always last -->
        <a href="/logout" class="nav-link nav-logout"
           onclick="return confirm('Are you sure you want to log out?')">
            <i class="ti ti-logout" aria-hidden="true"></i>
            <span class="nav-label">Logout</span>
        </a>

    </nav>
</aside>
