<?php
/**
 * PE Smart School System - Tenant Resolution Layer
 * ==================================================
 * يحدد المدرسة (المستأجر) الحالية في كل طلب.
 * الأولوية: 1. Session → 2. Subdomain → 3. Header
 */

class Tenant {
    private static ?int $schoolId = null;
    private static ?array $school = null;
    private static bool $resolved = false;

    /**
     * Resolve the current tenant from available sources
     */
    public static function resolve(): void {
        if (self::$resolved) return;
        self::$resolved = true;

        // Priority 1: Session (already logged in)
        if (isset($_SESSION['school_id'])) {
            $sid = (int)$_SESSION['school_id'];
            $school = self::findById($sid);
            if ($school && $school['active'] == 1) {
                self::$schoolId = $sid;
                self::$school = $school;
                return;
            } else {
                // School deactivated or not found - clear session
                unset($_SESSION['school_id']);
                unset($_SESSION['user_id']);
                if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            }
        }

        // Priority 2: URL Slug (e.g., example.com/school1)
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        // Strip base path from URI
        $relativeUri = ltrim(substr($uri, strlen($basePath)), '/');
        $uriParts = explode('/', $relativeUri);
        
        if (!empty($uriParts[0])) {
            $slug = explode('?', $uriParts[0])[0];
            $slug = strtolower($slug);
            // Skip common paths that are NOT school slugs
            if (!in_array($slug, ['api', 'admin', 'assets', 'css', 'js', 'modules', 'api.php', 'index.html', 'install.php', 'register.html', 'welcome.html', 'favicon.ico'])) {
                $school = self::findBySlug($slug);
                if ($school) {
                    self::$schoolId = (int)$school['id'];
                    self::$school = $school;
                    // Store in session for consistency
                    $_SESSION['school_id'] = self::$schoolId;
                    return;
                }
            }
        }

        // Priority 3: Subdomain (e.g., school1.pesmart.com)
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $slug = strtolower($parts[0]);
            if (!in_array($slug, ['www', 'api', 'admin', 'mail', 'ftp'])) {
                $school = self::findBySlug($slug);
                if ($school) {
                    self::$schoolId = (int)$school['id'];
                    self::$school = $school;
                    return;
                }
            }
        }

        // Priority 3: Header (for API clients / mobile apps)
        $header = $_SERVER['HTTP_X_SCHOOL_ID'] ?? null;
        if ($header) {
            self::$schoolId = (int)$header;
            return;
        }

        // Priority 4: User Session Fallback (if school_id missing but user_id exists)
        if (isset($_SESSION['user_id']) && !isset($_SESSION['school_id'])) {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT school_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $sid = $stmt->fetchColumn();
                if ($sid) {
                    self::$schoolId = (int)$sid;
                    $_SESSION['school_id'] = self::$schoolId;
                    return;
                }
            } catch (Exception $e) {}
        }

        // Priority 5: GET/POST parameter (for school selection page)
        if (isset($_GET['school_id'])) {
            self::$schoolId = (int)$_GET['school_id'];
        }
    }

    /**
     * Get the current school ID (null if not resolved)
     */
    public static function id(): ?int {
        if (!self::$resolved) self::resolve();
        return self::$schoolId;
    }

    /**
     * Set the school ID manually (e.g., after login)
     */
    public static function setId(int $id): void {
        self::$schoolId = $id;
        $_SESSION['school_id'] = $id;
        self::$school = null; // Reset cache
    }

    /**
     * Require a valid school ID or die with error
     */
    public static function requireId(): int {
        if (!self::$schoolId) {
            jsonError('لم يتم تحديد المدرسة. الرجاء تسجيل الدخول أولاً.', 400);
        }
        return self::$schoolId;
    }

    /**
     * Get the full school record
     */
    public static function school(): ?array {
        if (self::$school === null && self::$schoolId !== null) {
            self::$school = self::findById(self::$schoolId);
        }
        return self::$school;
    }

    /**
     * Get school name (helper)
     */
    public static function name(): string {
        $school = self::school();
        return $school['name'] ?? 'PE Smart School';
    }

    /**
     * Get school setting
     */
    public static function setting(string $key, $default = null) {
        $school = self::school();
        if (!$school || !$school['settings']) return $default;
        $settings = is_string($school['settings']) ? json_decode($school['settings'], true) : $school['settings'];
        return $settings[$key] ?? $default;
    }

    /**
     * Check if the system is in SaaS mode (multi-tenant)
     * Returns false if no schools table exists yet (backward compatibility)
     */
    public static function isSaasMode(): bool {
        static $result = null;
        if ($result !== null) return $result;
        try {
            $db = getDB();
            $stmt = $db->query("SHOW TABLES LIKE 'schools'");
            $result = $stmt->fetch() !== false;
        } catch (Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Check if current context is platform admin (Super Admin)
     */
    public static function isPlatformAdmin(): bool {
        return isset($_SESSION['platform_admin']) && $_SESSION['platform_admin'] === true;
    }

    // ============================================================
    // INTERNAL METHODS
    // ============================================================

    private static function findBySlug(string $slug): ?array {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM schools WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
            $school = $stmt->fetch();
            return $school ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function findById(int $id): ?array {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $school = $stmt->fetch();
            return $school ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Reset tenant (for testing or impersonation)
     */
    public static function reset(): void {
        self::$schoolId = null;
        self::$school = null;
        self::$resolved = false;
        unset($_SESSION['school_id']);
    }

    /**
     * Impersonate a school (for Super Admin)
     */
    public static function impersonate(int $schoolId): void {
        $db = getDB();
        
        // Find an admin for this school
        $stmt = $db->prepare("SELECT id, name FROM users WHERE school_id = ? AND role = 'admin' AND active = 1 LIMIT 1");
        $stmt->execute([$schoolId]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $user['name'] . ' (Impersonated)';
        }

        self::$schoolId = $schoolId;
        self::$school = null;
        $_SESSION['school_id'] = $schoolId;
        $_SESSION['is_impersonating'] = true;
    }
}
