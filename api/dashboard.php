<?php
/**
 * PE Smart School System - Dashboard API
 * (Multi-Teacher Support)
 */

function getDashboard() {
    requireLogin();
    
    // Protection: students should not call this endpoint
    if ($_SESSION['user_role'] === 'student') {
        return getStudentDashboardSummary();
    }

    $db = getDB();
    $teacherClassIds = getTeacherClassIds(); // null = admin = no restriction

    // ── Build class filter clause ──────────────────────────────────────
    if ($teacherClassIds === null) {
        // Admin: no filter
        $classFilter  = '';
        $classParams  = [];
        $cFilterWhere = 'WHERE c.active = 1';
    } elseif (empty($teacherClassIds)) {
        // Teacher with no assigned classes → return empty dashboard
        jsonSuccess([
            'stats'      => ['totalStudents' => 0, 'totalClasses' => 0, 'presentToday' => 0, 'absentToday' => 0, 'healthAlerts' => 0],
            'ranking'    => [],
            'topStudent' => null
        ]);
        return;
    } else {
        $ph           = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $classFilter  = "AND s.class_id IN ($ph)";
        $classParams  = $teacherClassIds;
        $cFilterWhere = "WHERE c.active = 1 AND c.id IN ($ph)";
    }

    // ── Total Students ─────────────────────────────────────────────────
    if ($teacherClassIds === null) {
        $totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE active = 1")->fetchColumn();
        $totalClasses  = $db->query("SELECT COUNT(*) FROM classes WHERE active = 1")->fetchColumn();
    } else {
        $ph = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE active = 1 AND class_id IN ($ph)");
        $stmt->execute($teacherClassIds);
        $totalStudents = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM classes WHERE active = 1 AND id IN ($ph)");
        $stmt->execute($teacherClassIds);
        $totalClasses = $stmt->fetchColumn();
    }

    // ── Attendance today ───────────────────────────────────────────────
    $today = date('Y-m-d');
    if ($teacherClassIds === null) {
        $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE attendance_date = ? GROUP BY status");
        $stmt->execute([$today]);
    } else {
        $ph   = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("
            SELECT a.status, COUNT(*) as cnt
            FROM attendance a JOIN students s ON a.student_id = s.id
            WHERE a.attendance_date = ? AND s.class_id IN ($ph)
            GROUP BY a.status
        ");
        $stmt->execute(array_merge([$today], $teacherClassIds));
    }
    $attStats = ['present' => 0, 'absent' => 0, 'late' => 0];
    while ($row = $stmt->fetch()) $attStats[$row['status']] = (int)$row['cnt'];

    // ── Health alerts ──────────────────────────────────────────────────
    $healthAlerts = 0;
    try {
        if ($teacherClassIds === null) {
            $healthAlerts = $db->query("SELECT COUNT(DISTINCT student_id) FROM student_health WHERE is_active = 1")->fetchColumn();
        } else {
            $ph   = implode(',', array_fill(0, count($teacherClassIds), '?'));
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT sh.student_id)
                FROM student_health sh JOIN students s ON sh.student_id = s.id
                WHERE sh.is_active = 1 AND s.class_id IN ($ph)
            ");
            $stmt->execute($teacherClassIds);
            $healthAlerts = $stmt->fetchColumn();
        }
    } catch (Exception $e) {}

    // ── Class ranking ──────────────────────────────────────────────────
    if ($teacherClassIds === null) {
        $rankingSql = "
            SELECT c.id as class_id, CONCAT(g.name, ' - ', c.name) as class_name,
                   COUNT(DISTINCT s.id) as students_count,
                   ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
            FROM classes c JOIN grades g ON c.grade_id = g.id
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            LEFT JOIN student_fitness sf ON sf.student_id = s.id
            WHERE c.active = 1 GROUP BY c.id ORDER BY avg_score DESC LIMIT 5";
        $ranking = $db->query($rankingSql)->fetchAll();
    } else {
        $ph      = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt    = $db->prepare("
            SELECT c.id as class_id, CONCAT(g.name, ' - ', c.name) as class_name,
                   COUNT(DISTINCT s.id) as students_count,
                   ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
            FROM classes c JOIN grades g ON c.grade_id = g.id
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            LEFT JOIN student_fitness sf ON sf.student_id = s.id
            WHERE c.active = 1 AND c.id IN ($ph)
            GROUP BY c.id ORDER BY avg_score DESC LIMIT 5
        ");
        $stmt->execute($teacherClassIds);
        $ranking = $stmt->fetchAll();
    }

    // ── Top student ────────────────────────────────────────────────────
    if ($teacherClassIds === null) {
        $topStudent = $db->query("
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
                   ROUND(AVG(sf.score), 2) as avg_score
            FROM students s JOIN student_fitness sf ON sf.student_id = s.id
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1 GROUP BY s.id ORDER BY avg_score DESC LIMIT 1
        ")->fetch();
    } else {
        $ph   = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
                   ROUND(AVG(sf.score), 2) as avg_score
            FROM students s JOIN student_fitness sf ON sf.student_id = s.id
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1 AND s.class_id IN ($ph)
            GROUP BY s.id ORDER BY avg_score DESC LIMIT 1
        ");
        $stmt->execute($teacherClassIds);
        $topStudent = $stmt->fetch();
    }

    jsonSuccess([
        'stats' => [
            'totalStudents' => (int)$totalStudents,
            'totalClasses'  => (int)$totalClasses,
            'presentToday'  => $attStats['present'],
            'absentToday'   => $attStats['absent'],
            'healthAlerts'  => (int)$healthAlerts
        ],
        'ranking'    => $ranking,
        'topStudent' => $topStudent
    ]);
}

/**
 * Student Dashboard Summary
 * Fetches personal stats for the logged-in student
 */
function getStudentDashboardSummary() {
    requireRole(['student']);
    $db = getDB();
    $studentId = $_SESSION['user_id'];

    // 1. Basic Student Info
    $stmt = $db->prepare("SELECT id, name, student_number, class_id, medical_notes FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    // 2. Latest Measurements
    $stmt = $db->prepare("SELECT height_cm, weight_kg, bmi, bmi_category, waist_cm, resting_heart_rate 
                          FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC LIMIT 1");
    $stmt->execute([$studentId]);
    $measurements = $stmt->fetch();
    if ($measurements) {
        $cats = ['underweight' => 'نحيف', 'normal' => 'طبيعي', 'overweight' => 'وزن زائد', 'obese' => 'سمنة'];
        $measurements['bmi_category_ar'] = $cats[$measurements['bmi_category']] ?? 'غير محدد';
    }

    // 3. Attendance Stats
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE student_id = ? GROUP BY status");
    $stmt->execute([$studentId]);
    $att = ['present' => 0, 'total' => 0];
    while ($row = $stmt->fetch()) {
        if ($row['status'] === 'present') $att['present'] = (int)$row['cnt'];
        $att['total'] += (int)$row['cnt'];
    }
    $att['percentage'] = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100) : 0;

    // 4. Joined Teams
    $teams = [];
    try {
        $stmt = $db->prepare("SELECT t.name as team_name, t.sport_type 
                              FROM sports_team_members stm 
                              JOIN sports_teams t ON stm.team_id = t.id 
                              WHERE stm.student_id = ?");
        $stmt->execute([$studentId]);
        $teams = $stmt->fetchAll();
    } catch (Exception $e) { /* Table might not exist yet or error in schema */ }

    // 5. Badges
    $badges = [];
    try {
        $stmt = $db->prepare("SELECT b.name, b.icon, b.color, b.description, sb.awarded_at 
                              FROM student_badges sb 
                              JOIN badges b ON sb.badge_id = b.id 
                              WHERE sb.student_id = ? ORDER BY sb.awarded_at DESC");
        $stmt->execute([$studentId]);
        $badges = $stmt->fetchAll();
    } catch (Exception $e) {}

    jsonSuccess([
        'student' => $student,
        'measurements' => $measurements,
        'attendance' => $att,
        'teams' => $teams,
        'badges' => $badges
    ]);
}
