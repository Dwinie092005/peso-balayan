-- ============================================================
-- PESO Balayan IMIS — Job Vacancy Schema
-- File: database/jobs.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Job Categories ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_categories` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)    NOT NULL,
    `slug`       VARCHAR(100)    NOT NULL,
    `icon`       VARCHAR(60)     NOT NULL DEFAULT 'ti-briefcase',
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Jobs ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jobs` (
    `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `employer_id`        INT UNSIGNED    NOT NULL,
    `category_id`        INT UNSIGNED    DEFAULT NULL,
    `title`              VARCHAR(200)    NOT NULL,
    `description`        TEXT            NOT NULL,
    `requirements`       TEXT            DEFAULT NULL,
    `benefits`           TEXT            DEFAULT NULL,
    `employment_type`    ENUM('full_time','part_time','contract','temporary','internship')
                                         NOT NULL DEFAULT 'full_time',
    `salary_min`         DECIMAL(12,2)   DEFAULT NULL,
    `salary_max`         DECIMAL(12,2)   DEFAULT NULL,
    `salary_negotiable`  TINYINT(1)      NOT NULL DEFAULT 0,
    `location_city`      VARCHAR(100)    DEFAULT NULL,
    `location_province`  VARCHAR(100)    DEFAULT NULL,
    `location_address`   VARCHAR(255)    DEFAULT NULL,
    `slots`              TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `education_required` ENUM('none','elementary','high_school','vocational',
                              'associate','bachelor','masteral','doctorate')
                                         NOT NULL DEFAULT 'none',
    `experience_years`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`             ENUM('draft','active','closed','expired')
                                         NOT NULL DEFAULT 'draft',
    `expires_at`         DATE            DEFAULT NULL,
    `views_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jobs_employer`  (`employer_id`),
    KEY `idx_jobs_category`  (`category_id`),
    KEY `idx_jobs_status`    (`status`),
    KEY `idx_jobs_expires`   (`expires_at`),
    CONSTRAINT `fk_jobs_employer`  FOREIGN KEY (`employer_id`)
        REFERENCES `employers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jobs_category`  FOREIGN KEY (`category_id`)
        REFERENCES `job_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Job ↔ Skills pivot ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_skills` (
    `job_id`   INT UNSIGNED NOT NULL,
    `skill_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`job_id`, `skill_id`),
    CONSTRAINT `fk_js_job`   FOREIGN KEY (`job_id`)
        REFERENCES `jobs`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_js_skill` FOREIGN KEY (`skill_id`)
        REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Saved / bookmarked jobs ────────────────────────────────
CREATE TABLE IF NOT EXISTS `saved_jobs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `applicant_id` INT UNSIGNED NOT NULL,
    `job_id`       INT UNSIGNED NOT NULL,
    `saved_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_saved_job` (`applicant_id`, `job_id`),
    CONSTRAINT `fk_sj_applicant` FOREIGN KEY (`applicant_id`)
        REFERENCES `applicants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sj_job`       FOREIGN KEY (`job_id`)
        REFERENCES `jobs`       (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed: Job categories ───────────────────────────────────
INSERT IGNORE INTO `job_categories` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Information Technology',  'information-technology',  'ti-device-laptop',    1),
('Manufacturing',           'manufacturing',            'ti-building-factory', 2),
('Healthcare',              'healthcare',               'ti-stethoscope',      3),
('Sales & Marketing',       'sales-marketing',          'ti-speakerphone',     4),
('Administrative',          'administrative',           'ti-clipboard',        5),
('Construction',            'construction',             'ti-hard-hat',         6),
('Food & Beverage',         'food-beverage',            'ti-soup',             7),
('Transportation',          'transportation',           'ti-truck',            8),
('Education',               'education',                'ti-school',           9),
('Customer Service',        'customer-service',         'ti-headset',         10),
('Finance & Accounting',    'finance-accounting',       'ti-calculator',      11),
('General Labor',           'general-labor',            'ti-tools',           12);

SET FOREIGN_KEY_CHECKS = 1;
