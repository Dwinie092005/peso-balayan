-- ============================================================
-- TABLE: password_resets
-- PURPOSE: Stores secure one-time password reset tokens
--          with expiry and usage tracking.
-- ============================================================

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         BIGINT        UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT        UNSIGNED NOT NULL,
    `email`      VARCHAR(255)  NOT NULL,
    `token_hash` VARCHAR(255)  NOT NULL COMMENT 'SHA-256 hash of the raw token',
    `expires_at` DATETIME      NOT NULL,
    `used_at`    DATETIME      NULL DEFAULT NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_email`      (`email`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_token_hash` (`token_hash`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
