<?php
/**
 * PE Smart School System - API Router v2.1
 * ==========================================
 * هذا الملف يعمل كراوتر فقط
 * الوظائف موزعة في مجلد api/
 */

require_once 'config.php';

// Resolve current tenant (school) context
Tenant::resolve();

// Set JSON headers ONLY for non-file-download actions
$action = getParam('action', '');
if ($action !== 'students_template') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

// ============================================================
// AUTO-MIGRATE: Ensure all required tables & columns exist
// ============================================================
function ensureSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    
    try {
        $db = getDB();
        
        // Check & add missing columns to students table
        $cols = array_column($db->query("SHOW COLUMNS FROM students")->fetchAll(), 'Field');
        $alter = [];
        if (!in_array('date_of_birth', $cols)) $alter[] = "ADD COLUMN `date_of_birth` DATE DEFAULT NULL";
        if (!in_array('blood_type', $cols)) $alter[] = "ADD COLUMN `blood_type` VARCHAR(5) DEFAULT NULL";
        if (!in_array('guardian_phone', $cols)) $alter[] = "ADD COLUMN `guardian_phone` VARCHAR(20) DEFAULT NULL";
        if (!in_array('medical_notes', $cols)) $alter[] = "ADD COLUMN `medical_notes` TEXT DEFAULT NULL";
        if (!empty($alter)) $db->exec("ALTER TABLE students " . implode(", ", $alter));
        
        // Create student_measurements table if not exists
        $db->exec("CREATE TABLE IF NOT EXISTS `student_measurements` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `measurement_date` DATE NOT NULL,
            `height_cm` DECIMAL(5,1) DEFAULT NULL,
            `weight_kg` DECIMAL(5,1) DEFAULT NULL,
            `bmi` DECIMAL(4,1) DEFAULT NULL,
            `bmi_category` ENUM('underweight','normal','overweight','obese') DEFAULT NULL,
            `waist_cm` DECIMAL(5,1) DEFAULT NULL,
            `resting_heart_rate` INT UNSIGNED DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sm_student` (`student_id`),
            INDEX `idx_sm_date` (`measurement_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Create student_health table if not exists
        $db->exec("CREATE TABLE IF NOT EXISTS `student_health` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `condition_type` ENUM('asthma','diabetes','heart','allergy','bones','vision','exemption','other') NOT NULL,
            `condition_name` VARCHAR(150) NOT NULL,
            `severity` ENUM('mild','moderate','severe') NOT NULL DEFAULT 'mild',
            `notes` TEXT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sh_student` (`student_id`),
            INDEX `idx_sh_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Multi-Teacher: ensure teacher_classes table exists ──────────
        $db->exec("CREATE TABLE IF NOT EXISTS `teacher_classes` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `teacher_id`   INT UNSIGNED NOT NULL,
            `class_id`     INT UNSIGNED NOT NULL,
            `is_temporary` TINYINT(1)   NOT NULL DEFAULT 0,
            `assigned_by`  INT UNSIGNED DEFAULT NULL,
            `assigned_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            `expires_at`   DATE         DEFAULT NULL,
            UNIQUE KEY `uk_teacher_class` (`teacher_id`, `class_id`),
            INDEX `idx_tc_parent` (`teacher_id`),
            INDEX `idx_tc_class`   (`class_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Parent Portal: ensure parents table exists ──────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `parents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_parent_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Parent Portal: ensure parent_students table exists ──────────
        $db->exec("CREATE TABLE IF NOT EXISTS `parent_students` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id`  INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_parent_student` (`parent_id`, `student_id`),
            INDEX `idx_ps_parent` (`parent_id`),
            INDEX `idx_ps_student` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Ensure users.role EXCLUDES 'parent' ─────────────────────────
        $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','teacher','viewer','supervisor') NOT NULL DEFAULT 'teacher'");

        // ── Notification System ─────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id` INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED DEFAULT NULL,
            `type` ENUM('attendance', 'fitness', 'health', 'general') NOT NULL DEFAULT 'general',
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_notif_parent` (`parent_id`),
            INDEX `idx_notif_read` (`parent_id`, `is_read`),
            CONSTRAINT `fk_notif_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_notif_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Badges System ───────────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `badges` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`           VARCHAR(100) NOT NULL,
            `description`    TEXT DEFAULT NULL,
            `icon`           VARCHAR(50) NOT NULL,
            `color`          VARCHAR(50) NOT NULL,
            `badge_type`     ENUM('manual', 'attendance_100', 'fitness_pro', 'improvement') NOT NULL DEFAULT 'manual',
            `criteria_value` DECIMAL(10,2) DEFAULT NULL,
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `student_badges` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `badge_id`   INT UNSIGNED NOT NULL,
            `awarded_by` INT UNSIGNED DEFAULT NULL,
            `awarded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `notes`      TEXT DEFAULT NULL,
            UNIQUE KEY `uk_student_badge` (`student_id`, `badge_id`),
            CONSTRAINT `fk_sb_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sb_badge`   FOREIGN KEY (`badge_id`)   REFERENCES `badges`(`id`)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Password Resets ─────────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `email` VARCHAR(150) NOT NULL,
            `user_type` VARCHAR(50) NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `otp` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pr_email` (`email`),
            INDEX `idx_pr_otp` (`otp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Ensure classes.created_by column exists ─────────────────────
        $classCols = array_column($db->query("SHOW COLUMNS FROM classes")->fetchAll(), 'Field');
        if (!in_array('created_by', $classCols)) {
            $db->exec("ALTER TABLE `classes` ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL");
        }

        // ── Fitness Criteria (Automated Scoring) ───────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `fitness_criteria` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `test_id` INT UNSIGNED NOT NULL,
            `min_value` DECIMAL(10,2) NOT NULL,
            `max_value` DECIMAL(10,2) NOT NULL,
            `score` INT NOT NULL,
            CONSTRAINT `fk_criteria_test_auto` FOREIGN KEY (`test_id`) REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ── Sports Calendar ─────────────────────────────────────────────
        $db->exec("CREATE TABLE IF NOT EXISTS `sports_calendar` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `event_date` DATE NOT NULL,
            `end_date` DATE DEFAULT NULL,
            `start_time` TIME DEFAULT NULL,
            `end_time` TIME DEFAULT NULL,
            `event_type` ENUM('match','training','tournament','fitness','ceremony','meeting','holiday','other') NOT NULL DEFAULT 'other',
            `location` VARCHAR(255) DEFAULT NULL,
            `color` VARCHAR(20) DEFAULT '#10b981',
            `icon` VARCHAR(50) DEFAULT '📅',
            `is_recurring` TINYINT(1) DEFAULT 0,
            `recurrence_pattern` VARCHAR(50) DEFAULT NULL,
            `target_grades` VARCHAR(255) DEFAULT NULL,
            `is_public` TINYINT(1) DEFAULT 1,
            `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_cal_school` (`school_id`),
            INDEX `idx_cal_date` (`event_date`),
            INDEX `idx_cal_type` (`event_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    } catch (Exception $e) {
        // Silent fail
    }
}

ensureSchema();

// ============================================================
// LOAD API MODULES
// ============================================================
require_once 'api/auth.php';
require_once 'api/dashboard.php';
require_once 'api/grades.php';
require_once 'api/students.php';
require_once 'api/profile.php';
require_once 'api/attendance.php';
require_once 'api/fitness.php';
require_once 'api/competition.php';
require_once 'api/users.php';
require_once 'api/parent.php';
require_once 'api/parents_admin.php';
require_once 'api/notifications.php';
require_once 'api/badges.php';
require_once 'api/calendar.php';
require_once 'api/analytics.php';
require_once 'api/reports_mail.php';
require_once 'api/audit_log.php';

// ============================================================
// ROUTE REQUESTS
// ============================================================
try {
    // SaaS Middleware: Check subscription for protected actions
    $publicActions = ['login', 'student_login', 'check_auth', 'logout', 'schools_list', 'get_public_plans', 'register_school', 'forgot_password', 'reset_password'];
    if (!in_array($action, $publicActions)) {
        Subscription::requireActive();
    }

    // Feature-gated actions
    $featureActions = [
        'tournaments' => ['tournament_save', 'tournament_delete'],
        'badges' => ['badge_save', 'badge_delete', 'award_badge', 'run_auto_badges'],
        'sports_teams' => [],  // handled in module
    ];
    foreach ($featureActions as $feature => $actions) {
        if (in_array($action, $actions)) {
            Subscription::requireFeature($feature);
        }
    }

    switch ($action) {
        // AUTH
        case 'check_auth':      checkAuth(); break;
        case 'login':           login(); break;
        case 'student_login':   studentLogin(); break;
        case 'logout':          logout(); break;
        case 'schools_list':    getSchoolsList(); break;
        case 'forgot_password': forgotPassword(); break;
        case 'reset_password':  resetPassword(); break;

        // DASHBOARD
        case 'dashboard':       getDashboard(); break;
        case 'student_dashboard_summary': getStudentDashboardSummary(); break;
        case 'analytics_dashboard': getAnalyticsDashboard(); break;
        case 'audit_logs':      getAuditLogs(); break;

        // GRADES
        case 'grades':          getGrades(); break;
        case 'grade_save':      saveGrade(); break;
        case 'grade_delete':    deleteGrade(); break;

        // CLASSES
        case 'classes':         getClasses(); break;
        case 'class_save':      saveClass(); break;
        case 'class_delete':    deleteClass(); break;

        // STUDENTS
        case 'students':        getStudents(); break;
        case 'student_save':    saveStudent(); break;
        case 'student_delete':  deleteStudent(); break;
        case 'students_import': importStudents(); break;
        case 'students_template': exportStudentsTemplate(); break;

        // STUDENT PROFILE
        case 'student_profile': getStudentProfile(); break;

        // MEASUREMENTS
        case 'measurements':        getMeasurements(); break;
        case 'measurement_save':    saveMeasurement(); break;
        case 'measurement_delete':  deleteMeasurement(); break;

        // HEALTH
        case 'health_conditions':   getHealthConditions(); break;
        case 'health_save':         saveHealthCondition(); break;
        case 'health_delete':       deleteHealthCondition(); break;
        case 'class_health_alerts': getClassHealthAlerts(); break;

        // ATTENDANCE
        case 'attendance':      getAttendance(); break;
        case 'attendance_save': saveAttendance(); break;
        case 'absence_report':  getAbsenceReport(); break;

        // FITNESS TESTS
        case 'fitness_tests':       getFitnessTests(); break;
        case 'fitness_test_save':   saveFitnessTest(); break;
        case 'fitness_test_delete': deleteFitnessTest(); break;

        // FITNESS RESULTS
        case 'fitness_results':      getFitnessResults(); break;
        case 'fitness_results_save': saveFitnessResults(); break;
        case 'fitness_view':         getFitnessView(); break;
        case 'fitness_criteria':      getFitnessCriteria(); break;
        case 'fitness_criteria_save': saveFitnessCriteria(); break;

        // COMPETITION
        case 'competition':     getCompetition(); break;

        // PARENT PORTAL
        case 'parent_dashboard':    getParentDashboard(); break;
        case 'parent_link_phone':   linkChildrenByPhone(); break;

        // REPORTS
        case 'report_student':  getStudentReport(); break;
        case 'report_class':    getClassReport(); break;
        case 'report_compare':  getCompareReport(); break;
        case 'send_report_email': sendReportEmail(); break;

        // USERS
        case 'users':           getUsers(); break;
        case 'user_save':       saveUser(); break;
        case 'user_delete':     deleteUser(); break;
        
        // PARENT MANAGEMENT (Admin)
        case 'parents_list':        getParents(); break;
        case 'parent_save':         saveParent(); break;
        case 'parent_delete':       deleteParent(); break;
        case 'parent_links':        getParentLinkedStudents(); break;
        case 'parent_search_students': searchStudentsForLinking(); break;
        case 'parent_link_student': linkParentStudent(); break;
        case 'parent_unlink_student': unlinkParentStudent(); break;

        // NOTIFICATIONS
        case 'notifications':           getNotifications(); break;
        case 'notification_read':       markNotificationRead(); break;
        case 'notification_mark_all_read': markAllNotificationsRead(); break;
        case 'notification_unread_count': getUnreadNotificationsCount(); break;

        // TEACHER CLASS ASSIGNMENTS (Admin)
        case 'teacher_assignments':    getTeacherAssignments(); break;
        case 'assign_teacher_class':   assignTeacherClass(); break;
        case 'unassign_teacher_class': unassignTeacherClass(); break;
        case 'save_teacher_assignments': saveTeacherAssignments(); break;

        // MY PROFILE
        case 'get_my_profile':    getMyProfile(); break;
        case 'update_my_profile': updateMyProfile(); break;

        // BADGES
        case 'get_badges':          getBadges(); break;
        case 'get_student_badges':  getStudentBadges(); break;
        case 'award_badge':         awardBadge(); break;
        case 'revoke_badge':        revokeBadge(); break;
        case 'badge_delete':        deleteBadge(); break;
        case 'run_auto_badges':     runAutoBadges(); break;

        // SPORTS CALENDAR
        case 'calendar_events':      getCalendarEvents(); break;
        case 'calendar_save':        saveCalendarEvent(); break;
        case 'calendar_delete':      deleteCalendarEvent(); break;
        case 'calendar_summary':     getCalendarSummary(); break;
        case 'calendar_export_ics':  exportCalendarICS(); break;

        // PUBLIC / ONBOARDING
        case 'get_public_plans':    getPublicPlans(); break;
        case 'register_school':     registerSchool(); break;

        default:
            jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error: ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
/**
 * Public Plans List for Registration
 */
function getPublicPlans() {
    $db = getDB();
    $plans = $db->query("SELECT id, name, slug, price_monthly, max_students, max_teachers, max_classes FROM plans ORDER BY sort_order")->fetchAll();
    jsonSuccess($plans);
}

/**
 * Public School Registration
 */
function registerSchool() {
    $data = getPostData();
    validateRequired($data, ['name', 'slug', 'admin_username', 'admin_password']);
    
    $db = getDB();
    $name = sanitize($data['name']);
    $slug = strtolower(sanitize($data['slug']));
    $adminName = sanitize($data['admin_name'] ?? ('مدير ' . $name));
    $adminEmail = sanitize($data['admin_email'] ?? '');
    $adminUser = sanitize($data['admin_username']);
    $adminPass = $data['admin_password'];
    $planId = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;

    // Check slug uniqueness
    $stmt = $db->prepare("SELECT id FROM schools WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) jsonError('المعرف الفريد (slug) مستخدم بالفعل، اختر اسماً آخر');

    $db->beginTransaction();
    try {
        // Find plan limits
        $maxStudents = 100; $maxTeachers = 5;
        if ($planId) {
            $p = $db->prepare("SELECT max_students, max_teachers FROM plans WHERE id = ?");
            $p->execute([$planId]);
            $plan = $p->fetch();
            if ($plan) {
                $maxStudents = (int)$plan['max_students'];
                $maxTeachers = (int)$plan['max_teachers'];
            }
        }

        $stmt = $db->prepare("INSERT INTO schools (name, slug, email, plan_id, max_students, max_teachers, subscription_status, trial_ends_at) VALUES (?,?,?,?,?,?,'trial',?)");
        $stmt->execute([$name, $slug, $adminEmail, $planId, $maxStudents, $maxTeachers, date('Y-m-d', strtotime('+14 days'))]);
        $schoolId = $db->lastInsertId();

        // Create Admin User
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("INSERT INTO users (school_id, username, password, name, role) VALUES (?,?,?,?,?)")
           ->execute([$schoolId, $adminUser, $hash, $adminName, 'admin']);

        // Create Default Grades
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$schoolId, 'الصف الأول', '1', 1]);
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$schoolId, 'الصف الثاني', '2', 2]);

        $db->commit();
        jsonSuccess(['slug' => $slug], 'تم تسجيل مدرستك بنجاح!');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('خطأ أثناء الإنشاء: ' . $e->getMessage());
    }
}
?>
