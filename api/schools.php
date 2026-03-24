<?php
/**
 * PE Smart School System — Schools & Settings API
 * =================================================
 * يشمل: إعدادات المدرسة، رفع الشعار، معلومات الاشتراك،
 *       الإعلانات، تسجيل المدارس الجديدة (Onboarding).
 */

// ============================================================
// GET SCHOOL INFO
// ============================================================
function getSchoolInfo() {
    requireLogin();
    $sid = schoolId();
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt->execute([$sid]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school) jsonError('المدرسة غير موجودة');

    // Clean sensitive data
    unset($school['plan_id']); 
    $school['settings'] = $school['settings'] ? json_decode($school['settings'], true) : [];
    
    // Get grading weights
    $weightsStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $weightsStmt->execute([$sid]);
    $weights = $weightsStmt->fetch(PDO::FETCH_ASSOC);
    if ($weights) {
        $school['grading_weights'] = $weights;
    } else {
        $school['grading_weights'] = [
            'attendance_pct' => 20,
            'uniform_pct' => 20,
            'behavior_skills_pct' => 20,
            'participation_pct' => 0,
            'fitness_pct' => 40,
            'quiz_pct' => 0,
            'project_pct' => 0,
            'final_exam_pct' => 0,
            'quiz_max' => 10,
            'project_max' => 10,
            'final_exam_max' => 10
        ];
    }
    
    jsonSuccess($school);
}

// ============================================================
// SAVE SCHOOL INFO
// ============================================================
function saveSchoolInfo() {
    requireRole(['admin']);
    $sid = schoolId();
    $db = getDB();
    $data = getPostData();

    validateRequired($data, ['name']);

    $name = sanitize($data['name']);
    $email = sanitize($data['email'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $address = sanitize($data['address'] ?? '');
    $weekStart = (int)($data['week_start_day'] ?? 1);
    $totalPeriods = (int)($data['total_periods'] ?? 8);
    $startTime = sanitize($data['school_start_time'] ?? '07:30:00');
    $endTime = sanitize($data['school_end_time'] ?? '13:30:00');

    $principalName = sanitize($data['principal_name'] ?? '');
    $teacherName = sanitize($data['teacher_name'] ?? '');

    // Get current settings to merge
    $stmt = $db->prepare("SELECT settings FROM schools WHERE id = ?");
    $stmt->execute([$sid]);
    $currentSettingsJson = $stmt->fetchColumn();
    $settings = $currentSettingsJson ? json_decode($currentSettingsJson, true) : [];
    
    $settings['principal_name'] = $principalName;
    $settings['teacher_name'] = $teacherName;
    $settings['education_dept'] = sanitize($data['education_dept'] ?? '');
    $settingsJson = json_encode($settings);

    try {
        $stmt = $db->prepare("
            UPDATE schools SET 
                name = ?, email = ?, phone = ?, address = ?, 
                week_start_day = ?, total_periods = ?, 
                school_start_time = ?, school_end_time = ?,
                settings = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $email, $phone, $address, 
            $weekStart, $totalPeriods, 
            $startTime, $endTime, 
            $settingsJson,
            $sid
        ]);

        // Handle grading weights
        if (isset($data['attendance_pct'], $data['uniform_pct'], $data['behavior_skills_pct'], $data['participation_pct'], $data['fitness_pct'], $data['quiz_pct'], $data['project_pct'], $data['final_exam_pct'])) {
            $aPct = (int)$data['attendance_pct'];
            $uPct = (int)$data['uniform_pct'];
            $bPct = (int)$data['behavior_skills_pct'];
            $pPct = (int)$data['participation_pct'];
            $fPct = (int)$data['fitness_pct'];
            $qPct = (int)$data['quiz_pct'];
            $prjPct = (int)$data['project_pct'];
            $fnlPct = (int)$data['final_exam_pct'];
            
            if ($aPct + $uPct + $bPct + $pPct + $fPct + $qPct + $prjPct + $fnlPct !== 100) {
                jsonError('مجموع أوزان التقييم يجب أن يساوي 100%');
            }

            $wStmt = $db->prepare("
                INSERT INTO school_grading_weights (school_id, attendance_pct, uniform_pct, behavior_skills_pct, participation_pct, fitness_pct, quiz_pct, project_pct, final_exam_pct, quiz_max, project_max, final_exam_max)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    attendance_pct = VALUES(attendance_pct),
                    uniform_pct = VALUES(uniform_pct),
                    behavior_skills_pct = VALUES(behavior_skills_pct),
                    participation_pct = VALUES(participation_pct),
                    fitness_pct = VALUES(fitness_pct),
                    quiz_pct = VALUES(quiz_pct),
                    project_pct = VALUES(project_pct),
                    final_exam_pct = VALUES(final_exam_pct),
                    quiz_max = VALUES(quiz_max),
                    project_max = VALUES(project_max),
                    final_exam_max = VALUES(final_exam_max)
            ");
            $wStmt->execute([
                $sid, $aPct, $uPct, $bPct, $pPct, $fPct, $qPct, $prjPct, $fnlPct,
                (int)($data['quiz_max'] ?? 10), (int)($data['project_max'] ?? 10), (int)($data['final_exam_max'] ?? 10)
            ]);
        }

        logActivity('update', 'school_settings', $sid, 'تم تحديث إعدادات المدرسة: ' . $name);
        jsonSuccess(null, 'تم حفظ إعدادات المدرسة بنجاح');
    } catch (Exception $e) {
        jsonError('خطأ في قاعدة البيانات: ' . $e->getMessage());
    }
}

// ============================================================
// UPLOAD LOGO
// ============================================================
function uploadSchoolLogo() {
    requireRole(['admin']);
    $sid = schoolId();
    
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        jsonError('فشل رفع الملف أو لم يتم اختيار ملف.');
    }

    $file = $_FILES['logo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    // Fix: Use finfo to detect the REAL content type (MIME from browser is spoofable)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowed)) {
        jsonError('نوع الملف غير مدعوم. يرجى اختيار صورة (جيبيج, PNG, WEBP).');
    }

    // Limit size to 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        jsonError('حجم الصورة كبير جداً. الحد الأقصى 2 ميجابايت.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'school_' . $sid . '_' . time() . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/logos/';
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $db = getDB();
        
        // Delete old logo file if exists
        $stmt = $db->prepare("SELECT logo_url FROM schools WHERE id = ?");
        $stmt->execute([$sid]);
        $oldLogo = $stmt->fetchColumn();
        if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
            @unlink(__DIR__ . '/../' . $oldLogo);
        }

        $logoUrl = 'uploads/logos/' . $filename;
        $db->prepare("UPDATE schools SET logo_url = ? WHERE id = ?")->execute([$logoUrl, $sid]);

        logActivity('update', 'school_logo', $sid, 'تم تغيير شعار المدرسة');
        jsonSuccess(['logo_url' => $logoUrl], 'تم رفع الشعار بنجاح');
    } else {
        jsonError('حدث خطأ أثناء حفظ الملف على الخادم.');
    }
}
// ============================================================
// GET SUBSCRIPTION INFO
// ============================================================
function getSubscriptionInfo() {
    requireRole(['admin']);
    $info = Subscription::getInfo();
    if (empty($info)) jsonError('تعذر جلب بيانات الاشتراك');
    jsonSuccess($info);
}

/**
 * Get Active System Announcements
 * For Display in School Admin Dashboard
 */
function getActiveAnnouncements() {
    requireLogin();
    $sid = (int)schoolId();
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT id, title, message, type, created_at
        FROM platform_announcements
        WHERE is_active = 1 
          AND (expires_at IS NULL OR expires_at >= CURDATE())
          AND (target_school_id IS NULL OR target_school_id = ?)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$sid]);
    jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
}


// ============================================================
// PUBLIC ONBOARDING — لا تحتاج تسجيل دخول
// ============================================================

/**
 * Return available subscription plans (for the registration page).
 */
function getPublicPlans(): void {
    $plans = getDB()->prepare(
        "SELECT id, name, slug, price_monthly, max_students, max_teachers, max_classes
         FROM   plans WHERE active = 1 ORDER BY sort_order"
    );
    $plans->execute();
    jsonSuccess($plans->fetchAll());
}

/**
 * Register a new school (SaaS onboarding).
 * Creates the school, default admin user, and default grades in one transaction.
 */
function registerSchool(): void {
    $data = getPostData();
    validateRequired($data, ['name', 'slug', 'admin_username', 'admin_password']);

    $pwCheck = validatePasswordStrength($data['admin_password']);
    if ($pwCheck !== true) jsonError($pwCheck);

    $db        = getDB();
    $name      = sanitize($data['name']);
    $slug      = strtolower(sanitize($data['slug']));
    $adminName = sanitize($data['admin_name'] ?? ('مدير ' . $name));
    $adminEmail= sanitize($data['admin_email'] ?? '');
    $adminUser = sanitize($data['admin_username']);
    $adminPass = $data['admin_password'];
    $planId    = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;

    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        jsonError('المعرّف الفريد يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطة (-) فقط');
    }
    $stmt = $db->prepare("SELECT id FROM schools WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) jsonError('المعرّف الفريد (slug) مستخدم بالفعل، اختر اسماً آخر');

    $db->beginTransaction();
    try {
        $maxStudents = 100; $maxTeachers = 5; $isPaid = false;
        if ($planId) {
            $p = $db->prepare("SELECT max_students, max_teachers, price_monthly FROM plans WHERE id = ?");
            $p->execute([$planId]);
            $plan = $p->fetch();
            if ($plan) {
                $maxStudents = (int)$plan['max_students'];
                $maxTeachers = (int)$plan['max_teachers'];
                $isPaid      = ((float)$plan['price_monthly'] > 0);
            }
        }

        $subscriptionStatus = $isPaid ? 'pending_payment' : 'trial';
        $active             = $isPaid ? 0 : 1;
        $trialEnds          = $isPaid ? null : date('Y-m-d', strtotime('+14 days'));

        $db->prepare(
            "INSERT INTO schools
                (name, slug, email, plan_id, max_students, max_teachers, subscription_status, active, trial_ends_at)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([$name, $slug, $adminEmail, $planId, $maxStudents, $maxTeachers, $subscriptionStatus, $active, $trialEnds]);
        $newSchoolId = $db->lastInsertId();

        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $db->prepare(
            "INSERT INTO users (school_id, username, password, name, role) VALUES (?,?,?,?,?)"
        )->execute([$newSchoolId, $adminUser, $hash, $adminName, 'admin']);

        foreach ([['الصف الأول', '1', 1], ['الصف الثاني', '2', 2]] as [$gName, $gCode, $gSort]) {
            $db->prepare(
                "INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)"
            )->execute([$newSchoolId, $gName, $gCode, $gSort]);
        }

        $db->commit();

        if ($isPaid) {
            $paymentInfo = [
                'bank'     => getPlatformSetting('payment_bank',     defined('PAYMENT_BANK_NAME') ? PAYMENT_BANK_NAME : ''),
                'iban'     => getPlatformSetting('payment_iban',     defined('PAYMENT_IBAN')      ? PAYMENT_IBAN     : ''),
                'holder'   => getPlatformSetting('payment_holder',   defined('PAYMENT_HOLDER')    ? PAYMENT_HOLDER   : ''),
                'stc_pay'  => getPlatformSetting('payment_stc_pay',  defined('PAYMENT_STC_PAY')   ? PAYMENT_STC_PAY  : ''),
                'whatsapp' => getPlatformSetting('payment_whatsapp', defined('PAYMENT_WHATSAPP')  ? PAYMENT_WHATSAPP : ''),
            ];
            jsonSuccess(['slug' => $slug, 'isPaid' => true, 'paymentInfo' => $paymentInfo], 'تم تسجيل طلبك بنجاح! يرجى إكمال عملية الدفع للتفعيل.');
        } else {
            jsonSuccess(['slug' => $slug, 'isPaid' => false], 'تم تسجيل مدرستك بنجاح!');
        }
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('خطأ أثناء الإنشاء: ' . $e->getMessage());
    }
}
