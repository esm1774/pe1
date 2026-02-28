<?php
/**
 * PE Smart School System - Fitness Tests & Results API
 * (Multi-Teacher + SaaS Support)
 */

// ============================================================
// FITNESS TESTS
// ============================================================
function getFitnessTests() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();
    $sql = "SELECT * FROM fitness_tests WHERE active = 1";
    if ($sid) $sql .= " AND school_id = $sid";
    $sql .= " ORDER BY id";
    jsonSuccess($db->query($sql)->fetchAll());
}

function saveFitnessTest() {
    requireRole(['admin', 'teacher']);
    $data     = getPostData();
    validateRequired($data, ['name', 'unit', 'type']);
    $db       = getDB();
    $id       = $data['id'] ?? null;
    $name     = sanitize($data['name']);
    $unit     = sanitize($data['unit']);
    $type     = sanitize($data['type']);
    $maxScore = (int)($data['max_score'] ?? 10);
    $sid      = schoolId();

    if ($id) {
        $sql = "UPDATE fitness_tests SET name=?, unit=?, type=?, max_score=? WHERE id=?";
        $params = [$name, $unit, $type, $maxScore, $id];
        if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
        $db->prepare($sql)->execute($params);
    } else {
        $db->prepare("INSERT INTO fitness_tests (school_id, name, unit, type, max_score) VALUES (?,?,?,?,?)")->execute([$sid, $name, $unit, $type, $maxScore]);
        $id = $db->lastInsertId();
    }
    logActivity($id ? 'update' : 'create', 'fitness_test', $id, $name);
    jsonSuccess(['id' => (int)$id], 'تم حفظ الاختبار');
}

function deleteFitnessTest() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $sid = schoolId();
    $sql = "UPDATE fitness_tests SET active = 0 WHERE id = ?";
    $params = [$id];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    getDB()->prepare($sql)->execute($params);
    logActivity('delete', 'fitness_test', $id);
    jsonSuccess(null, 'تم حذف الاختبار');
}

// ============================================================
// FITNESS RESULTS
// ============================================================
function getFitnessResults() {
    requireLogin();
    $classId = (int)getParam('class_id');
    $testId  = (int)getParam('test_id');

    if (!$classId || !$testId) jsonError('يجب تحديد الفصل والاختبار');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    $db = getDB();

    $queries = [
        "SELECT s.id as student_id, s.name, s.student_number,
               sf.value, sf.score, sf.test_date, sf.id as result_id,
               (SELECT GROUP_CONCAT(sh.condition_name SEPARATOR ', ') FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_notes
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id AND sf.test_id = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name",

        "SELECT s.id as student_id, s.name, s.student_number,
               sf.value, sf.score, sf.test_date, sf.id as result_id,
               NULL as health_notes
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id AND sf.test_id = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name"
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$testId, $classId]);
            jsonSuccess($stmt->fetchAll());
            return;
        } catch (PDOException $e) {
            continue;
        }
    }
    jsonError('خطأ في جلب نتائج اللياقة');
}

function saveFitnessResults() {
    requireRole(['admin', 'teacher']);
    $data    = getPostData();
    $testId  = (int)($data['test_id'] ?? 0);
    $classId = (int)($data['class_id'] ?? 0);
    $records = $data['records'] ?? [];

    if (!$testId || empty($records)) jsonError('بيانات غير مكتملة');
    if ($classId && !canAccessClass($classId)) jsonError('لا تملك صلاحية تسجيل نتائج هذا الفصل', 403);

    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO student_fitness (student_id, test_id, value, score, test_date, recorded_by)
        VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), score=VALUES(score), test_date=VALUES(test_date), recorded_by=VALUES(recorded_by), updated_at=NOW()");

    // Get test name for notification
    $tStmt = $db->prepare("SELECT name FROM fitness_tests WHERE id = ?");
    $tStmt->execute([$testId]);
    $testName = $tStmt->fetchColumn();

    $today = date('Y-m-d');
    $db->beginTransaction();
    try {
        foreach ($records as $r) {
            if (!isset($r['value']) || $r['value'] === '' || $r['value'] === null) continue;
            $studentId = (int)$r['student_id'];
            $stmt->execute([$studentId, $testId, (float)$r['value'], (int)$r['score'], $today, $_SESSION['user_id']]);

            // Notify Parents
            $sStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
            $sStmt->execute([$studentId]);
            $studentName = $sStmt->fetchColumn();

            $title = "نتيجة اختبار لياقة: " . $studentName;
            $msg = "تم رصد نتيجة الطالب ({$studentName}) في اختبار ({$testName}) بقيمة ({$r['value']}) ودرجة ({$r['score']}).";
            notifyStudentParents($studentId, 'fitness', $title, $msg);

            // Trigger Auto-Badges check
            try {
                include_once 'api/badges.php';
                checkAutoBadges($studentId);
            } catch (Exception $e) {}
        }
        $db->commit();
        updateClassPoints();
        logActivity('save_fitness', 'student_fitness', $testId, "Records: " . count($records));
        jsonSuccess(null, 'تم حفظ النتائج بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getFitnessView() {
    requireLogin();
    $classId = (int)getParam('class_id');
    if (!$classId) jsonError('يجب تحديد الفصل');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    $db      = getDB();
    $sid     = schoolId();

    // Fitness tests scoped to school
    $testSql = "SELECT * FROM fitness_tests WHERE active = 1";
    if ($sid) $testSql .= " AND school_id = $sid";
    $testSql .= " ORDER BY id";
    $tests   = $db->query($testSql)->fetchAll();

    $stmt    = $db->prepare("SELECT id, name, student_number FROM students WHERE class_id = ? AND active = 1 ORDER BY name");
    $stmt->execute([$classId]);
    $students  = $stmt->fetchAll();
    $stmt      = $db->prepare("SELECT sf.student_id, sf.test_id, sf.value, sf.score FROM student_fitness sf JOIN students s ON sf.student_id = s.id WHERE s.class_id = ? AND s.active = 1");
    $stmt->execute([$classId]);
    $resultMap = [];
    foreach ($stmt->fetchAll() as $r) $resultMap[$r['student_id']][$r['test_id']] = $r;
    jsonSuccess(['tests' => $tests, 'students' => $students, 'results' => $resultMap]);
}

// ============================================================
// FITNESS CRITERIA (Automated Scoring)
// ============================================================
function getFitnessCriteria() {
    requireLogin();
    $testId = (int)getParam('test_id');
    if (!$testId) jsonError('يجب تحديد الاختبار');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM fitness_criteria WHERE test_id = ? ORDER BY min_value ASC");
    $stmt->execute([$testId]);
    jsonSuccess($stmt->fetchAll());
}

function saveFitnessCriteria() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $testId = (int)($data['test_id'] ?? 0);
    $criteria = $data['criteria'] ?? []; // Array of {min_value, max_value, score}

    if (!$testId) jsonError('بيانات غير مكتملة');
    
    $db = getDB();
    $db->beginTransaction();
    try {
        // Clear existing criteria for this test
        $db->prepare("DELETE FROM fitness_criteria WHERE test_id = ?")->execute([$testId]);
        
        $stmt = $db->prepare("INSERT INTO fitness_criteria (test_id, min_value, max_value, score) VALUES (?, ?, ?, ?)");
        foreach ($criteria as $c) {
            if (!isset($c['min_value']) || !isset($c['max_value']) || !isset($c['score'])) continue;
            $stmt->execute([$testId, (float)$c['min_value'], (float)$c['max_value'], (int)$c['score']]);
        }
        
        $db->commit();
        logActivity('save_criteria', 'fitness_criteria', $testId, "Records: " . count($criteria));
        jsonSuccess(null, 'تم حفظ معايير التقييم بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function updateClassPoints() {
    $db = getDB();
    $sid = schoolId();
    $schoolFilter = $sid ? "AND c.school_id = $sid" : "";
    $db->exec("INSERT INTO class_points (class_id, total_score, average_score, total_points, students_count)
        SELECT c.id, COALESCE(SUM(sf.score),0), ROUND(COALESCE(AVG(sf.score),0),2),
               ROUND(COALESCE(AVG(sf.score),0)*10), COUNT(DISTINCT s.id)
        FROM classes c LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id WHERE c.active = 1 $schoolFilter GROUP BY c.id
        ON DUPLICATE KEY UPDATE total_score=VALUES(total_score), average_score=VALUES(average_score),
            total_points=VALUES(total_points), students_count=VALUES(students_count)");
    $db->exec("SET @rank = 0");
    $db->exec("UPDATE class_points SET rank_position = (@rank := @rank + 1) ORDER BY total_points DESC");
}
