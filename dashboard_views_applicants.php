<?php
/**
 * Applicant Dashboard View
 *
 * Available: $applicant, $stats, $recentApplications, $recentJobs, $notifications
 */
?>

<div class="page-header">
    <div class="page-header__text">
        <h1 class="page-header__title">My Dashboard</h1>
        <p class="page-header__sub">
            Welcome back, <?= htmlspecialchars($applicant['first_name'] ?? 'Applicant') ?> 👋
        </p>
    </div>
    <div class="page-header__meta">
        <span class="page-header__date">
            <i class="fas fa-calendar-alt"></i>
            <?= date('l, F j, Y') ?>
        </span>
    </div>
</div>

<!-- ── STAT CARDS ─────────────────────────────────────── -->
<section class="dash-cards" aria-label="Dashboard Statistics">
    <?php
    $cards = [
        [
            'cardIcon'  => 'fa-file-alt',
            'cardLabel' => 'Total Applications',
            'cardValue' => $stats['total_applications'],
            'cardColor' => 'blue',
            'cardBadge' => 'All Time',
            'cardLink'  => '/applicant/applications',
        ],
        [
            'cardIcon'  => 'fa-hourglass-half',
            'cardLabel' => 'Pending',
            'cardValue' => $stats['pending_applications'],
            'cardColor' => 'orange',
            'cardBadge' => 'In Review',
            'cardLink'  => '/applicant/applications?status=pending',
        ],
        [
            'cardIcon'  => 'fa-check-circle',
            'cardLabel' => 'Matched',
            'cardValue' => $stats['matched_jobs'],
            'cardColor' => 'green',
            'cardBadge' => 'Matched',
            'cardLink'  => '/applicant/applications?status=matched',
        ],
        [
            'cardIcon'  => 'fa-paper-plane',
            'cardLabel' => 'Referred',
            'cardValue' => $stats['referred_jobs'],
            'cardColor' => 'purple',
            'cardBadge' => 'Referred',
            'cardLink'  => '/applicant/applications?status=referred',
        ],
    ];

    foreach ($cards as $card):
        extract($card);
        include __DIR__ . '/../components/dashboard-card.php';
    endforeach;
    ?>
</section>

<!-- ── MAIN CONTENT GRID ──────────────────────────────── -->
<div class="dash-grid">

    <!-- Recent Applications Table -->
    <div class="dash-grid__col dash-grid__col--wide">
        <?php
        $tableId      = 'recent-applications';
        $tableTitle   = 'Recent Applications';
        $tableHeaders = ['Job Title', 'Company', 'Date Applied', 'Status'];
        $tableViewAll = '/applicant/applications';
        $tableEmpty   = 'You have not applied to any jobs yet.';

        $tableRows = array_map(function ($app) {
            $statusMap = [
                'pending'  => '<span class="badge badge--orange">Pending</span>',
                'matched'  => '<span class="badge badge--green">Matched</span>',
                'referred' => '<span class="badge badge--purple">Referred</span>',
                'rejected' => '<span class="badge badge--red">Rejected</span>',
                'hired'    => '<span class="badge badge--blue">Hired</span>',
            ];
            $status = $statusMap[$app['status'] ?? 'pending'] ?? '<span class="badge badge--gray">Unknown</span>';

            return [
                htmlspecialchars($app['job_title']    ?? '—'),
                htmlspecialchars($app['company_name'] ?? '—'),
                htmlspecialchars(date('M d, Y', strtotime($app['created_at'] ?? 'now'))),
                $status,
            ];
        }, $recentApplications ?? []);

        include __DIR__ . '/../components/data-table.php';
        ?>
    </div>

    <!-- Activity Feed: Latest Jobs + Notifications -->
    <div class="dash-grid__col dash-grid__col--narrow">
        <?php
        $feedTitle   = 'Latest Job Openings';
        $feedViewAll = '/jobs';
        $feedItems   = array_map(function ($job) {
            return [
                'icon'     => 'fa-briefcase',
                'color'    => 'blue',
                'title'    => $job['title']        ?? 'Untitled Job',
                'subtitle' => $job['company_name'] ?? '',
                'time'     => isset($job['created_at']) ? date('M d', strtotime($job['created_at'])) : '',
            ];
        }, $recentJobs ?? []);

        include __DIR__ . '/../components/activity-feed.php';
        ?>
    </div>

</div>
