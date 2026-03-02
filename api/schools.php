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

    // Clean sensitive data if needed (though this is for school admins)
    unset($school['plan_id']); 
    
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

    $stmt = $db->prepare("
        UPDATE schools SET 
            name = ?, email = ?, phone = ?, address = ?, 
            week_start_day = ?, total_periods = ?, 
            school_start_time = ?, school_end_time = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $name, $email, $phone, $address, 
        $weekStart, $totalPeriods, 
        $startTime, $endTime, 
        $sid
    ]);

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
