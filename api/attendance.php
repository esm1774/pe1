<?php
/**
 * PE Smart School System - Attendance API
 * (Multi-Teacher Support)
 */

function getAttendance() {
    requireLogin();
    $classId = (int)getParam('class_id');
    $date    = getParam('date', date('Y-m-d'));

    if (!$classId) jsonError('يجب تحديد الفصل');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    $db = getDB();

    $queries = [
        "SELECT s.id as student_id, s.name, s.student_number, a.status, a.id as attendance_id,
               a.uniform_status, a.behavior_stars, a.skills_stars, a.participation_stars,
               (SELECT COUNT(*) FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_alerts,
               (SELECT GROUP_CONCAT(CONCAT(sh.condition_name, ' (', sh.severity, ')') SEPARATOR ', ')
                FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_summary
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name",

        "SELECT s.id as student_id, s.name, s.student_number, a.status, a.id as attendance_id,
               a.uniform_status, a.behavior_stars, a.skills_stars, a.participation_stars,
               0 as health_alerts, NULL as health_summary
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name"
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$date, $classId]);
            jsonSuccess($stmt->fetchAll());
            return;
        } catch (PDOException $e) {
            continue;
        }
    }
    jsonError('خطأ في جلب بيانات الحضور');
}

function saveAttendance() {
    requireRole(['admin', 'teacher']);
    $data    = getPostData();
    $date    = sanitize($data['date'] ?? date('Y-m-d'));
    $classId = (int)($data['class_id'] ?? 0);
    $records = $data['records'] ?? [];

    if (empty($records)) jsonError('لا توجد بيانات للحفظ');

    // Validate class ownership if class_id provided
    if ($classId && !canAccessClass($classId)) {
        jsonError('لا تملك صلاحية تسجيل حضور هذا الفصل', 403);
    }

    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO attendance (student_id, attendance_date, status, uniform_status, behavior_stars, skills_stars, participation_stars, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            uniform_status = VALUES(uniform_status), 
            behavior_stars = VALUES(behavior_stars), 
            skills_stars = VALUES(skills_stars), 
            participation_stars = VALUES(participation_stars), 
            recorded_by = VALUES(recorded_by), 
            updated_at = NOW()");

    // Fix #6: Whitelist valid status values to prevent data corruption
    $validStatuses = ['present', 'absent', 'late', 'excused'];

    $db->beginTransaction();
    try {
        foreach ($records as $record) {
            $studentId = (int)$record['student_id'];
            $status = sanitize($record['status']);
            $uniform = isset($record['uniform_status']) ? sanitize($record['uniform_status']) : null;
            $behavior = isset($record['behavior_stars']) ? (int)$record['behavior_stars'] : 0;
            $skills = isset($record['skills_stars']) ? (int)$record['skills_stars'] : 0;
            $participation = isset($record['participation_stars']) ? (int)$record['participation_stars'] : 0;

            // Fix #6: Whitelist valid status values to prevent data corruption
            if (!in_array($status, ['present', 'absent', 'late', 'excused'])) continue;
            
            // Validate uniform
            if ($uniform && !in_array($uniform, ['full', 'partial', 'wrong', 'missing'])) {
                $uniform = null;
            }

            $stmt->execute([$studentId, $date, $status, $uniform, $behavior, $skills, $participation, $_SESSION['user_id']]);

            // Trigger Notifications for Absent/Late
            if ($status === 'absent' || $status === 'late') {
                $statusAr = ($status === 'absent') ? 'غائب' : 'متأخر';
                
                // Get student name
                $sStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
                $sStmt->execute([$studentId]);
                $studentName = $sStmt->fetchColumn();

                $title = "إشعار حضور: " . $studentName;
                $msg = "نفيدكم بأن الطالب ({$studentName}) تم رصده كـ ({$statusAr}) في حصة التربية البدنية بتاريخ {$date}.";
                notifyStudentParents($studentId, 'attendance', $title, $msg);
            }
        }
        $db->commit();
        logActivity('save_attendance', 'attendance', null, "Date: $date, Records: " . count($records));
        jsonSuccess(null, 'تم حفظ الحضور بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getAbsenceReport() {
    requireLogin();
    $db = getDB();
    $teacherClassIds = getTeacherClassIds();
    $sid = schoolId();

    if ($teacherClassIds === null) {
        // Fix #10: Use prepared statements for school_id filter
        $sql = "
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name, COUNT(a.id) as absent_count
            FROM students s JOIN attendance a ON a.student_id = s.id AND a.status = 'absent'
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1";
        $params = [];
        if ($sid) { $sql .= " AND s.school_id = ?"; $params[] = $sid; }
        $sql .= " GROUP BY s.id ORDER BY absent_count DESC LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } elseif (empty($teacherClassIds)) {
        jsonSuccess([]);
        return;
    } else {
        $ph   = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name, COUNT(a.id) as absent_count
            FROM students s JOIN attendance a ON a.student_id = s.id AND a.status = 'absent'
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1 AND s.class_id IN ($ph)
            GROUP BY s.id ORDER BY absent_count DESC LIMIT 20
        ");
        $stmt->execute($teacherClassIds);
    }
    jsonSuccess($stmt->fetchAll());
}
