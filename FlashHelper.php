<?php

namespace App\Helpers;

/**
 * FlashHelper
 *
 * Session-based one-time flash messages.
 * Supports multiple types: success, error, warning, info.
 *
 * USAGE (Controller):
 *   FlashHelper::set('success', 'Profile updated successfully.');
 *
 * USAGE (View):
 *   FlashHelper::render();
 *   — or —
 *   if (FlashHelper::has('error')) { echo FlashHelper::get('error'); }
 */
class FlashHelper
{
    private const SESSION_KEY = '_flash';

    /** Valid message types mapped to CSS classes and icons */
    private const TYPES = [
        'success' => ['icon' => 'fa-check-circle',      'css' => 'flash--success'],
        'error'   => ['icon' => 'fa-times-circle',      'css' => 'flash--error'],
        'warning' => ['icon' => 'fa-exclamation-triangle','css' => 'flash--warning'],
        'info'    => ['icon' => 'fa-info-circle',        'css' => 'flash--info'],
    ];

    /**
     * Store a flash message for the next request.
     *
     * @param string $type    'success' | 'error' | 'warning' | 'info'
     * @param string $message Human-readable message text
     */
    public static function set(string $type, string $message): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $type = in_array($type, array_keys(self::TYPES), true) ? $type : 'info';

        $_SESSION[self::SESSION_KEY][] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Check whether any flash messages exist (optionally filter by type).
     *
     * @param string|null $type
     */
    public static function has(?string $type = null): bool
    {
        $flashes = $_SESSION[self::SESSION_KEY] ?? [];

        if ($type === null) {
            return !empty($flashes);
        }

        foreach ($flashes as $flash) {
            if ($flash['type'] === $type) return true;
        }

        return false;
    }

    /**
     * Retrieve and clear all flash messages (optionally filter by type).
     *
     * @param string|null $type
     * @return array
     */
    public static function getAll(?string $type = null): array
    {
        $flashes = $_SESSION[self::SESSION_KEY] ?? [];

        if ($type !== null) {
            $flashes = array_filter($flashes, fn($f) => $f['type'] === $type);
            $flashes = array_values($flashes);
        }

        unset($_SESSION[self::SESSION_KEY]);
        return $flashes;
    }

    /**
     * Render all flash messages as HTML and clear them.
     * Call once per page in the layout, after the topbar.
     */
    public static function render(): void
    {
        $flashes = self::getAll();

        if (empty($flashes)) return;

        echo '<div class="flash-container" role="alert" aria-live="polite">';

        foreach ($flashes as $flash) {
            $type    = htmlspecialchars($flash['type']);
            $message = htmlspecialchars($flash['message']);
            $meta    = self::TYPES[$flash['type']] ?? self::TYPES['info'];
            $icon    = $meta['icon'];
            $css     = $meta['css'];

            echo "
            <div class='flash {$css}' data-flash='true'>
                <div class='flash__icon'>
                    <i class='fas {$icon}'></i>
                </div>
                <div class='flash__body'>
                    <p class='flash__message'>{$message}</p>
                </div>
                <button class='flash__close' type='button' aria-label='Dismiss notification'>
                    <i class='fas fa-times'></i>
                </button>
            </div>";
        }

        echo '</div>';
    }

    /**
     * Convenience shorthand setters.
     */
    public static function success(string $message): void { self::set('success', $message); }
    public static function error(string $message): void   { self::set('error',   $message); }
    public static function warning(string $message): void { self::set('warning', $message); }
    public static function info(string $message): void    { self::set('info',    $message); }
}
