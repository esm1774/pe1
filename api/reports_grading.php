<?php
/**
 * PE Smart School System - Grading Reports API
 * Engine to calculate final weighted grades for students.
 */

function getGradingReport() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();
    $classId = (int)getParam('class_id');
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-t'));

    if (!$classId) jsonError('يجب تحديد الفصل');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    // 1. Get School Weights
    $wStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $wStmt->execute([$sid]);
    $weights = $wStmt->fetch(PDO::FETCH_ASSOC);
    if (!$weights) {
        $weights = [
            'attendance_pct' => 20,
            'uniform_pct' => 20,
            'behavior_skills_pct' => 20,
            'participation_pct' => 10,
            'fitness_pct' => 10,
            'quiz_pct' => 10,
            'project_pct' => 5,
            'final_exam_pct' => 5
        ];
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

    // 2. Fetch Students
    $sStmt = $db->prepare("SELECT id, name, student_number FROM students WHERE class_id = ? AND active = 1 ORDER BY name ASC");
    $sStmt->execute([$classId]);
    $students = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Loop over students and calculate their scores
    foreach ($students as &$st) {
        $stId = $st['id'];

        // --- ATTENDANCE, UNIFORM, BEHAVIOR, SKILLS & PARTICIPATION ---
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
        $aStmt->execute([$stId, $startDate, $endDate]);
        $attData = $aStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalDays = (int)$attData['total_days'];
        
        // Default to 100% if no classes recorded
        $attPercent = 100;
        if ($totalDays > 0) {
            $attPercent = (($attData['present_days'] + $attData['late_days']) / $totalDays) * 100;
        }

        $uniPercent = 100;
        if ((int)$attData['max_uniform_score'] > 0) {
            $uniPercent = ((float)$attData['uniform_score'] / (float)$attData['max_uniform_score']) * 100;
        }

        $behSkillPercent = 100;
        $earnedStars = (float)$attData['behavior_score'] + (float)$attData['skills_score'];
        $maxStars = (float)$attData['max_behavior_score'] + (float)$attData['max_skills_score'];
        if ($maxStars > 0) {
            $behSkillPercent = ($earnedStars / $maxStars) * 100;
        }

        $partPercent = 100;
        if ((int)$attData['max_participation_score'] > 0) {
            $partPercent = ((float)$attData['participation_score'] / (float)$attData['max_participation_score']) * 100;
        }

        // --- FITNESS SCORE (Best result per test) ---
        $fStmt = $db->prepare("
            SELECT 
                COALESCE(SUM(best_score), 0) as total_earned,
                (SELECT SUM(max_score) FROM fitness_tests ft2 WHERE ft2.active = 1 AND ft2.school_id = ?) as total_max
            FROM (
                SELECT sf.test_id,
                       CASE
                           WHEN ft.type = 'lower_better' THEN
                               (SELECT sf2.score FROM student_fitness sf2 WHERE sf2.student_id = sf.student_id AND sf2.test_id = sf.test_id ORDER BY sf2.value ASC LIMIT 1)
                           ELSE MAX(sf.score)
                       END as best_score
                FROM student_fitness sf
                JOIN fitness_tests ft ON sf.test_id = ft.id
                WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?
                GROUP BY sf.test_id, ft.type
            ) as best_scores
        ");
        $fStmt->execute([$sid, $stId, $startDate, $endDate]);
        $fitData = $fStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalEarned = (float)$fitData['total_earned'];
        $totalMax = (float)$fitData['total_max'];
        
        $fitPercent = 100; // Assume 100 if no tests are recorded yet
        if ($totalMax > 0) {
            $fitPercent = ($totalEarned / $totalMax) * 100;
        }

        // --- NEW ASSESSMENTS (Quiz, Project, Final Exam) ---
        $asStmt = $db->prepare("SELECT type, score FROM student_assessments WHERE student_id = ?");
        $asStmt->execute([$stId]);
        $assRecords = $asStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $qScore = (float)($assRecords['quiz'] ?? 0);
        $pScore = (float)($assRecords['project'] ?? 0);
        $fnScore = (float)($assRecords['final_exam'] ?? 0);
        // Scores are out of their respective max scores defined in weights, so convert to percentage
        $qMax = (float)($weights['quiz_max'] ?? 10);
        $pMax = (float)($weights['project_max'] ?? 10);
        $fMax = (float)($weights['final_exam_max'] ?? 10);

        $qPercent = $qMax > 0 ? ($qScore / $qMax) * 100 : 0;
        $prjPercent = $pMax > 0 ? ($pScore / $pMax) * 100 : 0;
        $fnlPercent = $fMax > 0 ? ($fnScore / $fMax) * 100 : 0;

        // Apply Weights
        $wAtt = $attPercent * ($weights['attendance_pct'] / 100);
        $wUni = $uniPercent * ($weights['uniform_pct'] / 100);
        $wBeh = $behSkillPercent * ($weights['behavior_skills_pct'] / 100);
        $wPart = $partPercent * ($weights['participation_pct'] / 100);
        $wFit = $fitPercent * ($weights['fitness_pct'] / 100);
        $wQuiz = $qPercent * (($weights['quiz_pct'] ?? 0) / 100);
        $wPrj = $prjPercent * (($weights['project_pct'] ?? 0) / 100);
        $wFnl = $fnlPercent * (($weights['final_exam_pct'] ?? 0) / 100);

        $finalScore = $wAtt + $wUni + $wBeh + $wPart + $wFit + $wQuiz + $wPrj + $wFnl;

        $st['total_days'] = $totalDays;
        $st['attendance_score'] = round($wAtt, 1);
        $st['uniform_score'] = round($wUni, 1);
        $st['behavior_skills_score'] = round($wBeh, 1);
        $st['participation_score'] = round($wPart, 1);
        $st['fitness_score'] = round($wFit, 1);
        $st['quiz_score'] = round($wQuiz, 1);
        $st['project_score'] = round($wPrj, 1);
        $st['final_exam_score'] = round($wFnl, 1);
        
        $st['attendance_pct'] = round($attPercent, 1);
        $st['uniform_pct'] = round($uniPercent, 1);
        $st['behavior_skills_pct'] = round($behSkillPercent, 1);
        $st['fitness_pct'] = round($fitPercent, 1);
        
        $st['final_grade'] = round($finalScore, 1);
        
        // Calculate Grade Letter (Arabic Evaluative terms)
        if ($finalScore >= 90) $st['letter'] = 'ممتاز';
        else if ($finalScore >= 80) $st['letter'] = 'جيد جداً';
        else if ($finalScore >= 70) $st['letter'] = 'جيد';
        else if ($finalScore >= 60) $st['letter'] = 'مقبول';
        else $st['letter'] = 'ضعيف';
    }

    // Sort by final grade descending
    usort($students, function($a, $b) {
        return $b['final_grade'] <=> $a['final_grade'];
    });

    jsonSuccess([
        'weights' => $weights,
        'students' => $students
    ]);
}

/**
 * NEW: Class Monitoring Report (كشف متابعة فصل)
 * Shows attendance, uniform, participation, fitness, and behavior per lesson date.
 */
function getClassMonitoringReport() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();
    $classId = (int)getParam('class_id');
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-t'));

    if (!$classId) jsonError('يجب تحديد الفصل');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    // 1. Get Class Info
    $cStmt = $db->prepare("SELECT c.*, CONCAT(g.name, ' - ', c.name) as full_name FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.id = ?");
    $cStmt->execute([$classId]);
    $classInfo = $cStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get Students
    $sStmt = $db->prepare("SELECT id, name, student_number FROM students WHERE class_id = ? AND active = 1 ORDER BY name ASC");
    $sStmt->execute([$classId]);
    $students = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get lesson dates during this period (dates where at least one attendance record exists for this class)
    $dStmt = $db->prepare("
        SELECT DISTINCT attendance_date 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.class_id = ? AND a.attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date ASC
    ");
    $dStmt->execute([$classId, $startDate, $endDate]);
    $lessonDates = $dStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($lessonDates)) {
        jsonSuccess([
            'class' => $classInfo,
            'students' => $students,
            'dates' => [],
            'matrix' => []
        ]);
        return;
    }

    // 4. Get all attendance & fitness data for these students/dates
    // We'll fetch all records and organize them in a matrix in PHP for easier UI rendering
    $stIds = array_column($students, 'id');
    if (empty($stIds)) jsonError('لا يوجد طلاب في هذا الفصل');

    $placeholders = implode(',', array_fill(0, count($stIds), '?'));
    
    // Attendance data
    $aParams = array_merge([$startDate, $endDate], $stIds);
    $aStmt = $db->prepare("
        SELECT student_id, attendance_date, status, uniform_status, participation_stars, behavior_stars, skills_stars
        FROM attendance 
        WHERE attendance_date BETWEEN ? AND ? AND student_id IN ($placeholders)
    ");
    $aStmt->execute($aParams);
    $attRecords = $aStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fitness data (we'll fetch test results for these dates)
    $fStmt = $db->prepare("
        SELECT student_id, test_date, ROUND(AVG(score), 1) as avg_score
        FROM student_fitness 
        WHERE test_date BETWEEN ? AND ? AND student_id IN ($placeholders)
        GROUP BY student_id, test_date
    ");
    $fStmt->execute($aParams);
    $fitRecords = $fStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize into matrix: [student_id][date] = { att, uni, part, fit, beh }
    $matrix = [];
    foreach ($attRecords as $r) {
        $sid_st = $r['student_id'];
        $dt = $r['attendance_date'];
        if (!isset($matrix[$sid_st])) $matrix[$sid_st] = [];
        
        $matrix[$sid_st][$dt] = [
            'status' => $r['status'],
            'uniform' => $r['uniform_status'],
            'participation' => $r['participation_stars'],
            'behavior' => $r['behavior_stars'],
            'skills' => $r['skills_stars']
        ];
    }

    foreach ($fitRecords as $f) {
        $sid_st = $f['student_id'];
        $dt = $f['test_date'];
        if (!isset($matrix[$sid_st])) $matrix[$sid_st] = [];
        if (!isset($matrix[$sid_st][$dt])) $matrix[$sid_st][$dt] = [];
        $matrix[$sid_st][$dt]['fitness'] = $f['avg_score'];
    }

    jsonSuccess([
        'class' => $classInfo,
        'students' => $students,
        'dates' => $lessonDates,
        'matrix' => $matrix
    ]);
}
