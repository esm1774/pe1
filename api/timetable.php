<?php
/**
 * PE Smart School System - Timetable API
 * =====================================
 * إدارة ومزامنة جدول الحصص الأسبوعي للمعلمين
 */

// ============================================================
// GET TIMETABLE
// ============================================================
function getTimetable() {
    requireLogin();
    $db = getDB();
    $schoolId = schoolId();

    // Determine whose timetable to fetch
    $targetTeacherId = $_SESSION['user_id']; // Default to self
    
    // If admin or supervisor wants to see another teacher's timetable
    if (in_array($_SESSION['user_role'], ['admin', 'supervisor']) && isset($_GET['teacher_id'])) {
        $targetTeacherId = (int)$_GET['teacher_id'];
        
        // Ensure the requested teacher belongs to the same school
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND role = 'teacher'");
        $stmt->execute([$targetTeacherId, $schoolId]);
        if (!$stmt->fetch()) {
            jsonError('المعلم غير موجود أو لا ينتمي لهذه المدرسة.');
        }
    } else {
        // Normal user must be a teacher to have a timetable
        if ($_SESSION['user_role'] !== 'teacher') {
            jsonError('عذراً، هذه الميزة متاحة للمعلمين فقط.');
        }
    }

    $stmt = $db->prepare("
        SELECT t.id, t.day_of_week, t.period_number, t.class_id, c.name as class_name
        FROM teacher_timetables t
        JOIN classes c ON t.class_id = c.id
        WHERE t.teacher_id = ? AND t.school_id = ?
        ORDER BY t.day_of_week ASC, t.period_number ASC
    ");
    $stmt->execute([$targetTeacherId, $schoolId]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess($schedule);
}

// ============================================================
// SAVE TIMETABLE
// ============================================================
function saveTimetable() {
    requireRole(['teacher', 'admin', 'supervisor']);
    $db = getDB();
    $schoolId = schoolId();
    $teacherId = $_SESSION['user_id'];

    $data = getPostData();
    
    // If admin is saving for someone else
    if (in_array($_SESSION['user_role'], ['admin', 'supervisor']) && isset($data['teacher_id'])) {
        $teacherId = (int)$data['teacher_id'];
        // Double check this teacher belongs to the school
        $chk = $db->prepare("SELECT id FROM users WHERE id = ? AND school_id = ?");
        $chk->execute([$teacherId, $schoolId]);
        if (!$chk->fetch()) jsonError('غير مسموح بحفظ جدول لهذا المعلم');
    }

    if (!isset($data['timetable']) || !is_array($data['timetable'])) {
        jsonError('بيانات الجدول غير صالحة.');
    }

    try {
        $db->beginTransaction();

        // 1. Delete existing timetable for this teacher
        $stmtDelete = $db->prepare("DELETE FROM teacher_timetables WHERE teacher_id = ? AND school_id = ?");
        $stmtDelete->execute([$teacherId, $schoolId]);

        // 2. Insert new timetable entries
        $stmtInsert = $db->prepare("
            INSERT INTO teacher_timetables (school_id, teacher_id, class_id, day_of_week, period_number)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($data['timetable'] as $entry) {
            $classId = (int)$entry['class_id'];
            $dayOfWeek = (int)$entry['day_of_week'];
            $periodNum = (int)$entry['period_number'];

            if ($classId > 0 && $dayOfWeek >= 1 && $dayOfWeek <= 7 && $periodNum >= 1 && $periodNum <= 12) {
                // Ensure class belongs to the school
                $stmtCheck = $db->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
                $stmtCheck->execute([$classId, $schoolId]);
                if ($stmtCheck->fetch()) {
                    $stmtInsert->execute([$schoolId, $teacherId, $classId, $dayOfWeek, $periodNum]);
                }
            }
        }

        $db->commit();
        logActivity('update', 'Timetable', $teacherId, 'تم تحديث جدول الحصص للمعلم ID: ' . $teacherId);
        jsonSuccess(['message' => 'تم حفظ الجدول بنجاح.']);

    } catch (Exception $e) {
        $db->rollBack();
        jsonError('فشل حفظ الجدول: ' . $e->getMessage());
    }
}

// ============================================================
// GET PERIOD TIMES (School-level configuration)
// ============================================================
function getPeriodTimes() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();

    $stmt = $db->prepare("SELECT period_number, start_time, end_time FROM school_period_times WHERE school_id = ? ORDER BY period_number ASC");
    $stmt->execute([$sid]);
    jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ============================================================
// SAVE PERIOD TIMES
// ============================================================
function savePeriodTimes() {
    requireRole(['admin', 'teacher']);
    $db = getDB();
    $sid = schoolId();

    $data = getPostData();
    if (!isset($data['periods']) || !is_array($data['periods'])) {
        jsonError('بيانات المواعيد غير صالحة.');
    }

    try {
        $db->beginTransaction();

        // Clear existing
        $db->prepare("DELETE FROM school_period_times WHERE school_id = ?")->execute([$sid]);

        // Insert new
        $stmt = $db->prepare("INSERT INTO school_period_times (school_id, period_number, start_time, end_time) VALUES (?, ?, ?, ?)");
        foreach ($data['periods'] as $p) {
            $num   = (int)$p['period_number'];
            $start = $p['start_time'] ?? '';
            $end   = $p['end_time'] ?? '';
            if ($num >= 1 && $num <= 10 && $start && $end) {
                $stmt->execute([$sid, $num, $start, $end]);
            }
        }

        $db->commit();
        logActivity('update', 'PeriodTimes', null, 'تم تحديث مواعيد الحصص للمدرسة.');
        jsonSuccess(['message' => 'تم حفظ مواعيد الحصص بنجاح.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('فشل حفظ المواعيد: ' . $e->getMessage());
    }
}
