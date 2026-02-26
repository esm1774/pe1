<?php
/**
 * Sports Teams - Training Sessions & Attendance
 * إدارة التدريبات وحضور اللاعبين
 */

// ============================================================
// TRAINING SESSIONS
// ============================================================

function listSessions() {
    requireLogin();
    $teamId = getParam('team_id');
    if (!$teamId) jsonError('معرّف الفريق مطلوب');

    $db = getDB();
    $stmt = $db->prepare("
        SELECT ts.*,
               u.name AS coach_name,
               COUNT(ta.id)                                    AS total_attendance,
               SUM(ta.status = 'present')                     AS present_count,
               ROUND(AVG(ta.performance), 1)                  AS avg_performance
        FROM training_sessions ts
        LEFT JOIN users u ON ts.coach_id = u.id
        LEFT JOIN training_attendance ta ON ta.session_id = ts.id
        WHERE ts.team_id = ?
        GROUP BY ts.id
        ORDER BY ts.session_date DESC
    ");
    $stmt->execute([$teamId]);
    jsonSuccess($stmt->fetchAll());
}

function createSession() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    if (empty($data['team_id']))      jsonError('معرّف الفريق مطلوب');
    if (empty($data['session_date'])) jsonError('تاريخ التدريب مطلوب');

    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO training_sessions (team_id, title, session_date, start_time, end_time, venue, focus, notes, coach_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['team_id'],
        $data['title']        ?? 'تدريب',
        $data['session_date'],
        $data['start_time']   ?? null,
        $data['end_time']     ?? null,
        $data['venue']        ?? null,
        $data['focus']        ?? null,
        $data['notes']        ?? null,
        $data['coach_id']     ?? ($_SESSION['user_id'] ?? null)
    ]);

    $sessionId = $db->lastInsertId();

    // إنشاء سجل حضور مبدئي لجميع أعضاء الفريق النشطين
    _initAttendance($sessionId, $data['team_id']);

    logActivity('create', 'training_session', $sessionId, $data['title'] ?? 'تدريب');
    jsonSuccess(['id' => $sessionId], 'تم إنشاء جلسة التدريب');
}

function updateSession() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    if (empty($data['id'])) jsonError('معرّف الجلسة مطلوب');

    $db = getDB();
    $db->prepare("
        UPDATE training_sessions
        SET title = ?, session_date = ?, start_time = ?, end_time = ?,
            venue = ?, focus = ?, notes = ?
        WHERE id = ?
    ")->execute([
        $data['title']        ?? 'تدريب',
        $data['session_date'] ?? date('Y-m-d'),
        $data['start_time']   ?? null,
        $data['end_time']     ?? null,
        $data['venue']        ?? null,
        $data['focus']        ?? null,
        $data['notes']        ?? null,
        $data['id']
    ]);

    jsonSuccess(null, 'تم تحديث جلسة التدريب');
}

function deleteSession() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف الجلسة مطلوب');

    $db = getDB();
    $db->prepare("DELETE FROM training_sessions WHERE id = ?")->execute([$id]);

    jsonSuccess(null, 'تم حذف جلسة التدريب');
}

// ============================================================
// ATTENDANCE
// ============================================================

function getAttendance() {
    requireLogin();
    $sessionId = getParam('session_id');
    if (!$sessionId) jsonError('معرّف الجلسة مطلوب');

    $db = getDB();

    // بيانات الجلسة
    $stmt = $db->prepare("SELECT * FROM training_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) jsonError('الجلسة غير موجودة', 404);

    // سجل الحضور مع أسماء الطلاب
    $stmt = $db->prepare("
        SELECT ta.*, s.name AS student_name, s.student_number,
               CONCAT(g.name, ' - ', c.name) AS class_name,
               tm.jersey_number, tm.position, tm.status AS member_status
        FROM training_attendance ta
        JOIN students s ON ta.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        LEFT JOIN team_members tm ON tm.team_id = ? AND tm.student_id = s.id
        WHERE ta.session_id = ?
        ORDER BY tm.jersey_number, s.name
    ");
    $stmt->execute([$session['team_id'], $sessionId]);
    $attendance = $stmt->fetchAll();

    jsonSuccess([
        'session'    => $session,
        'attendance' => $attendance
    ]);
}

function saveAttendance() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    if (empty($data['session_id'])) jsonError('معرّف الجلسة مطلوب');
    if (empty($data['records']))    jsonError('بيانات الحضور مطلوبة');

    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO training_attendance (session_id, student_id, status, performance, notes)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            performance = VALUES(performance),
            notes = VALUES(notes)
    ");

    foreach ($data['records'] as $record) {
        if (empty($record['student_id'])) continue;
        $stmt->execute([
            $data['session_id'],
            $record['student_id'],
            $record['status']      ?? 'present',
            isset($record['performance']) ? (int)$record['performance'] : null,
            $record['notes']       ?? null
        ]);
    }

    jsonSuccess(null, 'تم حفظ الحضور بنجاح');
}

// ============================================================
// PRIVATE HELPERS
// ============================================================

/**
 * إنشاء سجل حضور مبدئي (غياب افتراضي) لجميع أعضاء الفريق
 */
function _initAttendance($sessionId, $teamId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT student_id FROM team_members
        WHERE team_id = ? AND status IN ('active', 'substitute')
    ");
    $stmt->execute([$teamId]);
    $members = $stmt->fetchAll();

    $insert = $db->prepare("
        INSERT IGNORE INTO training_attendance (session_id, student_id, status)
        VALUES (?, ?, 'absent')
    ");
    foreach ($members as $m) {
        $insert->execute([$sessionId, $m['student_id']]);
    }
}
