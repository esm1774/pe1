<?php
/**
 * Tournament Teams Functions
 */




// ============================================================
// LOTTERY WIZARD INTEGRATION — إضافة فرق من نتيجة القرعة
// ============================================================
/**
 * يستقبل نتيجة القرعة (فرق + أعضاء) ويضيفها مباشرةً كـ tournament_teams
 * مع تسجيل الأعضاء في tournament_player_stats لظهورهم في الإحصائيات
 *
 * POST: { tournament_id, teams: [{name, color, members: [{id, name}]}] }
 */
function addTeamsFromLottery() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();

    if (empty($data['tournament_id'])) jsonError('معرّف البطولة مطلوب');
    if (empty($data['teams']) || !is_array($data['teams'])) jsonError('بيانات الفرق مطلوبة');

    $db        = getDB();
    $tournamentId = (int)$data['tournament_id'];
    $schoolId  = schoolId();

    // تحقق من البطولة والحالة
    $stmt = $db->prepare("SELECT status, type FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$tournamentId, $schoolId]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');
    if (!in_array($tournament['status'], ['draft', 'registration'])) {
        jsonError('لا يمكن إضافة فرق لبطولة قيد التنفيذ أو منتهية');
    }

    $colors   = ['#ef4444','#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1'];
    $added    = 0;
    $teamIds  = [];

    try {
        $db->beginTransaction();

        foreach ($data['teams'] as $i => $team) {
            if (empty($team['name'])) throw new Exception("اسم الفريق رقم " . ($i + 1) . " مطلوب");
            if (empty($team['members'])) continue; // تجاوز الفريق الفارغ

            // رقم البذرة التالية
            $stmt = $db->prepare("SELECT COALESCE(MAX(seed_number), 0) + 1 FROM tournament_teams WHERE tournament_id = ?");
            $stmt->execute([$tournamentId]);
            $seed = (int)$stmt->fetchColumn();

            $color = $team['color'] ?? $colors[$i % count($colors)];

            // إدراج الفريق
            $stmt = $db->prepare("
                INSERT INTO tournament_teams (tournament_id, school_id, team_name, team_color, seed_number)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tournamentId, $schoolId, sanitize($team['name']), sanitize($color), $seed]);
            $teamId = (int)$db->lastInsertId();
            $teamIds[] = $teamId;

            // تسجيل الأعضاء في إحصائيات اللاعبين (لظهورهم في الأهداف والتتويجات)
            $insertStat = $db->prepare("
                INSERT IGNORE INTO tournament_player_stats (tournament_id, school_id, student_id, team_id)
                SELECT ?, ?, s.id, ?
                FROM students s
                WHERE s.id = ? AND s.school_id = ? AND s.active = 1
            ");

            foreach ($team['members'] as $member) {
                $studentId = is_array($member) ? (int)($member['id'] ?? 0) : (int)$member;
                if ($studentId > 0) {
                    $insertStat->execute([$tournamentId, $schoolId, $teamId, $studentId, $schoolId]);
                }
            }

            $added++;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonError($e->getMessage());
    }

    logActivity('lottery_teams_add', 'tournament', $tournamentId, "أُضيف $added فريق");
    jsonSuccess([
        'added'    => $added,
        'team_ids' => $teamIds
    ], "تم إضافة $added فريق من القرعة بنجاح ✅");
}

