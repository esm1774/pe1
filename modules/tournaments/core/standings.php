<?php
/**
 * Tournament Standings Functions
 */

function getStandings() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.*, tt.team_name, tt.team_color, c.name as class_name
        FROM standings s
        JOIN tournament_teams tt ON s.team_id = tt.id
        LEFT JOIN classes c ON tt.class_id = c.id
        WHERE s.tournament_id = ?
        ORDER BY COALESCE(s.group_name, '') ASC, s.points DESC, s.goal_difference DESC, s.goals_for DESC
    ");
    $stmt->execute([$tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

function recalculateStandings() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Get tournament settings
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Reset standings
    $db->prepare("
        UPDATE standings SET
            played = 0, wins = 0, draws = 0, losses = 0,
            goals_for = 0, goals_against = 0, goal_difference = 0, points = 0
        WHERE tournament_id = ?
    ")->execute([$tournamentId]);
    
    // Get completed matches
    $stmt = $db->prepare("
        SELECT * FROM matches
        WHERE tournament_id = ? AND status = 'completed'
        AND team1_id IS NOT NULL AND team2_id IS NOT NULL
    ");
    $stmt->execute([$tournamentId]);
    $matches = $stmt->fetchAll();
    
    // Recalculate from matches
    foreach ($matches as $match) {
        if ($match['team1_score'] !== null && $match['team2_score'] !== null) {
            $match['points_win'] = $tournament['points_win'];
            $match['points_draw'] = $tournament['points_draw'];
            $match['points_loss'] = $tournament['points_loss'];
            updateStandingsForMatch($match, $match['team1_score'], $match['team2_score']);
        }
    }
    
    jsonSuccess(null, 'تم إعادة حساب الترتيب');
}
