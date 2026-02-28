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
    unset($_SESSION['platform_admin'], $_SESSION['platform_admin_id'], $_SESSION['platform_admin_name']);
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
        SELECT s.*, p.name as plan_name,
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
    jsonSuccess(getDB()->query("SELECT * FROM plans ORDER BY sort_order")->fetchAll());
}

function savePlan() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['name', 'slug']);
    $db = getDB();

    $id = $data['id'] ?? null;
    $name = sanitize($data['name']);
    $slug = sanitize($data['slug']);
    $priceMonthly = (float)($data['price_monthly'] ?? 0);
    $priceYearly = (float)($data['price_yearly'] ?? 0);
    $maxStudents = (int)($data['max_students'] ?? 100);
    $maxTeachers = (int)($data['max_teachers'] ?? 5);
    $maxClasses = (int)($data['max_classes'] ?? 10);
    $features = $data['features'] ?? '{}';

    if (is_array($features)) $features = json_encode($features);

    if ($id) {
        $db->prepare("UPDATE plans SET name=?, slug=?, price_monthly=?, price_yearly=?, max_students=?, max_teachers=?, max_classes=?, features=? WHERE id=?")
           ->execute([$name, $slug, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features, $id]);
    } else {
        $db->prepare("INSERT INTO plans (name, slug, price_monthly, price_yearly, max_students, max_teachers, max_classes, features) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$name, $slug, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features]);
        $id = $db->lastInsertId();
    }
    jsonSuccess(['id' => (int)$id], 'تم حفظ الخطة');
}

// ============================================================
// PLATFORM ANALYTICS
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
        case 'school_subscription': updateSchoolSubscription(); break;
        case 'plans':             getPlans(); break;
        case 'plan_save':         savePlan(); break;
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
