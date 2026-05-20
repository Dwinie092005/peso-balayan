<?php
/**
 * Reusable Activity Feed Component
 *
 * @var array  $feedItems   - Array of items: [icon, color, title, subtitle, time]
 * @var string $feedTitle   - Section heading
 * @var string $feedViewAll - Optional 'View All' link href
 */

$feedTitle   = $feedTitle   ?? 'Recent Activity';
$feedViewAll = $feedViewAll ?? null;
$feedItems   = $feedItems   ?? [];
?>

<div class="activity-feed">
    <div class="activity-feed__header">
        <h3 class="activity-feed__title"><?= htmlspecialchars($feedTitle) ?></h3>
        <?php if ($feedViewAll): ?>
            <a href="<?= htmlspecialchars($feedViewAll) ?>" class="activity-feed__view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="activity-feed__list">
        <?php if (empty($feedItems)): ?>
            <div class="activity-feed__empty">
                <i class="fas fa-inbox"></i>
                <p>No recent activity.</p>
            </div>
        <?php else: ?>
            <?php foreach ($feedItems as $item): ?>
                <div class="activity-feed__item">
                    <div class="activity-feed__icon activity-feed__icon--<?= htmlspecialchars($item['color'] ?? 'blue') ?>">
                        <i class="fas <?= htmlspecialchars($item['icon'] ?? 'fa-circle') ?>"></i>
                    </div>
                    <div class="activity-feed__content">
                        <p class="activity-feed__item-title"><?= htmlspecialchars($item['title'] ?? '') ?></p>
                        <?php if (!empty($item['subtitle'])): ?>
                            <p class="activity-feed__item-sub"><?= htmlspecialchars($item['subtitle']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['time'])): ?>
                        <span class="activity-feed__time"><?= htmlspecialchars($item['time']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
