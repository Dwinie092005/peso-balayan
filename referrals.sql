-- ============================================================
-- TABLE: referrals
-- PURPOSE: Admin-initiated referral of an applicant to an employer.
-- STATUS FLOW: pending → sent → acknowledged → hired | rejected
-- ============================================================

CREATE TABLE IF NOT EXISTS `referrals` (
    `id`              BIGINT        UNSIGNED NOT NULL AUTO_INCREMENT,
    `applicant_id`    BIGINT        UNSIGNED NOT NULL,
    `employer_id`     BIGINT        UNSIGNED NOT NULL,
    `job_id`          BIGINT        UNSIGNED NULL COMMENT 'Specific job opening, if applicable',
    `referred_by`     BIGINT        UNSIGNED NOT NULL COMMENT 'Admin user_id who made the referral',
    `status`          ENUM(
                          'pending',
                          'sent',
                          'acknowledged',
                          'interview_scheduled',
                          'hired',
                          'rejected',
                          'withdrawn'
                      ) NOT NULL DEFAULT 'pending',
    `referral_notes`  TEXT          NULL COMMENT 'Admin notes attached to referral',
    `employer_notes`  TEXT          NULL COMMENT 'Employer feedback on the referral',
    `referred_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `acknowledged_at` DATETIME      NULL,
    `resolved_at`     DATETIME      NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_applicant_id` (`applicant_id`),
    INDEX `idx_employer_id`  (`employer_id`),
    INDEX `idx_job_id`       (`job_id`),
    INDEX `idx_referred_by`  (`referred_by`),
    INDEX `idx_status`       (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: referral_history
-- PURPOSE: Immutable audit trail of every referral status change.
-- ============================================================

CREATE TABLE IF NOT EXISTS `referral_history` (
    `id`           BIGINT        UNSIGNED NOT NULL AUTO_INCREMENT,
    `referral_id`  BIGINT        UNSIGNED NOT NULL,
    `changed_by`   BIGINT        UNSIGNED NOT NULL COMMENT 'user_id who triggered the change',
    `from_status`  VARCHAR(50)   NOT NULL,
    `to_status`    VARCHAR(50)   NOT NULL,
    `note`         TEXT          NULL,
    `changed_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_referral_id` (`referral_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
