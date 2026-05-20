-- ============================================================
-- PESO Balayan – Database Schema
-- File: database/peso_balayan.sql
-- Engine: MySQL / MariaDB | Charset: utf8mb4
-- ============================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+08:00";

CREATE DATABASE IF NOT EXISTS `peso_balayan`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `peso_balayan`;

-- ── users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `email`             VARCHAR(180)     NOT NULL UNIQUE,
  `password_hash`     VARCHAR(255)     NOT NULL,
  `role`              ENUM('applicant','employer','admin','superadmin') NOT NULL DEFAULT 'applicant',
  `is_active`         TINYINT(1)       NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME         DEFAULT NULL,
  `remember_token`    VARCHAR(100)     DEFAULT NULL,
  `reset_token`       VARCHAR(100)     DEFAULT NULL,
  `reset_expires_at`  DATETIME         DEFAULT NULL,
  `last_login_at`     DATETIME         DEFAULT NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── locations ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `locations` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barangay`   VARCHAR(100) NOT NULL,
  `municipality` VARCHAR(100) NOT NULL DEFAULT 'Balayan',
  `province`   VARCHAR(100) NOT NULL DEFAULT 'Batangas',
  `region`     VARCHAR(50)  NOT NULL DEFAULT 'IV-A',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── schools ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `schools` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200) NOT NULL,
  `address`    VARCHAR(300) DEFAULT NULL,
  `type`       ENUM('elementary','high_school','vocational','college','graduate') NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── skills ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `skills` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL UNIQUE,
  `category`   VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── applicants ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `applicants` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED     NOT NULL UNIQUE,
  `applicant_code`    VARCHAR(20)      NOT NULL UNIQUE COMMENT 'Auto-generated unique NSRP code',
  `first_name`        VARCHAR(80)      NOT NULL,
  `middle_name`       VARCHAR(80)      DEFAULT NULL,
  `last_name`         VARCHAR(80)      NOT NULL,
  `suffix`            VARCHAR(10)      DEFAULT NULL,
  `gender`            ENUM('male','female','other') NOT NULL,
  `birthdate`         DATE             NOT NULL,
  `civil_status`      ENUM('single','married','widowed','separated','divorced') NOT NULL DEFAULT 'single',
  `contact_number`    VARCHAR(20)      DEFAULT NULL,
  `address`           TEXT             DEFAULT NULL,
  `location_id`       INT UNSIGNED     DEFAULT NULL,
  `education_level`   ENUM('elementary','high_school','vocational','college','graduate','post_graduate') DEFAULT NULL,
  `school_id`         INT UNSIGNED     DEFAULT NULL,
  `course`            VARCHAR(150)     DEFAULT NULL,
  `year_graduated`    YEAR             DEFAULT NULL,
  `experience_years`  TINYINT UNSIGNED DEFAULT 0,
  `experience_summary` TEXT            DEFAULT NULL,
  `resume_path`       VARCHAR(300)     DEFAULT NULL,
  `photo_path`        VARCHAR(300)     DEFAULT NULL,
  `status`            ENUM('pending','active','matched','referred','hired','rejected','inactive','archived')
                                       NOT NULL DEFAULT 'pending',
  `last_activity_at`  DATETIME         DEFAULT NULL,
  `registered_by`     INT UNSIGNED     DEFAULT NULL COMMENT 'user_id of admin who registered manually',
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME         DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`school_id`)   REFERENCES `schools`(`id`)   ON DELETE SET NULL,
  INDEX `idx_applicants_status` (`status`),
  INDEX `idx_applicants_code`   (`applicant_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── applicant_skills (pivot) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `applicant_skills` (
  `applicant_id` INT UNSIGNED NOT NULL,
  `skill_id`     INT UNSIGNED NOT NULL,
  `proficiency`  ENUM('beginner','intermediate','advanced','expert') NOT NULL DEFAULT 'intermediate',
  PRIMARY KEY (`applicant_id`, `skill_id`),
  FOREIGN KEY (`applicant_id`) REFERENCES `applicants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`)     REFERENCES `skills`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── employers ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL UNIQUE,
  `company_name`     VARCHAR(200) NOT NULL,
  `industry`         VARCHAR(100) DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `address`          TEXT         DEFAULT NULL,
  `location_id`      INT UNSIGNED DEFAULT NULL,
  `contact_person`   VARCHAR(150) DEFAULT NULL,
  `contact_number`   VARCHAR(20)  DEFAULT NULL,
  `website`          VARCHAR(255) DEFAULT NULL,
  `logo_path`        VARCHAR(300) DEFAULT NULL,
  `verification_status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by`      INT UNSIGNED DEFAULT NULL COMMENT 'superadmin user_id',
  `verified_at`      DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  INDEX `idx_employers_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── jobs ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `jobs` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `employer_id`         INT UNSIGNED  NOT NULL,
  `title`               VARCHAR(200)  NOT NULL,
  `description`         TEXT          NOT NULL,
  `requirements`        TEXT          DEFAULT NULL,
  `education_required`  ENUM('any','elementary','high_school','vocational','college','graduate') DEFAULT 'any',
  `experience_required` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Minimum years',
  `location_id`         INT UNSIGNED  DEFAULT NULL,
  `salary_min`          DECIMAL(12,2) DEFAULT NULL,
  `salary_max`          DECIMAL(12,2) DEFAULT NULL,
  `job_type`            ENUM('full_time','part_time','contractual','seasonal','ojt') NOT NULL DEFAULT 'full_time',
  `slots`               SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status`              ENUM('open','closed','on_hold') NOT NULL DEFAULT 'open',
  `expires_at`          DATE          DEFAULT NULL,
  `posted_by`           INT UNSIGNED  DEFAULT NULL COMMENT 'admin user_id if posted by admin',
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`          DATETIME      DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employer_id`) REFERENCES `employers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  INDEX `idx_jobs_status`   (`status`),
  INDEX `idx_jobs_employer` (`employer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── job_skills (pivot) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_skills` (
  `job_id`   INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`job_id`, `skill_id`),
  FOREIGN KEY (`job_id`)   REFERENCES `jobs`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── applications ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `applications` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_id`   INT UNSIGNED NOT NULL,
  `job_id`         INT UNSIGNED NOT NULL,
  `cover_letter`   TEXT         DEFAULT NULL,
  `status`         ENUM('submitted','under_review','matched','referred','hired','rejected','withdrawn')
                                NOT NULL DEFAULT 'submitted',
  `applied_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`    DATETIME     DEFAULT NULL,
  `reviewed_by`    INT UNSIGNED DEFAULT NULL COMMENT 'admin user_id',
  `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_application` (`applicant_id`, `job_id`),
  FOREIGN KEY (`applicant_id`) REFERENCES `applicants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)       REFERENCES `jobs`(`id`)       ON DELETE CASCADE,
  INDEX `idx_applications_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── matches ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `matches` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `applicant_id`     INT UNSIGNED   NOT NULL,
  `job_id`           INT UNSIGNED   NOT NULL,
  `application_id`   INT UNSIGNED   DEFAULT NULL,
  `skill_score`      DECIMAL(5,2)   NOT NULL DEFAULT 0.00 COMMENT 'Out of 100',
  `education_score`  DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
  `experience_score` DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
  `location_score`   DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
  `match_score`      DECIMAL(5,2)   NOT NULL DEFAULT 0.00 COMMENT 'Weighted total',
  `status`           ENUM('pending','reviewed','referred','hired','rejected') NOT NULL DEFAULT 'pending',
  `referred_by`      INT UNSIGNED   DEFAULT NULL COMMENT 'admin user_id',
  `referred_at`      DATETIME       DEFAULT NULL,
  `computed_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`applicant_id`)   REFERENCES `applicants`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)         REFERENCES `jobs`(`id`)          ON DELETE CASCADE,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`)  ON DELETE SET NULL,
  INDEX `idx_matches_score`      (`match_score`),
  INDEX `idx_matches_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `type`       VARCHAR(60)  NOT NULL COMMENT 'e.g. match_found, application_update, referral',
  `title`      VARCHAR(200) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `link`       VARCHAR(300) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── employment_records ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `employment_records` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_id` INT UNSIGNED NOT NULL,
  `employer_id`  INT UNSIGNED NOT NULL,
  `job_id`       INT UNSIGNED NOT NULL,
  `match_id`     INT UNSIGNED DEFAULT NULL,
  `hired_at`     DATE         NOT NULL,
  `end_date`     DATE         DEFAULT NULL,
  `salary`       DECIMAL(12,2) DEFAULT NULL,
  `remarks`      TEXT         DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`applicant_id`) REFERENCES `applicants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employer_id`)  REFERENCES `employers`(`id`)  ON DELETE CASCADE,
  FOREIGN KEY (`job_id`)       REFERENCES `jobs`(`id`)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── audit_logs ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. login, create_applicant, trigger_matching',
  `module`      VARCHAR(60)  DEFAULT NULL,
  `record_id`   INT UNSIGNED DEFAULT NULL,
  `description` TEXT         DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_user`   (`user_id`),
  INDEX `idx_audit_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default Super Admin seed ──────────────────────────────────
INSERT INTO `users` (`email`, `password_hash`, `role`, `is_active`, `email_verified_at`)
VALUES (
  'superadmin@pesobalayan.gov.ph',
  '$2y$12$exampleHashReplaceMeOnFirstRunWithPasswordHashFunction',
  'superadmin',
  1,
  NOW()
) ON DUPLICATE KEY UPDATE `id` = `id`;

-- ── Sample Locations ──────────────────────────────────────────
INSERT INTO `locations` (`barangay`, `municipality`, `province`) VALUES
  ('Calo',          'Balayan', 'Batangas'),
  ('Caloocan',      'Balayan', 'Batangas'),
  ('Dao',           'Balayan', 'Batangas'),
  ('Evangelista',   'Balayan', 'Batangas'),
  ('Patugo',        'Balayan', 'Batangas'),
  ('Poblacion',     'Balayan', 'Batangas'),
  ('Sampaga',       'Balayan', 'Batangas'),
  ('Santo Niño',    'Balayan', 'Batangas'),
  ('Talisay',       'Balayan', 'Batangas'),
  ('Tanggoy',       'Balayan', 'Batangas')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- ── Sample Skills ─────────────────────────────────────────────
INSERT INTO `skills` (`name`, `category`) VALUES
  ('Microsoft Office',    'Computer'),
  ('Data Entry',          'Computer'),
  ('Customer Service',    'Service'),
  ('Driving',             'Technical'),
  ('Welding',             'Technical'),
  ('Carpentry',           'Technical'),
  ('Electrical Works',    'Technical'),
  ('Plumbing',            'Technical'),
  ('Cooking',             'Service'),
  ('Sewing/Dressmaking',  'Technical'),
  ('Bookkeeping',         'Finance'),
  ('Sales',               'Business'),
  ('Communication',       'Soft Skill'),
  ('Leadership',          'Soft Skill'),
  ('Teamwork',            'Soft Skill')
ON DUPLICATE KEY UPDATE `id` = `id`;
