<?php
/**
 * Reusable Dashboard Stat Card Component
 *
 * Required variables:
 * @var string $cardIcon      - FontAwesome icon class (e.g. 'fa-briefcase')
 * @var string $cardLabel     - Display label
 * @var string|int $cardValue - Stat value
 * @var string $cardColor     - CSS color class: 'blue' | 'green' | 'orange' | 'purple' | 'red'
 * @var string $cardBadge     - Optional badge text (e.g. 'Active')
 * @var string $cardLink      - Optional href link
 */

$cardColor  = $cardColor  ?? 'blue';
$cardBadge  = $cardBadge  ?? null;
$cardLink   = $cardLink   ?? null;
$cardIcon   = $cardIcon   ?? 'fa-chart-bar';
$cardValue  = $cardValue  ?? 0;
$cardLabel  = $cardLabel  ?? 'Stat';
?>

<div class="dash-card dash-card--<?= htmlspecialchars($cardColor) ?>">
    <div class="dash-card__icon-wrap">
        <i class="fas <?= htmlspecialchars($cardIcon) ?>"></i>
    </div>

    <div class="dash-card__body">
        <span class="dash-card__value"><?= htmlspecialchars((string)$cardValue) ?></span>
        <span class="dash-card__label"><?= htmlspecialchars($cardLabel) ?></span>
    </div>

    <?php if ($cardBadge): ?>
        <div class="dash-card__badge">
            <span><?= htmlspecialchars($cardBadge) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($cardLink): ?>
        <a href="<?= htmlspecialchars($cardLink) ?>" class="dash-card__link" aria-label="View <?= htmlspecialchars($cardLabel) ?>">
            <i class="fas fa-arrow-right"></i>
        </a>
    <?php endif; ?>
</div>
