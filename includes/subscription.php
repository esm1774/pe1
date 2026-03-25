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
            $stmt = $db->prepare("SELECT active, subscription_status, trial_ends_at, subscription_starts_at, subscription_ends_at FROM schools WHERE id = ?");
            $stmt->execute([$schoolId]);
            $school = $stmt->fetch();
            if (!$school || $school['active'] == 0) return false;

            $status = $school['subscription_status'];
            $today = date('Y-m-d');

            // Suspended or cancelled always inactive
            if (in_array($status, ['suspended', 'cancelled'])) return false;

            // Active subscription
            if ($status === 'active') {
                // If starts_at is set and in the future, it is not active yet
                if (!empty($school['subscription_starts_at']) && $school['subscription_starts_at'] > $today) return false;
                
                // If ends_at is set and in the past, it is expired
                if (!empty($school['subscription_ends_at']) && $school['subscription_ends_at'] < $today) {
                    self::suspend($schoolId, 'انتهت صلاحية الاشتراك تلقائياً');
                    return false;
                }
                return true;
            }

            // Trial period
            if ($status === 'trial') {
                if ($school['trial_ends_at'] && $school['trial_ends_at'] >= $today) {
                    return true;
                }
                // Trial expired
                self::suspend($schoolId, 'انتهت الفترة التجريبية');
                return false;
            }

            return false;
        } catch (Exception $e) {
            return true; // Fail open for backward compatibility during transitions
        }
    }

    /**
     * Require active subscription or die with error
     */
    public static function requireActive(): void {
        if (!Tenant::isSaasMode()) return;
        if (Tenant::isPlatformAdmin()) return;

        if (!self::isActive()) {
            jsonError('الخدمة غير مفعّلة حالياً لمدرستك. يرجى مراجعة تفاصيل الاشتراك أو التواصل مع الإدارة.', 403);
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

            // Get school limits (prefer school-level override, else plan-level, else fallback)
            $stmt = $db->prepare("
                SELECT s.max_students as school_max_students, 
                       s.max_teachers as school_max_teachers, 
                       s.max_classes as school_max_classes,
                       p.max_students as plan_max_students,
                       p.max_teachers as plan_max_teachers,
                       p.max_classes as plan_max_classes
                FROM schools s
                LEFT JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $row = $stmt->fetch();
            if (!$row) return false;

            // Determine final limit for this resource
            $limit = 0;
            if ($resource === 'students') {
                $limit = !empty($row['school_max_students']) ? $row['school_max_students'] : (!empty($row['plan_max_students']) ? $row['plan_max_students'] : 100);
            } elseif ($resource === 'teachers') {
                $limit = !empty($row['school_max_teachers']) ? $row['school_max_teachers'] : (!empty($row['plan_max_teachers']) ? $row['plan_max_teachers'] : 5);
            } elseif ($resource === 'classes') {
                $limit = !empty($row['school_max_classes']) ? $row['school_max_classes'] : (!empty($row['plan_max_classes']) ? $row['plan_max_classes'] : 10);
            }

            // Count current usage
            if ($resource === 'students') {
                $count = $db->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND active = 1");
            } elseif ($resource === 'teachers') {
                $count = $db->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND active = 1");
            } elseif ($resource === 'classes') {
                $count = $db->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND active = 1");
            } else {
                return true;
            }

            $count->execute([$schoolId]);
            return (int)$count->fetchColumn() < (int)$limit;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Require resource limit check or die
     */
    public static function requireLimit(string $resource): void {
        if (!self::checkLimit($resource)) {
            $names = ['students'=>'الطلاب', 'teachers'=>'المعلمين', 'classes'=>'الفصول'];
            $name = $names[$resource] ?? $resource;
            jsonError("تم الوصول للحد الأقصى من {$name} المسموح به في خطة اشتراكك. يرجى الترقية.", 403);
        }
    }

    /**
     * Check if a feature is available in the current plan (with school-level override)
     */
    public static function hasFeature(string $feature): bool {
        if (!Tenant::isSaasMode()) return true;
        if (Tenant::isPlatformAdmin()) return true;

        $schoolId = Tenant::id();
        if (!$schoolId) return false;

        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT s.features as school_features, p.features as plan_features
                FROM schools s
                LEFT JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $row = $stmt->fetch();
            if (!$row) return false;

            // Prioritize per-school toggles if they exist
            if (!empty($row['school_features'])) {
                $sf = json_decode($row['school_features'], true);
                if (isset($sf[$feature])) return (bool)$sf[$feature];
            }

            // Fallback to plan features
            if (!empty($row['plan_features'])) {
                $pf = json_decode($row['plan_features'], true);
                return isset($pf[$feature]) ? (bool)$pf[$feature] : true;
            }

            return true; // No plan and no override = assume free and allow all (or define default)
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
                'tournaments'   => 'البطولات الرياضية',
                'badges'        => 'الأوسمة والتحفيز',
                'notifications' => 'إشعارات أولياء الأمور',
                'reports'       => 'التقارير المتقدمة',
                'sports_teams'  => 'إدارة الفرق الرياضية',
                'certificates'  => 'إصدار الشهادات',
                'analytics'     => 'لوحة التحليلات المتقدمة',
                'fitness_tests' => 'اختبارات اللياقة البدنية',
                'timetable'     => 'جدول الحصص الأسبوعي',
                'weighted_grading' => 'محرك التقييم الموزون',
                'monitoring_report' => 'كشف المتابعة اليومي',
                'assessments_bank' => 'الاختباارت والمشاريع',
                'behavior_analytics' => 'تحليلات السلوك والمشاركة',
                'white_label'   => 'تخصيص هوية التقارير'
            ];
            $name = $names[$feature] ?? $feature;
            jsonError("ميزة '{$name}' غير مفعلة لمدرستك حالياً. يرجى ترقية الاشتراك.", 403);
        }
    }

    /**
     * Get summary subscription info
     */
    public static function getInfo(?int $schoolId = null): array {
        $schoolId = $schoolId ?? Tenant::id();
        if (!$schoolId) return [];

        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT s.subscription_status, s.subscription_starts_at, s.subscription_ends_at, s.trial_ends_at,
                       s.max_students as school_max_students, s.max_teachers as school_max_teachers, s.max_classes as school_max_classes,
                       s.features as school_features, s.subscription_notes,
                       p.name as plan_name, p.slug as plan_slug, p.features as plan_features,
                       p.max_students as plan_max_students, p.max_teachers as plan_max_teachers, p.max_classes as plan_max_classes
                FROM schools s
                LEFT JOIN plans p ON s.plan_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$schoolId]);
            $raw = $stmt->fetch();
            if (!$raw) return [];

            // Determine final limits
            $info = [
                'status'        => $raw['subscription_status'],
                'starts_at'     => $raw['subscription_starts_at'],
                'ends_at'       => ($raw['subscription_status'] === 'trial') ? $raw['trial_ends_at'] : $raw['subscription_ends_at'],
                'plan_name'     => $raw['plan_name'] ?? 'خطة مخصصة',
                'plan_slug'     => $raw['plan_slug'] ?? 'custom',
                'max_students'  => !empty($raw['school_max_students']) ? (int)$raw['school_max_students'] : (int)$raw['plan_max_students'],
                'max_teachers'  => !empty($raw['school_max_teachers']) ? (int)$raw['school_max_teachers'] : (int)$raw['plan_max_teachers'],
                'max_classes'   => !empty($raw['school_max_classes'])  ? (int)$raw['school_max_classes']  : (int)$raw['plan_max_classes'],
                'notes'         => $raw['subscription_notes'],
                'is_active'     => self::isActive($schoolId)
            ];

            // Usage stats
            $q1 = $db->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND active = 1"); $q1->execute([$schoolId]);
            $q2 = $db->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND active = 1"); $q2->execute([$schoolId]);
            $q3 = $db->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ? AND active = 1"); $q3->execute([$schoolId]);
            
            $info['usage'] = [
                'students' => (int)$q1->fetchColumn(),
                'teachers' => (int)$q2->fetchColumn(),
                'classes'  => (int)$q3->fetchColumn(),
            ];

            // Days left calculation
            $info['days_left'] = 0;
            if (!empty($info['ends_at'])) {
                $end = new DateTime($info['ends_at'] . ' 23:59:59');
                $now = new DateTime();
                if ($end > $now) {
                    $diff = $now->diff($end);
                    $info['days_left'] = (int)$diff->format('%a'); // Use %a for total days difference
                    if ($info['days_left'] == 0 && $end > $now) $info['days_left'] = 1; // At least 1 day if not yet expired today
                }
            }

            // Features merge
            $pf = !empty($raw['plan_features']) ? json_decode($raw['plan_features'], true) : [];
            $sf = !empty($raw['school_features']) ? json_decode($raw['school_features'], true) : [];
            $info['features'] = array_merge($pf, $sf);

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
     * Activate a school subscription with full parameters
     */
    public static function activate(int $schoolId, ?int $planId, ?string $endsAt, ?string $startsAt = null, ?array $features = null, ?array $limits = null, ?array $gradingWeights = null): void {
        try {
            $db = getDB();
            
            $sql = "UPDATE schools SET 
                    subscription_status = 'active', 
                    active = 1,
                    updated_at = NOW()";
            $params = [];

            if ($planId !== null) {
                $sql .= ", plan_id = ?";
                $params[] = $planId;
            }
            if ($endsAt !== null && $endsAt !== '') {
                $sql .= ", subscription_ends_at = ?";
                $params[] = $endsAt;
            } else {
                // If activating without an end date, default to +30 days from starts_at or now
                $base = (!empty($startsAt) && $startsAt !== '') ? $startsAt : date('Y-m-d');
                $sql .= ", subscription_ends_at = ?";
                $params[] = date('Y-m-d', strtotime($base . ' +30 days'));
            }
            if ($startsAt !== null) {
                $sql .= ", subscription_starts_at = ?";
                $params[] = $startsAt;
            }
            if ($features !== null) {
                $sql .= ", features = ?";
                $params[] = json_encode($features, JSON_UNESCAPED_UNICODE);
            }
            if ($limits !== null) {
                if (isset($limits['max_students'])) { $sql .= ", max_students = ?"; $params[] = $limits['max_students']; }
                if (isset($limits['max_teachers'])) { $sql .= ", max_teachers = ?"; $params[] = $limits['max_teachers']; }
                if (isset($limits['max_classes']))  { $sql .= ", max_classes = ?";  $params[] = $limits['max_classes']; }
            }

            $sql .= " WHERE id = ?";
            $params[] = $schoolId;

            $db->prepare($sql)->execute($params);

            if ($gradingWeights !== null) {
                self::saveGradingWeights($schoolId, $gradingWeights);
            }
        } catch (Exception $e) {
            // Silent
        }
    }

    /**
     * Start/Update a trial for a school
     */
    public static function startTrial(int $schoolId, int $days = 14, ?int $planId = null, ?array $features = null, ?array $limits = null, ?string $specificEndDate = null, ?array $gradingWeights = null): void {
        try {
            $db = getDB();
            
            // If specific end date provided, use it; otherwise calculate from days
            $trialEnd = ($specificEndDate !== null && $specificEndDate !== '') 
                        ? $specificEndDate 
                        : date('Y-m-d', strtotime("+{$days} days"));
            
            $sql = "UPDATE schools SET 
                    subscription_status = 'trial', 
                    active = 1,
                    trial_ends_at = ?,
                    updated_at = NOW()";
            $params = [$trialEnd];

            if ($planId !== null) {
                $sql .= ", plan_id = ?";
                $params[] = $planId;
            }
            if ($features !== null) {
                $sql .= ", features = ?";
                $params[] = json_encode($features, JSON_UNESCAPED_UNICODE);
            }
            if ($limits !== null) {
                if (isset($limits['max_students'])) { $sql .= ", max_students = ?"; $params[] = $limits['max_students']; }
                if (isset($limits['max_teachers'])) { $sql .= ", max_teachers = ?"; $params[] = $limits['max_teachers']; }
                if (isset($limits['max_classes']))  { $sql .= ", max_classes = ?";  $params[] = $limits['max_classes']; }
            }

            $sql .= " WHERE id = ?";
            $params[] = $schoolId;

            $db->prepare($sql)->execute($params);

            if ($gradingWeights !== null) {
                self::saveGradingWeights($schoolId, $gradingWeights);
            }
        } catch (Exception $e) {
            // Silent
        }
    }

    /**
     * Seed default fitness tests for a school if they don't exist
     */
    public static function seedDefaultFitnessTests($schoolId) {
        if (!$schoolId) return;
        $db = getDB();
        
        // Check if school already has tests
        $stmt = $db->prepare("SELECT COUNT(*) FROM fitness_tests WHERE school_id = ? AND active = 1");
        $stmt->execute([$schoolId]);
        if ($stmt->fetchColumn() > 0) return;

        $defaults = [
            ['جري 50 متر', 'ثانية', 'lower_better', 10],
            ['الضغط', 'عدة', 'higher_better', 10],
            ['الجلوس من الرقود', 'عدة', 'higher_better', 10],
            ['المرونة', 'سم', 'higher_better', 10],
            ['جري المكوك', 'ثانية', 'lower_better', 10]
        ];

        $stmt = $db->prepare("INSERT INTO fitness_tests (school_id, name, unit, type, max_score) VALUES (?, ?, ?, ?, ?)");
        foreach ($defaults as $d) {
            $stmt->execute([$schoolId, $d[0], $d[1], $d[2], $d[3]]);
        }
    }

    /**
     * Save/Update grading weights for a school
     */
    private static function saveGradingWeights(int $schoolId, array $weights): void {
        try {
            $db = getDB();
            $sql = "INSERT INTO school_grading_weights 
                    (school_id, attendance_pct, uniform_pct, behavior_skills_pct, participation_pct, fitness_pct, quiz_pct, project_pct, final_exam_pct)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    attendance_pct = VALUES(attendance_pct),
                    uniform_pct = VALUES(uniform_pct),
                    behavior_skills_pct = VALUES(behavior_skills_pct),
                    participation_pct = VALUES(participation_pct),
                    fitness_pct = VALUES(fitness_pct),
                    quiz_pct = VALUES(quiz_pct),
                    project_pct = VALUES(project_pct),
                    final_exam_pct = VALUES(final_exam_pct)";
            
            $db->prepare($sql)->execute([
                $schoolId,
                $weights['attendance_pct'] ?? 0,
                $weights['uniform_pct'] ?? 0,
                $weights['behavior_skills_pct'] ?? 0,
                $weights['participation_pct'] ?? 0,
                $weights['fitness_pct'] ?? 0,
                $weights['quiz_pct'] ?? 0,
                $weights['project_pct'] ?? 0,
                $weights['final_exam_pct'] ?? 0
            ]);
        } catch (Exception $e) {
            // Silent
        }
    }
}
