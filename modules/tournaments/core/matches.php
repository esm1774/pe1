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

// ============================================================
// DOUBLE ELIMINATION - خروج المغلوب من مرتين (Ghost Protocol)
// ============================================================

function generateDoubleElimination($tournamentId, $teams) {
    $db = getDB();
    $n = count($teams);
    if ($n < 2) throw new Exception('يجب إضافة فريقين على الأقل');
    
    $wRounds = (int)ceil(log($n, 2));
    $bracketSize = (int)pow(2, $wRounds);
    
    // 1. Sort teams by seed
    usort($teams, fn($a, $b) => ($a['seed_number'] ?: 999) - ($b['seed_number'] ?: 999));
    
    // 2. Pad with Ghosts ('الفريق باي 1', 'الفريق باي 2', etc.)
    $allTeams = [];
    $ghostIds = [];
    $byeCounter = 1;
    for ($i = 0; $i < $bracketSize; $i++) {
        $seed = $i + 1;
        if ($i < $n) {
            $allTeams[$seed] = $teams[$i];
        } else {
            $byeName = 'الفريق باي ' . $byeCounter++;
            $stmt = $db->prepare("INSERT INTO tournament_teams (tournament_id, team_name, seed_number, is_eliminated) VALUES (?, ?, ?, 1)");
            $stmt->execute([$tournamentId, $byeName, $seed]);
            $ghostId = (int)$db->lastInsertId();
            $ghostIds[] = $ghostId;
            $allTeams[$seed] = ['id' => $ghostId, 'team_name' => $byeName, 'seed_number' => $seed];
        }
    }
    
    // 3. Folded Bracket Seed Order
    function buildFoldedBracket($size) {
        if ($size === 2) return [1, 2];
        $half = buildFoldedBracket($size / 2);
        $res = [];
        foreach ($half as $h) {
            $res[] = $h;
            $res[] = $size + 1 - $h;
        }
        return $res;
    }
    
    $seedOrder = buildFoldedBracket($bracketSize);
    $slots = [];
    foreach ($seedOrder as $s) {
        $slots[] = $allTeams[$s];
    }
    
    $matchNumber = 1;
    
    // ---------------------------------------------
    // 4. Generate WB Matches
    // ---------------------------------------------
    $wbMatches = []; // $wbMatches[round][pos] = match
    
    // WB R1 
    $wbMatches[1] = [];
    for ($i = 0; $i < $bracketSize / 2; $i++) {
        $t1 = $slots[$i * 2];
        $t2 = $slots[$i * 2 + 1];
        $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, team1_id, team2_id, status) VALUES (?, 1, ?, 'main', ?, ?, 'scheduled')");
        $stmt->execute([$tournamentId, $matchNumber++, $t1['id'], $t2['id']]);
        $wbMatches[1][$i] = ['id' => (int)$db->lastInsertId()];
    }
    
    // WB R2+
    for ($r = 2; $r <= $wRounds; $r++) {
        $wbMatches[$r] = [];
        $cnt = count($wbMatches[$r - 1]) / 2;
        for ($i = 0; $i < $cnt; $i++) {
            $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status) VALUES (?, ?, ?, 'main', 'scheduled')");
            $stmt->execute([$tournamentId, $r, $matchNumber++]);
            $wbMatches[$r][$i] = ['id' => (int)$db->lastInsertId()];
        }
    }
    
    // ---------------------------------------------
    // 5. Generate LB Matches
    // ---------------------------------------------
    $lbMatches = [];
    $lbRound = 1;
    $prevLBCnt = count($wbMatches[1]) / 2; // LBR1 match count
    
    for ($wr = 1; $wr < $wRounds; $wr++) {
        // Minor Round
        if ($wr == 1) {
            $lbMatches[$lbRound] = [];
            for ($i = 0; $i < $prevLBCnt; $i++) {
                $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status) VALUES (?, ?, ?, 'losers', 'scheduled')");
                $stmt->execute([$tournamentId, $lbRound, $matchNumber++]);
                $lbMatches[$lbRound][$i] = ['id' => (int)$db->lastInsertId()];
            }
            $lbRound++;
        } else {
            $minorCnt = $prevLBCnt / 2;
            if ($minorCnt >= 1) { // must generate round
                $lbMatches[$lbRound] = [];
                for ($i = 0; $i < $minorCnt; $i++) {
                    $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status) VALUES (?, ?, ?, 'losers', 'scheduled')");
                    $stmt->execute([$tournamentId, $lbRound, $matchNumber++]);
                    $lbMatches[$lbRound][$i] = ['id' => (int)$db->lastInsertId()];
                }
                $prevLBCnt = $minorCnt;
                $lbRound++;
            }
        }
        
        // Major Round
        $majorCnt = $prevLBCnt; 
        $lbMatches[$lbRound] = [];
        for ($i = 0; $i < $majorCnt; $i++) {
            $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status) VALUES (?, ?, ?, 'losers', 'scheduled')");
            $stmt->execute([$tournamentId, $lbRound, $matchNumber++]);
            $lbMatches[$lbRound][$i] = ['id' => (int)$db->lastInsertId()];
        }
        $lbRound++;
    }
    
    // ---------------------------------------------
    // 6. GF Match
    // ---------------------------------------------
    $stmt = $db->prepare("INSERT INTO matches (tournament_id, round_number, match_number, bracket_type, status) VALUES (?, ?, ?, 'final', 'scheduled')");
    $stmt->execute([$tournamentId, $wRounds + 1, $matchNumber++]);
    $gfId = (int)$db->lastInsertId();
    
    // ---------------------------------------------
    // 7. Topology Links
    // ---------------------------------------------
    
    // WBs to WBs & LBs
    for ($r = 1; $r < $wRounds; $r++) {
        foreach ($wbMatches[$r] as $i => $m) {
            // Next WB
            $nextPos = (int)floor($i / 2);
            $nextSlot = ($i % 2 === 0) ? 'team1' : 'team2';
            $nextId = $wbMatches[$r + 1][$nextPos]['id'];
            $db->prepare("UPDATE matches SET next_match_id=?, next_match_slot=? WHERE id=?")->execute([$nextId, $nextSlot, $m['id']]);
            
            // Loser to LB
            if ($r === 1) {
                // WBR1 -> LBR1 (Minor)
                $lbId = $lbMatches[1][(int)floor($i / 2)]['id'];
                $lbSlot = ($i % 2 === 0) ? 'team1' : 'team2';
                $db->prepare("UPDATE matches SET loser_next_match_id=?, loser_next_match_slot=? WHERE id=?")->execute([$lbId, $lbSlot, $m['id']]);
            } else {
                // WBR2+ -> LB Major
                $lbR = ($r - 1) * 2;
                $targetCount = count($lbMatches[$lbR]);
                $lbIdx = ($targetCount - 1) - $i; // CROSS: reverses index!
                $lbId = $lbMatches[$lbR][$lbIdx]['id'];
                $db->prepare("UPDATE matches SET loser_next_match_id=?, loser_next_match_slot='team1' WHERE id=?")->execute([$lbId, $m['id']]);
            }
        }
    }
    
    // WBR_last -> GF
    $db->prepare("UPDATE matches SET next_match_id=?, next_match_slot='team1' WHERE id=?")->execute([$gfId, $wbMatches[$wRounds][0]['id']]);
    
    // LBs to LBs
    for ($r = 1; $r < count($lbMatches); $r++) {
        $curList = $lbMatches[$r];
        $nxtList = $lbMatches[$r + 1];
        $isMinorToMajor = (count($curList) === count($nxtList));
        
        foreach ($curList as $i => $m) {
            if ($isMinorToMajor) {
                $nextPos = $i;
                $nextSlot = 'team2';
            } else {
                $nextPos = (int)floor($i / 2);
                $nextSlot = ($i % 2 === 0) ? 'team1' : 'team2';
            }
            $nextId = $nxtList[$nextPos]['id'];
            $db->prepare("UPDATE matches SET next_match_id=?, next_match_slot=? WHERE id=?")->execute([$nextId, $nextSlot, $m['id']]);
        }
    }
    
    // LBR_last -> GF
    $db->prepare("UPDATE matches SET next_match_id=?, next_match_slot='team2' WHERE id=?")->execute([$gfId, end($lbMatches)[0]['id']]);
    
    // ---------------------------------------------
    // 8. Collapse Ghost Matches!
    // ---------------------------------------------
    if (!empty($ghostIds)) {
        resolveGhostMatches($tournamentId, $ghostIds);
    }
}

/**
 * Automagically resolves and propagates all Ghost Team bracket placeholders
 */
function resolveGhostMatches($tournamentId, $ghostIds) {
    if (empty($ghostIds)) return;
    $db = getDB();
    $resolvedAny = true;
    
    $ghostIdsStr = implode(',', $ghostIds);
    
    while ($resolvedAny) {
        $resolvedAny = false;
        
        $stmt = $db->prepare("SELECT * FROM matches WHERE tournament_id = ? AND status = 'scheduled' AND (team1_id IN ($ghostIdsStr) OR team2_id IN ($ghostIdsStr))");
        $stmt->execute([$tournamentId]);
        $ghostMatches = $stmt->fetchAll();
        
        foreach ($ghostMatches as $m) {
            $t1 = $m['team1_id'];
            $t2 = $m['team2_id'];
            
            // Wait until both teams arrive (from prev rounds)
            if ($t1 !== null && $t2 !== null) {
                $t1Ghost = in_array($t1, $ghostIds);
                $t2Ghost = in_array($t2, $ghostIds);
                
                $winnerId = null;
                $loserId = null;
                $isBye = 0;
                
                if ($t1Ghost && !$t2Ghost) {
                    $winnerId = $t2; $loserId = $t1; $isBye = 1; // Real beats Ghost
                } elseif (!$t1Ghost && $t2Ghost) {
                    $winnerId = $t1; $loserId = $t2; $isBye = 1; // Real beats Ghost
                } elseif ($t1Ghost && $t2Ghost) {
                    $winnerId = $t1; $loserId = $t2; $isBye = 2; // Ghost beats Ghost 
                }
                
                if ($winnerId !== null) {
                    // Fast-forward this match
                    $stmt = $db->prepare("UPDATE matches SET winner_team_id=?, loser_team_id=?, status='completed', is_bye=? WHERE id=?");
                    $stmt->execute([$winnerId, $loserId, $isBye, $m['id']]);
                    
                    // Push winner forward
                    if ($m['next_match_id']) {
                        $col = $m['next_match_slot'] === 'team1' ? 'team1_id' : 'team2_id';
                        $db->prepare("UPDATE matches SET $col=? WHERE id=?")->execute([$winnerId, $m['next_match_id']]);
                    }
                    
                    // Push loser to LB
                    if ($m['loser_next_match_id']) {
                        $col = $m['loser_next_match_slot'] === 'team1' ? 'team1_id' : 'team2_id';
                        $db->prepare("UPDATE matches SET $col=? WHERE id=?")->execute([$loserId, $m['loser_next_match_id']]);
                    }
                    
                    $resolvedAny = true;
                }
            }
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
    
    // If a match is BYE, both teams could be NULL (empty slot in larger bracket)
    // or one team exists and other is NULL.
    
    $t1Id = $match['team1_id'];
    $t2Id = $match['team2_id'];
    
    // Check if this match is waiting for teams from other matches
    $stmtRec = $db->prepare("SELECT COUNT(*) FROM matches WHERE next_match_id = ? AND status != 'completed'");
    $stmtRec->execute([$matchId]);
    $pendingFeeders = (int)$stmtRec->fetchColumn();
    
    if ($pendingFeeders === 0) {
        if ($t1Id && !$t2Id) {
             $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                ->execute([$t1Id, $matchId]);
             advanceWinnerToNext($db, $matchId, $t1Id);
        } elseif (!$t1Id && $t2Id) {
             $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                ->execute([$t2Id, $matchId]);
             advanceWinnerToNext($db, $matchId, $t2Id);
        } elseif (!$t1Id && !$t2Id) {
            // Check if it's truly empty (no feeders)
            $stmtFeeds = $db->prepare("SELECT COUNT(*) FROM matches WHERE next_match_id = ?");
            $stmtFeeds->execute([$matchId]);
            if ((int)$stmtFeeds->fetchColumn() === 0) {
                $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed' WHERE id = ?")->execute([$matchId]);
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
/**
 * Send loser to losers bracket
 */
function sendLoserToLosersBracket($db, $matchId, $loserId) {
    if (!$loserId) return;
    
    $stmt = $db->prepare("SELECT loser_next_match_id, loser_next_match_slot FROM matches WHERE id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if ($match && $match['loser_next_match_id']) {
        $column = $match['loser_next_match_slot'] === 'team2' ? 'team2_id' : 'team1_id';
        $db->prepare("UPDATE matches SET $column = ? WHERE id = ?")
           ->execute([$loserId, $match['loser_next_match_id']]);
        
        // After placing a loser, check if LB match now has a BYE situation
        checkAndAdvanceBye($db, $match['loser_next_match_id']);
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
