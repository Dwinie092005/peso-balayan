<?php
/**
 * View: employer/applicants.php
 * Location : /app/views/employer/applicants.php
 *
 * Employer-facing applicant pipeline.
 * Displays all applicants for the employer's job postings,
 * grouped by hiring status with bulk action support.
 *
 * Variables supplied by ApplicationController::employerList():
 * @var array  $applications   Paginated application rows
 * @var array  $jobs           Employer's job list (for filter dropdown)
 * @var array  $stats          ['total','under_review','interview','hired']
 * @var int    $total_count    Total matched applications
 * @var int    $current_page
 * @var int    $total_pages
 * @var string $filter_status  Active status filter
 * @var int    $filter_job_id  Active job filter
 * @var string $csrf_token
 */

$applications  = $applications  ?? [];
$jobs          = $jobs          ?? [];
$stats         = $stats         ?? ['total' => 0, 'under_review' => 0, 'interview' => 0, 'hired' => 0];
$total_count   = $total_count   ?? 0;
$current_page  = $current_page  ?? 1;
$total_pages   = $total_pages   ?? 1;
$filter_status = $filter_status ?? '';
$filter_job_id = $filter_job_id ?? 0;
$csrf_token    = $csrf_token    ?? ($_SESSION['csrf_token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicants — Employer Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="/public/css/components/forms.css">
    <link rel="stylesheet" href="/public/css/components/hiring.css">
    <link rel="stylesheet" href="/public/css/components/referrals.css">
    <style>
        body { font-family: var(--font); background: var(--gray-50); margin: 0; }
        .page-header {
            background: linear-gradient(135deg, #1e40af, #0891b2);
            padding: 1.5rem 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .page-header h1 { font-size: 1.25rem; font-weight: 800; margin: 0; }
        .page-header p  { font-size: 0.8125rem; opacity: 0.85; margin: 0.25rem 0 0; }
        .page-body { max-width: 1200px; margin: 0 auto; padding: 1.75rem 1.5rem; }
        .card {
            background: var(--white);
            border: 1.5px solid var(--gray-100);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-100);
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .card-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-wrap { overflow-x: auto; }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-top: 1px solid var(--gray-100);
            background: var(--gray-50);
            font-family: var(--font);
            font-size: 0.8125rem;
            color: var(--gray-500);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .pagination-btns { display: flex; gap: 0.375rem; }
        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1.5px solid var(--gray-200);
            background: var(--white);
            font-family: var(--font);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .page-btn:hover { border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .page-btn:disabled { opacity: 0.4; cursor: default; }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .filter-select {
            padding: 0.4375rem 2rem 0.4375rem 0.75rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: var(--font);
            font-size: 0.8rem;
            color: var(--gray-700);
            background: var(--white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6,9 12,15 18,9'/%3E%3C/svg%3E") no-repeat right 0.5rem center / 14px;
            appearance: none;
            cursor: pointer;
        }
        .filter-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .search-box {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            background: var(--white);
        }
        .search-box i { padding: 0 0.625rem; color: var(--gray-400); font-size: 0.875rem; }
        .search-box input {
            border: none;
            outline: none;
            font-family: var(--font);
            font-size: 0.8375rem;
            color: var(--gray-700);
            padding: 0.4375rem 0.75rem 0.4375rem 0;
            width: 200px;
            background: transparent;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.4375rem 0.875rem;
            border-radius: 8px;
            font-family: var(--font);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }
        .btn-primary  { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-ghost    { background: transparent; color: var(--gray-600); border: 1.5px solid var(--gray-200); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    </style>
</head>
<body>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i data-lucide="users" style="width:20px;height:20px;vertical-align:middle;margin-right:0.5rem;"></i>Applicant Pipeline</h1>
        <p>Manage and review applicants for your job postings</p>
    </div>
    <a href="/employer/jobs" class="action-btn btn-ghost" style="color:white;border-color:rgba(255,255,255,0.3);">
        <i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back to Jobs
    </a>
</div>

<div class="page-body">

    <!-- Stats Row -->
    <div class="referral-stats-row" style="margin-bottom:1.5rem;">
        <div class="referral-stat-card">
            <div class="referral-stat-num"><?= number_format($stats['total'] ?? 0) ?></div>
            <div class="referral-stat-label">Total Applicants</div>
        </div>
        <div class="referral-stat-card">
            <div class="referral-stat-num blue"><?= number_format($stats['under_review'] ?? 0) ?></div>
            <div class="referral-stat-label">Under Review</div>
        </div>
        <div class="referral-stat-card">
            <div class="referral-stat-num" style="color:#7c3aed;"><?= number_format($stats['interview'] ?? 0) ?></div>
            <div class="referral-stat-label">Interviews</div>
        </div>
        <div class="referral-stat-card">
            <div class="referral-stat-num green"><?= number_format($stats['hired'] ?? 0) ?></div>
            <div class="referral-stat-label">Hired</div>
        </div>
    </div>

    <!-- Status Filter Chips -->
    <div class="ref-filter-bar" id="statusFilterBar">
        <?php
        $chips = [
            ''             => ['label' => 'All',          'count' => $stats['total']        ?? 0],
            'under_review' => ['label' => 'Under Review', 'count' => $stats['under_review'] ?? 0],
            'interview'    => ['label' => 'Interview',    'count' => $stats['interview']     ?? 0],
            'referred'     => ['label' => 'Referred',     'count' => $stats['referred']      ?? 0],
            'hired'        => ['label' => 'Hired',        'count' => $stats['hired']         ?? 0],
            'rejected'     => ['label' => 'Rejected',     'count' => $stats['rejected']      ?? 0],
        ];
        foreach ($chips as $key => $chip):
            $active = ($filter_status === $key) ? 'active' : '';
        ?>
            <button
                type="button"
                class="ref-filter-chip <?= $active ?>"
                data-filter-status="<?= htmlspecialchars($key) ?>"
            >
                <?= htmlspecialchars($chip['label']) ?>
                <span class="ref-filter-count"><?= $chip['count'] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Main Table Card -->
    <div class="card">
        <div class="card-head">
            <span class="card-title">
                <i data-lucide="list" style="width:16px;height:16px;color:var(--primary);"></i>
                Applicants
                <span style="background:var(--primary-light);color:var(--primary);font-size:0.75rem;padding:0.125rem 0.5rem;border-radius:20px;font-weight:700;">
                    <?= $total_count ?>
                </span>
            </span>
            <div class="filter-group">
                <!-- Search -->
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input
                        type="text"
                        id="applicantSearch"
                        placeholder="Search by name..."
                        value=""
                        autocomplete="off"
                    >
                </div>

                <!-- Job filter -->
                <select class="filter-select" id="jobFilter">
                    <option value="">All Jobs</option>
                    <?php foreach ($jobs as $job): ?>
                        <option value="<?= (int)$job['id'] ?>"
                            <?= $filter_job_id === (int)$job['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($job['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Sort -->
                <select class="filter-select" id="sortOrder">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name_asc">Name A–Z</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <?php if (!empty($applications)): ?>
                <table class="applicant-table" id="applicantTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" id="selectAll" style="cursor:pointer;">
                            </th>
                            <th data-sort="name">
                                Applicant
                                <span class="sort-icon"><i data-lucide="chevrons-up-down" style="width:12px;height:12px;"></i></span>
                            </th>
                            <th data-sort="job">Job Applied</th>
                            <th data-sort="date">
                                Applied
                                <span class="sort-icon"><i data-lucide="chevrons-up-down" style="width:12px;height:12px;"></i></span>
                            </th>
                            <th>Status</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="applicantTableBody">
                        <?php foreach ($applications as $app):
                            $app_id     = (int) ($app['id']           ?? 0);
                            $first      = htmlspecialchars($app['first_name']  ?? '');
                            $last       = htmlspecialchars($app['last_name']   ?? '');
                            $full_name  = trim("$first $last") ?: '—';
                            $email      = htmlspecialchars($app['email']       ?? '');
                            $job_title  = htmlspecialchars($app['job_title']   ?? '—');
                            $status     = $app['status']      ?? 'submitted';
                            $applied_at = !empty($app['applied_at'])
                                ? (new DateTime($app['applied_at']))->format('M j, Y')
                                : '—';
                            $avatar     = !empty($app['profile_photo'])
                                ? htmlspecialchars('/uploads/photos/' . $app['profile_photo'])
                                : '/public/images/default-avatar.png';
                        ?>
                            <tr
                                data-app-id="<?= $app_id ?>"
                                data-status="<?= htmlspecialchars($status) ?>"
                                onclick="window.location='/employer/applicant/<?= $app_id ?>'"
                            >
                                <td onclick="event.stopPropagation()">
                                    <input type="checkbox" class="row-check" value="<?= $app_id ?>" style="cursor:pointer;">
                                </td>
                                <td>
                                    <div class="applicant-name-cell">
                                        <img src="<?= $avatar ?>" alt="" class="applicant-avatar-sm"
                                             onerror="this.src='/public/images/default-avatar.png'">
                                        <div>
                                            <div class="applicant-name"><?= $full_name ?></div>
                                            <div class="applicant-sub"><?= $email ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= $job_title ?></td>
                                <td><?= $applied_at ?></td>
                                <td>
                                    <?php
                                    $status_include = $status;
                                    $show_bar  = false;
                                    $show_pill = true;
                                    $size      = 'sm';
                                    include __DIR__ . '/../components/hiring-status.php';
                                    ?>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <div style="display:flex;align-items:center;gap:0.375rem;">
                                        <a href="/employer/applicant/<?= $app_id ?>"
                                           style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:1.5px solid var(--gray-200);color:var(--gray-500);transition:all 0.2s;"
                                           title="View profile">
                                            <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                        </a>
                                        <?php if (!in_array($status, ['hired','rejected','withdrawn'], true)): ?>
                                            <button
                                                type="button"
                                                class="action-quick-btn"
                                                data-action="advance"
                                                data-app-id="<?= $app_id ?>"
                                                data-current-status="<?= htmlspecialchars($status) ?>"
                                                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                                title="Advance status"
                                                style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:1.5px solid var(--success);color:var(--success);background:transparent;cursor:pointer;transition:all 0.2s;"
                                                onclick="event.stopPropagation();"
                                            >
                                                <i data-lucide="arrow-right" style="width:13px;height:13px;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ref-empty" style="padding:3rem 2rem;">
                    <div class="ref-empty-icon">
                        <i data-lucide="users" style="width:28px;height:28px;"></i>
                    </div>
                    <div class="ref-empty-title">No applicants found</div>
                    <div class="ref-empty-text">
                        <?= $filter_status ? 'No applicants match this status filter.' : 'You have no applicants yet.' ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span>
                    Showing page <?= $current_page ?> of <?= $total_pages ?>
                    (<?= $total_count ?> total)
                </span>
                <div class="pagination-btns">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>&status=<?= urlencode($filter_status) ?>&job_id=<?= $filter_job_id ?>"
                           class="page-btn">
                            <i data-lucide="chevron-left" style="width:14px;height:14px;"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $current_page - 2);
                    $end   = min($total_pages, $start + 4);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                        <a href="?page=<?= $p ?>&status=<?= urlencode($filter_status) ?>&job_id=<?= $filter_job_id ?>"
                           class="page-btn <?= $p === $current_page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>&status=<?= urlencode($filter_status) ?>&job_id=<?= $filter_job_id ?>"
                           class="page-btn">
                            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bulk Action Bar (shown when rows selected) -->
    <div id="bulkActionBar" style="display:none;position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:var(--gray-900);color:white;border-radius:var(--radius-lg);padding:0.875rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:var(--shadow-lg);z-index:100;font-family:var(--font);font-size:0.875rem;">
        <span id="bulkCount" style="font-weight:700;"></span> selected
        <button type="button" id="bulkReview"
                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                style="background:var(--primary);color:white;border:none;border-radius:8px;padding:0.375rem 0.75rem;font-family:var(--font);font-size:0.8rem;font-weight:700;cursor:pointer;">
            Move to Review
        </button>
        <button type="button" id="bulkReject"
                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                style="background:#dc2626;color:white;border:none;border-radius:8px;padding:0.375rem 0.75rem;font-family:var(--font);font-size:0.8rem;font-weight:700;cursor:pointer;">
            Reject Selected
        </button>
        <button type="button" id="bulkCancel" style="background:transparent;border:none;color:rgba(255,255,255,0.6);cursor:pointer;padding:0.25rem;">
            <i data-lucide="x" style="width:15px;height:15px;"></i>
        </button>
    </div>

</div>

<script src="/public/js/hiring.js"></script>
<script> lucide.createIcons(); </script>
</body>
</html>
