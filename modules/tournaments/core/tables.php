<?php
/**
 * Tournament Tables - Auto Creation
 */

function ensureTournamentTables() {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();
        
        // Check if main table exists
        $result = $db->query("SHOW TABLES LIKE 'tournaments'")->fetch();
        if ($result) return;
        
        // Create tournaments table
        $db->exec("CREATE TABLE IF NOT EXISTS `tournaments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `type` ENUM('single_elimination','double_elimination','round_robin_single','round_robin_double') NOT NULL,
            `sport_type` VARCHAR(50) DEFAULT 'كرة قدم',
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `randomize_teams` TINYINT(1) DEFAULT 1,
            `auto_generate` TINYINT(1) DEFAULT 1,
            `points_win` INT UNSIGNED DEFAULT 3,
            `points_draw` INT UNSIGNED DEFAULT 1,
            `points_loss` INT UNSIGNED DEFAULT 0,
            `status` ENUM('draft','registration','in_progress','completed','cancelled') DEFAULT 'draft',
            `winner_team_id` INT UNSIGNED DEFAULT NULL,
            `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create tournament_teams table
        $db->exec("CREATE TABLE IF NOT EXISTS `tournament_teams` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL,
            `class_id` INT UNSIGNED DEFAULT NULL,
            `team_name` VARCHAR(100) NOT NULL,
            `team_color` VARCHAR(20) DEFAULT '#10b981',
            `seed_number` INT UNSIGNED DEFAULT NULL,
            `is_eliminated` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tournament` (`tournament_id`),
            CONSTRAINT `fk_tt_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create matches table
        $db->exec("CREATE TABLE IF NOT EXISTS `matches` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL,
            `round_number` INT UNSIGNED DEFAULT 1,
            `match_number` INT UNSIGNED NOT NULL,
            `bracket_type` ENUM('main','losers','final') DEFAULT 'main',
            `team1_id` INT UNSIGNED DEFAULT NULL,
            `team2_id` INT UNSIGNED DEFAULT NULL,
            `team1_score` INT UNSIGNED DEFAULT NULL,
            `team2_score` INT UNSIGNED DEFAULT NULL,
            `winner_team_id` INT UNSIGNED DEFAULT NULL,
            `loser_team_id` INT UNSIGNED DEFAULT NULL,
            `is_bye` TINYINT(1) DEFAULT 0,
            `match_date` DATE DEFAULT NULL,
            `status` ENUM('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
            `next_match_id` INT UNSIGNED DEFAULT NULL,
            `next_match_slot` ENUM('team1','team2') DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tournament` (`tournament_id`),
            INDEX `idx_round` (`round_number`),
            CONSTRAINT `fk_m_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Create standings table
        $db->exec("CREATE TABLE IF NOT EXISTS `standings` (
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
            UNIQUE KEY `uk_tournament_team` (`tournament_id`, `team_id`),
            CONSTRAINT `fk_s_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_s_team` FOREIGN KEY (`team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    } catch (Exception $e) {
        // Silent fail - log if needed
        if (DEBUG_MODE) error_log('Tournament tables error: ' . $e->getMessage());
    }
}
