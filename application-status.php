<?php
/**
 * FILE: /app/views/components/application-status.php
 * PURPOSE: Reusable application status badge with icon, color, and optional progress rail.
 *
 * Variables (set before including):
 *   string $status      - one of: submitted, under_review, matched, referred, interview, hired, rejected, withdrawn
 *   bool   $showLabel   - display text label beside icon (default: true)
 *   bool   $showRail    - display full progress rail (default: false)
 *   string $size        - 'sm' | 'md' | 'lg' (default: 'md')
 */

$status    = $status    ?? 'submitted';
$showLabel = $showLabel ?? true;
$showRail  = $showRail  ?? false;
$size      = $size      ?? 'md';

$statusMap = [
    'submitted'    => ['label' => 'Submitted',      'hex' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'fa-paper-plane',    'step' => 1],
    'under_review' => ['label' => 'Under Review',   'hex' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'fa-search',         'step' => 2],
    'matched'      => ['label' => 'Matched',         'hex' => '#8b5cf6', 'bg' => '#f5f3ff', 'icon' => 'fa-link',           'step' => 3],
    'referred'     => ['label' => 'Referred',        'hex' => '#06b6d4', 'bg' => '#ecfeff', 'icon' => 'fa-share-square',  'step' => 4],
    'interview'    => ['label' => 'For Interview',   'hex' => '#f97316', 'bg' => '#fff7ed', 'icon' => 'fa-calendar-check', 'step' => 5],
    'hired'        => ['label' => 'Hired',            'hex' => '#22c55e', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle',  'step' => 6],
    'rejected'     => ['label' => 'Not Qualified',   'hex' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'fa-times-circle',  'step' => 0],
    'withdrawn'    => ['label' => 'Withdrawn',        'hex' => '#94a3b8', 'bg' => '#f8fafc', 'icon' => 'fa-undo',          'step' => 0],
];

$cfg  = $statusMap[$status] ?? $statusMap['submitted'];
$esc  = htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8');

$padMap  = ['sm' => '3px 8px',  'md' => '5px 12px', 'lg' => '7px 16px'];
$fszMap  = ['sm' => '11px',     'md' => '12px',      'lg' => '14px'];
$iconMap = ['sm' => '10px',     'md' => '12px',      'lg' => '14px'];

$pad  = $padMap[$size]  ?? $padMap['md'];
$fsz  = $fszMap[$size]  ?? $fszMap['md'];
$isz  = $iconMap[$size] ?? $iconMap['md'];

$railSteps = [
    ['key' => 'submitted',    'label' => 'Submitted'],
    ['key' => 'under_review', 'label' => 'Review'],
    ['key' => 'matched',      'label' => 'Matched'],
    ['key' => 'referred',     'label' => 'Referred'],
    ['key' => 'interview',    'label' => 'Interview'],
    ['key' => 'hired',        'label' => 'Hired'],
];
?>

<!-- ── Status Badge ─────────────────────────────────────────────────── -->
<span class="appstatus-badge appstatus-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
      style="
        display:inline-flex;align-items:center;gap:5px;
        background:<?= $cfg['bg'] ?>;color:<?= $cfg['hex'] ?>;
        padding:<?= $pad ?>;border-radius:50px;
        font-size:<?= $fsz ?>;font-weight:600;letter-spacing:.3px;
        border:1px solid <?= $cfg['hex'] ?>22;
        white-space:nowrap;
      "
      title="Application Status: <?= $esc ?>">
    <i class="fas <?= htmlspecialchars($cfg['icon'], ENT_QUOTES, 'UTF-8') ?>"
       style="font-size:<?= $isz ?>"></i>
    <?php if ($showLabel): ?>
        <?= $esc ?>
    <?php endif; ?>
</span>

<?php if ($showRail && $cfg['step'] > 0): ?>
<!-- ── Progress Rail ─────────────────────────────────────────────────── -->
<div class="appstatus-rail" style="margin-top:14px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;position:relative;">

        <!-- Connector line behind steps -->
        <div style="
            position:absolute;top:14px;left:14px;right:14px;
            height:3px;background:#e2e8f0;z-index:0;
        "></div>
        <div style="
            position:absolute;top:14px;left:14px;
            height:3px;
            width:<?= min(100, round(($cfg['step'] - 1) / 5 * 100)) ?>%;
            background:linear-gradient(90deg,#1565c0,#00acc1);
            z-index:1;transition:width .5s ease;
        "></div>

        <?php foreach ($railSteps as $railStep):
            $stepNum   = array_search($railStep['key'], array_column($railSteps, 'key')) + 1;
            $isDone    = $cfg['step'] >= $stepNum;
            $isCurrent = $status === $railStep['key'];

            $dotColor  = $isDone ? '#1565c0' : '#e2e8f0';
            $dotText   = $isDone ? '#fff' : '#94a3b8';
            $lblColor  = $isCurrent ? '#1565c0' : ($isDone ? '#475569' : '#94a3b8');
            $lblWeight = $isCurrent ? '700' : '500';
        ?>
        <div style="display:flex;flex-direction:column;align-items:center;z-index:2;flex:1;min-width:0;">
            <div style="
                width:28px;height:28px;border-radius:50%;
                background:<?= $dotColor ?>;color:<?= $dotText ?>;
                display:flex;align-items:center;justify-content:center;
                font-size:11px;font-weight:700;
                border:3px solid <?= $isDone ? '#1565c0' : '#e2e8f0' ?>;
                <?= $isCurrent ? 'box-shadow:0 0 0 4px #1565c022;' : '' ?>
                transition:all .3s ease;
            ">
                <?php if ($isDone && !$isCurrent): ?>
                    <i class="fas fa-check" style="font-size:10px;"></i>
                <?php else: ?>
                    <?= $stepNum ?>
                <?php endif; ?>
            </div>
            <span style="
                font-size:10px;margin-top:6px;text-align:center;
                color:<?= $lblColor ?>;font-weight:<?= $lblWeight ?>;
                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60px;
            "><?= htmlspecialchars($railStep['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($status === 'rejected' || $status === 'withdrawn'): ?>
<div style="
    display:inline-flex;align-items:center;gap:6px;
    background:<?= $cfg['bg'] ?>;color:<?= $cfg['hex'] ?>;
    padding:8px 14px;border-radius:8px;font-size:12px;
    border-left:3px solid <?= $cfg['hex'] ?>;margin-top:8px;
">
    <i class="fas <?= htmlspecialchars($cfg['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
    <span>Application <?= $esc ?></span>
</div>
<?php endif; ?>
