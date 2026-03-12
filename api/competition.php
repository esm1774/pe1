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
    $sid = schoolId();
    $schoolFilter = $sid ? "AND c.school_id = $sid" : "";
    $studentSchoolFilter = $sid ? "AND s.school_id = $sid" : "";

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
        WHERE c.active = 1 $schoolFilter GROUP BY c.id ORDER BY avg_score DESC
    ")->fetchAll();

    // 2. School-wide Top 10 Students
    $topStudents = $db->query("
        SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
               ROUND(AVG(sf.score), 2) as avg_score, SUM(sf.score) as total_score, COUNT(sf.id) as test_count
        FROM students s JOIN student_fitness sf ON sf.student_id = s.id
        JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 $studentSchoolFilter GROUP BY s.id ORDER BY avg_score DESC LIMIT 10
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
            // Fix #8: Calculate school rank with a single efficient SQL query instead of PHP loop
            $rankStmt = $db->prepare("
                SELECT COUNT(*) + 1 as school_rank,
                       (SELECT COUNT(*) FROM students WHERE active = 1" . ($sid ? " AND school_id = ?" : "") . ") as total_in_school
                FROM (
                    SELECT s.id, COALESCE(AVG(sf.score), 0) as avg_score
                    FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                    WHERE s.active = 1" . ($sid ? " AND s.school_id = ?" : "") . "
                    GROUP BY s.id
                ) ranked
                WHERE avg_score > (
                    SELECT COALESCE(AVG(sf2.score), 0)
                    FROM student_fitness sf2 WHERE sf2.student_id = ?
                )
            ");
            $rankParams = $sid ? [$sid, $sid, $targetStudentId] : [$targetStudentId];
            $rankStmt->execute($rankParams);
            $rankData = $rankStmt->fetch();
            $schoolRank = (int)($rankData['school_rank'] ?? 0);
            $totalInSchool = (int)($rankData['total_in_school'] ?? 0);

            // Fix #8: Calculate class rank efficiently
            $classRankStmt = $db->prepare("
                SELECT COUNT(*) + 1 as class_rank,
                       (SELECT COUNT(*) FROM students WHERE class_id = ? AND active = 1) as total_in_class
                FROM (
                    SELECT s.id, COALESCE(AVG(sf.score), 0) as avg_score
                    FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                    WHERE s.class_id = ? AND s.active = 1
                    GROUP BY s.id
                ) ranked
                WHERE avg_score > (
                    SELECT COALESCE(AVG(sf2.score), 0)
                    FROM student_fitness sf2 WHERE sf2.student_id = ?
                )
            ");
            $classRankStmt->execute([$classId, $classId, $targetStudentId]);
            $classRankData = $classRankStmt->fetch();
            $classRank = (int)($classRankData['class_rank'] ?? 0);
            $totalInClass = (int)($classRankData['total_in_class'] ?? 0);

            // Top 3 in student's class
            $stmt = $db->prepare("
                SELECT s.id, s.name, ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
                FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id
                WHERE s.class_id = ? AND s.active = 1 GROUP BY s.id ORDER BY avg_score DESC LIMIT 3
            ");
            $stmt->execute([$classId]);
            $classTop3 = $stmt->fetchAll();

            $studentData = [
                'studentId'    => $targetStudentId,
                'schoolRank'   => $schoolRank,
                'classRank'    => $classRank,
                'classId'      => $classId,
                'classTop3'    => $classTop3,
                'totalInSchool'=> $totalInSchool,
                'totalInClass' => $totalInClass
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
    $sid = schoolId();

    $stmt = $db->prepare("SELECT s.*, CONCAT(g.name, ' - ', c.name) as full_class_name, g.name as grade_name, c.name as class_name,
        TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
        FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE s.id = ?"
        . ($sid ? " AND s.school_id = ?" : ""));
    $stmtParams = [$studentId];
    if ($sid) $stmtParams[] = $sid;
    $stmt->execute($stmtParams);
    $student = $stmt->fetch();
    if (!$student) jsonError('الطالب غير موجود أو ليس ضمن صلاحياتك');

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

    // --- NEW: WEIGHTED GRADING CALCULATION ---
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-t'));

    // 1. Get School Weights
    $wStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $wStmt->execute([$sid]);
    $weights = $wStmt->fetch(PDO::FETCH_ASSOC);
    if (!$weights) {
        $weights = ['attendance_pct' => 20, 'uniform_pct' => 20, 'behavior_skills_pct' => 20, 'participation_pct' => 20, 'fitness_pct' => 20];
    } else {
        $weights['attendance_pct'] = (int)$weights['attendance_pct'];
        $weights['uniform_pct'] = (int)$weights['uniform_pct'];
        $weights['behavior_skills_pct'] = (int)$weights['behavior_skills_pct'];
        $weights['participation_pct'] = (int)($weights['participation_pct'] ?? 0);
        $weights['fitness_pct'] = (int)$weights['fitness_pct'];
        $weights['quiz_pct'] = (int)($weights['quiz_pct'] ?? 0);
        $weights['project_pct'] = (int)($weights['project_pct'] ?? 0);
        $weights['final_exam_pct'] = (int)($weights['final_exam_pct'] ?? 0);
    }

    // 2. Attendance, Uniform, Behavior, Skills & Participation Data
    $aStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'late' OR status = 'excused' THEN 0.5 ELSE 0 END) as late_days,
            SUM(CASE WHEN uniform_status = 'full' THEN 3 WHEN uniform_status = 'partial' THEN 2 WHEN uniform_status = 'wrong' THEN 1 ELSE 0 END) as uniform_score,
            SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as max_uniform_score,
            SUM(COALESCE(behavior_stars, 0)) as behavior_score,
            SUM(CASE WHEN behavior_stars > 0 THEN 3 ELSE 0 END) as max_behavior_score,
            SUM(COALESCE(skills_stars, 0)) as skills_score,
            SUM(CASE WHEN skills_stars > 0 THEN 3 ELSE 0 END) as max_skills_score,
            SUM(COALESCE(participation_stars, 0)) as participation_score,
            SUM(CASE WHEN participation_stars > 0 THEN 3 ELSE 0 END) as max_participation_score
        FROM attendance 
        WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
    ");
    $aStmt->execute([$studentId, $startDate, $endDate]);
    $attData = $aStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalDays = (int)$attData['total_days'];
    $attPercent = $totalDays > 0 ? (($attData['present_days'] + $attData['late_days']) / $totalDays) * 100 : 100;
    $uniPercent = (int)$attData['max_uniform_score'] > 0 ? ((float)$attData['uniform_score'] / (float)$attData['max_uniform_score']) * 100 : 100;
    
    $earnedStars = (float)$attData['behavior_score'] + (float)$attData['skills_score'];
    $maxStars = (float)$attData['max_behavior_score'] + (float)$attData['max_skills_score'];
    $behSkillPercent = $maxStars > 0 ? ($earnedStars / $maxStars) * 100 : 100;

    $partPercent = (int)$attData['max_participation_score'] > 0 ? ((float)$attData['participation_score'] / (float)$attData['max_participation_score']) * 100 : 100;

    // 3. Fitness Results (Filtered by Date)
    // ... (fitness results logic)
    $fitnessTestsSql = "SELECT ft.id, ft.name as test_name, ft.unit, ft.type, ft.max_score, sf.value, sf.score, sf.test_date
        FROM fitness_tests ft LEFT JOIN student_fitness sf ON sf.test_id = ft.id AND sf.student_id = ? AND sf.test_date BETWEEN ? AND ?
        WHERE ft.active = 1";
    $fitnessParams = [$studentId, $startDate, $endDate];
    if ($sid) { $fitnessTestsSql .= " AND ft.school_id = ?"; $fitnessParams[] = $sid; }
    $fitnessTestsSql .= " ORDER BY ft.id";
    $stmt = $db->prepare($fitnessTestsSql);
    $stmt->execute($fitnessParams);
    $fitnessResults = $stmt->fetchAll();

    $rangeTotalScore = 0; $rangeTotalMax = 0;
    foreach ($fitnessResults as $r) {
        if ($r['score'] !== null) { $rangeTotalScore += $r['score']; $rangeTotalMax += $r['max_score']; }
    }

    // 4. Fitness Score (Weighted)
    $fitPercent = $rangeTotalMax > 0 ? ($rangeTotalScore / $rangeTotalMax) * 100 : 100;

    // 5. Assessments Data
    $asStmt = $db->prepare("SELECT type, score FROM student_assessments WHERE student_id = ?");
    $asStmt->execute([$studentId]);
    $assRecords = $asStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $qScore = (float)($assRecords['quiz'] ?? 0);
    $pScore = (float)($assRecords['project'] ?? 0);
    $fnScore = (float)($assRecords['final_exam'] ?? 0);
    
    // Normalize based on max scores from settings (default 10)
    $qMax = (int)($weights['quiz_max'] ?? 10) ?: 10;
    $pMax = (int)($weights['project_max'] ?? 10) ?: 10;
    $fnMax = (int)($weights['final_exam_max'] ?? 10) ?: 10;

    $qPercent = ($qScore / $qMax) * 100;
    $prjPercent = ($pScore / $pMax) * 100;
    $fnlPercent = ($fnScore / $fnMax) * 100;

    // 6. Final Weighted Grade
    $finalScore = 
        ($attPercent * ($weights['attendance_pct'] / 100)) +
        ($uniPercent * ($weights['uniform_pct'] / 100)) +
        ($behSkillPercent * ($weights['behavior_skills_pct'] / 100)) +
        ($partPercent * ($weights['participation_pct'] / 100)) +
        ($fitPercent * ($weights['fitness_pct'] / 100)) +
        ($qPercent * (($weights['quiz_pct'] ?? 0) / 100)) +
        ($prjPercent * (($weights['project_pct'] ?? 0) / 100)) +
        ($fnlPercent * (($weights['final_exam_pct'] ?? 0) / 100));

    $letter = '';
    if ($finalScore >= 90) $letter = 'ممتاز';
    else if ($finalScore >= 80) $letter = 'جيد جداً';
    else if ($finalScore >= 70) $letter = 'جيد';
    else if ($finalScore >= 60) $letter = 'مقبول';
    else $letter = 'ضعيف';

    $gradingSummary = [
        'weights' => $weights,
        'attendance_pct' => round($attPercent, 1),
        'uniform_pct' => round($uniPercent, 1),
        'behavior_skills_pct' => round($behSkillPercent, 1),
        'participation_pct' => round($partPercent, 1),
        'fitness_pct' => round($fitPercent, 1),
        'quiz_score' => $qScore,
        'quiz_max' => $qMax,
        'project_score' => $pScore,
        'project_max' => $pMax,
        'final_exam_score' => $fnScore,
        'final_exam_max' => $fnMax,
        'final_grade' => round($finalScore, 1),
        'letter' => $letter,
        'total_days' => $totalDays,
        'start_date' => $startDate,
        'end_date' => $endDate
    ];

    $stmt = $db->prepare("SELECT SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_count FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
    $stmt->execute([$studentId, $startDate, $endDate]);
    $att = $stmt->fetch();

    jsonSuccess([
        'student' => $student, 'measurement' => $measurement, 'health' => $health,
        'fitness' => $fitnessResults, 'totalScore' => (int)$rangeTotalScore, 'totalMax' => (int)$rangeTotalMax,
        'percentage' => round($finalScore), 
        'grading_summary' => $gradingSummary,
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
    $sid = schoolId();

    // Fix: Ensure the class belongs to the current school before fetching data
    $classSql = "SELECT c.*, g.name as grade_name, CONCAT(g.name, ' - ', c.name) as full_name
        FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?";
    $classParams = [$classId];
    if ($sid) { $classSql .= " AND c.school_id = ?"; $classParams[] = $sid; }
    $stmt = $db->prepare($classSql);
    $stmt->execute($classParams);
    $class = $stmt->fetch();
    if (!$class) jsonError('الفصل غير موجود أو ليس ضمن صلاحياتك');

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

    // --- NEW: WEIGHTED CLASS CALCULATION ---
    $weightsStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $weightsStmt->execute([$sid]);
    $weights = $weightsStmt->fetch(PDO::FETCH_ASSOC) ?: ['attendance_pct' => 20, 'uniform_pct' => 20, 'behavior_skills_pct' => 20, 'fitness_pct' => 40];
    
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');

    foreach ($students as &$s) {
        $stId = $s['id'];

        // Fitness Score
        $fitStmt = $db->prepare("SELECT SUM(sf.score) as earned, SUM(ft.max_score) as max FROM student_fitness sf JOIN fitness_tests ft ON sf.test_id = ft.id WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?");
        $fitStmt->execute([$stId, $startDate, $endDate]);
        $f = $fitStmt->fetch();
        $fitPct = ($f['max'] > 0) ? ($f['earned'] / $f['max']) * 100 : 100;

        // Assessments
        $asStmt = $db->prepare("SELECT type, score FROM student_assessments WHERE student_id = ?");
        $asStmt->execute([$stId]);
        $assRecords = $asStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $qScore = (float)($assRecords['quiz'] ?? 0);
        $pScore = (float)($assRecords['project'] ?? 0);
        $fnScore = (float)($assRecords['final_exam'] ?? 0);
        
        $qMax = (int)($weights['quiz_max'] ?? 10) ?: 10;
        $pMax = (int)($weights['project_max'] ?? 10) ?: 10;
        $fnMax = (int)($weights['final_exam_max'] ?? 10) ?: 10;

        $qPct = ($qScore / $qMax) * 100;
        $prjPct = ($pScore / $pMax) * 100;
        $fnlPct = ($fnScore / $fnMax) * 100;

        // Attendance & Others
        $aStmt = $db->prepare("SELECT COUNT(*) as days, SUM(CASE WHEN status='present' THEN 1 WHEN status IN ('late','excused') THEN 0.5 ELSE 0 END) as att_earned, SUM(CASE WHEN uniform_status='full' THEN 3 WHEN uniform_status='partial' THEN 2 WHEN uniform_status='wrong' THEN 1 ELSE 0 END) as uni_earned, SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as uni_max, SUM(COALESCE(behavior_stars,0)+COALESCE(skills_stars,0)) as stars_earned, SUM((CASE WHEN behavior_stars>0 THEN 3 ELSE 0 END)+(CASE WHEN skills_stars>0 THEN 3 ELSE 0 END)) as stars_max, SUM(COALESCE(participation_stars, 0)) as part_earned, SUM(CASE WHEN participation_stars > 0 THEN 3 ELSE 0 END) as part_max FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
        $aStmt->execute([$stId, $startDate, $endDate]);
        $a = $aStmt->fetch();

        $attPct = ($a['days'] > 0) ? ($a['att_earned'] / $a['days']) * 100 : 100;
        $uniPct = ($a['uni_max'] > 0) ? ($a['uni_earned'] / $a['uni_max']) * 100 : 100;
        $starPct = ($a['stars_max'] > 0) ? ($a['stars_earned'] / $a['stars_max']) * 100 : 100;
        $partPct = ($a['part_max'] > 0) ? ($a['part_earned'] / $a['part_max']) * 100 : 100;

        $final = 
            ($attPct * (($weights['attendance_pct'] ?? 0) / 100)) + 
            ($uniPct * (($weights['uniform_pct'] ?? 0) / 100)) + 
            ($starPct * (($weights['behavior_skills_pct'] ?? 0) / 100)) + 
            ($partPct * (($weights['participation_pct'] ?? 0) / 100)) +
            ($fitPct * (($weights['fitness_pct'] ?? 0) / 100)) +
            ($qPct * (($weights['quiz_pct'] ?? 0) / 100)) +
            ($prjPct * (($weights['project_pct'] ?? 0) / 100)) +
            ($fnlPct * (($weights['final_exam_pct'] ?? 0) / 100));
        
        $s['percentage'] = round($final);
        
        if ($final >= 90) $s['letter'] = 'ممتاز';
        else if ($final >= 80) $s['letter'] = 'جيد جداً';
        else if ($final >= 70) $s['letter'] = 'جيد';
        else if ($final >= 60) $s['letter'] = 'مقبول';
        else $s['letter'] = 'ضعيف';
    }

    $avgPct = count($students) > 0 ? round(array_sum(array_column($students, 'percentage')) / count($students), 1) : 0;

    jsonSuccess(['class' => $class, 'students' => $students, 'classAverage' => $avgPct, 'totalStudents' => count($students), 'weights' => $weights]);
}

function getCompareReport() {
    requireRole(['admin', 'teacher', 'supervisor', 'viewer']);
    $db = getDB();
    $sid = schoolId();
    $schoolFilter = $sid ? "AND c.school_id = $sid" : "";
    $classes = $db->query("
        SELECT c.id, CONCAT(g.name, ' - ', c.name) as class_name,
               COUNT(DISTINCT s.id) as students_count,
               ROUND(COALESCE(AVG(CASE WHEN sf.score IS NOT NULL THEN sf.score END), 0), 2) as avg_score
        FROM classes c JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE c.active = 1 $schoolFilter GROUP BY c.id HAVING students_count > 0 ORDER BY avg_score DESC
    ")->fetchAll();

    // --- NEW: WEIGHTED COMPARISON CALCULATION ---
    $weightsStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $weightsStmt->execute([$sid]);
    $weights = $weightsStmt->fetch(PDO::FETCH_ASSOC) ?: ['attendance_pct' => 20, 'uniform_pct' => 20, 'behavior_skills_pct' => 20, 'fitness_pct' => 40];

    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');

    foreach ($classes as &$c) {
        $cid = $c['id'];
        
        // We calculate the average of all students in this class
        $stStmt = $db->prepare("SELECT id FROM students WHERE class_id = ? AND active = 1");
        $stStmt->execute([$cid]);
        $stIds = $stStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($stIds)) {
            $c['percentage'] = 0;
            $c['bar_width'] = 0;
            continue;
        }

        $classTotal = 0;
        foreach ($stIds as $stId) {
            // Fitness
            $fit = $db->prepare("SELECT SUM(sf.score) as earned, SUM(ft.max_score) as max FROM student_fitness sf JOIN fitness_tests ft ON sf.test_id = ft.id WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?");
            $fit->execute([$stId, $startDate, $endDate]);
            $fr = $fit->fetch();
            $fitP = ($fr['max'] > 0) ? ($fr['earned'] / $fr['max']) * 100 : 100;

            // Attendance & Others
            $att = $db->prepare("SELECT COUNT(*) as days, SUM(CASE WHEN status='present' THEN 1 WHEN status IN ('late','excused') THEN 0.5 ELSE 0 END) as att_e, SUM(CASE WHEN uniform_status='full' THEN 3 WHEN uniform_status='partial' THEN 2 WHEN uniform_status='wrong' THEN 1 ELSE 0 END) as uni_e, SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as uni_m, SUM(COALESCE(behavior_stars,0)+COALESCE(skills_stars,0)) as s_e, SUM((CASE WHEN behavior_stars>0 THEN 3 ELSE 0 END)+(CASE WHEN skills_stars>0 THEN 3 ELSE 0 END)) as s_m FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
            $att->execute([$stId, $startDate, $endDate]);
            $ar = $att->fetch();

            $attP = ($ar['days'] > 0) ? ($ar['att_e'] / $ar['days']) * 100 : 100;
            $uniP = ($ar['uni_m'] > 0) ? ($ar['uni_e'] / $ar['uni_m']) * 100 : 100;
            $starP = ($ar['s_m'] > 0) ? ($ar['s_e'] / $ar['s_m']) * 100 : 100;

            $classTotal += ($attP * ($weights['attendance_pct']/100)) + ($uniP * ($weights['uniform_pct']/100)) + ($starP * ($weights['behavior_skills_pct']/100)) + ($fitP * ($weights['fitness_pct']/100));
        }
        
        $c['percentage'] = round($classTotal / count($stIds), 1);
        $c['bar_width'] = $c['percentage']; // Bar width is 1:1 with percentage now (max 100)
    }

    // Sort by percentage descending
    usort($classes, function($a, $b) {
        return $b['percentage'] <=> $a['percentage'];
    });

    jsonSuccess($classes);
}
