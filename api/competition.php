<?php
/**
 * PE Smart School System - Competition & Reports API
 */

// ============================================================
// COMPETITION
// ============================================================
function getCompetition() {
    requireLogin();
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['user_role'];

    // 1. School-wide Class Ranking
    $classRanking = $db->query("
        SELECT c.id as class_id, g.name as grade_name, c.name as class_name,
               CONCAT(g.name, ' - ', c.name) as full_class_name,
               COUNT(DISTINCT s.id) as students_count,
               ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score,
               ROUND(COALESCE(AVG(sf.score), 0) * 10) as points
        FROM classes c JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE c.active = 1 GROUP BY c.id ORDER BY avg_score DESC
    ")->fetchAll();

    // 2. School-wide Top 10 Students
    $topStudents = $db->query("
        SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
               ROUND(AVG(sf.score), 2) as avg_score, SUM(sf.score) as total_score, COUNT(sf.id) as test_count
        FROM students s JOIN student_fitness sf ON sf.student_id = s.id
        JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 GROUP BY s.id ORDER BY avg_score DESC LIMIT 10
    ")->fetchAll();

    $studentData = null;
    $targetStudentId = null;

    if ($userRole === 'student') {
        $targetStudentId = $userId;
    } elseif ($userRole === 'parent') {
        $targetStudentId = getParam('student_id');
        
        // Security: Check if this student is linked to the parent
        if ($targetStudentId) {
            $stmt = $db->prepare("SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ?");
            $stmt->execute([$userId, $targetStudentId]);
            if (!$stmt->fetch()) {
                $targetStudentId = null; // Unauthorized or not specified
            }
        }
    }

    if ($targetStudentId) {
        // Find student's own class info
        $stmt = $db->prepare("SELECT class_id FROM students WHERE id = ?");
        $stmt->execute([$targetStudentId]);
        $student = $stmt->fetch();
        $classId = $student ? $student['class_id'] : null;

        if ($classId) {
            // Student's school-wide rank
            $schoolRanking = $db->query("
                SELECT s.id, ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
                FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                WHERE s.active = 1 GROUP BY s.id ORDER BY avg_score DESC
            ")->fetchAll();
            
            $schoolRank = 0;
            foreach ($schoolRanking as $index => $r) {
                if ($r['id'] == $targetStudentId) { $schoolRank = $index + 1; break; }
            }

            // Student's class rank
            $classRankingList = $db->prepare("
                SELECT s.id, ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
                FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                WHERE s.class_id = ? AND s.active = 1 GROUP BY s.id ORDER BY avg_score DESC
            ");
            $classRankingList->execute([$classId]);
            $classStudents = $classRankingList->fetchAll();

            $classRank = 0;
            foreach ($classStudents as $index => $r) {
                if ($r['id'] == $targetStudentId) { $classRank = $index + 1; break; }
            }

            // Top 3 in student's class
            $stmt = $db->prepare("
                SELECT s.id, s.name, ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
                FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                WHERE s.class_id = ? AND s.active = 1 GROUP BY s.id ORDER BY avg_score DESC LIMIT 3
            ");
            $stmt->execute([$classId]);
            $classTop3 = $stmt->fetchAll();

            $studentData = [
                'studentId' => $targetStudentId,
                'schoolRank' => $schoolRank,
                'classRank' => $classRank,
                'classId' => $classId,
                'classTop3' => $classTop3,
                'totalInSchool' => count($schoolRanking),
                'totalInClass' => count($classStudents)
            ];
        }
    }

    jsonSuccess([
        'classRanking' => $classRanking, 
        'topStudents' => $topStudents,
        'studentData' => $studentData
    ]);
}

// ============================================================
// REPORTS
// ============================================================
function getStudentReport() {
    requireLogin();
    $studentId = getParam('student_id');
    if (!$studentId) jsonError('يجب تحديد الطالب');
    
    // Security: Student can only see their own report
    if ($_SESSION['user_role'] === 'student' && $_SESSION['user_id'] != $studentId) {
        jsonError('غير مصرح لك بمشاهدة هذا التقرير');
    }

    // Security: Parent can only see their linked children
    if ($_SESSION['user_role'] === 'parent') {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ?");
        $stmt->execute([$_SESSION['user_id'], $studentId]);
        if (!$stmt->fetch()) {
            jsonError('غير مصرح لك بمشاهدة تقرير هذا الطالب');
        }
    }

    $db = getDB();

    $stmt = $db->prepare("SELECT s.*, CONCAT(g.name, ' - ', c.name) as full_class_name, g.name as grade_name, c.name as class_name,
        TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
        FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE s.id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) jsonError('الطالب غير موجود');

    $measurement = null;
    try {
        $stmt = $db->prepare("SELECT * FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC LIMIT 1");
        $stmt->execute([$studentId]);
        $measurement = $stmt->fetch();
    } catch (Exception $e) {}

    $health = [];
    try {
        $stmt = $db->prepare("SELECT * FROM student_health WHERE student_id = ? AND is_active = 1");
        $stmt->execute([$studentId]);
        $health = $stmt->fetchAll();
    } catch (Exception $e) {}

    $stmt = $db->prepare("SELECT ft.id, ft.name as test_name, ft.unit, ft.type, ft.max_score, sf.value, sf.score, sf.test_date
        FROM fitness_tests ft LEFT JOIN student_fitness sf ON sf.test_id = ft.id AND sf.student_id = ?
        WHERE ft.active = 1 ORDER BY ft.id");
    $stmt->execute([$studentId]);
    $fitnessResults = $stmt->fetchAll();

    $totalScore = 0; $totalMax = 0;
    foreach ($fitnessResults as $r) {
        if ($r['score'] !== null) { $totalScore += $r['score']; $totalMax += $r['max_score']; }
    }

    $stmt = $db->prepare("SELECT SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_count FROM attendance WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $att = $stmt->fetch();

    jsonSuccess([
        'student' => $student, 'measurement' => $measurement, 'health' => $health,
        'fitness' => $fitnessResults, 'totalScore' => (int)$totalScore, 'totalMax' => (int)$totalMax,
        'percentage' => $totalMax > 0 ? round($totalScore / $totalMax * 100) : 0,
        'attendance' => [
            'present' => (int)($att['present_count'] ?? 0),
            'absent' => (int)($att['absent_count'] ?? 0),
            'late' => (int)($att['late_count'] ?? 0)
        ]
    ]);
}

function getClassReport() {
    requireRole(['admin', 'teacher', 'supervisor', 'viewer']);
    $classId = getParam('class_id');
    if (!$classId) jsonError('يجب تحديد الفصل');
    $db = getDB();

    $stmt = $db->prepare("SELECT c.*, g.name as grade_name, CONCAT(g.name, ' - ', c.name) as full_name
        FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    if (!$class) jsonError('الفصل غير موجود');

    // Try with measurements & health, fallback without
    $queries = [
        "SELECT s.id, s.name, s.student_number,
            COALESCE(SUM(sf.score), 0) as total_score,
            (SELECT SUM(ft.max_score) FROM fitness_tests ft WHERE ft.active = 1
             AND EXISTS(SELECT 1 FROM student_fitness sf2 WHERE sf2.student_id = s.id AND sf2.test_id = ft.id)) as total_max,
            ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'present') as present_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'absent') as absent_count,
            (SELECT bmi FROM student_measurements sm WHERE sm.student_id = s.id ORDER BY measurement_date DESC LIMIT 1) as latest_bmi,
            (SELECT bmi_category FROM student_measurements sm WHERE sm.student_id = s.id ORDER BY measurement_date DESC LIMIT 1) as bmi_category,
            (SELECT COUNT(*) FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_alerts
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE s.class_id = ? AND s.active = 1 GROUP BY s.id ORDER BY avg_score DESC",

        "SELECT s.id, s.name, s.student_number,
            COALESCE(SUM(sf.score), 0) as total_score,
            (SELECT SUM(ft.max_score) FROM fitness_tests ft WHERE ft.active = 1
             AND EXISTS(SELECT 1 FROM student_fitness sf2 WHERE sf2.student_id = s.id AND sf2.test_id = ft.id)) as total_max,
            ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'present') as present_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'absent') as absent_count,
            NULL as latest_bmi, NULL as bmi_category, 0 as health_alerts
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE s.class_id = ? AND s.active = 1 GROUP BY s.id ORDER BY avg_score DESC"
    ];

    $students = [];
    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$classId]);
            $students = $stmt->fetchAll();
            break;
        } catch (PDOException $e) {
            continue;
        }
    }

    foreach ($students as &$s) {
        $s['percentage'] = $s['total_max'] > 0 ? round($s['total_score'] / $s['total_max'] * 100) : 0;
    }
    $avgPct = count($students) > 0 ? round(array_sum(array_column($students, 'percentage')) / count($students), 1) : 0;

    jsonSuccess(['class' => $class, 'students' => $students, 'classAverage' => $avgPct, 'totalStudents' => count($students)]);
}

function getCompareReport() {
    requireRole(['admin', 'teacher', 'supervisor', 'viewer']);
    $db = getDB();
    $classes = $db->query("
        SELECT c.id, CONCAT(g.name, ' - ', c.name) as class_name,
               COUNT(DISTINCT s.id) as students_count,
               ROUND(COALESCE(AVG(CASE WHEN sf.score IS NOT NULL THEN sf.score END), 0), 2) as avg_score
        FROM classes c JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE c.active = 1 GROUP BY c.id HAVING students_count > 0 ORDER BY avg_score DESC
    ")->fetchAll();

    $maxAvg = !empty($classes) ? max(array_column($classes, 'avg_score')) : 1;
    foreach ($classes as &$c) {
        $c['percentage'] = $maxAvg > 0 ? round(($c['avg_score'] / 10) * 100, 1) : 0;
        $c['bar_width'] = $maxAvg > 0 ? round(($c['avg_score'] / $maxAvg) * 100) : 0;
    }
    jsonSuccess($classes);
}
