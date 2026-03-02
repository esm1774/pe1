<?php
/**
 * PE Smart School System - Badges API
 */

function getBadges() {
    requireLogin();
    Subscription::requireFeature('badges');
    $db = getDB();
    $sid = schoolId();
    $sql = "SELECT * FROM badges WHERE 1=1";
    if ($sid) $sql .= " AND school_id = $sid";
    $sql .= " ORDER BY id ASC";
    $badges = $db->query($sql)->fetchAll();
    jsonSuccess($badges);
}

function getStudentBadges() {
    requireLogin();
    $studentId = getParam('student_id');
    if (!$studentId) jsonError('يجب تحديد الطالب');
    
    // SaaS Isolation
    requireOwnership('students', $studentId);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT sb.*, b.name, b.icon, b.color, b.description, u.name as awarded_by_name
        FROM student_badges sb
        JOIN badges b ON sb.badge_id = b.id
        LEFT JOIN users u ON sb.awarded_by = u.id
        WHERE sb.student_id = ?
        ORDER BY sb.awarded_at DESC
    ");
    $stmt->execute([$studentId]);
    jsonSuccess($stmt->fetchAll());
}

function awardBadge() {
    requireRole(['admin', 'teacher', 'supervisor']);
    $data = getPostData();
    validateRequired($data, ['student_id', 'badge_id']);

    // SaaS Isolation
    requireOwnership('students', $data['student_id']);
    requireOwnership('badges', $data['badge_id']);

    $db = getDB();
    
    // Check if already has this badge (to avoid duplicates of the same badge type if not intended)
    // For simplicity, we allow multiple if awarded at different times, but usually one of each is enough.
    $stmt = $db->prepare("SELECT id FROM student_badges WHERE student_id = ? AND badge_id = ?");
    $stmt->execute([$data['student_id'], $data['badge_id']]);
    if ($stmt->fetch()) {
        jsonError('هذا الطالب حصل على هذا الوسام بالفعل');
    }

    $stmt = $db->prepare("INSERT INTO student_badges (student_id, badge_id, awarded_by, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $data['student_id'],
        $data['badge_id'],
        $_SESSION['user_id'],
        sanitize($data['notes'] ?? '')
    ]);

    // Create notification for student/parent
    try {
        include_once 'api/notifications.php';
        $badgeName = $db->query("SELECT name FROM badges WHERE id = " . (int)$data['badge_id'])->fetchColumn();
        notifyStudentParents($data['student_id'], 'general', '🎉 وسام جديد!', "حصل ابنكم على وسام: $badgeName");
    } catch (Exception $e) {}

    logActivity('award_badge', 'student', $data['student_id'], "Badge ID: " . $data['badge_id']);
    jsonSuccess(null, 'تم منح الوسام بنجاح');
}

function revokeBadge() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('يجب تحديد المعرف');
    
    // SaaS Isolation
    requireOwnership('student_badges', $id);

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM student_badges WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonSuccess(null, 'تم سحب الوسام');
}

function saveBadge() {
    requireRole(['admin']);
    $data = getPostData();
    validateRequired($data, ['name', 'icon', 'color']);

    $db = getDB();
    $id = $data['id'] ?? null;
    $name = sanitize($data['name']);
    $description = sanitize($data['description'] ?? '');
    $icon = sanitize($data['icon']);
    $color = sanitize($data['color']);
    $type = sanitize($data['badge_type'] ?? 'manual');
    $criteriaValue = isset($data['criteria_value']) && $data['criteria_value'] !== '' ? (float)$data['criteria_value'] : null;
    $sid = schoolId();

    if ($id) {
        $stmt = $db->prepare("UPDATE badges SET name = ?, description = ?, icon = ?, color = ?, badge_type = ?, criteria_value = ? WHERE id = ?" . ($sid ? " AND school_id = $sid" : ""));
        $stmt->execute([$name, $description, $icon, $color, $type, $criteriaValue, $id]);
    } else {
        $stmt = $db->prepare("INSERT INTO badges (school_id, name, description, icon, color, badge_type, criteria_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sid, $name, $description, $icon, $color, $type, $criteriaValue]);
    }

    jsonSuccess(null, 'تم حفظ الوسام بنجاح');
}

function deleteBadge() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('يجب تحديد المعرف');

    // SaaS Isolation
    requireOwnership('badges', $id);

    $db = getDB();
    
    // Check if any student has this badge
    $check = $db->prepare("SELECT COUNT(*) FROM student_badges WHERE badge_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        jsonError('لا يمكن حذف هذا الوسام لأنه ممنوح لبعض الطلاب بالفعل. يرجى سحب الأوسمة من الطلاب أولاً.');
    }

    $stmt = $db->prepare("DELETE FROM badges WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonSuccess(null, 'تم حذف الوسام');
}

/**
 * Automated Badge Awarding Engine
 * This can be triggered manually or after specific actions
 */
function checkAutoBadges($studentId = null) {
    $db = getDB();
    
    // Get all automated badges
    $autoBadges = $db->query("SELECT * FROM badges WHERE badge_type != 'manual'")->fetchAll();
    if (empty($autoBadges)) return 0;
    
    // Determine target students
    if ($studentId) {
        $students = [[ 'id' => $studentId ]];
    } else {
        $students = $db->query("SELECT id FROM students WHERE active = 1")->fetchAll();
    }

    $awardedCount = 0;

    foreach ($students as $s) {
        $sid = $s['id'];

        foreach ($autoBadges as $b) {
            $badgeId = $b['id'];
            $type = $b['badge_type'];
            $threshold = (float)($b['criteria_value'] ?? 0);

            // Check if already has it
            $has = $db->prepare("SELECT 1 FROM student_badges WHERE student_id = ? AND badge_id = ?");
            $has->execute([$sid, $badgeId]);
            if ($has->fetch()) continue;

            $shouldAward = false;
            $note = '';

            if ($type === 'attendance_100') {
                $threshold = $threshold ?: 100.0; // Default to 100%
                $stats = $db->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                    FROM attendance 
                    WHERE student_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ");
                $stats->execute([$sid]);
                $res = $stats->fetch();
                if ($res['total'] >= 5) {
                    $percent = ($res['present'] / $res['total']) * 100;
                    if ($percent >= $threshold) {
                        $shouldAward = true;
                        $note = "تلقائي: نسبة حضور متميزة ({$percent}%) خلال آخر 30 يوم";
                    }
                }
            } 
            else if ($type === 'fitness_pro') {
                $threshold = $threshold ?: 9.0;
                $fit = $db->prepare("SELECT AVG(score) as avg_score FROM student_fitness WHERE student_id = ?");
                $fit->execute([$sid]);
                $avg = (float)$fit->fetchColumn();
                if ($avg >= $threshold) {
                    $shouldAward = true;
                    $note = "تلقائي: مستوى لياقة متفوق (معدل {$avg})";
                }
            }
            else if ($type === 'improvement') {
                $threshold = $threshold ?: 1.0;
                // Check if last score is better than previous score for any test
                $fit = $db->prepare("
                    SELECT test_id, score, test_date 
                    FROM student_fitness 
                    WHERE student_id = ? 
                    ORDER BY test_id, test_date DESC
                ");
                $fit->execute([$sid]);
                $results = $fit->fetchAll(PDO::FETCH_GROUP);
                
                foreach ($results as $testId => $testResults) {
                    if (count($testResults) >= 2) {
                        $latest = (float)$testResults[0]['score'];
                        $previous = (float)$testResults[1]['score'];
                        if (($latest - $previous) >= $threshold) {
                            $shouldAward = true;
                            $note = "تلقائي: تحسن ملحوظ في الأداء (+{$threshold} درجات)";
                            break;
                        }
                    }
                }
            }

            if ($shouldAward) {
                awardBadgeInternal($sid, $badgeId, $note);
                $awardedCount++;
            }
        }
    }

    return $awardedCount;
}

/**
 * Internal helper to award badge without separate role check
 */
function awardBadgeInternal($studentId, $badgeId, $notes = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$studentId, $badgeId, null, $notes]);

    // Notification
    try {
        include_once 'api/notifications.php';
        $badgeName = $db->query("SELECT name FROM badges WHERE id = " . (int)$badgeId)->fetchColumn();
        notifyStudentParents($studentId, 'general', '🎊 وسام تلقائي جديد!', "ابنكم استحق وسام جديد: $badgeName ($notes)");
    } catch (Exception $e) {}
}

/**
 * Endpoint to trigger auto-checks
 */
function runAutoBadges() {
    requireRole(['admin']);
    $count = checkAutoBadges();
    jsonSuccess(['awarded' => $count], "تم تشغيل نظام الأتمتة. تم منح $count وساماً جديداً لمستحقيها.");
}
