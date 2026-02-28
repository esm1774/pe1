<?php
/**
 * PE Smart School System - Subscription Manager
 * ================================================
 * يتحكم في صلاحيات المدرسة بناءً على خطة الاشتراك.
 */

class Subscription {

    /**
     * Check if the school's subscription is active
     */
    public static function isActive(?int $schoolId = null): bool {
        $schoolId = $schoolId ?? Tenant::id();
        if (!$schoolId) return false;

        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT subscription_status, trial_ends_at, subscription_ends_at FROM schools WHERE id = ?");
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
            if (!$school) return false;

            $status = $school['subscription_status'];

            // Active subscription
            if ($status === 'active') {
                if ($school['subscription_ends_at'] && $school['subscription_ends_at'] < date('Y-m-d')) {
                    // Subscription expired — auto-suspend
                    self::suspend($schoolId, 'انتهت صلاحية الاشتراك تلقائياً');
                    return false;
                }
                return true;
            }

            // Trial period
            if ($status === 'trial') {
                if ($school['trial_ends_at'] && $school['trial_ends_at'] >= date('Y-m-d')) {
                    return true;
                }
                // Trial expired
                self::suspend($schoolId, 'انتهت الفترة التجريبية');
                return false;
            }

            return false; // suspended or cancelled
        } catch (Exception $e) {
            return true; // Fail open for backward compatibility
        }
    }

    /**
     * Require active subscription or die with error
     */
    public static function requireActive(): void {
        if (!Tenant::isSaasMode()) return; // Backward compatibility
        if (Tenant::isPlatformAdmin()) return; // Super admins bypass

        if (!self::isActive()) {
            jsonError('اشتراك المدرسة غير فعّال. الرجاء التواصل مع إدارة المنصة.', 403);
        }
    }

    /**
     * Check resource limit (students, teachers, classes)
     */
    public static function checkLimit(string $resource): bool {
        if (!Tenant::isSaasMode()) return true;
        if (Tenant::isPlatformAdmin()) return true;

        $schoolId = Tenant::id();
        if (!$schoolId) return false;

        try {
            $db = getDB();

            // Get school limits (from school or plan)
            $stmt = $db->prepare("
                SELECT s.max_students, s.max_teachers, s.plan_id,
                       COALESCE(p.max_students, s.max_students) as plan_max_students,
                       COALESCE(p.max_teachers, s.max_teachers) as plan_max_teachers,
                       COALESCE(p.max_classes, 999) as plan_max_classes
                FROM schools s
                LEFT JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $limits = $stmt->fetch();
            if (!$limits) return false;

            switch ($resource) {
                case 'students':
                    $count = $db->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND active = 1");
                    $count->execute([$schoolId]);
                    return $count->fetchColumn() < $limits['plan_max_students'];

                case 'teachers':
                    $count = $db->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND active = 1");
                    $count->execute([$schoolId]);
                    return $count->fetchColumn() < $limits['plan_max_teachers'];

                case 'classes':
                    $count = $db->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND active = 1");
                    $count->execute([$schoolId]);
                    return $count->fetchColumn() < $limits['plan_max_classes'];

                default:
                    return true;
            }
        } catch (Exception $e) {
            return true; // Fail open
        }
    }

    /**
     * Require resource limit check or die
     */
    public static function requireLimit(string $resource): void {
        if (!self::checkLimit($resource)) {
            $names = [
                'students' => 'الطلاب',
                'teachers' => 'المعلمين',
                'classes' => 'الفصول'
            ];
            $name = $names[$resource] ?? $resource;
            jsonError("تم الوصول للحد الأقصى من {$name}. يرجى ترقية خطة الاشتراك.", 403);
        }
    }

    /**
     * Check if a feature is available in the current plan
     */
    public static function hasFeature(string $feature): bool {
        if (!Tenant::isSaasMode()) return true;
        if (Tenant::isPlatformAdmin()) return true;

        $schoolId = Tenant::id();
        if (!$schoolId) return false;

        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT p.features
                FROM schools s
                JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $row = $stmt->fetch();
            if (!$row || !$row['features']) return true; // No plan = all features

            $features = json_decode($row['features'], true);
            return isset($features[$feature]) ? (bool)$features[$feature] : true;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Require a specific feature
     */
    public static function requireFeature(string $feature): void {
        if (!self::hasFeature($feature)) {
            $names = [
                'tournaments' => 'البطولات',
                'badges' => 'الأوسمة',
                'notifications' => 'الإشعارات',
                'reports' => 'التقارير المتقدمة',
                'sports_teams' => 'الفرق الرياضية',
                'certificates' => 'الشهادات'
            ];
            $name = $names[$feature] ?? $feature;
            jsonError("ميزة {$name} غير متاحة في خطتك الحالية. يرجى الترقية.", 403);
        }
    }

    /**
     * Get subscription info for current school
     */
    public static function getInfo(?int $schoolId = null): array {
        $schoolId = $schoolId ?? Tenant::id();
        if (!$schoolId) return [];

        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT s.subscription_status, s.trial_ends_at, s.subscription_ends_at,
                       s.max_students, s.max_teachers,
                       p.name as plan_name, p.slug as plan_slug,
                       p.price_monthly, p.price_yearly,
                       p.max_students as plan_max_students,
                       p.max_teachers as plan_max_teachers,
                       p.max_classes as plan_max_classes,
                       p.features as plan_features
                FROM schools s
                LEFT JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $info = $stmt->fetch();
            if (!$info) return [];

            // Count current usage
            $students = $db->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND active = 1");
            $students->execute([$schoolId]);
            $teachers = $db->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND active = 1");
            $teachers->execute([$schoolId]);
            $classes = $db->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND active = 1");
            $classes->execute([$schoolId]);

            $info['current_students'] = (int)$students->fetchColumn();
            $info['current_teachers'] = (int)$teachers->fetchColumn();
            $info['current_classes'] = (int)$classes->fetchColumn();
            $info['is_active'] = self::isActive($schoolId);

            return $info;
        } catch (Exception $e) {
            return [];
        }
    }

    // ============================================================
    // ADMIN METHODS (for Super Admin / Platform)
    // ============================================================

    /**
     * Suspend a school
     */
    public static function suspend(int $schoolId, string $reason = ''): void {
        try {
            $db = getDB();
            $db->prepare("UPDATE schools SET subscription_status = 'suspended', updated_at = NOW() WHERE id = ?")
               ->execute([$schoolId]);
        } catch (Exception $e) {
            // Silent
        }
    }

    /**
     * Activate a school subscription
     */
    public static function activate(int $schoolId, int $planId, string $endsAt): void {
        try {
            $db = getDB();
            $db->prepare("
                UPDATE schools 
                SET subscription_status = 'active', 
                    plan_id = ?,
                    subscription_ends_at = ?,
                    updated_at = NOW() 
                WHERE id = ?
            ")->execute([$planId, $endsAt, $schoolId]);
        } catch (Exception $e) {
            // Silent
        }
    }

    /**
     * Start a trial for a new school
     */
    public static function startTrial(int $schoolId, int $days = 14): void {
        try {
            $db = getDB();
            $trialEnd = date('Y-m-d', strtotime("+{$days} days"));
            $db->prepare("
                UPDATE schools 
                SET subscription_status = 'trial', 
                    trial_ends_at = ?,
                    updated_at = NOW() 
                WHERE id = ?
            ")->execute([$trialEnd, $schoolId]);
        } catch (Exception $e) {
            // Silent
        }
    }
}
