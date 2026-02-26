-- ============================================================
-- PE Smart School System - Tournament Engine Module
-- Database Schema v1.0
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. TOURNAMENTS TABLE - البطولات
-- ============================================================
CREATE TABLE IF NOT EXISTS `tournaments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `type` ENUM('single_elimination', 'double_elimination', 'round_robin_single', 'round_robin_double') NOT NULL,
    `team_mode` ENUM('class_based', 'student_based') NOT NULL DEFAULT 'class_based',
    `sport_type` VARCHAR(50) DEFAULT 'كرة قدم',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `randomize_teams` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_generate` TINYINT(1) NOT NULL DEFAULT 1,
    `teams_per_match` INT UNSIGNED DEFAULT 2,
    `points_win` INT UNSIGNED DEFAULT 3,
    `points_draw` INT UNSIGNED DEFAULT 1,
    `points_loss` INT UNSIGNED DEFAULT 0,
    `status` ENUM('draft', 'registration', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    `winner_team_id` INT UNSIGNED DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TOURNAMENT_TEAMS TABLE - فرق البطولة
-- ============================================================
CREATE TABLE IF NOT EXISTS `tournament_teams` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED DEFAULT NULL,
    `team_name` VARCHAR(100) NOT NULL,
    `team_color` VARCHAR(20) DEFAULT '#10b981',
    `seed_number` INT UNSIGNED DEFAULT NULL,
    `is_eliminated` TINYINT(1) NOT NULL DEFAULT 0,
    `elimination_count` INT UNSIGNED DEFAULT 0,
    `current_round` INT UNSIGNED DEFAULT 1,
    `final_rank` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tournament` (`tournament_id`),
    INDEX `idx_class` (`class_id`),
    INDEX `idx_eliminated` (`is_eliminated`),
    CONSTRAINT `fk_tt_tournament` FOREIGN KEY (`tournament_id`) 
        REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tt_class` FOREIGN KEY (`class_id`) 
        REFERENCES `classes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. STUDENT_TEAMS TABLE - فرق الطلاب (للوضع student_based)
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_teams` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tournament_team_id` INT UNSIGNED NOT NULL,
    `team_name` VARCHAR(100) NOT NULL,
    `captain_student_id` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tournament_team` (`tournament_team_id`),
    CONSTRAINT `fk_st_tournament_team` FOREIGN KEY (`tournament_team_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_st_captain` FOREIGN KEY (`captain_student_id`) 
        REFERENCES `students`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. STUDENT_TEAM_MEMBERS TABLE - أعضاء فرق الطلاب
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_team_members` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_team_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `jersey_number` INT UNSIGNED DEFAULT NULL,
    `is_substitute` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_team_student` (`student_team_id`, `student_id`),
    INDEX `idx_student` (`student_id`),
    CONSTRAINT `fk_stm_team` FOREIGN KEY (`student_team_id`) 
        REFERENCES `student_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_stm_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. MATCHES TABLE - المباريات
-- ============================================================
CREATE TABLE IF NOT EXISTS `matches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT UNSIGNED NOT NULL,
    `round_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `match_number` INT UNSIGNED NOT NULL,
    `bracket_type` ENUM('main', 'losers', 'final', 'third_place') NOT NULL DEFAULT 'main',
    `team1_id` INT UNSIGNED DEFAULT NULL,
    `team2_id` INT UNSIGNED DEFAULT NULL,
    `team1_score` INT UNSIGNED DEFAULT NULL,
    `team2_score` INT UNSIGNED DEFAULT NULL,
    `winner_team_id` INT UNSIGNED DEFAULT NULL,
    `loser_team_id` INT UNSIGNED DEFAULT NULL,
    `is_bye` TINYINT(1) DEFAULT 0,
    `match_date` DATE DEFAULT NULL,
    `match_time` TIME DEFAULT NULL,
    `venue` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('scheduled', 'in_progress', 'completed', 'postponed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `next_match_id` INT UNSIGNED DEFAULT NULL,
    `next_match_slot` ENUM('team1', 'team2') DEFAULT NULL,
    `loser_next_match_id` INT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tournament` (`tournament_id`),
    INDEX `idx_round` (`round_number`),
    INDEX `idx_bracket` (`bracket_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_teams` (`team1_id`, `team2_id`),
    CONSTRAINT `fk_m_tournament` FOREIGN KEY (`tournament_id`) 
        REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_m_team1` FOREIGN KEY (`team1_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_m_team2` FOREIGN KEY (`team2_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_m_winner` FOREIGN KEY (`winner_team_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_m_next` FOREIGN KEY (`next_match_id`) 
        REFERENCES `matches`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. STANDINGS TABLE - جدول الترتيب (للدوري)
-- ============================================================
CREATE TABLE IF NOT EXISTS `standings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tournament_id` INT UNSIGNED NOT NULL,
    `team_id` INT UNSIGNED NOT NULL,
    `played` INT UNSIGNED DEFAULT 0,
    `wins` INT UNSIGNED DEFAULT 0,
    `draws` INT UNSIGNED DEFAULT 0,
    `losses` INT UNSIGNED DEFAULT 0,
    `goals_for` INT UNSIGNED DEFAULT 0,
    `goals_against` INT UNSIGNED DEFAULT 0,
    `goal_difference` INT DEFAULT 0,
    `points` INT UNSIGNED DEFAULT 0,
    `form` VARCHAR(20) DEFAULT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tournament_team` (`tournament_id`, `team_id`),
    INDEX `idx_points` (`points` DESC, `goal_difference` DESC, `goals_for` DESC),
    CONSTRAINT `fk_s_tournament` FOREIGN KEY (`tournament_id`) 
        REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_s_team` FOREIGN KEY (`team_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MATCH_EVENTS TABLE - أحداث المباراة (اختياري)
-- ============================================================
CREATE TABLE IF NOT EXISTS `match_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `match_id` INT UNSIGNED NOT NULL,
    `team_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED DEFAULT NULL,
    `event_type` ENUM('goal', 'own_goal', 'penalty', 'yellow_card', 'red_card', 'substitution', 'injury') NOT NULL,
    `minute` INT UNSIGNED DEFAULT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_match` (`match_id`),
    INDEX `idx_event_type` (`event_type`),
    CONSTRAINT `fk_me_match` FOREIGN KEY (`match_id`) 
        REFERENCES `matches`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_me_team` FOREIGN KEY (`team_id`) 
        REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_me_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VIEWS
-- ============================================================

-- View: Tournament with team count
CREATE OR REPLACE VIEW `v_tournaments_summary` AS
SELECT 
    t.*,
    COUNT(DISTINCT tt.id) as team_count,
    COUNT(DISTINCT m.id) as match_count,
    SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
    u.name as created_by_name
FROM tournaments t
LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
LEFT JOIN matches m ON m.tournament_id = t.id
LEFT JOIN users u ON u.id = t.created_by
GROUP BY t.id;

-- View: Standings with team names
CREATE OR REPLACE VIEW `v_standings_full` AS
SELECT 
    s.*,
    tt.team_name,
    tt.team_color,
    tt.class_id,
    c.name as class_name,
    t.name as tournament_name,
    t.type as tournament_type
FROM standings s
JOIN tournament_teams tt ON s.team_id = tt.id
JOIN tournaments t ON s.tournament_id = t.id
LEFT JOIN classes c ON tt.class_id = c.id
ORDER BY s.tournament_id, s.points DESC, s.goal_difference DESC, s.goals_for DESC;
