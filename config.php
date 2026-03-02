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
// في بيئة الإنتاج، غيّر إلى 0
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
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
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? '',
        'school_id' => $_SESSION['school_id'] ?? null
    ];
}

/**
 * Require specific role(s)
 */
function requireRole($roles) {
    $user = requireLogin();
    
    // SaaS: Security check - ensure user's school matches the current tenant
    if (Tenant::isSaasMode() && !Tenant::isPlatformAdmin()) {
        $sid = schoolId();
        if ($sid && $user['school_id'] != $sid) {
            jsonError('محاولة وصول غير مصرح بها (تضارب في بيانات المدرسة)', 403);
        }
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