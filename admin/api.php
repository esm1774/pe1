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
    $startsAt = !empty($data['starts_at']) ? sanitize($data['starts_at']) : null;
    $endsAt = !empty($data['ends_at']) ? sanitize($data['ends_at']) : null;
    $features = $data['features'] ?? null;
    $notes = isset($data['subscription_notes']) ? sanitize($data['subscription_notes']) : null;
    
    $limits = [
        'max_students' => !empty($data['max_students']) ? (int)$data['max_students'] : null,
        'max_teachers' => !empty($data['max_teachers']) ? (int)$data['max_teachers'] : null,
        'max_classes'  => !empty($data['max_classes'])  ? (int)$data['max_classes']  : null,
    ];

    if (!$id) jsonError('معرف غير صالح');

    $db = getDB();

    // If notes provided, update them separately or via activate
    if ($notes !== null) {
        $db->prepare("UPDATE schools SET subscription_notes = ? WHERE id = ?")->execute([$notes, $id]);
    }

    if ($status === 'active') {
        Subscription::activate($id, $planId, $endsAt, $startsAt, $features, $limits);
    } elseif ($status === 'suspended') {
        Subscription::suspend($id);
    } elseif ($status === 'trial') {
        $days = !empty($data['trial_days']) ? (int)$data['trial_days'] : 14;
        Subscription::startTrial($id, $days);
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
    $featuresList = sanitize($data['features_list'] ?? '');

    if (is_array($features)) $features = json_encode($features, JSON_UNESCAPED_UNICODE);

    if ($isDefault) {
        $db->prepare("UPDATE plans SET is_default = 0 WHERE id != ?")->execute([$id ?? 0]);
    }

    if ($id) {
        $db->prepare("UPDATE plans SET name=?, name_en=?, slug=?, description=?, price_monthly=?, price_yearly=?, max_students=?, max_teachers=?, max_classes=?, features=?, features_list=?, is_default=?, active=?, sort_order=? WHERE id=?")
           ->execute([$name, $nameEn, $slug, $description, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features, $featuresList, $isDefault, $active, $sortOrder, $id]);
    } else {
        $db->prepare("INSERT INTO plans (name, name_en, slug, description, price_monthly, price_yearly, max_students, max_teachers, max_classes, features, features_list, is_default, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name, $nameEn, $slug, $description, $priceMonthly, $priceYearly, $maxStudents, $maxTeachers, $maxClasses, $features, $featuresList, $isDefault, $active, $sortOrder]);
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

// Consolidated: updateSchoolSubscriptionFull now points to updateSchoolSubscription or we just point the router

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
// ADMIN PASSWORD RESET
// ============================================================
function getSchoolAdmins() {
    requirePlatformAdmin();
    $schoolId = (int)(getParam('school_id', 0));
    if (!$schoolId) jsonError('معرف مدرسة غير صالح');

    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, name, active FROM users WHERE school_id = ? AND role = 'admin'");
    $stmt->execute([$schoolId]);
    jsonSuccess($stmt->fetchAll());
}

function resetSchoolAdminPassword() {
    requirePlatformAdmin();
    $data = getPostData();
    $userId = (int)($data['user_id'] ?? 0);
    $newPassword = $data['new_password'] ?? '';

    if (!$userId) jsonError('معرف مستخدم غير صالح');
    if (strlen($newPassword) < 6) jsonError('يجب أن تتكون كلمة المرور من 6 أحرف على الأقل');

    $db = getDB();
    
    // Safety check: ensure user is actually an admin
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) jsonError('المستخدم ليس مدير مدرسة أو غير موجود');

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    
    jsonSuccess(null, 'تم تعيين كلمة المرور بنجاح. أبلغ المدير ببياناته الجديدة.');
}

// ============================================================
// SYSTEM ANNOUNCEMENTS
// ============================================================
function getAnnouncements() {
    requirePlatformAdmin();
    $db = getDB();
    $ans = $db->query("
        SELECT a.*, s.name as school_name 
        FROM platform_announcements a 
        LEFT JOIN schools s ON a.target_school_id = s.id 
        ORDER BY a.id DESC
    ")->fetchAll();
    jsonSuccess($ans);
}

function saveAnnouncement() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['title', 'message']);
    
    $id = $data['id'] ?? null;
    $title = sanitize($data['title']);
    $message = $data['message']; // message can have HTML or line breaks, sanitize might be too restrictive
    $type = sanitize($data['type'] ?? 'info');
    $schoolId = !empty($data['target_school_id']) ? (int)$data['target_school_id'] : null;
    $isActive = (int)($data['is_active'] ?? 1);
    $expiresAt = !empty($data['expires_at']) ? sanitize($data['expires_at']) : null;

    $db = getDB();
    if ($id) {
        $db->prepare("UPDATE platform_announcements SET title=?, message=?, type=?, target_school_id=?, is_active=?, expires_at=? WHERE id=?")
           ->execute([$title, $message, $type, $schoolId, $isActive, $expiresAt, $id]);
    } else {
        $db->prepare("INSERT INTO platform_announcements (title, message, type, target_school_id, is_active, expires_at) VALUES (?,?,?,?,?,?)")
           ->execute([$title, $message, $type, $schoolId, $isActive, $expiresAt]);
    }
    jsonSuccess(null, 'تم حفظ الإعلان بنجاح');
}

function deleteAnnouncement() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');
    getDB()->prepare("DELETE FROM platform_announcements WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم مسح الإعلان');
}

/**
 * Export all unique subscribers for marketing
 */
function exportSubscribers() {
    requirePlatformAdmin();
    $db = getDB();
    
    // We'll collect emails from users, parents and school contact emails
    $emails = [];
    
    // 1. Staff Emails
    $res = $db->query("SELECT DISTINCT email, name FROM users WHERE email IS NOT NULL AND email != ''");
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $emails[strtolower($row['email'])] = $row['name'];
    }
    
    // 2. Parent Emails
    // Check if parents table has email column (it should from previous audit)
    $res = $db->query("SELECT DISTINCT email, name FROM parents WHERE email IS NOT NULL AND email != ''");
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $emails[strtolower($row['email'])] = $row['name'];
    }
    
    // 3. School Contact Emails
    $res = $db->query("SELECT DISTINCT email, name FROM schools WHERE email IS NOT NULL AND email != ''");
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $emails[strtolower($row['email'])] = $row['name'];
    }
    
    // Prepare CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=subscribers_'.date('Y-m-d').'.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['Email', 'Name']);
    
    foreach($emails as $email => $name) {
        fputcsv($output, [$email, $name]);
    }
    
    fclose($output);
    exit;
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
        case 'school_subscription': updateSchoolSubscription(); break;
        case 'plans':             getPlans(); break;
        case 'plan_save':         savePlan(); break;
        case 'plan_delete':       deletePlan(); break;
        case 'platform_stats':    getPlatformStats(); break;
        case 'impersonate':       impersonateSchool(); break;
        case 'get_school_admins': getSchoolAdmins(); break;
        case 'reset_school_admin': resetSchoolAdminPassword(); break;
        case 'announcements':     getAnnouncements(); break;
        case 'announcement_save': saveAnnouncement(); break;
        case 'announcement_delete': deleteAnnouncement(); break;
        case 'global_audit_logs':    getGlobalAuditLogs(); break;
        case 'advanced_analytics':   getAdvancedAnalytics(); break;
        case 'maintenance_get':      getPlatformSettingsExtended(); break;
        case 'maintenance_save':     savePlatformSettingsExtended(); break;
        case 'export_subscribers':   exportSubscribers(); break;
        case 'system_health':        getPlatformHealth(); break;
        case 'blocked_accounts':     getBlockedAccounts(); break;
        case 'unlock_logins':        unlockLogins(); break;
        
        case 'blog_posts':           getBlogPosts(); break;
        case 'blog_post':            getBlogPost(); break;
        case 'blog_save':            saveBlogPost(); break;
        case 'blog_delete':          deleteBlogPost(); break;
        case 'blog_categories':      getBlogCategories(); break;
        case 'blog_category_save':   saveBlogCategory(); break;
        case 'blog_category_delete': deleteBlogCategory(); break;

        case 'get_media':            getMedia(); break;
        case 'upload_media':         uploadMedia(); break;
        case 'delete_media':         deleteMedia(); break;

        default: jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error: ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

// ... existing functions ...

// ============================================================
// PLATFORM MAINTENANCE
// ============================================================
function getMaintenanceSettings() {
    requirePlatformAdmin();
    jsonSuccess([
        'mode' => getPlatformSetting('maintenance_mode', '0'),
        'message' => getPlatformSetting('maintenance_message', ''),
        'until' => getPlatformSetting('maintenance_until', '')
    ]);
}

function saveMaintenanceSettings() {
    requirePlatformAdmin();
    $data = getPostData();
    $db = getDB();

    $settings = [
        'maintenance_mode' => (string)($data['mode'] ?? '0'),
        'maintenance_message' => sanitize($data['message'] ?? ''),
        'maintenance_until' => sanitize($data['until'] ?? '')
    ];

    foreach ($settings as $key => $val) {
        $db->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
           ->execute([$key, $val, $val]);
    }

    logActivity('update', 'platform_settings', 0, 'تم تحديث إعدادات وضع الصيانة');
    jsonSuccess(null, 'تم حفظ الإعدادات بنجاح');
}

function getPlatformSettingsExtended() {
    requirePlatformAdmin();
    jsonSuccess([
        'payment_bank'     => getPlatformSetting('payment_bank', PAYMENT_BANK_NAME),
        'payment_iban'     => getPlatformSetting('payment_iban', PAYMENT_IBAN),
        'payment_holder'   => getPlatformSetting('payment_holder', PAYMENT_HOLDER),
        'payment_stc_pay'  => getPlatformSetting('payment_stc_pay', PAYMENT_STC_PAY),
        'payment_whatsapp' => getPlatformSetting('payment_whatsapp', PAYMENT_WHATSAPP),
        'maintenance_mode' => getPlatformSetting('maintenance_mode', '0'),
        'maintenance_message' => getPlatformSetting('maintenance_message', ''),
        'maintenance_until' => getPlatformSetting('maintenance_until', '')
    ]);
}

function savePlatformSettingsExtended() {
    requirePlatformAdmin();
    $data = getPostData();
    $db = getDB();

    $settings = [];
    $fields = ['payment_bank', 'payment_iban', 'payment_holder', 'payment_stc_pay', 'payment_whatsapp', 'maintenance_mode', 'maintenance_message', 'maintenance_until'];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $val = ($field === 'maintenance_mode') ? (string)$data[$field] : sanitize($data[$field]);
            $settings[$field] = $val;
        }
    }

    foreach ($settings as $key => $val) {
        $db->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?")
           ->execute([$key, $val, $val]);
    }

    logActivity('update', 'platform_settings', 0, 'تم تحديث إعدادات المنصة');
    jsonSuccess(null, 'تم حفظ الإعدادات بنجاح');
}

/**
 * Detailed System Health for Platform Admin
 */
function getPlatformHealth() {
    requirePlatformAdmin();
    
    $health = [
        'database' => [
            'status' => 'ok',
            'details' => 'Connected'
        ],
        'server' => [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max' => ini_get('upload_max_filesize'),
            'post_max' => ini_get('post_max_size'),
            'execution_time' => ini_get('max_execution_time') . 's',
            'debug_mode' => defined('DEBUG_MODE') ? DEBUG_MODE : false
        ],
        'storage' => [
            'status' => 'unknown',
            'free' => '—',
            'total' => '—',
            'percent' => 0
        ],
        'counts' => [
            'schools' => 0,
            'students' => 0,
            'teachers' => 0,
            'classes' => 0
        ]
    ];

    try {
        $db = getDB();
        $health['database']['version'] = $db->query("SELECT VERSION()")->fetchColumn();
        
        // Detailed DB Stats
        $stmt = $db->query("SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS `size_mb` 
                             FROM information_schema.TABLES 
                             WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
        $health['database']['tables'] = $stmt->fetchAll();

        // Counts
        $health['counts']['schools'] = (int)$db->query("SELECT COUNT(*) FROM schools")->fetchColumn();
        $health['counts']['students'] = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $health['counts']['teachers'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
        $health['counts']['classes'] = (int)$db->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    } catch (Exception $e) {
        $health['database']['status'] = 'error';
        $health['database']['details'] = $e->getMessage();
    }

    // Storage
    if (function_exists('disk_free_space')) {
        $path = __DIR__;
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free !== false && $total !== false) {
            $health['storage']['status'] = ($free < 500 * 1024 * 1024) ? 'warning' : 'ok';
            $health['storage']['free'] = round($free / 1024 / 1024 / 1024, 2) . ' GB';
            $health['storage']['total'] = round($total / 1024 / 1024 / 1024, 2) . ' GB';
            $health['storage']['percent'] = round(($free / $total) * 100, 1);
        }
    }

    jsonSuccess($health);
}

// ============================================================
// SECURITY & LOCKOUTS
// ============================================================
function getBlockedAccounts() {
    requirePlatformAdmin();
    $db = getDB();
    $lockoutTime = 900; // Match LOGIN_LOCKOUT_TIME from config
    $maxAttempts = 5;   // Match MAX_LOGIN_ATTEMPTS from config

    $stmt = $db->prepare("
        SELECT username, COUNT(*) as attempt_count, MAX(attempted_at) as last_attempt
        FROM login_attempts
        WHERE attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        GROUP BY username
        HAVING attempt_count >= ?
        ORDER BY last_attempt DESC
    ");
    $stmt->execute([$lockoutTime, $maxAttempts]);
    jsonSuccess($stmt->fetchAll());
}

function unlockLogins() {
    requirePlatformAdmin();
    $db = getDB();
    $username = sanitize(getParam('username'));

    if ($username) {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
        logActivity('unlock_login', 'platform', 0, "تم فك حظر تسجيل الدخول عن الحساب: $username");
        jsonSuccess(null, "تم فك الحظر عن الحساب ($username) بنجاح");
    } else {
        $db->query("TRUNCATE TABLE login_attempts");
        logActivity('unlock_logins', 'platform', 0, 'تم فك حظر تسجيل الدخول عن جميع الحسابات المحظورة');
        jsonSuccess(null, 'تم فك الحظر عن جميع الحسابات بنجاح');
    }
}

// ============================================================
// GLOBAL AUDIT LOG
// ============================================================

// ============================================================
// GLOBAL AUDIT LOG
// ============================================================
function getGlobalAuditLogs() {
    requirePlatformAdmin();
    $db = getDB();
    $page = max(1, (int)getParam('page', 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("
        SELECT a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at,
               u.name as user_name, u.role as user_role, s.name as school_name
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN schools s ON a.school_id = s.id
        ORDER BY a.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
    jsonSuccess($stmt->fetchAll());
}

// ============================================================
// ADVANCED ANALYTICS
// ============================================================
function getAdvancedAnalytics() {
    requirePlatformAdmin();
    $db = getDB();

    // 1. Most Active Schools (last 7 days)
    $mostActive = $db->query("
        SELECT s.name, COUNT(a.id) as activity_count
        FROM activity_log a
        JOIN schools s ON a.school_id = s.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY a.school_id
        ORDER BY activity_count DESC
        LIMIT 5
    ")->fetchAll();

    // 2. Schools nearing limits (>80%)
    $allSchools = $db->query("
        SELECT s.id, s.name, s.max_students, s.max_teachers,
               (SELECT COUNT(*) FROM students WHERE school_id = s.id AND active = 1) as student_count,
               (SELECT COUNT(*) FROM users WHERE school_id = s.id AND role = 'teacher' AND active = 1) as teacher_count
        FROM schools s
        WHERE active = 1
    ")->fetchAll();

    $alertSchools = [];
    foreach ($allSchools as $s) {
        $studentUsage = $s['max_students'] > 0 ? ($s['student_count'] / $s['max_students']) : 0;
        $teacherUsage = $s['max_teachers'] > 0 ? ($s['teacher_count'] / $s['max_teachers']) : 0;
        
        if ($studentUsage > 0.8 || $teacherUsage > 0.8) {
            $alertSchools[] = [
                'id' => $s['id'],
                'name' => $s['name'],
                'student_usage' => round($studentUsage * 100),
                'teacher_usage' => round($teacherUsage * 100),
                'counts' => [
                    'students' => $s['student_count'],
                    'max_students' => $s['max_students'],
                    'teachers' => $s['teacher_count'],
                    'max_teachers' => $s['max_teachers']
                ]
            ];
        }
    }

    // 3. Subscription Distribution
    $subDist = $db->query("
        SELECT subscription_status, COUNT(*) as count
        FROM schools
        GROUP BY subscription_status
    ")->fetchAll();

    jsonSuccess([
        'most_active' => $mostActive,
        'alert_schools' => $alertSchools,
        'subscription_distribution' => $subDist
    ]);
}

// ============================================================
// BLOG MANAGEMENT
// ============================================================

/**
 * purifyBlogHtml — strips XSS vectors & Base64 images from Quill HTML.
 * A lightweight alternative to HTMLPurifier for this use case.
 */
function purifyBlogHtml(string $html): string
{
    // 1. Remove Base64 embedded images (src="data:...") — main cause of max_allowed_packet
    $html = preg_replace('/<img([^>]*?)\ssrc=\s*[\'"](data:[^\'"]*)[\'"]([^>]*?)>/i', '', $html);

    // 2. Allow only safe HTML tags (Quill output)
    $allowedTags = '<p><br><b><strong><i><em><u><s><strike><h1><h2><h3><h4><h5><h6>'.
                   '<ol><ul><li><blockquote><pre><code><a><img><span><div>';
    $html = strip_tags($html, $allowedTags);

    // 3. Remove dangerous attributes (onclick, onerror, javascript:, etc.)
    $html = preg_replace('/\s(on\w+|style)\s*=\s*[\'"[^\'"]]*[\'"]*/i', '', $html);
    $html = preg_replace('/javascript\s*:/i', '#', $html);

    return trim($html);
}

function getBlogPosts() {
    requirePlatformAdmin();
    $db = getDB();
    // Return without content (big field) for listing performance
    $posts = $db->query("SELECT id,title,slug,category,status,image_path,excerpt,published_at,publish_at,sort_order,views,created_at FROM blog_posts ORDER BY sort_order DESC, publish_at DESC, created_at DESC")->fetchAll();
    jsonSuccess($posts);
}

function getBlogPost() {
    requirePlatformAdmin();
    $id = (int)getParam('id', 0);
    if (!$id) jsonError('معرف غير صالح');
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) jsonError('المقال غير موجود');
    jsonSuccess($post);
}

function saveBlogPost() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['title', 'slug', 'content']);
    $db = getDB();

    $id         = $data['id'] ?? null;
    $title      = sanitize($data['title']);
    $slug       = sanitize($data['slug']);
    $category   = sanitize($data['category'] ?? 'general');
    $status     = sanitize($data['status'] ?? 'draft');
    $imagePath  = sanitize($data['image_path'] ?? '');
    $excerpt    = sanitize($data['excerpt'] ?? '');
    $publishAt  = !empty($data['publish_at']) ? sanitize($data['publish_at']) : null;
    $sortOrder  = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
    $content    = purifyBlogHtml($data['content']); // ✅ Sanitize HTML, strip Base64 images

    // Guard: reject if content is empty after purification
    if (empty($content)) {
        jsonError('المحتوى فارغ أو يحتوي على بيانات غير مسموحة (مثل صور Base64). يرجى استخدام مكتبة الوسائط لإدراج الصور.');
    }

    // Ensure slug uniqueness
    $stmt = $db->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $id ?? 0]);
    if ($stmt->fetch()) jsonError('الرابط الصديق (slug) مستخدم بالفعل');

    if ($id) {
        $db->prepare("UPDATE blog_posts SET title=?, slug=?, category=?, status=?, image_path=?, excerpt=?, publish_at=?, sort_order=?, content=? WHERE id=?")
           ->execute([$title, $slug, $category, $status, $imagePath, $excerpt, $publishAt, $sortOrder, $content, $id]);
    } else {
        $db->prepare("INSERT INTO blog_posts (title, slug, category, status, image_path, excerpt, publish_at, sort_order, content, published_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$title, $slug, $category, $status, $imagePath, $excerpt, $publishAt, $sortOrder, $content, ($status === 'published' ? date('Y-m-d H:i:s') : null)]);
    }
    jsonSuccess(null, 'تم حفظ المقال بنجاح');
}

function deleteBlogPost() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');
    getDB()->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم حذف المقال');
}

function getBlogCategories() {
    requirePlatformAdmin();
    $db = getDB();
    $categories = $db->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();
    jsonSuccess($categories);
}

function saveBlogCategory() {
    requirePlatformAdmin();
    $data = getPostData();
    validateRequired($data, ['name']);
    $db = getDB();

    $id = $data['id'] ?? null;
    $name = sanitize($data['name']);

    $stmt = $db->prepare("SELECT id FROM blog_categories WHERE name = ? AND id != ?");
    $stmt->execute([$name, $id ?? 0]);
    if ($stmt->fetch()) jsonError('اسم التصنيف مستخدم بالفعل');

    if ($id) {
        $db->prepare("UPDATE blog_categories SET name=? WHERE id=?")->execute([$name, $id]);
    } else {
        $db->prepare("INSERT INTO blog_categories (name) VALUES (?)")->execute([$name]);
    }
    jsonSuccess(null, 'تم حفظ التصنيف');
}

function deleteBlogCategory() {
    requirePlatformAdmin();
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');
    getDB()->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم حذف التصنيف');
}


// ============================================================
// MEDIA LIBRARY
// ============================================================
function getMedia() {
    requirePlatformAdmin();
    $db = getDB();
    $media = $db->query("SELECT * FROM media_library ORDER BY created_at DESC")->fetchAll();
    jsonSuccess($media);
}

function uploadMedia() {
    requirePlatformAdmin();

    if (empty($_FILES['file'])) {
        jsonError('لم يتم إرسال أي ملف');
    }

    $file    = $_FILES['file'];
    $maxSize = 3 * 1024 * 1024; // 3 MB

    if ($file['size'] > $maxSize) {
        jsonError('حجم الصورة يتجاوز الحد المسموح (3 MB). يرجى ضغط الصورة أولاً.');
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes)) {
        jsonError('نوع الملف غير مسموح. يُقبل فقط: JPG, PNG, WEBP, GIF');
    }

    $extMap   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext      = $extMap[$mime] ?? 'jpg';
    $filename = 'media_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/media/';
    $savePath  = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        jsonError('فشل في رفع الملف. تحقق من صلاحيات المجلد.');
    }

    $dimensions = @getimagesize($savePath);
    $width  = $dimensions ? $dimensions[0] : null;
    $height = $dimensions ? $dimensions[1] : null;

    $db = getDB();
    $db->prepare("INSERT INTO media_library (filename, original_name, file_path, file_size, mime_type, width, height) VALUES (?,?,?,?,?,?,?)")
       ->execute([$filename, $file['name'], 'uploads/media/' . $filename, $file['size'], $mime, $width, $height]);

    $id = $db->lastInsertId();
    jsonSuccess([
        'id'        => (int)$id,
        'filename'  => $filename,
        'file_path' => 'uploads/media/' . $filename,
        'file_size' => $file['size'],
        'width'     => $width,
        'height'    => $height,
        'mime_type' => $mime,
    ], 'تم رفع الصورة بنجاح');
}

function deleteMedia() {
    requirePlatformAdmin();
    $data = getPostData();
    $id   = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');

    $db   = getDB();
    $stmt = $db->prepare("SELECT file_path FROM media_library WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();
    if (!$media) jsonError('الملف غير موجود');

    $physicalPath = __DIR__ . '/../' . $media['file_path'];
    if (file_exists($physicalPath)) {
        unlink($physicalPath);
    }

    $db->prepare("DELETE FROM media_library WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم حذف الصورة بنجاح');
}
