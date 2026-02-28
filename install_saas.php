<?php
/**
 * PE Smart School System - SaaS Migration Installer
 * ====================================================
 * يُنشئ الجداول الجديدة ويضيف school_id للجداول الحالية
 * 
 * 🔧 طريقة الاستخدام:
 * زُر: https://yourdomain.com/install_saas.php
 * احذف الملف بعد التثبيت!
 */

require_once 'config.php';

$messages = [];
$errors = [];
$success = true;

try {
    $db = getDB();
    $messages[] = "✅ تم الاتصال بقاعدة البيانات بنجاح";
} catch (Exception $e) {
    $errors[] = "❌ فشل الاتصال: " . $e->getMessage();
    $success = false;
}

if ($success) {

    // ============================================================
    // STEP 1: Create SaaS Core Tables
    // ============================================================

    $saasCoreTables = [

        // خطط الاشتراك
        "plans" => "CREATE TABLE IF NOT EXISTS `plans` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL COMMENT 'اسم الخطة',
            `name_en` VARCHAR(100) DEFAULT NULL COMMENT 'English name',
            `slug` VARCHAR(50) NOT NULL COMMENT 'معرف فريد',
            `description` TEXT DEFAULT NULL,
            `price_monthly` DECIMAL(10,2) DEFAULT 0 COMMENT 'السعر الشهري',
            `price_yearly` DECIMAL(10,2) DEFAULT 0 COMMENT 'السعر السنوي',
            `max_students` INT UNSIGNED DEFAULT 100,
            `max_teachers` INT UNSIGNED DEFAULT 5,
            `max_classes` INT UNSIGNED DEFAULT 10,
            `features` JSON DEFAULT NULL COMMENT '{\"tournaments\": true, \"badges\": true, ...}',
            `is_default` TINYINT(1) DEFAULT 0 COMMENT 'الخطة الافتراضية للتسجيل',
            `active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_plan_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='خطط الاشتراك'",

        // المدارس (المستأجرين)
        "schools" => "CREATE TABLE IF NOT EXISTS `schools` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(200) NOT NULL COMMENT 'اسم المدرسة',
            `slug` VARCHAR(50) NOT NULL COMMENT 'المعرف الفريد (للنطاق الفرعي)',
            `logo_url` VARCHAR(500) DEFAULT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `region` VARCHAR(100) DEFAULT NULL,
            `timezone` VARCHAR(50) DEFAULT 'Asia/Riyadh',
            `plan_id` INT UNSIGNED DEFAULT NULL COMMENT 'خطة الاشتراك',
            `subscription_status` ENUM('trial','active','suspended','cancelled') DEFAULT 'trial',
            `trial_ends_at` DATE DEFAULT NULL,
            `subscription_ends_at` DATE DEFAULT NULL,
            `max_students` INT UNSIGNED DEFAULT 100,
            `max_teachers` INT UNSIGNED DEFAULT 5,
            `settings` JSON DEFAULT NULL COMMENT 'إعدادات خاصة بالمدرسة',
            `active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_school_slug` (`slug`),
            INDEX `idx_school_status` (`subscription_status`),
            INDEX `idx_school_active` (`active`),
            CONSTRAINT `fk_school_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المدارس (المستأجرين)'",

        // مديرو المنصة
        "platform_admins" => "CREATE TABLE IF NOT EXISTS `platform_admins` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `role` ENUM('super_admin','support') DEFAULT 'super_admin',
            `active` TINYINT(1) DEFAULT 1,
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_platform_admin_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مديرو المنصة'"
    ];

    foreach ($saasCoreTables as $tableName => $sql) {
        try {
            $exists = $db->query("SHOW TABLES LIKE '$tableName'")->fetch();
            if ($exists) {
                $messages[] = "ℹ️ الجدول '$tableName' موجود بالفعل";
            } else {
                $db->exec($sql);
                $messages[] = "✅ تم إنشاء جدول '$tableName'";
            }
        } catch (PDOException $e) {
            $errors[] = "❌ خطأ في جدول '$tableName': " . $e->getMessage();
            $success = false;
        }
    }

    // ============================================================
    // STEP 2: Add school_id to existing tables
    // ============================================================

    // Tables that need school_id directly
    $tablesNeedingSchoolId = [
        'users'         => 'بعد id',
        'grades'        => 'بعد id',
        'classes'       => 'بعد id',
        'students'      => 'بعد id',
        'fitness_tests' => 'بعد id',
        'parents'       => 'بعد id',
        'activity_log'  => 'بعد id',
    ];

    // Tables with school_id (existing that may have been added by tournaments/badges etc)
    $optionalTablesNeedingSchoolId = [
        'badges'        => 'بعد id',
        'tournaments'   => 'بعد id',
        'sports_teams'  => 'بعد id',
    ];

    foreach ($tablesNeedingSchoolId as $table => $note) {
        try {
            $cols = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
            if (!in_array('school_id', $cols)) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `school_id` INT UNSIGNED DEFAULT NULL AFTER `id`");
                $db->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_school` (`school_id`)");
                $messages[] = "✅ تم إضافة school_id لجدول '$table'";
            } else {
                $messages[] = "ℹ️ school_id موجود بالفعل في '$table'";
            }
        } catch (PDOException $e) {
            $errors[] = "❌ خطأ في تعديل '$table': " . $e->getMessage();
        }
    }

    // Optional tables (may not exist)
    foreach ($optionalTablesNeedingSchoolId as $table => $note) {
        try {
            $tableExists = $db->query("SHOW TABLES LIKE '$table'")->fetch();
            if (!$tableExists) {
                $messages[] = "ℹ️ تخطي '$table' (غير موجود)";
                continue;
            }
            $cols = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
            if (!in_array('school_id', $cols)) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `school_id` INT UNSIGNED DEFAULT NULL AFTER `id`");
                $db->exec("ALTER TABLE `$table` ADD INDEX `idx_{$table}_school` (`school_id`)");
                $messages[] = "✅ تم إضافة school_id لجدول '$table'";
            } else {
                $messages[] = "ℹ️ school_id موجود بالفعل في '$table'";
            }
        } catch (PDOException $e) {
            $messages[] = "⚠️ لم يتم تعديل '$table': " . $e->getMessage();
        }
    }

    // ============================================================
    // STEP 3: Modify unique keys to include school_id
    // ============================================================

    // users.uk_username → users.uk_school_username (school_id + username)
    try {
        // Check if old unique key exists
        $keys = $db->query("SHOW INDEX FROM users WHERE Key_name = 'uk_username'")->fetchAll();
        if (!empty($keys)) {
            $db->exec("ALTER TABLE `users` DROP INDEX `uk_username`");
            $db->exec("ALTER TABLE `users` ADD UNIQUE KEY `uk_school_username` (`school_id`, `username`)");
            $messages[] = "✅ تم تعديل UNIQUE KEY لـ users (school_id + username)";
        }
    } catch (PDOException $e) {
        $messages[] = "⚠️ تعديل UNIQUE KEY لـ users: " . $e->getMessage();
    }

    // students.uk_student_number → students.uk_school_student_number
    try {
        $keys = $db->query("SHOW INDEX FROM students WHERE Key_name = 'uk_student_number'")->fetchAll();
        if (!empty($keys)) {
            $db->exec("ALTER TABLE `students` DROP INDEX `uk_student_number`");
            $db->exec("ALTER TABLE `students` ADD UNIQUE KEY `uk_school_student_number` (`school_id`, `student_number`)");
            $messages[] = "✅ تم تعديل UNIQUE KEY لـ students (school_id + student_number)";
        }
    } catch (PDOException $e) {
        $messages[] = "⚠️ تعديل UNIQUE KEY لـ students: " . $e->getMessage();
    }

    // parents.uk_parent_username → parents.uk_school_parent_username
    try {
        $keys = $db->query("SHOW INDEX FROM parents WHERE Key_name = 'uk_parent_username'")->fetchAll();
        if (!empty($keys)) {
            $db->exec("ALTER TABLE `parents` DROP INDEX `uk_parent_username`");
            $db->exec("ALTER TABLE `parents` ADD UNIQUE KEY `uk_school_parent_username` (`school_id`, `username`)");
            $messages[] = "✅ تم تعديل UNIQUE KEY لـ parents (school_id + username)";
        }
    } catch (PDOException $e) {
        $messages[] = "⚠️ تعديل UNIQUE KEY لـ parents: " . $e->getMessage();
    }

    // ============================================================
    // STEP 4: Insert default data
    // ============================================================

    try {
        // Default plans
        $planCount = $db->query("SELECT COUNT(*) FROM plans")->fetchColumn();
        if ($planCount == 0) {
            $db->exec("INSERT INTO plans (name, name_en, slug, price_monthly, price_yearly, max_students, max_teachers, max_classes, features, is_default, sort_order) VALUES
                ('مجاني', 'Free', 'free', 0, 0, 50, 2, 5, '{\"tournaments\": false, \"badges\": false, \"notifications\": true, \"reports\": false, \"sports_teams\": false, \"certificates\": false}', 0, 1),
                ('أساسي', 'Basic', 'basic', 99, 999, 200, 5, 15, '{\"tournaments\": false, \"badges\": true, \"notifications\": true, \"reports\": true, \"sports_teams\": false, \"certificates\": true}', 1, 2),
                ('متقدم', 'Advanced', 'advanced', 199, 1999, 500, 15, 30, '{\"tournaments\": true, \"badges\": true, \"notifications\": true, \"reports\": true, \"sports_teams\": true, \"certificates\": true}', 0, 3),
                ('مؤسسي', 'Enterprise', 'enterprise', 499, 4999, 9999, 999, 999, '{\"tournaments\": true, \"badges\": true, \"notifications\": true, \"reports\": true, \"sports_teams\": true, \"certificates\": true}', 0, 4)
            ");
            $messages[] = "✅ تم إنشاء خطط الاشتراك الافتراضية (4 خطط)";
        }

        // Default platform admin
        $adminCount = $db->query("SELECT COUNT(*) FROM platform_admins")->fetchColumn();
        if ($adminCount == 0) {
            $password = password_hash('superadmin123', PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("INSERT INTO platform_admins (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)")
               ->execute(['superadmin', $password, 'مدير المنصة', 'admin@pesmart.com', 'super_admin']);
            $messages[] = "✅ تم إنشاء حساب مدير المنصة (superadmin / superadmin123)";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠️ خطأ في البيانات الافتراضية: " . $e->getMessage();
    }

    // ============================================================
    // STEP 5: Migrate existing data to default school
    // ============================================================

    try {
        $schoolCount = $db->query("SELECT COUNT(*) FROM schools")->fetchColumn();
        if ($schoolCount == 0) {
            // Create the default school from existing data
            $trialEnd = date('Y-m-d', strtotime('+30 days'));
            $basicPlanId = $db->query("SELECT id FROM plans WHERE slug = 'advanced' LIMIT 1")->fetchColumn();

            $db->prepare("INSERT INTO schools (name, slug, timezone, plan_id, subscription_status, trial_ends_at, max_students, max_teachers, active) VALUES (?, ?, ?, ?, 'trial', ?, 9999, 999, 1)")
               ->execute(['المدرسة الافتراضية', 'default', 'Asia/Riyadh', $basicPlanId, $trialEnd]);
            $defaultSchoolId = $db->lastInsertId();
            $messages[] = "✅ تم إنشاء المدرسة الافتراضية (ID: $defaultSchoolId)";

            // Update ALL existing records to belong to the default school
            $tablesToUpdate = ['users', 'grades', 'classes', 'students', 'fitness_tests', 'parents', 'activity_log'];
            foreach ($tablesToUpdate as $table) {
                try {
                    $updated = $db->exec("UPDATE `$table` SET school_id = $defaultSchoolId WHERE school_id IS NULL");
                    if ($updated > 0) {
                        $messages[] = "✅ تم ربط $updated سجل في '$table' بالمدرسة الافتراضية";
                    }
                } catch (Exception $e) {
                    // Silent
                }
            }

            // Optional tables
            foreach (['badges', 'tournaments', 'sports_teams'] as $table) {
                try {
                    $tableExists = $db->query("SHOW TABLES LIKE '$table'")->fetch();
                    if ($tableExists) {
                        $updated = $db->exec("UPDATE `$table` SET school_id = $defaultSchoolId WHERE school_id IS NULL");
                        if ($updated > 0) {
                            $messages[] = "✅ تم ربط $updated سجل في '$table' بالمدرسة الافتراضية";
                        }
                    }
                } catch (Exception $e) {
                    // Silent
                }
            }
        } else {
            $messages[] = "ℹ️ مدارس موجودة بالفعل - تم تخطي الترحيل";
        }
    } catch (PDOException $e) {
        $errors[] = "⚠️ خطأ في ترحيل البيانات: " . $e->getMessage();
    }
}

// ============================================================
// Display Results
// ============================================================
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت نظام SaaS - PE Smart School</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap');
        * { font-family: 'Cairo', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, #7c3aed, #2563eb); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 25px 80px rgba(0,0,0,0.2); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo span { font-size: 70px; display: block; margin-bottom: 10px; }
        .logo h1 { color: #1f2937; font-size: 26px; font-weight: 800; }
        .logo p { color: #6b7280; margin-top: 5px; }
        .badge { display: inline-block; background: linear-gradient(135deg, #7c3aed, #2563eb); color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .msg { padding: 12px 16px; border-radius: 10px; margin-bottom: 8px; font-size: 14px; font-weight: 600; }
        .msg-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .msg-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .msg-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .credentials { background: #f9fafb; border-radius: 12px; padding: 20px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .credentials h3 { margin-bottom: 10px; color: #374151; }
        .cred-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .cred-item:last-child { border-bottom: none; }
        .plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 20px 0; }
        .plan-card { border: 2px solid #e5e7eb; border-radius: 16px; padding: 16px; text-align: center; }
        .plan-card.featured { border-color: #7c3aed; background: #f5f3ff; }
        .plan-name { font-weight: 800; font-size: 16px; color: #374151; }
        .plan-price { font-size: 24px; font-weight: 800; color: #7c3aed; margin: 8px 0; }
        .plan-price small { font-size: 12px; color: #6b7280; font-weight: 400; }
        .plan-detail { font-size: 12px; color: #6b7280; }
        .btn { display: inline-block; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 15px; margin-top: 16px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #7c3aed, #2563eb); color: #fff; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124,58,237,0.4); }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
        .warning { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 12px; margin-top: 24px; font-size: 14px; font-weight: 600; }
    </style>
</head>
<body>
<div class="container"><div class="card">
    <div class="logo">
        <span>🏢</span>
        <h1>تحويل النظام إلى SaaS</h1>
        <p><span class="badge">Multi-Tenant SaaS v1.0</span></p>
    </div>

    <div class="messages">
        <?php foreach ($messages as $msg): ?>
            <div class="msg <?php
                if (strpos($msg, '✅') !== false) echo 'msg-success';
                elseif (strpos($msg, '❌') !== false) echo 'msg-error';
                elseif (strpos($msg, '⚠️') !== false) echo 'msg-warn';
                else echo 'msg-info';
            ?>"><?= $msg ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="msg msg-error"><?= $err ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($success): ?>
        <div class="credentials">
            <h3>🔐 بيانات دخول مدير المنصة (Super Admin):</h3>
            <div class="cred-item"><span>👑 المستخدم:</span><span style="direction:ltr;font-weight:700">superadmin</span></div>
            <div class="cred-item"><span>🔑 كلمة المرور:</span><span style="direction:ltr;font-weight:700">superadmin123</span></div>
        </div>

        <h3 style="margin: 20px 0 10px;">📋 خطط الاشتراك المُنشأة:</h3>
        <div class="plans">
            <div class="plan-card">
                <div class="plan-name">مجاني</div>
                <div class="plan-price">0<small> ر.س/شهر</small></div>
                <div class="plan-detail">50 طالب • 2 معلم</div>
            </div>
            <div class="plan-card featured">
                <div class="plan-name">⭐ أساسي</div>
                <div class="plan-price">99<small> ر.س/شهر</small></div>
                <div class="plan-detail">200 طالب • 5 معلمين</div>
            </div>
            <div class="plan-card">
                <div class="plan-name">متقدم</div>
                <div class="plan-price">199<small> ر.س/شهر</small></div>
                <div class="plan-detail">500 طالب • 15 معلم</div>
            </div>
            <div class="plan-card">
                <div class="plan-name">مؤسسي</div>
                <div class="plan-price">499<small> ر.س/شهر</small></div>
                <div class="plan-detail">غير محدود</div>
            </div>
        </div>

        <div class="actions">
            <a href="index.html" class="btn btn-primary">🚀 الدخول للنظام</a>
        </div>

        <div class="warning">
            ⚠️ <strong>أمان:</strong> احذف هذا الملف (install_saas.php) بعد التثبيت! وغيّر كلمة مرور مدير المنصة.
        </div>
    <?php endif; ?>
</div></div>
</body>
</html>
