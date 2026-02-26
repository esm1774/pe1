<?php
/**
 * Tournament Teams Functions
 */

function getTeams() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name, CONCAT(g.name, ' - ', c.name) as full_class_name
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ?
        ORDER BY tt.seed_number, tt.id
    ");
    $stmt->execute([$tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

function addTeam() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['tournament_id', 'team_name']);
    
    $db = getDB();
    
    // Check tournament status
    $stmt = $db->prepare("SELECT status FROM tournaments WHERE id = ?");
    $stmt->execute([$data['tournament_id']]);
    $status = $stmt->fetchColumn();
    if (!in_array($status, ['draft', 'registration'])) {
        jsonError('لا يمكن إضافة فرق لبطولة قيد التنفيذ');
    }
    
    // Get next seed
    $stmt = $db->prepare("SELECT COALESCE(MAX(seed_number), 0) + 1 FROM tournament_teams WHERE tournament_id = ?");
    $stmt->execute([$data['tournament_id']]);
    $seed = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        INSERT INTO tournament_teams (tournament_id, class_id, team_name, team_color, seed_number)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$data['tournament_id'],
        $data['class_id'] ?? null,
        sanitize($data['team_name']),
        $data['team_color'] ?? '#10b981',
        $seed
    ]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'تم إضافة الفريق');
}

function removeTeam() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف الفريق مطلوب');
    
    $db = getDB();
    
    // Check tournament status
    $stmt = $db->prepare("
        SELECT t.status FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.id
        WHERE tt.id = ?
    ");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();
    if (!in_array($status, ['draft', 'registration'])) {
        jsonError('لا يمكن حذف فريق من بطولة قيد التنفيذ');
    }
    
    $db->prepare("DELETE FROM tournament_teams WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم حذف الفريق');
}

function addClassesAsTeams() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    if (empty($data['tournament_id'])) jsonError('معرّف البطولة مطلوب');
    if (empty($data['class_ids']) || !is_array($data['class_ids'])) {
        jsonError('يجب اختيار فصل واحد على الأقل');
    }
    
    $db = getDB();
    $tournamentId = (int)$data['tournament_id'];
    
    // Check tournament status
    $stmt = $db->prepare("SELECT status FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $status = $stmt->fetchColumn();
    if (!$status) jsonError('البطولة غير موجودة');
    if (!in_array($status, ['draft', 'registration'])) {
        jsonError('لا يمكن إضافة فرق لبطولة قيد التنفيذ');
    }
    
    // Get existing classes
    $stmt = $db->prepare("SELECT class_id FROM tournament_teams WHERE tournament_id = ? AND class_id IS NOT NULL");
    $stmt->execute([$tournamentId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    $added = 0;
    
    foreach ($data['class_ids'] as $classId) {
        $classId = (int)$classId;
        if (in_array($classId, $existing)) continue;
        
        // Get class name
        $stmt = $db->prepare("
            SELECT CONCAT(g.name, ' - ', c.name) as full_name
            FROM classes c JOIN grades g ON c.grade_id = g.id
            WHERE c.id = ? AND c.active = 1
        ");
        $stmt->execute([$classId]);
        $class = $stmt->fetch();
        if (!$class) continue;
        
        // Get next seed
        $stmt = $db->prepare("SELECT COALESCE(MAX(seed_number), 0) + 1 FROM tournament_teams WHERE tournament_id = ?");
        $stmt->execute([$tournamentId]);
        $seed = $stmt->fetchColumn();
        
        $color = $colors[$added % count($colors)];
        
        $stmt = $db->prepare("
            INSERT INTO tournament_teams (tournament_id, class_id, team_name, team_color, seed_number)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tournamentId, $classId, $class['full_name'], $color, $seed]);
        $added++;
    }
    
    if ($added === 0) jsonError('جميع الفصول المختارة مضافة بالفعل');
    jsonSuccess(['added' => $added], "تم إضافة $added فصل");
}

function getAvailableClasses() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    
    $db = getDB();
    
    $sql = "
        SELECT c.id, c.name, g.name as grade_name,
               CONCAT(g.name, ' - ', c.name) as full_name,
               COUNT(s.id) as student_count
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
        WHERE c.active = 1
    ";
    
    $params = [];
    if ($tournamentId) {
        $sql .= " AND c.id NOT IN (
            SELECT COALESCE(class_id, 0) FROM tournament_teams 
            WHERE tournament_id = ? AND class_id IS NOT NULL
        )";
        $params[] = $tournamentId;
    }
    
    $sql .= " GROUP BY c.id ORDER BY g.sort_order, c.section";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}
