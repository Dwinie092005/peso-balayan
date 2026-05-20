<?php
/**
 * View: employer/applicant-show.php
 * Location : /app/views/employer/applicant-show.php
 *
 * Full applicant profile for employer review.
 * Shows documents, skills, education, timeline, interviews, hiring actions.
 *
 * Variables from ApplicationController::employerShow():
 * @var array  $application   Full application row (joined)
 * @var array  $applicant     Applicant profile data
 * @var array  $timeline      application_timeline rows
 * @var array  $interviews    All interviews for this application
 * @var array  $referrals     Referral history (if any)
 * @var array  $skills        Applicant skills (array of strings)
 * @var array  $documents     ['type'=>string, 'path'=>string, 'label'=>string]
 * @var string $csrf_token
 */

$application = $application ?? [];
$applicant   = $applicant   ?? [];
$timeline    = $timeline    ?? [];
$interviews  = $interviews  ?? [];
$referrals   = $referrals   ?? [];
$skills      = $skills      ?? [];
$documents   = $documents   ?? [];
$csrf_token  = $csrf_token  ?? ($_SESSION['csrf_token'] ?? '');

if (empty($application)) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;text-align:center;padding:4rem;">Application not found.</p>';
    exit;
}

$app_id     = (int)   ($application['id']     ?? 0);
$status     = $application['status']           ?? 'submitted';
$applied_at = !empty($application['applied_at'])
    ? (new DateTime($application['applied_at']))->format('F j, Y')
    : '—';
$job_title  = htmlspecialchars($application['job_title']  ?? '—');
$first      = htmlspecialchars($applicant['first_name']   ?? '');
$last       = htmlspecialchars($applicant['last_name']    ?? '');
$full_name  = trim("$first $last") ?: 'Unknown Applicant';
$avatar     = !empty($applicant['profile_photo'])
    ? htmlspecialchars('/uploads/photos/' . $applicant['profile_photo'])
    : '/public/images/default-avatar.png';

$is_terminal = in_array($status, ['hired', 'rejected', 'withdrawn'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $full_name ?> — Applicant Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="/public/css/components/forms.css">
    <link rel="stylesheet" href="/public/css/components/hiring.css">
    <link rel="stylesheet" href="/public/css/components/referrals.css">
    <style>
        body { font-family: var(--font); background: var(--gray-50); margin: 0; }
        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--gray-100);
            padding: 0.875rem 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            color: var(--gray-500);
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
        }
        .back-link:hover { color: var(--primary); }
        .breadcrumb-sep { color: var(--gray-300); }
        .topbar-title { font-size: 0.9375rem; font-weight: 700; color: var(--gray-800); }
        .page-body { max-width: 1100px; margin: 0 auto; padding: 1.75rem 1.5rem 4rem; }
        .skill-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            background: var(--primary-light);
            color: var(--primary-dark);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(37,99,235,0.2);
        }
        .edu-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px solid var(--gray-50);
        }
        .edu-item:last-child { border-bottom: none; }
        .edu-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--teal-light);
            color: var(--teal);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .edu-label { font-size: 0.875rem; font-weight: 700; color: var(--gray-800); }
        .edu-sub   { font-size: 0.8rem; color: var(--gray-400); margin-top: 1px; }
    </style>
</head>
<body>

<!-- Topbar breadcrumb -->
<div class="topbar">
    <a href="/employer/applicants" class="back-link">
        <i data-lucide="arrow-left" style="width:14px;height:14px;"></i>
        Applicants
    </a>
    <span class="breadcrumb-sep">/</span>
    <span class="topbar-title"><?= htmlspecialchars($full_name) ?></span>
    <div style="margin-left:auto;">
        <?php
        $show_pill = true; $show_bar = false; $size = 'md';
        include __DIR__ . '/../components/hiring-status.php';
        ?>
    </div>
</div>

<div class="page-body">

    <!-- Pipeline Progress -->
    <div style="background:var(--white);border:1.5px solid var(--gray-100);border-radius:var(--radius-lg);padding:1.125rem 1.5rem;margin-bottom:1.5rem;">
        <div style="font-size:0.75rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">
            Application Progress
        </div>
        <?php
        $show_pill = false; $show_bar = true; $size = 'md';
        include __DIR__ . '/../components/hiring-status.php';
        ?>
        <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--gray-400);">
            Applied for <strong style="color:var(--gray-700);"><?= $job_title ?></strong>
            on <?= $applied_at ?>
        </div>
    </div>

    <!-- Profile + Content Grid -->
    <div class="applicant-profile-grid">

        <!-- Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-sidebar-header">
                <img src="<?= $avatar ?>" alt="<?= htmlspecialchars($full_name) ?>"
                     class="profile-avatar-lg"
                     onerror="this.src='/public/images/default-avatar.png'">
                <div class="profile-name-lg"><?= htmlspecialchars($full_name) ?></div>
                <div class="profile-role-lg">
                    <?= htmlspecialchars($applicant['employment_status'] ?? 'Applicant') ?>
                </div>
            </div>
            <div class="profile-sidebar-body">
                <?php
                $info_rows = [
                    ['icon' => 'mail',     'label' => 'Email',    'value' => $applicant['email']          ?? '—'],
                    ['icon' => 'phone',    'label' => 'Mobile',   'value' => $applicant['contact_number'] ?? '—'],
                    ['icon' => 'map-pin',  'label' => 'Address',  'value' => trim(
                        ($applicant['city_municipality'] ?? '') . ', ' .
                        ($applicant['province']          ?? '')
                    , ', ')                                                                                     ],
                    ['icon' => 'calendar', 'label' => 'Birthday', 'value' => !empty($applicant['birthdate'])
                        ? (new DateTime($applicant['birthdate']))->format('F j, Y') : '—'],
                    ['icon' => 'user',     'label' => 'Gender',   'value' => $applicant['gender']         ?? '—'],
                    ['icon' => 'heart',    'label' => 'Civil',    'value' => $applicant['civil_status']   ?? '—'],
                ];
                foreach ($info_rows as $row):
                    $display = htmlspecialchars($row['value'] ?: '—');
                ?>
                    <div class="profile-info-item">
                        <div class="info-icon">
                            <i data-lucide="<?= htmlspecialchars($row['icon']) ?>" style="width:14px;height:14px;"></i>
                        </div>
                        <div>
                            <div class="profile-info-label"><?= htmlspecialchars($row['label']) ?></div>
                            <div class="profile-info-value"><?= $display ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Expected Salary -->
                <?php if (!empty($application['expected_salary'])): ?>
                    <div class="profile-info-item">
                        <div class="info-icon">
                            <i data-lucide="banknote" style="width:14px;height:14px;"></i>
                        </div>
                        <div>
                            <div class="profile-info-label">Expected Salary</div>
                            <div class="profile-info-value">
                                ₱<?= number_format((float)$application['expected_salary']) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="profile-content">

            <!-- Hiring Action Bar -->
            <?php if (!$is_terminal): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon">
                                <i data-lucide="zap" style="width:13px;height:13px;"></i>
                            </div>
                            Hiring Actions
                        </div>
                    </div>
                    <div class="hiring-action-bar">
                        <!-- Advance to next status -->
                        <?php
                        $next_map = [
                            'submitted'    => ['status' => 'under_review', 'label' => 'Move to Review',    'icon' => 'eye',           'class' => 'btn-primary'],
                            'under_review' => ['status' => 'interview',    'label' => 'Schedule Interview', 'icon' => 'calendar-plus', 'class' => 'btn-primary'],
                            'matched'      => ['status' => 'interview',    'label' => 'Schedule Interview', 'icon' => 'calendar-plus', 'class' => 'btn-primary'],
                            'referred'     => ['status' => 'interview',    'label' => 'Schedule Interview', 'icon' => 'calendar-plus', 'class' => 'btn-primary'],
                            'interview'    => ['status' => 'hired',        'label' => 'Mark as Hired',      'icon' => 'check-circle',  'class' => 'btn-success'],
                        ];
                        $next = $next_map[$status] ?? null;
                        if ($next):
                        ?>
                            <button
                                type="button"
                                class="action-btn <?= $next['class'] ?>"
                                data-action="<?= $next['status'] === 'interview' ? 'schedule-interview' : 'update-status' ?>"
                                data-app-id="<?= $app_id ?>"
                                data-new-status="<?= htmlspecialchars($next['status']) ?>"
                                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                id="primaryActionBtn"
                            >
                                <i data-lucide="<?= htmlspecialchars($next['icon']) ?>" style="width:14px;height:14px;"></i>
                                <?= htmlspecialchars($next['label']) ?>
                            </button>
                        <?php endif; ?>

                        <!-- Shortlist -->
                        <?php if ($status === 'submitted'): ?>
                            <button type="button"
                                    class="action-btn btn-outline-primary"
                                    data-action="update-status"
                                    data-app-id="<?= $app_id ?>"
                                    data-new-status="under_review"
                                    data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                    style="background:var(--white);border:1.5px solid var(--primary);color:var(--primary);">
                                <i data-lucide="bookmark" style="width:14px;height:14px;"></i>
                                Shortlist
                            </button>
                        <?php endif; ?>

                        <!-- Reject -->
                        <button
                            type="button"
                            class="action-btn"
                            data-action="update-status"
                            data-app-id="<?= $app_id ?>"
                            data-new-status="rejected"
                            data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                            style="background:var(--white);border:1.5px solid var(--danger);color:var(--danger);margin-left:auto;"
                            id="rejectBtn"
                        >
                            <i data-lucide="x-circle" style="width:14px;height:14px;"></i>
                            Reject
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div style="background:<?= $status === 'hired' ? 'var(--success-light)' : 'var(--danger-light)' ?>;border-radius:var(--radius-lg);padding:1rem 1.25rem;font-family:var(--font);font-size:0.8375rem;font-weight:700;color:<?= $status === 'hired' ? '#065f46' : '#991b1b' ?>;display:flex;align-items:center;gap:0.5rem;">
                    <i data-lucide="<?= $status === 'hired' ? 'check-circle' : ($status === 'withdrawn' ? 'minus-circle' : 'x-circle') ?>" style="width:16px;height:16px;"></i>
                    This application is <?= htmlspecialchars(ucfirst($status)) ?>.
                </div>
            <?php endif; ?>

            <!-- Education -->
            <div class="profile-section">
                <div class="profile-section-head">
                    <div class="profile-section-title">
                        <div class="section-icon"><i data-lucide="book-open" style="width:13px;height:13px;"></i></div>
                        Education
                    </div>
                </div>
                <div class="profile-section-body">
                    <div class="edu-item">
                        <div class="edu-icon"><i data-lucide="graduation-cap" style="width:16px;height:16px;"></i></div>
                        <div>
                            <div class="edu-label"><?= htmlspecialchars($applicant['school_name'] ?? '—') ?></div>
                            <div class="edu-sub">
                                <?= htmlspecialchars($applicant['course_degree'] ?? '') ?>
                                <?php if (!empty($applicant['year_graduated'])): ?>
                                    · Graduated <?= (int)$applicant['year_graduated'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="edu-sub" style="margin-top:3px;">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $applicant['highest_education'] ?? ''))) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skills -->
            <?php if (!empty($skills)): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="zap" style="width:13px;height:13px;"></i></div>
                            Skills (<?= count($skills) ?>)
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                            <?php foreach ($skills as $skill): ?>
                                <span class="skill-chip"><?= htmlspecialchars($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($applicant['other_qualifications'])): ?>
                            <div style="margin-top:0.875rem;padding:0.75rem;background:var(--gray-50);border-radius:var(--radius);font-size:0.8125rem;color:var(--gray-600);line-height:1.6;">
                                <strong style="display:block;font-size:0.75rem;color:var(--gray-400);margin-bottom:0.25rem;">Other Qualifications:</strong>
                                <?= nl2br(htmlspecialchars($applicant['other_qualifications'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Documents -->
            <?php if (!empty($documents)): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="folder" style="width:13px;height:13px;"></i></div>
                            Documents (<?= count($documents) ?>)
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div class="doc-list">
                            <?php foreach ($documents as $doc):
                                $ext    = strtolower(pathinfo($doc['path'] ?? '', PATHINFO_EXTENSION));
                                $icon_c = in_array($ext, ['jpg','jpeg','png','gif'], true) ? 'img'
                                        : ($ext === 'pdf' ? 'pdf' : 'doc');
                                $icon_n = $icon_c === 'pdf' ? 'file-text' : ($icon_c === 'img' ? 'image' : 'file');
                            ?>
                                <a href="<?= htmlspecialchars('/uploads/docs/' . $doc['path']) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   class="doc-item" style="text-decoration:none;">
                                    <div class="doc-icon <?= $icon_c ?>">
                                        <i data-lucide="<?= $icon_n ?>" style="width:15px;height:15px;"></i>
                                    </div>
                                    <div class="doc-name"><?= htmlspecialchars($doc['label'] ?? $doc['path']) ?></div>
                                    <div class="doc-size">
                                        <i data-lucide="external-link" style="width:12px;height:12px;"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cover Letter -->
            <?php if (!empty($application['cover_letter'])): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="file-text" style="width:13px;height:13px;"></i></div>
                            Cover Letter
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div style="font-size:0.875rem;color:var(--gray-700);line-height:1.75;white-space:pre-line;">
                            <?= htmlspecialchars($application['cover_letter']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Interview Schedule -->
            <?php if (!empty($interviews)): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="calendar" style="width:13px;height:13px;"></i></div>
                            Interviews (<?= count($interviews) ?>)
                        </div>
                        <?php if (!$is_terminal): ?>
                            <button type="button"
                                    class="action-btn btn-ghost"
                                    data-action="schedule-interview"
                                    data-app-id="<?= $app_id ?>"
                                    style="padding:0.375rem 0.75rem;font-size:0.8rem;border:1.5px solid var(--gray-200);">
                                <i data-lucide="plus" style="width:13px;height:13px;"></i>
                                Add Interview
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="profile-section-body" style="display:flex;flex-direction:column;gap:0.75rem;">
                        <?php foreach ($interviews as $interview):
                            $interview['applicant_name'] = $full_name;
                            $interview['job_title']      = $application['job_title'] ?? '';
                            $show_actions = !$is_terminal;
                            include __DIR__ . '/../components/interview-schedule.php';
                        endforeach; ?>
                    </div>
                </div>
            <?php elseif (!$is_terminal && $status === 'interview'): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="calendar" style="width:13px;height:13px;"></i></div>
                            Interview
                        </div>
                    </div>
                    <div class="profile-section-body" style="text-align:center;padding:1.5rem;">
                        <button type="button"
                                class="action-btn btn-primary"
                                data-action="schedule-interview"
                                data-app-id="<?= $app_id ?>">
                            <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i>
                            Schedule Interview
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Application Timeline -->
            <?php if (!empty($timeline)): ?>
                <div class="profile-section">
                    <div class="profile-section-head">
                        <div class="profile-section-title">
                            <div class="section-icon"><i data-lucide="clock" style="width:13px;height:13px;"></i></div>
                            Application Timeline
                        </div>
                    </div>
                    <div class="profile-section-body">
                        <div class="referral-timeline" id="appTimeline">
                            <?php foreach ($timeline as $event):
                                $ev_status = $event['status']     ?? 'submitted';
                                $ev_note   = $event['note']       ?? '';
                                $ev_actor  = trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? ''));
                                $ev_role   = ucfirst($event['role'] ?? '');
                                $ev_time   = !empty($event['created_at'])
                                    ? (new DateTime($event['created_at']))->format('M j, Y g:i A')
                                    : '';
                            ?>
                                <div class="ref-timeline-item <?= htmlspecialchars($ev_status) ?>">
                                    <div class="ref-timeline-dot"></div>
                                    <div class="ref-timeline-content">
                                        <div class="ref-timeline-label">
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $ev_status))) ?>
                                        </div>
                                        <?php if ($ev_note): ?>
                                            <div class="ref-timeline-detail"><?= htmlspecialchars($ev_note) ?></div>
                                        <?php endif; ?>
                                        <div class="ref-timeline-time">
                                            <?= htmlspecialchars($ev_actor) ?>
                                            <?= $ev_role ? "($ev_role)" : '' ?>
                                            <?= $ev_time ? "· $ev_time" : '' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div><!-- /profile-content -->
    </div><!-- /grid -->
</div>

<!-- Interview Scheduler Modal (populated by interview-scheduler.js) -->
<div id="interviewModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div id="interviewModalBox" style="background:var(--white);border-radius:var(--radius-lg);padding:1.75rem;width:100%;max-width:480px;margin:1rem;box-shadow:var(--shadow-lg);"></div>
</div>

<!-- Confirm Reject Modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius-lg);padding:1.75rem;width:100%;max-width:400px;margin:1rem;box-shadow:var(--shadow-lg);">
        <h3 style="font-family:var(--font);font-size:1rem;font-weight:800;color:var(--gray-800);margin:0 0 0.75rem;">Reject this application?</h3>
        <p style="font-family:var(--font);font-size:0.875rem;color:var(--gray-500);margin:0 0 1.25rem;line-height:1.6;">
            This action cannot be undone. The applicant will be notified.
        </p>
        <div style="display:flex;gap:0.625rem;justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('rejectModal').style.display='none';"
                    style="background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;padding:0.5rem 1rem;font-family:var(--font);font-size:0.875rem;font-weight:600;cursor:pointer;">
                Cancel
            </button>
            <button type="button" id="confirmRejectBtn"
                    data-app-id="<?= $app_id ?>"
                    data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                    style="background:var(--danger);color:white;border:none;border-radius:8px;padding:0.5rem 1.25rem;font-family:var(--font);font-size:0.875rem;font-weight:700;cursor:pointer;">
                Yes, Reject
            </button>
        </div>
    </div>
</div>

<script src="/public/js/hiring.js"></script>
<script src="/public/js/interview-scheduler.js"></script>
<script>
    lucide.createIcons();
    // Pass PHP data to JS context
    window.NSRP = window.NSRP || {};
    window.NSRP.applicationId = <?= $app_id ?>;
    window.NSRP.csrfToken     = <?= json_encode($csrf_token) ?>;
    window.NSRP.currentStatus = <?= json_encode($status) ?>;
</script>
</body>
</html>
