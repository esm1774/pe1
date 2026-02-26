<?php
/**
 * PE Smart School System - Parent Portal API
 */

function getParentDashboard() {
    requireRole(['parent']);
    $db = getDB();
    $parentId = $_SESSION['user_id'];

    // Get all students linked to this parent
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.student_number, c.name as class_name, g.name as grade_name,
               CONCAT(g.name, ' - ', c.name) as full_class_name
        FROM parent_students ps
        JOIN students s ON ps.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE ps.parent_id = ? AND s.active = 1
    ");
    $stmt->execute([$parentId]);
    $children = $stmt->fetchAll();

    foreach ($children as &$child) {
        // Latest BMI
        $stmtBmi = $db->prepare("SELECT bmi, bmi_category, measurement_date FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC LIMIT 1");
        $stmtBmi->execute([$child['id']]);
        $child['latest_measurement'] = $stmtBmi->fetch();

        // Average Fitness Score
        $stmtFit = $db->prepare("SELECT ROUND(AVG(score), 1) as avg_score FROM student_fitness WHERE student_id = ?");
        $stmtFit->execute([$child['id']]);
        $fit = $stmtFit->fetch();
        $child['avg_fitness_score'] = $fit ? $fit['avg_score'] : 0;

        // Attendance Summary
        $stmtAtt = $db->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
            FROM attendance WHERE student_id = ?
        ");
        $stmtAtt->execute([$child['id']]);
        $child['attendance_summary'] = $stmtAtt->fetch();
        
        // Health Alerts count
        $stmtHealth = $db->prepare("SELECT COUNT(*) as count FROM student_health WHERE student_id = ? AND is_active = 1");
        $stmtHealth->execute([$child['id']]);
        $child['health_alerts'] = $stmtHealth->fetch()['count'];

        // Badges
        $stmtBadge = $db->prepare("
            SELECT b.id, b.name, b.icon, b.color 
            FROM student_badges sb 
            JOIN badges b ON sb.badge_id = b.id 
            WHERE sb.student_id = ? 
            ORDER BY sb.awarded_at DESC LIMIT 5
        ");
        $stmtBadge->execute([$child['id']]);
        $child['badges'] = $stmtBadge->fetchAll();
    }

    jsonSuccess($children);
}

/**
 * Check and auto-link parent by phone number if empty
 * This can be called after login or periodically
 */
function linkChildrenByPhone() {
    requireRole(['parent']);
    $db = getDB();
    $parentId = $_SESSION['user_id'];
    
    // Get parent phone
    $stmt = $db->prepare("SELECT phone FROM parents WHERE id = ?");
    $stmt->execute([$parentId]);
    $phone = $stmt->fetch()['phone'];
    
    if (!$phone) return jsonError('رقم الجوال غير مسجل في ملفك الشخصي');

    // Find students with matching guardian phone
    $stmt = $db->prepare("
        SELECT id FROM students 
        WHERE guardian_phone = ? 
        AND id NOT IN (SELECT student_id FROM parent_students WHERE parent_id = ?)
    ");
    $stmt->execute([$phone, $parentId]);
    $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($studentIds)) {
        return jsonSuccess(null, 'لا توجد أبناء جدد للربط');
    }

    $db->beginTransaction();
    try {
        $stmtInsert = $db->prepare("INSERT INTO parent_students (parent_id, student_id) VALUES (?, ?)");
        foreach ($studentIds as $sid) {
            $stmtInsert->execute([$parentId, $sid]);
        }
        $db->commit();
        jsonSuccess(['linked_count' => count($studentIds)], 'تم ربط الأبناء بنجاح بناءً على رقم الجوال');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('خطأ في ربط الأبناء: ' . $e->getMessage());
    }
}
