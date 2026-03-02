-- ============================================================
-- PE Smart School System - Database Schema v2.1
-- Compatible with MySQL 5.7+ / MariaDB 10.2+
-- Hosting: Shared Hosting (Contabo / cPanel)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE';

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin','teacher','viewer') NOT NULL DEFAULT 'teacher',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. GRADES TABLE
-- ============================================================
DROP TABLE IF EXISTS `grades`;
CREATE TABLE `grades` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `sort_order` INT UNSIGNED DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`active`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. CLASSES TABLE
-- ============================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `grade_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `section` VARCHAR(10) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_grade` (`grade_id`),
    INDEX `idx_active` (`active`),
    CONSTRAINT `fk_classes_grade` FOREIGN KEY (`grade_id`) 
        REFERENCES `grades`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. STUDENTS TABLE (مع الحقول الجديدة)
-- ============================================================
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `student_number` VARCHAR(20) NOT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `blood_type` ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
    `guardian_phone` VARCHAR(20) DEFAULT NULL,
    `medical_notes` TEXT DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_student_number` (`student_number`),
    INDEX `idx_class` (`class_id`),
    INDEX `idx_active` (`active`),
    INDEX `idx_name` (`name`),
    INDEX `idx_dob` (`date_of_birth`),
    CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) 
        REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. STUDENT MEASUREMENTS TABLE - القياسات الجسمية الدورية
-- ============================================================
DROP TABLE IF EXISTS `student_measurements`;
CREATE TABLE `student_measurements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `measurement_date` DATE NOT NULL,
    `height_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'الطول بالسنتيمتر',
    `weight_kg` DECIMAL(5,1) DEFAULT NULL COMMENT 'الوزن بالكيلوجرام',
    `bmi` DECIMAL(4,1) DEFAULT NULL COMMENT 'مؤشر كتلة الجسم - يحسب تلقائياً',
    `bmi_category` ENUM('underweight','normal','overweight','obese') DEFAULT NULL,
    `waist_cm` DECIMAL(5,1) DEFAULT NULL COMMENT 'محيط الخصر بالسنتيمتر',
    `resting_heart_rate` INT UNSIGNED DEFAULT NULL COMMENT 'نبض القلب أثناء الراحة',
    `notes` TEXT DEFAULT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_student` (`student_id`),
    INDEX `idx_date` (`measurement_date`),
    INDEX `idx_bmi` (`bmi_category`),
    CONSTRAINT `fk_measurements_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_measurements_user` FOREIGN KEY (`recorded_by`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='القياسات الجسمية الدورية';

-- ============================================================
-- 6. STUDENT HEALTH TABLE - الحالة الصحية
-- ============================================================
DROP TABLE IF EXISTS `student_health`;
CREATE TABLE `student_health` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `condition_type` ENUM('asthma','diabetes','heart','allergy','bones','vision','exemption','other') NOT NULL,
    `condition_name` VARCHAR(150) NOT NULL,
    `severity` ENUM('mild','moderate','severe') NOT NULL DEFAULT 'mild',
    `notes` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL COMMENT 'للحالات المؤقتة',
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_student` (`student_id`),
    INDEX `idx_type` (`condition_type`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_severity` (`severity`),
    CONSTRAINT `fk_health_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_health_user` FOREIGN KEY (`recorded_by`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='الحالة الصحية للطلاب';

-- ============================================================
-- 7. ATTENDANCE TABLE
-- ============================================================
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('present','absent','late') NOT NULL DEFAULT 'present',
    `notes` VARCHAR(255) DEFAULT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_student_date` (`student_id`, `attendance_date`),
    INDEX `idx_date` (`attendance_date`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_attendance_user` FOREIGN KEY (`recorded_by`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. FITNESS TESTS TABLE
-- ============================================================
DROP TABLE IF EXISTS `fitness_tests`;
CREATE TABLE `fitness_tests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `unit` VARCHAR(30) NOT NULL,
    `type` ENUM('higher_better','lower_better') NOT NULL DEFAULT 'higher_better',
    `max_score` INT UNSIGNED NOT NULL DEFAULT 10,
    `description` TEXT DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. STUDENT FITNESS RESULTS
-- ============================================================
DROP TABLE IF EXISTS `student_fitness`;
CREATE TABLE `student_fitness` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `test_id` INT UNSIGNED NOT NULL,
    `value` DECIMAL(10,2) NOT NULL,
    `score` INT UNSIGNED NOT NULL DEFAULT 0,
    `test_date` DATE NOT NULL,
    `recorded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_student_test` (`student_id`, `test_id`),
    INDEX `idx_test` (`test_id`),
    INDEX `idx_score` (`score`),
    CONSTRAINT `fk_fitness_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fitness_test` FOREIGN KEY (`test_id`) 
        REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fitness_user` FOREIGN KEY (`recorded_by`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. CLASS POINTS TABLE
-- ============================================================
DROP TABLE IF EXISTS `class_points`;
CREATE TABLE `class_points` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT UNSIGNED NOT NULL,
    `total_score` DECIMAL(10,2) DEFAULT 0,
    `average_score` DECIMAL(5,2) DEFAULT 0,
    `total_points` INT UNSIGNED DEFAULT 0,
    `students_count` INT UNSIGNED DEFAULT 0,
    `rank_position` INT UNSIGNED DEFAULT 0,
    `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_class` (`class_id`),
    CONSTRAINT `fk_points_class` FOREIGN KEY (`class_id`) 
        REFERENCES `classes`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. ACTIVITY LOG TABLE
-- ============================================================
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`),
    CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VIEWS
-- ============================================================

CREATE OR REPLACE VIEW `v_students_full` AS
SELECT 
    s.id, s.name, s.student_number, s.class_id, s.active,
    s.date_of_birth, s.blood_type, s.guardian_phone, s.medical_notes,
    c.name AS class_name, c.section, c.grade_id,
    g.name AS grade_name, g.code AS grade_code,
    CONCAT(g.name, ' - ', c.name) AS full_class_name,
    TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
FROM students s
JOIN classes c ON s.class_id = c.id
JOIN grades g ON c.grade_id = g.id;

CREATE OR REPLACE VIEW `v_class_rankings` AS
SELECT 
    c.id AS class_id, c.name AS class_name, g.name AS grade_name,
    CONCAT(g.name, ' - ', c.name) AS full_name,
    COUNT(DISTINCT s.id) AS students_count,
    COALESCE(AVG(sf.score), 0) AS avg_score,
    COALESCE(SUM(sf.score), 0) AS total_score,
    ROUND(COALESCE(AVG(sf.score), 0) * 10) AS points
FROM classes c
JOIN grades g ON c.grade_id = g.id
LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
LEFT JOIN student_fitness sf ON sf.student_id = s.id
WHERE c.active = 1
GROUP BY c.id ORDER BY avg_score DESC;

CREATE OR REPLACE VIEW `v_student_latest_measurements` AS
SELECT sm.*
FROM student_measurements sm
INNER JOIN (
    SELECT student_id, MAX(measurement_date) as latest_date
    FROM student_measurements
    GROUP BY student_id
) latest ON sm.student_id = latest.student_id AND sm.measurement_date = latest.latest_date;
