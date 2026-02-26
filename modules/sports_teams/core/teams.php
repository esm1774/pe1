<?php
/**
 * Sports Teams - Teams & Members Management
 * إدارة الفرق وأعضائها
 */

// ============================================================
// TEAMS CRUD
// ============================================================

function listTeams() {
    requireLogin();
    $db = getDB();

    $sport    = getParam('sport_type', '');
    $type     = getParam('team_type', '');
    $classId  = getParam('class_id', '');

    $where  = ['1=1'];
    $params = [];

    if ($sport)   { $where[] = 'st.sport_type = ?';  $params[] = $sport; }
    if ($type)    { $where[] = 'st.team_type = ?';   $params[] = $type; }
    if ($classId) { $where[] = 'st.class_id = ?';    $params[] = $classId; }

    $sql = "
        SELECT st.*,
               CONCAT(g.name, ' - ', c.name) AS class_name,
               u.name AS coach_name,
               COUNT(DISTINCT tm.id) AS member_count
        FROM sports_teams st
        LEFT JOIN classes c ON st.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        LEFT JOIN users   u ON st.coach_id = u.id
        LEFT JOIN team_members tm ON tm.team_id = st.id AND tm.status = 'active'
        WHERE " . implode(' AND ', $where) . "
        GROUP BY st.id
        ORDER BY st.sport_type, st.team_type, st.name
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function getTeam() {
    requireLogin();
    $id = getParam('id');
    if (!$id) jsonError('معرّف الفريق مطلوب');

    $db = getDB();

    // بيانات الفريق
    $stmt = $db->prepare("
        SELECT st.*,
               CONCAT(g.name, ' - ', c.name) AS class_name,
               u.name AS coach_name
        FROM sports_teams st
        LEFT JOIN classes c ON st.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        LEFT JOIN users   u ON st.coach_id = u.id
        WHERE st.id = ?
    ");
    $stmt->execute([$id]);
    $team = $stmt->fetch();
    if (!$team) jsonError('الفريق غير موجود', 404);

    // الأعضاء
    $stmt = $db->prepare("
        SELECT tm.*, s.name AS student_name, s.student_number,
               CONCAT(g.name, ' - ', c.name) AS class_name
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id = g.id
        WHERE tm.team_id = ?
        ORDER BY tm.status, tm.jersey_number, s.name
    ");
    $stmt->execute([$id]);
    $team['members'] = $stmt->fetchAll();

    // إحصائيات التدريب
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_sessions,
               MAX(session_date) AS last_session
        FROM training_sessions WHERE team_id = ?
    ");
    $stmt->execute([$id]);
    $team['training_stats'] = $stmt->fetch();

    jsonSuccess($team);
}

function createTeam() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    if (empty($data['name']))       jsonError('اسم الفريق مطلوب');
    if (empty($data['sport_type'])) jsonError('نوع الرياضة مطلوب');
    if (empty($data['team_type']))  jsonError('نوع الفريق مطلوب');

    $db = getDB();

    // لو كان منتخب فصل، تحقق من class_id
    if ($data['team_type'] === 'class' && empty($data['class_id'])) {
        jsonError('يجب تحديد الفصل لمنتخب الفصل');
    }

    // تحقق من عدم التكرار
    $exists = $db->prepare("
        SELECT id FROM sports_teams
        WHERE name = ? AND sport_type = ?
    ");
    $exists->execute([$data['name'], $data['sport_type']]);
    if ($exists->fetch()) jsonError('يوجد فريق بنفس الاسم والرياضة مسبقاً');

    $stmt = $db->prepare("
        INSERT INTO sports_teams (name, sport_type, team_type, class_id, coach_id, color, logo_emoji, description, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['name'],
        $data['sport_type'],
        $data['team_type'],
        $data['class_id']    ?? null,
        $data['coach_id']    ?? null,
        $data['color']       ?? '#10b981',
        $data['logo_emoji']  ?? '⚽',
        $data['description'] ?? null,
        $_SESSION['user_id'] ?? null
    ]);

    $teamId = $db->lastInsertId();

    // لو كان منتخب فصل، أضف طلاب الفصل تلقائياً
    if ($data['team_type'] === 'class' && !empty($data['class_id'])) {
        _autoAddClassStudents($teamId, $data['class_id']);
    }

    logActivity('create', 'sports_team', $teamId, $data['name']);
    jsonSuccess(['id' => $teamId], 'تم إنشاء الفريق بنجاح');
}

function updateTeam() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    if (empty($data['id']))   jsonError('معرّف الفريق مطلوب');
    if (empty($data['name'])) jsonError('اسم الفريق مطلوب');

    $db = getDB();
    $db->prepare("
        UPDATE sports_teams
        SET name = ?, sport_type = ?, coach_id = ?, color = ?,
            logo_emoji = ?, description = ?, is_active = ?
        WHERE id = ?
    ")->execute([
        $data['name'],
        $data['sport_type']  ?? 'كرة قدم',
        $data['coach_id']    ?? null,
        $data['color']       ?? '#10b981',
        $data['logo_emoji']  ?? '⚽',
        $data['description'] ?? null,
        isset($data['is_active']) ? (int)$data['is_active'] : 1,
        $data['id']
    ]);

    logActivity('update', 'sports_team', $data['id'], $data['name']);
    jsonSuccess(null, 'تم تحديث بيانات الفريق');
}

function deleteTeam() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف الفريق مطلوب');

    $db = getDB();
    $db->prepare("DELETE FROM sports_teams WHERE id = ?")->execute([$id]);

    logActivity('delete', 'sports_team', $id);
    jsonSuccess(null, 'تم حذف الفريق بنجاح');
}

// ============================================================
// MEMBERS MANAGEMENT
// ============================================================

function listMembers() {
    requireLogin();
    $teamId = getParam('team_id');
    if (!$teamId) jsonError('معرّف الفريق مطلوب');

    $db = getDB();
    $stmt = $db->prepare("
        SELECT tm.*, s.name AS student_name, s.student_number,
               CONCAT(g.name, ' - ', c.name) AS class_name
        FROM team_members tm
        JOIN students s ON tm.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id = g.id
        WHERE tm.team_id = ?
        ORDER BY tm.status, tm.jersey_number, s.name
    ");
    $stmt->execute([$teamId]);
    jsonSuccess($stmt->fetchAll());
}

function addMember() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    if (empty($data['team_id']))    jsonError('معرّف الفريق مطلوب');
    if (empty($data['student_id'])) jsonError('معرّف الطالب مطلوب');

    $db = getDB();

    // هل الطالب عضو مسبقاً؟
    $exists = $db->prepare("SELECT id FROM team_members WHERE team_id = ? AND student_id = ?");
    $exists->execute([$data['team_id'], $data['student_id']]);
    if ($exists->fetch()) jsonError('الطالب عضو في هذا الفريق مسبقاً');

    $stmt = $db->prepare("
        INSERT INTO team_members (team_id, student_id, jersey_number, position, status, joined_at, notes)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
    ");
    $stmt->execute([
        $data['team_id'],
        $data['student_id'],
        $data['jersey_number'] ?? null,
        $data['position']      ?? null,
        $data['status']        ?? 'active',
        $data['notes']         ?? null
    ]);

    logActivity('add_member', 'sports_team', $data['team_id']);
    jsonSuccess(['id' => $db->lastInsertId()], 'تم إضافة اللاعب بنجاح');
}

function updateMember() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    if (empty($data['id'])) jsonError('معرّف العضوية مطلوب');

    $db = getDB();
    $db->prepare("
        UPDATE team_members
        SET jersey_number = ?, position = ?, status = ?, notes = ?
        WHERE id = ?
    ")->execute([
        $data['jersey_number'] ?? null,
        $data['position']      ?? null,
        $data['status']        ?? 'active',
        $data['notes']         ?? null,
        $data['id']
    ]);

    jsonSuccess(null, 'تم تحديث بيانات اللاعب');
}

function removeMember() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف العضوية مطلوب');

    $db = getDB();
    $db->prepare("DELETE FROM team_members WHERE id = ?")->execute([$id]);

    jsonSuccess(null, 'تم إزالة اللاعب من الفريق');
}

// ============================================================
// HELPERS
// ============================================================

function availableClassesST() {
    requireLogin();
    $db = getDB();
    $stmt = $db->query("
        SELECT c.id, c.name, g.name as grade_name,
               CONCAT(g.name, ' - ', c.name) as full_name,
               COUNT(s.id) as student_count
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        WHERE c.active = 1
        GROUP BY c.id
        ORDER BY g.name, c.name
    ");
    jsonSuccess($stmt->fetchAll());
}

function availableStudentsST() {
    requireLogin();
    $teamId  = getParam('team_id', '');
    $classId = getParam('class_id', '');
    $search  = getParam('search', '');

    $db = getDB();

    $where  = ['s.active = 1'];
    $params = [];

    if ($classId) { $where[] = 's.class_id = ?'; $params[] = $classId; }
    if ($search)  { $where[] = 's.name LIKE ?';  $params[] = "%$search%"; }

    // استثناء الطلاب المنضمين فعلاً لهذا الفريق
    if ($teamId) {
        $where[] = 's.id NOT IN (SELECT student_id FROM team_members WHERE team_id = ?)';
        $params[] = $teamId;
    }

    $stmt = $db->prepare("
        SELECT s.id, s.name, s.student_number,
               CONCAT(g.name, ' - ', c.name) as class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.name
        LIMIT 100
    ");
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function getTeamStats() {
    requireLogin();
    $teamId = getParam('team_id');
    if (!$teamId) jsonError('معرّف الفريق مطلوب');

    $db = getDB();

    // إحصائيات عامة
    $stmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM team_members   WHERE team_id = ? AND status = 'active')     AS active_members,
            (SELECT COUNT(*) FROM team_members   WHERE team_id = ? AND status = 'substitute') AS substitutes,
            (SELECT COUNT(*) FROM training_sessions WHERE team_id = ?)                         AS total_trainings,
            (SELECT COUNT(*) FROM training_sessions WHERE team_id = ? AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS trainings_this_month
    ");
    $stmt->execute([$teamId, $teamId, $teamId, $teamId]);
    $stats = $stmt->fetch();

    // متوسط الحضور لآخر 5 تدريبات
    $stmt = $db->prepare("
        SELECT AVG(present_count / total_count * 100) AS avg_attendance
        FROM (
            SELECT ts.id,
                   SUM(ta.status = 'present') AS present_count,
                   COUNT(ta.id) AS total_count
            FROM training_sessions ts
            LEFT JOIN training_attendance ta ON ta.session_id = ts.id
            WHERE ts.team_id = ?
            GROUP BY ts.id
            ORDER BY ts.session_date DESC
            LIMIT 5
        ) t
    ");
    $stmt->execute([$teamId]);
    $attendance = $stmt->fetch();
    $stats['avg_attendance'] = round($attendance['avg_attendance'] ?? 0, 1);

    jsonSuccess($stats);
}

// ============================================================
// PRIVATE HELPERS
// ============================================================

function _autoAddClassStudents($teamId, $classId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM students WHERE class_id = ? AND active = 1");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    $insert = $db->prepare("
        INSERT IGNORE INTO team_members (team_id, student_id, joined_at)
        VALUES (?, ?, CURDATE())
    ");
    foreach ($students as $s) {
        $insert->execute([$teamId, $s['id']]);
    }
}
