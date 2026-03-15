<?php
/**
 * PE Smart School System - Configuration
 * =======================================
 * قم بتعديل بيانات الاتصال حسب استضافتك
 * For Shared Hosting (Contabo / cPanel)
 */

// ============================================================
// DATABASE CONFIGURATION - إعدادات قاعدة البيانات
// ============================================================
define('DB_HOST', '127.0.0.1');           // عادة localhost في الاستضافة المشتركة
define('DB_NAME', 'pe_smart_school');     // اسم قاعدة البيانات - غيّره حسب استضافتك
define('DB_USER', 'root');               // اسم المستخدم - غيّره حسب استضافتك  
define('DB_PASS', '');                   // كلمة المرور - غيّرها حسب استضافتك
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);                 // المنفذ - عادة 3306

// ============================================================
// APPLICATION SETTINGS - إعدادات التطبيق
// ============================================================
define('APP_NAME', 'PE Smart School System');
define('APP_VERSION', '2.0.0');
define('APP_TIMEZONE', 'Asia/Riyadh');

// Determine Base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rootHome = "http://localhost/pe1"; // Fallback
if (isset($_SERVER['PHP_SELF'])) {
    $rootHome = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF']);
    // Adjust if called from a subdirectory
    if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) $rootHome = $protocol . "://" . $host . dirname(dirname(dirname($_SERVER['PHP_SELF'])));
    elseif (strpos($_SERVER['PHP_SELF'], '/api/') !== false) $rootHome = $protocol . "://" . $host . dirname(dirname($_SERVER['PHP_SELF']));
    elseif (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) $rootHome = $protocol . "://" . $host . dirname(dirname($_SERVER['PHP_SELF']));
}
define('BASE_URL', rtrim($rootHome, '/'));

define('SESSION_LIFETIME', 7200); // ساعتان

// ============================================================
// EMAIL / SMTP CONFIGURATION - إعدادات البريد الإلكتروني
// ============================================================
// اضبط هذه الإعدادات لإرسال رسائل OTP واستعادة كلمة المرور
// لإيقاف SMTP والاعتماد على mail() المحلية: اضبط MAIL_USE_SMTP = false

define('MAIL_USE_SMTP',    false);            // ← اضبط على true عند الاستضافة

// إعدادات SMTP (Gmail, SendGrid, cPanel, Contabo...)
define('MAIL_HOST',       'smtp.gmail.com'); // خادم SMTP
define('MAIL_PORT',        587);             // 587 = TLS | 465 = SSL
define('MAIL_ENCRYPTION', 'tls');            // 'tls' أو 'ssl'
define('MAIL_USERNAME',   '');               // بريدك الإلكتروني
define('MAIL_PASSWORD',   '');               // كلمة مرور التطبيق (App Password)
define('MAIL_FROM_EMAIL', '');               // البريد المُرسِل (= MAIL_USERNAME عادةً)
define('MAIL_FROM_NAME',  APP_NAME);         // اسم المُرسِل


// ============================================================
// PAYMENT SETTINGS - إعدادات الدفع
// ============================================================
define('PAYMENT_BANK_NAME', 'Bank Al-Rajhi');
define('PAYMENT_IBAN',      'SA00 0000 0000 0000 0000 0000');
define('PAYMENT_HOLDER',    'PE Smart School Est.');
define('PAYMENT_STC_PAY',   '0500000000');
define('PAYMENT_WHATSAPP',  '966500000000'); // International format without +

// ============================================================
// SECURITY SETTINGS - إعدادات الأمان
// ============================================================
define('HASH_COST', 12); // bcrypt cost factor
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set(APP_TIMEZONE);

// ============================================================
// ERROR HANDLING - معالجة الأخطاء
// ============================================================
// ⚠️ مهم: في بيئة الإنتاج، يجب تغيير إلى false لحماية المعلومات الحساسة
define('DEBUG_MODE', true);

// Production Safety Guard: Warn if DEBUG is on outside localhost
if (DEBUG_MODE) {
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = in_array($currentHost, ['localhost', '127.0.0.1']) || str_contains($currentHost, '.local');
    if (!$isLocal) {
        // In production with DEBUG=true: log but do not expose errors to browser
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // NEVER output errors in production
        error_log('[SECURITY WARNING] DEBUG_MODE is true on production host: ' . $currentHost);
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================================
// SESSION CONFIGURATION - إعدادات الجلسة
// ============================================================
// Must be set BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);

// Lax is better than Strict for shared hosting - Strict can cause session loss on some configs
ini_set('session.cookie_samesite', 'Lax');

// Set cookie path to root to ensure session works across all pages
ini_set('session.cookie_path', '/');

// Use cookies only (not URL parameters) for session ID
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);

// Set session save path for shared hosting (use default if not writable)
$sessionPath = sys_get_temp_dir();
if (is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 1800) {
    // Regenerate every 30 minutes
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// CSRF Protection Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set a cookie that JS can read to send back in headers
setcookie('XSRF-TOKEN', $_SESSION['csrf_token'], [
    'expires' => time() + SESSION_LIFETIME,
    'path' => '/',
    'samesite' => 'Lax',
    'httponly' => false // Required for JS to read the token and send it in headers
]);

// ============================================================
// DATABASE CONNECTION CLASS - فئة الاتصال بقاعدة البيانات
// ============================================================
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ]);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database Error: " . $e->getMessage());
            }
            die(json_encode(['success' => false, 'error' => 'فشل الاتصال بقاعدة البيانات']));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    // Prevent cloning
    private function __clone() {}
}

// ============================================================
// SAAS: TENANT & SUBSCRIPTION
// ============================================================
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/subscription.php';

// ============================================================
// AUTO-MIGRATE: Ensure all required tables exist
// ============================================================
function ensureSchema() {
    static $done = false;
    if ($done) return;
    $done = true;
    
    // Performance: Only run schema checks if version changed or periodically
    // Optimized: Skip DB query for version check in production unless forced
    if (!DEBUG_MODE && !isset($_GET['force_schema'])) return;

    $currentVersion = '2.1.9'; 
    $dbVersion = getPlatformSetting('db_schema_version', '0');
    if ($dbVersion === $currentVersion && !isset($_GET['force_schema'])) return;

    try {
        $db = getDB();
        
        // 1. Core Tables & Improvements
        // ... (existing checks)
        
        // Update school_grading_weights with max score columns
        try {
            $db->exec("ALTER TABLE `school_grading_weights` ADD COLUMN `quiz_max` INT DEFAULT 10");
        } catch (Exception $e) {}
        try {
            $db->exec("ALTER TABLE `school_grading_weights` ADD COLUMN `project_max` INT DEFAULT 10");
        } catch (Exception $e) {}
        try {
            $db->exec("ALTER TABLE `school_grading_weights` ADD COLUMN `final_exam_max` INT DEFAULT 10");
        } catch (Exception $e) {}

        // Ensure student_assessments has unique constraint
        try {
            $db->exec("ALTER TABLE `student_assessments` ADD UNIQUE INDEX `idx_student_type` (`student_id`, `type`)");
        } catch (Exception $e) {}

        // Core Tables...
        
        // Check & add participation_stars to attendance table
        try {
            $colsAtt = array_column($db->query("SHOW COLUMNS FROM attendance")->fetchAll(), 'Field');
            if (!in_array('participation_stars', $colsAtt)) {
                $db->exec("ALTER TABLE attendance ADD COLUMN `participation_stars` TINYINT UNSIGNED DEFAULT 0 AFTER `skills_stars`");
            }
        } catch (Exception $e) {}

        // Check & add grading weight columns to school_grading_weights table
        try {
            $colsWeights = array_column($db->query("SHOW COLUMNS FROM school_grading_weights")->fetchAll(), 'Field');
            if (!in_array('participation_pct', $colsWeights)) {
                $db->exec("ALTER TABLE school_grading_weights ADD COLUMN `participation_pct` TINYINT UNSIGNED DEFAULT 0 AFTER `behavior_skills_pct`");
            }
            if (!in_array('quiz_pct', $colsWeights)) {
                $db->exec("ALTER TABLE school_grading_weights ADD COLUMN `quiz_pct` TINYINT UNSIGNED DEFAULT 0 AFTER `fitness_pct` ");
            }
            if (!in_array('project_pct', $colsWeights)) {
                $db->exec("ALTER TABLE school_grading_weights ADD COLUMN `project_pct` TINYINT UNSIGNED DEFAULT 0 AFTER `quiz_pct` ");
            }
            if (!in_array('final_exam_pct', $colsWeights)) {
                $db->exec("ALTER TABLE school_grading_weights ADD COLUMN `final_exam_pct` TINYINT UNSIGNED DEFAULT 0 AFTER `project_pct` ");
            }
        } catch (Exception $e) {}

        // New table for student assessments (Quizzes, Projects, Final Exams)
        $db->exec("CREATE TABLE IF NOT EXISTS `student_assessments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `type` ENUM('quiz', 'project', 'final_exam') NOT NULL,
            `title` VARCHAR(150) DEFAULT NULL,
            `score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `max_score` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            `assessment_date` DATE NOT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_sa_student` (`student_id`),
            INDEX `idx_sa_type` (`type`),
            INDEX `idx_sa_date` (`assessment_date`),
            CONSTRAINT `fk_sa_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 1. Core Tables & Improvements
        $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45) NOT NULL,
            `username` VARCHAR(100) NOT NULL,
            `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_login_ip` (`ip_address`),
            INDEX `idx_login_time` (`attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Check & add missing columns to students table
        $cols = array_column($db->query("SHOW COLUMNS FROM students")->fetchAll(), 'Field');
        $alter = [];
        if (!in_array('date_of_birth', $cols)) $alter[] = "ADD COLUMN `date_of_birth` DATE DEFAULT NULL";
        if (!in_array('blood_type', $cols)) $alter[] = "ADD COLUMN `blood_type` VARCHAR(5) DEFAULT NULL";
        if (!in_array('guardian_phone', $cols)) $alter[] = "ADD COLUMN `guardian_phone` VARCHAR(20) DEFAULT NULL";
        if (!in_array('medical_notes', $cols)) $alter[] = "ADD COLUMN `medical_notes` TEXT DEFAULT NULL";
        if (!in_array('photo_url', $cols)) $alter[] = "ADD COLUMN `photo_url` VARCHAR(255) DEFAULT NULL AFTER `name`";
        if (!in_array('last_login', $cols)) $alter[] = "ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL";
        if (!in_array('must_change_password', $cols)) $alter[] = "ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 1";
        if (!empty($alter)) $db->exec("ALTER TABLE students " . implode(", ", $alter));

        // Check & add missing columns to users table
        $colsUsers = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(), 'Field');
        $alterUsers = [];
        if (!in_array('photo_url', $colsUsers)) $alterUsers[] = "ADD COLUMN `photo_url` VARCHAR(255) DEFAULT NULL AFTER `name`";
        if (!in_array('must_change_password', $colsUsers)) $alterUsers[] = "ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 1";
        if (!in_array('email', $colsUsers)) $alterUsers[] = "ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `username` ";
        if (!empty($alterUsers)) $db->exec("ALTER TABLE users " . implode(", ", $alterUsers));

        // Ensure unique email index (globally)
        try {
            $hasEmailIdx = $db->query("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0")->fetch();
            if (!$hasEmailIdx) {
                $db->exec("ALTER TABLE users ADD UNIQUE INDEX `uk_user_email_global` (`email`) ");
            }
        } catch (Exception $e) {}

        // Check & add missing columns to parents table
        $colsParents = array_column($db->query("SHOW COLUMNS FROM parents")->fetchAll(), 'Field');
        $alterParents = [];
        if (!in_array('must_change_password', $colsParents)) $alterParents[] = "ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 1";
        if (!in_array('photo_url', $colsParents)) $alterParents[] = "ADD COLUMN `photo_url` VARCHAR(255) DEFAULT NULL AFTER `name`";
        if (!empty($alterParents)) $db->exec("ALTER TABLE parents " . implode(", ", $alterParents));
        
        // Tables definitions...
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

        $db->exec("CREATE TABLE IF NOT EXISTS `parent_students` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id`  INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_parent_student` (`parent_id`, `student_id`),
            INDEX `idx_ps_parent` (`parent_id`),
            INDEX `idx_ps_student` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','teacher','viewer','supervisor') NOT NULL DEFAULT 'teacher'");

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
        
        $classCols = array_column($db->query("SHOW COLUMNS FROM classes")->fetchAll(), 'Field');
        if (!in_array('created_by', $classCols)) {
            $db->exec("ALTER TABLE `classes` ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL");
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `fitness_criteria` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `test_id` INT UNSIGNED NOT NULL,
            `min_value` DECIMAL(10,2) NOT NULL,
            `max_value` DECIMAL(10,2) NOT NULL,
            `score` INT NOT NULL,
            CONSTRAINT `fk_criteria_test_auto` FOREIGN KEY (`test_id`) REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

        $db->exec("CREATE TABLE IF NOT EXISTS `teacher_timetables` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id`     INT UNSIGNED NOT NULL,
            `teacher_id`    INT UNSIGNED NOT NULL,
            `day_of_week`   TINYINT UNSIGNED NOT NULL, -- 1=الأحد, 7=السبت
            `period_number` TINYINT UNSIGNED NOT NULL,
            `class_id`      INT UNSIGNED NOT NULL,
            `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tt_teacher` (`teacher_id`),
            INDEX `idx_tt_school` (`school_id`),
            INDEX `idx_tt_class` (`class_id`),
            UNIQUE KEY `uk_teacher_slot` (`teacher_id`, `day_of_week`, `period_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `school_period_times` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id`     INT UNSIGNED NOT NULL,
            `period_number` TINYINT UNSIGNED NOT NULL,
            `start_time`    TIME NOT NULL,
            `end_time`      TIME NOT NULL,
            UNIQUE KEY `uk_school_period` (`school_id`, `period_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `platform_announcements` (
            `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title`            VARCHAR(255) NOT NULL,
            `message`          TEXT NOT NULL,
            `type`             ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
            `target_school_id` INT UNSIGNED DEFAULT NULL, -- NULL for all schools
            `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
            `expires_at`       DATE DEFAULT NULL,
            `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_ann_school` (`target_school_id`),
            INDEX `idx_ann_active` (`is_active`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `platform_settings` (
            `setting_key`   VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT DEFAULT NULL,
            `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->prepare("INSERT IGNORE INTO `platform_settings` (setting_key, setting_value) VALUES ('maintenance_mode', '0')")->execute();
        $db->prepare("INSERT IGNORE INTO `platform_settings` (setting_key, setting_value) VALUES ('maintenance_message', 'المنصة في صيانة حالياً، سنعود قريباً.')")->execute();

        $db->exec("CREATE TABLE IF NOT EXISTS `blog_posts` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id`      INT UNSIGNED DEFAULT NULL,
            `title`          VARCHAR(255) NOT NULL,
            `slug`           VARCHAR(255) NOT NULL,
            `content`        LONGTEXT NOT NULL,
            `excerpt`        TEXT DEFAULT NULL,
            `image_path`     VARCHAR(255) DEFAULT NULL,
            `category`       VARCHAR(100) DEFAULT 'general',
            `status`         ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
            `published_at`   DATETIME DEFAULT NULL,
            `created_by`     INT UNSIGNED DEFAULT NULL,
            `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_blog_slug` (`slug`),
            INDEX `idx_blog_status` (`status`, `published_at`),
            INDEX `idx_blog_school` (`school_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Multi-School Access Table
        $db->exec("CREATE TABLE IF NOT EXISTS `user_school_access` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`       INT UNSIGNED NOT NULL,
            `school_id`     INT UNSIGNED NOT NULL,
            `role`          VARCHAR(50) DEFAULT NULL,
            `is_primary`    TINYINT(1) DEFAULT 0,
            `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_user_school` (`user_id`, `school_id`),
            INDEX `idx_usa_user` (`user_id`),
            INDEX `idx_usa_school` (`school_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Update version to avoid repeated runs
        $db->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('db_schema_version', ?) 
                      ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()")->execute([$currentVersion, $currentVersion]);

    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('[ensureSchema Error] ' . $e->getMessage());
        }
    }
}

/**
 * PLATFORM SETTINGS HELPER
 */
function getPlatformSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * GLOBAL MAINTENANCE CHECK
 */
function checkMaintenance() {
    global $action;
    
    // Exception 1: Platform Admin API should ALWAYS ignore maintenance
    if (strpos($_SERVER['PHP_SELF'], '/admin/api.php') !== false) return;
    
    // Exception 2: Authenticated Platform Admins can bypass maintenance even on school site
    if (isset($_SESSION['platform_admin']) && $_SESSION['platform_admin'] === true) return;

    // Check if maintenance mode is ACTIVE
    if (getPlatformSetting('maintenance_mode') === '1') {
        // Resolve target action (even if global $action isn't set yet)
        $currentAction = $action ?? getParam('action', '');
        
        // Critical: In school portal (api.php), block everything except essentials
        // We only allow logout. check_auth/login should be blocked to show maintenance UI.
        $allowed = ['logout']; 
        
        if (!in_array($currentAction, $allowed)) {
            $msg = getPlatformSetting('maintenance_message', 'المنصة في صيانة حالياً، سنعود قريباً.');
            $until = getPlatformSetting('maintenance_until', '');
            
            // If it's an API call or a critical POST/AUTH request, block with 503
            if (!empty($currentAction) || $_SERVER['REQUEST_METHOD'] === 'POST') {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode([
                    'success' => false, 
                    'error' => 'maintenance',
                    'message' => $msg,
                    'until' => $until
                ]));
            }
        }
    }
}



// ============================================================
// HELPER FUNCTIONS - دوال مساعدة
// ============================================================

/**
 * Get database PDO instance
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Get current school ID (shortcut for Tenant::id())
 */
function schoolId(): ?int {
    return Tenant::id();
}

/**
 * Send JSON response
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send success response
 */
function jsonSuccess($data = null, $message = 'تمت العملية بنجاح') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    jsonResponse($response);
}

/**
 * Check password strength
 * Returns true if strong, or an error message string if weak
 */
function validatePasswordStrength($password) {
    if (strlen($password) < 8) return 'كلمة المرور يجب أن لا تقل عن 8 أحرف';
    if (!preg_match('/[A-Z]/', $password)) return 'يجب أن تحتوي على حرف كبير واحد على الأقل (A-Z)';
    if (!preg_match('/[a-z]/', $password)) return 'يجب أن تحتوي على حرف صغير واحد على الأقل (a-z)';
    if (!preg_match('/[0-9]/', $password)) return 'يجب أن تحتوي على رقم واحد على الأقل (0-9)';
    if (!preg_match('/[\W]/', $password)) return 'يجب أن تحتوي على رمز خاص واحد على الأقل (مثل @#$%)';
    return true;
}

/**
 * Send error response
 */
function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        jsonError('غير مسجل الدخول', 401);
    }

    $sid = Tenant::id();
    // SaaS Security: Ensure session matches the requested tenant
    if (Tenant::isSaasMode() && !Tenant::isPlatformAdmin()) {
        if ($sid && isset($_SESSION['school_id']) && $_SESSION['school_id'] != $sid) {
            // Context mismatch: User is logged into School A but trying to access School B's API
            jsonError('محاولة وصول غير مصرح بها (تضارب في بيانات المدرسة)', 403);
        }
    }

    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? '',
        'school_id' => $_SESSION['school_id'] ?? null
    ];
}

/**
 * SaaS: Verify if a record in a table belongs to the current school.
 */
function verifyOwnership(string $table, int $id): bool {
    // Platform Admins skip isolation checks
    if (isset($_SESSION['platform_admin']) && $_SESSION['platform_admin'] === true) return true;
    
    // Resolve school context
    $sid = Tenant::id();
    if (!$sid) return false;

    try {
        $db = getDB();
        
        // 1. Direct Ownership (Table has school_id)
        $directTables = [
            'students', 'classes', 'grades', 'users', 'parents', 
            'fitness_tests', 'badges', 'sports_calendar', 'tournaments',
            'teams', 'schools'
        ];

        if (in_array($table, $directTables)) {
            $stmt = $db->prepare("SELECT id FROM `$table` WHERE id = ? AND school_id = ?");
            $stmt->execute([$id, $sid]);
            return (bool)$stmt->fetch();
        }

        // 2. Child Ownership (Linked via student_id)
        $studentLinked = [
            'student_badges', 'student_fitness', 'attendance', 
            'student_measurements', 'student_health', 'notifications'
        ];
        if (in_array($table, $studentLinked)) {
            $stmt = $db->prepare("SELECT t.id FROM `$table` t JOIN students s ON t.student_id = s.id WHERE t.id = ? AND s.school_id = ?");
            $stmt->execute([$id, $sid]);
            return (bool)$stmt->fetch();
        }

        // 3. Special Case: teacher_classes
        if ($table === 'teacher_classes') {
            $stmt = $db->prepare("SELECT tc.id FROM teacher_classes tc JOIN classes c ON tc.class_id = c.id WHERE tc.id = ? AND c.school_id = ?");
            $stmt->execute([$id, $sid]);
            return (bool)$stmt->fetch();
        }

        // Default to true for unknown tables (safe fallback)
        return true; 
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require ownership or die with 403 error.
 */
function requireOwnership(string $table, int $id): void {
    if (!verifyOwnership($table, $id)) {
        jsonError('محاولة وصول غير مصرح بها لبيانات مدرسة أخرى (SaaS Protection)', 403);
    }
}

/**
 * CSRF Protection Check
 */
function checkCSRF() {
    // Skip protection for GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') return true;
    
    // Check if token exists in headers or posted data
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!$token && isset($jsonData['csrf_token'])) $token = $jsonData['csrf_token'];

    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        jsonError('فشل التحقق من أمان الجلسة (CSRF Token Invalid). يرجى تحديث الصفحة.', 419);
    }
    return true;
}

/**
 * Require specific role(s)
 */
function requireRole($roles) {
    $user = requireLogin();
    checkCSRF(); // Automatically check CSRF on POST for protected routes
    
    // SaaS: Security check - ensure user's school matches the current tenant
    if (Tenant::isSaasMode() && !Tenant::isPlatformAdmin()) {
        $sid = schoolId();
        if ($sid && $user['school_id'] != $sid) {
            jsonError('محاولة وصول غير مصرح بها (تضارب في بيانات المدرسة)', 403);
        }

        // NEW: Subscription enforcement
        // This blocks all actions (POST/DELETE/Complex Views) if subscription is expired
        Subscription::requireActive();
    }

    $roles = (array) $roles;
    if (!in_array($user['role'], $roles)) {
        jsonError('لا تملك صلاحية لهذا الإجراء', 403);
    }
    return $user;
}

/**
 * Check if current user can edit
 */
function canEdit() {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'teacher']);
}

function isAdmin() {
    // In SaaS mode, "admin" means school admin. 
    // Super admins use Tenant::isPlatformAdmin()
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if current user is supervisor
 */
function isSupervisor() {
    return isLoggedIn() && $_SESSION['user_role'] === 'supervisor';
}

/**
 * Sanitize input string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    if ($input === null) return null;
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get POST data as JSON
 */
function getPostData() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null && !empty($raw)) {
        // Try form data
        parse_str($raw, $data);
    }
    if ($data === null) {
        $data = $_POST;
    }
    return $data ?: [];
}

/**
 * Get GET parameter with default
 */
function getParam($key, $default = null) {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Log activity
 */
function logActivity($action, $entityType = null, $entityId = null, $details = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_log (school_id, user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            schoolId() ?? null,
            $_SESSION['user_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail - don't break the app for logging
    }
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            jsonError("الحقل مطلوب: $field");
        }
    }
}

// ============================================================
// MULTI-TEACHER HELPERS - صلاحيات تعدد المعلمين
// ============================================================

/**
 * Get class IDs accessible to the current logged-in teacher.
 * Returns null if the user is admin (= no restriction, see all).
 * Returns [] if teacher has no assigned classes.
 * Respects temporary assignments and their expiry dates.
 *
 * @return int[]|null
 */
function getTeacherClassIds(): ?array {
    if (!isLoggedIn()) return [];

    $sid = schoolId();
    // Admin and Supervisor see everything in THEIR school
    if (isAdmin() || isSupervisor()) return null;

    try {
        $db = getDB();
        $sql = "
            SELECT tc.class_id
            FROM teacher_classes tc
            INNER JOIN classes c ON tc.class_id = c.id
            WHERE tc.teacher_id = ?
              AND (tc.expires_at IS NULL OR tc.expires_at >= CURDATE())
        ";
        if ($sid) $sql .= " AND c.school_id = $sid";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if the current user can access a specific class.
 * Admin: always true.
 * Teacher: only if class is in their assigned list.
 *
 * @param int $classId
 * @return bool
 */
function canAccessClass(int $classId): bool {
    if (!isLoggedIn()) return false;
    
    $sid = schoolId();
    $db = getDB();

    // 1. First, verify the class belongs to the current school
    if ($sid) {
        $stmt = $db->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ? AND active = 1");
        $stmt->execute([$classId, $sid]);
        if (!$stmt->fetch()) return false;
    }

    // 2. Then check role-based access
    if (isAdmin() || isSupervisor()) return true;

    $allowed = getTeacherClassIds();
    if ($allowed === null) return true; // session role fallback
    return in_array($classId, $allowed);
}

/**
 * Assign a class to a teacher (permanent or temporary).
 * Only admins should call this.
 *
 * @param int      $teacherId
 * @param int      $classId
 * @param bool     $isTemporary
 * @param string|null $expiresAt  Date string 'Y-m-d' or null
 */
function assignClassToTeacher(int $teacherId, int $classId, bool $isTemporary = false, ?string $expiresAt = null): void {
    $db = getDB();
    $adminId = $_SESSION['user_id'] ?? null;
    $stmt = $db->prepare("
        INSERT INTO teacher_classes (teacher_id, class_id, is_temporary, assigned_by, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_temporary = VALUES(is_temporary),
            assigned_by  = VALUES(assigned_by),
            expires_at   = VALUES(expires_at),
            assigned_at  = NOW()
    ");
    $stmt->execute([$teacherId, $classId, $isTemporary ? 1 : 0, $adminId, $expiresAt]);
}

/**
 * Remove a teacher's access to a class.
 *
 * @param int $teacherId
 * @param int $classId
 */
function unassignClassFromTeacher(int $teacherId, int $classId): void {
    $db = getDB();
    $db->prepare("DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?")->execute([$teacherId, $classId]);
}

// ============================================================
// INITIALIZE SYSTEM
// ============================================================
ensureSchema();
checkMaintenance();
/**
 * REUSABLE MAIL SENDER
 */
function sendEmail($to, $subject, $message, $name = '') {
    $htmlMessage = "<html><body style='font-family: Arial, sans-serif; direction: rtl; text-align: right;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px;'>
            <h2 style='color: #10b981;'>" . APP_NAME . "</h2>
            <div style='line-height: 1.6; color: #374151;'>$message</div>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='color: #9ca3af; font-size: 12px; font-style: italic;'>" . APP_NAME . " &mdash; نظام إدارة التربية البدنية الذكي</p>
        </div>
    </body></html>";

    if (MAIL_USE_SMTP && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)) {
        $mailerPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($mailerPath)) {
            require_once $mailerPath;
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = (MAIL_ENCRYPTION === 'ssl') ? 'ssl' : 'tls';
                $mail->Port       = MAIL_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(MAIL_FROM_EMAIL ?: MAIL_USERNAME, MAIL_FROM_NAME);
                $mail->addAddress($to, $name);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlMessage;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message));
                return $mail->send();
            } catch (Exception $e) {
                error_log('[SMTP Error] ' . $e->getMessage());
            }
        }
    }
    
    // Fallback to mail()
    $fromEmail = (MAIL_FROM_EMAIL ?: 'noreply@pesmart.local');
    $headers  = "From: " . MAIL_FROM_NAME . " <$fromEmail>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlMessage, $headers);
}
