-- ============================================================
--  SocioWatch Jateng — Database Schema + Seed Data
--  MySQL 8.0+
--  Jalankan: mysql -u root -p < sociowatch_jateng.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
--  Buat & pilih database
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `sociowatch_jateng`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sociowatch_jateng`;

-- ============================================================
--  TABEL SKEMA
-- ============================================================

-- 1. users
CREATE TABLE IF NOT EXISTS `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(255)    NOT NULL,
  `email`             VARCHAR(255)    NOT NULL UNIQUE,
  `email_verified_at` TIMESTAMP       NULL,
  `password`          VARCHAR(255)    NOT NULL,
  `role`              ENUM('superadmin','admin','viewer') NOT NULL DEFAULT 'viewer',
  `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
  `last_login_at`     TIMESTAMP       NULL,
  `remember_token`    VARCHAR(100)    NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. password_reset_tokens
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `id`            VARCHAR(255) NOT NULL,
  `user_id`       BIGINT UNSIGNED NULL,
  `ip_address`    VARCHAR(45)  NULL,
  `user_agent`    TEXT         NULL,
  `payload`       LONGTEXT     NOT NULL,
  `last_activity` INT          NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. cache
CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) NOT NULL,
  `value`      MEDIUMTEXT   NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. cache_locks
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) NOT NULL,
  `owner`      VARCHAR(255) NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. jobs
CREATE TABLE IF NOT EXISTS `jobs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue`        VARCHAR(255)    NOT NULL,
  `payload`      LONGTEXT        NOT NULL,
  `attempts`     TINYINT UNSIGNED NOT NULL,
  `reserved_at`  INT UNSIGNED    NULL,
  `available_at` INT UNSIGNED    NOT NULL,
  `created_at`   INT UNSIGNED    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. job_batches
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id`             VARCHAR(255) NOT NULL,
  `name`           VARCHAR(255) NOT NULL,
  `total_jobs`     INT          NOT NULL,
  `pending_jobs`   INT          NOT NULL,
  `failed_jobs`    INT          NOT NULL,
  `failed_job_ids` LONGTEXT     NOT NULL,
  `options`        MEDIUMTEXT   NULL,
  `cancelled_at`   INT          NULL,
  `created_at`     INT          NOT NULL,
  `finished_at`    INT          NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. failed_jobs
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`       VARCHAR(255)    NOT NULL UNIQUE,
  `connection` TEXT            NOT NULL,
  `queue`      TEXT            NOT NULL,
  `payload`    LONGTEXT        NOT NULL,
  `exception`  LONGTEXT        NOT NULL,
  `failed_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. regions
CREATE TABLE IF NOT EXISTS `regions` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255)    NOT NULL,
  `type`        ENUM('kabupaten','kota') NOT NULL,
  `latitude`    DECIMAL(10,7)   NOT NULL,
  `longitude`   DECIMAL(10,7)   NOT NULL,
  `geojson_key` VARCHAR(255)    NOT NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255)    NOT NULL,
  `color`       VARCHAR(7)      NOT NULL,
  `description` TEXT            NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. social_accounts
CREATE TABLE IF NOT EXISTS `social_accounts` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform`        ENUM('instagram','twitter','facebook','tiktok','youtube') NOT NULL,
  `username`        VARCHAR(255)    NOT NULL,
  `display_name`    VARCHAR(255)    NOT NULL,
  `profile_url`     VARCHAR(255)    NULL,
  `profile_picture` VARCHAR(255)    NULL,
  `followers_count` BIGINT          NOT NULL DEFAULT 0,
  `following_count` BIGINT          NOT NULL DEFAULT 0,
  `post_count`      INT             NOT NULL DEFAULT 0,
  `bio`             TEXT            NULL,
  `category_id`     BIGINT UNSIGNED NOT NULL,
  `region_id`       BIGINT UNSIGNED NOT NULL,
  `is_active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `notes`           TEXT            NULL,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `social_accounts_category_id_index` (`category_id`),
  KEY `social_accounts_region_id_index` (`region_id`),
  CONSTRAINT `social_accounts_category_id_foreign`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `social_accounts_region_id_foreign`
    FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. account_admins
CREATE TABLE IF NOT EXISTS `account_admins` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id` BIGINT UNSIGNED NOT NULL,
  `full_name`         VARCHAR(255)    NOT NULL,
  `alias`             VARCHAR(255)    NULL,
  `nik`               VARCHAR(20)     NULL,
  `phone`             VARCHAR(30)     NULL,
  `email`             VARCHAR(255)    NULL,
  `occupation`        VARCHAR(255)    NULL,
  `affiliation`       VARCHAR(255)    NULL,
  `notes`             TEXT            NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `account_admins_social_account_id_foreign`
    FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. activity_logs
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id` BIGINT UNSIGNED NOT NULL,
  `activity_type`     VARCHAR(255)    NOT NULL,
  `old_value`         TEXT            NULL,
  `new_value`         TEXT            NULL,
  `logged_at`         TIMESTAMP       NOT NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `activity_logs_social_account_id_foreign`
    FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. user_activity_logs
CREATE TABLE IF NOT EXISTS `user_activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `action`      VARCHAR(255)    NOT NULL,
  `target_type` VARCHAR(255)    NULL,
  `target_id`   BIGINT UNSIGNED NULL,
  `ip_address`  VARCHAR(45)     NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `user_activity_logs_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. follower_snapshots
CREATE TABLE IF NOT EXISTS `follower_snapshots` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id` BIGINT UNSIGNED NOT NULL,
  `followers_count`   BIGINT          NOT NULL DEFAULT 0,
  `following_count`   BIGINT          NOT NULL DEFAULT 0,
  `post_count`        INT             NOT NULL DEFAULT 0,
  `recorded_at`       TIMESTAMP       NOT NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `follower_snapshots_account_recorded_idx` (`social_account_id`, `recorded_at`),
  CONSTRAINT `fk_snapshot_account` FOREIGN KEY (`social_account_id`)
    REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. alerts  (social_account_id nullable → mendukung keyword_spike system alerts)
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id` BIGINT UNSIGNED NULL,
  `alert_type`        ENUM('spike_up','spike_down','milestone','keyword_spike') NOT NULL,
  `threshold_value`   BIGINT          NOT NULL DEFAULT 0,
  `current_value`     BIGINT          NOT NULL DEFAULT 0,
  `change_percent`    DECIMAL(5,2)    NOT NULL DEFAULT 0,
  `message`           TEXT            NOT NULL,
  `is_read`           TINYINT(1)      NOT NULL DEFAULT 0,
  `triggered_at`      TIMESTAMP       NOT NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `alerts_is_read_triggered_idx` (`is_read`, `triggered_at`),
  CONSTRAINT `fk_alert_account` FOREIGN KEY (`social_account_id`)
    REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. alert_settings
CREATE TABLE IF NOT EXISTS `alert_settings` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id`        BIGINT UNSIGNED NULL UNIQUE,
  `spike_up_threshold`       DECIMAL(5,2)    NOT NULL DEFAULT 10.00,
  `spike_down_threshold`     DECIMAL(5,2)    NOT NULL DEFAULT 10.00,
  `milestone_values`         JSON            NULL,
  `keyword_spike_threshold`  INT             NOT NULL DEFAULT 50,
  `is_active`                TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`               TIMESTAMP       NULL,
  `updated_at`               TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_alertsetting_account` FOREIGN KEY (`social_account_id`)
    REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default global alert setting (social_account_id = NULL)
INSERT IGNORE INTO `alert_settings`
  (`social_account_id`, `spike_up_threshold`, `spike_down_threshold`, `milestone_values`, `keyword_spike_threshold`, `is_active`, `created_at`, `updated_at`)
VALUES
  (NULL, 10.00, 10.00, '[1000, 5000, 10000, 50000, 100000]', 50, 1, NOW(), NOW());

-- 18. personal_access_tokens (Laravel Sanctum)
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255)    NOT NULL,
  `tokenable_id`   BIGINT UNSIGNED NOT NULL,
  `name`           VARCHAR(255)    NOT NULL,
  `token`          VARCHAR(64)     NOT NULL UNIQUE,
  `abilities`      TEXT            NULL,
  `last_used_at`   TIMESTAMP       NULL,
  `expires_at`     TIMESTAMP       NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. posts
CREATE TABLE IF NOT EXISTS `posts` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `social_account_id` BIGINT UNSIGNED NOT NULL,
  `platform`          ENUM('instagram','twitter','facebook','tiktok','youtube') NOT NULL,
  `post_url`          VARCHAR(255)    NULL,
  `content`           TEXT            NOT NULL,
  `media_type`        ENUM('text','image','video','reel','story') NULL,
  `likes_count`       BIGINT          NOT NULL DEFAULT 0,
  `comments_count`    BIGINT          NOT NULL DEFAULT 0,
  `shares_count`      BIGINT          NOT NULL DEFAULT 0,
  `views_count`       BIGINT          NOT NULL DEFAULT 0,
  `posted_at`         TIMESTAMP       NOT NULL,
  `is_flagged`        TINYINT(1)      NOT NULL DEFAULT 0,
  `flag_reason`       TEXT            NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `posts_account_posted_idx` (`social_account_id`, `posted_at`),
  INDEX `posts_is_flagged_index` (`is_flagged`),
  FULLTEXT INDEX `posts_content_fulltext` (`content`),
  CONSTRAINT `posts_social_account_id_foreign`
    FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. post_tags
CREATE TABLE IF NOT EXISTS `post_tags` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255)    NOT NULL UNIQUE,
  `color`      VARCHAR(7)      NOT NULL DEFAULT '#6366f1',
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. post_tag_pivot
CREATE TABLE IF NOT EXISTS `post_tag_pivot` (
  `post_id`     BIGINT UNSIGNED NOT NULL,
  `post_tag_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `post_tag_id`),
  CONSTRAINT `post_tag_pivot_post_id_foreign`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_tag_pivot_post_tag_id_foreign`
    FOREIGN KEY (`post_tag_id`) REFERENCES `post_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. keywords
CREATE TABLE IF NOT EXISTS `keywords` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `word`       VARCHAR(255)    NOT NULL UNIQUE,
  `category`   ENUM('sensitif','negatif','netral','positif') NOT NULL,
  `color`      VARCHAR(7)      NOT NULL DEFAULT '#6b7280',
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  INDEX `keywords_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. post_keyword_pivot
CREATE TABLE IF NOT EXISTS `post_keyword_pivot` (
  `post_id`          BIGINT UNSIGNED NOT NULL,
  `keyword_id`       BIGINT UNSIGNED NOT NULL,
  `occurrence_count` INT             NOT NULL DEFAULT 1,
  PRIMARY KEY (`post_id`, `keyword_id`),
  CONSTRAINT `post_keyword_pivot_post_id_foreign`
    FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_keyword_pivot_keyword_id_foreign`
    FOREIGN KEY (`keyword_id`) REFERENCES `keywords` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. migrations (table Laravel internal)
CREATE TABLE IF NOT EXISTS `migrations` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch`     INT          NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DATA SEED
-- ============================================================

-- ------------------------------------------------------------
--  Users (password di-hash dengan bcrypt)
--  superadmin: admin123 | admin: operator123 | viewer: viewer123
-- ------------------------------------------------------------
INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
('Super Admin', 'admin@sociowatch.id',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, NOW(), NOW()),
('Operator',    'operator@sociowatch.id', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',      1, NOW(), NOW()),
('Viewer',      'viewer@sociowatch.id',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer',     1, NOW(), NOW());

-- Catatan: hash di atas adalah placeholder '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' (password: "password")
-- PASTIKAN menjalankan `php artisan db:seed --class=UserSeeder` untuk hash yang benar,
-- ATAU update manual password setelah import dengan:
--   UPDATE users SET password = '$2y$12$...' WHERE email = 'admin@sociowatch.id';

-- ------------------------------------------------------------
--  Regions — 35 Kab/Kota Jawa Tengah
-- ------------------------------------------------------------
INSERT INTO `regions` (`name`, `type`, `latitude`, `longitude`, `geojson_key`, `created_at`, `updated_at`) VALUES
-- 29 Kabupaten
('Kabupaten Banjarnegara', 'kabupaten', -7.3907000, 109.6858000, 'BANJARNEGARA', NOW(), NOW()),
('Kabupaten Banyumas',     'kabupaten', -7.5151000, 109.2947000, 'BANYUMAS',     NOW(), NOW()),
('Kabupaten Batang',       'kabupaten', -6.9143000, 109.7293000, 'BATANG',       NOW(), NOW()),
('Kabupaten Blora',        'kabupaten', -6.9627000, 111.4140000, 'BLORA',        NOW(), NOW()),
('Kabupaten Boyolali',     'kabupaten', -7.5325000, 110.5993000, 'BOYOLALI',     NOW(), NOW()),
('Kabupaten Brebes',       'kabupaten', -6.8798000, 108.9047000, 'BREBES',       NOW(), NOW()),
('Kabupaten Cilacap',      'kabupaten', -7.7232000, 109.0147000, 'CILACAP',      NOW(), NOW()),
('Kabupaten Demak',        'kabupaten', -6.8944000, 110.6381000, 'DEMAK',        NOW(), NOW()),
('Kabupaten Grobogan',     'kabupaten', -7.0126000, 110.9189000, 'GROBOGAN',     NOW(), NOW()),
('Kabupaten Jepara',       'kabupaten', -6.5892000, 110.6718000, 'JEPARA',       NOW(), NOW()),
('Kabupaten Karanganyar',  'kabupaten', -7.5958000, 111.0300000, 'KARANGANYAR',  NOW(), NOW()),
('Kabupaten Kebumen',      'kabupaten', -7.6820000, 109.6527000, 'KEBUMEN',      NOW(), NOW()),
('Kabupaten Kendal',       'kabupaten', -7.0232000, 110.2017000, 'KENDAL',       NOW(), NOW()),
('Kabupaten Klaten',       'kabupaten', -7.7070000, 110.6017000, 'KLATEN',       NOW(), NOW()),
('Kabupaten Kudus',        'kabupaten', -6.8048000, 110.8369000, 'KUDUS',        NOW(), NOW()),
('Kabupaten Magelang',     'kabupaten', -7.5479000, 110.2179000, 'MAGELANG',     NOW(), NOW()),
('Kabupaten Pati',         'kabupaten', -6.7478000, 111.0384000, 'PATI',         NOW(), NOW()),
('Kabupaten Pekalongan',   'kabupaten', -7.0883000, 109.6640000, 'PEKALONGAN',   NOW(), NOW()),
('Kabupaten Pemalang',     'kabupaten', -6.9078000, 109.3793000, 'PEMALANG',     NOW(), NOW()),
('Kabupaten Purbalingga',  'kabupaten', -7.3903000, 109.3647000, 'PURBALINGGA',  NOW(), NOW()),
('Kabupaten Purworejo',    'kabupaten', -7.7132000, 110.0178000, 'PURWOREJO',    NOW(), NOW()),
('Kabupaten Rembang',      'kabupaten', -6.7059000, 111.3412000, 'REMBANG',      NOW(), NOW()),
('Kabupaten Semarang',     'kabupaten', -7.2271000, 110.4280000, 'SEMARANG',     NOW(), NOW()),
('Kabupaten Sragen',       'kabupaten', -7.4254000, 111.0186000, 'SRAGEN',       NOW(), NOW()),
('Kabupaten Sukoharjo',    'kabupaten', -7.6868000, 110.8316000, 'SUKOHARJO',    NOW(), NOW()),
('Kabupaten Tegal',        'kabupaten', -7.0000000, 109.1400000, 'TEGAL',        NOW(), NOW()),
('Kabupaten Temanggung',   'kabupaten', -7.3167000, 110.1667000, 'TEMANGGUNG',   NOW(), NOW()),
('Kabupaten Wonogiri',     'kabupaten', -7.8167000, 110.9167000, 'WONOGIRI',     NOW(), NOW()),
('Kabupaten Wonosobo',     'kabupaten', -7.3613000, 109.9027000, 'WONOSOBO',     NOW(), NOW()),
-- 6 Kota
('Kota Magelang',   'kota', -7.4797000, 110.2177000, 'KOTA MAGELANG',   NOW(), NOW()),
('Kota Pekalongan', 'kota', -6.8886000, 109.6753000, 'KOTA PEKALONGAN', NOW(), NOW()),
('Kota Salatiga',   'kota', -7.3306000, 110.5084000, 'KOTA SALATIGA',   NOW(), NOW()),
('Kota Semarang',   'kota', -6.9932000, 110.4203000, 'KOTA SEMARANG',   NOW(), NOW()),
('Kota Surakarta',  'kota', -7.5755000, 110.8243000, 'KOTA SURAKARTA',  NOW(), NOW()),
('Kota Tegal',      'kota', -6.8694000, 109.1402000, 'KOTA TEGAL',      NOW(), NOW());

-- ------------------------------------------------------------
--  Categories
-- ------------------------------------------------------------
INSERT INTO `categories` (`name`, `color`, `description`, `created_at`, `updated_at`) VALUES
('Mahasiswa', '#3B82F6', 'Akun media sosial milik organisasi atau individu mahasiswa',            NOW(), NOW()),
('Buruh',     '#EF4444', 'Akun media sosial terkait serikat buruh dan tenaga kerja',              NOW(), NOW()),
('LSM',       '#10B981', 'Lembaga Swadaya Masyarakat dan organisasi non-profit',                  NOW(), NOW()),
('Media',     '#F59E0B', 'Akun media online, jurnalisme warga, dan pers lokal',                   NOW(), NOW()),
('Ormas',     '#8B5CF6', 'Organisasi kemasyarakatan dan komunitas daerah',                        NOW(), NOW());

-- ------------------------------------------------------------
--  Social Accounts (20 data contoh)
--  region_id mengacu pada urutan INSERT regions di atas:
--    1=Banjarnegara … 29=Wonosobo, 30=Kota Magelang,
--    31=Kota Pekalongan, 32=Kota Salatiga, 33=Kota Semarang,
--    34=Kota Surakarta, 35=Kota Tegal
-- ------------------------------------------------------------
INSERT INTO `social_accounts`
  (`platform`,`username`,`display_name`,`profile_url`,`followers_count`,`following_count`,`post_count`,`bio`,`category_id`,`region_id`,`is_active`,`created_at`,`updated_at`)
VALUES
('instagram','bem_undip_official',      'BEM Universitas Diponegoro',    'https://instagram.com/bem_undip_official',  45200, 312,  874,  'Badan Eksekutif Mahasiswa UNDIP Semarang',                  1, 33, 1, NOW(), NOW()),
('twitter',  'spsi_jateng',             'SPSI Jawa Tengah',              'https://twitter.com/spsi_jateng',           12800, 540,  2310, 'Serikat Pekerja Seluruh Indonesia - Jawa Tengah',           2, 33, 1, NOW(), NOW()),
('facebook', 'walhi.jateng',            'WALHI Jawa Tengah',             'https://facebook.com/walhi.jateng',         28500, 180,  3450, 'Wahana Lingkungan Hidup Indonesia - Jawa Tengah',           3, 33, 1, NOW(), NOW()),
('youtube',  'JawaPosTV',               'Jawa Pos TV Semarang',          'https://youtube.com/@JawaPosTV',            98700, 0,    1250, 'Berita terkini Jawa Tengah dari Jawa Pos',                  4, 33, 1, NOW(), NOW()),
('instagram','nu_jateng',               'Nahdlatul Ulama Jawa Tengah',   'https://instagram.com/nu_jateng',           67300, 420,  5600, 'Akun resmi PWNU Jawa Tengah',                               5, 33, 1, NOW(), NOW()),
('instagram','bem_ums_solo',            'BEM UMS Surakarta',             'https://instagram.com/bem_ums_solo',        23100, 278,  621,  'BEM Universitas Muhammadiyah Surakarta',                    1, 34, 1, NOW(), NOW()),
('tiktok',   'solopos_update',          'Solopos Update',                'https://tiktok.com/@solopos_update',        142000,85,   980,  'Berita Solo dan Jawa Tengah',                               4, 34, 1, NOW(), NOW()),
('facebook', 'lksbhi.semarang',         'LKS BHI Semarang',             'https://facebook.com/lksbhi.semarang',      8900,  320,  1120, 'Lembaga Kajian dan Studi Buruh & HAM Indonesia',            2, 7,  1, NOW(), NOW()),
('instagram','yayasan_samin_blora',     'Yayasan Samin Blora',           'https://instagram.com/yayasan_samin_blora', 5400,  190,  430,  'Pelestarian budaya dan kearifan lokal Blora',               3, 4,  1, NOW(), NOW()),
('twitter',  'pbnu_kudus',              'PCNU Kudus',                    'https://twitter.com/pbnu_kudus',            9800,  450,  1870, 'Pengurus Cabang Nahdlatul Ulama Kudus',                     5, 15, 1, NOW(), NOW()),
('instagram','bem_unsoed_purwokerto',   'BEM UNSOED Purwokerto',         'https://instagram.com/bem_unsoed_purwokerto',31500, 195,  712,  'BEM Universitas Jenderal Soedirman Purwokerto',             1, 2,  1, NOW(), NOW()),
('youtube',  'cilacap_news',            'Cilacap News TV',               'https://youtube.com/@cilacap_news',         34500, 0,    560,  'Media berita online Cilacap dan sekitarnya',                4, 7,  1, NOW(), NOW()),
('facebook', 'komunitas.petani.kebumen','Komunitas Petani Kebumen',      'https://facebook.com/komunitas.petani.kebumen',7200, 510,  980,  'Wadah aspirasi dan informasi petani Kebumen',               2, 12, 1, NOW(), NOW()),
('tiktok',   'pati_raya_updates',       'Pati Raya Updates',             'https://tiktok.com/@pati_raya_updates',     58000, 120,  345,  'Informasi viral Pati dan sekitarnya',                       4, 17, 1, NOW(), NOW()),
('instagram','muhammadiyah_temanggung', 'PDM Temanggung',                'https://instagram.com/muhammadiyah_temanggung',6700, 290, 520,  'Pimpinan Daerah Muhammadiyah Temanggung',                   5, 27, 1, NOW(), NOW()),
('twitter',  'lpbi_jepara',             'LPBI Jepara',                   'https://twitter.com/lpbi_jepara',           4300,  380,  760,  'Lembaga Pemberdayaan Buruh dan Industri Jepara',            2, 10, 1, NOW(), NOW()),
('instagram','bem_untidar_magelang',    'BEM UNTIDAR Magelang',          'https://instagram.com/bem_untidar_magelang', 18900, 210, 490,  'BEM Universitas Tidar Magelang',                            1, 30, 1, NOW(), NOW()),
('facebook', 'lkm.wonogiri',            'LKM Wonogiri Berdaya',          'https://facebook.com/lkm.wonogiri',         3800,  260,  320,  'Lembaga Kemandirian Masyarakat Wonogiri',                   3, 28, 1, NOW(), NOW()),
('tiktok',   'semarang_viral',          'Semarang Viral',                'https://tiktok.com/@semarang_viral',        215000,98,   1230, 'Konten viral dan berita Semarang',                          4, 23, 1, NOW(), NOW()),
('instagram','fspmi_tegal',             'FSPMI Tegal Raya',              'https://instagram.com/fspmi_tegal',         11200, 430,  870,  'Federasi Serikat Pekerja Metal Indonesia - Tegal',          2, 35, 1, NOW(), NOW());

-- ------------------------------------------------------------
--  migrations (track state untuk Laravel)
-- ------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table',                      1),
('0001_01_01_000001_create_cache_table',                      1),
('0001_01_01_000002_create_jobs_table',                       1),
('2026_05_02_203808_create_regions_table',                    1),
('2026_05_02_203809_create_categories_table',                 1),
('2026_05_02_203809_create_social_accounts_table',            1),
('2026_05_02_203809_create_account_admins_table',             1),
('2026_05_02_203809_create_activity_logs_table',              1),
('2026_05_03_101319_add_role_to_users_table',                 2),
('2026_05_03_101319_create_user_activity_logs_table',         2),
('2026_05_03_143546_create_follower_snapshots_table',         3),
('2026_05_03_143547_create_alerts_table',                     3),
('2026_05_03_143547_create_alert_settings_table',             3),
('2026_05_03_144509_create_personal_access_tokens_table',     3),
('2026_05_03_200001_create_posts_table',                      4),
('2026_05_03_200002_create_post_tags_table',                  4),
('2026_05_03_200003_create_keywords_table',                   4),
('2026_05_03_200004_add_fulltext_index_to_posts',             4),
('2026_05_03_200005_add_keyword_spike_to_alert_settings',     4),
('2026_05_03_200006_add_keyword_spike_to_alerts_enum',        4),
('2026_05_03_200007_make_alerts_account_nullable',            4);

SET foreign_key_checks = 1;

-- ============================================================
--  SELESAI
--  Setelah import, jalankan:
--    php artisan db:seed --class=UserSeeder
--  untuk membuat password yang benar di tabel users.
-- ============================================================
