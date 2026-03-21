<?php
/**
 * PE Smart School System - Core Configuration & Bootstrapping
 * ============================================================
 */

// 1. Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 2. Constants & Environment
define('APP_NAME', 'PE Smart School');
define('BASE_URL', '/pe1');
define('DEBUG_MODE', true); // Toggle for development
define('APP_TIMEZONE', 'Asia/Riyadh');

// 3. Security Settings
define('SESSION_LIFETIME', 7200);
define('HASH_COST', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// 4. Database Credentials (Main School)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'pe_smart_school');
define('DB_USER', 'root');
define('DB_PASS', '');

// 4.1 Database Credentials (WordPress Blog)
define('DB_BLOG_HOST', '127.0.0.1');
define('DB_BLOG_NAME', 'pe_smart_blog'); // تأكد من إنشاء هذه القاعدة محلياً
define('DB_BLOG_USER', 'root');
define('DB_BLOG_PASS', '');
define('DB_BLOG_PREFIX', 'wp_');

// 5. Mail Settings (SMTP)
define('MAIL_USE_SMTP', false);
define('MAIL_HOST', '');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', '');
define('MAIL_FROM_NAME', 'PE Smart Admin');

// 5. Core Files Loading
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/subscription.php';
require_once __DIR__ . '/includes/helpers.php';       // All shared helper functions
require_once __DIR__ . '/includes/SchemaManager.php'; // Schema extension point

// 6. Services Layer (shared business logic — loaded once for all API modules)
foreach (glob(__DIR__ . '/services/*.php') as $serviceFile) {
    require_once $serviceFile;
}

// 6. Session Management
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/'); // Set to root for broader compatibility

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 7. CSRF Protection Initialize
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Sync with cookie for frontend (XSRF-TOKEN)
setcookie('XSRF-TOKEN', $_SESSION['csrf_token'], [
    'expires' => time() + 86400 * 30,
    'path' => '/',
    'samesite' => 'Lax',
    'httponly' => false // Must be accessible by JS to read it
]);

// 8. System Initialization
SchemaManager::ensureSchema();
checkMaintenance();