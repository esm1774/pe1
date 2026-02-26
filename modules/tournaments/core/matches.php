<?php
/**
 * Tournament Matches Functions
 * ============================
 * Single Elimination + Double Elimination + Round Robin
 */

function getMatches() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT m.*,
               t1.team_name as team1_name, t1.team_color as team1_color,
               t2.team_name as team2_name, t2.team_color as team2_color,
               w.team_name as winner_name
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        LEFT JOIN tournament_teams w ON m.winner_team_id = w.id
        WHERE m.tournament_id = ?
        ORDER BY m.bracket_type, m.round_number, m.match_number
    ");
    $stmt->execute([$tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

function generateMatches() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Delete existing matches
    $db->prepare("DELETE FROM matches WHERE tournament_id = ?")->execute([$tournamentId]);
    try { $db->prepare("DELETE FROM standings WHERE tournament_id = ?")->execute([$tournamentId]); } catch(Exception $e) {}
    
    // Reset teams
    $db->prepare("UPDATE tournament_teams SET is_eliminated = 0, elimination_count = 0 WHERE tournament_id = ?")->execute([$tournamentId]);
    
    generateMatchesForTournament($tournamentId, $tournament['type'], $tournament['randomize_teams']);
    jsonSuccess(null, 'تم توليد المباريات');
}

function generateMatchesForTournament($tournamentId, $type, $randomize) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournament_teams WHERE tournament_id = ? ORDER BY seed_number");
    $stmt->execute([$tournamentId]);
    $teams = $stmt->fetchAll();
    
    if (count($teams) < 2) throw new Exception('يجب إضافة فريقين على الأقل');
    
    if ($randomize) {
        shuffle($teams);
        foreach ($teams as $i => $team) {
            $db->prepare("UPDATE tournament_teams SET seed_number = ? WHERE id = ?")
               ->execute([$i + 1, $team['id']]);
        }
    }
    
    switch ($type) {
        case 'single_elimination':
            generateSingleElimination($tournamentId, $teams);
            break;
        case 'double_elimination':
            generateDoubleElimination($tournamentId, $teams);
            break;
        case 'round_robin_single':
            generateRoundRobin($tournamentId, $teams, false);
            break;
        case 'round_robin_double':
            generateRoundRobin($tournamentId, $teams, true);
            break;
        case 'mixed':
            generateMixedTournament($tournamentId, $teams);
            break;
    }
}

// ============================================================
// MIXED TOURNAMENT - نظام المجموعات + خروج المغلوب
// ============================================================
function generateMixedTournament($tournamentId, $teams) {
    $db = getDB();
    $n = count($teams);
    
    // Determine number of groups. Aim for ~4 teams per group.
    $groupCount = max(2, (int)ceil($n / 4));
    $groupNames = range('A', 'Z');
    
    // Assign teams to groups
    $groups = [];
    for ($i = 0; $i < $n; $i++) {
        $groupIndex = $i % $groupCount;
        $groupName = $groupNames[$groupIndex];
        
        $groups[$groupName][] = $teams[$i];
        
        // Update database with group name
        $db->prepare("UPDATE tournament_teams SET group_name = ? WHERE id = ?")
           ->execute([$groupName, $teams[$i]['id']]);
    }
    
    // Generate Round Robin for each group
    $matchNumber = 1;
    foreach ($groups as $groupName => $groupTeams) {
        generateGroupRoundRobin($tournamentId, $groupTeams, $groupName, $matchNumber);
    }
}

function generateGroupRoundRobin($tournamentId, $teams, $groupName, &$matchNumber) {
    $db = getDB();
    $n = count($teams);
    
    $realTeams = $teams;
    if ($n % 2 !== 0) {
        $realTeams[] = ['id' => null, 'team_name' => 'BYE'];
        $n++;
    }
    
    foreach ($realTeams as $team) {
        if ($team['id']) {
            try {
                $db->prepare("INSERT INTO standings (tournament_id, team_id, group_name) VALUES (?, ?, ?)")
                   ->execute([$tournamentId, $team['id'], $groupName]);
            } catch (Exception $e) {}
        }
    }
    
    $rounds = $n - 1;
    $fixed = $realTeams[0];
    $rotating = array_slice($realTeams, 1);
    
    for ($round = 1; $round <= $rounds; $round++) {
        $pairings = [[$fixed, $rotating[0]]];
        $halfSize = (int)(($n - 1) / 2);
        for ($i = 1; $i <= $halfSize; $i++) {
            $home = $rotating[$i] ?? null;
            $away = $rotating[$n - 1 - $i] ?? null;
            if ($home && $away) $pairings[] = [$home, $away];
        }
        
        foreach ($pairings as $pair) {
            $team1 = $pair[0];
            $team2 = $pair[1];
            if (!$team1['id'] || !$team2['id']) continue;
            
            $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, team1_id, team2_id, group_name, status)
                VALUES (?, ?, ?, 'main', ?, ?, ?, 'scheduled')
            ")->execute([$tournamentId, $round, $matchNumber, $team1['id'], $team2['id'], $groupName]);
            $matchNumber++;
        }
        
        $last = array_pop($rotating);
        array_unshift($rotating, $last);
    }
}

function generateMixedKnockoutStage($tournamentId) {
    $db = getDB();
    
    // Get group rankings
    $stmt = $db->prepare("
        SELECT s.*, tt.team_name, tt.group_name 
        FROM standings s
        JOIN tournament_teams tt ON s.team_id = tt.id
        WHERE s.tournament_id = ?
        ORDER BY s.group_name ASC, s.points DESC, s.goal_difference DESC, s.goals_for DESC
    ");
    $stmt->execute([$tournamentId]);
    $allStandings = $stmt->fetchAll();
    
    $groups = [];
    foreach ($allStandings as $row) {
        $groups[$row['group_name']][] = $row;
    }
    
    $qualifiedTeams = [];
    foreach ($groups as $groupName => $teams) {
        $qualifiedTeams[$groupName] = [
            '1st' => $teams[0]['team_id'],
            '2nd' => $teams[1]['team_id'] ?? null
        ];
    }
    
    $pairings = [];
    $groupNames = array_keys($qualifiedTeams);
    $count = count($groupNames);
    
    for ($i = 0; $i < $count; $i += 2) {
        if ($i + 1 < $count) {
            $g1 = $groupNames[$i];
            $g2 = $groupNames[$i + 1];
            $pairings[] = [$qualifiedTeams[$g1]['1st'], $qualifiedTeams[$g2]['2nd']];
            $pairings[] = [$qualifiedTeams[$g2]['1st'], $qualifiedTeams[$g1]['2nd']];
        } else {
            $g1 = $groupNames[$i];
            $pairings[] = [$qualifiedTeams[$g1]['1st'], $qualifiedTeams[$g1]['2nd']];
        }
    }
    
    $knockoutTeamsData = [];
    foreach ($pairings as $pair) {
        foreach ($pair as $teamId) {
            if ($teamId) {
                $stmt = $db->prepare("SELECT * FROM tournament_teams WHERE id = ?");
                $stmt->execute([$teamId]);
                $knockoutTeamsData[] = $stmt->fetch();
            }
        }
    }
    
    $stmt = $db->prepare("SELECT COALESCE(MAX(round_number), 0) FROM matches WHERE tournament_id = ? AND group_name IS NOT NULL");
    $stmt->execute([$tournamentId]);
    $lastGroupRound = (int)$stmt->fetchColumn();
    
    generateSingleElimination($tournamentId, $knockoutTeamsData, $lastGroupRound + 1);
}

// ============================================================
// SINGLE ELIMINATION - خروج المغلوب من مرة واحدة
// ============================================================
// ============================================================
// SINGLE ELIMINATION - خروج المغلوب من مرة واحدة
// ============================================================
function generateSingleElimination($tournamentId, $teams, $startRound = 1) {
    $db = getDB();
    $n = count($teams);
    
    if ($n < 2) return;
    
    // Calculate bracket size (next power of 2)
    $rounds = (int)ceil(log($n, 2));
    $bracketSize = (int)pow(2, $rounds);
    
    // Get max match number to continue
    $stmt = $db->prepare("SELECT COALESCE(MAX(match_number), 0) FROM matches WHERE tournament_id = ?");
    $stmt->execute([$tournamentId]);
    $matchNumber = (int)$stmt->fetchColumn() + 1;
    
    // Phase 1: Create all empty matches for all rounds
    $matchIds = [];
    
    for ($round = 1; $round <= $rounds; $round++) {
        $actualRound = $round + $startRound - 1;
        $matchesInRound = (int)($bracketSize / pow(2, $round));
        $matchIds[$round] = [];
        $bracketType = ($round === $rounds) ? 'final' : 'main';
        
        for ($pos = 0; $pos < $matchesInRound; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$tournamentId, $actualRound, $matchNumber, $bracketType]);
            $matchIds[$round][$pos] = (int)$db->lastInsertId();
            $matchNumber++;
        }
    }
    
    // Phase 2: Link matches (winner goes to next match)
    for ($round = 1; $round < $rounds; $round++) {
        foreach ($matchIds[$round] as $pos => $matchId) {
            $nextPos = (int)floor($pos / 2);
            $nextSlot = ($pos % 2 === 0) ? 'team1' : 'team2';
            $nextMatchId = $matchIds[$round + 1][$nextPos] ?? null;
            
            if ($nextMatchId) {
                $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = ? WHERE id = ?")
                   ->execute([$nextMatchId, $nextSlot, $matchId]);
            }
        }
    }
    
    // Phase 3: Place teams in round 1 of this bracket
    $paddedTeams = $teams;
    while (count($paddedTeams) < $bracketSize) {
        $paddedTeams[] = null; // BYE
    }
    
    foreach ($matchIds[1] as $pos => $matchId) {
        $team1 = $paddedTeams[$pos * 2] ?? null;
        $team2 = $paddedTeams[$pos * 2 + 1] ?? null;
        $team1Id = $team1 ? $team1['id'] : null;
        $team2Id = $team2 ? $team2['id'] : null;
        
        if ($team1Id && $team2Id) {
            // Real match
            $db->prepare("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?")
               ->execute([$team1Id, $team2Id, $matchId]);
        } elseif ($team1Id || $team2Id) {
            // BYE - one team advances automatically
            $realTeamId = $team1Id ?: $team2Id;
            $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
               ->execute([$realTeamId, $realTeamId, $matchId]);
            
            // Advance to next match
            advanceWinnerToNext($db, $matchId, $realTeamId);
        } else {
            // Both BYE - mark as completed
            $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed' WHERE id = ?")
               ->execute([$matchId]);
        }
    }
    
    // Phase 4: Handle cascading BYEs in later rounds
    for ($round = 2; $round <= $rounds; $round++) {
        foreach ($matchIds[$round] as $pos => $matchId) {
            checkAndAdvanceBye($db, $matchId);
        }
    }
}

// ============================================================
// DOUBLE ELIMINATION - خروج المغلوب من مرتين
// ============================================================
function generateDoubleElimination($tournamentId, $teams) {
    $db = getDB();
    $n = count($teams);
    
    if ($n < 2) throw new Exception('يجب إضافة فريقين على الأقل');
    
    // ======== حساب أبعاد القوس ========
    $wRounds = (int)ceil(log($n, 2));
    $bracketSize = (int)pow(2, $wRounds);
    $lRounds = ($wRounds - 1) * 2; // عدد جولات الخاسرين
    
    $matchNumber = 1;
    
    // ================================================================
    // الخطوة 1: إنشاء شعبة الفائزين (Winners Bracket)
    // ================================================================
    $winnersMatchIds = [];
    
    for ($round = 1; $round <= $wRounds; $round++) {
        $matchesInRound = (int)($bracketSize / pow(2, $round));
        $winnersMatchIds[$round] = [];
        
        for ($pos = 0; $pos < $matchesInRound; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, 'main', 'scheduled')
            ");
            $stmt->execute([$tournamentId, $round, $matchNumber]);
            $winnersMatchIds[$round][$pos] = (int)$db->lastInsertId();
            $matchNumber++;
        }
    }
    
    // ربط شعبة الفائزين: الفائز → المباراة التالية
    for ($round = 1; $round < $wRounds; $round++) {
        foreach ($winnersMatchIds[$round] as $pos => $matchId) {
            $nextPos = (int)floor($pos / 2);
            $nextSlot = ($pos % 2 === 0) ? 'team1' : 'team2';
            $nextMatchId = $winnersMatchIds[$round + 1][$nextPos] ?? null;
            
            if ($nextMatchId) {
                $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = ? WHERE id = ?")
                   ->execute([$nextMatchId, $nextSlot, $matchId]);
            }
        }
    }
    
    // ================================================================
    // الخطوة 2: إنشاء شعبة الخاسرين (Losers Bracket)
    // ================================================================
    $losersMatchIds = [];
    $lMatchCount = (int)($bracketSize / 4);
    
    for ($lr = 1; $lr <= $lRounds; $lr++) {
        if ($lr === 1) {
            $lMatchCount = max(1, (int)($bracketSize / 4));
        } elseif ($lr % 2 === 0) {
            $lMatchCount = max(1, $lMatchCount);
        } else {
            $lMatchCount = max(1, (int)ceil($lMatchCount / 2));
        }
        
        $losersMatchIds[$lr] = [];
        
        for ($pos = 0; $pos < $lMatchCount; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, 'losers', 'scheduled')
            ");
            $stmt->execute([$tournamentId, $lr, $matchNumber]);
            $losersMatchIds[$lr][$pos] = (int)$db->lastInsertId();
            $matchNumber++;
        }
    }
    
    // ربط شعبة الخاسرين داخلياً
    for ($lr = 1; $lr < $lRounds; $lr++) {
        foreach ($losersMatchIds[$lr] as $pos => $matchId) {
            $nextRound = $lr + 1;
            if (!isset($losersMatchIds[$nextRound])) continue;
            
            $nextMatchCount = count($losersMatchIds[$nextRound]);
            
            if ($lr % 2 === 1) {
                $nextPos = min($pos, $nextMatchCount - 1);
                $nextSlot = 'team1';
            } else {
                $nextPos = min((int)floor($pos / 2), $nextMatchCount - 1);
                $nextSlot = ($pos % 2 === 0) ? 'team1' : 'team2';
            }
            
            $nextMatchId = $losersMatchIds[$nextRound][$nextPos] ?? null;
            if ($nextMatchId) {
                $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = ? WHERE id = ?")
                   ->execute([$nextMatchId, $nextSlot, $matchId]);
            }
        }
    }
    
    // ================================================================
    // الخطوة 3: ربط الخاسرين من الفائزين → شعبة الخاسرين
    // ================================================================
    foreach ($winnersMatchIds[1] as $pos => $matchId) {
        $lrPos = (int)floor($pos / 2);
        $losersTargetId = $losersMatchIds[1][$lrPos] ?? null;
        
        if ($losersTargetId) {
            $db->prepare("UPDATE matches SET loser_next_match_id = ? WHERE id = ?")
               ->execute([$losersTargetId, $matchId]);
        }
    }
    
    for ($wr = 2; $wr <= $wRounds; $wr++) {
        $targetLR = ($wr - 1) * 2;
        if (isset($losersMatchIds[$targetLR])) {
            foreach ($winnersMatchIds[$wr] as $pos => $matchId) {
                $lrPos = min($pos, count($losersMatchIds[$targetLR]) - 1);
                $losersTargetId = $losersMatchIds[$targetLR][$lrPos] ?? null;
                
                if ($losersTargetId) {
                    $db->prepare("UPDATE matches SET loser_next_match_id = ? WHERE id = ?")
                       ->execute([$losersTargetId, $matchId]);
                }
            }
        }
    }
    
    // ================================================================
    // الخطوة 4: إنشاء النهائي الكبير (Grand Final)
    // ================================================================
    $stmt = $db->prepare("
        INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status)
        VALUES (?, ?, ?, 'final', 'scheduled')
    ");
    $stmt->execute([$tournamentId, $wRounds + 1, $matchNumber]);
    $grandFinalId = (int)$db->lastInsertId();
    $matchNumber++;
    
    $wFinalId = end($winnersMatchIds[$wRounds]);
    if ($wFinalId) {
        $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = 'team1' WHERE id = ?")
           ->execute([$grandFinalId, $wFinalId]);
    }
    
    if ($lRounds > 0 && isset($losersMatchIds[$lRounds])) {
        $lFinalId = end($losersMatchIds[$lRounds]);
        if ($lFinalId) {
            $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = 'team2' WHERE id = ?")
               ->execute([$grandFinalId, $lFinalId]);
        }
    }
    
    // ================================================================
    // الخطوة 5: توزيع الفرق في الجولة الأولى من الفائزين
    // ================================================================
    $paddedTeams = $teams;
    while (count($paddedTeams) < $bracketSize) {
        $paddedTeams[] = null;
    }
    
    foreach ($winnersMatchIds[1] as $pos => $matchId) {
        $team1 = $paddedTeams[$pos * 2] ?? null;
        $team2 = $paddedTeams[$pos * 2 + 1] ?? null;
        $team1Id = $team1 ? ($team1['id'] ?? null) : null;
        $team2Id = $team2 ? ($team2['id'] ?? null) : null;
        
        if ($team1Id && $team2Id) {
            $db->prepare("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?")
               ->execute([$team1Id, $team2Id, $matchId]);
        } elseif ($team1Id || $team2Id) {
            $realTeamId = $team1Id ?: $team2Id;
            $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
               ->execute([$realTeamId, $realTeamId, $matchId]);
            advanceWinnerToNext($db, $matchId, $realTeamId);
        } else {
            $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed' WHERE id = ?")
               ->execute([$matchId]);
        }
    }
    
    // ================================================================
    // الخطوة 6: معالجة BYE متتالية في شعبة الفائزين
    // ================================================================
    for ($round = 2; $round <= $wRounds; $round++) {
        foreach ($winnersMatchIds[$round] as $pos => $matchId) {
            checkAndAdvanceBye($db, $matchId);
        }
    }
}

/**
 * Check if a match has only one team (BYE situation) and auto-advance
 */
function checkAndAdvanceBye($db, $matchId) {
    $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match || $match['status'] === 'completed') return;
    
    if ($match['team1_id'] && !$match['team2_id']) {
        // Check if team2 feeder is done
        $feeder = $db->prepare("SELECT COUNT(*) FROM matches WHERE next_match_id = ? AND next_match_slot = 'team2' AND status != 'completed'");
        $feeder->execute([$matchId]);
        $pending = $feeder->fetchColumn();
        
        if ($pending == 0) {
            // Check if there's actually a feeder that could send a team
            $feederDone = $db->prepare("SELECT * FROM matches WHERE next_match_id = ? AND next_match_slot = 'team2' AND status = 'completed'");
            $feederDone->execute([$matchId]);
            $doneFeed = $feederDone->fetch();
            
            if (!$doneFeed || !$doneFeed['winner_team_id']) {
                // No team coming, auto-advance
                $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                   ->execute([$match['team1_id'], $matchId]);
                advanceWinnerToNext($db, $matchId, $match['team1_id']);
            }
        }
    } elseif (!$match['team1_id'] && $match['team2_id']) {
        $feeder = $db->prepare("SELECT COUNT(*) FROM matches WHERE next_match_id = ? AND next_match_slot = 'team1' AND status != 'completed'");
        $feeder->execute([$matchId]);
        $pending = $feeder->fetchColumn();
        
        if ($pending == 0) {
            $feederDone = $db->prepare("SELECT * FROM matches WHERE next_match_id = ? AND next_match_slot = 'team1' AND status = 'completed'");
            $feederDone->execute([$matchId]);
            $doneFeed = $feederDone->fetch();
            
            if (!$doneFeed || !$doneFeed['winner_team_id']) {
                $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                   ->execute([$match['team2_id'], $matchId]);
                advanceWinnerToNext($db, $matchId, $match['team2_id']);
            }
        }
    }
}

/**
 * Advance winner to next match
 */
function advanceWinnerToNext($db, $matchId, $winnerId) {
    if (!$winnerId) return;
    
    $stmt = $db->prepare("SELECT next_match_id, next_match_slot FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if ($match && $match['next_match_id']) {
        $column = $match['next_match_slot'] === 'team1' ? 'team1_id' : 'team2_id';
        $db->prepare("UPDATE matches SET $column = ? WHERE id = ?")
           ->execute([$winnerId, $match['next_match_id']]);
        
        // Check if the next match now has a BYE situation
        checkAndAdvanceBye($db, $match['next_match_id']);
    }
}

/**
 * Send loser to losers bracket
 */
function sendLoserToLosersBracket($db, $matchId, $loserId) {
    if (!$loserId) return;
    
    $stmt = $db->prepare("SELECT loser_next_match_id FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if ($match && $match['loser_next_match_id']) {
        // Find empty slot in losers match
        $lMatch = $db->prepare("SELECT * FROM matches WHERE id = ?");
        $lMatch->execute([$match['loser_next_match_id']]);
        $losersMatch = $lMatch->fetch();
        
        if ($losersMatch) {
            if (!$losersMatch['team1_id']) {
                $db->prepare("UPDATE matches SET team1_id = ? WHERE id = ?")
                   ->execute([$loserId, $match['loser_next_match_id']]);
            } elseif (!$losersMatch['team2_id']) {
                $db->prepare("UPDATE matches SET team2_id = ? WHERE id = ?")
                   ->execute([$loserId, $match['loser_next_match_id']]);
            }
        }
    }
}

// ============================================================
// ROUND ROBIN - الدوري
// ============================================================
function generateRoundRobin($tournamentId, $teams, $isDouble) {
    $db = getDB();
    $n = count($teams);
    
    // Add BYE if odd number
    if ($n % 2 !== 0) {
        $teams[] = ['id' => null, 'team_name' => 'BYE'];
        $n++;
    }
    
    // Create standings for each real team
    foreach ($teams as $team) {
        if ($team['id']) {
            try {
                $db->prepare("INSERT INTO standings (tournament_id, team_id) VALUES (?, ?)")
                   ->execute([$tournamentId, $team['id']]);
            } catch (Exception $e) {}
        }
    }
    
    // Circle method for round robin scheduling
    $rounds = $n - 1;
    $matchNumber = 1;
    $fixed = $teams[0]; // First team stays fixed
    $rotating = array_slice($teams, 1); // Rest rotate
    
    for ($round = 1; $round <= $rounds; $round++) {
        // First pairing: fixed vs first of rotating
        $pairings = [[$fixed, $rotating[0]]];
        
        // Remaining pairings: fold the rotating array
        $halfSize = (int)(($n - 1) / 2);
        for ($i = 1; $i <= $halfSize; $i++) {
            $home = $rotating[$i] ?? null;
            $away = $rotating[$n - 1 - $i] ?? null;
            if ($home && $away) {
                $pairings[] = [$home, $away];
            }
        }
        
        // Insert matches for this round
        foreach ($pairings as $pair) {
            $team1 = $pair[0];
            $team2 = $pair[1];
            
            // Skip if either is BYE
            if (!$team1['id'] || !$team2['id']) continue;
            
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, team1_id, team2_id, status)
                VALUES (?, ?, ?, 'main', ?, ?, 'scheduled')
            ");
            $stmt->execute([$tournamentId, $round, $matchNumber, $team1['id'], $team2['id']]);
            $matchNumber++;
        }
        
        // Rotate: move last element to second position
        $last = array_pop($rotating);
        array_unshift($rotating, $last);
    }
    
    // Double round robin - add reverse fixtures
    if ($isDouble) {
        $stmt = $db->prepare("SELECT team1_id, team2_id, round_number FROM matches WHERE tournament_id = ? ORDER BY round_number, match_number");
        $stmt->execute([$tournamentId]);
        $firstLeg = $stmt->fetchAll();
        
        foreach ($firstLeg as $match) {
            $reverseRound = $match['round_number'] + $rounds;
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, team1_id, team2_id, status)
                VALUES (?, ?, ?, 'main', ?, ?, 'scheduled')
            ");
            $stmt->execute([
                $tournamentId,
                $reverseRound,
                $matchNumber,
                $match['team2_id'], // Reversed home/away
                $match['team1_id']
            ]);
            $matchNumber++;
        }
    }
}

// ============================================================
// SAVE MATCH RESULT
// ============================================================
function saveMatchResult() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['match_id', 'team1_score', 'team2_score']);
    
    $db = getDB();
    $matchId = (int)$data['match_id'];
    $team1Score = (int)$data['team1_score'];
    $team2Score = (int)$data['team2_score'];
    
    // Get match and tournament info
    $stmt = $db->prepare("
        SELECT m.*, t.type as tournament_type, t.points_win, t.points_draw, t.points_loss
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) jsonError('المباراة غير موجودة');
    if (!$match['team1_id'] || !$match['team2_id']) jsonError('المباراة غير مكتملة - لم يتم تحديد الفريقين');
    if ($match['status'] === 'completed') jsonError('المباراة منتهية بالفعل');
    
    $isElimination = strpos($match['tournament_type'], 'elimination') !== false;
    $isDoubleElim = $match['tournament_type'] === 'double_elimination';
    
    // No draws allowed in elimination tournaments
    if ($isElimination && $team1Score === $team2Score) {
        jsonError('لا يمكن التعادل في نظام خروج المغلوب - يجب تحديد فائز');
    }
    
    // Determine winner and loser
    $winnerId = null;
    $loserId = null;
    if ($team1Score > $team2Score) {
        $winnerId = $match['team1_id'];
        $loserId = $match['team2_id'];
    } elseif ($team2Score > $team1Score) {
        $winnerId = $match['team2_id'];
        $loserId = $match['team1_id'];
    }
    
    // Update match result
    $stmt = $db->prepare("
        UPDATE matches SET team1_score = ?, team2_score = ?, winner_team_id = ?, loser_team_id = ?, status = 'completed'
        WHERE id = ?
    ");
    $stmt->execute([$team1Score, $team2Score, $winnerId, $loserId, $matchId]);
    
    $isMixed = $match['tournament_type'] === 'mixed';
    $useEliminationLogic = $isElimination || ($isMixed && !$match['group_name']);

    if ($useEliminationLogic && $winnerId) {
        // Advance winner to next match
        advanceWinnerToNext($db, $matchId, $winnerId);
        
        if ($isDoubleElim) {
            // DOUBLE ELIMINATION LOGIC
            $bracketType = $match['bracket_type'];
            
            // Increment elimination count for loser
            $db->prepare("UPDATE tournament_teams SET elimination_count = elimination_count + 1 WHERE id = ?")
               ->execute([$loserId]);
            
            if ($bracketType === 'main') {
                // Loser from Winners Bracket → goes to Losers Bracket
                sendLoserToLosersBracket($db, $matchId, $loserId);
            } elseif ($bracketType === 'losers') {
                // Loser from Losers Bracket → ELIMINATED (2nd loss)
                $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
                   ->execute([$loserId]);
            } elseif ($bracketType === 'final') {
                // Grand Final
                $stmt = $db->prepare("SELECT elimination_count FROM tournament_teams WHERE id = ?");
                $stmt->execute([$loserId]);
                $elimCount = (int)$stmt->fetchColumn();
                
                if ($elimCount <= 1) {
                    // الخاسر من شعبة الفائزين (أول خسارة) → مباراة إعادة
                    $maxMatchNum = $db->prepare("SELECT MAX(match_number) FROM matches WHERE tournament_id = ?");
                    $maxMatchNum->execute([$match['tournament_id']]);
                    $nextMatchNum = (int)$maxMatchNum->fetchColumn() + 1;
                    
                    $stmt = $db->prepare("
                        INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, 
                            team1_id, team2_id, status)
                        VALUES (?, ?, ?, 'final', ?, ?, 'scheduled')
                    ");
                    $stmt->execute([
                        $match['tournament_id'],
                        $match['round_number'] + 1,
                        $nextMatchNum,
                        $winnerId,
                        $loserId
                    ]);
                } else {
                    // خسارة ثانية → يُقصى نهائياً
                    $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
                       ->execute([$loserId]);
                }
            }
        } else {
            // SINGLE ELIMINATION or Knockout stage of Mixed
            $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
               ->execute([$loserId]);
        }
    } elseif ($isMixed && $match['group_name']) {
        // Mixed Tournament Group Stage
        updateStandingsForMatch($match, $team1Score, $team2Score);
        
        // Check if all group stage matches are done
        $stmt = $db->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = ? AND group_name IS NOT NULL AND status != 'completed'");
        $stmt->execute([$match['tournament_id']]);
        if ($stmt->fetchColumn() == 0) {
            generateMixedKnockoutStage($match['tournament_id']);
        }
    } elseif (!$isElimination) {
        // ROUND ROBIN
        updateStandingsForMatch($match, $team1Score, $team2Score);
    }
    
    jsonSuccess(['winner_id' => $winnerId], 'تم حفظ النتيجة');
}

/**
 * Update standings table for a league match
 */
function updateStandingsForMatch($match, $score1, $score2) {
    $db = getDB();
    
    $team1Id = $match['team1_id'];
    $team2Id = $match['team2_id'];
    $tournamentId = $match['tournament_id'];
    
    $pw = $match['points_win'] ?? 3;
    $pd = $match['points_draw'] ?? 1;
    $pl = $match['points_loss'] ?? 0;
    
    if ($score1 > $score2) {
        // Team 1 wins
        $t1Points = $pw; $t2Points = $pl;
        $t1W = 1; $t2W = 0; $t1L = 0; $t2L = 1; $t1D = 0; $t2D = 0;
    } elseif ($score2 > $score1) {
        // Team 2 wins
        $t1Points = $pl; $t2Points = $pw;
        $t1W = 0; $t2W = 1; $t1L = 1; $t2L = 0; $t1D = 0; $t2D = 0;
    } else {
        // Draw
        $t1Points = $pd; $t2Points = $pd;
        $t1W = 0; $t2W = 0; $t1L = 0; $t2L = 0; $t1D = 1; $t2D = 1;
    }
    
    $sql = "UPDATE standings SET
            played = played + 1,
            wins = wins + ?, draws = draws + ?, losses = losses + ?,
            goals_for = goals_for + ?, goals_against = goals_against + ?,
            goal_difference = goal_difference + ?,
            points = points + ?
            WHERE tournament_id = ? AND team_id = ?";
    
    $db->prepare($sql)->execute([$t1W, $t1D, $t1L, $score1, $score2, $score1 - $score2, $t1Points, $tournamentId, $team1Id]);
    $db->prepare($sql)->execute([$t2W, $t2D, $t2L, $score2, $score1, $score2 - $score1, $t2Points, $tournamentId, $team2Id]);
}
