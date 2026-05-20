<?php
/**
 * Referral Card Component
 *
 * @var array  $referral    - Referral record with joined fields
 * @var string $viewerRole  - 'admin' | 'employer'
 * @var string $csrfToken   - For inline status forms
 */

$viewerRole = $viewerRole ?? 'admin';

$statusConfig = [
    'pending'              => ['label' => 'Pending',             'color' => 'gray',   'icon' => 'fa-clock'],
    'sent'                 => ['label' => 'Sent',                'color' => 'blue',   'icon' => 'fa-paper-plane'],
    'acknowledged'         => ['label' => 'Acknowledged',        'color' => 'orange', 'icon' => 'fa-eye'],
    'interview_scheduled'  => ['label' => 'Interview Scheduled', 'color' => 'purple', 'icon' => 'fa-calendar-check'],
    'hired'                => ['label' => 'Hired',               'color' => 'green',  'icon' => 'fa-check-circle'],
    'rejected'             => ['label' => 'Rejected',            'color' => 'red',    'icon' => 'fa-times-circle'],
    'withdrawn'            => ['label' => 'Withdrawn',           'color' => 'gray',   'icon' => 'fa-ban'],
];

$status = $referral['status'] ?? 'pending';
$meta   = $statusConfig[$status] ?? ['label' => $status, 'color' => 'gray', 'icon' => 'fa-circle'];
?>

<div class="referral-card referral-card--<?= htmlspecialchars($meta['color']) ?>"
     data-referral-id="<?= (int)($referral['id'] ?? 0) ?>">

    <!-- Status Strip -->
    <div class="referral-card__strip"></div>

    <div class="referral-card__body">

        <!-- Header Row -->
        <div class="referral-card__header">
            <div class="referral-card__avatar">
                <?= htmlspecialchars(strtoupper(substr($referral['applicant_name'] ?? 'A', 0, 1))) ?>
            </div>
            <div class="referral-card__info">
                <h4 class="referral-card__name">
                    <?= htmlspecialchars($referral['applicant_name'] ?? '—') ?>
                </h4>
                <p class="referral-card__sub">
                    <?= htmlspecialchars($referral['applicant_email'] ?? '') ?>
                </p>
            </div>
            <span class="badge badge--<?= htmlspecialchars($meta['color']) ?> referral-card__badge">
                <i class="fas <?= htmlspecialchars($meta['icon']) ?>"></i>
                <?= htmlspecialchars($meta['label']) ?>
            </span>
        </div>

        <!-- Details -->
        <div class="referral-card__details">
            <?php if (!empty($referral['company_name'])): ?>
                <span class="referral-card__detail">
                    <i class="fas fa-building"></i>
                    <?= htmlspecialchars($referral['company_name']) ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($referral['job_title'])): ?>
                <span class="referral-card__detail">
                    <i class="fas fa-briefcase"></i>
                    <?= htmlspecialchars($referral['job_title']) ?>
                </span>
            <?php endif; ?>
            <span class="referral-card__detail">
                <i class="fas fa-calendar-alt"></i>
                <?= htmlspecialchars(date('M d, Y', strtotime($referral['referred_at'] ?? 'now'))) ?>
            </span>
            <?php if (!empty($referral['referred_by_name'])): ?>
                <span class="referral-card__detail">
                    <i class="fas fa-user-tie"></i>
                    Referred by <?= htmlspecialchars($referral['referred_by_name']) ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Notes preview -->
        <?php if (!empty($referral['referral_notes'])): ?>
            <p class="referral-card__notes">
                <i class="fas fa-sticky-note"></i>
                <?= htmlspecialchars(mb_substr($referral['referral_notes'], 0, 100)) ?>
                <?= strlen($referral['referral_notes']) > 100 ? '…' : '' ?>
            </p>
        <?php endif; ?>

        <!-- Actions -->
        <div class="referral-card__actions">
            <a href="/<?= $viewerRole ?>/referrals/<?= (int)($referral['id'] ?? 0) ?>"
               class="btn btn--outline btn--sm">
                <i class="fas fa-eye"></i> View
            </a>

            <?php if ($viewerRole === 'admin' && !in_array($status, ['hired', 'rejected', 'withdrawn'])): ?>
                <button
                    type="button"
                    class="btn btn--ghost btn--sm js-referral-status-btn"
                    data-referral-id="<?= (int)($referral['id'] ?? 0) ?>"
                    data-current-status="<?= htmlspecialchars($status) ?>"
                >
                    <i class="fas fa-exchange-alt"></i> Update Status
                </button>
            <?php endif; ?>
        </div>

    </div>
</div>
