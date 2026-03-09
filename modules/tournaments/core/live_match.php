<?php
/**
 * Live Match Panel — لوحة تحكيم الميدان
 * =========================================
 * API actions for real-time match control from mobile devices.
 * All functions are registered in modules/tournaments/api.php
 */

// ============================================================
// GET LIVE MATCH STATE
// Returns match info + both teams + all events + player list
// ============================================================
function getLiveMatchState() {
    requireLogin();
    $matchId = getParam('match_id');
    if (!$matchId) jsonError('معرّف المباراة مطلوب');

    $db = getDB();
    $schoolId = schoolId();

    // Get match with tournament and teams
    $stmt = $db->prepare("
        SELECT m.*,
               t.name as tournament_name, t.sport_type, t.school_id,
               tt1.team_name as team1_name, tt1.team_color as team1_color,
               tt2.team_name as team2_name, tt2.team_color as team2_color
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        LEFT JOIN tournament_teams tt1 ON m.team1_id = tt1.id
        LEFT JOIN tournament_teams tt2 ON m.team2_id = tt2.id
        WHERE m.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$matchId, $schoolId]);
    $match = $stmt->fetch();
    if (!$match) jsonError('المباراة غير موجودة');

    // Get events with student names
    $stmt = $db->prepare("
        SELECT me.*,
               s.name as student_name,
               tt.team_name
        FROM match_events me
        LEFT JOIN students s ON me.student_id = s.id
        LEFT JOIN tournament_teams tt ON me.team_id = tt.id
        WHERE me.match_id = ?
        ORDER BY me.minute ASC, me.created_at ASC
    ");
    $stmt->execute([$matchId]);
    $events = $stmt->fetchAll();

    // Get players for team1
    $team1Players = _getLiveMatchPlayers($db, $match['team1_id'], $match['tournament_id'], $schoolId);
    // Get players for team2
    $team2Players = _getLiveMatchPlayers($db, $match['team2_id'], $match['tournament_id'], $schoolId);

    // Calculate live score from events
    $score1 = 0; $score2 = 0;
    foreach ($events as $ev) {
        if (in_array($ev['event_type'], ['goal', 'penalty'])) {
            if ((int)$ev['team_id'] === (int)$match['team1_id']) $score1++;
            else $score2++;
        } elseif ($ev['event_type'] === 'own_goal') {
            // Own goal counts for the OTHER team
            if ((int)$ev['team_id'] === (int)$match['team1_id']) $score2++;
            else $score1++;
        }
    }

    // Get man of match if set
    $stmt = $db->prepare("
        SELECT tps.student_id, s.name as student_name, tt.team_name
        FROM tournament_player_stats tps
        JOIN students s ON tps.student_id = s.id
        JOIN tournament_teams tt ON tps.team_id = tt.id
        WHERE tps.tournament_id = ? AND tps.man_of_match > 0
          AND tps.team_id IN (?, ?)
        ORDER BY tps.man_of_match DESC
        LIMIT 1
    ");
    $stmt->execute([$match['tournament_id'], $match['team1_id'], $match['team2_id']]);
    $manOfMatch = $stmt->fetch() ?: null;

    jsonSuccess([
        'match'        => $match,
        'events'       => $events,
        'team1_players'=> $team1Players,
        'team2_players'=> $team2Players,
        'live_score'   => ['team1' => $score1, 'team2' => $score2],
        'man_of_match' => $manOfMatch,
    ]);
}

/**
 * Helper: جلب لاعبي الفريق من tournament_player_stats أو student_team_members
 */
function _getLiveMatchPlayers(PDO $db, $teamId, $tournamentId, $schoolId): array {
    if (!$teamId) return [];

    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.name, 
               COALESCE(tps.goals, 0) as goals, 
               COALESCE(tps.yellow_cards, 0) as yellow_cards, 
               COALESCE(tps.red_cards, 0) as red_cards
        FROM students s
        LEFT JOIN tournament_player_stats tps 
               ON tps.student_id = s.id 
              AND tps.tournament_id = ? 
              AND tps.team_id = ?
        WHERE (
            -- Lottery Teams or players with existing stats
            s.id IN (SELECT student_id FROM tournament_player_stats WHERE tournament_id = ? AND team_id = ?)
            OR
            -- Manual Selection 
            s.id IN (
                SELECT stm.student_id FROM student_team_members stm
                JOIN student_teams st ON st.id = stm.student_team_id
                WHERE st.tournament_team_id = ?
            )
            OR
            -- Sports Teams 
            s.id IN (
                SELECT tm.student_id FROM team_members tm
                JOIN tournament_teams tt ON tt.sports_team_id = tm.team_id
                WHERE tt.id = ?
            )
            OR
            -- Entire Class fallback (Only if no specific members selected)
            (
                s.class_id = (SELECT class_id FROM tournament_teams WHERE id = ?)
                AND NOT EXISTS (
                    SELECT 1 FROM student_teams st WHERE st.tournament_team_id = ?
                )
            )
        ) AND s.active = 1 AND s.school_id = ?
        ORDER BY s.name
    ");
    
    $stmt->execute([
        $tournamentId, $teamId, 
        $tournamentId, $teamId, 
        $teamId, 
        $teamId, 
        $teamId, $teamId,
        $schoolId
    ]);
    
    $players = $stmt->fetchAll();
    
    // Absolute fallback in case logic misses something
    if (empty($players)) {
        $stmt = $db->prepare("
            SELECT s.id, s.name, 0 as goals, 0 as yellow_cards, 0 as red_cards
            FROM students s
            JOIN tournament_teams tt ON s.class_id = tt.class_id
            WHERE tt.id = ? AND s.active = 1 AND s.school_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$teamId, $schoolId]);
        return $stmt->fetchAll();
    }
    
    return $players;
}

// ============================================================
// ADD MATCH EVENT
// ============================================================
function addMatchEvent() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    $required = ['match_id', 'team_id', 'event_type'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonError("الحقل $f مطلوب");
    }

    $validTypes = ['goal', 'own_goal', 'penalty', 'yellow_card', 'red_card', 'substitution', 'injury'];
    if (!in_array($data['event_type'], $validTypes)) jsonError('نوع الحدث غير صالح');

    $db       = getDB();
    $schoolId = schoolId();
    $matchId  = (int)$data['match_id'];
    $teamId   = (int)$data['team_id'];

    // Verify match belongs to this school
    $stmt = $db->prepare("
        SELECT m.status, m.team1_id, m.team2_id, m.tournament_id
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE m.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$matchId, $schoolId]);
    $match = $stmt->fetch();
    if (!$match) jsonError('المباراة غير موجودة');
    if ($match['status'] === 'completed') jsonError('المباراة منتهية — لا يمكن إضافة أحداث');
    if (!in_array($teamId, [(int)$match['team1_id'], (int)$match['team2_id']])) {
        jsonError('الفريق لا ينتمي لهذه المباراة');
    }

    // If match is still scheduled, auto-start it
    if ($match['status'] === 'scheduled') {
        $db->prepare("UPDATE matches SET status = 'in_progress' WHERE id = ?")->execute([$matchId]);
    }

    $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
    $minute    = !empty($data['minute'])     ? (int)$data['minute']    : null;
    $notes     = !empty($data['notes'])      ? sanitize($data['notes']): null;

    $stmt = $db->prepare("
        INSERT INTO match_events (match_id, team_id, student_id, event_type, minute, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$matchId, $teamId, $studentId, $data['event_type'], $minute, $notes]);
    $eventId = (int)$db->lastInsertId();

    // Update player stats if student identified
    if ($studentId) {
        _updatePlayerStatFromEvent($db, $match['tournament_id'], $schoolId, $studentId, $teamId, $data['event_type']);
        
        // Check for 2 yellow cards = 1 red card logic
        if ($data['event_type'] === 'yellow_card') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM match_events WHERE match_id = ? AND student_id = ? AND event_type = 'yellow_card'");
            $stmt->execute([$matchId, $studentId]);
            $yellowCount = (int)$stmt->fetchColumn();
            
            if ($yellowCount == 2) {
                // Auto-add red card event
                $stmt = $db->prepare("INSERT INTO match_events (match_id, team_id, student_id, event_type, minute, notes) VALUES (?, ?, ?, 'red_card', ?, 'طرد تلقائي للإنذار الثاني')");
                $stmt->execute([$matchId, $teamId, $studentId, $minute]);
                
                // Update player stats for the new red card
                _updatePlayerStatFromEvent($db, $match['tournament_id'], $schoolId, $studentId, $teamId, 'red_card');
            }
        }
    }

    // Return updated score
    [$score1, $score2] = _calculateLiveScore($db, $matchId, $match['team1_id'], $match['team2_id']);

    jsonSuccess([
        'event_id'  => $eventId,
        'live_score'=> ['team1' => $score1, 'team2' => $score2],
    ], _eventLabel($data['event_type']) . ' تم تسجيله ✅');
}

// ============================================================
// DELETE MATCH EVENT
// ============================================================
function deleteMatchEvent() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $eventId = $data['event_id'] ?? getParam('event_id');
    if (!$eventId) jsonError('معرّف الحدث مطلوب');

    $db = getDB();
    $schoolId = schoolId();

    // Verify ownership via join
    $stmt = $db->prepare("
        SELECT me.*, m.team1_id, m.team2_id, m.tournament_id
        FROM match_events me
        JOIN matches m ON me.match_id = m.id
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE me.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$eventId, $schoolId]);
    $event = $stmt->fetch();
    if (!$event) jsonError('الحدث غير موجود');

    $db->prepare("DELETE FROM match_events WHERE id = ?")->execute([$eventId]);

    // Reverse player stat if student was identified
    if ($event['student_id']) {
        _reversePlayerStatFromEvent($db, $event['tournament_id'], $schoolId, $event['student_id'], $event['team_id'], $event['event_type']);
    }

    [$score1, $score2] = _calculateLiveScore($db, $event['match_id'], $event['team1_id'], $event['team2_id']);

    jsonSuccess([
        'live_score' => ['team1' => $score1, 'team2' => $score2],
    ], 'تم حذف الحدث');
}

// ============================================================
// START LIVE MATCH
// ============================================================
function startLiveMatch() {
    requireRole(['admin', 'teacher']);
    $matchId = getParam('match_id');
    if (!$matchId) jsonError('معرّف المباراة مطلوب');

    $db = getDB();
    $schoolId = schoolId();

    $stmt = $db->prepare("
        SELECT m.status FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE m.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$matchId, $schoolId]);
    $match = $stmt->fetch();
    if (!$match) jsonError('المباراة غير موجودة');
    if ($match['status'] === 'completed') jsonError('المباراة منتهية بالفعل');

    $db->prepare("UPDATE matches SET status = 'in_progress' WHERE id = ?")->execute([$matchId]);
    jsonSuccess(null, 'بدأت المباراة ⚽');
}

// ============================================================
// END LIVE MATCH  (تسجيل النتيجة وإغلاق المباراة)
// ============================================================
function endLiveMatch() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $matchId = (int)($data['match_id'] ?? getParam('match_id'));
    if (!$matchId) jsonError('معرّف المباراة مطلوب');

    $db = getDB();
    $schoolId = schoolId();

    // Get match info
    $stmt = $db->prepare("
        SELECT m.*, t.type as tournament_type, t.points_win, t.points_draw, t.points_loss
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE m.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$matchId, $schoolId]);
    $match = $stmt->fetch();
    if (!$match) jsonError('المباراة غير موجودة');
    if (!$match['team1_id'] || !$match['team2_id']) jsonError('المباراة غير مكتملة - لم يتم تحديد الفريقين');
    if ($match['status'] === 'completed') jsonError('المباراة منتهية بالفعل');

    // Calculate score from events
    [$score1, $score2] = _calculateLiveScore($db, $matchId, $match['team1_id'], $match['team2_id']);

    // Allow manual override if provided
    if (isset($data['team1_score'])) $score1 = (int)$data['team1_score'];
    if (isset($data['team2_score'])) $score2 = (int)$data['team2_score'];

    $isElimination = strpos($match['tournament_type'], 'elimination') !== false;
    if ($isElimination && $score1 === $score2) {
        jsonError('لا يمكن التعادل في نظام خروج المغلوب — يجب تحديد فائز');
    }

    $winnerId = null; $loserId = null;
    if ($score1 > $score2)      { $winnerId = $match['team1_id']; $loserId = $match['team2_id']; }
    elseif ($score2 > $score1)  { $winnerId = $match['team2_id']; $loserId = $match['team1_id']; }

    $db->prepare("
        UPDATE matches SET team1_score = ?, team2_score = ?, winner_team_id = ?,
                           loser_team_id = ?, status = 'completed' WHERE id = ?
    ")->execute([$score1, $score2, $winnerId, $loserId, $matchId]);

    // Handle elimination advancement and standings (reuse existing functions)
    $isMixed = $match['tournament_type'] === 'mixed';
    $useElim = $isElimination || ($isMixed && !$match['group_name']);
    if ($useElim && $winnerId) {
        advanceWinnerToNext($db, $matchId, $winnerId);
        if ($match['tournament_type'] === 'double_elimination' && $loserId) {
            sendLoserToLosersBracket($db, $matchId, $loserId);
        }
    }

    if (!$useElim) {
        // Round robin — update standings
        $pts = [
            'win' => $match['points_win'] ?? 3,
            'draw'=> $match['points_draw'] ?? 1,
            'loss'=> $match['points_loss'] ?? 0,
        ];
        _updateStandingsAfterMatch($db, $match['tournament_id'], $match, $score1, $score2, $pts);
    }

    // Update matches_played in player_stats for participating players
    _markMatchesPlayed($db, $matchId, $match['team1_id'], $match['team2_id'], $match['tournament_id'], $schoolId);

    jsonSuccess([
        'team1_score' => $score1,
        'team2_score' => $score2,
        'winner_id'   => $winnerId,
    ], 'انتهت المباراة ✅');
}

// ============================================================
// PRIVATE HELPERS
// ============================================================

function _calculateLiveScore(PDO $db, int $matchId, $team1Id, $team2Id): array {
    $stmt = $db->prepare("SELECT team_id, event_type FROM match_events WHERE match_id = ?");
    $stmt->execute([$matchId]);
    $events = $stmt->fetchAll();
    $s1 = 0; $s2 = 0;
    foreach ($events as $ev) {
        $tid = (int)$ev['team_id'];
        if (in_array($ev['event_type'], ['goal', 'penalty'])) {
            if ($tid === (int)$team1Id) $s1++; else $s2++;
        } elseif ($ev['event_type'] === 'own_goal') {
            if ($tid === (int)$team1Id) $s2++; else $s1++;
        }
    }
    return [$s1, $s2];
}

function _updatePlayerStatFromEvent(PDO $db, $tournamentId, $schoolId, $studentId, $teamId, $type): void {
    $map = [
        'goal'        => 'goals',
        'penalty'     => 'goals',
        'own_goal'    => 'own_goals',
        'yellow_card' => 'yellow_cards',
        'red_card'    => 'red_cards',
        'man_of_match' => 'man_of_match',
    ];
    if (!isset($map[$type])) return;
    $col = $map[$type];

    $db->prepare("
        INSERT INTO tournament_player_stats (tournament_id, school_id, student_id, team_id, `$col`)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE `$col` = `$col` + 1
    ")->execute([$tournamentId, $schoolId, $studentId, $teamId]);
}

function _reversePlayerStatFromEvent(PDO $db, $tournamentId, $schoolId, $studentId, $teamId, $type): void {
    $map = [
        'goal'        => 'goals',
        'penalty'     => 'goals',
        'own_goal'    => 'own_goals',
        'yellow_card' => 'yellow_cards',
        'red_card'    => 'red_cards',
        'man_of_match' => 'man_of_match',
    ];
    if (!isset($map[$type])) return;
    $col = $map[$type];
    $db->prepare("
        UPDATE tournament_player_stats SET `$col` = GREATEST(0, `$col` - 1)
        WHERE tournament_id = ? AND school_id = ? AND student_id = ? AND team_id = ?
    ")->execute([$tournamentId, $schoolId, $studentId, $teamId]);
}

function _updateStandingsAfterMatch(PDO $db, $tournamentId, $match, $s1, $s2, $pts): void {
    $team1Id = $match['team1_id'];
    $team2Id = $match['team2_id'];

    $p1w = $p1d = $p1l = $p1pts = 0;
    $p2w = $p2d = $p2l = $p2pts = 0;
    $gd1 = $s1 - $s2;
    $gd2 = $s2 - $s1;

    if ($s1 > $s2)      { $p1w = 1; $p1pts = $pts['win'];  $p2l = 1; $p2pts = $pts['loss']; }
    elseif ($s2 > $s1)  { $p2w = 1; $p2pts = $pts['win'];  $p1l = 1; $p1pts = $pts['loss']; }
    else                { $p1d = $p2d = 1; $p1pts = $p2pts = $pts['draw']; }

    $groupName = $match['group_name'] ?? null;

    $upsert = $db->prepare("
        INSERT INTO standings (tournament_id, team_id, group_name, played, wins, draws, losses,
                               goals_for, goals_against, goal_difference, points)
        VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            played = played + 1, wins = wins + VALUES(wins), draws = draws + VALUES(draws),
            losses = losses + VALUES(losses), goals_for = goals_for + VALUES(goals_for),
            goals_against = goals_against + VALUES(goals_against),
            goal_difference = goal_difference + VALUES(goal_difference),
            points = points + VALUES(points)
    ");
    $upsert->execute([$tournamentId, $team1Id, $groupName, $p1w, $p1d, $p1l, $s1, $s2, $gd1, $p1pts]);
    $upsert->execute([$tournamentId, $team2Id, $groupName, $p2w, $p2d, $p2l, $s2, $s1, $gd2, $p2pts]);
}

function _markMatchesPlayed(PDO $db, $matchId, $team1Id, $team2Id, $tournamentId, $schoolId): void {
    foreach ([$team1Id, $team2Id] as $tid) {
        $db->prepare("
            UPDATE tournament_player_stats
            SET matches_played = matches_played + 1
            WHERE tournament_id = ? AND team_id = ? AND school_id = ?
        ")->execute([$tournamentId, $tid, $schoolId]);
    }
}

function _eventLabel(string $type): string {
    $labels = [
        'goal'        => '⚽ هدف',
        'own_goal'    => '🙈 هدف عكسي',
        'penalty'     => '🎯 ركلة جزاء',
        'yellow_card' => '🟨 بطاقة صفراء',
        'red_card'    => '🟥 بطاقة حمراء',
        'substitution'=> '🔄 تبديل',
        'injury'      => '🚑 إصابة',
    ];
    return $labels[$type] ?? $type;
}
