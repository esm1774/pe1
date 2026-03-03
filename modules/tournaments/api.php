<?php
/**
 * PE Smart School System - Tournament Engine API v2.0
 * Fully Independent Module - Live Tournament Suite
 */

require_once '../../config.php';
require_once '../../api/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$action = getParam('action', $_POST['action'] ?? '');

// Resolve current tenant (school) context
Tenant::resolve();

// SaaS Check: Tournaments feature
Subscription::requireFeature('tournaments');

// Ensure tournament tables exist
ensureTournamentTables();

try {
    switch ($action) {
        // TOURNAMENTS CRUD
        case 'tournaments_list':     getTournaments(); break;
        case 'tournament_get':       getTournament(); break;
        case 'tournament_create':    createTournament(); break;
        case 'tournament_update':    updateTournament(); break;
        case 'tournament_delete':    deleteTournament(); break;
        case 'tournament_start':     startTournament(); break;
        case 'tournament_complete':  completeTournament(); break;

        // TEAMS
        case 'teams_list':           getTeams(); break;
        case 'team_add':             addTeam(); break;
        case 'team_remove':          removeTeam(); break;
        case 'teams_add_classes':    addClassesAsTeams(); break;
        case 'teams_randomize':      randomizeStudentTeams(); break;

        // MATCHES
        case 'matches_list':         getMatches(); break;
        case 'matches_generate':     generateMatches(); break;
        case 'match_update':         updateMatch(); break;
        case 'match_result':         saveMatchResult(); break;
        case 'matches_schedule':     scheduleMatches(); break;

        // STANDINGS
        case 'standings_get':        getStandings(); break;
        case 'standings_recalculate': recalculateStandings(); break;

        // BRACKET
        case 'bracket_get':          getBracket(); break;

        // ════════════════════════════════════════════════════
        // LIVE TOURNAMENT SUITE — الجديد v2.0
        // ════════════════════════════════════════════════════

        // الصفحة العامة (لا تحتاج تسجيل دخول)
        case 'tournament_public':    getTournamentPublic();  break;
        case 'tournament_live_feed': getLiveFeed();          break;

        // إحصائيات اللاعبين
        case 'player_stats_get':     getPlayerStats();       break;
        case 'player_stats_update':  updatePlayerStats();    break;
        case 'top_scorers':          getTopScorers();        break;
        case 'man_of_match_set':     setManOfMatch();        break;
        case 'player_award_set':     setPlayerAward();       break;
        case 'available_students':   getTournamentStudents(); break;
        case 'available_sports_teams': getAvailableSportsTeams(); break;
        case 'teams_add_sports_teams': addSportsTeamsToTournament(); break;
        case 'class_students':       getClassStudents();      break;
        case 'tournament_students':  getTournamentStudents(); break;
        case 'team_members_get':     getTeamMembers();        break;

        // مشاركة ورابط البطولة
        case 'tournament_share':     getTournamentShareInfo(); break;
        case 'bracket_public':       getBracketPublic(); break;

        // وسائط المباريات (صور/فيديو)
        case 'match_media_upload':   uploadMatchMedia(); break;
        case 'match_media_delete':   deleteMatchMedia(); break;
        case 'match_media_list':     getMatchMedia();    break;

        // HELPERS
        case 'available_classes':    getAvailableClasses(); break;
        case 'available_potential_students': getAvailableStudents(); break;
        case 'tournament_print':      printTournament(); break;
        case 'tournament_full_report': getTournamentFullReport(); break;
        
        // التفاعل والتحفيز (v2.1)
        case 'cheer_action':          cheerAction(); break;
        case 'player_history':        getPlayerHistory(); break;

        default:
            jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error: ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

// ============================================================
// ENSURE TABLES EXIST - v2.0: Live Tournament Suite
// ============================================================
function ensureTournamentTables() {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $db = getDB();
        
        // ─── الجداول الرئيسية ──────────────────────────────────────────
        
        // 1. tournaments
        $db->exec("CREATE TABLE IF NOT EXISTS `tournaments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(150) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `type` ENUM('single_elimination', 'double_elimination', 'round_robin_single', 'round_robin_double', 'mixed') NOT NULL,
            `team_mode` ENUM('class_based', 'student_based') NOT NULL DEFAULT 'class_based',
            `sport_type` VARCHAR(50) DEFAULT 'كرة قدم',
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `randomize_teams` TINYINT(1) NOT NULL DEFAULT 1,
            `auto_generate` TINYINT(1) NOT NULL DEFAULT 1,
            `teams_per_match` INT UNSIGNED DEFAULT 2,
            `points_win` INT UNSIGNED DEFAULT 3,
            `points_draw` INT UNSIGNED DEFAULT 1,
            `points_loss` INT UNSIGNED DEFAULT 0,
            `status` ENUM('draft', 'registration', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
            `winner_team_id` INT UNSIGNED DEFAULT NULL,
            `created_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_t_school` (`school_id`),
            INDEX `idx_t_status` (`status`),
            INDEX `idx_t_type` (`type`),
            INDEX `idx_t_mode` (`team_mode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. tournament_teams
        $db->exec("CREATE TABLE IF NOT EXISTS `tournament_teams` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `class_id` INT UNSIGNED DEFAULT NULL,
            `team_name` VARCHAR(100) NOT NULL,
            `team_color` VARCHAR(20) DEFAULT '#10b981',
            `seed_number` INT UNSIGNED DEFAULT NULL,
            `is_eliminated` TINYINT(1) NOT NULL DEFAULT 0,
            `elimination_count` INT UNSIGNED DEFAULT 0,
            `current_round` INT UNSIGNED DEFAULT 1,
            `group_name` VARCHAR(10) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tt_tournament` (`tournament_id`),
            INDEX `idx_tt_school` (`school_id`),
            INDEX `idx_tt_class` (`class_id`),
            INDEX `idx_tt_group` (`group_name`),
            CONSTRAINT `fk_tt_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. student_teams
        $db->exec("CREATE TABLE IF NOT EXISTS `student_teams` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_team_id` INT UNSIGNED NOT NULL,
            `team_name` VARCHAR(100) NOT NULL,
            `captain_student_id` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_st_tt` (`tournament_team_id`),
            CONSTRAINT `fk_st_tournament_team` FOREIGN KEY (`tournament_team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. student_team_members
        $db->exec("CREATE TABLE IF NOT EXISTS `student_team_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_team_id` INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `position` VARCHAR(50) DEFAULT NULL,
            `jersey_number` INT UNSIGNED DEFAULT NULL,
            `is_substitute` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_stm_team_student` (`student_team_id`, `student_id`),
            INDEX `idx_stm_student` (`student_id`),
            CONSTRAINT `fk_stm_team` FOREIGN KEY (`student_team_id`) 
                REFERENCES `student_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. matches
        $db->exec("CREATE TABLE IF NOT EXISTS `matches` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `round_number` INT UNSIGNED NOT NULL DEFAULT 1,
            `match_number` INT UNSIGNED NOT NULL,
            `bracket_type` ENUM('main', 'losers', 'final', 'third_place') NOT NULL DEFAULT 'main',
            `team1_id` INT UNSIGNED DEFAULT NULL,
            `team2_id` INT UNSIGNED DEFAULT NULL,
            `team1_score` INT UNSIGNED DEFAULT NULL,
            `team2_score` INT UNSIGNED DEFAULT NULL,
            `winner_team_id` INT UNSIGNED DEFAULT NULL,
            `loser_team_id` INT UNSIGNED DEFAULT NULL,
            `is_bye` TINYINT(1) DEFAULT 0,
            `match_date` DATE DEFAULT NULL,
            `match_time` TIME DEFAULT NULL,
            `venue` VARCHAR(100) DEFAULT NULL,
            `status` ENUM('scheduled', 'in_progress', 'completed', 'postponed', 'cancelled') NOT NULL DEFAULT 'scheduled',
            `next_match_id` INT UNSIGNED DEFAULT NULL,
            `next_match_slot` ENUM('team1', 'team2') DEFAULT NULL,
            `loser_next_match_id` INT UNSIGNED DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `group_name` VARCHAR(10) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_m_tournament` (`tournament_id`),
            INDEX `idx_m_school` (`school_id`),
            INDEX `idx_m_round` (`round_number`),
            INDEX `idx_m_bracket` (`bracket_type`),
            INDEX `idx_m_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. standings
        $db->exec("CREATE TABLE IF NOT EXISTS `standings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL,
            `school_id` INT UNSIGNED DEFAULT NULL,
            `team_id` INT UNSIGNED NOT NULL,
            `played` INT UNSIGNED DEFAULT 0,
            `wins` INT UNSIGNED DEFAULT 0,
            `draws` INT UNSIGNED DEFAULT 0,
            `losses` INT UNSIGNED DEFAULT 0,
            `goals_for` INT UNSIGNED DEFAULT 0,
            `goals_against` INT UNSIGNED DEFAULT 0,
            `goal_difference` INT DEFAULT 0,
            `points` INT UNSIGNED DEFAULT 0,
            `form` VARCHAR(20) DEFAULT NULL,
            `group_name` VARCHAR(10) DEFAULT NULL,
            `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_s_tournament_team` (`tournament_id`, `team_id`),
            INDEX `idx_s_school` (`school_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. match_events
        $db->exec("CREATE TABLE IF NOT EXISTS `match_events` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `match_id` INT UNSIGNED NOT NULL,
            `team_id` INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED DEFAULT NULL,
            `event_type` ENUM('goal', 'own_goal', 'penalty', 'yellow_card', 'red_card', 'substitution', 'injury') NOT NULL,
            `minute` INT UNSIGNED DEFAULT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_me_match` (`match_id`),
            CONSTRAINT `fk_me_match` FOREIGN KEY (`match_id`) 
                REFERENCES `matches`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 8. tournament_player_stats — إحصائيات اللاعبين
        $db->exec("CREATE TABLE IF NOT EXISTS `tournament_player_stats` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id`  INT UNSIGNED NOT NULL,
            `school_id`      INT UNSIGNED DEFAULT NULL,
            `student_id`     INT UNSIGNED NOT NULL,
            `team_id`        INT UNSIGNED NOT NULL,
            `goals`          INT UNSIGNED DEFAULT 0,
            `own_goals`      INT UNSIGNED DEFAULT 0,
            `assists`        INT UNSIGNED DEFAULT 0,
            `yellow_cards`   INT UNSIGNED DEFAULT 0,
            `red_cards`      INT UNSIGNED DEFAULT 0,
            `man_of_match`   INT UNSIGNED DEFAULT 0,
            `matches_played` INT UNSIGNED DEFAULT 0,
            `last_updated`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_tps_tour_student` (`tournament_id`, `student_id`),
            INDEX `idx_tps_school` (`school_id`),
            CONSTRAINT `fk_tps_tournament` FOREIGN KEY (`tournament_id`)
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 9. match_media — وسائط المباريات
        $db->exec("CREATE TABLE IF NOT EXISTS `match_media` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `match_id`      INT UNSIGNED NOT NULL,
            `school_id`     INT UNSIGNED DEFAULT NULL,
            `media_type`    ENUM('photo', 'video') NOT NULL DEFAULT 'photo',
            `media_url`     VARCHAR(255) NOT NULL,
            `description`   VARCHAR(255) DEFAULT NULL,
            `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_mm_match` (`match_id`),
            INDEX `idx_mm_school` (`school_id`),
            CONSTRAINT `fk_mm_match` FOREIGN KEY (`match_id`) 
                REFERENCES `matches`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ─── ترحيل الأعمدة للجداول الموجودة مسبقاً ─────────────────────────
        $childTables = ['matches', 'tournament_teams', 'standings', 'tournament_player_stats', 'match_media'];
        foreach ($childTables as $tableName) {
            $cols = array_column($db->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(), 'Field');
            if (!in_array('school_id', $cols)) {
                $db->exec("ALTER TABLE `$tableName` ADD COLUMN `school_id` INT UNSIGNED DEFAULT NULL AFTER `id` NOT NULL");
                $db->exec("ALTER TABLE `$tableName` ADD INDEX `idx_saas_school` (`school_id`)");
            }
            
            // Backfill school_id from parent tournament if missing
            if ($tableName !== 'match_media') {
                $db->exec("
                    UPDATE `$tableName` child
                    JOIN tournaments t ON child.tournament_id = t.id
                    SET child.school_id = t.school_id
                    WHERE child.school_id IS NULL AND t.school_id IS NOT NULL
                ");
            } else {
                $db->exec("
                    UPDATE `match_media` mm
                    JOIN matches m ON mm.match_id = m.id
                    SET mm.school_id = m.school_id
                    WHERE mm.school_id IS NULL AND m.school_id IS NOT NULL
                ");
            }
        }
        
        // ─── إضافات أخرى v2.1 ───
        _addColumnIfNotExists($db, 'tournament_teams', 'cheers_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
        _addColumnIfNotExists($db, 'tournament_player_stats', 'cheers_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
        _addColumnIfNotExists($db, 'tournament_teams', 'sports_team_id', 'INT UNSIGNED DEFAULT NULL AFTER `class_id`');

    } catch (Exception $e) {
        if (DEBUG_MODE) {
            error_log('Tournament table creation error: ' . $e->getMessage());
        }
    }
}

/**
 * مساعد: يضيف عموداً إلى جدول إن لم يكن موجوداً بالفعل
 */
function _addColumnIfNotExists(PDO $db, string $table, string $column, string $definition): void {
    try {
        $row = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->fetch();
        if (!$row) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    } catch (Exception $e) {
        if (DEBUG_MODE) error_log("_addColumnIfNotExists({$table}.{$column}): " . $e->getMessage());
    }
}

// ============================================================
// TOURNAMENTS
// ============================================================
function getTournaments() {
    requireLogin();
    $db = getDB();
    $status = getParam('status');
    
    $sql = "SELECT t.*, 
            COUNT(DISTINCT tt.id) as team_count,
            COUNT(DISTINCT m.id) as match_count,
            SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_matches
            FROM tournaments t
            LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
            LEFT JOIN matches m ON m.tournament_id = t.id
            WHERE t.school_id = ?";
    
    $params = [schoolId()];
    if ($status) {
        $sql .= " AND t.status = ?";
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
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, schoolId()]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Get teams
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name, 
               CONCAT(g.name, ' - ', c.name) as full_class_name
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ?
        ORDER BY tt.seed_number, tt.id
    ");
    $stmt->execute([$id]);
    $tournament['teams'] = $stmt->fetchAll();
    
    // Get match stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
        FROM matches WHERE tournament_id = ?
    ");
    $stmt->execute([$id]);
    $tournament['match_stats'] = $stmt->fetch();
    
    jsonSuccess($tournament);
}

function createTournament() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    validateRequired($data, ['name', 'type']);
    
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO tournaments (school_id, name, description, type, team_mode, sport_type, 
            start_date, end_date, randomize_teams, auto_generate, 
            points_win, points_draw, points_loss, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");
    
    $stmt->execute([
        schoolId(),
        sanitize($data['name']),
        sanitize($data['description'] ?? ''),
        sanitize($data['type']),
        sanitize($data['team_mode'] ?? 'class_based'),
        sanitize($data['sport_type'] ?? 'كرة قدم'),
        !empty($data['start_date']) ? $data['start_date'] : null,
        !empty($data['end_date']) ? $data['end_date'] : null,
        isset($data['randomize_teams']) ? (int)$data['randomize_teams'] : 1,
        isset($data['auto_generate']) ? (int)$data['auto_generate'] : 1,
        (int)($data['points_win'] ?? 3),
        (int)($data['points_draw'] ?? 1),
        (int)($data['points_loss'] ?? 0),
        $_SESSION['user_id']
    ]);
    
    $id = $db->lastInsertId();
    logActivity('create', 'tournament', $id, $data['name']);
    jsonSuccess(['id' => (int)$id], 'تم إنشاء البطولة بنجاح');
}

function updateTournament() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $id = $data['id'] ?? getParam('id');
    
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE tournaments
        SET name = ?, description = ?, type = ?, team_mode = ?, sport_type = ?,
            start_date = ?, end_date = ?, randomize_teams = ?, auto_generate = ?,
            points_win = ?, points_draw = ?, points_loss = ?
        WHERE id = ? AND school_id = ? AND status IN ('draft', 'registration')
    ");
    
    $result = $stmt->execute([
        sanitize($data['name']),
        sanitize($data['description'] ?? ''),
        sanitize($data['type']),
        sanitize($data['team_mode'] ?? 'class_based'),
        sanitize($data['sport_type'] ?? 'كرة قدم'),
        !empty($data['start_date']) ? $data['start_date'] : null,
        !empty($data['end_date']) ? $data['end_date'] : null,
        isset($data['randomize_teams']) ? (int)$data['randomize_teams'] : 1,
        isset($data['auto_generate']) ? (int)$data['auto_generate'] : 1,
        (int)($data['points_win'] ?? 3),
        (int)($data['points_draw'] ?? 1),
        (int)($data['points_loss'] ?? 0),
        $id,
        schoolId()
    ]);
    
    if ($stmt->rowCount() === 0) {
        jsonError('لا يمكن تعديل بطولة قيد التنفيذ');
    }
    
    logActivity('update', 'tournament', $id);
    jsonSuccess(null, 'تم تحديث البطولة');
}

function deleteTournament() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $db->prepare("DELETE FROM tournaments WHERE id = ?")->execute([$id]);
    logActivity('delete', 'tournament', $id);
    jsonSuccess(null, 'تم حذف البطولة');
}

function startTournament() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Get tournament info
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    if ($tournament['status'] === 'in_progress') {
        jsonError('البطولة جارية بالفعل!');
    }

    // Check team count (only non-eliminated teams)
    $stmt = $db->prepare("SELECT COUNT(*) FROM tournament_teams WHERE tournament_id = ? AND is_eliminated = 0");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() < 2) {
        jsonError('يجب إضافة فريقين على الأقل للبدء');
    }
    
    // Generate matches only if auto_generate and no matches exist
    $stmt = $db->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = ?");
    $stmt->execute([$id]);
    $existingMatches = (int)$stmt->fetchColumn();
    
    if ($tournament['auto_generate'] && $existingMatches === 0) {
        // Reset teams first
        $db->prepare("UPDATE tournament_teams SET is_eliminated = 0, elimination_count = 0 WHERE tournament_id = ?")->execute([$id]);
        generateMatchesForTournament($id, $tournament['type'], $tournament['randomize_teams']);
    }
    
    // ─── NEW: Generate public link (if not exists) ──────────────────
    $publicToken = $tournament['public_token'] ?: bin2hex(random_bytes(16));
    $db->prepare("UPDATE tournaments SET public_token = ?, is_public = 1, status = 'in_progress' WHERE id = ?")
       ->execute([$publicToken, $id]);

    // ─── NEW: Bulk notification ─────────────────────────────────────
    $publicUrl = BASE_URL . "/tournament.php?t={$publicToken}";
    sendBulkNotification([
        'type' => 'tournament_started',
        'title' => "🏆 انطلقت البطولة {$tournament['name']}!",
        'message' => "🔴 تابعوا النتائج والأهداف مباشرة من هنا.",
        'link' => $publicUrl,
        'targets' => 'all_students_and_parents'
    ]);

    logActivity('start', 'tournament', $id);
    jsonSuccess([
        'public_token' => $publicToken,
        'public_url'   => $publicUrl
    ], 'تم بدء البطولة بنجاح وإرسال الإشعارات');
}

function completeTournament() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Find winner based on tournament type
    $stmt = $db->prepare("SELECT type FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$id, schoolId()]);
    $type = $stmt->fetchColumn();
    
    if (!$type) jsonError('البطولة غير موجودة أو لا تملك صلاحية الوصول');

    $winnerId = null;
    $completedMatches = 0;

    // Check if any matches were completed
    $stmt = $db->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = ? AND status = 'completed'");
    $stmt->execute([$id]);
    $completedMatches = (int)$stmt->fetchColumn();
    
    if ($completedMatches > 0) {
        if (strpos($type, 'elimination') !== false) {
            $stmt = $db->prepare("
                SELECT m.winner_team_id FROM matches m
                JOIN tournaments t ON m.tournament_id = t.id
                WHERE m.tournament_id = ? AND t.school_id = ? AND m.bracket_type = 'final' AND m.status = 'completed'
                ORDER BY m.round_number DESC LIMIT 1
            ");
            $stmt->execute([$id, schoolId()]);
            $winnerId = $stmt->fetchColumn();
        } else {
            $stmt = $db->prepare("
                SELECT s.team_id FROM standings s
                JOIN tournaments t ON s.tournament_id = t.id
                WHERE s.tournament_id = ? AND t.school_id = ?
                ORDER BY s.points DESC, s.goal_difference DESC, s.goals_for DESC 
                LIMIT 1
            ");
            $stmt->execute([$id, schoolId()]);
            $winnerId = $stmt->fetchColumn();
        }
    }
    
    // Ensure winnerId is null if not found (fetchColumn returns false)
    if ($winnerId === false) $winnerId = null;
    
    $stmt = $db->prepare("UPDATE tournaments SET status = 'completed', winner_team_id = ? WHERE id = ? AND school_id = ?");
    $stmt->execute([$winnerId, $id, schoolId()]);
    
    if ($completedMatches > 0) {
        // Only update points if matches were actually played
        updateClassPointsFromTournament($id);
    }
    
    logActivity('complete', 'tournament', $id);
    jsonSuccess(['winner_id' => $winnerId], 'تم إنهاء البطولة');
}

// ============================================================
// TEAMS
// ============================================================
function getTeams() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Try with member count, fallback without
    $queries = [
        "SELECT tt.*, c.name as class_name,
               CONCAT(g.name, ' - ', c.name) as full_class_name,
               (SELECT COUNT(*) FROM student_team_members stm 
                JOIN student_teams st ON stm.student_team_id = st.id 
                WHERE st.tournament_team_id = tt.id) as member_count
        FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.id
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ? AND t.school_id = ?
        ORDER BY tt.seed_number, tt.id",
        
        "SELECT tt.*, c.name as class_name,
               CONCAT(g.name, ' - ', c.name) as full_class_name,
               0 as member_count
        FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.id
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ? AND t.school_id = ?
        ORDER BY tt.seed_number, tt.id"
    ];
    
    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$tournamentId, schoolId()]);
            jsonSuccess($stmt->fetchAll());
            return;
        } catch (PDOException $e) {
            continue;
        }
    }
    jsonSuccess([]);
}

function addTeam() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    validateRequired($data, ['tournament_id', 'team_name']);
    
    $db = getDB();
    
    // Check tournament status
    $stmt = $db->prepare("SELECT status FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$data['tournament_id'], schoolId()]);
    $status = $stmt->fetchColumn();
    
    if (!$status) jsonError('البطولة غير موجودة');
    
    if (!in_array($status, ['draft', 'registration'])) {
        jsonError('لا يمكن إضافة فرق لبطولة قيد التنفيذ');
    }
    
    // Get next seed number
    $stmt = $db->prepare("SELECT COALESCE(MAX(seed_number), 0) + 1 FROM tournament_teams WHERE tournament_id = ?");
    $stmt->execute([$data['tournament_id']]);
    $seedNumber = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        INSERT INTO tournament_teams (tournament_id, class_id, team_name, team_color, seed_number)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$data['tournament_id'],
        !empty($data['class_id']) ? (int)$data['class_id'] : null,
        sanitize($data['team_name']),
        sanitize($data['team_color'] ?? '#10b981'),
        $seedNumber
    ]);
    
    jsonSuccess(['id' => (int)$db->lastInsertId()], 'تم إضافة الفريق');
}

function removeTeam() {
    requireRole(['admin', 'teacher']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف الفريق مطلوب');
    
    $db = getDB();
    
    // Check if tournament allows removal
    $stmt = $db->prepare("
        SELECT t.status FROM tournament_teams tt
        JOIN tournaments t ON tt.tournament_id = t.id
        WHERE tt.id = ? AND t.school_id = ?
    ");
    $stmt->execute([$id, schoolId()]);
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
    if (empty($data['class_ids']) || !is_array($data['class_ids'])) jsonError('يجب اختيار فصل واحد على الأقل');
    
    $db = getDB();
    $tournamentId = (int)$data['tournament_id'];
    $classIds = $data['class_ids'];
    $studentIds = isset($data['student_ids']) ? (array)$data['student_ids'] : null; // جديد: قائمة الطلاب المختارين
    
    // Check tournament exists and is in draft/registration
    $stmt = $db->prepare("SELECT status FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$tournamentId, schoolId()]);
    $status = $stmt->fetchColumn();
    
    if (!$status) jsonError('البطولة غير موجودة');
    if (!in_array($status, ['draft', 'registration'])) jsonError('لا يمكن إضافة فرق لبطولة قيد التنفيذ');
    
    // Get existing classes in tournament
    $stmt = $db->prepare("SELECT class_id FROM tournament_teams WHERE tournament_id = ? AND class_id IS NOT NULL");
    $stmt->execute([$tournamentId]);
    $existingClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate random colors for teams
    $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#14b8a6', '#a855f7', '#e11d48'];
    
    $added = 0;
    foreach ($classIds as $classId) {
        $classId = (int)$classId;
        if (in_array($classId, $existingClasses)) continue;
        
        // Get class info
        $stmt = $db->prepare("
            SELECT c.name, CONCAT(g.name, ' - ', c.name) as full_name
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
        $tournamentTeamId = $db->lastInsertId();

        // إذا تم اختيار طلاب محددين، ننشئ علاقة الربط لتظهر أسماؤهم في الجوائز/الأهداف
        if ($studentIds && count($studentIds) > 0) {
            $stmt = $db->prepare("INSERT INTO student_teams (tournament_team_id, team_name) VALUES (?, ?)");
            $stmt->execute([$tournamentTeamId, $class['full_name']]);
            $studentTeamId = $db->lastInsertId();

            $stmtMember = $db->prepare("INSERT INTO student_team_members (student_team_id, student_id) VALUES (?, ?)");
            foreach ($studentIds as $sid) {
                $stmtMember->execute([$studentTeamId, (int)$sid]);
            }
        }
        $added++;
    }
    
    if ($added === 0) jsonError('جميع الفصول المختارة مضافة بالفعل');
    
    logActivity('add_teams', 'tournament', $tournamentId, "Added $added classes");
    jsonSuccess(['added' => $added], "تم إضافة $added فريق بنجاح");
}

function randomizeStudentTeams() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    validateRequired($data, ['tournament_id', 'student_ids', 'team_count']);
    
    $db = getDB();
    $tournamentId = (int)$data['tournament_id'];
    $studentIds = $data['student_ids'];
    $teamCount = (int)$data['team_count'];
    
    if ($teamCount < 2) jsonError('يجب إنشاء فريقين على الأقل');
    if (count($studentIds) < $teamCount) jsonError('عدد الطلاب أقل من عدد الفرق');
    
    shuffle($studentIds);
    
    $teamNames = ['الأسود', 'النمور', 'الصقور', 'الفهود', 'الذئاب', 'النسور', 'الأبطال', 'المحاربون'];
    $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    
    $teams = [];
    for ($i = 0; $i < $teamCount; $i++) {
        $stmt = $db->prepare("SELECT COALESCE(MAX(seed_number), 0) + 1 FROM tournament_teams WHERE tournament_id = ?");
        $stmt->execute([$tournamentId]);
        $seed = $stmt->fetchColumn();
        
        $teamName = $teamNames[$i % count($teamNames)] . ($i >= count($teamNames) ? ' ' . (floor($i / count($teamNames)) + 1) : '');
        $color = $colors[$i % count($colors)];
        
        $stmt = $db->prepare("INSERT INTO tournament_teams (tournament_id, team_name, team_color, seed_number) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tournamentId, trim($teamName), $color, $seed]);
        $teamId = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO student_teams (tournament_team_id, team_name) VALUES (?, ?)");
        $stmt->execute([$teamId, trim($teamName)]);
        $studentTeamId = $db->lastInsertId();
        
        $teams[] = ['tournament_team_id' => $teamId, 'student_team_id' => $studentTeamId, 'members' => []];
    }
    
    foreach ($studentIds as $index => $studentId) {
        $teamIndex = $index % $teamCount;
        $teams[$teamIndex]['members'][] = $studentId;
        
        $stmt = $db->prepare("INSERT INTO student_team_members (student_team_id, student_id) VALUES (?, ?)");
        $stmt->execute([$teams[$teamIndex]['student_team_id'], $studentId]);
    }
    
    foreach ($teams as $team) {
        if (!empty($team['members'])) {
            $db->prepare("UPDATE student_teams SET captain_student_id = ? WHERE id = ?")
               ->execute([$team['members'][0], $team['student_team_id']]);
        }
    }
    
    jsonSuccess(['teams' => count($teams)], "تم إنشاء $teamCount فريق وتوزيع الطلاب");
}

// ============================================================
// MATCHES
// ============================================================
function getMatches() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    $round = getParam('round');
    $bracket = getParam('bracket');
    
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $sql = "
        SELECT m.*,
               t1.team_name as team1_name, t1.team_color as team1_color,
               t2.team_name as team2_name, t2.team_color as team2_color,
               w.team_name as winner_name
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        LEFT JOIN tournament_teams w ON m.winner_team_id = w.id
        WHERE m.tournament_id = ?
    ";
    $params = [$tournamentId];
    
    if ($round) {
        $sql .= " AND m.round_number = ?";
        $params[] = $round;
    }
    if ($bracket) {
        $sql .= " AND m.bracket_type = ?";
        $params[] = $bracket;
    }
    
    $sql .= " ORDER BY m.bracket_type, m.round_number, m.match_number";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function generateMatches() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$tournamentId, schoolId()]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة أو ليست من صلاحياتك!');
    
    try {
        $db->beginTransaction();
        
        // Delete existing matches and standings
        $db->prepare("DELETE FROM matches WHERE tournament_id = ?")->execute([$tournamentId]);
        try { $db->prepare("DELETE FROM standings WHERE tournament_id = ?")->execute([$tournamentId]); } catch(Exception $e) {}
        
        generateMatchesForTournament($tournamentId, $tournament['type'], $tournament['randomize_teams']);
        
        $db->commit();
        jsonSuccess(null, 'تم توليد المباريات');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('حدث خطأ أثناء الإنشاء: ' . $e->getMessage());
    }
}

function generateMatchesForTournament($tournamentId, $type, $randomize) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournament_teams WHERE tournament_id = ? ORDER BY seed_number");
    $stmt->execute([$tournamentId]);
    $teams = $stmt->fetchAll();
    
    if (count($teams) < 2) {
        throw new Exception('يجب إضافة فريقين على الأقل');
    }
    
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
// MIXED TOURNAMENT - المجموعات + خروج المغلوب
// ============================================================
function generateMixedTournament($tournamentId, $teams) {
    $db = getDB();
    $n = count($teams);
    
    // تقسيم المجموعات (4 فرق لكل مجموعة تقريباً)
    $groupCount = max(2, (int)ceil($n / 4));
    $groupNames = range('A', 'Z');
    
    $groups = [];
    for ($i = 0; $i < $n; $i++) {
        $groupIndex = $i % $groupCount;
        $groupName = $groupNames[$groupIndex];
        $groups[$groupName][] = $teams[$i];
        
        $db->prepare("UPDATE tournament_teams SET group_name = ? WHERE id = ?")
           ->execute([$groupName, $teams[$i]['id']]);
    }
    
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
                $db->prepare("INSERT INTO standings (tournament_id, school_id, team_id, group_name) VALUES (?, ?, ?, ?)")
                   ->execute([$tournamentId, schoolId(), $team['id'], $groupName]);
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
            $team1 = $pair[0]; $team2 = $pair[1];
            if (!$team1['id'] || !$team2['id']) continue;
            
            $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, team1_id, team2_id, group_name, status)
                VALUES (?, ?, ?, ?, 'main', ?, ?, ?, 'scheduled')
            ")->execute([$tournamentId, schoolId(), $round, $matchNumber, $team1['id'], $team2['id'], $groupName]);
            $matchNumber++;
        }
        $last = array_pop($rotating);
        array_unshift($rotating, $last);
    }
}

function generateMixedKnockoutStage($tournamentId) {
    $db = getDB();
    
    // جلب ترتيب المجموعات
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
            $g1 = $groupNames[$i]; $g2 = $groupNames[$i + 1];
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

/**
 * Single Elimination - خروج المغلوب من مرة واحدة
 * ================================================
 * المنطق:
 * 1. حساب حجم القوس (أقرب قوة للعدد 2)
 * 2. إنشاء كل المباريات لكل الجولات (فارغة)
 * 3. ربط كل مباراة بالمباراة التالية (next_match_id)
 * 4. وضع الفرق في مباريات الجولة الأولى
 * 5. معالجة BYE: الفريق بدون منافس يتأهل تلقائياً
 * 
 * مثال مع 5 فرق:
 *   الجولة 1: [A vs B] [C vs D] [E vs BYE] [BYE vs BYE]
 *   الجولة 2: [فائز1 vs فائز2] [E vs ...]
 *   النهائي: [فائز vs فائز]
 */
function generateSingleElimination($tournamentId, $teams, $startRound = 1) {
    $db = getDB();
    $n = count($teams);
    if ($n < 2) return;
    
    // حساب حجم القوس (أقرب قوة للعدد 2)
    $rounds = (int)ceil(log($n, 2));
    $bracketSize = (int)pow(2, $rounds);
    
    $stmt = $db->prepare("SELECT COALESCE(MAX(match_number), 0) FROM matches WHERE tournament_id = ?");
    $stmt->execute([$tournamentId]);
    $matchNumber = (int)$stmt->fetchColumn() + 1;
    
    // ======== المرحلة 1: إنشاء كل المباريات (فارغة) ========
    $matchIds = []; // [round][position] => match_id

    for ($round = 1; $round <= $rounds; $round++) {
        $actualRound = $round + $startRound - 1;
        $matchesInRound = (int)($bracketSize / pow(2, $round));
        $matchIds[$round] = [];
        $bracketType = ($round === $rounds) ? 'final' : 'main';

        for ($pos = 0; $pos < $matchesInRound; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            $stmt->execute([$tournamentId, schoolId(), $actualRound, $matchNumber, $bracketType]);
            $matchIds[$round][$pos] = (int)$db->lastInsertId();
            $matchNumber++;
        }
    }

    // ======== إنشاء مباراة المركز الثالث (إذا كانت هناك نصف نهائي) ========
    $thirdPlaceMatchId = null;
    $semiFinalsRound = $rounds - 1; // الجولة التي تحتوي نصف النهائي
    if ($rounds >= 2 && isset($matchIds[$semiFinalsRound]) && count($matchIds[$semiFinalsRound]) >= 2) {
        $finalRoundActual = $rounds + $startRound - 1;
        $db->prepare("
            INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, status)
            VALUES (?, ?, ?, ?, 'third_place', 'scheduled')
        ")->execute([$tournamentId, schoolId(), $finalRoundActual, $matchNumber]);
        $thirdPlaceMatchId = (int)$db->lastInsertId();
        $matchNumber++;
    }

    // ======== المرحلة 2: ربط المباريات ========
    for ($round = 1; $round < $rounds; $round++) {
        foreach ($matchIds[$round] as $pos => $matchId) {
            $nextPos = (int)floor($pos / 2);
            $nextSlot = ($pos % 2 === 0) ? 'team1' : 'team2';
            $nextMatchId = $matchIds[$round + 1][$nextPos] ?? null;

            if ($nextMatchId) {
                $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = ? WHERE id = ?")
                   ->execute([$nextMatchId, $nextSlot, $matchId]);
            }

            // نصف النهائي → الخاسر يذهب لمباراة المركز الثالث
            if ($round === $semiFinalsRound && $thirdPlaceMatchId) {
                $loserSlot = ($pos % 2 === 0) ? 'team1' : 'team2';
                $db->prepare("UPDATE matches SET loser_next_match_id = ?, loser_next_match_slot = ? WHERE id = ?")
                   ->execute([$thirdPlaceMatchId, $loserSlot, $matchId]);
            }
        }
    }
    
    // ======== المرحلة 3: توزيع الفرق في الجولة الأولى ========
    // ملء الفراغات بـ null (BYE)
    $paddedTeams = $teams;
    while (count($paddedTeams) < $bracketSize) {
        $paddedTeams[] = null;
    }
    
    foreach ($matchIds[1] as $pos => $matchId) {
        $t1Idx = $pos * 2;
        $t2Idx = $pos * 2 + 1;
        $team1 = $paddedTeams[$t1Idx] ?? null;
        $team2 = $paddedTeams[$t2Idx] ?? null;
        $team1Id = $team1 ? ($team1['id'] ?? null) : null;
        $team2Id = $team2 ? ($team2['id'] ?? null) : null;
        
        if ($team1Id && $team2Id) {
            // مباراة حقيقية - فريقان حقيقيان
            $db->prepare("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?")
               ->execute([$team1Id, $team2Id, $matchId]);
               
        } elseif ($team1Id || $team2Id) {
            // مباراة BYE - فريق واحد فقط يتأهل تلقائياً
            $realTeamId = $team1Id ?: $team2Id;
            $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
               ->execute([$realTeamId, $realTeamId, $matchId]);
            
            // ترقية الفائز للمباراة التالية
            advanceWinnerToNext($db, $matchId, $realTeamId);
               
        } else {
            // كلاهما BYE - مباراة فارغة تماماً (تُخفى)
            $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed' WHERE id = ?")
               ->execute([$matchId]);
        }
    }
    
    // ======== المرحلة 4: معالجة BYE متتالية ========
    // إذا تأهل فريق من BYE ومنافسه في الجولة التالية أيضاً BYE، يتأهل تلقائياً
    for ($round = 2; $round <= $rounds; $round++) {
        foreach ($matchIds[$round] as $pos => $matchId) {
            $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
            $stmt->execute([$matchId]);
            $match = $stmt->fetch();
            
            // إذا أحد الفريقين موجود والآخر لا (والمباراة المقابلة كانت BYE فارغة)
            if ($match && (($match['team1_id'] && !$match['team2_id']) || (!$match['team1_id'] && $match['team2_id']))) {
                // تحقق: هل المباراة المقابلة التي تُغذي الفريق الناقص كانت BYE فارغة؟
                $feedPos1 = $pos * 2;
                $feedPos2 = $pos * 2 + 1;
                $prevRound = $round - 1;
                
                $feedMatch1 = isset($matchIds[$prevRound][$feedPos1]) ? $matchIds[$prevRound][$feedPos1] : null;
                $feedMatch2 = isset($matchIds[$prevRound][$feedPos2]) ? $matchIds[$prevRound][$feedPos2] : null;
                
                $allFeedsCompleted = true;
                foreach ([$feedMatch1, $feedMatch2] as $fmId) {
                    if ($fmId) {
                        $stmt2 = $db->prepare("SELECT status, winner_team_id FROM matches WHERE id = ?");
                        $stmt2->execute([$fmId]);
                        $fm = $stmt2->fetch();
                        if ($fm && $fm['status'] !== 'completed') $allFeedsCompleted = false;
                    }
                }
                
                if ($allFeedsCompleted) {
                    $realTeamId = $match['team1_id'] ?: $match['team2_id'];
                    $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                       ->execute([$realTeamId, $realTeamId, $matchId]);
                    advanceWinnerToNext($db, $matchId, $realTeamId);
                }
            }
        }
    }
}

/**
 * ترقية الفائز إلى المباراة التالية
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
    }
}

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
    $winnersMatchIds = []; // [round][position] => match_id
    
    for ($round = 1; $round <= $wRounds; $round++) {
        $matchesInRound = (int)($bracketSize / pow(2, $round));
        $winnersMatchIds[$round] = [];
        
        for ($pos = 0; $pos < $matchesInRound; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, ?, 'main', 'scheduled')
            ");
            $stmt->execute([$tournamentId, schoolId(), $round, $matchNumber]);
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
    // الهيكل لـ 8 فرق (wRounds=3):
    //   L جولة 1: مباراتان (خاسرو الجولة 1 من الفائزين)
    //   L جولة 2: مباراتان (فائزو L1 ضد خاسري الجولة 2 من الفائزين)
    //   L جولة 3: مباراة واحدة (فائزو L2 ضد بعضهم)
    //   L جولة 4: مباراة واحدة (فائز L3 ضد خاسر نصف نهائي الفائزين)
    
    $losersMatchIds = []; // [round][position] => match_id
    $lMatchCount = (int)($bracketSize / 4); // مباريات الجولة الأولى من الخاسرين
    
    for ($lr = 1; $lr <= $lRounds; $lr++) {
        if ($lr === 1) {
            $lMatchCount = max(1, (int)($bracketSize / 4));
        } elseif ($lr % 2 === 0) {
            // جولة زوجية: نفس العدد (استيعاب الخاسرين النازلين من الفائزين)
            $lMatchCount = max(1, $lMatchCount);
        } else {
            // جولة فردية: النصف (الناجون يلعبون ضد بعض)
            $lMatchCount = max(1, (int)ceil($lMatchCount / 2));
        }
        
        $losersMatchIds[$lr] = [];
        
        for ($pos = 0; $pos < $lMatchCount; $pos++) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, status)
                VALUES (?, ?, ?, ?, 'losers', 'scheduled')
            ");
            $stmt->execute([$tournamentId, schoolId(), $lr, $matchNumber]);
            $losersMatchIds[$lr][$pos] = (int)$db->lastInsertId();
            $matchNumber++;
        }
    }
    
    // ربط شعبة الخاسرين داخلياً: الفائز → المباراة التالية
    for ($lr = 1; $lr < $lRounds; $lr++) {
        foreach ($losersMatchIds[$lr] as $pos => $matchId) {
            $nextRound = $lr + 1;
            if (!isset($losersMatchIds[$nextRound])) continue;
            
            $nextMatchCount = count($losersMatchIds[$nextRound]);
            
            if ($lr % 2 === 1) {
                // جولة فردية: الفائزون يذهبون لـ team1 في الجولة الزوجية التالية
                $nextPos = min($pos, $nextMatchCount - 1);
                $nextSlot = 'team1';
            } else {
                // جولة زوجية: الفائزون يتقدمون للجولة الفردية التالية
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
    // خاسرو جولة 1 فائزين → جولة 1 خاسرين
    foreach ($winnersMatchIds[1] as $pos => $matchId) {
        $lrPos = (int)floor($pos / 2);
        $losersTargetId = $losersMatchIds[1][$lrPos] ?? null;
        
        if ($losersTargetId) {
            $db->prepare("UPDATE matches SET loser_next_match_id = ? WHERE id = ?")
               ->execute([$losersTargetId, $matchId]);
        }
    }
    
    // خاسرو جولة 2+ فائزين → الجولات الزوجية من الخاسرين (كـ team2)
    for ($wr = 2; $wr <= $wRounds; $wr++) {
        $targetLR = ($wr - 1) * 2; // جولة 2 فائزين → L جولة 2، جولة 3 → L جولة 4، ...
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
        INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, status)
        VALUES (?, ?, ?, ?, 'final', 'scheduled')
    ");
    $stmt->execute([$tournamentId, schoolId(), $wRounds + 1, $matchNumber]);
    $grandFinalId = (int)$db->lastInsertId();
    $matchNumber++;
    
    // فائز شعبة الفائزين → team1 في النهائي الكبير
    $wFinalId = end($winnersMatchIds[$wRounds]);
    if ($wFinalId) {
        $db->prepare("UPDATE matches SET next_match_id = ?, next_match_slot = 'team1' WHERE id = ?")
           ->execute([$grandFinalId, $wFinalId]);
    }
    
    // فائز شعبة الخاسرين → team2 في النهائي الكبير
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
            // مباراة حقيقية
            $db->prepare("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?")
               ->execute([$team1Id, $team2Id, $matchId]);
        } elseif ($team1Id || $team2Id) {
            // BYE - فريق واحد يتأهل تلقائياً
            $realTeamId = $team1Id ?: $team2Id;
            $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
               ->execute([$realTeamId, $realTeamId, $matchId]);
            advanceWinnerToNext($db, $matchId, $realTeamId);
        } else {
            // كلاهما BYE
            $db->prepare("UPDATE matches SET is_bye = 1, status = 'completed' WHERE id = ?")
               ->execute([$matchId]);
        }
    }
    
    // ================================================================
    // الخطوة 6: معالجة BYE متتالية في شعبة الفائزين
    // ================================================================
    for ($round = 2; $round <= $wRounds; $round++) {
        foreach ($winnersMatchIds[$round] as $pos => $matchId) {
            $stmt = $db->prepare("SELECT * FROM matches WHERE id = ?");
            $stmt->execute([$matchId]);
            $match = $stmt->fetch();
            
            if ($match && (($match['team1_id'] && !$match['team2_id']) || (!$match['team1_id'] && $match['team2_id']))) {
                $feedPos1 = $pos * 2;
                $feedPos2 = $pos * 2 + 1;
                $prevRound = $round - 1;
                
                $feedMatch1 = isset($winnersMatchIds[$prevRound][$feedPos1]) ? $winnersMatchIds[$prevRound][$feedPos1] : null;
                $feedMatch2 = isset($winnersMatchIds[$prevRound][$feedPos2]) ? $winnersMatchIds[$prevRound][$feedPos2] : null;
                
                $allFeedsCompleted = true;
                foreach ([$feedMatch1, $feedMatch2] as $fmId) {
                    if ($fmId) {
                        $stmt2 = $db->prepare("SELECT status FROM matches WHERE id = ?");
                        $stmt2->execute([$fmId]);
                        $fm = $stmt2->fetch();
                        if ($fm && $fm['status'] !== 'completed') $allFeedsCompleted = false;
                    }
                }
                
                if ($allFeedsCompleted) {
                    $realTeamId = $match['team1_id'] ?: $match['team2_id'];
                    $db->prepare("UPDATE matches SET team1_id = ?, is_bye = 1, status = 'completed', winner_team_id = ? WHERE id = ?")
                       ->execute([$realTeamId, $realTeamId, $matchId]);
                    advanceWinnerToNext($db, $matchId, $realTeamId);
                }
            }
        }
    }
}

function generateRoundRobin($tournamentId, $teams, $isDouble) {
    $db = getDB();
    $n = count($teams);
    
    if ($n % 2 !== 0) {
        $teams[] = ['id' => null, 'team_name' => 'BYE', 'is_bye' => true];
        $n++;
    }
    
    foreach ($teams as $team) {
        if ($team['id']) {
            try {
                $db->prepare("INSERT INTO standings (tournament_id, school_id, team_id) VALUES (?, ?, ?)")
                   ->execute([$tournamentId, schoolId(), $team['id']]);
            } catch (Exception $e) {}
        }
    }
    
    $rounds = $n - 1;
    $matchNumber = 1;
    $rotation = array_slice($teams, 1);
    
    for ($round = 1; $round <= $rounds; $round++) {
        $pairings = [[$teams[0], $rotation[0]]];
        
        for ($i = 1; $i < $n / 2; $i++) {
            $home = $rotation[$i];
            $away = $rotation[($n - 1) - $i];
            $pairings[] = [$home, $away];
        }
        
        foreach ($pairings as $pair) {
            $team1 = $pair[0];
            $team2 = $pair[1];
            
            if (($team1['id'] ?? null) === null || ($team2['id'] ?? null) === null) {
                continue;
            }
            
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, 
                    team1_id, team2_id, status)
                VALUES (?, ?, ?, ?, 'main', ?, ?, 'scheduled')
            ");
            $stmt->execute([
                $tournamentId, schoolId(), $round, $matchNumber,
                $team1['id'], $team2['id']
            ]);
            $matchNumber++;
        }
        
        $last = array_pop($rotation);
        array_splice($rotation, 0, 0, [$last]);
    }
    
    if ($isDouble) {
        $stmt = $db->prepare("
            SELECT team1_id, team2_id, round_number FROM matches 
            WHERE tournament_id = ? AND bracket_type = 'main'
            ORDER BY round_number, match_number
        ");
        $stmt->execute([$tournamentId]);
        $firstLegMatches = $stmt->fetchAll();
        
        foreach ($firstLegMatches as $match) {
            $stmt = $db->prepare("
                INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, 
                    team1_id, team2_id, status)
                VALUES (?, ?, ?, ?, 'main', ?, ?, 'scheduled')
            ");
            $stmt->execute([
                $tournamentId, 
                schoolId(),
                $match['round_number'] + $rounds, 
                $matchNumber,
                $match['team2_id'],
                $match['team1_id'],
            ]);
            $matchNumber++;
        }
    }
}

function updateMatch() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $id = $data['id'] ?? getParam('id');
    
    if (!$id) jsonError('معرّف المباراة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE matches SET
            match_date = ?, match_time = ?, venue = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $data['match_date'] ?? null,
        $data['match_time'] ?? null,
        sanitize($data['venue'] ?? ''),
        sanitize($data['notes'] ?? ''),
        $id
    ]);
    
    jsonSuccess(null, 'تم تحديث المباراة');
}

function saveMatchResult() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    validateRequired($data, ['match_id', 'team1_score', 'team2_score']);
    
    $db = getDB();
    $matchId = (int)$data['match_id'];
    $team1Score = (int)$data['team1_score'];
    $team2Score = (int)$data['team2_score'];
    
    $stmt = $db->prepare("SELECT * FROM matches m JOIN tournaments t ON m.tournament_id = t.id WHERE m.id = ? AND t.school_id = ?");
    $stmt->execute([$matchId, schoolId()]);
    $match = $stmt->fetch();
    
    if (!$match) jsonError('المباراة غير موجودة أو لا تملك صلاحية الوصول');
    if (!$match['team1_id'] || !$match['team2_id']) jsonError('المباراة غير مكتملة الفرق');
    if ($match['status'] === 'completed') jsonError('هذه المباراة منتهية بالفعل');
    
    // جلب نوع البطولة
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$match['tournament_id'], schoolId()]);
    $tournament = $stmt->fetch();
    
    // في نظام الخروج المباشر/المزدوج/المزيج (الأدوار الإقصائية): لا يمكن التعادل!
    $isElimination = strpos($tournament['type'], 'elimination') !== false;
    $isMixedKnockout = ($tournament['type'] === 'mixed' && !$match['group_name']); // مرحلة knockout في المزيج
    $isKnockout = $isElimination || $isMixedKnockout;
    
    if ($isKnockout && $team1Score === $team2Score) {
        jsonError('في مرحلة خروج المغلوب لا يمكن التعادل - يجب تحديد فائز');
    }
    
    $winnerId = null;
    $loserId = null;
    if ($team1Score > $team2Score) {
        $winnerId = $match['team1_id'];
        $loserId = $match['team2_id'];
    } elseif ($team2Score > $team1Score) {
        $winnerId = $match['team2_id'];
        $loserId = $match['team1_id'];
    }
    
    $stmt = $db->prepare("
        UPDATE matches SET 
            team1_score = ?, team2_score = ?, 
            winner_team_id = ?, loser_team_id = ?,
            status = 'completed'
        WHERE id = ?
    ");
    $stmt->execute([$team1Score, $team2Score, $winnerId, $loserId, $matchId]);

    // ─── تسجيل الهدافين (جديد) ─────────────────────────
    if (!empty($data['scorers']) && is_array($data['scorers'])) {
        $stmtScorer = $db->prepare("
            INSERT INTO tournament_player_stats (tournament_id, school_id, team_id, student_id, goals)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE goals = goals + VALUES(goals)
        ");
        foreach ($data['scorers'] as $s) {
            if (!empty($s['student_id']) && !empty($s['team_id'])) {
                $stmtScorer->execute([
                    $match['tournament_id'],
                    schoolId(),
                    (int)$s['team_id'],
                    (int)$s['student_id'],
                    (int)$s['goals']
                ]);
            }
        }
    }
    
    // Notifications for Tournament Results
    try {
        $nameStmt = $db->prepare("SELECT team_name FROM tournament_teams WHERE id = ?");
        
        $nameStmt->execute([$match['team1_id']]);
        $t1Name = $nameStmt->fetchColumn();
        
        $nameStmt->execute([$match['team2_id']]);
        $t2Name = $nameStmt->fetchColumn();
        
        $notifTitle = "نتيجة مباراة: " . ($tournament['name'] ?? 'البطولة');
        $notifMsg = "انتهت المباراة بين فريق ({$t1Name}) وفريق ({$t2Name}) بنتيجة ({$team1Score} - {$team2Score}).";

        // If class-based, notify both classes
        if (($tournament['team_mode'] ?? '') === 'class_based') {
            $classStmt = $db->prepare("SELECT class_id FROM tournament_teams WHERE id = ?");
            
            $classStmt->execute([$match['team1_id']]);
            $c1Id = $classStmt->fetchColumn();
            
            $classStmt->execute([$match['team2_id']]);
            $c2Id = $classStmt->fetchColumn();
            
            if ($c1Id) notifyClassParents($c1Id, 'general', $notifTitle, $notifMsg);
            if ($c2Id) notifyClassParents($c2Id, 'general', $notifTitle, $notifMsg);
        } else {
            // If student-based, notify parents of all players in both teams
            $playerStmt = $db->prepare("SELECT DISTINCT student_id FROM student_team_members stm 
                                       JOIN student_teams st ON stm.student_team_id = st.id 
                                       WHERE st.tournament_team_id IN (?, ?)");
            $playerStmt->execute([$match['team1_id'], $match['team2_id']]);
            $playerIds = $playerStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($playerIds as $pid) {
                notifyStudentParents($pid, 'general', $notifTitle, $notifMsg);
            }
        }
    } catch (Exception $e) {
        // Log notification error but don't stop the match result save
        error_log("Notification error in tournament: " . $e->getMessage());
    }

    if ($isKnockout) {
        // ترقية الفائز للمباراة التالية
        if ($winnerId && $match['next_match_id'] && $match['next_match_slot']) {
            $column = $match['next_match_slot'] === 'team1' ? 'team1_id' : 'team2_id';
            $db->prepare("UPDATE matches SET $column = ? WHERE id = ?")
               ->execute([$winnerId, $match['next_match_id']]);
        }
        
        // تحويل خاسر نصف النهائي → مباراة المركز الثالث (لكل أنواع الإقصاء)
        if ($loserId && $match['loser_next_match_id'] && $match['bracket_type'] !== 'third_place') {
            $loserSlot = ($match['loser_next_match_slot'] ?? 'team1') === 'team1' ? 'team1_id' : 'team2_id';
            $db->prepare("UPDATE matches SET $loserSlot = ? WHERE id = ?")
               ->execute([$loserId, $match['loser_next_match_id']]);
        }

        // خروج المغلوب المزدوج
        if ($tournament['type'] === 'double_elimination' && $loserId) {
            $bracketType = $match['bracket_type'];

            // زيادة عداد الخسارات
            $db->prepare("UPDATE tournament_teams SET elimination_count = elimination_count + 1 WHERE id = ?")
               ->execute([$loserId]);

            if ($bracketType === 'main') {
                // خاسر من شعبة الفائزين → ينتقل لشعبة الخاسرين
                if ($match['loser_next_match_id']) {
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
            } elseif ($bracketType === 'losers') {
                // خاسر من شعبة الخاسرين → يُقصى نهائياً (خسارة ثانية)
                $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
                   ->execute([$loserId]);
            } elseif ($bracketType === 'final') {
                // النهائي الكبير
                $stmt = $db->prepare("SELECT elimination_count FROM tournament_teams WHERE id = ?");
                $stmt->execute([$loserId]);
                $elimCount = (int)$stmt->fetchColumn();
                
                if ($elimCount <= 1) {
                    // الخاسر من شعبة الفائزين (أول خسارة) → مباراة إعادة (reset match)
                    // بطل الخاسرين فاز، لكن بطل الفائزين خسر لأول مرة فقط
                    // نحتاج مباراة فاصلة!
                    $db->beginTransaction();
                    try {
                        $maxMatchNum = $db->prepare("SELECT MAX(match_number) FROM matches WHERE tournament_id = ? AND school_id = ?");
                        $maxMatchNum->execute([$match['tournament_id'], schoolId()]);
                        $nextMatchNum = (int)$maxMatchNum->fetchColumn() + 1;
                        
                        $stmt = $db->prepare("
                            INSERT INTO matches (tournament_id, school_id, round_number, match_number, bracket_type, 
                                team1_id, team2_id, status)
                            VALUES (?, ?, ?, ?, 'final', ?, ?, 'scheduled')
                        ");
                        $stmt->execute([
                            $match['tournament_id'],
                            schoolId(),
                            $match['round_number'] + 1,
                            $nextMatchNum,
                            $winnerId,  // فائز النهائي الكبير (بطل الخاسرين)
                            $loserId    // خاسر النهائي الكبير (بطل الفائزين)
                        ]);
                        $db->commit();
                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log("Error creating double elimination reset match: " . $e->getMessage());
                        jsonError('حدث خطأ أثناء إنشاء مباراة الإعادة');
                    }
                } else {
                    // خسارة ثانية → يُقصى نهائياً
                    $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
                       ->execute([$loserId]);
                }
            }
        }
        
        // خروج المغلوب المباشر - الخاسر يُقصى فوراً
        if ($tournament['type'] === 'single_elimination' && $loserId) {
            $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
               ->execute([$loserId]);
        }

        // في نظام الخلط - الأدوار الإقصائية - الخاسر يُقصى فوراً
        if ($isMixedKnockout && $loserId) {
            $db->prepare("UPDATE tournament_teams SET is_eliminated = 1 WHERE id = ?")
               ->execute([$loserId]);
        }
    } else {
        // دوري أو مرحلة المجموعات في نظام الخلط
        updateStandings($match['tournament_id'], $match['team1_id'], $match['team2_id'], 
                       $team1Score, $team2Score, $tournament);
                       
        if ($tournament['type'] === 'mixed' && $match['group_name']) {
            // التحقق مما إذا انتهت جميع مباريات المجموعات
            $stmt = $db->prepare("SELECT COUNT(*) FROM matches WHERE tournament_id = ? AND group_name IS NOT NULL AND status != 'completed'");
            $stmt->execute([$match['tournament_id']]);
            if ((int)$stmt->fetchColumn() === 0) {
                generateMixedKnockoutStage($match['tournament_id']);
            }
        }
    }
    
    logActivity('match_result', 'match', $matchId, "$team1Score - $team2Score");
    jsonSuccess(['winner_id' => $winnerId], 'تم حفظ النتيجة');
}

function updateStandings($tournamentId, $team1Id, $team2Id, $score1, $score2, $tournament) {
    $db = getDB();
    
    $pointsWin = $tournament['points_win'];
    $pointsDraw = $tournament['points_draw'];
    $pointsLoss = $tournament['points_loss'];
    
    if ($score1 > $score2) {
        $t1P = $pointsWin; $t2P = $pointsLoss; $t1R = 'W'; $t2R = 'L';
    } elseif ($score2 > $score1) {
        $t1P = $pointsLoss; $t2P = $pointsWin; $t1R = 'L'; $t2R = 'W';
    } else {
        $t1P = $pointsDraw; $t2P = $pointsDraw; $t1R = 'D'; $t2R = 'D';
    }
    
    $updateSql = "UPDATE standings SET
        played = played + 1,
        wins = wins + ?, draws = draws + ?, losses = losses + ?,
        goals_for = goals_for + ?, goals_against = goals_against + ?,
        goal_difference = goal_difference + ?,
        points = points + ?,
        form = CONCAT(SUBSTRING(COALESCE(form, ''), 1, 4), ?)
        WHERE tournament_id = ? AND team_id = ?";
    
    $db->prepare($updateSql)->execute([
        $t1R === 'W' ? 1 : 0, $t1R === 'D' ? 1 : 0, $t1R === 'L' ? 1 : 0,
        $score1, $score2, $score1 - $score2, $t1P, $t1R,
        $tournamentId, $team1Id
    ]);
    
    $db->prepare($updateSql)->execute([
        $t2R === 'W' ? 1 : 0, $t2R === 'D' ? 1 : 0, $t2R === 'L' ? 1 : 0,
        $score2, $score1, $score2 - $score1, $t2P, $t2R,
        $tournamentId, $team2Id
    ]);
}

// ============================================================
// SMART MATCH SCHEDULING
// ============================================================
function scheduleMatches() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    $tournamentId = (int)($data['tournament_id'] ?? getParam('tournament_id'));
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');

    $startDate    = $data['start_date']    ?? date('Y-m-d');
    $startTime    = $data['start_time']    ?? '09:00';
    $matchDuration = (int)($data['match_duration'] ?? 60);  // minutes per match
    $breakBetween  = (int)($data['break_between']  ?? 30);  // minutes break between matches
    $matchesPerDay = (int)($data['matches_per_day'] ?? 4);  // max matches per day
    $preview       = (bool)($data['preview'] ?? false);     // true = preview only, false = save

    $db = getDB();

    // جلب كل المباريات غير المكتملة وغير الباي مرتبة حسب الجولة
    $stmt = $db->prepare("
        SELECT m.*,
               t1.team_name as team1_name,
               t2.team_name as team2_name
        FROM matches m
        JOIN tournaments t ON m.tournament_id = t.id
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE m.tournament_id = ? AND t.school_id = ?
          AND m.status != 'completed'
          AND (m.is_bye IS NULL OR m.is_bye = 0)
          AND m.team1_id IS NOT NULL
          AND m.team2_id IS NOT NULL
        ORDER BY m.round_number ASC, m.bracket_type ASC, m.match_number ASC
    ");
    $stmt->execute([$tournamentId, schoolId()]);
    $matches = $stmt->fetchAll();

    if (empty($matches)) {
        jsonError('لا توجد مباريات مجدولة لتوزيعها');
    }

    // ====================================================
    // خوارزمية التوزيع العادل
    // المبدأ: كل فريق لا يلعب مباراتين في نفس اليوم
    // ====================================================
    $currentDate  = new DateTime($startDate);
    $currentTime  = $startTime;
    $dayMatchCount = 0;
    $teamLastDate  = []; // team_id => last date they played
    $schedule     = [];

    // تجميع المباريات حسب الجولة (round)
    $matchesByRound = [];
    foreach ($matches as $m) {
        $matchesByRound[$m['round_number']][] = $m;
    }
    ksort($matchesByRound);

    // دالة مساعدة: انتقل لليوم التالي مع استبعاد الويكند
    $nextDay = function() use (&$currentDate, &$currentTime, &$dayMatchCount, $startTime) {
        $currentDate->modify('+1 day');
        // إذا كان الجمعة (5) أو السبت (6)، انتقل للأحد (0) أو اليوم التالي المتاح
        while (in_array((int)$currentDate->format('w'), [5, 6])) { // 5=Friday, 6=Saturday (PHP w is 0-Sunday to 6-Saturday)
            $currentDate->modify('+1 day');
        }
        $currentTime  = $startTime;
        $dayMatchCount = 0;
    };

    // التأكد من أن البداية ليست في الويكند
    while (in_array((int)$currentDate->format('w'), [5, 6])) {
        $currentDate->modify('+1 day');
    }

    // دالة مساعدة: احسب الوقت التالي
    $addMinutes = function($time, $minutes) {
        [$h, $m] = explode(':', $time);
        $total = (int)$h * 60 + (int)$m + $minutes;
        return sprintf('%02d:%02d', (int)($total / 60) % 24, $total % 60);
    };

    foreach ($matchesByRound as $round => $roundMatches) {
        // خلط المباريات عشوائياً في الجولة الواحدة لضمان العدالة
        shuffle($roundMatches);
        
        foreach ($roundMatches as $match) {
            $t1 = $match['team1_id'];
            $t2 = $match['team2_id'];

            // محاولة إيجاد يوم مناسب
            $maxTries = 90; 
            $tries = 0;
            while ($tries < $maxTries) {
                $todayStr = $currentDate->format('Y-m-d');
                
                // شروط اليوم المناسب:
                // 1. الفريق لم يلعب اليوم
                // 2. اليوم ليس ممتلئاً
                // 3. (أفضلية) الفريق لم يلعب أمس (لتجنب الإرهاق إذا أمكن)
                
                $team1PlayedToday = (($teamLastDate[$t1] ?? '') === $todayStr);
                $team2PlayedToday = (($teamLastDate[$t2] ?? '') === $todayStr);
                $dayFull = ($dayMatchCount >= $matchesPerDay);

                if (!$team1PlayedToday && !$team2PlayedToday && !$dayFull) {
                    // تحقق من "الفجوة العادلة" - محاولة ترك يوم راحة بين المباريات إذا لم نصل للحد الأقصى
                    $yesterday = (clone $currentDate)->modify('-1 day')->format('Y-m-d');
                    $t1PlayedYesterday = (($teamLastDate[$t1] ?? '') === $yesterday);
                    $t2PlayedYesterday = (($teamLastDate[$t2] ?? '') === $yesterday);
                    
                    if (($t1PlayedYesterday || $t1PlayedYesterday) && $tries < 5) {
                        // نحاول تأجيلها لليوم التالي لترك فجوة، بشرط عدم التأجيل للأبد
                        $nextDay();
                        $tries++;
                        continue;
                    }
                    break; 
                }
                $nextDay();
                $tries++;
            }

            $todayStr = $currentDate->format('Y-m-d');
            $schedule[] = [
                'match_id'   => $match['id'],
                'match_date' => $todayStr,
                'match_time' => $currentTime,
                'team1_name' => $match['team1_name'],
                'team2_name' => $match['team2_name'],
                'round'      => $round,
                'slot'       => $dayMatchCount + 1,
            ];

            $teamLastDate[$t1]  = $todayStr;
            $teamLastDate[$t2]  = $todayStr;
            $dayMatchCount++;
            $currentTime = $addMinutes($currentTime, $matchDuration + $breakBetween);
        }

        // بعد كل جولة نترك يوم راحة إضافي إذا أمكن
        $nextDay();
    }

    // إذا معاينة فقط
    if ($preview) {
        jsonSuccess($schedule, 'معاينة الجدول المقترح');
    }

    // حفظ الجدول في قاعدة البيانات
    $updateStmt = $db->prepare("UPDATE matches SET match_date = ?, match_time = ? WHERE id = ?");
    foreach ($schedule as $s) {
        $updateStmt->execute([$s['match_date'], $s['match_time'], $s['match_id']]);
    }

    logActivity('schedule_matches', 'tournament', $tournamentId, count($schedule) . ' matches');
    jsonSuccess($schedule, 'تم جدولة ' . count($schedule) . ' مباراة بنجاح');
}

// ============================================================
// STANDINGS
// ============================================================
function getStandings() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT s.*, tt.team_name, tt.team_color, tt.class_id,
               c.name as class_name
        FROM standings s
        JOIN tournament_teams tt ON s.team_id = tt.id
        JOIN tournaments t ON s.tournament_id = t.id
        LEFT JOIN classes c ON tt.class_id = c.id
        WHERE s.tournament_id = ? AND t.school_id = ?
        ORDER BY COALESCE(s.group_name, '') ASC, s.points DESC, s.goal_difference DESC, s.goals_for DESC
    ");
    $stmt->execute([$tournamentId, schoolId()]);
    jsonSuccess($stmt->fetchAll());
}

function recalculateStandings() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ? AND school_id = ?");
    $stmt->execute([$tournamentId, schoolId()]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة أو لا تملك صلاحية الوصول');
    
    $db->prepare("
        UPDATE standings s
        JOIN tournaments t ON s.tournament_id = t.id
        SET 
            s.played = 0, s.wins = 0, s.draws = 0, s.losses = 0,
            s.goals_for = 0, s.goals_against = 0, s.goal_difference = 0,
            s.points = 0, s.form = ''
        WHERE s.tournament_id = ? AND t.school_id = ?
    ")->execute([$tournamentId, schoolId()]);
    
    $stmt = $db->prepare("
        SELECT * FROM matches 
        WHERE tournament_id = ? AND status = 'completed' 
        AND team1_id IS NOT NULL AND team2_id IS NOT NULL
    ");
    $stmt->execute([$tournamentId]);
    $matches = $stmt->fetchAll();
    
    foreach ($matches as $match) {
        updateStandings($tournamentId, $match['team1_id'], $match['team2_id'],
                       $match['team1_score'], $match['team2_score'], $tournament);
    }
    
    jsonSuccess(null, 'تم إعادة حساب الترتيب');
}

// ============================================================
// BRACKET
// ============================================================
function getBracket() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();
    
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
    
    $bracket = ['main' => [], 'losers' => [], 'final' => []];
    
    foreach ($matches as $match) {
        $type = $match['bracket_type'];
        $round = $match['round_number'];
        if (!isset($bracket[$type][$round])) $bracket[$type][$round] = [];
        $bracket[$type][$round][] = $match;
    }
    
    jsonSuccess(['tournament' => $tournament, 'bracket' => $bracket]);
}

// ============================================================
// HELPERS
// ============================================================
function getAvailableClasses() {
    requireLogin();
    $tournamentId = getParam('tournament_id');
    
    $db = getDB();
    
    // Build query - handle case where tournament_teams might have issues
    try {
        if ($tournamentId) {
            $sql = "
                SELECT c.id, c.name, g.name as grade_name,
                       CONCAT(g.name, ' - ', c.name) as full_name,
                       COUNT(s.id) as student_count
                FROM classes c
                JOIN grades g ON c.grade_id = g.id
                LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
                WHERE c.active = 1
                AND c.id NOT IN (
                    SELECT COALESCE(class_id, 0) FROM tournament_teams 
                    WHERE tournament_id = ? AND class_id IS NOT NULL
                )
                GROUP BY c.id ORDER BY g.sort_order, g.id, c.section
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tournamentId]);
        } else {
            $sql = "
                SELECT c.id, c.name, g.name as grade_name,
                       CONCAT(g.name, ' - ', c.name) as full_name,
                       COUNT(s.id) as student_count
                FROM classes c
                JOIN grades g ON c.grade_id = g.id
                LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
                WHERE c.active = 1
                GROUP BY c.id ORDER BY g.sort_order, g.id, c.section
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }
        jsonSuccess($stmt->fetchAll());
    } catch (PDOException $e) {
        // Fallback: return all classes without filtering
        $sql = "
            SELECT c.id, c.name, g.name as grade_name,
                   CONCAT(g.name, ' - ', c.name) as full_name,
                   COUNT(s.id) as student_count
            FROM classes c
            JOIN grades g ON c.grade_id = g.id
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            WHERE c.active = 1
            GROUP BY c.id ORDER BY g.sort_order, g.id, c.section
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        jsonSuccess($stmt->fetchAll());
    }
}

function getAvailableStudents() {
    requireLogin();
    $classId = getParam('class_id');
    $tournamentId = getParam('tournament_id');
    
    $db = getDB();
    $sql = "
        SELECT s.id, s.name, s.student_number, c.name as class_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        WHERE s.active = 1
    ";
    $params = [];
    
    if ($classId) {
        $sql .= " AND s.class_id = ?";
        $params[] = $classId;
    }
    
    if ($tournamentId) {
        try {
            $sql .= " AND s.id NOT IN (
                SELECT stm.student_id FROM student_team_members stm
                JOIN student_teams st ON stm.student_team_id = st.id
                JOIN tournament_teams tt ON st.tournament_team_id = tt.id
                WHERE tt.tournament_id = ?
            )";
            $params[] = $tournamentId;
        } catch (Exception $e) {
            // Ignore filter if tables don't exist
        }
    }
    
    $sql .= " ORDER BY s.name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function updateClassPointsFromTournament($tournamentId) {
    try {
        $db = getDB();
        
        // Fetch teams with their final ranks and attempt to resolve a class_id
        // Try direct class_id first, then through sports_teams if available
        $stmt = $db->prepare("
            SELECT 
                COALESCE(tt.class_id, st.class_id) as resolved_class_id,
                tt.final_rank,
                CASE 
                    WHEN tt.final_rank = 1 THEN 100
                    WHEN tt.final_rank = 2 THEN 75
                    WHEN tt.final_rank = 3 THEN 50
                    ELSE 25
                END as bonus_points
            FROM tournament_teams tt
            LEFT JOIN sports_teams st ON tt.sports_team_id = st.id
            WHERE tt.tournament_id = ? AND (tt.class_id IS NOT NULL OR tt.sports_team_id IS NOT NULL)
        ");
        $stmt->execute([$tournamentId]);
        $teams = $stmt->fetchAll();
        
        foreach ($teams as $team) {
            $classId = $team['resolved_class_id'];
            $points = $team['bonus_points'];
            
            if ($classId && $points && $team['final_rank'] !== null) {
                // Ensure record exists in class_points
                $db->prepare("
                    INSERT INTO class_points (class_id, total_points) 
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE total_points = total_points + VALUES(total_points)
                ")->execute([$classId, $points]);
            }
        }
    } catch (Exception $e) {
        error_log("Error in updateClassPointsFromTournament: " . $e->getMessage());
    }
}

function printTournament() {
    requireLogin();
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // Get tournament with teams
    $stmt = $db->prepare("
        SELECT t.*, 
               COUNT(DISTINCT tt.id) as team_count,
               COUNT(DISTINCT m.id) as match_count
        FROM tournaments t
        LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
        LEFT JOIN matches m ON m.tournament_id = t.id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) jsonError('البطولة غير موجودة');
    
    // Get teams
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name,
               CONCAT(g.name, ' - ', c.name) as full_class_name
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE tt.tournament_id = ?
        ORDER BY tt.seed_number, tt.id
    ");
    $stmt->execute([$id]);
    $teams = $stmt->fetchAll();
    
    $tournament['teams'] = $teams;
    
    // Get matches with team names
    $stmt = $db->prepare("
        SELECT m.*, 
               t1.team_name as team1_name, t1.team_color as team1_color, t1.is_eliminated as team1_eliminated,
               t2.team_name as team2_name, t2.team_color as team2_color, t2.is_eliminated as team2_eliminated,
               w.team_name as winner_name
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        LEFT JOIN tournament_teams w ON m.winner_team_id = w.id
        WHERE m.tournament_id = ?
        ORDER BY m.bracket_type, m.round_number, m.match_number
    ");
    $stmt->execute([$id]);
    $matches = $stmt->fetchAll();
    
    $bracketNames = [
        'main' => 'القوس الرئيسي',
        'losers' => 'قوس الخاسرين',
        'final' => 'النهائي',
        'third_place' => 'المركز الثالث'
    ];
    
    $matches = array_map(function($m) use ($bracketNames) {
        $m['bracket_type_name'] = $bracketNames[$m['bracket_type']] ?? $m['bracket_type'];
        return $m;
    }, $matches);
    
    // Get standings for league types
    $standings = null;
    if (strpos($tournament['type'], 'round_robin') !== false) {
        $stmt = $db->prepare("
            SELECT s.*, tt.team_name, tt.team_color
            FROM standings s
            JOIN tournament_teams tt ON s.team_id = tt.id
            WHERE s.tournament_id = ?
            ORDER BY s.points DESC, s.goal_difference DESC, s.goals_for DESC
        ");
        $stmt->execute([$id]);
        $standings = $stmt->fetchAll();
    }
    
    jsonSuccess([
        'tournament' => $tournament,
        'teams' => $teams,
        'matches' => $matches,
        'standings' => $standings
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// LIVE TOURNAMENT SUITE FUNCTIONS (v2.0)
// ════════════════════════════════════════════════════════════════════════

/**
 * جلب بيانات البطولة للجمهور (بدون تسجيل دخول)
 */
function getTournamentPublic() {
    $token = getParam('t');
    if (!$token) jsonError('رابط غير صالح');

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE public_token = ?");
    $stmt->execute([$token]);
    $tournament = $stmt->fetch();

    if (!$tournament) jsonError('البطولة غير موجودة أو غير متاحة للجمهور');

    $tournamentId = $tournament['id'];

    // جلب الفرق والترتيب
    $stmt = $db->prepare("
        SELECT tt.*, c.name as class_name, s.played, s.points, s.wins, s.goals_for
        FROM tournament_teams tt
        LEFT JOIN classes c ON tt.class_id = c.id
        LEFT JOIN standings s ON (s.team_id = tt.id AND s.tournament_id = tt.tournament_id)
        WHERE tt.tournament_id = ?
        ORDER BY s.points DESC, s.goal_difference DESC
    ");
    $stmt->execute([$tournamentId]);
    $tournament['teams'] = $stmt->fetchAll();

    // جلب الجوائز ونجوم البطولة (الجديد: يشمل الجوائز والتميز)
    $stmt = $db->prepare("
        SELECT ps.*, s.name as student_name, tt.team_name
        FROM tournament_player_stats ps
        JOIN students s ON ps.student_id = s.id
        JOIN tournament_teams tt ON ps.team_id = tt.id
        WHERE ps.tournament_id = ? AND (ps.awards IS NOT NULL OR ps.man_of_match > 0 OR ps.goals > 0)
        ORDER BY ps.goals DESC, ps.man_of_match DESC
    ");
    $stmt->execute([$tournamentId]);
    $tournament['awards'] = $stmt->fetchAll();

    // جلب آخر المباريات والنتائج
    $stmt = $db->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name,
               (SELECT COUNT(*) FROM match_media mm WHERE mm.match_id = m.id) as media_count
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE m.tournament_id = ?
        ORDER BY CASE WHEN m.status = 'completed' THEN 1 WHEN m.status = 'in_progress' THEN 0 ELSE 2 END ASC, 
                 m.updated_at DESC LIMIT 20
    ");
    $stmt->execute([$tournamentId]);
    $tournament['recent_matches'] = $stmt->fetchAll();

    // إحصائيات التفاعل (المحبوبية)
    $stmt = $db->prepare("SELECT team_name, cheers_count FROM tournament_teams WHERE tournament_id = ? ORDER BY cheers_count DESC LIMIT 1");
    $stmt->execute([$tournamentId]);
    $tournament['top_loved_team'] = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT s.name as student_name, ps.cheers_count 
        FROM tournament_player_stats ps 
        JOIN students s ON ps.student_id = s.id 
        WHERE ps.tournament_id = ? 
        ORDER BY ps.cheers_count DESC LIMIT 1
    ");
    $stmt->execute([$tournamentId]);
    $tournament['top_loved_player'] = $stmt->fetch();

    jsonSuccess($tournament);
}

/**
 * جلب تحديثات مباشرة (النتائج الحية)
 */
function getLiveFeed() {
    $token = getParam('t');
    $db = getDB();
    $stmt = $db->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name
        FROM matches m
        JOIN tournaments tr ON m.tournament_id = tr.id
        JOIN tournament_teams t1 ON m.team1_id = t1.id
        JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE tr.public_token = ? AND m.status IN ('in_progress', 'completed')
        ORDER BY m.updated_at DESC LIMIT 5
    ");
    $stmt->execute([$token]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * جلب التقرير الشامل للبطولة (لأغراض الطباعة والأرشفة)
 */
function getTournamentFullReport() {
    requireLogin();
    $id = (int)getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    
    // 1. الأساسيات
    $stmt = $db->prepare("SELECT * FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    if (!$tournament) jsonError('البطولة غير موجودة');

    // 2. البطل (إذا انتهت)
    $champion = null;
    if ($tournament['winner_team_id']) {
        $stmt = $db->prepare("SELECT team_name, team_color FROM tournament_teams WHERE id = ?");
        $stmt->execute([$tournament['winner_team_id']]);
        $champion = $stmt->fetch();
    }

    // 3. الترتيب النهائي
    $stmt = $db->prepare("
        SELECT s.*, tt.team_name, tt.team_color, c.name as class_name
        FROM standings s
        JOIN tournament_teams tt ON s.team_id = tt.id
        LEFT JOIN classes c ON tt.class_id = c.id
        WHERE s.tournament_id = ?
        ORDER BY s.points DESC, s.goal_difference DESC, s.goals_for DESC
    ");
    $stmt->execute([$id]);
    $standings = $stmt->fetchAll();

    // 4. إحصائيات الطلاب المتميزين (الجوائز)
    $stmt = $db->prepare("
        SELECT ps.*, s.name as student_name, tt.team_name
        FROM tournament_player_stats ps
        JOIN students s ON ps.student_id = s.id
        JOIN tournament_teams tt ON ps.team_id = tt.id
        WHERE ps.tournament_id = ? AND (ps.awards IS NOT NULL OR ps.man_of_match > 0)
        ORDER BY ps.man_of_match DESC
    ");
    $stmt->execute([$id]);
    $awards = $stmt->fetchAll();

    // 5. الهدافون
    $stmt = $db->prepare("
        SELECT ps.*, s.name as student_name, tt.team_name
        FROM tournament_player_stats ps
        JOIN students s ON ps.student_id = s.id
        JOIN tournament_teams tt ON ps.team_id = tt.id
        WHERE ps.tournament_id = ? AND ps.goals > 0
        ORDER BY ps.goals DESC LIMIT 10
    ");
    $stmt->execute([$id]);
    $scorers = $stmt->fetchAll();

    // 6. جميع المباريات
    $stmt = $db->prepare("
        SELECT m.*, t1.team_name as team1_name, t2.team_name as team2_name,
               (SELECT COUNT(*) FROM match_media mm WHERE mm.match_id = m.id) as media_count
        FROM matches m
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE m.tournament_id = ?
        ORDER BY m.round_number, m.match_number
    ");
    $stmt->execute([$id]);
    $matches = $stmt->fetchAll();

    // 7. جميع الوسائط المرفوعة للبطولة (شواهد مصورة)
    $stmt = $db->prepare("
        SELECT mm.*, t1.team_name as t1, t2.team_name as t2
        FROM match_media mm
        JOIN matches m ON mm.match_id = m.id
        LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
        LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
        WHERE m.tournament_id = ?
        ORDER BY mm.created_at DESC
    ");
    $stmt->execute([$id]);
    $media = $stmt->fetchAll();

    jsonSuccess([
        'tournament' => $tournament,
        'champion' => $champion,
        'standings' => $standings,
        'awards' => $awards,
        'scorers' => $scorers,
        'matches' => $matches,
        'media' => $media
    ]);
}

/**
 * جلب هدافي البطولة
 */
function getTopScorers() {
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) {
        $token = getParam('t');
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM tournaments WHERE public_token = ?");
        $stmt->execute([$token]);
        $tournamentId = $stmt->fetchColumn();
    }

    if (!$tournamentId) jsonError('البطولة غير محددة');

    $db = getDB();
    $stmt = $db->prepare("
        SELECT ps.*, s.name as student_name, tt.team_name
        FROM tournament_player_stats ps
        JOIN students s ON ps.student_id = s.id
        JOIN tournament_teams tt ON ps.team_id = tt.id
        WHERE ps.tournament_id = ? AND ps.goals > 0
        ORDER BY ps.goals DESC, ps.assists DESC LIMIT 10
    ");
    $stmt->execute([$tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * تحديد أفضل لاعب في المباراة
 */
function setManOfMatch() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['match_id', 'student_id', 'student_name']);

    $db = getDB();
    // تحديث المباراة
    $db->prepare("UPDATE matches SET man_of_match_student_id = ?, man_of_match_name = ? WHERE id = ?")
       ->execute([$data['student_id'], sanitize($data['student_name']), $data['match_id']]);

    // إضافة إحصائية للاعب
    $stmt = $db->prepare("SELECT tournament_id, team1_id, team2_id FROM matches WHERE id = ?");
    $stmt->execute([$data['match_id']]);
    $match = $stmt->fetch();

    $teamId = $match['team1_id']; 

    $db->prepare("
        INSERT INTO tournament_player_stats (tournament_id, student_id, team_id, man_of_match)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE man_of_match = man_of_match + 1
    ")->execute([$match['tournament_id'], $data['student_id'], $teamId]);

    jsonSuccess(null, 'تم اختيار أفضل لاعب في المباراة');
}


function getTournamentShareInfo() {
    $id = getParam('id');
    if (!$id) jsonError('معرّف البطولة مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT name, public_token, status FROM tournaments WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();

    if (!$t) jsonError('البطولة غير موجودة');

    // إذا كانت البطولة جارية أو منتهية ولكن ليس لها مفتاح رابط (للبطولات القديمة)
    // نقوم بتوليد مفتاح لها الآن فوراً
    if (!$t['public_token'] && ($t['status'] === 'in_progress' || $t['status'] === 'completed')) {
        $newToken = bin2hex(random_bytes(16));
        $db->prepare("UPDATE tournaments SET public_token = ?, is_public = 1 WHERE id = ?")
           ->execute([$newToken, $id]);
        $t['public_token'] = $newToken;
    }

    if (!$t['public_token']) {
        jsonError('رابط المشاركة يتوفر فقط بعد بدء البطولة (Start Tournament)');
    }

    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
           . dirname(dirname(dirname($_SERVER['PHP_SELF']))) . '/tournament.php?t=' . $t['public_token'];

    jsonSuccess([
        'name' => $t['name'],
        'url' => $url,
        'token' => $t['public_token']
    ]);
}

function updatePlayerStats() {
    requireRole(['admin', 'teacher']);
    jsonSuccess(null, 'سيتم ربط هذا المنطق تلجائياً مع تسجيل النتائج');
}

/**
 * إسناد جائزة أو مسمى متميز لطالب
 */
function setPlayerAward() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['tournament_id', 'student_id', 'award_name', 'team_id']);

    $db = getDB();
    
    try {
        // التأكد من وجود الفهرس الفريد لضمان عمل UPDATE عند التكرار
        $db->exec("ALTER TABLE `tournament_player_stats` ADD UNIQUE INDEX IF NOT EXISTS `uk_tps_tour_student` (`tournament_id`, `student_id`)");
    } catch (Exception $e) { /* تجاهل إذا كان موجوداً */ }

    try {
        $stmt = $db->prepare("
            INSERT INTO tournament_player_stats (tournament_id, student_id, team_id, awards)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE awards = VALUES(awards), team_id = VALUES(team_id)
        ");
        $stmt->execute([
            $data['tournament_id'], 
            $data['student_id'], 
            $data['team_id'], 
            sanitize($data['award_name'])
        ]);
        jsonSuccess(null, 'تم إسناد الجائزة بنجاح');
    } catch (Exception $e) {
        jsonError('خطأ في قاعدة البيانات: ' . $e->getMessage());
    }
}


/**
 * جلب جميع الطلاب المشاركين في بطولة معينة مع فرقهم
 */
function getTournamentStudents() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    if (!$tournamentId) jsonError('معرّف البطولة مطلوب');

    $db = getDB();
    $stmt = $db->prepare("
        -- 1. الطلاب المختارون يدوياً من الفصول (عبر student_team_members)
        SELECT DISTINCT s.id, s.name, tt.id as tournament_team_id, tt.team_name, 'selection' as source
        FROM students s
        JOIN student_team_members stm ON stm.student_id = s.id
        JOIN student_teams st ON stm.student_team_id = st.id
        JOIN tournament_teams tt ON st.tournament_team_id = tt.id
        WHERE tt.tournament_id = ?

        UNION

        -- 2. حمل الطلاب المرتبطين عبر الفرق الرياضية (وحدة الفرق)
        SELECT DISTINCT s.id, s.name, tt.id as tournament_team_id, tt.team_name, 'sports_team' as source
        FROM students s
        JOIN team_members tm ON tm.student_id = s.id
        JOIN tournament_teams tt ON tt.sports_team_id = tm.team_id
        WHERE tt.tournament_id = ?
        
        UNION

        -- 3. حمل كافة طلاب الفصل (في حال تم إضافة الفصل كاملاً بدون اختيار)
        SELECT DISTINCT s.id, s.name, tt.id as tournament_team_id, tt.team_name, 'full_class' as source
        FROM students s
        JOIN tournament_teams tt ON tt.class_id = s.class_id
        WHERE tt.tournament_id = ? AND tt.id NOT IN (
            SELECT ttt.id FROM tournament_teams ttt 
            JOIN student_teams st ON st.tournament_team_id = ttt.id
        )
    ");
    $stmt->execute([$tournamentId, $tournamentId, $tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * جلب طلاب فصل معين للاختيار منهم للبطولة
 */
function getClassStudents() {
    requireRole(['admin', 'teacher']);
    $classId = getParam('class_id');
    if (!$classId) jsonError('معرف الفصل مطلوب');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name FROM students WHERE class_id = ? AND active = 1");
    $stmt->execute([$classId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * جلب الفرق الرياضية المتوفرة للإضافة للبطولة
 */
function getAvailableSportsTeams() {
    requireRole(['admin', 'teacher']);
    $tournamentId = getParam('tournament_id');
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, name, sport_type, color, logo_emoji
        FROM sports_teams
        WHERE id NOT IN (SELECT sports_team_id FROM tournament_teams WHERE tournament_id = ? AND sports_team_id IS NOT NULL)
        AND is_active = 1
    ");
    $stmt->execute([$tournamentId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * إضافة فرق رياضية محددة إلى البطولة
 */
function addSportsTeamsToTournament() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    
    // التحقق يدوياً لتجنب مشكلة trim() مع المصفوفات في validateRequired
    if (empty($data['tournament_id']) || empty($data['team_ids'])) {
        jsonError('بيانات غير مكتملة');
    }

    $db = getDB();
    $tournamentId = (int)$data['tournament_id'];
    $teamIds = (array)$data['team_ids'];

    $db->beginTransaction();
    try {
        $stmtGet = $db->prepare("SELECT name, color FROM sports_teams WHERE id = ?");
        // التأكد من أن أسماء الأعمدة مطابقة لما في قاعدة البيانات
        $stmtIns = $db->prepare("INSERT INTO tournament_teams (tournament_id, sports_team_id, team_name, team_color) VALUES (?, ?, ?, ?)");
        
        $addedCount = 0;
        foreach ($teamIds as $tid) {
            $stmtGet->execute([(int)$tid]);
            $sTeam = $stmtGet->fetch();
            if ($sTeam) {
                $stmtIns->execute([$tournamentId, (int)$tid, $sTeam['name'], $sTeam['color']]);
                $addedCount++;
            }
        }
        
        if ($addedCount === 0) {
            $db->rollBack();
            jsonError('لم يتم العثور على أي من الفرق المحددة');
            return;
        }

        $db->commit();
        jsonSuccess(null, "تم إضافة $addedCount من الفرق الرياضية بنجاح");
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonError('خطأ في قاعدة البيانات: ' . $e->getMessage());
    }
}
/**
 * جلب أعضاء فريق معين في البطولة
 */
function getTeamMembers() {
    requireLogin();
    $teamId = getParam('team_id');
    if (!$teamId) jsonError('معرف الفريق مطلوب');

    $db = getDB();
    
    // جلب الطلاب المختارين يدويا أو عبر الفصول
    $stmt1 = $db->prepare("
        SELECT s.id, s.name, stm.position, stm.jersey_number
        FROM students s
        JOIN student_team_members stm ON stm.student_id = s.id
        JOIN student_teams st ON stm.student_team_id = st.id
        WHERE st.tournament_team_id = ?
    ");
    $stmt1->execute([$teamId]);
    $members1 = $stmt1->fetchAll();

    // جلب الطلاب من الفرق الرياضية (إذا كان الفريق مربوط بوحدة الفرق)
    $stmt2 = $db->prepare("
        SELECT s.id, s.name, tm.position, tm.jersey_number
        FROM students s
        JOIN team_members tm ON tm.student_id = s.id
        JOIN tournament_teams tt ON tt.sports_team_id = tm.team_id
        WHERE tt.id = ?
    ");
    $stmt2->execute([$teamId]);
    $members2 = $stmt2->fetchAll();

    // جلب كافة طلاب الفصل إذا كان الفريق عبارة عن فصل كامل بدون اختيار يدوي
    $stmt3 = $db->prepare("
        SELECT s.id, s.name, NULL as position, NULL as jersey_number
        FROM students s
        JOIN tournament_teams tt ON tt.class_id = s.class_id
        WHERE tt.id = ? AND tt.id NOT IN (
            SELECT tournament_team_id FROM student_teams
        )
    ");
    $stmt3->execute([$teamId]);
    $members3 = $stmt3->fetchAll();

    jsonSuccess(array_merge($members1, $members2, $members3));
}

// ════════════════════════════════════════════════════
// MEDIA & VISUAL SYSTEM (System-Generated v2.0)
// ════════════════════════════════════════════════════

/**
 * رفع الوسائط لمباراة معينة
 */
function uploadMatchMedia() {
    requireRole(['admin', 'teacher']);
    
    $matchId = (int)($_POST['match_id'] ?? 0);
    $desc = sanitize($_POST['description'] ?? '');
    
    if (!$matchId) jsonError('المباراة غير محددة');
    
    if (!isset($_FILES['media'])) {
        jsonError('لم يتم اختيار ملف أو حجم الملف تجاوز الحد المسموح به في الخادم (upload_max_filesize)');
    }

    if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'فشل في رفع الملف: ';
        switch ($_FILES['media']['error']) {
            case UPLOAD_ERR_INI_SIZE:   $errorMsg .= 'حجم الملف أكبر مما يسمح به الخادم'; break;
            case UPLOAD_ERR_FORM_SIZE:  $errorMsg .= 'حجم الملف أكبر من المسموح به في النموذج'; break;
            case UPLOAD_ERR_PARTIAL:    $errorMsg .= 'تم رفع جزء من الملف فقط'; break;
            case UPLOAD_ERR_NO_FILE:    $errorMsg .= 'لم يتم اختيار ملف'; break;
            case UPLOAD_ERR_NO_TMP_DIR: $errorMsg .= 'مجلد الملفات المؤقتة مفقود'; break;
            case UPLOAD_ERR_CANT_WRITE: $errorMsg .= 'فشل الكتابة على القرص'; break;
            default: $errorMsg .= 'خطأ غير معروف برقم: ' . $_FILES['media']['error'];
        }
        jsonError($errorMsg);
    }

    $file = $_FILES['media'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'mp4'];
    
    if (!in_array($ext, $allowed)) jsonError('نوع الملف غير مدعوم (المسموح: ' . implode(',', $allowed) . ')');
    if ($file['size'] > 10 * 1024 * 1024) jsonError('حجم الملف كبير جداً (الأقصى 10MB)');

    $newName = 'match_' . $matchId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetDir = '../../uploads/tournaments/';
    
    // التأكد من وجود المجلد وصلاحيته
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            jsonError('مجلد الرفع غير موجود وفشل إنشاؤه: ' . $targetDir);
        }
    }

    $targetPath = $targetDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonError('فشل نقل الملف. تأكد من صلاحيات الكتابة لمجلد ' . $targetDir);
    }

    $db = getDB();
    $type = ($ext === 'mp4') ? 'video' : 'photo';
    $url = 'uploads/tournaments/' . $newName;

    $stmt = $db->prepare("INSERT INTO match_media (match_id, media_type, media_url, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$matchId, $type, $url, $desc]);

    jsonSuccess(['id' => $db->lastInsertId(), 'url' => $url], 'تم رفع الوسائط بنجاح');
}

/**
 * جلب وسائط مباراة
 */
function getMatchMedia() {
    $matchId = (int)getParam('match_id');
    if (!$matchId) jsonError('المباراة غير محددة');

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM match_media WHERE match_id = ? ORDER BY created_at DESC");
    $stmt->execute([$matchId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * حذف وسائط
 */
function deleteMatchMedia() {
    requireRole(['admin', 'teacher']);
    $id = (int)getParam('id');
    
    $db = getDB();
    $stmt = $db->prepare("SELECT media_url FROM match_media WHERE id = ?");
    $stmt->execute([$id]);
    $url = $stmt->fetchColumn();
    
    if ($url) {
        $fullPath = '../../' . $url;
        if (file_exists($fullPath)) unlink($fullPath);
        
        $db->prepare("DELETE FROM match_media WHERE id = ?")->execute([$id]);
    }
    
    jsonSuccess(null, 'تم الحذف بنجاح');
}

/**
 * جلب المخطط الشجري للجمهور عبر التوكن
 */
function getBracketPublic() {
    $token = getParam('t');
    if (!$token) jsonError('التوكن مطلوب');

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM tournaments WHERE public_token = ?");
    $stmt->execute([$token]);
    $tournamentId = $stmt->fetchColumn();

    if (!$tournamentId) jsonError('البطولة غير موجودة');

    // إعادة توجيه الطلب للدالة الأصلية مع تعديل بسيط لتخطي تسجيل الدخول
    $_GET['tournament_id'] = $tournamentId;
    
    // محاكاة تسجيل الدخول كزائر لتخطي requireLogin إذا لزم الأمر أو تعديل getBracket
    // سنقوم بجلب البيانات هنا مباشرة لضمان الأمان
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
    
    $bracket = ['main' => [], 'losers' => [], 'final' => []];
    foreach ($matches as $match) {
        $type = $match['bracket_type'];
        $round = $match['round_number'];
        if (!isset($bracket[$type][$round])) $bracket[$type][$round] = [];
        $bracket[$type][$round][] = $match;
    }
    
    jsonSuccess(['bracket' => $bracket]);
}

/**
 * نظام التشجيع - v2.1
 * زيادة عداد الحب/التشجيع للفريق أو اللاعب
 */
function cheerAction() {
    $type = getParam('type'); // 'team' or 'player'
    $id = (int)getParam('id'); // ID of the target (tournament_team_id or student_id)
    $tourId = (int)getParam('tournament_id');

    if (!$id || !$tourId) jsonError('بيانات ناقصة');

    $db = getDB();
    if ($type === 'team') {
        $db->prepare("UPDATE tournament_teams SET cheers_count = cheers_count + 1 WHERE id = ?")
           ->execute([$id]);
    } else {
        $db->prepare("UPDATE tournament_player_stats SET cheers_count = cheers_count + 1 WHERE student_id = ? AND tournament_id = ?")
           ->execute([$id, $tourId]);
    }

    jsonSuccess(null, 'شكراً لتشجيعك! ❤️');
}

/**
 * ملف اللاعب الشامل - v2.1
 * جلب تاريخ الطالب الرياضي في المدرسة بالكامل
 */
function getPlayerHistory() {
    $studentId = (int)getParam('student_id');
    if (!$studentId) jsonError('الطالب غير معروف');

    $db = getDB();
    
    // 1. المعلومات الأساسية
    $stmt = $db->prepare("SELECT name, (SELECT name FROM classes WHERE id = students.class_id) as class_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) jsonError('الطالب غير موجود');

    // 2. إحصائيات مجمعة من كافة البطولات
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT tournament_id) as tournaments_played,
            SUM(goals) as total_goals,
            SUM(man_of_match) as total_mom,
            SUM(cheers_count) as total_cheers,
            GROUP_CONCAT(DISTINCT awards SEPARATOR ' | ') as all_titles
        FROM tournament_player_stats
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $stats = $stmt->fetch();

    // 3. سجل البطولات التفصيلي
    $stmt = $db->prepare("
        SELECT ps.*, t.name as tournament_name, t.sport_type, tt.team_name
        FROM tournament_player_stats ps
        JOIN tournaments t ON ps.tournament_id = t.id
        JOIN tournament_teams tt ON ps.team_id = tt.id
        WHERE ps.student_id = ?
        ORDER BY t.start_date DESC
    ");
    $stmt->execute([$studentId]);
    $history = $stmt->fetchAll();

    jsonSuccess([
        'student' => $student,
        'stats'   => $stats,
        'history' => $history
    ]);
}
