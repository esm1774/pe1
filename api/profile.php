<?php
/**
 * PE Smart School System - Student Profile, Measurements & Health API
 */

// ============================================================
// STUDENT PROFILE
// ============================================================
function getStudentProfile() {
    requireLogin();
    $studentId = getParam('student_id');
    if (!$studentId) jsonError('يجب تحديد الطالب');
    
    // Security: Student can only see their own profile
    if ($_SESSION['user_role'] === 'student' && $_SESSION['user_id'] != $studentId) {
        jsonError('غير مصرح لك بمشاهدة هذا الملف الشخصي');
    }

    $db = getDB();

    try {
        $stmt = $db->prepare("
            SELECT s.*, CONCAT(g.name, ' - ', c.name) as full_class_name, g.name as grade_name, c.name as class_name,
                   TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
            FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
    } catch (PDOException $e) {
        $stmt = $db->prepare("
            SELECT s.*, CONCAT(g.name, ' - ', c.name) as full_class_name, g.name as grade_name, c.name as class_name,
                   0 as age
            FROM students s JOIN classes c ON s.class_id = c.id JOIN grades g ON c.grade_id = g.id WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
    }
    if (!$student) jsonError('الطالب غير موجود');

    $latestMeasurement = null;
    try {
        $stmt = $db->prepare("SELECT * FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC LIMIT 1");
        $stmt->execute([$studentId]);
        $latestMeasurement = $stmt->fetch();
    } catch (Exception $e) {}

    $measurements = [];
    try {
        $stmt = $db->prepare("SELECT * FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC");
        $stmt->execute([$studentId]);
        $measurements = $stmt->fetchAll();
    } catch (Exception $e) {}

    $healthConditions = [];
    try {
        $stmt = $db->prepare("SELECT * FROM student_health WHERE student_id = ? ORDER BY is_active DESC, created_at DESC");
        $stmt->execute([$studentId]);
        $healthConditions = $stmt->fetchAll();
    } catch (Exception $e) {}

    $activeAlerts = array_filter($healthConditions, function($h) { return $h['is_active']; });

    $stmt = $db->prepare("
        SELECT ft.name as test_name, ft.unit, ft.max_score, sf.value, sf.score, sf.test_date
        FROM fitness_tests ft LEFT JOIN student_fitness sf ON sf.test_id = ft.id AND sf.student_id = ?
        WHERE ft.active = 1 ORDER BY ft.id
    ");
    $stmt->execute([$studentId]);
    $fitnessResults = $stmt->fetchAll();

    $totalScore = 0; $totalMax = 0;
    foreach ($fitnessResults as $r) {
        if ($r['score'] !== null) { $totalScore += $r['score']; $totalMax += $r['max_score']; }
    }

    $stmt = $db->prepare("
        SELECT SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_count,
               SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_count,
               SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_count
        FROM attendance WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $attendance = $stmt->fetch();

    $badges = [];
    try {
        $stmt = $db->prepare("
            SELECT sb.id, b.name, b.icon, b.color, b.description, sb.awarded_at
            FROM student_badges sb
            JOIN badges b ON sb.badge_id = b.id
            WHERE sb.student_id = ?
            ORDER BY sb.awarded_at DESC
        ");
        $stmt->execute([$studentId]);
        $badges = $stmt->fetchAll();
    } catch (Exception $e) {}

    // --- NEW: WEIGHTED GRADING CALCULATION ---
    $sid = schoolId();
    $startDate = getParam('start_date', date('Y-m-01'));
    $endDate = getParam('end_date', date('Y-m-t'));

    // 1. Get School Weights
    $wStmt = $db->prepare("SELECT * FROM school_grading_weights WHERE school_id = ?");
    $wStmt->execute([$sid]);
    $weights = $wStmt->fetch(PDO::FETCH_ASSOC);
    if (!$weights) {
        $weights = ['attendance_pct' => 20, 'uniform_pct' => 20, 'behavior_skills_pct' => 20, 'fitness_pct' => 40];
    } else {
        $weights['attendance_pct'] = (int)$weights['attendance_pct'];
        $weights['uniform_pct'] = (int)$weights['uniform_pct'];
        $weights['behavior_skills_pct'] = (int)$weights['behavior_skills_pct'];
        $weights['fitness_pct'] = (int)$weights['fitness_pct'];
    }

    // 2. Attendance, Uniform, Behavior & Skills Data
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
            SUM(CASE WHEN participation_stars IS NOT NULL THEN 3 ELSE 0 END) as max_participation_score
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

    // 3. Fitness Score (Weighted)
    $fitPercent = $totalMax > 0 ? ($totalScore / $totalMax) * 100 : 100;

    // 5. Assessments Data
    $asStmt = $db->prepare("SELECT type, score, max_score FROM student_assessments WHERE student_id = ?");
    $asStmt->execute([$studentId]);
    $studentAssessments = $asStmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize based on max scores from settings (default 10)
    $asMap = [];
    foreach($studentAssessments as $sa) $asMap[$sa['type']] = $sa;

    $qScore = (float)($asMap['quiz']['score'] ?? 0);
    $pScore = (float)($asMap['project']['score'] ?? 0);
    $fnScore = (float)($asMap['final_exam']['score'] ?? 0);

    $qMax = (int)($weights['quiz_max'] ?? 10) ?: 10;
    $pMax = (int)($weights['project_max'] ?? 10) ?: 10;
    $fnMax = (int)($weights['final_exam_max'] ?? 10) ?: 10;

    $qPercent = ($qScore / $qMax) * 100;
    $prjPercent = ($pScore / $pMax) * 100;
    $fnlPercent = ($fnScore / $fnMax) * 100;

    // 6. Participations Data (Stars)
    $partPercent = (int)$attData['max_participation_score'] > 0 ? ((float)$attData['participation_score'] / (float)$attData['max_participation_score']) * 100 : 100;

    // 7. Final Weighted Grade
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
        'fitness_pct' => round($fitPercent, 1),
        'final_grade' => round($finalScore, 1),
        'letter' => $letter,
        'total_days' => $totalDays
    ];

    jsonSuccess([
        'student' => $student,
        'latestMeasurement' => $latestMeasurement,
        'measurements' => $measurements,
        'healthConditions' => $healthConditions,
        'activeAlerts' => array_values($activeAlerts),
        'fitness' => $fitnessResults,
        'badges' => $badges,
        'assessments' => $studentAssessments,
        'totalScore' => (int)$totalScore,
        'totalMax' => (int)$totalMax,
        'percentage' => round($finalScore), // Comprehensive Weighted Score
        'grading_summary' => $gradingSummary,
        'attendance' => [
            'present' => (int)($attendance['present_count'] ?? 0),
            'absent' => (int)($attendance['absent_count'] ?? 0),
            'late' => (int)($attendance['late_count'] ?? 0)
        ]
    ]);
}


// ============================================================
// MEASUREMENTS
// ============================================================
function getMeasurements() {
    requireLogin();
    $studentId = (int)getParam('student_id');
    if (!$studentId) jsonError('يجب تحديد الطالب');
    
    $role = $_SESSION['user_role'] ?? '';
    $uid  = $_SESSION['user_id'] ?? 0;
    
    // Fix: Enforce authorization — students can only see their own measurements
    if ($role === 'student' && $uid != $studentId) {
        jsonError('غير مصرح لك بمشاهدة هذه البيانات', 403);
    }
    // Fix: Parents can only see their linked children
    if ($role === 'parent') {
        $db = getDB();
        $chk = $db->prepare("SELECT 1 FROM parent_students WHERE parent_id = ? AND student_id = ?");
        $chk->execute([$uid, $studentId]);
        if (!$chk->fetch()) jsonError('غير مصرح لك بمشاهدة بيانات هذا الطالب', 403);
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM student_measurements WHERE student_id = ? ORDER BY measurement_date DESC");
    $stmt->execute([$studentId]);
    jsonSuccess($stmt->fetchAll());
}

function saveMeasurement() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['student_id', 'measurement_date']);
    $db = getDB();

    $id = $data['id'] ?? null;
    $studentId = (int)$data['student_id'];
    $date = sanitize($data['measurement_date']);
    $height = !empty($data['height_cm']) ? (float)$data['height_cm'] : null;
    $weight = !empty($data['weight_kg']) ? (float)$data['weight_kg'] : null;
    $waist = !empty($data['waist_cm']) ? (float)$data['waist_cm'] : null;
    $heartRate = !empty($data['resting_heart_rate']) ? (int)$data['resting_heart_rate'] : null;
    $notes = !empty($data['notes']) ? sanitize($data['notes']) : null;

    $bmi = null;
    $bmiCategory = null;
    if ($height && $weight && $height > 0) {
        $heightM = $height / 100;
        $bmi = round($weight / ($heightM * $heightM), 1);
        if ($bmi < 18.5) $bmiCategory = 'underweight';
        elseif ($bmi < 25) $bmiCategory = 'normal';
        elseif ($bmi < 30) $bmiCategory = 'overweight';
        else $bmiCategory = 'obese';
    }

    if ($id) {
        $db->prepare("UPDATE student_measurements SET measurement_date=?, height_cm=?, weight_kg=?, bmi=?, bmi_category=?, waist_cm=?, resting_heart_rate=?, notes=?, recorded_by=? WHERE id=?")
           ->execute([$date, $height, $weight, $bmi, $bmiCategory, $waist, $heartRate, $notes, $_SESSION['user_id'], $id]);
    } else {
        $db->prepare("INSERT INTO student_measurements (student_id, measurement_date, height_cm, weight_kg, bmi, bmi_category, waist_cm, resting_heart_rate, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$studentId, $date, $height, $weight, $bmi, $bmiCategory, $waist, $heartRate, $notes, $_SESSION['user_id']]);
        $id = $db->lastInsertId();
    }
    logActivity('save_measurement', 'student_measurements', $id);

    // Notify Parents
    $sStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
    $sStmt->execute([$studentId]);
    $studentName = $sStmt->fetchColumn();

    $title = "قياسات جديدة: " . $studentName;
    $msg = "تم تحديث القياسات البدنية للطالب ({$studentName}) بتاريخ ({$date}).";
    if ($bmi) $msg .= " مؤشر كتلة الجسم الحالي: {$bmi}.";
    
    notifyStudentParents($studentId, 'health', $title, $msg);

    jsonSuccess(['id' => (int)$id, 'bmi' => $bmi, 'bmi_category' => $bmiCategory], 'تم حفظ القياسات بنجاح');
}

function deleteMeasurement() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    $db->prepare("DELETE FROM student_measurements WHERE id = ?")->execute([$id]);
    logActivity('delete', 'student_measurements', $id);
    jsonSuccess(null, 'تم حذف القياس');
}

// ============================================================
// HEALTH CONDITIONS
// ============================================================
function getHealthConditions() {
    requireLogin();
    $studentId = getParam('student_id');
    if (!$studentId) jsonError('يجب تحديد الطالب');
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM student_health WHERE student_id = ? ORDER BY is_active DESC, created_at DESC");
    $stmt->execute([$studentId]);
    jsonSuccess($stmt->fetchAll());
}

function saveHealthCondition() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['student_id', 'condition_type', 'condition_name']);
    $db = getDB();

    $id = $data['id'] ?? null;
    $studentId = (int)$data['student_id'];
    $type = sanitize($data['condition_type']);
    $name = sanitize($data['condition_name']);
    $severity = sanitize($data['severity'] ?? 'mild');
    $notes = !empty($data['notes']) ? sanitize($data['notes']) : null;
    $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $startDate = !empty($data['start_date']) ? sanitize($data['start_date']) : null;
    $endDate = !empty($data['end_date']) ? sanitize($data['end_date']) : null;

    if ($id) {
        $db->prepare("UPDATE student_health SET condition_type=?, condition_name=?, severity=?, notes=?, is_active=?, start_date=?, end_date=?, recorded_by=? WHERE id=?")
           ->execute([$type, $name, $severity, $notes, $isActive, $startDate, $endDate, $_SESSION['user_id'], $id]);
    } else {
        $db->prepare("INSERT INTO student_health (student_id, condition_type, condition_name, severity, notes, is_active, start_date, end_date, recorded_by) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$studentId, $type, $name, $severity, $notes, $isActive, $startDate, $endDate, $_SESSION['user_id']]);
        $id = $db->lastInsertId();
    }
    logActivity('save_health', 'student_health', $id);

    // Notify Parents
    $sStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
    $sStmt->execute([$studentId]);
    $studentName = $sStmt->fetchColumn();

    $statusTxt = ($isActive) ? "إضافة/تحديث حالة" : "إيقاف حالة";
    $title = "تحديث الحالة الصحية: " . $studentName;
    $msg = "تم {$statusTxt} صحية للطالب ({$studentName}): {$name}.";
    
    notifyStudentParents($studentId, 'health', $title, $msg);

    jsonSuccess(['id' => (int)$id], 'تم حفظ الحالة الصحية');
}

function deleteHealthCondition() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    $db->prepare("DELETE FROM student_health WHERE id = ?")->execute([$id]);
    logActivity('delete', 'student_health', $id);
    jsonSuccess(null, 'تم حذف الحالة الصحية');
}

function getClassHealthAlerts() {
    requireLogin();
    $classId = getParam('class_id');
    if (!$classId) jsonError('يجب تحديد الفصل');
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.id as student_id, s.name as student_name, sh.condition_type, sh.condition_name, sh.severity, sh.notes
        FROM student_health sh
        JOIN students s ON sh.student_id = s.id
        WHERE s.class_id = ? AND s.active = 1 AND sh.is_active = 1
        ORDER BY FIELD(sh.severity, 'severe', 'moderate', 'mild'), s.name
    ");
    $stmt->execute([$classId]);
    jsonSuccess($stmt->fetchAll());
}
