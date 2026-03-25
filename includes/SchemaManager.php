<?php
/**
 * PE Smart School System — Schema Manager
 * ========================================
 * Extension point: يمكن إضافة auto-migration هنا مستقبلاً.
 * حالياً: إدارة المخطط تتم عبر ملفات SQL يدوياً.
 *
 * كيفية التوسع:
 *   1. أضف ملف migration في migrations/ (مثل: 001_add_xyz_column.php)
 *   2. نفذه هنا عبر ensureSchema() بشرط التحقق من الإصدار الحالي
 */
class SchemaManager
{
    public static function ensureSchema(): void
    {
        $db = getDB();
        
        try {
            // 0. Ensure system_settings exists to track version
            $db->exec("
                CREATE TABLE IF NOT EXISTS `system_settings` (
                    `key` varchar(50) NOT NULL,
                    `value` text DEFAULT NULL,
                    PRIMARY KEY (`key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");

            // Get current DB version
            $stmt = $db->prepare("SELECT `value` FROM `system_settings` WHERE `key` = 'db_version'");
            $stmt->execute();
            $dbVersion = $stmt->fetchColumn() ?: '0.0.0';

            // If we are already at the target version, skip migrations
            if (version_compare($dbVersion, APP_VERSION, '>=')) {
                return;
            }

            // --- ALL MIGRATIONS GO HERE ---

            // Migration Phase 1 (Initial Weights & Assessments) - v2.5.0
            if (version_compare($dbVersion, '2.5.0', '<')) {
                // 1. Create school_grading_weights if not exists
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `school_grading_weights` (
                        `school_id` int(10) unsigned NOT NULL,
                        `attendance_pct` tinyint(4) DEFAULT 20,
                        `uniform_pct` tinyint(4) DEFAULT 20,
                        `behavior_skills_pct` tinyint(4) DEFAULT 20,
                        `participation_pct` tinyint(3) unsigned DEFAULT 0,
                        `fitness_pct` tinyint(4) DEFAULT 40,
                        `quiz_pct` tinyint(3) unsigned DEFAULT 0,
                        `project_pct` tinyint(3) unsigned DEFAULT 0,
                        `final_exam_pct` tinyint(3) unsigned DEFAULT 0,
                        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        `quiz_max` int(11) DEFAULT 10,
                        `project_max` int(11) DEFAULT 10,
                        `final_exam_max` int(11) DEFAULT 10,
                        PRIMARY KEY (`school_id`),
                        CONSTRAINT `school_grading_weights_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");

                // 2. Create student_assessments if not exists
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `student_assessments` (
                        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                        `student_id` int(10) unsigned NOT NULL,
                        `type` enum('quiz','project','final_exam') NOT NULL,
                        `title` varchar(150) DEFAULT NULL,
                        `score` decimal(5,2) NOT NULL DEFAULT 0.00,
                        `max_score` decimal(5,2) NOT NULL DEFAULT 10.00,
                        `assessment_date` date NOT NULL,
                        `recorded_by` int(10) unsigned DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `idx_student_type` (`student_id`,`type`),
                        KEY `idx_sa_student` (`student_id`),
                        KEY `idx_sa_type` (`type`),
                        KEY `idx_sa_date` (`assessment_date`),
                        CONSTRAINT `fk_sa_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // --- END OF MIGRATIONS ---

            // Update schema version in DB
            $stmt = $db->prepare("INSERT INTO `system_settings` (`key`, `value`) VALUES ('db_version', :v) ON DUPLICATE KEY UPDATE `value` = :v");
            $stmt->execute([':v' => APP_VERSION]);

        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("SchemaManager Error (v" . APP_VERSION . "): " . $e->getMessage());
            }
        }
    }
}
