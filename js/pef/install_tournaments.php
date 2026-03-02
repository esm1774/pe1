<?php
/**
 * PE Smart School System - Tournament Module Installer v1.0
 * ============================================================
 * هذا الملف يُنشئ جداول البطولات فقط
 * يمكن تشغيله بشكل مستقل دون التأثير على الجداول الأخرى
 * 
 * 🔧 طريقة الاستخدام:
 * 1. ارفع هذا الملف إلى الاستضافة
 * 2. زُر: https://yourdomain.com/install_tournaments.php
 * 3. احذف الملف بعد التثبيت
 * ============================================================
 */

require_once 'config.php';

$messages = [];
$errors = [];
$success = true;

// ============================================================
// التحقق من الاتصال بقاعدة البيانات
// ============================================================
try {
    $db = getDB();
    $messages[] = "✅ تم الاتصال بقاعدة البيانات بنجاح";
} catch (Exception $e) {
    $errors[] = "❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage();
    $success = false;
}

// ============================================================
// التحقق من وجود الجداول الأساسية المطلوبة
// ============================================================
if ($success) {
    $requiredTables = ['classes', 'students', 'users'];
    foreach ($requiredTables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$result) {
            $errors[] = "❌ الجدول '$table' غير موجود. يجب تشغيل install.php أولاً";
            $success = false;
        }
    }
    if ($success) {
        $messages[] = "✅ الجداول الأساسية موجودة (classes, students, users)";
    }
}

// ============================================================
// إنشاء جداول البطولات
// ============================================================
if ($success) {
    $tournamentTables = [
        // 1. جدول البطولات الرئيسي
        "tournaments" => "CREATE TABLE IF NOT EXISTS `tournaments` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL COMMENT 'اسم البطولة',
            `description` TEXT DEFAULT NULL COMMENT 'وصف البطولة',
            `type` ENUM('single_elimination', 'double_elimination', 'round_robin_single', 'round_robin_double') NOT NULL COMMENT 'نوع البطولة',
            `team_mode` ENUM('class_based', 'student_based') NOT NULL DEFAULT 'class_based' COMMENT 'وضع الفرق',
            `sport_type` VARCHAR(50) DEFAULT 'كرة قدم' COMMENT 'نوع الرياضة',
            `start_date` DATE DEFAULT NULL COMMENT 'تاريخ البدء',
            `end_date` DATE DEFAULT NULL COMMENT 'تاريخ الانتهاء',
            `randomize_teams` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'ترتيب عشوائي للفرق',
            `auto_generate` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'توليد المباريات تلقائياً',
            `teams_per_match` INT UNSIGNED DEFAULT 2 COMMENT 'عدد الفرق في المباراة',
            `points_win` INT UNSIGNED DEFAULT 3 COMMENT 'نقاط الفوز',
            `points_draw` INT UNSIGNED DEFAULT 1 COMMENT 'نقاط التعادل',
            `points_loss` INT UNSIGNED DEFAULT 0 COMMENT 'نقاط الخسارة',
            `status` ENUM('draft', 'registration', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft' COMMENT 'حالة البطولة',
            `winner_team_id` INT UNSIGNED DEFAULT NULL COMMENT 'الفريق الفائز',
            `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'منشئ البطولة',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_status` (`status`),
            INDEX `idx_type` (`type`),
            INDEX `idx_dates` (`start_date`, `end_date`),
            INDEX `idx_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول البطولات'",

        // 2. جدول فرق البطولة
        "tournament_teams" => "CREATE TABLE IF NOT EXISTS `tournament_teams` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL COMMENT 'معرف البطولة',
            `class_id` INT UNSIGNED DEFAULT NULL COMMENT 'معرف الفصل (اختياري)',
            `team_name` VARCHAR(100) NOT NULL COMMENT 'اسم الفريق',
            `team_color` VARCHAR(20) DEFAULT '#10b981' COMMENT 'لون الفريق',
            `seed_number` INT UNSIGNED DEFAULT NULL COMMENT 'رقم البذرة/الترتيب',
            `is_eliminated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل تم إقصاؤه',
            `elimination_count` INT UNSIGNED DEFAULT 0 COMMENT 'عدد مرات الخسارة (للخروج المزدوج)',
            `current_round` INT UNSIGNED DEFAULT 1 COMMENT 'الجولة الحالية',
            `final_rank` INT UNSIGNED DEFAULT NULL COMMENT 'الترتيب النهائي',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tournament` (`tournament_id`),
            INDEX `idx_class` (`class_id`),
            INDEX `idx_eliminated` (`is_eliminated`),
            INDEX `idx_seed` (`seed_number`),
            CONSTRAINT `fk_tt_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_tt_class` FOREIGN KEY (`class_id`) 
                REFERENCES `classes`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فرق البطولات'",

        // 3. جدول فرق الطلاب (للوضع student_based)
        "student_teams" => "CREATE TABLE IF NOT EXISTS `student_teams` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_team_id` INT UNSIGNED NOT NULL COMMENT 'معرف فريق البطولة',
            `team_name` VARCHAR(100) NOT NULL COMMENT 'اسم الفريق',
            `captain_student_id` INT UNSIGNED DEFAULT NULL COMMENT 'قائد الفريق',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_tournament_team` (`tournament_team_id`),
            INDEX `idx_captain` (`captain_student_id`),
            CONSTRAINT `fk_st_tournament_team` FOREIGN KEY (`tournament_team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_st_captain` FOREIGN KEY (`captain_student_id`) 
                REFERENCES `students`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='فرق الطلاب'",

        // 4. جدول أعضاء فرق الطلاب
        "student_team_members" => "CREATE TABLE IF NOT EXISTS `student_team_members` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_team_id` INT UNSIGNED NOT NULL COMMENT 'معرف فريق الطلاب',
            `student_id` INT UNSIGNED NOT NULL COMMENT 'معرف الطالب',
            `position` VARCHAR(50) DEFAULT NULL COMMENT 'المركز',
            `jersey_number` INT UNSIGNED DEFAULT NULL COMMENT 'رقم القميص',
            `is_substitute` TINYINT(1) DEFAULT 0 COMMENT 'احتياطي',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_team_student` (`student_team_id`, `student_id`),
            INDEX `idx_student` (`student_id`),
            CONSTRAINT `fk_stm_team` FOREIGN KEY (`student_team_id`) 
                REFERENCES `student_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_stm_student` FOREIGN KEY (`student_id`) 
                REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أعضاء فرق الطلاب'",

        // 5. جدول المباريات
        "matches" => "CREATE TABLE IF NOT EXISTS `matches` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL COMMENT 'معرف البطولة',
            `round_number` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'رقم الجولة',
            `match_number` INT UNSIGNED NOT NULL COMMENT 'رقم المباراة',
            `bracket_type` ENUM('main', 'losers', 'final', 'third_place') NOT NULL DEFAULT 'main' COMMENT 'نوع القوس',
            `team1_id` INT UNSIGNED DEFAULT NULL COMMENT 'الفريق الأول',
            `team2_id` INT UNSIGNED DEFAULT NULL COMMENT 'الفريق الثاني',
            `team1_score` INT UNSIGNED DEFAULT NULL COMMENT 'نتيجة الفريق الأول',
            `team2_score` INT UNSIGNED DEFAULT NULL COMMENT 'نتيجة الفريق الثاني',
            `winner_team_id` INT UNSIGNED DEFAULT NULL COMMENT 'الفريق الفائز',
            `loser_team_id` INT UNSIGNED DEFAULT NULL COMMENT 'الفريق الخاسر',
            `is_bye` TINYINT(1) DEFAULT 0 COMMENT 'مباراة BYE',
            `match_date` DATE DEFAULT NULL COMMENT 'تاريخ المباراة',
            `match_time` TIME DEFAULT NULL COMMENT 'وقت المباراة',
            `venue` VARCHAR(100) DEFAULT NULL COMMENT 'مكان المباراة',
            `status` ENUM('scheduled', 'in_progress', 'completed', 'postponed', 'cancelled') NOT NULL DEFAULT 'scheduled' COMMENT 'حالة المباراة',
            `next_match_id` INT UNSIGNED DEFAULT NULL COMMENT 'المباراة التالية للفائز',
            `next_match_slot` ENUM('team1', 'team2') DEFAULT NULL COMMENT 'موقع الفائز في المباراة التالية',
            `loser_next_match_id` INT UNSIGNED DEFAULT NULL COMMENT 'المباراة التالية للخاسر (للخروج المزدوج)',
            `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_tournament` (`tournament_id`),
            INDEX `idx_round` (`round_number`),
            INDEX `idx_bracket` (`bracket_type`),
            INDEX `idx_status` (`status`),
            INDEX `idx_teams` (`team1_id`, `team2_id`),
            INDEX `idx_winner` (`winner_team_id`),
            INDEX `idx_next_match` (`next_match_id`),
            CONSTRAINT `fk_m_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_m_team1` FOREIGN KEY (`team1_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_m_team2` FOREIGN KEY (`team2_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_m_winner` FOREIGN KEY (`winner_team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_m_loser` FOREIGN KEY (`loser_team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_m_next` FOREIGN KEY (`next_match_id`) 
                REFERENCES `matches`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_m_loser_next` FOREIGN KEY (`loser_next_match_id`) 
                REFERENCES `matches`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مباريات البطولات'",

        // 6. جدول الترتيب (للدوري)
        "standings" => "CREATE TABLE IF NOT EXISTS `standings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `tournament_id` INT UNSIGNED NOT NULL COMMENT 'معرف البطولة',
            `team_id` INT UNSIGNED NOT NULL COMMENT 'معرف الفريق',
            `played` INT UNSIGNED DEFAULT 0 COMMENT 'المباريات الملعوبة',
            `wins` INT UNSIGNED DEFAULT 0 COMMENT 'الانتصارات',
            `draws` INT UNSIGNED DEFAULT 0 COMMENT 'التعادلات',
            `losses` INT UNSIGNED DEFAULT 0 COMMENT 'الخسائر',
            `goals_for` INT UNSIGNED DEFAULT 0 COMMENT 'الأهداف المسجلة',
            `goals_against` INT UNSIGNED DEFAULT 0 COMMENT 'الأهداف المستقبلة',
            `goal_difference` INT DEFAULT 0 COMMENT 'فارق الأهداف',
            `points` INT UNSIGNED DEFAULT 0 COMMENT 'النقاط',
            `form` VARCHAR(20) DEFAULT NULL COMMENT 'آخر 5 نتائج (WWLDW)',
            `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_tournament_team` (`tournament_id`, `team_id`),
            INDEX `idx_points` (`points` DESC, `goal_difference` DESC, `goals_for` DESC),
            CONSTRAINT `fk_s_tournament` FOREIGN KEY (`tournament_id`) 
                REFERENCES `tournaments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_s_team` FOREIGN KEY (`team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول ترتيب الدوري'",

        // 7. جدول أحداث المباراة (اختياري - للتفاصيل)
        "match_events" => "CREATE TABLE IF NOT EXISTS `match_events` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `match_id` INT UNSIGNED NOT NULL COMMENT 'معرف المباراة',
            `team_id` INT UNSIGNED NOT NULL COMMENT 'معرف الفريق',
            `student_id` INT UNSIGNED DEFAULT NULL COMMENT 'معرف الطالب',
            `event_type` ENUM('goal', 'own_goal', 'penalty', 'yellow_card', 'red_card', 'substitution', 'injury') NOT NULL COMMENT 'نوع الحدث',
            `minute` INT UNSIGNED DEFAULT NULL COMMENT 'الدقيقة',
            `notes` VARCHAR(255) DEFAULT NULL COMMENT 'ملاحظات',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_match` (`match_id`),
            INDEX `idx_team` (`team_id`),
            INDEX `idx_student` (`student_id`),
            INDEX `idx_event_type` (`event_type`),
            CONSTRAINT `fk_me_match` FOREIGN KEY (`match_id`) 
                REFERENCES `matches`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_me_team` FOREIGN KEY (`team_id`) 
                REFERENCES `tournament_teams`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_me_student` FOREIGN KEY (`student_id`) 
                REFERENCES `students`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أحداث المباريات'"
    ];

    // إنشاء الجداول
    foreach ($tournamentTables as $tableName => $sql) {
        try {
            // التحقق من وجود الجدول
            $exists = $db->query("SHOW TABLES LIKE '$tableName'")->fetch();
            if ($exists) {
                $messages[] = "ℹ️ الجدول '$tableName' موجود بالفعل";
            } else {
                $db->exec($sql);
                $messages[] = "✅ تم إنشاء جدول '$tableName'";
            }
        } catch (PDOException $e) {
            $errors[] = "❌ خطأ في إنشاء جدول '$tableName': " . $e->getMessage();
            $success = false;
        }
    }
}

// ============================================================
// إنشاء Views
// ============================================================
if ($success) {
    $views = [
        "v_tournaments_summary" => "CREATE OR REPLACE VIEW `v_tournaments_summary` AS
            SELECT 
                t.*,
                COUNT(DISTINCT tt.id) as team_count,
                COUNT(DISTINCT m.id) as match_count,
                SUM(CASE WHEN m.status = 'completed' THEN 1 ELSE 0 END) as completed_matches,
                u.name as created_by_name
            FROM tournaments t
            LEFT JOIN tournament_teams tt ON tt.tournament_id = t.id
            LEFT JOIN matches m ON m.tournament_id = t.id
            LEFT JOIN users u ON u.id = t.created_by
            GROUP BY t.id",

        "v_standings_full" => "CREATE OR REPLACE VIEW `v_standings_full` AS
            SELECT 
                s.*,
                tt.team_name,
                tt.team_color,
                tt.class_id,
                c.name as class_name,
                t.name as tournament_name,
                t.type as tournament_type
            FROM standings s
            JOIN tournament_teams tt ON s.team_id = tt.id
            JOIN tournaments t ON s.tournament_id = t.id
            LEFT JOIN classes c ON tt.class_id = c.id
            ORDER BY s.tournament_id, s.points DESC, s.goal_difference DESC, s.goals_for DESC",

        "v_matches_full" => "CREATE OR REPLACE VIEW `v_matches_full` AS
            SELECT 
                m.*,
                t.name as tournament_name,
                t.type as tournament_type,
                t1.team_name as team1_name,
                t1.team_color as team1_color,
                t2.team_name as team2_name,
                t2.team_color as team2_color,
                w.team_name as winner_name
            FROM matches m
            JOIN tournaments t ON m.tournament_id = t.id
            LEFT JOIN tournament_teams t1 ON m.team1_id = t1.id
            LEFT JOIN tournament_teams t2 ON m.team2_id = t2.id
            LEFT JOIN tournament_teams w ON m.winner_team_id = w.id"
    ];

    foreach ($views as $viewName => $sql) {
        try {
            $db->exec($sql);
            $messages[] = "✅ تم إنشاء View '$viewName'";
        } catch (PDOException $e) {
            $errors[] = "⚠️ تحذير في إنشاء View '$viewName': " . $e->getMessage();
            // لا نوقف العملية بسبب Views
        }
    }
}

// ============================================================
// إنشاء بيانات تجريبية (اختياري)
// ============================================================
$sampleDataCreated = false;
if ($success && isset($_GET['with_sample_data'])) {
    try {
        // التحقق من عدم وجود بيانات
        $count = $db->query("SELECT COUNT(*) FROM tournaments")->fetchColumn();
        if ($count == 0) {
            $db->beginTransaction();

            // إنشاء بطولة تجريبية
            $db->exec("INSERT INTO tournaments (name, type, sport_type, status, created_by) VALUES 
                ('بطولة كأس المدرسة', 'single_elimination', 'كرة قدم', 'draft', 1),
                ('دوري الفصول', 'round_robin_single', 'كرة قدم', 'draft', 1)");
            
            $messages[] = "✅ تم إنشاء بطولتين تجريبيتين";

            // إضافة فصول كفرق للبطولة الأولى
            $classes = $db->query("SELECT id, name FROM classes WHERE active = 1 LIMIT 4")->fetchAll();
            $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            $seed = 1;
            foreach ($classes as $i => $class) {
                $color = $colors[$i % count($colors)];
                $db->prepare("INSERT INTO tournament_teams (tournament_id, class_id, team_name, team_color, seed_number) VALUES (1, ?, ?, ?, ?)")
                   ->execute([$class['id'], $class['name'], $color, $seed++]);
            }
            $messages[] = "✅ تم إضافة " . count($classes) . " فرق للبطولة التجريبية";

            $db->commit();
            $sampleDataCreated = true;
        } else {
            $messages[] = "ℹ️ توجد بيانات بطولات بالفعل - تم تخطي البيانات التجريبية";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "⚠️ تحذير في إنشاء البيانات التجريبية: " . $e->getMessage();
    }
}

// ============================================================
// عرض النتائج
// ============================================================
$tableCount = 0;
if ($success) {
    $result = $db->query("SHOW TABLES LIKE 'tournament%'")->fetchAll();
    $result2 = $db->query("SHOW TABLES LIKE 'matches'")->fetchAll();
    $result3 = $db->query("SHOW TABLES LIKE 'standings'")->fetchAll();
    $result4 = $db->query("SHOW TABLES LIKE 'student_team%'")->fetchAll();
    $result5 = $db->query("SHOW TABLES LIKE 'match_events'")->fetchAll();
    $tableCount = count($result) + count($result2) + count($result3) + count($result4) + count($result5);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت وحدة البطولات - PE Smart School</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap');
        * { font-family: 'Cairo', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #4f46e5, #7c3aed); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 25px 80px rgba(0,0,0,0.2); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo span { font-size: 70px; display: block; margin-bottom: 10px; }
        .logo h1 { color: #1f2937; font-size: 26px; font-weight: 800; }
        .logo p { color: #6b7280; margin-top: 5px; }
        .badge { display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-right: 8px; }
        
        .msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 8px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .msg-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .msg-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin: 24px 0; }
        .stat-card { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); border-radius: 16px; padding: 20px; text-align: center; }
        .stat-card.primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; }
        .stat-number { font-size: 36px; font-weight: 800; }
        .stat-label { font-size: 13px; opacity: 0.8; margin-top: 4px; }
        
        .tables-list { background: #f9fafb; border-radius: 16px; padding: 20px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .tables-list h3 { margin-bottom: 16px; color: #374151; font-size: 16px; }
        .table-item { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        .table-item:last-child { border-bottom: none; }
        .table-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .table-icon.purple { background: #ede9fe; }
        .table-icon.blue { background: #dbeafe; }
        .table-icon.green { background: #dcfce7; }
        .table-icon.orange { background: #ffedd5; }
        .table-name { font-weight: 600; color: #374151; }
        .table-desc { font-size: 12px; color: #6b7280; }
        
        .btn { display: inline-block; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 15px; margin-top: 16px; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79,70,229,0.4); }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-secondary:hover { background: #e5e7eb; }
        .btn-success { background: linear-gradient(135deg, #059669, #10b981); color: #fff; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.4); }
        
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
        
        .warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 16px; border-radius: 12px; margin-top: 24px; font-size: 14px; font-weight: 600; }
        
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 20px 0; }
        .feature { background: #f9fafb; border-radius: 12px; padding: 16px; border: 1px solid #e5e7eb; }
        .feature-title { font-weight: 700; color: #374151; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .feature-desc { font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <span>⚽</span>
            <h1>وحدة البطولات والدوريات</h1>
            <p><span class="badge">Tournament Engine v1.0</span> PE Smart School System</p>
        </div>

        <!-- الرسائل -->
        <div class="messages">
            <?php foreach ($messages as $msg): ?>
                <div class="msg <?php echo strpos($msg, '✅') !== false ? 'msg-success' : (strpos($msg, '❌') !== false ? 'msg-error' : 'msg-info'); ?>">
                    <?php echo $msg; ?>
                </div>
            <?php endforeach; ?>
            <?php foreach ($errors as $err): ?>
                <div class="msg msg-error"><?php echo $err; ?></div>
            <?php endforeach; ?>
        </div>

        <?php if ($success): ?>
            <!-- الإحصائيات -->
            <div class="stats">
                <div class="stat-card primary">
                    <div class="stat-number"><?php echo $tableCount; ?></div>
                    <div class="stat-label">جدول تم إنشاؤه</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">3</div>
                    <div class="stat-label">Views</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">4</div>
                    <div class="stat-label">أنواع بطولات</div>
                </div>
            </div>

            <!-- قائمة الجداول -->
            <div class="tables-list">
                <h3>📋 الجداول المُنشأة:</h3>
                
                <div class="table-item">
                    <div class="table-icon purple">🏆</div>
                    <div>
                        <div class="table-name">tournaments</div>
                        <div class="table-desc">البطولات الرئيسية (الاسم، النوع، الحالة، التواريخ)</div>
                    </div>
                </div>
                
                <div class="table-item">
                    <div class="table-icon blue">👥</div>
                    <div>
                        <div class="table-name">tournament_teams</div>
                        <div class="table-desc">فرق البطولات (مرتبطة بالفصول أو مستقلة)</div>
                    </div>
                </div>
                
                <div class="table-item">
                    <div class="table-icon green">👨‍🎓</div>
                    <div>
                        <div class="table-name">student_teams + student_team_members</div>
                        <div class="table-desc">فرق الطلاب وأعضاؤها (للتوزيع العشوائي)</div>
                    </div>
                </div>
                
                <div class="table-item">
                    <div class="table-icon orange">⚔️</div>
                    <div>
                        <div class="table-name">matches</div>
                        <div class="table-desc">المباريات (الجولات، الأقواس، النتائج، الربط)</div>
                    </div>
                </div>
                
                <div class="table-item">
                    <div class="table-icon purple">📊</div>
                    <div>
                        <div class="table-name">standings</div>
                        <div class="table-desc">جدول ترتيب الدوري (النقاط، الأهداف، الفارق)</div>
                    </div>
                </div>
                
                <div class="table-item">
                    <div class="table-icon blue">⚡</div>
                    <div>
                        <div class="table-name">match_events</div>
                        <div class="table-desc">أحداث المباراة (أهداف، بطاقات، تبديلات)</div>
                    </div>
                </div>
            </div>

            <!-- أنواع البطولات -->
            <div class="features">
                <div class="feature">
                    <div class="feature-title">🏆 خروج مباشر</div>
                    <div class="feature-desc">Single Elimination - الخاسر يخرج فوراً</div>
                </div>
                <div class="feature">
                    <div class="feature-title">🔄 خروج مزدوج</div>
                    <div class="feature-desc">Double Elimination - خسارتان للإقصاء</div>
                </div>
                <div class="feature">
                    <div class="feature-title">📊 دوري دور واحد</div>
                    <div class="feature-desc">Round Robin - كل فريق يلعب مرة</div>
                </div>
                <div class="feature">
                    <div class="feature-title">📊 دوري دورين</div>
                    <div class="feature-desc">Double Round Robin - ذهاب وإياب</div>
                </div>
            </div>

            <!-- الأزرار -->
            <div class="actions">
                <a href="index.html" class="btn btn-success">🚀 الدخول للنظام</a>
                <?php if (!isset($_GET['with_sample_data']) && !$sampleDataCreated): ?>
                    <a href="?with_sample_data=1" class="btn btn-secondary">📦 إضافة بيانات تجريبية</a>
                <?php endif; ?>
            </div>

            <div class="warning">
                ⚠️ <strong>تنبيه أمني:</strong> احذف هذا الملف (install_tournaments.php) بعد التثبيت!
            </div>

        <?php else: ?>
            <div class="actions">
                <a href="install.php" class="btn btn-primary">⬅️ تشغيل المثبّت الرئيسي أولاً</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
