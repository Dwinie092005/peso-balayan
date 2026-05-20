<?php
/**
 * Component: form-stepper.php
 * Multi-step form progress indicator
 *
 * @var array  $steps        Array of ['label' => ..., 'icon' => ...]
 * @var int    $current_step 1-based current step index
 */

$current_step = $current_step ?? 1;
$steps        = $steps        ?? [];
$total        = count($steps);

// Track fill percentage
$fill_pct = $total > 1 ? (($current_step - 1) / ($total - 1)) * 100 : 0;
?>

<!-- ---- DESKTOP STEPPER ---- -->
<div class="form-stepper" role="navigation" aria-label="Form progress">
    <!-- Track line -->
    <div class="stepper-track" aria-hidden="true">
        <div class="stepper-track-fill" style="width: <?= round($fill_pct, 2) ?>%;" id="stepperTrackFill"></div>
    </div>

    <?php foreach ($steps as $index => $step):
        $step_num     = $index + 1;
        $step_label   = $step['label'] ?? "Step $step_num";
        $step_icon    = $step['icon']  ?? '';
        $is_completed = $step_num < $current_step;
        $is_active    = $step_num === $current_step;
        $css_class    = $is_completed ? 'completed' : ($is_active ? 'active' : '');
        $aria_current = $is_active    ? 'step'      : 'false';
    ?>
        <div
            class="stepper-item <?= $css_class ?>"
            data-step="<?= $step_num ?>"
            role="listitem"
            aria-current="<?= $aria_current ?>"
            aria-label="Step <?= $step_num ?>: <?= htmlspecialchars($step_label) ?> — <?= $is_completed ? 'Completed' : ($is_active ? 'Current' : 'Pending') ?>"
        >
            <div class="stepper-circle">
                <?php if ($is_completed): ?>
                    <svg class="stepper-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20,6 9,17 4,12"></polyline>
                    </svg>
                <?php else: ?>
                    <span class="stepper-number"><?= $step_num ?></span>
                <?php endif; ?>
            </div>
            <span class="stepper-label"><?= htmlspecialchars($step_label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<!-- ---- MOBILE COMPACT STEPPER ---- -->
<div class="form-stepper-compact" aria-hidden="true">
    <div class="stepper-compact-indicator">
        <?php foreach ($steps as $index => $step):
            $step_num  = $index + 1;
            $dot_class = $step_num < $current_step ? 'completed' : ($step_num === $current_step ? 'active' : '');
        ?>
            <div class="stepper-compact-dot <?= $dot_class ?>"></div>
        <?php endforeach; ?>
    </div>
    <div class="stepper-compact-info">
        <div class="stepper-compact-step">Step <?= $current_step ?> of <?= $total ?></div>
        <div class="stepper-compact-title">
            <?= htmlspecialchars($steps[$current_step - 1]['label'] ?? '') ?>
        </div>
    </div>
</div>
