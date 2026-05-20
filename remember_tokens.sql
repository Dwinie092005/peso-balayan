-- ============================================================
-- TABLE: remember_tokens
-- PURPOSE: Stores hashed remember-me tokens for persistent
--          login across sessions (token rotation on each use).
-- ============================================================

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`          BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT       UNSIGNED NOT NULL,
    `selector`    VARCHAR(32)  NOT NULL  COMMENT 'Public lookup key (not secret)',
    `token_hash`  VARCHAR(255) NOT NULL  COMMENT 'SHA-256 hash of the validator portion',
    `expires_at`  DATETIME     NOT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE INDEX `uq_selector`   (`selector`),
    INDEX  `idx_user_id`         (`user_id`),
    INDEX  `idx_expires_at`      (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: email_verifications
-- PURPOSE: Stores email verification tokens sent on registration.
-- ============================================================

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id`         BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT       UNSIGNED NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `verified_at`DATETIME     NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: login_attempts
-- PURPOSE: Brute-force prevention — tracks failed logins by IP.
-- ============================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`          BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`  VARCHAR(45)  NOT NULL,
    `email`       VARCHAR(255) NULL,
    `attempted_at`DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_ip`    (`ip_address`),
    INDEX `idx_email` (`email`),
    INDEX `idx_time`  (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
