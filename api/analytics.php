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
    $params = $sid ? [$sid] : [];
    $schoolFilterS = $sid ? "AND s.school_id = ?" : "";
    $schoolFilterC = $sid ? "AND c.school_id = ?" : "";

    // 1. Performance / Attendance Timeline (Last 6 Months)
    $stmt1 = $db->prepare("
        SELECT DATE_FORMAT(a.attendance_date, '%Y-%m') as month,
               SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present_count,
               COUNT(*) as total_count
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.active = 1 $schoolFilterS
        GROUP BY month
        ORDER BY month DESC 
        LIMIT 6
    ");
    $stmt1->execute($params);
    $timelineRaw = $stmt1->fetchAll();
    
    // Reverse to ascending chronological order
    $timelineRaw = array_reverse($timelineRaw);
    
    $timeline = [
        'labels' => [],
        'data' => []
    ];
    foreach ($timelineRaw as $row) {
        $rate = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
        $timeline['labels'][] = $row['month'];
        $timeline['data'][] = $rate;
    }

    // 2. Class Comparison (Average Fitness Score)
    $stmt2 = $db->prepare("
        SELECT CONCAT(g.name, ' - ', c.name) as class_name,
               ROUND(COALESCE(AVG(sf.score), 0), 2) as avg_score
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE c.active = 1 $schoolFilterC
        GROUP BY c.id
        ORDER BY avg_score DESC
    ");
    $stmt2->execute($params);
    $classesRaw = $stmt2->fetchAll();

    $classComparison = [
        'labels' => array_column($classesRaw, 'class_name'),
        'data' => array_column($classesRaw, 'avg_score')
    ];

    // 3. Attendance Heatmap (Last 30 days)
    $stmt3 = $db->prepare("
        SELECT a.attendance_date as date,
               SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present_count,
               COUNT(*) as total_count
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE s.active = 1 $schoolFilterS
          AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY a.attendance_date
        ORDER BY a.attendance_date ASC
    ");
    $stmt3->execute($params);
    $heatmapRaw = $stmt3->fetchAll();

    $heatmap = [
        'labels' => [],
        'data' => [] // array of {x, y, v} objects for custom scatter/rect rendering, or just a simple arr
    ];
    foreach ($heatmapRaw as $row) {
        $rate = $row['total_count'] > 0 ? round(($row['present_count'] / $row['total_count']) * 100, 1) : 0;
        $heatmap['labels'][] = $row['date'];
        $heatmap['data'][] = $rate;
    }

    // 4. Top 10 Students in Fitness Criteria
    $stmt4 = $db->prepare("
        SELECT s.id, s.name, CONCAT(g.name, ' - ', c.name) as class_name,
               ROUND(AVG(sf.score), 2) as avg_score
        FROM students s
        JOIN student_fitness sf ON sf.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE s.active = 1 $schoolFilterS
        GROUP BY s.id
        ORDER BY avg_score DESC
        LIMIT 10
    ");
    $stmt4->execute($params);
    $top10 = $stmt4->fetchAll();

    // Insights (Summaries)
    $avgAttendance = count($heatmap['data']) > 0 ? round(array_sum($heatmap['data']) / count($heatmap['data']), 1) : 0;
    
    // Most active class
    $bestClass = !empty($classesRaw) ? $classesRaw[0]['class_name'] : '-';

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
