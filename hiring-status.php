<?php
/**
 * Component: hiring-status.php
 * Location : /app/views/components/hiring-status.php
 *
 * Renders a hiring status pill and optional pipeline progress bar.
 *
 * @var string $status       Current status key (e.g. 'interview')
 * @var bool   $show_bar     Whether to render the full pipeline progress bar
 * @var bool   $show_pill    Whether to render the status pill (default true)
 * @var string $size         'sm' | 'md' (default)  — pill sizing
 */

$status    = $status    ?? 'submitted';
$show_bar  = $show_bar  ?? false;
$show_pill = $show_pill ?? true;
$size      = $size      ?? 'md';

/* ---- Status configuration map ---- */
$status_config = [
    'submitted'    => ['label' => 'Submitted',    'icon' => 'send'],
    'under_review' => ['label' => 'Under Review', 'icon' => 'eye'],
    'matched'      => ['label' => 'Matched',      'icon' => 'zap'],
    'referred'     => ['label' => 'Referred',     'icon' => 'share-2'],
    'interview'    => ['label' => 'Interview',    'icon' => 'calendar'],
    'hired'        => ['label' => 'Hired',        'icon' => 'check-circle'],
    'rejected'     => ['label' => 'Rejected',     'icon' => 'x-circle'],
    'withdrawn'    => ['label' => 'Withdrawn',    'icon' => 'minus-circle'],
];

/* ---- Ordered pipeline stages (forward flow only) ---- */
$pipeline_stages = [
    'submitted',
    'under_review',
    'matched',
    'referred',
    'interview',
    'hired',
];

$current_config = $status_config[$status] ?? ['label' => ucfirst($status), 'icon' => 'circle'];
$pill_class     = 'hiring-pill ' . htmlspecialchars($status);
if ($size === 'sm') {
    $pill_class .= ' hiring-pill-sm';
}

/* Determine pipeline position (terminal: rejected/withdrawn are off-track) */
$current_index  = array_search($status, $pipeline_stages, true);
$is_terminal_off = in_array($status, ['rejected', 'withdrawn'], true);
?>

<?php if ($show_pill): ?>
    <span class="<?= $pill_class ?>">
        <span class="pill-dot"></span>
        <i data-lucide="<?= htmlspecialchars($current_config['icon']) ?>" style="width:11px;height:11px;"></i>
        <?= htmlspecialchars($current_config['label']) ?>
    </span>
<?php endif; ?>

<?php if ($show_bar): ?>
    <div class="pipeline-bar" role="list" aria-label="Application progress">
        <?php foreach ($pipeline_stages as $idx => $stage):
            $stage_config = $status_config[$stage] ?? [];
            $stage_label  = $stage_config['label'] ?? ucfirst($stage);
            $stage_icon   = $stage_config['icon']  ?? 'circle';

            if ($is_terminal_off) {
                /* All stages up to the last known step are "done" */
                $node_class = 'pipeline-stage';
            } elseif ($current_index !== false) {
                if ($idx < $current_index) {
                    $node_class = 'pipeline-stage done';
                } elseif ($idx === $current_index) {
                    $node_class = 'pipeline-stage current';
                } else {
                    $node_class = 'pipeline-stage';
                }
            } else {
                $node_class = 'pipeline-stage';
            }
        ?>
            <div class="<?= $node_class ?>" role="listitem" aria-label="<?= htmlspecialchars($stage_label) ?>">
                <div class="pipeline-stage-node">
                    <?php if (str_contains($node_class, 'done')): ?>
                        <i data-lucide="check" style="width:10px;height:10px;stroke-width:3;"></i>
                    <?php else: ?>
                        <?= $idx + 1 ?>
                    <?php endif; ?>
                </div>
                <span class="pipeline-stage-label"><?= htmlspecialchars($stage_label) ?></span>
            </div>
        <?php endforeach; ?>

        <?php if ($is_terminal_off): ?>
            <div class="pipeline-stage <?= $status === 'rejected' ? 'rejected-stage' : 'withdrawn-stage' ?>">
                <div class="pipeline-stage-node" style="border-color:<?= $status === 'rejected' ? '#dc2626' : '#94a3b8' ?>;background:<?= $status === 'rejected' ? '#dc2626' : '#94a3b8' ?>;color:white;">
                    <i data-lucide="<?= $status === 'rejected' ? 'x' : 'minus' ?>" style="width:10px;height:10px;stroke-width:3;"></i>
                </div>
                <span class="pipeline-stage-label" style="color:<?= $status === 'rejected' ? '#dc2626' : '#94a3b8' ?>;">
                    <?= htmlspecialchars($current_config['label']) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
