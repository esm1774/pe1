<?php
/**
 * Sports Teams - Lottery Engine
 * محرك القرعة: تقسيم الطلاب إلى فرق عشوائية أو متساوية
 */

/**
 * معاينة القرعة (بدون حفظ)
 * Inputs: student_ids[], team_count OR players_per_team, sport_type, team_names[]?
 */
function lotteryPreview() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    $studentIds    = $data['student_ids'] ?? [];
    $teamCount     = isset($data['team_count'])        ? (int)$data['team_count']        : 0;
    $playersPerTeam= isset($data['players_per_team']) ? (int)$data['players_per_team']  : 0;

    if (empty($studentIds)) jsonError('اختر طلاباً للقرعة');

    // تحديد عدد الفرق
    if ($teamCount < 2 && $playersPerTeam < 2) {
        jsonError('حدد عدد الفرق أو عدد اللاعبين في كل فريق');
    }
    if ($playersPerTeam >= 2 && $teamCount < 2) {
        $teamCount = (int)ceil(count($studentIds) / $playersPerTeam);
    }
    if ($teamCount < 2) jsonError('يجب أن يكون عدد الفرق 2 على الأقل');
    if ($teamCount > count($studentIds)) {
        jsonError('عدد الفرق أكبر من عدد الطلاب');
    }

    // جلب أسماء الطلاب
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.student_number,
               CONCAT(g.name, ' - ', c.name) AS class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        WHERE s.id IN ($placeholders) AND s.active = 1
        ORDER BY s.name
    ");
    $stmt->execute($studentIds);
    $students = $stmt->fetchAll();

    if (empty($students)) jsonError('لم يُعثر على طلاب بالمعرفات المحددة');

    // --- خوارزمية القرعة المتوازنة ---
    shuffle($students); // خلط عشوائي

    $groups = array_fill(0, $teamCount, []);
    foreach ($students as $i => $student) {
        $groups[$i % $teamCount][] = $student;
    }

    // توليد أسماء الفرق
    $defaultNames = _generateTeamNames($teamCount, $data['sport_type'] ?? '');
    $customNames  = $data['team_names'] ?? [];

    $result = [];
    foreach ($groups as $i => $members) {
        $result[] = [
            'team_index' => $i + 1,
            'name'       => $customNames[$i] ?? $defaultNames[$i],
            'members'    => $members,
            'count'      => count($members)
        ];
    }

    jsonSuccess([
        'teams'        => $result,
        'total_teams'  => $teamCount,
        'total_players'=> count($students),
        'sport_type'   => $data['sport_type'] ?? 'كرة قدم'
    ]);
}

/**
 * تأكيد القرعة وحفظ الفرق في قاعدة البيانات
 */
function lotteryConfirm() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    $teams      = $data['teams'] ?? [];         // [{ name, sport_type, members:[{id}] }]
    $sportType  = $data['sport_type'] ?? 'كرة قدم';
    $color      = $data['color']      ?? null;

    if (empty($teams)) jsonError('لا توجد فرق لحفظها');

    $db = getDB();
    $colors = ['#ef4444','#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1'];
    $created = [];

    foreach ($teams as $i => $team) {
        if (empty($team['name']))    jsonError("اسم الفريق رقم " . ($i+1) . " مطلوب");
        if (empty($team['members'])) continue; // فريق فارغ نتجاوزه

        $teamColor = $color ?? $colors[$i % count($colors)];

        // إنشاء الفريق
        $stmt = $db->prepare("
            INSERT INTO sports_teams (school_id, name, sport_type, team_type, color, created_by)
            VALUES (?, ?, ?, 'mixed', ?, ?)
        ");
        $stmt->execute([
            schoolId(),
            $team['name'],
            $sportType,
            $teamColor,
            $_SESSION['user_id'] ?? null
        ]);
        $teamId = $db->lastInsertId();

        // إضافة الأعضاء
        $insertMember = $db->prepare("
            INSERT IGNORE INTO team_members (team_id, student_id, joined_at)
            VALUES (?, ?, CURDATE())
        ");
        foreach ($team['members'] as $member) {
            $studentId = is_array($member) ? ($member['id'] ?? null) : $member;
            if ($studentId) $insertMember->execute([$teamId, $studentId]);
        }

        $created[] = ['id' => $teamId, 'name' => $team['name']];
        logActivity('lottery_create', 'sports_team', $teamId, $team['name']);
    }

    jsonSuccess([
        'created_teams' => $created,
        'count'         => count($created)
    ], 'تم حفظ ' . count($created) . ' فرق بنجاح');
}

/**
 * الطلاب المتاحون للقرعة (مع فلترة)
 */
function lotteryAvailableStudents() {
    requireRole(['admin', 'teacher']);

    $classIds   = getParam('class_ids', '');   // يمكن إرسالها مفصولة بفاصلة
    $gradeId    = getParam('grade_id', '');
    $excludeTeam= getParam('exclude_team_id', '');

    $db = getDB();
    $where  = ['s.active = 1'];
    $params = [];

    if ($gradeId) {
        $where[]  = 'c.grade_id = ?';
        $params[] = $gradeId;
    }

    if ($classIds) {
        $ids = array_filter(array_map('intval', explode(',', $classIds)));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[]  = "s.class_id IN ($placeholders)";
            $params   = array_merge($params, $ids);
        }
    }

    if ($excludeTeam) {
        $where[]  = 's.id NOT IN (SELECT student_id FROM team_members WHERE team_id = ?)';
        $params[] = $excludeTeam;
    }

    $stmt = $db->prepare("
        SELECT s.id, s.name, s.student_number,
               CONCAT(g.name, ' - ', c.name) AS class_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades  g ON c.grade_id  = g.id
        WHERE " . implode(' AND ', $where) . " AND s.school_id = ?
        ORDER BY s.name
    ");
    $stmt->execute(array_merge($params, [schoolId()]));
    jsonSuccess($stmt->fetchAll());
}

// ============================================================
// PRIVATE HELPERS
// ============================================================

function _generateTeamNames($count, $sportType) {
    $base = [
        'النمور', 'الأسود', 'النسور', 'الصقور', 'الذئاب',
        'الأبطال', 'الفيالق', 'الدروع', 'البراكين', 'الخضر',
        'السيوف', 'العقارب', 'الفهود', 'الثيران', 'الدراجون'
    ];
    shuffle($base);
    $names = [];
    for ($i = 0; $i < $count; $i++) {
        $names[] = ($i < count($base))
            ? 'فريق ' . $base[$i]
            : 'فريق ' . ($i + 1);
    }
    return $names;
}
