<?php
/**
 * Component: interview-schedule.php
 * Location : /app/views/components/interview-schedule.php
 *
 * Renders a single interview schedule card.
 * Can be used in both employer and admin views.
 *
 * @var array  $interview {
 *   int    id
 *   int    application_id
 *   string interview_date      (Y-m-d)
 *   string interview_time      (H:i:s)
 *   string interview_type      'onsite'|'online'|'phone'
 *   string interview_link      (optional — for online)
 *   string interview_location  (optional — for onsite)
 *   string status              'scheduled'|'completed'|'cancelled'|'no_show'|'rescheduled'
 *   string notes               (optional employer note)
 *   string applicant_name      (joined)
 *   string job_title           (joined)
 * }
 * @var bool   $show_actions   Whether to show reschedule/cancel buttons
 * @var string $csrf_token     CSRF token for action forms
 */

$interview    = $interview    ?? [];
$show_actions = $show_actions ?? true;
$csrf_token   = $csrf_token   ?? ($_SESSION['csrf_token'] ?? '');

if (empty($interview)) return;

/* ---- Derived values ---- */
$interview_id  = (int)   ($interview['id']               ?? 0);
$app_id        = (int)   ($interview['application_id']   ?? 0);
$raw_date      = $interview['interview_date']  ?? '';
$raw_time      = $interview['interview_time']  ?? '';
$type          = $interview['interview_type']  ?? 'onsite';
$link          = $interview['interview_link']  ?? '';
$location      = $interview['interview_location'] ?? '';
$status        = $interview['status']          ?? 'scheduled';
$notes         = $interview['notes']           ?? '';
$applicant     = $interview['applicant_name']  ?? 'Applicant';
$job_title     = $interview['job_title']       ?? '';

/* Format date/time */
$date_obj      = $raw_date ? new DateTime($raw_date) : null;
$time_obj      = $raw_time ? new DateTime($raw_time) : null;
$display_date  = $date_obj ? $date_obj->format('l, F j, Y')  : '—';
$display_time  = $time_obj ? $time_obj->format('g:i A')       : '—';
$is_past       = $date_obj && $date_obj < new DateTime('today');

/* Status badge classes */
$status_map = [
    'scheduled'   => ['label' => 'Scheduled',   'class' => 'badge-purple'],
    'completed'   => ['label' => 'Completed',   'class' => 'badge-green'],
    'cancelled'   => ['label' => 'Cancelled',   'class' => 'badge-red'],
    'no_show'     => ['label' => 'No Show',     'class' => 'badge-gray'],
    'rescheduled' => ['label' => 'Rescheduled', 'class' => 'badge-orange'],
];
$status_cfg   = $status_map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-gray'];

/* Type icons */
$type_icons = [
    'onsite' => 'map-pin',
    'online' => 'video',
    'phone'  => 'phone',
];
$type_icon  = $type_icons[$type] ?? 'calendar';
$type_label = ucfirst($type);

$can_act = $show_actions && in_array($status, ['scheduled', 'rescheduled'], true);
?>

<div class="interview-card" data-interview-id="<?= $interview_id ?>" id="interview-<?= $interview_id ?>">

    <!-- Card Header -->
    <div class="interview-card-header">
        <div class="interview-card-title">
            <i data-lucide="calendar" style="width:15px;height:15px;color:var(--primary);"></i>
            Interview — <?= htmlspecialchars($applicant) ?>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <!-- Status badge -->
            <span class="hiring-pill <?= htmlspecialchars($status) ?>" style="font-size:0.6875rem;">
                <span class="pill-dot"></span>
                <?= htmlspecialchars($status_cfg['label']) ?>
            </span>
            <?php if ($is_past && $status === 'scheduled'): ?>
                <span style="font-size:0.6875rem;color:var(--warning);font-weight:600;">Past</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Body -->
    <div class="interview-card-body">

        <!-- Job context -->
        <?php if ($job_title): ?>
            <p style="font-family:var(--font);font-size:0.8rem;color:var(--gray-400);margin-bottom:0.875rem;">
                <i data-lucide="briefcase" style="width:12px;height:12px;vertical-align:middle;"></i>
                <?= htmlspecialchars($job_title) ?>
            </p>
        <?php endif; ?>

        <!-- Date / Time / Type -->
        <div class="interview-datetime">
            <div class="interview-dt-block">
                <div class="dt-icon"><i data-lucide="calendar" style="width:14px;height:14px;"></i></div>
                <div>
                    <div class="dt-label">Date</div>
                    <div class="dt-value"><?= htmlspecialchars($display_date) ?></div>
                </div>
            </div>

            <div class="interview-dt-block">
                <div class="dt-icon"><i data-lucide="clock" style="width:14px;height:14px;"></i></div>
                <div>
                    <div class="dt-label">Time</div>
                    <div class="dt-value"><?= htmlspecialchars($display_time) ?></div>
                </div>
            </div>

            <div class="interview-dt-block">
                <div class="dt-icon"><i data-lucide="<?= htmlspecialchars($type_icon) ?>" style="width:14px;height:14px;"></i></div>
                <div>
                    <div class="dt-label">Type</div>
                    <div class="dt-value"><?= htmlspecialchars($type_label) ?></div>
                </div>
            </div>
        </div>

        <!-- Location / Link -->
        <?php if ($type === 'online' && $link): ?>
            <div style="margin-bottom:0.875rem;">
                <a href="<?= htmlspecialchars($link) ?>" target="_blank" rel="noopener noreferrer"
                   style="font-family:var(--font);font-size:0.8125rem;color:var(--primary);font-weight:600;display:inline-flex;align-items:center;gap:0.375rem;">
                    <i data-lucide="external-link" style="width:13px;height:13px;"></i>
                    Join Online Interview
                </a>
            </div>
        <?php elseif ($type === 'onsite' && $location): ?>
            <div style="margin-bottom:0.875rem;font-family:var(--font);font-size:0.8125rem;color:var(--gray-600);display:flex;align-items:flex-start;gap:0.375rem;">
                <i data-lucide="map-pin" style="width:13px;height:13px;margin-top:2px;color:var(--gray-400);flex-shrink:0;"></i>
                <?= htmlspecialchars($location) ?>
            </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if ($notes): ?>
            <div class="interview-notes">
                <strong style="display:block;font-size:0.75rem;color:var(--gray-500);margin-bottom:0.25rem;">Notes from employer:</strong>
                <?= nl2br(htmlspecialchars($notes)) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Action Footer -->
    <?php if ($can_act): ?>
        <div class="hiring-action-bar">
            <!-- Reschedule -->
            <button
                type="button"
                class="btn-sm btn-outline"
                data-action="reschedule"
                data-interview-id="<?= $interview_id ?>"
                data-application-id="<?= $app_id ?>"
                data-current-date="<?= htmlspecialchars($raw_date) ?>"
                data-current-time="<?= htmlspecialchars($raw_time) ?>"
                data-current-type="<?= htmlspecialchars($type) ?>"
            >
                <i data-lucide="calendar-clock" style="width:13px;height:13px;"></i>
                Reschedule
            </button>

            <!-- Mark Completed -->
            <button
                type="button"
                class="btn-sm btn-success-outline"
                data-action="complete-interview"
                data-interview-id="<?= $interview_id ?>"
                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
            >
                <i data-lucide="check" style="width:13px;height:13px;"></i>
                Mark Done
            </button>

            <!-- Cancel -->
            <button
                type="button"
                class="btn-sm btn-danger-outline"
                data-action="cancel-interview"
                data-interview-id="<?= $interview_id ?>"
                data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                style="margin-left:auto;"
            >
                <i data-lucide="x" style="width:13px;height:13px;"></i>
                Cancel
            </button>
        </div>
    <?php endif; ?>
</div>

<style>
/* Inline micro-styles — scoped to this component */
.btn-sm {
    display: inline-flex;
    align-items: center;
    gap: 0.3125rem;
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-family: var(--font);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid transparent;
    transition: all 0.2s;
    line-height: 1;
}
.btn-outline         { background: var(--white); border-color: var(--gray-200); color: var(--gray-600); }
.btn-outline:hover   { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.btn-success-outline { background: var(--white); border-color: var(--success); color: var(--success); }
.btn-success-outline:hover { background: var(--success-light); }
.btn-danger-outline  { background: var(--white); border-color: var(--danger); color: var(--danger); }
.btn-danger-outline:hover  { background: var(--danger-light); }
</style>
