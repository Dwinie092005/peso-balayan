-- =============================================================================
-- matches.sql
-- PESO Balayan — Matching Engine Database Schema
-- Location: /database/matches.sql
--
-- Tables:
--   matches        → computed match results per application+job pair
--   match_queue    → pending applications awaiting matching
--   match_history  → audit trail of every status change on a match
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- Table: matches
-- Stores the computed compatibility score between one applicant and one job.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `matches` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `application_id`    INT UNSIGNED     NOT NULL,
    `applicant_id`      INT UNSIGNED     NOT NULL,
    `job_id`            INT UNSIGNED     NOT NULL,

    -- Component scores (0.00 – 100.00 each)
    `skill_score`       DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT '40% weight',
    `education_score`   DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT '30% weight',
    `experience_score`  DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT '20% weight',
    `location_score`    DECIMAL(5,2)     NOT NULL DEFAULT 0.00 COMMENT '10% weight',

    -- Weighted total (0.00 – 100.00)
    `total_score`       DECIMAL(5,2)     NOT NULL DEFAULT 0.00,

    -- JSON snapshot of scoring explanation (stored for display)
    `score_breakdown`   JSON             DEFAULT NULL,

    -- Rematch tracking
    `rematch_count`     TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- Workflow status
    `status` ENUM(
        'pending',
        'qualified',
        'disqualified',
        'under_review',
        'approved',
        'rejected',
        'referred',
        'no_match'
    ) NOT NULL DEFAULT 'pending',

    -- Admin review
    `admin_notes`       TEXT             DEFAULT NULL,
    `reviewed_by`       INT UNSIGNED     DEFAULT NULL COMMENT 'FK → users.id',
    `reviewed_at`       DATETIME         DEFAULT NULL,

    -- Timestamps
    `matched_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_application_job`    (`application_id`, `job_id`),
    KEY `idx_applicant_id`             (`applicant_id`),
    KEY `idx_job_id`                   (`job_id`),
    KEY `idx_status`                   (`status`),
    KEY `idx_total_score`              (`total_score` DESC),
    KEY `idx_reviewed_by`              (`reviewed_by`),
    KEY `idx_created_at`               (`created_at`),

    CONSTRAINT `fk_matches_application`
        FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_matches_applicant`
        FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_matches_job`
        FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_matches_reviewer`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Computed compatibility scores between applicants and jobs';

-- -----------------------------------------------------------------------------
-- Table: match_queue
-- Tracks applications awaiting matching engine processing.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `match_queue` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `application_id`    INT UNSIGNED     NOT NULL,
    `priority`          TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
    `attempts`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `last_attempted_at` DATETIME         DEFAULT NULL,
    `scheduled_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM(
        'waiting',
        'processing',
        'completed',
        'failed',
        'skipped'
    ) NOT NULL DEFAULT 'waiting',
    `error_message`     TEXT             DEFAULT NULL,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_queue_status`      (`status`),
    KEY `idx_queue_priority`    (`priority`, `scheduled_at`),
    KEY `idx_queue_application` (`application_id`),

    CONSTRAINT `fk_queue_application`
        FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Queue of applications pending matching engine processing';

-- -----------------------------------------------------------------------------
-- Table: match_history
-- Immutable audit trail of every status transition on a match record.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `match_history` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `match_id`      INT UNSIGNED    NOT NULL,
    `from_status`   VARCHAR(30)     DEFAULT NULL,
    `to_status`     VARCHAR(30)     NOT NULL,
    `changed_by`    INT UNSIGNED    DEFAULT NULL COMMENT 'NULL = system/cron',
    `notes`         TEXT            DEFAULT NULL,
    `metadata`      JSON            DEFAULT NULL COMMENT 'extra context snapshot',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_history_match`     (`match_id`),
    KEY `idx_history_changed_by`(`changed_by`),
    KEY `idx_history_created`   (`created_at`),

    CONSTRAINT `fk_history_match`
        FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log of match status transitions';

SET FOREIGN_KEY_CHECKS = 1;
