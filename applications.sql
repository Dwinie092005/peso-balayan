-- ============================================================
-- PESO Balayan IMIS — Applications Schema
-- File: database/applications.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Applications ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `applications` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `job_id`          INT UNSIGNED  NOT NULL,
    `applicant_id`    INT UNSIGNED  NOT NULL,
    `resume_path`     VARCHAR(500)  DEFAULT NULL,
    `cover_letter`    TEXT          DEFAULT NULL,
    `status`          ENUM('pending','reviewed','shortlisted',
                           'referred','hired','rejected','withdrawn')
                                    NOT NULL DEFAULT 'pending',
    `match_score`     DECIMAL(5,2)  DEFAULT NULL,
    `admin_notes`     TEXT          DEFAULT NULL,
    `employer_notes`  TEXT          DEFAULT NULL,
    `applied_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_application` (`job_id`, `applicant_id`),
    KEY `idx_app_applicant` (`applicant_id`),
    KEY `idx_app_status`    (`status`),
    CONSTRAINT `fk_app_job`       FOREIGN KEY (`job_id`)
        REFERENCES `jobs`       (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_app_applicant` FOREIGN KEY (`applicant_id`)
        REFERENCES `applicants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Application status timeline ────────────────────────────
CREATE TABLE IF NOT EXISTS `application_timeline` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` INT UNSIGNED NOT NULL,
    `status`         VARCHAR(50)  NOT NULL,
    `note`           TEXT         DEFAULT NULL,
    `changed_by`     INT UNSIGNED DEFAULT NULL,
    `changed_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_at_app` (`application_id`),
    CONSTRAINT `fk_at_application` FOREIGN KEY (`application_id`)
        REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
