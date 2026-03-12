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

        // --- FITNESS SCORE ---
        $fStmt = $db->prepare("
            SELECT 
                SUM(sf.score) as total_earned,
                SUM(ft.max_score) as total_max
            FROM student_fitness sf
            JOIN fitness_tests ft ON sf.test_id = ft.id
            WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?
        ");
        $fStmt->execute([$stId, $startDate, $endDate]);
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
        // Scores are out of 10, so convert to percentage
        $qPercent = ($qScore / 10) * 100;
        $prjPercent = ($pScore / 10) * 100;
        $fnlPercent = ($fnScore / 10) * 100;

        // Apply Weights
        $finalScore = 
            ($attPercent * ($weights['attendance_pct'] / 100)) +
            ($uniPercent * ($weights['uniform_pct'] / 100)) +
            ($behSkillPercent * ($weights['behavior_skills_pct'] / 100)) +
            ($partPercent * ($weights['participation_pct'] / 100)) +
            ($fitPercent * ($weights['fitness_pct'] / 100)) +
            ($qPercent * (($weights['quiz_pct'] ?? 0) / 100)) +
            ($prjPercent * (($weights['project_pct'] ?? 0) / 100)) +
            ($fnlPercent * (($weights['final_exam_pct'] ?? 0) / 100));

        $st['total_days'] = $totalDays;
        $st['attendance_pct'] = round($attPercent, 1);
        $st['uniform_pct'] = round($uniPercent, 1);
        $st['behavior_skills_pct'] = round($behSkillPercent, 1);
        $st['fitness_pct'] = round($fitPercent, 1);
        $st['quiz_score'] = $qScore;
        $st['project_score'] = $pScore;
        $st['final_exam_score'] = $fnScore;
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
