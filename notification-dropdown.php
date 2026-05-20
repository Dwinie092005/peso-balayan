<?php
/**
 * Notification Dropdown Component
 *
 * @var array  $notifications     - Array of notification records
 * @var int    $unreadCount       - Total unread count for badge
 * @var string $markAllReadRoute  - URL to mark all notifications read
 */

$notifications    = $notifications    ?? [];
$unreadCount      = $unreadCount      ?? 0;
$markAllReadRoute = $markAllReadRoute ?? '/notifications/mark-all-read';
?>

<div class="notif-dropdown" id="notifDropdown" role="region" aria-label="Notifications">
    <button
        class="notif-dropdown__trigger"
        id="notifTrigger"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="notifPanel"
        type="button"
    >
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="notif-dropdown__badge" aria-label="<?= (int)$unreadCount ?> unread notifications">
                <?= $unreadCount > 99 ? '99+' : (int)$unreadCount ?>
            </span>
        <?php endif; ?>
    </button>

    <div class="notif-dropdown__panel" id="notifPanel" role="menu" aria-hidden="true">
        <div class="notif-dropdown__panel-header">
            <span class="notif-dropdown__panel-title">Notifications</span>
            <?php if ($unreadCount > 0): ?>
                <a
                    href="<?= htmlspecialchars($markAllReadRoute) ?>"
                    class="notif-dropdown__mark-all"
                    data-action="mark-all-read"
                >
                    Mark all read
                </a>
            <?php endif; ?>
        </div>

        <ul class="notif-dropdown__list" role="list">
            <?php if (empty($notifications)): ?>
                <li class="notif-dropdown__empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>You have no notifications.</p>
                </li>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <li
                        class="notif-dropdown__item <?= empty($notif['read_at']) ? 'notif-dropdown__item--unread' : '' ?>"
                        role="menuitem"
                        data-id="<?= (int)$notif['id'] ?>"
                    >
                        <div class="notif-dropdown__item-icon">
                            <i class="fas fa-<?= htmlspecialchars($notif['icon'] ?? 'info-circle') ?>"></i>
                        </div>
                        <div class="notif-dropdown__item-body">
                            <p class="notif-dropdown__item-msg">
                                <?= htmlspecialchars($notif['message'] ?? '') ?>
                            </p>
                            <span class="notif-dropdown__item-time">
                                <?= htmlspecialchars($notif['created_at'] ?? '') ?>
                            </span>
                        </div>
                        <?php if (empty($notif['read_at'])): ?>
                            <span class="notif-dropdown__unread-dot" aria-hidden="true"></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <div class="notif-dropdown__panel-footer">
            <a href="/notifications" class="notif-dropdown__view-all">View All Notifications</a>
        </div>
    </div>
</div>
