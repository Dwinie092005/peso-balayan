<?php
/**
 * PESO Balayan – Application Configuration
 * All dynamic values pulled from environment or defined constants.
 * No hardcoded credentials. Copy .env.example → .env and fill in values.
 */

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__));

// ── Database ──────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'peso_balayan');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ── Application ───────────────────────────────────────────────
define('APP_NAME',    'PESO Balayan');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'development');
define('APP_DEBUG',   APP_ENV === 'development');
define('APP_URL',     getenv('APP_URL')  ?: 'http://localhost/peso_balayan/public');
define('APP_TIMEZONE', 'Asia/Manila');

// ── Session ───────────────────────────────────────────────────
define('SESSION_NAME',     'PESO_SESSION');
define('SESSION_LIFETIME', 7200);  // 2 hours in seconds

// ── File Upload ───────────────────────────────────────────────
define('UPLOAD_PATH',     ROOT_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('ALLOWED_MIME_TYPES',  [
    'image/jpeg', 'image/png',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]);

// ── Matching Algorithm Weights ────────────────────────────────
define('MATCH_WEIGHT_SKILLS',     0.40);
define('MATCH_WEIGHT_EDUCATION',  0.30);
define('MATCH_WEIGHT_EXPERIENCE', 0.20);
define('MATCH_WEIGHT_LOCATION',   0.10);
define('MATCH_SCORE_THRESHOLD',   50.0); // minimum score to be considered a match

// ── Roles ─────────────────────────────────────────────────────
define('ROLE_APPLICANT',   'applicant');
define('ROLE_EMPLOYER',    'employer');
define('ROLE_ADMIN',       'admin');
define('ROLE_SUPER_ADMIN', 'superadmin');

// ── Routes / URLs ─────────────────────────────────────────────
define('BASE_URL', APP_URL);

// ── Mail ──────────────────────────────────────────────────────
define('MAIL_HOST',       getenv('MAIL_HOST')     ?: 'smtp.gmail.com');
define('MAIL_PORT',       getenv('MAIL_PORT')     ?: 587);
define('MAIL_USERNAME',   getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_NAME',  APP_NAME);
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: 'noreply@pesobalayan.gov.ph');
define('MAIL_ENCRYPTION', 'tls');

// ── Applicant Status ──────────────────────────────────────────
define('STATUS_PENDING',   'pending');
define('STATUS_ACTIVE',    'active');
define('STATUS_MATCHED',   'matched');
define('STATUS_REFERRED',  'referred');
define('STATUS_HIRED',     'hired');
define('STATUS_REJECTED',  'rejected');
define('STATUS_INACTIVE',  'inactive');
define('STATUS_ARCHIVED',  'archived');

// ── Inactivity Cleanup ────────────────────────────────────────
define('INACTIVITY_DAYS', 7);   // days before prompt email
define('ARCHIVE_DAYS',    14);  // days before auto-archive

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set(APP_TIMEZONE);
