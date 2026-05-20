<?php
/**
 * PESO Balayan IMIS — Admin Dashboard View
 * File: app/views/admin/dashboard.php
 *
 * Data expected from AdminDashboardController:
 *   $stats   (array)  — totalApplicants, activeJobs, appsToday, activeMatches, placements
 *   $recent  (array)  — last 10 activity rows: name, initials, color, action, detail, status, time
 *   $title   (string) — 'Dashboard'
 */

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');

// Sanitise stats with defaults to prevent undefined-key notices
$s = array_merge([
    'totalApplicants' => 0,
    'activeJobs'      => 0,
    'appsToday'       => 0,
    'activeMatches'   => 0,
    'placements'      => 0,
    'pendingEmployers'=> 0,
], (array)($stats ?? []));

$recent = (array)($recent ?? []);

// Live date (server-side; JS will update the hero badge on the client)
$todayFormatted = date('l, F j, Y');
?>

<!-- ── Page header ──────────────────────────────────────── -->
<div class="page-header">
    <h1>Dashboard</h1>
    <p class="page-subtitle">
        Welcome back, <?= $userName ?> 👋
    </p>
</div>

<!-- ── Hero banner ──────────────────────────────────────── -->
<div class="hero-banner" role="complementary" aria-label="Office overview banner">
    <div class="hero-inner">
        <div>
            <h2 class="hero-title">PESO Balayan at a Glance</h2>
            <p class="hero-desc">Here's what's happening in the employment office today.</p>
            <span class="hero-date-badge" id="hero-date-badge">
                <i class="ti ti-calendar" aria-hidden="true"></i>
                <?= htmlspecialchars($todayFormatted, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <div class="hero-status" aria-label="System status: online">
            <span class="hero-status-dot" aria-hidden="true"></span>
            System Online
        </div>
    </div>
</div>

<!-- ── Stats ────────────────────────────────────────────── -->
<p class="section-label">Office Overview</p>

<div class="stats-grid" role="list" aria-label="Key statistics">

    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Total<br>Applicants</span>
            <div class="stat-icon blue" aria-hidden="true">
                <i class="ti ti-users"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['totalApplicants']) ?></div>
        <div class="stat-meta">
            <i class="ti ti-database" aria-hidden="true"></i> In Database
        </div>
    </div>

    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Active Job<br>Vacancies</span>
            <div class="stat-icon green" aria-hidden="true">
                <i class="ti ti-briefcase"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['activeJobs']) ?></div>
        <div class="stat-meta">
            <i class="ti ti-circle-check" aria-hidden="true"></i> Active Listings
        </div>
    </div>

    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Applications<br>Today</span>
            <div class="stat-icon amber" aria-hidden="true">
                <i class="ti ti-file-description"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['appsToday']) ?></div>
        <div class="stat-meta up">
            <i class="ti ti-arrow-up" aria-hidden="true"></i> Today
        </div>
    </div>

    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Active<br>Matches</span>
            <div class="stat-icon gray" aria-hidden="true">
                <i class="ti ti-arrows-shuffle"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['activeMatches']) ?></div>
        <div class="stat-meta">
            <i class="ti ti-cpu" aria-hidden="true"></i> Computed
        </div>
    </div>

    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Successful<br>Placements</span>
            <div class="stat-icon teal" aria-hidden="true">
                <i class="ti ti-trophy"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['placements']) ?></div>
        <div class="stat-meta up">
            <i class="ti ti-arrow-up" aria-hidden="true"></i> This Year
        </div>
    </div>

    <?php if ($s['pendingEmployers'] > 0): ?>
    <div class="stat-card" role="listitem">
        <div class="stat-card-top">
            <span class="stat-label">Pending<br>Employers</span>
            <div class="stat-icon amber" aria-hidden="true">
                <i class="ti ti-building-store"></i>
            </div>
        </div>
        <div class="stat-value"><?= number_format($s['pendingEmployers']) ?></div>
        <div class="stat-meta">
            <i class="ti ti-clock" aria-hidden="true"></i> Needs Review
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ── Recent activity ──────────────────────────────────── -->
<p class="section-label">Recent Activity</p>

<div class="activity-card" role="region" aria-label="Recent activity">

    <div class="activity-card-header">
        <h2 class="card-title">Latest Updates</h2>
        <a href="/admin/activity" class="view-all" aria-label="View all activity">
            View all <i class="ti ti-arrow-right" aria-hidden="true"></i>
        </a>
    </div>

    <?php if (!empty($recent)): ?>
        <?php foreach ($recent as $row):
            $initials = htmlspecialchars($row['initials'] ?? '?', ENT_QUOTES, 'UTF-8');
            $color    = htmlspecialchars($row['color']    ?? 'blue', ENT_QUOTES, 'UTF-8');
            $name     = htmlspecialchars($row['name']     ?? '', ENT_QUOTES, 'UTF-8');
            $action   = htmlspecialchars($row['action']   ?? '', ENT_QUOTES, 'UTF-8');
            $detail   = htmlspecialchars($row['detail']   ?? '', ENT_QUOTES, 'UTF-8');
            $status   = htmlspecialchars($row['status']   ?? 'new', ENT_QUOTES, 'UTF-8');
            $time     = htmlspecialchars($row['time']     ?? '', ENT_QUOTES, 'UTF-8');

            // Color map for initials avatar
            $colorMap = [
                'blue'   => 'background:#eff6ff;color:#1d4ed8;',
                'green'  => 'background:#f0fdf4;color:#15803d;',
                'amber'  => 'background:#fffbeb;color:#b45309;',
                'red'    => 'background:#fef2f2;color:#b91c1c;',
                'teal'   => 'background:#f0fdfa;color:#0d9488;',
                'gray'   => 'background:#f8fafc;color:#475569;',
            ];
            $avatarStyle = $colorMap[$color] ?? $colorMap['gray'];
        ?>
        <div class="activity-row">
            <div class="activity-user">
                <div class="activity-initials" style="<?= $avatarStyle ?>"
                     aria-hidden="true"><?= $initials ?></div>
                <div style="min-width:0;">
                    <div class="activity-name"><?= $name ?></div>
                    <div class="activity-desc"><?= $action ?> — <?= $detail ?></div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <?php if ($time): ?>
                    <span style="font-size:10px;color:var(--text-muted);"><?= $time ?></span>
                <?php endif; ?>
                <span class="badge badge-<?= $status ?>"><?= ucfirst($status) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:32px;text-align:center;color:var(--text-muted);">
            <i class="ti ti-inbox" style="font-size:32px;display:block;margin-bottom:8px;"
               aria-hidden="true"></i>
            <span style="font-size:13px;">No recent activity yet.</span>
        </div>
    <?php endif; ?>

</div>

<!-- Hero date live update -->
<script>
(function () {
    var el = document.getElementById('hero-date-badge');
    if (!el) return;
    var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
    var now    = new Date();
    el.innerHTML = '<i class="ti ti-calendar" aria-hidden="true"></i> ' +
                   days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' +
                   now.getDate() + ', ' + now.getFullYear();
})();
</script>
