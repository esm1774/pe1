<?php
/**
 * PE Smart School System - Super Admin API
 * ==========================================
 * API خاص بلوحة تحكم المنصة (Platform Admin)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = getParam('action', '');

// ============================================================
// AUTH: Platform Admin Login
// ============================================================
function platformLogin() {
    $data = getPostData();
    $username = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
    if (empty($username) || empty($password)) jsonError('الرجاء إدخال البيانات');

    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password, name, role FROM platform_admins WHERE username = ? AND active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['platform_admin'] = true;
        $_SESSION['platform_admin_id'] = $admin['id'];
        $_SESSION['platform_admin_name'] = $admin['name'];
        $_SESSION['platform_admin_role'] = $admin['role'];
        $db->prepare("UPDATE platform_admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        unset($admin['password']);
        jsonSuccess($admin, 'تم تسجيل الدخول بنجاح');
    }
    jsonError('بيانات الدخول غير صحيحة');
}

function platformCheckAuth() {
    if (!Tenant::isPlatformAdmin()) {
        jsonError('غير مصرح', 401);
    }
    jsonSuccess([
        'id' => $_SESSION['platform_admin_id'],
        'name' => $_SESSION['platform_admin_name'],
        'role' => $_SESSION['platform_admin_role']
    ]);
}

function platformLogout() {
    // Fix: Properly destroy the entire session, not just unset keys
    unset($_SESSION['platform_admin'], $_SESSION['platform_admin_id'], $_SESSION['platform_admin_name'], $_SESSION['platform_admin_role']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    jsonSuccess(null, 'تم تسجيل الخروج');
}

function requirePlatformAdmin() {
    if (!Tenant::isPlatformAdmin()) {
        jsonError('صلاحيات غير كافية', 403);
    }
}

// ============================================================
// SCHOOLS MANAGEMENT
// ============================================================
function getSchools() {
    requirePlatformAdmin();
    $db = getDB();
    $schools = $db->query("
        SELECT s.*, p.name as plan_name, p.features as plan_features,
            (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id AND u.active = 1) as user_count,
            (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.active = 1) as student_count,
            (SELECT COUNT(*) FROM classes c WHERE c.school_id = s.id AND c.active = 1) as class_count
        FROM schools s
        LEFT JOIN plans p ON s.plan_id = p.id
        ORDER BY s.id DESC
    ")->fetchAll();
    jsonSuccess($schools);
}

function saveSchool() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['name', 'slug']);
    $db = getDB();

    $id = $data['id'] ?? null;
    $name = sanitize($data['name']);
    $slug = sanitize($data['slug']);
    $email = sanitize($data['email'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $city = sanitize($data['city'] ?? '');
    $region = sanitize($data['region'] ?? '');
    $planId = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;
    $maxStudents = (int)($data['max_students'] ?? 100);
    $maxTeachers = (int)($data['max_teachers'] ?? 5);

    // Check slug uniqueness
    $stmt = $db->prepare("SELECT id FROM schools WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id ?? 0]);
    if ($stmt->fetch()) jsonError('المعرف (slug) مستخدم بالفعل');

    if ($id) {
        $db->prepare("UPDATE schools SET name=?, slug=?, email=?, phone=?, city=?, region=?, plan_id=?, max_students=?, max_teachers=? WHERE id=?")
           ->execute([$name, $slug, $email, $phone, $city, $region, $planId, $maxStudents, $maxTeachers, $id]);
    } else {
        $db->prepare("INSERT INTO schools (name, slug, email, phone, city, region, plan_id, max_students, max_teachers, subscription_status, trial_ends_at) VALUES (?,?,?,?,?,?,?,?,?,'trial',?)")
           ->execute([$name, $slug, $email, $phone, $city, $region, $planId, $maxStudents, $maxTeachers, date('Y-m-d', strtotime('+14 days'))]);
        $newSchoolId = $db->lastInsertId();

        // Create default admin for this school
        if (!empty($data['admin_username']) && !empty($data['admin_password'])) {
            $hash = password_hash($data['admin_password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("INSERT INTO users (school_id, username, password, name, role) VALUES (?,?,?,?,?)")
               ->execute([$newSchoolId, sanitize($data['admin_username']), $hash, 'مدير ' . $name, 'admin']);
        }

        // Create default grades
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$newSchoolId, 'الأول', '1', 1]);
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$newSchoolId, 'الثاني', '2', 2]);
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$newSchoolId, 'الثالث', '3', 3]);

        $id = $newSchoolId;
    }

    jsonSuccess(['id' => (int)$id], 'تم حفظ المدرسة بنجاح');
}

function toggleSchool() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    $active = (int)($data['active'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');
    getDB()->prepare("UPDATE schools SET active = ? WHERE id = ?")->execute([$active, $id]);
    jsonSuccess(null, $active ? 'تم تفعيل المدرسة' : 'تم تعطيل المدرسة');
}

function updateSchoolSubscription() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    $status = sanitize($data['status'] ?? '');
    $planId = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;
    $endsAt = sanitize($data['ends_at'] ?? '');

    if (!$id) jsonError('معرف غير صالح');

    $db = getDB();
    if ($status === 'active') {
        Subscription::activate($id, $planId, $endsAt);
    } elseif ($status === 'suspended') {
        Subscription::suspend($id);
    } elseif ($status === 'trial') {
        Subscription::startTrial($id, 14);
    }

    jsonSuccess(null, 'تم تحديث حالة الاشتراك');
}

// ============================================================
// PLANS MANAGEMENT
// ============================================================
function getPlans() {
    requirePlatformAdmin();
    jsonSuccess(getDB()->query("SELECT * FROM plans ORDER BY sort_order, id")->fetchAll());
}

function savePlan() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['name', 'slug']);
    $db = getDB();

    $id           = $data['id'] ?? null;
    $name         = sanitize($data['name']);
    $nameEn       = sanitize($data['name_en'] ?? '');
    $slug         = strtolower(trim(sanitize($data['slug'])));
    $description  = sanitize($data['description'] ?? '');
    $priceMonthly = (float)($data['price_monthly'] ?? 0);
    $priceYearly  = (float)($data['price_yearly']  ?? 0);
    $maxStudents  = (int)($data['max_students'] ?? 100);
    $maxTeachers  = (int)($data['max_teachers'] ?? 5);
    $maxClasses   = (int)($data['max_classes']  ?? 10);
    $isDefault    = (int)(!empty($data['is_default']));
    $active       = isset($data['active']) ? (int)($data['active']) : 1;
    $sortOrder    = (int)($data['sort_order'] ?? 0);
    $features     = $data['features'] ?? '{}';

    if (is_array($features)) $features = json_encode($features, JSON_UNESCAPED_UNICODE);

    if ($isDefault) {
        $db->prepare("UPDATE plans SET is_default = 0 WHERE id != ?")->execute([$id ?? 0]);
    }

    if ($id) {
        $db->prepare("UPDATE plans SET name=?, name_en=?, slug=?, description=?, price_monthly=?, price_yearly=?, max_students=?, max_teachers=?, max_classes=?, features=?, is_default=?, active=?, sort_order=? WHERE id=?")
           ->execute([$name, $nameEn, $slug, $description, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features, $isDefault, $active, $sortOrder, $id]);
    } else {
        $db->prepare("INSERT INTO plans (name, name_en, slug, description, price_monthly, price_yearly, max_students, max_teachers, max_classes, features, is_default, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name, $nameEn, $slug, $description, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features, $isDefault, $active, $sortOrder]);
        $id = $db->lastInsertId();
    }
    jsonSuccess(['id' => (int)$id], 'تم حفظ الخطة بنجاح');
}

function deletePlan() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM schools WHERE plan_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) jsonError('لا يمكن مسح الخطة لأن مدارس مشتركة بها. يرجى نقلها لخطة أخرى أولاً.');
    $db->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم مسح الخطة');
}

function updateSchoolSubscriptionFull() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');

    $db         = getDB();
    $status     = sanitize($data['status'] ?? '');
    $planId     = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;
    $startsAt   = sanitize($data['starts_at'] ?? '');
    $endsAt     = sanitize($data['ends_at'] ?? '');
    $trialDays  = (int)($data['trial_days'] ?? 14);
    $maxStudents= !empty($data['max_students']) ? (int)$data['max_students'] : null;
    $maxTeachers= !empty($data['max_teachers']) ? (int)$data['max_teachers'] : null;
    $maxClasses = !empty($data['max_classes'])  ? (int)$data['max_classes']  : null;
    $notes      = sanitize($data['subscription_notes'] ?? '');
    $features   = $data['features'] ?? null;

    if (is_array($features)) {
        $features = json_encode($features, JSON_UNESCAPED_UNICODE);
    }

    // Prepare update parameters
    // We update subscription_starts_at always if sent, even if empty (sets to NULL)
    $updateData = [
        'subscription_status' => $status,
        'subscription_notes'  => $notes,
        'subscription_starts_at' => !empty($startsAt) ? $startsAt : null,
        'max_students'        => $maxStudents,
        'max_teachers'        => $maxTeachers,
        'max_classes'         => $maxClasses,
        'features'            => $features
    ];

    if ($planId !== null) {
        $updateData['plan_id'] = $planId;
    }

    // Mapping ends_at based on status
    if ($status === 'trial') {
        $updateData['trial_ends_at'] = date('Y-m-d', strtotime("+{$trialDays} days"));
    } else {
        if (!empty($endsAt)) {
            $updateData['subscription_ends_at'] = $endsAt;
        }
    }

    // Build query
    $sets = [];
    $params = [];
    foreach ($updateData as $col => $val) {
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    $params[] = $id;

    $sql = "UPDATE schools SET " . implode(", ", $sets) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    jsonSuccess(null, 'تم تحديث بيانات الاشتراك والميزات للمدرسة بنجاح');
}

// ============================================================
// PLATFORM ANALYTICS
// ============================================================
// SCHOOL DELETION
// ============================================================
function deleteSchool() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);

    if (!$id) jsonError('معرف مدرسة غير صالح');

    $db = getDB();

    // Begin Transaction to prevent partial deletion
    $db->beginTransaction();

    try {
        // Fix: Use prepared statements instead of $id interpolation in SQL
        // 1. Delete deeply nested related data
        $db->prepare("DELETE mm FROM match_media mm INNER JOIN matches m ON mm.match_id = m.id INNER JOIN tournaments t ON m.tournament_id = t.id WHERE t.school_id = ?")->execute([$id]);
        $db->prepare("DELETE me FROM match_events me INNER JOIN matches m ON me.match_id = m.id INNER JOIN tournaments t ON m.tournament_id = t.id WHERE t.school_id = ?")->execute([$id]);
        $db->prepare("DELETE stm FROM student_team_members stm INNER JOIN students s ON stm.student_id = s.id WHERE s.school_id = ?")->execute([$id]);
        $db->prepare("DELETE tps FROM tournament_player_stats tps INNER JOIN tournaments t ON tps.tournament_id = t.id WHERE t.school_id = ?")->execute([$id]);
        
        // 2. Delete main modules data using prepared statements
        $tablesWithSchoolId = [
            'activity_log', 'attendance', 'badges', 'class_points', 'classes', 'fitness_criteria',
            'fitness_tests', 'grades', 'matches', 'notifications', 'parent_students', 'parents', 
            'password_resets', 'sports_calendar', 'sports_teams', 'standings', 'student_badges', 
            'student_fitness', 'student_health', 'student_measurements', 'student_teams', 'students', 
            'teacher_classes', 'team_members', 'tournament_teams', 'tournaments', 
            'training_attendance', 'training_sessions', 'users' 
        ];

        foreach ($tablesWithSchoolId as $table) {
            // Fix: Use prepared statement for each table delete
            try {
                $db->prepare("DELETE FROM `$table` WHERE school_id = ?")->execute([$id]);
            } catch (PDOException $e) {
                // Some tables may not have school_id column - silently skip
            }
        }

        // 3. Delete the School Row itself
        $db->prepare("DELETE FROM schools WHERE id = ?")->execute([$id]);

        $db->commit();
        jsonSuccess(null, 'تم مسح مدرسة وبياناتها بالكامل من النظام');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('فشل مسح المدرسة: ' . $e->getMessage());
    }
}

// ============================================================
function getPlatformStats() {
    requirePlatformAdmin();
    $db = getDB();
    $stats = [
        'total_schools' => (int)$db->query("SELECT COUNT(*) FROM schools WHERE active = 1")->fetchColumn(),
        'total_students' => (int)$db->query("SELECT COUNT(*) FROM students WHERE active = 1")->fetchColumn(),
        'total_teachers' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND active = 1")->fetchColumn(),
        'active_subscriptions' => (int)$db->query("SELECT COUNT(*) FROM schools WHERE subscription_status = 'active'")->fetchColumn(),
        'trial_subscriptions' => (int)$db->query("SELECT COUNT(*) FROM schools WHERE subscription_status = 'trial'")->fetchColumn(),
        'suspended_subscriptions' => (int)$db->query("SELECT COUNT(*) FROM schools WHERE subscription_status = 'suspended'")->fetchColumn(),
    ];
    jsonSuccess($stats);
}

// ============================================================
// IMPERSONATION
// ============================================================
function impersonateSchool() {
    requirePlatformAdmin();
    $data = getPostData();
    $schoolId = (int)($data['school_id'] ?? 0);
    if (!$schoolId) jsonError('معرف مدرسة غير صالح');

    $db = getDB();
    $school = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $school->execute([$schoolId]);
    if (!$school->fetch()) jsonError('المدرسة غير موجودة');

    Tenant::impersonate($schoolId);
    jsonSuccess(null, 'تم الدخول كمدرسة');
}

// ============================================================
// ROUTER
// ============================================================
try {
    switch ($action) {
        case 'platform_login':    platformLogin(); break;
        case 'platform_check':    platformCheckAuth(); break;
        case 'platform_logout':   platformLogout(); break;
        case 'schools':           getSchools(); break;
        case 'school_save':       saveSchool(); break;
        case 'school_toggle':     toggleSchool(); break;
        case 'school_delete':     deleteSchool(); break;
        case 'school_subscription': updateSchoolSubscriptionFull(); break;
        case 'plans':             getPlans(); break;
        case 'plan_save':         savePlan(); break;
        case 'plan_delete':       deletePlan(); break;
        case 'platform_stats':    getPlatformStats(); break;
        case 'impersonate':       impersonateSchool(); break;
        default: jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error: ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
