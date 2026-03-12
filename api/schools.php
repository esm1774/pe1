<?php
/**
 * PE Smart School System - Schools & Settings API
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
            'participation_pct' => 10,
            'fitness_pct' => 10,
            'quiz_pct' => 10,
            'project_pct' => 5,
            'final_exam_pct' => 5,
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
    $settingsJson = json_encode($settings);

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
