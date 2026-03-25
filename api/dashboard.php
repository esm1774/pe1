<?php
/**
 * PE Smart School System - Dashboard API
 * (Multi-Teacher + SaaS Support)
 */

function getDashboard() {
    requireLogin();
    
    // Protection: students should not call this endpoint
    if ($_SESSION['user_role'] === 'student') {
        return getStudentDashboardSummary();
    }

    $db = getDB();
    $teacherClassIds = getTeacherClassIds(); // null = admin = no restriction
    $sid = schoolId();

    // ── Build class filter clause ──────────────────────────────────────
    if ($teacherClassIds === null) {
        // Admin: no filter (but scoped to school)
        $classFilter  = '';
        $classParams  = [];
        $cFilterWhere = 'WHERE c.active = 1';
        if ($sid) {
            // Fix: Store sid for parameterized use, not raw interpolation
            $classFilter  = "AND s.school_id = ?";
            $cFilterWhere = "WHERE c.active = 1 AND c.school_id = ?";
        }
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
        // Fix: Use prepared statements for school_id filters
        $sql = "SELECT COUNT(*) FROM students WHERE active = 1";
        $countParams = [];
        if ($sid) { $sql .= " AND school_id = ?"; $countParams[] = $sid; }
        $s = $db->prepare($sql); $s->execute($countParams);
        $totalStudents = $s->fetchColumn();

        $sql = "SELECT COUNT(*) FROM classes WHERE active = 1";
        $countParams2 = [];
        if ($sid) { $sql .= " AND school_id = ?"; $countParams2[] = $sid; }
        $s = $db->prepare($sql); $s->execute($countParams2);
        $totalClasses = $s->fetchColumn();
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
        if ($sid) {
            $stmt = $db->prepare("SELECT a.status, COUNT(*) as cnt FROM attendance a JOIN students s ON a.student_id = s.id WHERE a.attendance_date = ? AND s.school_id = ? GROUP BY a.status");
            $stmt->execute([$today, $sid]);
        } else {
            $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE attendance_date = ? GROUP BY status");
            $stmt->execute([$today]);
        }
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
            if ($sid) {
                $healthAlerts = $db->prepare("SELECT COUNT(DISTINCT sh.student_id) FROM student_health sh JOIN students s ON sh.student_id = s.id WHERE sh.is_active = 1 AND s.school_id = ?");
                $healthAlerts->execute([$sid]);
                $healthAlerts = $healthAlerts->fetchColumn();
            } else {
                $healthAlerts = $db->query("SELECT COUNT(DISTINCT student_id) FROM student_health WHERE is_active = 1")->fetchColumn();
            }
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
    // Fix: Use parameterized query for school filter
    if ($teacherClassIds === null) {
        $rankSql = "
            SELECT c.id as class_id, CONCAT(g.name, ' - ', c.name) as class_name,
                   COUNT(DISTINCT s.id) as students_count,
                   ROUND(COALESCE(SUM(sf.score), 0) / (
                       SELECT GREATEST(1, COUNT(*) * COUNT(DISTINCT s2.id))
                       FROM fitness_tests ft2, students s2
                       WHERE ft2.active = 1 AND ft2.school_id = c.school_id AND s2.class_id = c.id AND s2.active = 1
                   ), 2) as avg_score
            FROM classes c JOIN grades g ON c.grade_id = g.id
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            LEFT JOIN student_fitness sf ON sf.student_id = s.id
            WHERE c.active = 1";
        $rankParams = [];
        if ($sid) { $rankSql .= " AND c.school_id = ?"; $rankParams[] = $sid; }
        $rankSql .= " GROUP BY c.id ORDER BY avg_score DESC LIMIT 5";
        $rankStmt = $db->prepare($rankSql);
        $rankStmt->execute($rankParams);
        $ranking = $rankStmt->fetchAll();
    } else {
        $ph      = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt    = $db->prepare("
            SELECT c.id as class_id, CONCAT(g.name, ' - ', c.name) as class_name,
                   COUNT(DISTINCT s.id) as students_count,
                   ROUND(COALESCE(SUM(sf.score), 0) / (
                       SELECT GREATEST(1, COUNT(*) * COUNT(DISTINCT s2.id)) 
                       FROM fitness_tests ft2, students s2 
                       WHERE ft2.active = 1 AND ft2.school_id = c.school_id AND s2.class_id = c.id AND s2.active = 1
                   ), 2) as avg_score
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
    // Fix: Use parameterized query for school filter
    if ($teacherClassIds === null) {
        $topSql = "
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
                   ROUND(COALESCE(SUM(sf.score), 0) / (SELECT GREATEST(1, COUNT(*)) FROM fitness_tests ft2 WHERE ft2.active = 1 AND ft2.school_id = s.school_id), 2) as avg_score
            FROM students s JOIN student_fitness sf ON sf.student_id = s.id
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1";
        $topParams = [];
        if ($sid) { $topSql .= " AND s.school_id = ?"; $topParams[] = $sid; }
        $topSql .= " GROUP BY s.id ORDER BY avg_score DESC LIMIT 1";
        $topStmt = $db->prepare($topSql);
        $topStmt->execute($topParams);
        $topStudent = $topStmt->fetch();
    } else {
        $ph   = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("
            SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
                   ROUND(COALESCE(SUM(sf.score), 0) / (SELECT GREATEST(1, COUNT(*)) FROM fitness_tests ft2 WHERE ft2.active = 1 AND ft2.school_id = s.school_id), 2) as avg_score
            FROM students s JOIN student_fitness sf ON sf.student_id = s.id
            JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
            WHERE s.active = 1 AND s.class_id IN ($ph)
            GROUP BY s.id ORDER BY avg_score DESC LIMIT 1
        ");
        $stmt->execute($teacherClassIds);
        $topStudent = $stmt->fetch();
    }

    // ── Today's Timetable (Only for Teachers) ──────────────────────────
    $todayTimetable = [];
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher') {
        // PHP date('w'): 0 (Sun) to 6 (Sat). Our DB: 1=Sun .. 5=Thu
        $dayOfWeek = (int)date('w') + 1; 
        
        $stmt = $db->prepare("
            SELECT t.period_number, t.class_id, CONCAT(g.name, ' - ', c.name) as class_name,
                   pt.start_time, pt.end_time
            FROM teacher_timetables t
            JOIN classes c ON t.class_id = c.id
            JOIN grades g ON c.grade_id = g.id
            LEFT JOIN school_period_times pt ON pt.school_id = t.school_id AND pt.period_number = t.period_number
            WHERE t.teacher_id = ? AND t.school_id = ? AND t.day_of_week = ?
            ORDER BY t.period_number ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $sid, $dayOfWeek]);
        $todayTimetable = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        'topStudent' => $topStudent,
        'todayTimetable' => $todayTimetable,
        'serverTime' => date('H:i')
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
