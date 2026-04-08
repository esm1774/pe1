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
    Subscription::requireFeature('fitness_tests');
    $db = getDB();
    $sid = schoolId();

    // Seed defaults if empty for this school
    if ($sid) {
        Subscription::seedDefaultFitnessTests($sid);
    }

    $sql = "SELECT * FROM fitness_tests WHERE active = 1";
    if ($sid) $sql .= " AND school_id = $sid";
    $sql .= " ORDER BY id";
    jsonSuccess($db->query($sql)->fetchAll());
}

function saveFitnessTest() {
    requireRole(['admin', 'teacher']);
    Subscription::requireFeature('fitness_tests');
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
    Subscription::requireFeature('fitness_tests');
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
    Subscription::requireFeature('fitness_tests');
    $classId  = (int)getParam('class_id');
    $testId   = (int)getParam('test_id');
    $testDate = sanitize(getParam('test_date', date('Y-m-d')));

    if (!$classId || !$testId) jsonError('يجب تحديد الفصل والاختبار');
    
    requireOwnership('fitness_tests', $testId);
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    $db = getDB();

    $queries = [
        "SELECT s.id as student_id, s.name, s.student_number,
               sf.value, sf.score, sf.test_date, sf.id as result_id,
               (SELECT GROUP_CONCAT(sh.condition_name SEPARATOR ', ') FROM student_health sh WHERE sh.student_id = s.id AND sh.is_active = 1) as health_notes
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id AND sf.test_id = ? AND sf.test_date = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name",

        "SELECT s.id as student_id, s.name, s.student_number,
               sf.value, sf.score, sf.test_date, sf.id as result_id,
               NULL as health_notes
        FROM students s LEFT JOIN student_fitness sf ON sf.student_id = s.id AND sf.test_id = ? AND sf.test_date = ?
        WHERE s.class_id = ? AND s.active = 1 ORDER BY s.name"
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$testId, $testDate, $classId]);
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
    Subscription::requireFeature('fitness_tests');
    $data     = getPostData();
    $testId   = (int)($data['test_id'] ?? 0);
    $classId  = (int)($data['class_id'] ?? 0);
    $testDate = sanitize($data['test_date'] ?? date('Y-m-d'));
    $records  = $data['records'] ?? [];

    if (!$testId || empty($records)) jsonError('بيانات غير مكتملة');
    
    // SaaS Isolation
    requireOwnership('fitness_tests', $testId);
    if ($classId && !canAccessClass($classId)) jsonError('لا تملك صلاحية تسجيل نتائج هذا الفصل', 403);

    $db   = getDB();
    $sid  = schoolId();
    $stmt = $db->prepare("INSERT INTO student_fitness (student_id, test_id, value, score, test_date, recorded_by, school_id)
        VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE 
        value=VALUES(value), 
        score=VALUES(score), 
        recorded_by=VALUES(recorded_by), 
        school_id=VALUES(school_id),
        updated_at=NOW()");

    $delStmt = $db->prepare("DELETE FROM student_fitness WHERE student_id = ? AND test_id = ? AND test_date = ?");

    // Get test name for notification
    $tStmt = $db->prepare("SELECT name FROM fitness_tests WHERE id = ?");
    $tStmt->execute([$testId]);
    $testName = $tStmt->fetchColumn();
    
    // Performance: Include badges once before loop
    try { include_once __DIR__ . '/badges.php'; } catch (Exception $e) {}
    
    $db->beginTransaction();
    try {
        foreach ($records as $r) {
            $studentId = (int)$r['student_id'];
            
            // Security: verify student belongs to school
            if (!verifyOwnership('students', $studentId)) continue;
            
            if (!isset($r['value']) || $r['value'] === '' || $r['value'] === null) {
                // Delete if empty
                $delStmt->execute([$studentId, $testId, $testDate]);
                continue;
            }

            $stmt->execute([
                $studentId, 
                $testId, 
                (float)$r['value'], 
                (int)$r['score'], 
                $testDate, 
                $_SESSION['user_id'],
                $sid
            ]);

            // Notify Parents
            $sStmt = $db->prepare("SELECT name FROM students WHERE id = ?");
            $sStmt->execute([$studentId]);
            $studentName = $sStmt->fetchColumn();

            $title = "نتيجة اختبار لياقة: " . $studentName;
            $msg = "تم رصد نتيجة الطالب ({$studentName}) في اختبار ({$testName}) بقيمة ({$r['value']}) ودرجة ({$r['score']}).";
            notifyStudentParents($studentId, 'fitness', $title, $msg);

            // Auto Badges check
            checkAutoBadges($studentId);
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
    Subscription::requireFeature('fitness_tests');
    $classId = (int)getParam('class_id');
    if (!$classId) jsonError('يجب تحديد الفصل');
    if (!canAccessClass($classId)) jsonError('لا تملك صلاحية الوصول لهذا الفصل', 403);

    $db      = getDB();
    $sid     = schoolId();

    $testSql = "SELECT * FROM fitness_tests WHERE active = 1";
    if ($sid) $testSql .= " AND school_id = $sid";
    $testSql .= " ORDER BY id";
    $tests   = $db->query($testSql)->fetchAll();

    $stmt    = $db->prepare("SELECT id, name, student_number FROM students WHERE class_id = ? AND active = 1 ORDER BY name");
    $stmt->execute([$classId]);
    $students  = $stmt->fetchAll();

    // جلب أفضل نتيجة لكل طالب في كل اختبار (بدلاً من أي نتيجة)
    // إذا كان higher_better -> MAX(score)
    // إذا كان lower_better -> score المرتبط بـ MIN(value)
    $resultStmt = $db->prepare("
        SELECT 
            sf.student_id,
            sf.test_id,
            ft.type as test_type,
            CASE
                WHEN ft.type = 'lower_better' THEN
                    (SELECT sf2.score FROM student_fitness sf2 WHERE sf2.student_id = sf.student_id AND sf2.test_id = sf.test_id ORDER BY sf2.value ASC LIMIT 1)
                ELSE
                    MAX(sf.score)
            END as best_score,
            CASE
                WHEN ft.type = 'lower_better' THEN MIN(sf.value)
                ELSE (SELECT sf3.value FROM student_fitness sf3 WHERE sf3.student_id = sf.student_id AND sf3.test_id = sf.test_id ORDER BY sf3.score DESC LIMIT 1)
            END as best_value,
            COUNT(sf.id) as sessions_count
        FROM student_fitness sf
        JOIN students s ON sf.student_id = s.id
        JOIN fitness_tests ft ON sf.test_id = ft.id
        WHERE s.class_id = ? AND s.active = 1
        GROUP BY sf.student_id, sf.test_id, ft.type
    ");
    $resultStmt->execute([$classId]);
    $resultMap = [];
    foreach ($resultStmt->fetchAll() as $r) {
        $resultMap[$r['student_id']][$r['test_id']] = [
            'score'          => $r['best_score'],
            'value'          => $r['best_value'],
            'sessions_count' => $r['sessions_count']
        ];
    }
    jsonSuccess(['tests' => $tests, 'students' => $students, 'results' => $resultMap]);
}

// ============================================================
// FITNESS CRITERIA (Automated Scoring)
// ============================================================
function getFitnessCriteria() {
    requireLogin();
    Subscription::requireFeature('fitness_tests');
    $testId = (int)getParam('test_id');
    if (!$testId) jsonError('يجب تحديد الاختبار');
    
    // SaaS Isolation
    requireOwnership('fitness_tests', $testId);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM fitness_criteria WHERE test_id = ? ORDER BY min_value ASC");
    $stmt->execute([$testId]);
    jsonSuccess($stmt->fetchAll());
}

function saveFitnessCriteria() {
    requireRole(['admin', 'teacher']);
    Subscription::requireFeature('fitness_tests');
    $data = getPostData();
    $testId = (int)($data['test_id'] ?? 0);
    $criteria = $data['criteria'] ?? []; // Array of {min_value, max_value, score}

    if (!$testId) jsonError('بيانات غير مكتملة');
    
    // SaaS Isolation
    requireOwnership('fitness_tests', $testId);

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


// ============================================================
// SESSION MANAGEMENT (New Functions)
// ============================================================

// جلب التواريخ التي تم فيها رصد نتائج لاختبار معين وفصل معين
function getFitnessSessionDates() {
    requireLogin();
    Subscription::requireFeature('fitness_tests');
    $testId  = (int)getParam('test_id');
    $classId = (int)getParam('class_id');

    if (!$testId || !$classId) jsonError('يجب تحديد الاختبار والفصل');
    requireOwnership('fitness_tests', $testId);
    if (!canAccessClass($classId)) jsonError('صلاحية مرفوضة', 403);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT sf.test_date,
               COUNT(sf.id) as student_count
        FROM student_fitness sf
        JOIN students s ON sf.student_id = s.id
        WHERE sf.test_id = ? AND s.class_id = ? AND s.active = 1
        GROUP BY sf.test_date
        ORDER BY sf.test_date DESC
    ");
    $stmt->execute([$testId, $classId]);
    jsonSuccess($stmt->fetchAll());
}

// حذف نتيجة طالب واحد في تاريخ واختبار محددين
function deleteFitnessResult() {
    requireRole(['admin', 'teacher']);
    Subscription::requireFeature('fitness_tests');
    $data      = getPostData();
    $studentId = (int)($data['student_id'] ?? 0);
    $testId    = (int)($data['test_id'] ?? 0);
    $testDate  = sanitize($data['test_date'] ?? '');

    if (!$studentId || !$testId || !$testDate) jsonError('بيانات غير مكتملة');
    requireOwnership('fitness_tests', $testId);
    if (!verifyOwnership('students', $studentId)) jsonError('غير مصرح لك');

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM student_fitness WHERE student_id = ? AND test_id = ? AND test_date = ?");
    $stmt->execute([$studentId, $testId, $testDate]);
    logActivity('delete_fitness_result', 'student_fitness', $testId, "student_id: $studentId, date: $testDate");
    jsonSuccess(null, 'تم حذف نتيجة الطالب');
}

// حذف جلسة كاملة (جميع نتائج اختبار معين في تاريخ معين لفصل معين)
function deleteFitnessSession() {
    requireRole(['admin', 'teacher']);
    Subscription::requireFeature('fitness_tests');
    $data     = getPostData();
    $testId   = (int)($data['test_id'] ?? 0);
    $classId  = (int)($data['class_id'] ?? 0);
    $testDate = sanitize($data['test_date'] ?? '');

    if (!$testId || !$classId || !$testDate) jsonError('بيانات غير مكتملة');
    requireOwnership('fitness_tests', $testId);
    if (!canAccessClass($classId)) jsonError('صلاحية مرفوضة', 403);

    $db = getDB();
    $stmt = $db->prepare("
        DELETE sf FROM student_fitness sf
        JOIN students s ON sf.student_id = s.id
        WHERE sf.test_id = ? AND s.class_id = ? AND sf.test_date = ?
    ");
    $stmt->execute([$testId, $classId, $testDate]);
    logActivity('delete_fitness_session', 'student_fitness', $testId, "class_id: $classId, date: $testDate");
    jsonSuccess(null, 'تم حذف جلسة الرصد بالكامل');
}

function updateClassPoints() {
    $db = getDB();
    $sid = schoolId();
    $schoolFilter = $sid ? "AND c.school_id = $sid" : "";

    // 1. Get test counts for each school
    $testCounts = $db->query("SELECT school_id, COUNT(*) as cnt FROM fitness_tests WHERE active = 1 GROUP BY school_id")->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Get class stats
    $classes = $db->query("
        SELECT c.id, c.school_id, COUNT(DISTINCT s.id) as students_count, COALESCE(SUM(sf.score), 0) as total_earned
        FROM classes c 
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        LEFT JOIN student_fitness sf ON sf.student_id = s.id
        WHERE c.active = 1 $schoolFilter
        GROUP BY c.id
    ")->fetchAll();

    $saveStmt = $db->prepare("INSERT INTO class_points (class_id, total_score, average_score, total_points, students_count)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE total_score=VALUES(total_score), average_score=VALUES(average_score),
            total_points=VALUES(total_points), students_count=VALUES(students_count)");

    foreach ($classes as $c) {
        $cid = (int)$c['id'];
        $schId = (int)$c['school_id'];
        $sCount = (int)$c['students_count'];
        $tEarned = (float)$c['total_earned'];
        $tCount = (int)($testCounts[$schId] ?? 0);
        
        $denominator = max(1, $sCount * $tCount);
        $avg = round($tEarned / $denominator, 2);
        $pts = round(($tEarned / $denominator) * 10);
        
        $saveStmt->execute([$cid, $tEarned, $avg, $pts, $sCount]);
    }

    // 3. Update Rankings (Safe multi-step process for shared hosting)
    $allPoints = $db->query("SELECT class_id FROM class_points ORDER BY total_points DESC, total_score DESC")->fetchAll();
    $rank = 1;
    $updRank = $db->prepare("UPDATE class_points SET rank_position = ? WHERE class_id = ?");
    foreach ($allPoints as $p) {
        $updRank->execute([$rank++, $p['class_id']]);
    }
}
