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

        $urlSchoolId = null;
        $urlSchool = null;

        // DEBUG
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // error_log("Tenant::resolve - URI: " . $uri);

        // Priority 1: URL Slug Exists Anywhere in Path (e.g., example.com/school1 or physical folder /school1/api.php)
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uri) {
            $uriParts = array_filter(explode('/', $uri));
            $ignoreList = ['api', 'admin', 'assets', 'css', 'js', 'modules', 'api.php', 'index.html', 'install.php', 'register.html', 'welcome.html', 'favicon.ico'];
            
            // Search backwards so we pick the most specific folder
            foreach (array_reverse($uriParts) as $part) {
                $slug = strtolower(trim($part));
                if (!empty($slug) && !in_array($slug, $ignoreList)) {
                    $school = self::findBySlug($slug);
                    if ($school) {
                        $urlSchoolId = (int)$school['id'];
                        $urlSchool = $school;
                        break;
                    }
                }
            }
        }

        if ($urlSchoolId) {
            if (isset($_SESSION['school_id']) && $_SESSION['school_id'] != $urlSchoolId) {
                // error_log("Tenant::resolve - MISMATCH: URL=$urlSchoolId, Session=" . $_SESSION['school_id']);
                
                $hasAccess = false;
                $newRole = $_SESSION['user_role'] ?? 'teacher';

                if (self::isPlatformAdmin()) {
                    $hasAccess = true;
                    // error_log("Tenant::resolve - Platform Admin allowed switch.");
                } elseif (isset($_SESSION['user_id'])) {
                    try {
                        $db = getDB();
                        // 1. Check if they are a super_admin globally
                        $stmt = $db->prepare("SELECT role FROM users WHERE id = ? AND active = 1");
                        $stmt->execute([$_SESSION['user_id']]);
                        $globalRole = $stmt->fetchColumn();
                        
                        if ($globalRole === 'super_admin') {
                            $hasAccess = true;
                            // error_log("Tenant::resolve - Global Super Admin allowed switch.");
                        } else {
                            // 2. Check if it's their primary school or linked school
                            $stmt = $db->prepare("
                                SELECT role FROM users WHERE id = ? AND school_id = ? AND active = 1
                                UNION
                                SELECT role FROM user_school_access WHERE user_id = ? AND school_id = ?
                            ");
                            $stmt->execute([$_SESSION['user_id'], $urlSchoolId, $_SESSION['user_id'], $urlSchoolId]);
                            $foundRole = $stmt->fetchColumn();
                            
                            if ($foundRole !== false) {
                                $hasAccess = true;
                                $newRole = $foundRole ?: $newRole; // Keep current role if not specified in link
                                // error_log("Tenant::resolve - Authorized switch for user " . $_SESSION['user_id'] . " to role $newRole");
                            } else {
                                // error_log("Tenant::resolve - Unauthorized switch for user " . $_SESSION['user_id']);
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Tenant::resolve - DB Error: " . $e->getMessage());
                    }
                }

                if ($hasAccess) {
                    // error_log("Tenant::resolve - Context Switch context to $urlSchoolId for User " . ($_SESSION['user_id'] ?? 'unknown'));
                    $_SESSION['school_id'] = $urlSchoolId;
                    $_SESSION['user_role'] = $newRole;
                    unset($_SESSION['class_id']); 
                    self::$schoolId = $urlSchoolId;
                    self::$school = $urlSchool;
                    return;
                }

                // If switch not permitted, we must clear session to maintain isolation
                // error_log("Tenant::resolve - Switch REJECTED. Clearing session.");
                unset($_SESSION['school_id']);
                unset($_SESSION['user_id']);
                unset($_SESSION['user_role']);
                unset($_SESSION['user_name']);
                unset($_SESSION['class_id']);
                unset($_SESSION['is_impersonating']); 
            }
            self::$schoolId = $urlSchoolId;
            self::$school = $urlSchool;
            $_SESSION['school_id'] = $urlSchoolId;
            return;
        }

        // Priority 2: Session (already logged in)
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
