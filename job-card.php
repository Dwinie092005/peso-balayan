<?php
/**
 * PESO Balayan IMIS — Job Card Component
 * File: app/views/components/job-card.php
 *
 * Variables expected:
 *   $job        array  — job row with employer + category cols joined
 *   $role       string — current user role
 *   $csrfToken  string — CSRF token for action forms
 *
 * Optional per-job flags (pre-computed by controller):
 *   $job['is_saved']    bool
 *   $job['has_applied'] bool
 */

// ── Helpers ──────────────────────────────────────────────────
$jId       = (int)$job['id'];
$title     = htmlspecialchars($job['title']        ?? '',  ENT_QUOTES, 'UTF-8');
$company   = htmlspecialchars($job['company_name'] ?? '',  ENT_QUOTES, 'UTF-8');
$city      = htmlspecialchars($job['location_city']?? '',  ENT_QUOTES, 'UTF-8');
$province  = htmlspecialchars($job['location_province'] ?? '', ENT_QUOTES, 'UTF-8');
$location  = implode(', ', array_filter([$city, $province])) ?: 'Location not specified';
$type      = $job['employment_type'] ?? 'full_time';
$typeLabel = \App\Models\JobModel::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
$status    = $job['status'] ?? 'active';
$slots     = (int)($job['slots'] ?? 1);
$appCount  = (int)($job['app_count'] ?? 0);
$isSaved   = !empty($job['is_saved']);
$hasApplied= !empty($job['has_applied']);

// Salary display
if (!empty($job['salary_negotiable'])) {
    $salaryText = 'Negotiable';
} elseif (!empty($job['salary_min']) && !empty($job['salary_max'])) {
    $salaryText = '₱' . number_format($job['salary_min']) . ' – ₱' . number_format($job['salary_max']);
} elseif (!empty($job['salary_min'])) {
    $salaryText = 'From ₱' . number_format($job['salary_min']);
} else {
    $salaryText = 'Not specified';
}

// Education display
$eduLabel = \App\Models\JobModel::EDUCATION_LABELS[$job['education_required'] ?? 'none'] ?? '';

// Posted date (relative)
$postedTs   = strtotime($job['created_at'] ?? 'now');
$diffDays   = (int)floor((time() - $postedTs) / 86400);
$postedText = match(true) {
    $diffDays === 0 => 'Today',
    $diffDays === 1 => 'Yesterday',
    $diffDays < 7   => "{$diffDays}d ago",
    $diffDays < 30  => floor($diffDays / 7) . 'w ago',
    default         => date('M j', $postedTs),
};

// Company logo initials
$logoText = strtoupper(mb_substr(preg_replace('/[^a-zA-Z\s]/', '', $company), 0, 1)
            ?: mb_substr($title, 0, 1));
$logoFile = $job['logo_path'] ?? null;

// Skills (expect pre-joined string or array; handle both)
$skills = isset($job['skills']) && is_array($job['skills'])
    ? $job['skills']
    : [];

$displaySkills = array_slice($skills, 0, 4);
$extraSkills   = count($skills) - count($displaySkills);

// Action URLs
$detailUrl  = htmlspecialchars('/jobs/' . $jId, ENT_QUOTES, 'UTF-8');
$editUrl    = htmlspecialchars('/employer/jobs/' . $jId . '/edit', ENT_QUOTES, 'UTF-8');
$adminEdit  = htmlspecialchars('/admin/jobs/' . $jId . '/edit', ENT_QUOTES, 'UTF-8');
$appsUrl    = htmlspecialchars('/admin/jobs/' . $jId . '/applications', ENT_QUOTES, 'UTF-8');
$empAppsUrl = htmlspecialchars('/employer/jobs/' . $jId . '/applications', ENT_QUOTES, 'UTF-8');
$csrfSafe   = htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8');

$cardClass = 'job-card' . ($status !== 'active' ? ' closed' : '');
?>

<div class="<?= $cardClass ?>"
     data-job-id="<?= $jId ?>"
     data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ── Header: logo + company + bookmark ─────────────── -->
    <div class="job-card-header">
        <div class="job-card-company-info">
            <div class="job-card-logo">
                <?php if ($logoFile && file_exists(BASE_PATH . '/uploads/logos/' . $logoFile)): ?>
                    <img src="<?= BASE_URL ?>/uploads/logos/<?= htmlspecialchars($logoFile, ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= $company ?> logo">
                <?php else: ?>
                    <?= $logoText ?>
                <?php endif; ?>
            </div>
            <div style="min-width:0;">
                <p class="job-card-company-name" title="<?= $company ?>"><?= $company ?></p>
                <p class="job-card-location">
                    <i class="ti ti-map-pin" aria-hidden="true"></i>
                    <?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <?php if ($role === 'applicant'): ?>
            <!-- Bookmark toggle -->
            <button class="job-card-bookmark <?= $isSaved ? 'saved' : '' ?>"
                    data-job-id="<?= $jId ?>"
                    data-csrf="<?= $csrfSafe ?>"
                    title="<?= $isSaved ? 'Remove bookmark' : 'Save job' ?>"
                    aria-label="<?= $isSaved ? 'Remove bookmark' : 'Bookmark this job' ?>">
                <i class="ti <?= $isSaved ? 'ti-bookmark-filled' : 'ti-bookmark' ?>"
                   aria-hidden="true"></i>
            </button>
        <?php elseif (in_array($role, ['admin', 'super_admin', 'employer'], true)): ?>
            <!-- Status badge for management view -->
            <span class="job-card-badge status status-<?= $status ?>">
                <?= ucfirst($status) ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- ── Title ──────────────────────────────────────────── -->
    <a href="<?= $detailUrl ?>" class="job-card-title">
        <?= $title ?>
    </a>

    <!-- ── Meta badges ────────────────────────────────────── -->
    <div class="job-card-meta">
        <span class="job-card-badge type-<?= $type ?>">
            <i class="ti ti-clock" aria-hidden="true"></i>
            <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?>
        </span>

        <?php if ($salaryText !== 'Not specified'): ?>
            <span class="job-card-badge salary">
                <i class="ti ti-currency-peso" aria-hidden="true"></i>
                <?= htmlspecialchars($salaryText, ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endif; ?>

        <?php if ($eduLabel && $eduLabel !== 'No Requirement'): ?>
            <span class="job-card-badge edu">
                <i class="ti ti-school" aria-hidden="true"></i>
                <?= htmlspecialchars($eduLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endif; ?>

        <?php if (!empty($job['experience_years'])): ?>
            <span class="job-card-badge exp">
                <i class="ti ti-award" aria-hidden="true"></i>
                <?= (int)$job['experience_years'] ?>yr+ exp
            </span>
        <?php endif; ?>
    </div>

    <!-- ── Skill tags ─────────────────────────────────────── -->
    <?php if (!empty($displaySkills)): ?>
        <div class="job-card-skills">
            <?php foreach ($displaySkills as $sk): ?>
                <span class="job-skill-tag">
                    <?= htmlspecialchars($sk['name'] ?? $sk, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endforeach; ?>
            <?php if ($extraSkills > 0): ?>
                <span class="job-skill-more">+<?= $extraSkills ?> more</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="job-card-skills"></div>
    <?php endif; ?>

    <!-- ── Footer: slots + actions ────────────────────────── -->
    <div class="job-card-footer">

        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span class="job-card-slots <?= $slots <= 2 ? 'low' : '' ?>">
                <i class="ti ti-users" aria-hidden="true"></i>
                <?= $slots ?> slot<?= $slots !== 1 ? 's' : '' ?>
            </span>
            <span class="job-card-posted"><?= $postedText ?></span>
        </div>

        <!-- Applicant actions -->
        <?php if ($role === 'applicant'): ?>
            <?php if ($hasApplied): ?>
                <span class="btn-card-applied">
                    <i class="ti ti-check" aria-hidden="true"></i> Applied
                </span>
            <?php elseif ($status === 'active'): ?>
                <a href="<?= $detailUrl ?>" class="btn-card-apply">
                    Apply <i class="ti ti-arrow-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="badge badge-inactive">Closed</span>
            <?php endif; ?>

        <!-- Employer actions -->
        <?php elseif ($role === 'employer'): ?>
            <div style="display:flex;gap:6px;align-items:center;">
                <a href="<?= $empAppsUrl ?>" class="job-card-app-count"
                   title="View applications">
                    <i class="ti ti-file-description" aria-hidden="true"></i>
                    <?= $appCount ?>
                </a>
                <a href="<?= $editUrl ?>" class="btn-card-edit">
                    <i class="ti ti-pencil" aria-hidden="true"></i>
                </a>
            </div>

        <!-- Admin actions -->
        <?php elseif (in_array($role, ['admin', 'super_admin'], true)): ?>
            <div style="display:flex;gap:6px;align-items:center;">
                <a href="<?= $appsUrl ?>" class="job-card-app-count"
                   title="View applications">
                    <i class="ti ti-file-description" aria-hidden="true"></i>
                    <?= $appCount ?>
                </a>
                <a href="<?= $adminEdit ?>" class="btn-card-edit">
                    <i class="ti ti-pencil" aria-hidden="true"></i>
                </a>
                <button class="btn-danger btn-delete-job"
                        data-job-id="<?= $jId ?>"
                        data-csrf="<?= $csrfSafe ?>"
                        data-title="<?= $title ?>"
                        title="Delete vacancy"
                        style="padding:6px 8px;">
                    <i class="ti ti-trash" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>

    </div>
</div>
