<?php
/**
 * View: admin/referrals.php
 * Location : /app/views/admin/referrals.php
 *
 * Admin referral management dashboard.
 * Lists all referrals with filtering, status management,
 * employer assignment, and pagination.
 *
 * Variables from ReferralController::adminIndex():
 * @var array  $referrals      Paginated referral rows (joined)
 * @var array  $employers      All employer records (for assignment dropdown)
 * @var array  $stats          Referral counts by status
 * @var int    $total_count
 * @var int    $current_page
 * @var int    $total_pages
 * @var string $filter_status  Active status filter
 * @var string $filter_search  Active search string
 * @var string $csrf_token
 */

$referrals     = $referrals     ?? [];
$employers     = $employers     ?? [];
$stats         = $stats         ?? [];
$total_count   = $total_count   ?? 0;
$current_page  = $current_page  ?? 1;
$total_pages   = $total_pages   ?? 1;
$filter_status = $filter_status ?? '';
$filter_search = $filter_search ?? '';
$csrf_token    = $csrf_token    ?? ($_SESSION['csrf_token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="/public/css/components/forms.css">
    <link rel="stylesheet" href="/public/css/components/referrals.css">
    <link rel="stylesheet" href="/public/css/components/hiring.css">
    <style>
        body { font-family: var(--font); background: var(--gray-50); margin: 0; }
        .page-header {
            background: linear-gradient(135deg, #1e40af, #0891b2);
            padding: 1.5rem 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .page-header h1 { font-size: 1.25rem; font-weight: 800; margin: 0; }
        .page-header p  { font-size: 0.8125rem; opacity: 0.85; margin: 0.25rem 0 0; }
        .page-body { max-width: 1200px; margin: 0 auto; padding: 1.75rem 1.5rem 4rem; }
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
        .card-title { font-size: 0.9375rem; font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem; }
        .filter-row { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
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
        .filter-select:focus { outline: none; border-color: var(--primary); }
        .search-box {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            background: var(--white);
            overflow: hidden;
        }
        .search-box i   { padding: 0 0.625rem; color: var(--gray-400); flex-shrink: 0; }
        .search-box input {
            border: none;
            outline: none;
            font-family: var(--font);
            font-size: 0.8375rem;
            color: var(--gray-700);
            padding: 0.4375rem 0.75rem 0.4375rem 0;
            width: 220px;
            background: transparent;
        }
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.25rem;
            border-top: 1px solid var(--gray-100);
            background: var(--gray-50);
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
            min-width: 32px;
            height: 32px;
            padding: 0 6px;
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
        .page-btn:hover  { border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .ref-list-body   { padding: 1.25rem; display: flex; flex-direction: column; gap: 0.875rem; }
        .assign-select {
            padding: 0.3125rem 1.75rem 0.3125rem 0.625rem;
            border: 1.5px solid var(--gray-200);
            border-radius: 7px;
            font-family: var(--font);
            font-size: 0.75rem;
            color: var(--gray-700);
            appearance: none;
            background: var(--white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6,9 12,15 18,9'/%3E%3C/svg%3E") no-repeat right 0.375rem center / 12px;
            cursor: pointer;
            max-width: 180px;
        }
        .assign-select:focus { outline: none; border-color: var(--primary); }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-family: var(--font);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }
        .btn-primary  { background: var(--primary); color: white; }
        .btn-ghost    { background: var(--white); color: var(--gray-600); border: 1.5px solid var(--gray-200); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .skeleton {
            background: linear-gradient(90deg, var(--gray-100) 25%, var(--gray-50) 50%, var(--gray-100) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 6px;
        }
        @keyframes shimmer { from{background-position:200% 0} to{background-position:-200% 0} }
    </style>
</head>
<body>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>
            <i data-lucide="share-2" style="width:20px;height:20px;vertical-align:middle;margin-right:0.5rem;"></i>
            Referral Management
        </h1>
        <p>Review, assign, and track all employment referrals</p>
    </div>
    <div style="display:flex;gap:0.625rem;">
        <button type="button"
                id="exportBtn"
                class="action-btn btn-ghost"
                style="color:white;border-color:rgba(255,255,255,0.3);">
            <i data-lucide="download" style="width:14px;height:14px;"></i>
            Export CSV
        </button>
    </div>
</div>

<div class="page-body">

    <!-- Summary Stats -->
    <div class="referral-stats-row">
        <?php
        $stat_config = [
            ['key'=>'total',    'label'=>'Total Referrals', 'color'=>''],
            ['key'=>'pending',  'label'=>'Pending',          'color'=>''],
            ['key'=>'endorsed', 'label'=>'Endorsed',         'color'=>'blue'],
            ['key'=>'accepted', 'label'=>'Accepted',         'color'=>'green'],
            ['key'=>'hired',    'label'=>'Hired',            'color'=>'purple'],
            ['key'=>'rejected', 'label'=>'Not Accepted',     'color'=>'red'],
        ];
        foreach ($stat_config as $sc):
            $count = (int) ($stats[$sc['key']] ?? 0);
        ?>
            <div class="referral-stat-card">
                <div class="referral-stat-num <?= $sc['color'] ?>"><?= number_format($count) ?></div>
                <div class="referral-stat-label"><?= htmlspecialchars($sc['label']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Status Filter Chips -->
    <div class="ref-filter-bar" id="refFilterBar">
        <?php
        $chip_filters = [
            ''         => 'All',
            'pending'  => 'Pending',
            'endorsed' => 'Endorsed',
            'accepted' => 'Accepted',
            'hired'    => 'Hired',
            'rejected' => 'Rejected',
            'no_show'  => 'No Show',
        ];
        foreach ($chip_filters as $key => $label):
            $active = ($filter_status === $key) ? 'active' : '';
            $count  = (int) ($stats[$key === '' ? 'total' : $key] ?? 0);
        ?>
            <button
                type="button"
                class="ref-filter-chip <?= $active ?>"
                data-status="<?= htmlspecialchars($key) ?>"
            >
                <?= htmlspecialchars($label) ?>
                <span class="ref-filter-count"><?= $count ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-head">
            <span class="card-title">
                <i data-lucide="list" style="width:16px;height:16px;color:var(--primary);"></i>
                Referrals
                <span style="background:var(--primary-light);color:var(--primary);font-size:0.75rem;padding:0.125rem 0.5rem;border-radius:20px;font-weight:700;">
                    <?= $total_count ?>
                </span>
            </span>

            <div class="filter-row">
                <!-- Search -->
                <div class="search-box">
                    <i data-lucide="search" style="width:14px;height:14px;"></i>
                    <input
                        type="text"
                        id="refSearch"
                        placeholder="Search applicant or employer..."
                        value="<?= htmlspecialchars($filter_search) ?>"
                        autocomplete="off"
                    >
                </div>

                <!-- Date range -->
                <input type="date" id="dateFrom" class="filter-select"
                       style="width:140px;" placeholder="From" aria-label="From date">
                <input type="date" id="dateTo"   class="filter-select"
                       style="width:140px;" placeholder="To"   aria-label="To date">
            </div>
        </div>

        <!-- Referral List -->
        <div class="ref-list-body" id="referralListBody">
            <?php if (!empty($referrals)): ?>
                <?php foreach ($referrals as $ref):
                    $ref_id       = (int)   ($ref['id']            ?? 0);
                    $ref_status   = $ref['status']                 ?? 'pending';
                    $applicant    = trim(($ref['first_name'] ?? '') . ' ' . ($ref['last_name'] ?? ''));
                    $avatar       = !empty($ref['profile_photo'])
                        ? htmlspecialchars('/uploads/photos/' . $ref['profile_photo'])
                        : '/public/images/default-avatar.png';
                    $job_title    = htmlspecialchars($ref['job_title']      ?? '—');
                    $employer     = htmlspecialchars($ref['company_name']   ?? 'Unassigned');
                    $ref_date     = !empty($ref['created_at'])
                        ? (new DateTime($ref['created_at']))->format('M j, Y')
                        : '—';
                    $notes        = $ref['notes'] ?? '';
                    $employer_id  = (int) ($ref['employer_id'] ?? 0);
                ?>
                    <div class="referral-card <?= htmlspecialchars($ref_status) ?>"
                         id="ref-<?= $ref_id ?>"
                         data-ref-id="<?= $ref_id ?>">

                        <div class="referral-card-head">
                            <!-- Applicant -->
                            <div class="referral-card-applicant">
                                <img src="<?= $avatar ?>" alt=""
                                     class="referral-card-avatar"
                                     onerror="this.src='/public/images/default-avatar.png'">
                                <div>
                                    <div class="referral-card-name"><?= htmlspecialchars($applicant) ?></div>
                                    <div class="referral-card-meta"><?= htmlspecialchars($ref['email'] ?? '') ?></div>
                                </div>
                            </div>

                            <!-- Status badge -->
                            <span class="ref-badge <?= htmlspecialchars($ref_status) ?>">
                                <span class="ref-badge-dot"></span>
                                <?= htmlspecialchars(ucfirst($ref_status)) ?>
                            </span>
                        </div>

                        <!-- Info grid -->
                        <div class="referral-card-body">
                            <div class="referral-card-field">
                                <div class="referral-card-field-label">Job Position</div>
                                <div class="referral-card-field-value"><?= $job_title ?></div>
                            </div>
                            <div class="referral-card-field">
                                <div class="referral-card-field-label">Employer</div>
                                <div class="referral-card-field-value"><?= $employer ?></div>
                            </div>
                            <div class="referral-card-field">
                                <div class="referral-card-field-label">Referred On</div>
                                <div class="referral-card-field-value"><?= $ref_date ?></div>
                            </div>
                            <div class="referral-card-field">
                                <div class="referral-card-field-label">Ref. Code</div>
                                <div class="referral-card-field-value" style="font-family:monospace;font-size:0.8rem;">
                                    <?= htmlspecialchars($ref['referral_code'] ?? '—') ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($notes): ?>
                            <div class="interview-notes" style="margin:0 0 0.75rem;">
                                <?= htmlspecialchars($notes) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="referral-card-actions">
                            <!-- Assign/reassign employer -->
                            <select
                                class="assign-select"
                                data-ref-id="<?= $ref_id ?>"
                                data-action="assign-employer"
                                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                aria-label="Assign employer"
                            >
                                <option value="">-- Assign Employer --</option>
                                <?php foreach ($employers as $emp): ?>
                                    <option value="<?= (int)$emp['id'] ?>"
                                        <?= $employer_id === (int)$emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Status actions -->
                            <?php if ($ref_status === 'pending'): ?>
                                <button type="button"
                                        class="action-btn btn-primary"
                                        data-action="update-referral-status"
                                        data-ref-id="<?= $ref_id ?>"
                                        data-new-status="endorsed"
                                        data-csrf="<?= htmlspecialchars($csrf_token) ?>">
                                    <i data-lucide="send" style="width:13px;height:13px;"></i>
                                    Endorse
                                </button>
                            <?php elseif ($ref_status === 'endorsed'): ?>
                                <button type="button"
                                        class="action-btn btn-ghost"
                                        style="border-color:var(--success);color:var(--success);"
                                        data-action="update-referral-status"
                                        data-ref-id="<?= $ref_id ?>"
                                        data-new-status="accepted"
                                        data-csrf="<?= htmlspecialchars($csrf_token) ?>">
                                    <i data-lucide="check" style="width:13px;height:13px;"></i>
                                    Mark Accepted
                                </button>
                            <?php endif; ?>

                            <!-- Note button -->
                            <button type="button"
                                    class="action-btn btn-ghost"
                                    data-action="add-ref-note"
                                    data-ref-id="<?= $ref_id ?>"
                                    style="margin-left:auto;">
                                <i data-lucide="message-square" style="width:13px;height:13px;"></i>
                                Note
                            </button>

                            <!-- View applicant -->
                            <a href="/admin/applicant/<?= (int)($ref['applicant_id'] ?? 0) ?>"
                               class="action-btn btn-ghost">
                                <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ref-empty">
                    <div class="ref-empty-icon">
                        <i data-lucide="share-2" style="width:28px;height:28px;"></i>
                    </div>
                    <div class="ref-empty-title">No referrals found</div>
                    <div class="ref-empty-text">
                        <?= $filter_status
                            ? 'No referrals match this filter.'
                            : 'Referrals will appear here once applications are processed.'
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span>
                    Page <?= $current_page ?> of <?= $total_pages ?>
                    &mdash; <?= $total_count ?> referrals
                </span>
                <div class="pagination-btns">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>"
                           class="page-btn">
                            <i data-lucide="chevron-left" style="width:14px;height:14px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $current_page - 2);
                    $end   = min($total_pages, $start + 4);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                        <a href="?page=<?= $p ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>"
                           class="page-btn <?= $p === $current_page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($filter_search) ?>"
                           class="page-btn">
                            <i data-lucide="chevron-right" style="width:14px;height:14px;"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Note Modal -->
<div id="noteModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius-lg);padding:1.75rem;width:100%;max-width:440px;margin:1rem;box-shadow:var(--shadow-lg);">
        <h3 style="font-family:var(--font);font-size:1rem;font-weight:800;color:var(--gray-800);margin:0 0 0.875rem;">
            Add Note
        </h3>
        <textarea id="noteText" rows="4" placeholder="Enter note..."
                  style="width:100%;border:1.5px solid var(--gray-200);border-radius:var(--radius);padding:0.625rem;font-family:var(--font);font-size:0.875rem;resize:vertical;outline:none;box-sizing:border-box;"></textarea>
        <div style="display:flex;gap:0.625rem;justify-content:flex-end;margin-top:1rem;">
            <button type="button" onclick="document.getElementById('noteModal').style.display='none';"
                    style="background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;padding:0.5rem 1rem;font-family:var(--font);font-size:0.875rem;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button type="button" id="saveNoteBtn"
                    data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                    style="background:var(--primary);color:white;border:none;border-radius:8px;padding:0.5rem 1.25rem;font-family:var(--font);font-size:0.875rem;font-weight:700;cursor:pointer;">
                Save Note
            </button>
        </div>
    </div>
</div>

<script src="/public/js/referrals.js"></script>
<script>
    lucide.createIcons();
    window.NSRP = window.NSRP || {};
    window.NSRP.csrfToken = <?= json_encode($csrf_token) ?>;
</script>
</body>
</html>
