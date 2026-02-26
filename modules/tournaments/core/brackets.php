<?php
/**
 * Tournament Bracket Functions
 */

function getBracket() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Get tournament
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Get all matches with team info
    $stmt = $db->prepare("
        SELECT m.*,
               t1.team_name as team1_name, t1.team_color as team1_color, t1.seed_number as team1_seed,
               t2.team_name as team2_name, t2.team_color as team2_color, t2.seed_number as team2_seed,
               w.team_name as winner_name
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        LEFT JOIN tournament_teams w ON m.winner_team_id = w.id
        WHERE m.tournament_id = ?
        ORDER BY m.bracket_type, m.round_number, m.match_number
    ");
    $stmt->execute([$tournamentId]);
    $matches = $stmt->fetchAll();
    
    // Organize by bracket type and round
    $bracket = ['main' => [], 'losers' => [], 'final' => []];
    
    foreach ($matches as $match) {
        $type = $match['bracket_type'];
        $round = $match['round_number'];
        
        if (!isset($bracket[$type][$round])) {
            $bracket[$type][$round] = [];
        }
        $bracket[$type][$round][] = $match;
    }
    
    jsonSuccess([
        'tournament' => $tournament,
        'bracket' => $bracket
    ]);
}
