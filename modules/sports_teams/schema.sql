-- ============================================================
-- PE Smart School System - Sports Teams Module
-- Database Schema v1.0
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. SPORTS_TEAMS - الفرق الرياضية
-- ============================================================
CREATE TABLE IF NOT EXISTS `sports_teams` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `school_id`   INT UNSIGNED DEFAULT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `sport_type`  VARCHAR(50)  NOT NULL DEFAULT 'كرة قدم',
    `team_type`   ENUM('class','school','mixed') NOT NULL DEFAULT 'mixed',
    -- class  = منتخب الفصل (مرتبط بفصل واحد)
    -- school = منتخب المدرسة (نخبوي من جميع الفصول)
    -- mixed  = فريق مختلط (تكوّن بالقرعة أو يدوياً)
    `class_id`    INT UNSIGNED DEFAULT NULL,   -- فقط لـ team_type = class
    `coach_id`    INT UNSIGNED DEFAULT NULL,   -- معلم مسؤول عن الفريق
    `color`       VARCHAR(20)  NOT NULL DEFAULT '#10b981',
    `logo_emoji`  VARCHAR(10)  DEFAULT '⚽',
    `description` TEXT         DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_school`    (`school_id`),
    INDEX `idx_sport`     (`sport_type`),
    INDEX `idx_type`      (`team_type`),
    INDEX `idx_class`     (`class_id`),
    INDEX `idx_active`    (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TEAM_MEMBERS - أعضاء الفرق
-- ============================================================
CREATE TABLE IF NOT EXISTS `team_members` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team_id`       INT UNSIGNED NOT NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `jersey_number` INT UNSIGNED DEFAULT NULL,
    `position`      VARCHAR(50)  DEFAULT NULL,  -- حارس / مهاجم / مدافع / ...
    `status`        ENUM('active','substitute','injured','suspended') NOT NULL DEFAULT 'active',
    -- active    = لاعب أساسي
    -- substitute = احتياطي
    -- injured    = مصاب / موقوف مؤقتاً
    -- suspended  = موقوف
    `joined_at`     DATE         DEFAULT NULL,
    `notes`         TEXT         DEFAULT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_team_student`  (`team_id`, `student_id`),
    INDEX `idx_team`    (`team_id`),
    INDEX `idx_student` (`student_id`),
    INDEX `idx_status`  (`status`),
    CONSTRAINT `fk_tm_team`    FOREIGN KEY (`team_id`)    REFERENCES `sports_teams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tm_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. TRAINING_SESSIONS - جلسات التدريب
-- ============================================================
CREATE TABLE IF NOT EXISTS `training_sessions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `school_id`   INT UNSIGNED DEFAULT NULL,
    `team_id`     INT UNSIGNED NOT NULL,
    `title`       VARCHAR(150) NOT NULL DEFAULT 'تدريب عادي',
    `session_date` DATE        NOT NULL,
    `start_time`  TIME         DEFAULT NULL,
    `end_time`    TIME         DEFAULT NULL,
    `venue`       VARCHAR(100) DEFAULT NULL,
    `focus`       VARCHAR(200) DEFAULT NULL,  -- مثال: "تدريب على الهجمات المضادة"
    `notes`       TEXT         DEFAULT NULL,
    `coach_id`    INT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_school` (`school_id`),
    INDEX `idx_team` (`team_id`),
    INDEX `idx_date` (`session_date`),
    CONSTRAINT `fk_ts_team` FOREIGN KEY (`team_id`) REFERENCES `sports_teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TRAINING_ATTENDANCE - حضور التدريب
-- ============================================================
CREATE TABLE IF NOT EXISTS `training_attendance` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id`   INT UNSIGNED NOT NULL,
    `student_id`   INT UNSIGNED NOT NULL,
    `status`       ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `performance`  TINYINT UNSIGNED DEFAULT NULL, -- تقييم 1-10
    `notes`        VARCHAR(255) DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_session_student` (`session_id`, `student_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_student` (`student_id`),
    CONSTRAINT `fk_ta_session` FOREIGN KEY (`session_id`)  REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ta_student` FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
