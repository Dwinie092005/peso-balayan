<?php
/**
 * Employer Dashboard View
 *
 * Available: $employer, $stats, $recentJobs, $recentApplicants, $notifications
 */
?>

<div class="page-header">
    <div class="page-header__text">
        <h1 class="page-header__title">Employer Dashboard</h1>
        <p class="page-header__sub">
            Welcome, <?= htmlspecialchars($employer['company_name'] ?? 'Employer') ?> 🏢
        </p>
    </div>
    <div class="page-header__meta">
        <span class="page-header__date">
            <i class="fas fa-calendar-alt"></i>
            <?= date('l, F j, Y') ?>
        </span>
        <a href="/employer/jobs/create" class="btn btn--primary btn--sm">
            <i class="fas fa-plus"></i> Post a Job
        </a>
    </div>
</div>

<!-- ── STAT CARDS ─────────────────────────────────────── -->
<section class="dash-cards" aria-label="Dashboard Statistics">
    <?php
    $cards = [
        [
            'cardIcon'  => 'fa-briefcase',
            'cardLabel' => 'Total Jobs Posted',
            'cardValue' => $stats['total_jobs'],
            'cardColor' => 'blue',
            'cardBadge' => 'All Time',
            'cardLink'  => '/employer/jobs',
        ],
        [
            'cardIcon'  => 'fa-toggle-on',
            'cardLabel' => 'Active Listings',
            'cardValue' => $stats['active_jobs'],
            'cardColor' => 'green',
            'cardBadge' => 'Live',
            'cardLink'  => '/employer/jobs?status=active',
        ],
        [
            'cardIcon'  => 'fa-users',
            'cardLabel' => 'Total Applicants',
            'cardValue' => $stats['total_applicants'],
            'cardColor' => 'orange',
            'cardBadge' => 'Applied',
            'cardLink'  => '/employer/applicants',
        ],
        [
            'cardIcon'  => 'fa-paper-plane',
            'cardLabel' => 'Referred to You',
            'cardValue' => $stats['referred'],
            'cardColor' => 'purple',
            'cardBadge' => 'From PESO',
            'cardLink'  => '/employer/applicants?status=referred',
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

    <!-- Active Job Listings -->
    <div class="dash-grid__col dash-grid__col--wide">
        <?php
        $tableId      = 'employer-jobs';
        $tableTitle   = 'Your Job Postings';
        $tableHeaders = ['Job Title', 'Slots', 'Applications', 'Status', 'Posted'];
        $tableViewAll = '/employer/jobs';
        $tableEmpty   = 'No job postings yet. Post your first job!';

        $tableRows = array_map(function ($job) {
            $statusMap = [
                'active'   => '<span class="badge badge--green">Active</span>',
                'closed'   => '<span class="badge badge--red">Closed</span>',
                'draft'    => '<span class="badge badge--gray">Draft</span>',
            ];
            $status = $statusMap[$job['status'] ?? 'draft'] ?? '<span class="badge badge--gray">Unknown</span>';

            return [
                htmlspecialchars($job['title']             ?? '—'),
                htmlspecialchars($job['slots']             ?? '0'),
                htmlspecialchars($job['application_count'] ?? '0'),
                $status,
                htmlspecialchars(date('M d, Y', strtotime($job['created_at'] ?? 'now'))),
            ];
        }, $recentJobs ?? []);

        include __DIR__ . '/../components/data-table.php';
        ?>
    </div>

    <!-- Recent Referred Applicants Feed -->
    <div class="dash-grid__col dash-grid__col--narrow">
        <?php
        $feedTitle   = 'Recently Referred Applicants';
        $feedViewAll = '/employer/applicants';
        $feedItems   = array_map(function ($app) {
            return [
                'icon'     => 'fa-user-check',
                'color'    => 'purple',
                'title'    => trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')),
                'subtitle' => $app['job_title'] ?? '',
                'time'     => isset($app['referred_at']) ? date('M d', strtotime($app['referred_at'])) : '',
            ];
        }, $recentApplicants ?? []);

        include __DIR__ . '/../components/activity-feed.php';
        ?>
    </div>

</div>
