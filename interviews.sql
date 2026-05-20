-- ============================================================
-- TABLE: interviews
-- PURPOSE: Interview scheduling between employer and applicant.
-- STATUS FLOW: scheduled → confirmed → completed | cancelled | rescheduled
-- ============================================================

CREATE TABLE IF NOT EXISTS `interviews` (
    `id`                BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id`    BIGINT       UNSIGNED NULL  COMMENT 'Direct application link',
    `referral_id`       BIGINT       UNSIGNED NULL  COMMENT 'Referral link (if admin-referred)',
    `applicant_id`      BIGINT       UNSIGNED NOT NULL,
    `employer_id`       BIGINT       UNSIGNED NOT NULL,
    `job_id`            BIGINT       UNSIGNED NULL,
    `scheduled_by`      BIGINT       UNSIGNED NOT NULL COMMENT 'user_id (employer or admin)',
    `interview_date`    DATE         NOT NULL,
    `interview_time`    TIME         NOT NULL,
    `duration_minutes`  SMALLINT     UNSIGNED NOT NULL DEFAULT 60,
    `interview_type`    ENUM('in_person','phone','video','practical_exam')
                        NOT NULL DEFAULT 'in_person',
    `location`          VARCHAR(500) NULL COMMENT 'Physical address or meeting link',
    `status`            ENUM(
                            'scheduled',
                            'confirmed',
                            'rescheduled',
                            'completed',
                            'cancelled',
                            'no_show'
                        ) NOT NULL DEFAULT 'scheduled',
    `employer_notes`    TEXT         NULL,
    `applicant_notes`   TEXT         NULL,
    `outcome`           ENUM('hired','rejected','pending','no_decision')
                        NULL DEFAULT NULL,
    `outcome_notes`     TEXT         NULL,
    `reminder_sent`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_applicant_id`   (`applicant_id`),
    INDEX `idx_employer_id`    (`employer_id`),
    INDEX `idx_application_id` (`application_id`),
    INDEX `idx_referral_id`    (`referral_id`),
    INDEX `idx_interview_date` (`interview_date`),
    INDEX `idx_status`         (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TABLE: interview_reschedules
-- PURPOSE: Tracks every reschedule request with reason and actor.
-- ============================================================

CREATE TABLE IF NOT EXISTS `interview_reschedules` (
    `id`              BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `interview_id`    BIGINT       UNSIGNED NOT NULL,
    `requested_by`    BIGINT       UNSIGNED NOT NULL,
    `old_date`        DATE         NOT NULL,
    `old_time`        TIME         NOT NULL,
    `new_date`        DATE         NOT NULL,
    `new_time`        TIME         NOT NULL,
    `reason`          TEXT         NULL,
    `approved`        TINYINT(1)   NULL DEFAULT NULL COMMENT 'NULL=pending, 1=approved, 0=rejected',
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_interview_id` (`interview_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
