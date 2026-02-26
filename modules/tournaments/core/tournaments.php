<?php
/**
 * Tournament CRUD Functions
 */

function getTournaments() {
    requireLogin();
    $db = getDB();
    $status = getParam('status');
    
    $sql = "SELECT t.*, COUNT(DISTINCT tt.id) as team_count, COUNT(DISTINCT m.id) as match_count
            FROM tournaments t
            LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
            LEFT JOIN matches m ON m.tournament_id = t.id";
    
    $params = [];
    if ($status) {
        $sql .= " WHERE t.status = ?";
        $params[] = $status;
    }
    $sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function getTournament() {
    requireLogin();
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Get teams
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name, CONCAT(g.name, ' - ', c.name) as full_class_name
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ?
        ORDER BY tt.seed_number, tt.id
    ");
    $stmt->execute([$id]);
    $tournament['teams'] = $stmt->fetchAll();
    
    jsonSuccess($tournament);
}

function createTournament() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['name', 'type']);
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO tournaments (name, description, type, sport_type, start_date, end_date, 
            randomize_teams, auto_generate, points_win, points_draw, points_loss, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['description'] ?? ''),
        $data['type'],
        sanitize($data['sport_type'] ?? 'كرة قدم'),
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        $data['randomize_teams'] ?? 1,
        $data['auto_generate'] ?? 1,
        $data['points_win'] ?? 3,
        $data['points_draw'] ?? 1,
        $data['points_loss'] ?? 0,
        $_SESSION['user_id']
    ]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'تم إنشاء البطولة');
}

function updateTournament() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $id = $data['id'] ?? getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE tournaments SET name=?, description=?, type=?, sport_type=?,
            start_date=?, end_date=?, randomize_teams=?, auto_generate=?
        WHERE id=? AND status IN ('draft','registration')
    ");
    
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['description'] ?? ''),
        $data['type'],
        sanitize($data['sport_type'] ?? 'كرة قدم'),
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        $data['randomize_teams'] ?? 1,
        $data['auto_generate'] ?? 1,
        $id
    ]);
    
    jsonSuccess(null, 'تم تحديث البطولة');
}

function deleteTournament() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $db->prepare("DELETE FROM tournaments WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم حذف البطولة');
}

function startTournament() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Check team count
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = ? AND is_eliminated = 0");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() < 2) jsonError('يجب إضافة فريقين على الأقل');
    
    // Get tournament
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    if ($tournament['status'] === 'in_progress') jsonError('البطولة جارية بالفعل');
    
    // Check existing matches
    $stmt = $db->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = ?");
    $stmt->execute([$id]);
    $existingMatches = (int)$stmt->fetchColumn();
    
    // Generate matches if needed
    if ($tournament['auto_generate'] && $existingMatches === 0) {
        $db->prepare("UPDATE tournament_teams SET is_eliminated = 0 WHERE tournament_id = ?")->execute([$id]);
        generateMatchesForTournament($id, $tournament['type'], $tournament['randomize_teams']);
    }
    
    // Update status
    $db->prepare("UPDATE tournaments SET status = 'in_progress' WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'تم بدء البطولة');
}

function completeTournament() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Find winner
    $stmt = $db->prepare("SELECT type FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $type = $stmt->fetchColumn();
    
    $winnerId = null;
    if (strpos($type, 'elimination') !== false) {
        $stmt = $db->prepare("
            SELECT winner_team_id FROM matches 
            WHERE tournament_id = ? AND status = 'completed'
            ORDER BY round_number DESC LIMIT 1
        ");
        $stmt->execute([$id]);
        $winnerId = $stmt->fetchColumn();
    } else {
        $stmt = $db->prepare("
            SELECT team_id FROM standings WHERE tournament_id = ?
            ORDER BY points DESC, goal_difference DESC LIMIT 1
        ");
        $stmt->execute([$id]);
        $winnerId = $stmt->fetchColumn();
    }
    
    $db->prepare("UPDATE tournaments SET status = 'completed', winner_team_id = ? WHERE id = ?")
       ->execute([$winnerId, $id]);
    
    jsonSuccess(['winner_id' => $winnerId], 'تم إنهاء البطولة');
}

function printTournament() {
    requireLogin();
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Get tournament with teams
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Get teams
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        WHERE tt.tournament_id = ?
        ORDER BY tt.seed_number
    ");
    $stmt->execute([$id]);
    $tournament['teams'] = $stmt->fetchAll();
    
    // Get matches
    $stmt = $db->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE m.tournament_id = ?
        ORDER BY m.round_number, m.match_number
    ");
    $stmt->execute([$id]);
    $matches = $stmt->fetchAll();
    
    // Get standings for league
    $standings = null;
    if (strpos($tournament['type'], 'round_robin') !== false) {
        $stmt = $db->prepare("
            SELECT s.*, tt.team_name
            FROM standings s
            JOIN tournament_teams tt ON s.team_id = tt.id
            WHERE s.tournament_id = ?
            ORDER BY s.points DESC, s.goal_difference DESC
        ");
        $stmt->execute([$id]);
        $standings = $stmt->fetchAll();
    }
    
    jsonSuccess([
        'tournament' => $tournament,
        'matches' => $matches,
        'standings' => $standings
    ]);
}
