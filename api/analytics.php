<?php
/**
 * PE Smart School System - Analytics API
 * ========================================
 * يوفر بيانات لوحة التحليلات المتقدمة (الرسومات البيانية)
 */

function getAnalyticsDashboard() {
    requireLogin();
    Subscription::requireFeature('reports');
    // Allow only managerial roles
    requireRole(['admin', 'supervisor']);

    $db = getDB();
    $sid = schoolId();
    
    // Get School Weights
    $weightsStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $weightsStmt->execute([$sid]);
    $weights = $weightsStmt->fetch(PDO::FETCH_ASSOC) ?: ['attendance_pct' => 20, 'uniform_pct' => 20, 'behavior_skills_pct' => 20, 'fitness_pct' => 40];

    // Helper to calculate weighted score for a set of data or student
    // For large scale analytics, we might need a more optimized approach, 
    // but for now, we'll follow the established logic for consistency.

    // 1. Performance / Attendance Timeline (Last 6 Months) - Now Weighted!
    $timeline = ['labels' => [], 'data' => []];
    for ($i = 5; $i >= 0; $i--) {
        $monthStr = date('Y-m', strtotime("-$i months"));
        $start = "$monthStr-01";
        $end = date('Y-m-t', strtotime($start));
        $timeline['labels'][] = $monthStr;

        // Calculate Weighted Average for the whole school for this month
        $stStmt = $db->prepare("SELECT id FROM students WHERE active = 1 " . ($sid ? "AND school_id = ?" : ""));
        $stStmt->execute($sid ? [$sid] : []);
        $stIds = $stStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($stIds)) {
            $timeline['data'][] = 0;
            continue;
        }

        $totalScoreSum = 0;
        foreach ($stIds as $id) {
            // This is heavy, but ensures consistency with the grading system rules
            // Ideally we'd have a summary table, but let's stick to the core logic
            $fit = $db->prepare("SELECT SUM(sf.score) as e, SUM(ft.max_score) as m FROM student_fitness sf JOIN fitness_tests ft ON sf.test_id = ft.id WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?");
            $fit->execute([$id, $start, $end]);
            $fr = $fit->fetch();
            $fitP = ($fr['m'] > 0) ? ($fr['e'] / $fr['m']) * 100 : 100;

            $att = $db->prepare("SELECT COUNT(*) as d, SUM(CASE WHEN status='present' THEN 1 WHEN status IN ('late','excused') THEN 0.5 ELSE 0 END) as att_e, SUM(CASE WHEN uniform_status='full' THEN 3 WHEN uniform_status='partial' THEN 2 WHEN uniform_status='wrong' THEN 1 ELSE 0 END) as uni_e, SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as uni_m, SUM(COALESCE(behavior_stars,0)+COALESCE(skills_stars,0)) as s_e, SUM((CASE WHEN behavior_stars>0 THEN 3 ELSE 0 END)+(CASE WHEN skills_stars>0 THEN 3 ELSE 0 END)) as s_m FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
            $att->execute([$id, $start, $end]);
            $ar = $att->fetch();

            $attP = ($ar['d'] > 0) ? ($ar['att_e'] / $ar['d']) * 100 : 100;
            $uniP = ($ar['uni_m'] > 0) ? ($ar['uni_e'] / $ar['uni_m']) * 100 : 100;
            $starP = ($ar['s_m'] > 0) ? ($ar['s_e'] / $ar['s_m']) * 100 : 100;

            $totalScoreSum += ($attP * ($weights['attendance_pct']/100)) + ($uniP * ($weights['uniform_pct']/100)) + ($starP * ($weights['behavior_skills_pct']/100)) + ($fitP * ($weights['fitness_pct']/100));
        }
        $timeline['data'][] = round($totalScoreSum / count($stIds), 1);
    }

    // 2. Class Comparison (Average Weighted Score)
    $classParams = $sid ? [$sid] : [];
    $classSql = "SELECT c.id, CONCAT(g.name, ' - ', c.name) as class_name FROM classes c JOIN grades g ON c.grade_id = g.id WHERE c.active = 1 " . ($sid ? "AND c.school_id = ?" : "");
    $classesRaw = $db->prepare($classSql);
    $classesRaw->execute($classParams);
    $allClasses = $classesRaw->fetchAll();

    $classCompData = [];
    $currStart = date('Y-m-01');
    $currEnd = date('Y-m-t');

    foreach ($allClasses as $c) {
        $cid = $c['id'];
        $stStmt = $db->prepare("SELECT id FROM students WHERE class_id = ? AND active = 1");
        $stStmt->execute([$cid]);
        $stIds = $stStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($stIds)) {
            $classCompData[] = ['name' => $c['class_name'], 'score' => 0];
            continue;
        }

        $classSum = 0;
        foreach ($stIds as $id) {
            $fit = $db->prepare("SELECT SUM(sf.score) as e, SUM(ft.max_score) as m FROM student_fitness sf JOIN fitness_tests ft ON sf.test_id = ft.id WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?");
            $fit->execute([$id, $currStart, $currEnd]);
            $fr = $fit->fetch();
            $fitP = ($fr['m'] > 0) ? ($fr['e'] / $fr['m']) * 100 : 100;

            $att = $db->prepare("SELECT COUNT(*) as d, SUM(CASE WHEN status='present' THEN 1 WHEN status IN ('late','excused') THEN 0.5 ELSE 0 END) as att_e, SUM(CASE WHEN uniform_status='full' THEN 3 WHEN uniform_status='partial' THEN 2 WHEN uniform_status='wrong' THEN 1 ELSE 0 END) as uni_e, SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as uni_m, SUM(COALESCE(behavior_stars,0)+COALESCE(skills_stars,0)) as s_e, SUM((CASE WHEN behavior_stars>0 THEN 3 ELSE 0 END)+(CASE WHEN skills_stars>0 THEN 3 ELSE 0 END)) as s_m FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
            $att->execute([$id, $currStart, $currEnd]);
            $ar = $att->fetch();

            $attP = ($ar['d'] > 0) ? ($ar['att_e'] / $ar['d']) * 100 : 100;
            $uniP = ($ar['uni_m'] > 0) ? ($ar['uni_e'] / $ar['uni_m']) * 100 : 100;
            $starP = ($ar['s_m'] > 0) ? ($ar['s_e'] / $ar['s_m']) * 100 : 100;

            $classSum += ($attP * ($weights['attendance_pct']/100)) + ($uniP * ($weights['uniform_pct']/100)) + ($starP * ($weights['behavior_skills_pct']/100)) + ($fitP * ($weights['fitness_pct']/100));
        }
        $classCompData[] = ['name' => $c['class_name'], 'score' => round($classSum / count($stIds), 1)];
    }

    // Sort classes by score
    usort($classCompData, function($a, $b) { return $b['score'] <=> $a['score']; });

    $classComparison = [
        'labels' => array_column($classCompData, 'name'),
        'data' => array_column($classCompData, 'score')
    ];

    // 3. Attendance Heatmap (Last 30 days) - Keep as Attendance Intensity
    $heatmapRaw = $db->prepare("
        SELECT a.attendance_date as date,
               SUM(CASE WHEN a.status='present' THEN 1 WHEN a.status IN ('late','excused') THEN 0.5 ELSE 0 END) as present_count,
               COUNT(*) as total_count
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.active = 1 " . ($sid ? "AND s.school_id = ?" : "") . "
          AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY a.attendance_date
        ORDER BY a.attendance_date ASC
    ");
    $heatmapRaw->execute($sid ? [$sid] : []);
    $heatmapData = $heatmapRaw->fetchAll();

    $heatmap = ['labels' => [], 'data' => []];
    foreach ($heatmapData as $row) {
        $rate = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
        $heatmap['labels'][] = $row['date'];
        $heatmap['data'][] = $rate;
    }

    // 4. Top 10 Students (Weighted Final Grade)
    $stListStmt = $db->prepare("SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE s.active = 1 " . ($sid ? "AND s.school_id = ?" : ""));
    $stListStmt->execute($sid ? [$sid] : []);
    $allStudents = $stListStmt->fetchAll();

    $studentLeads = [];
    foreach ($allStudents as $s) {
        $id = $s['id'];
        // Re-use weighted calc
        $fit = $db->prepare("SELECT SUM(sf.score) as e, SUM(ft.max_score) as m FROM student_fitness sf JOIN fitness_tests ft ON sf.test_id = ft.id WHERE sf.student_id = ? AND sf.test_date BETWEEN ? AND ?");
        $fit->execute([$id, $currStart, $currEnd]);
        $fr = $fit->fetch();
        $fitP = ($fr['m'] > 0) ? ($fr['e'] / $fr['m']) * 100 : 100;

        $att = $db->prepare("SELECT COUNT(*) as d, SUM(CASE WHEN status='present' THEN 1 WHEN status IN ('late','excused') THEN 0.5 ELSE 0 END) as att_e, SUM(CASE WHEN uniform_status='full' THEN 3 WHEN uniform_status='partial' THEN 2 WHEN uniform_status='wrong' THEN 1 ELSE 0 END) as uni_e, SUM(CASE WHEN uniform_status IS NOT NULL THEN 3 ELSE 0 END) as uni_m, SUM(COALESCE(behavior_stars,0)+COALESCE(skills_stars,0)) as s_e, SUM((CASE WHEN behavior_stars>0 THEN 3 ELSE 0 END)+(CASE WHEN skills_stars>0 THEN 3 ELSE 0 END)) as s_m FROM attendance WHERE student_id = ? AND attendance_date BETWEEN ? AND ?");
        $att->execute([$id, $currStart, $currEnd]);
        $ar = $att->fetch();

        $attP = ($ar['d'] > 0) ? ($ar['att_e'] / $ar['d']) * 100 : 100;
        $uniP = ($ar['uni_m'] > 0) ? ($ar['uni_e'] / $ar['uni_m']) * 100 : 100;
        $starP = ($ar['s_m'] > 0) ? ($ar['s_e'] / $ar['s_m']) * 100 : 100;

        $final = ($attP * ($weights['attendance_pct']/100)) + ($uniP * ($weights['uniform_pct']/100)) + ($starP * ($weights['behavior_skills_pct']/100)) + ($fitP * ($weights['fitness_pct']/100));
        
        $studentLeads[] = [
            'id' => $id,
            'name' => $s['name'],
            'class_name' => $s['class_name'],
            'avg_score' => round($final, 1)
        ];
    }
    usort($studentLeads, function($a, $b) { return $b['avg_score'] <=> $a['avg_score']; });
    $top10 = array_slice($studentLeads, 0, 10);

    // Insights
    $avgAttendance = count($heatmap['data']) > 0 ? round(array_sum($heatmap['data']) / count($heatmap['data']), 1) : 0;
    $bestClass = !empty($classCompData) ? $classCompData[0]['name'] : '-';

    jsonSuccess([
        'timeline' => $timeline,
        'classComparison' => $classComparison,
        'heatmap' => $heatmap,
        'top10' => $top10,
        'insights' => [
            'avgAttendance30d' => $avgAttendance,
            'bestClass' => $bestClass
        ]
    ]);
}
