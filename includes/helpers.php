<?php
/**
 * PE Smart School System — Shared Helper Functions
 * =================================================
 * مسؤولية واحدة: يوفر دوال مساعدة مشتركة للطبقة كاملة.
 * لا يجب أن يحتوي على منطق أعمال — فقط أدوات.
 *
 * Extension point: أضف هنا أي دالة مساعدة مشتركة جديدة.
 * لا تضع منطق الأعمال (business logic) هنا — ضعه في services/
 */

// ============================================================
// DATABASE
// ============================================================

/**
 * Get the global PDO instance (shortcut).
 */
function getDB(): PDO {
    return Database::getInstance()->getConnection();
}

/**
 * Get current school ID (shortcut for Tenant::id()).
 */
function schoolId(): ?int {
    return Tenant::id();
}

// ============================================================
// JSON RESPONSE
// ============================================================

function jsonResponse($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonSuccess($data = null, string $message = 'تمت العملية بنجاح'): never {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    jsonResponse($response);
}

function jsonError(string $message, int $code = 400): never {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

// ============================================================
// AUTH HELPERS
// ============================================================

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function requireLogin(): array {
    if (!isLoggedIn()) {
        jsonError('غير مسجل الدخول', 401);
    }
    return [
        'id'        => $_SESSION['user_id'],
        'role'      => $_SESSION['user_role'],
        'name'      => $_SESSION['user_name'] ?? '',
        'school_id' => $_SESSION['school_id'] ?? null,
    ];
}

function requireRole(array|string $roles): array {
    $user  = requireLogin();
    $roles = (array) $roles;
    if (!in_array($user['role'], $roles)) {
        jsonError('لا تملك صلاحية لهذا الإجراء', 403);
    }
    return $user;
}

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function isSupervisor(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'supervisor';
}

function canEdit(): bool {
    return isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'teacher']);
}

// ============================================================
// INPUT / OUTPUT
// ============================================================

/**
 * Sanitize a string (or array of strings) against XSS.
 */
function sanitize(mixed $input): mixed {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Read request body as JSON or form-encoded data.
 */
function getPostData(): array {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if ($data === null && !empty($raw)) {
        parse_str($raw, $data);
    }
    return $data ?: $_POST ?: [];
}

/**
 * Read a GET parameter with sanitization.
 */
function getParam(string $key, mixed $default = null): mixed {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

// ============================================================
// VALIDATION
// ============================================================

function validateRequired(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            jsonError("الحقل مطلوب: $field");
        }
    }
}

function validatePasswordStrength(string $password): true|string {
    if (strlen($password) < 8) {
        return 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل';
    }
    return true;
}

// ============================================================
// AUDIT LOG
// ============================================================

function logActivity(
    string  $action,
    ?string $entityType = null,
    mixed   $entityId   = null,
    ?string $details    = null
): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            "INSERT INTO activity_log
                (school_id, user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            schoolId() ?? null,
            $_SESSION['user_id'] ?? null,
            $action,
            $entityType,
            $entityId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception) {
        // Silent fail — logging must never break the app
    }
}

// ============================================================
// MULTI-TEACHER CLASS ACCESS
// ============================================================

/**
 * Returns the class IDs the current teacher is allowed to access.
 * - null  → no restriction (admin / supervisor or legacy mode)
 * - []    → teacher has no assigned classes
 */
function getTeacherClassIds(): ?array {
    if (!isLoggedIn()) return [];
    if (isAdmin() || isSupervisor()) return null; // See everything

    try {
        $stmt = getDB()->prepare("
            SELECT tc.class_id
            FROM   teacher_classes tc
            WHERE  tc.teacher_id = ?
              AND  (tc.expires_at IS NULL OR tc.expires_at >= CURDATE())
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception) {
        return null; // Table not yet migrated — no restriction
    }
}

/**
 * Check if the current user can access a specific class.
 */
function canAccessClass(int $classId): bool {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;

    $allowed = getTeacherClassIds();
    if ($allowed === null) return true;
    return in_array($classId, $allowed);
}

/**
 * Assign a class to a teacher (admin-only action).
 */
function assignClassToTeacher(
    int     $teacherId,
    int     $classId,
    bool    $isTemporary = false,
    ?string $expiresAt   = null
): void {
    getDB()->prepare("
        INSERT INTO teacher_classes (teacher_id, class_id, is_temporary, assigned_by, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            is_temporary = VALUES(is_temporary),
            assigned_by  = VALUES(assigned_by),
            expires_at   = VALUES(expires_at),
            assigned_at  = NOW()
    ")->execute([$teacherId, $classId, $isTemporary ? 1 : 0, $_SESSION['user_id'] ?? null, $expiresAt]);
}

/**
 * Remove a teacher's access to a class.
 */
function unassignClassFromTeacher(int $teacherId, int $classId): void {
    getDB()->prepare(
        "DELETE FROM teacher_classes WHERE teacher_id = ? AND class_id = ?"
    )->execute([$teacherId, $classId]);
}

// ============================================================
// PLATFORM SETTINGS
// ============================================================

/**
 * Read a key from platform_settings table with a fallback default.
 */
function getPlatformSetting(string $key, mixed $default = null): mixed {
    try {
        $stmt = getDB()->prepare(
            "SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return ($value !== false) ? $value : $default;
    } catch (Exception) {
        return $default;
    }
}

// ============================================================
// MAINTENANCE MODE
// ============================================================

/**
 * Halt the request if maintenance mode is active.
 * Called once in config.php bootstrap.
 */
function checkMaintenance(): void {
    // Skip check for platform admins
    if (isset($_SESSION['platform_admin']) && $_SESSION['platform_admin'] === true) {
        return;
    }

    try {
        $mode = getPlatformSetting('maintenance_mode', '0');
        if ($mode !== '1') return;

        $message = getPlatformSetting(
            'maintenance_message',
            'النظام تحت الصيانة حالياً. يرجى المحاولة لاحقاً.'
        );

        http_response_code(503);
        // Return JSON for API calls, HTML for page requests
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || str_ends_with($_SERVER['REQUEST_URI'] ?? '', '.php')) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'error' => $message, 'maintenance' => true]));
        }
        die("<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='UTF-8'><title>صيانة</title></head><body style='font-family:sans-serif;text-align:center;padding:4rem'><h1>🔧 النظام تحت الصيانة</h1><p>$message</p></body></html>");
    } catch (Exception) {
        // Never block the app due to a maintenance-check failure
    }
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Validate CSRF token from request header or body.
 * Called at the start of any state-changing endpoint.
 * Silent pass if CSRF token is not set in session (e.g. first boot).
 */
function checkCSRF(): void {
    // Read token from X-XSRF-TOKEN header (Axios / Fetch standard)
    $headerToken = $_SERVER['HTTP_X_XSRF_TOKEN']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? null;

    // Also accept from request body as fallback
    if (!$headerToken) {
        $body = json_decode(file_get_contents('php://input'), true);
        $headerToken = $body['_csrf'] ?? $_POST['_csrf'] ?? null;
    }

    // If session has no token yet, initialise and skip check (first load)
    if (empty($_SESSION['csrf_token'])) {
        return;
    }

    // Perform time-safe comparison
    if (!$headerToken || !hash_equals($_SESSION['csrf_token'], $headerToken)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'طلب غير صالح (CSRF)'], JSON_UNESCAPED_UNICODE));
    }
}

// ============================================================
// EMAIL
// ============================================================

/**
 * Send an HTML email using PHPMailer (if available) or PHP mail().
 * Returns true on success, false on failure.
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $toName = '', array $attachment = null): bool {
    // Try PHPMailer first (composer install phpmailer/phpmailer)
    if (class_exists('PHPMailer\PHPMailer\PHPMailer') && defined('MAIL_USE_SMTP') && MAIL_USE_SMTP) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>'], "\n", $htmlBody));

            // Attachments
            if ($attachment && isset($attachment['data'])) {
                $mail->addStringAttachment($attachment['data'], $attachment['filename'] ?? 'document.pdf');
            }

            return $mail->send();
        } catch (\Exception $e) {
            error_log('PHPMailer Error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback: native PHP mail()
    // Note: native mail() is very complex for attachments, usually we stick to SMTP for reports
    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@pe-smart.com';
    $fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'PE Smart School';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "X-Mailer: PE-Smart/2.0\r\n";

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
}

// ============================================================
// SAAS SECURITY & ISOLATION
// ============================================================

/**
 * Verify that a record in a given table belongs to the current school.
 */
function verifyOwnership(string $table, int $id): bool {
    if (Tenant::isPlatformAdmin()) return true;
    if (!Tenant::isSaasMode()) return true;

    $schoolId = schoolId();
    if (!$schoolId) return false;

    try {
        $db = getDB();
        
        if ($table === 'student_fitness') {
            $stmt = $db->prepare("SELECT s.school_id FROM students s JOIN student_fitness sf ON sf.student_id = s.id WHERE sf.id = ?");
            $stmt->execute([$id]);
            return (int)$stmt->fetchColumn() === $schoolId;
        }
        
        if ($table === 'student_badges') {
            $stmt = $db->prepare("SELECT s.school_id FROM students s JOIN student_badges sb ON sb.student_id = s.id WHERE sb.id = ?");
            $stmt->execute([$id]);
            return (int)$stmt->fetchColumn() === $schoolId;
        }

        $allowedTables = ['students', 'classes', 'fitness_tests', 'badges', 'fitness_criteria', 'users', 'schools', 'attendance', 'activity_log', 'teacher_classes'];
        if (!in_array($table, $allowedTables)) return false;

        $stmt = $db->prepare("SELECT school_id FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        $ownerId = $stmt->fetchColumn();
        
        return $ownerId !== false && (int)$ownerId === $schoolId;
    } catch (Exception $e) {
        return false;
    }
}

function requireOwnership(string $table, int $id): void {
    if (!verifyOwnership($table, $id)) {
        jsonError("غير مسموح بالوصول لهذا السجل أو السجل غير موجود", 403);
    }
}

/**
 * Get a PDO connection to the separate blog database.
 * Returns null if connection fails to prevent breaking the main app.
 */
function getBlogDB(): ?PDO {
    // Check if constants are defined first
    if (!defined('DB_BLOG_NAME') || empty(DB_BLOG_NAME)) {
        return null;
    }

    try {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8mb4",
            defined('DB_BLOG_HOST') ? DB_BLOG_HOST : '127.0.0.1',
            DB_BLOG_NAME
        );
        return new PDO($dsn, 
            defined('DB_BLOG_USER') ? DB_BLOG_USER : '', 
            defined('DB_BLOG_PASS') ? DB_BLOG_PASS : '', 
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
    } catch (PDOException $e) {
        error_log("Blog DB Connection Failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Fetch the latest blog posts directly from WordPress database.
 * Optimized for robustness - works even if categories/images are missing.
 */
function fetchRecentBlogPosts(int $limit = 3): array {
    $blogDb = getBlogDB();
    if (!$blogDb) {
        // error_log("Blog DB connection failed in fetchRecentBlogPosts");
        return [];
    }

    try {
        $prefix = defined('DB_BLOG_PREFIX') ? DB_BLOG_PREFIX : 'wp_';
        
        // Simplified query to ensure we get results even if tax/meta is missing
        $sql = "
            SELECT p.ID, p.post_title as title, p.post_name as slug, p.post_excerpt as excerpt, p.post_content as content, p.post_date as date
            FROM {$prefix}posts p
            WHERE p.post_type = 'post' AND p.post_status = 'publish'
            ORDER BY p.post_date DESC
            LIMIT $limit
        ";
        
        $stmt = $blogDb->query($sql);
        $posts = $stmt->fetchAll();

        if (!$posts) return [];

        // Fetch meta and categories per post for better reliability
        foreach ($posts as &$post) {
            // Get Image
            $imgStmt = $blogDb->prepare("SELECT guid FROM {$prefix}posts WHERE id = (SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_thumbnail_id' LIMIT 1)");
            $imgStmt->execute([$post['ID']]);
            $post['image_path'] = $imgStmt->fetchColumn() ?: "";

            // Get Category
            $catStmt = $blogDb->prepare("
                SELECT t.name FROM {$prefix}terms t 
                INNER JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id 
                INNER JOIN {$prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id 
                WHERE tr.object_id = ? AND tt.taxonomy = 'category' LIMIT 1
            ");
            $catStmt->execute([$post['ID']]);
            $post['category'] = $catStmt->fetchColumn() ?: "مقالات";

            // Excerpt
            if (empty($post['excerpt'])) {
                $post['excerpt'] = mb_substr(strip_tags($post['content']), 0, 150) . '...';
            }
            unset($post['content']);
        }
        return $posts;
    } catch (Exception $e) {
        error_log("fetchRecentBlogPosts EXCEPTION: " . $e->getMessage());
        return [];
    }
}
