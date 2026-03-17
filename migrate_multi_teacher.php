<?php
/**
 * PE Smart School System - Multi-Teacher Migration
 * =================================================
 * يضيف دعم تعدد المعلمين للنظام الحالي
 * شغّل هذا الملف مرة واحدة فقط ثم احذفه!
 */

require_once 'config.php';

$messages = [];
$errors   = [];
$success  = true;

try {
    $db = getDB();

    // ── 1. إضافة عمود created_by لجدول classes ──────────────────────────
    try {
        $db->exec("ALTER TABLE `classes` ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL AFTER `active`");
        $db->exec("ALTER TABLE `classes` ADD CONSTRAINT `fk_classes_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        $messages[] = "✅ تم إضافة عمود created_by لجدول classes";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            $messages[] = "ℹ️ عمود created_by موجود بالفعل - تم التخطي";
        } else {
            throw $e;
        }
    }

    // ── 2. إنشاء جدول teacher_classes ───────────────────────────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS `teacher_classes` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `teacher_id`   INT UNSIGNED NOT NULL,
            `class_id`     INT UNSIGNED NOT NULL,
            `is_temporary` TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = تعيين مؤقت من الإدارة',
            `assigned_by`  INT UNSIGNED DEFAULT NULL COMMENT 'من قام بالتعيين (admin_id)',
            `assigned_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            `expires_at`   DATE         DEFAULT NULL COMMENT 'تاريخ انتهاء التعيين المؤقت',
            UNIQUE KEY `uk_teacher_class` (`teacher_id`, `class_id`),
            INDEX `idx_teacher`   (`teacher_id`),
            INDEX `idx_class`     (`class_id`),
            INDEX `idx_temp`      (`is_temporary`),
            CONSTRAINT `fk_tc_teacher`  FOREIGN KEY (`teacher_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
            CONSTRAINT `fk_tc_class`    FOREIGN KEY (`class_id`)    REFERENCES `classes`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tc_assigned` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='ربط المعلمين بفصولهم (دائم أو مؤقت)'
    ");
    $messages[] = "✅ تم إنشاء جدول teacher_classes";

    // ── 3. ربط الفصول الحالية بالمعلم الأول في النظام ──────────────────
    $firstTeacher = $db->query("SELECT id FROM users WHERE role = 'teacher' AND active = 1 ORDER BY id LIMIT 1")->fetch();
    $allClasses   = $db->query("SELECT id FROM classes WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);

    if ($firstTeacher && !empty($allClasses)) {
        $teacherId = $firstTeacher['id'];
        $stmt = $db->prepare("INSERT IGNORE INTO teacher_classes (teacher_id, class_id) VALUES (?, ?)");
        $count = 0;
        foreach ($allClasses as $classId) {
            $stmt->execute([$teacherId, $classId]);
            $count++;
        }
        // ربط created_by بنفس المعلم
        $db->prepare("UPDATE classes SET created_by = ? WHERE active = 1 AND created_by IS NULL")->execute([$teacherId]);
        $messages[] = "✅ تم ربط $count فصل بالمعلم الأول (id=$teacherId)";
    } else {
        $messages[] = "ℹ️ لا يوجد معلمون حاليون - الفصول غير مرتبطة بعد";
    }

    $messages[] = "🎉 تم الترحيل بنجاح! احذف هذا الملف الآن.";

} catch (Exception $e) {
    $errors[] = "❌ خطأ: " . $e->getMessage();
    $success  = false;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ترحيل دعم تعدد المعلمين</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
        *{font-family:'Cairo',sans-serif;margin:0;padding:0;box-sizing:border-box}
        body{background:linear-gradient(135deg,#1e40af,#7c3aed);min-height:100vh;padding:40px 20px}
        .card{max-width:700px;margin:0 auto;background:#fff;border-radius:20px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        h1{text-align:center;color:#1e40af;margin-bottom:30px;font-size:22px}
        .msg{padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:13px;font-weight:600}
        .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;margin-top:20px;padding:15px;border-radius:10px}
        .btn{display:inline-block;background:#1e40af;color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;margin-top:20px}
    </style>
</head>
<body>
<div class="card">
    <h1>🔄 ترحيل دعم تعدد المعلمين</h1>
    <?php foreach ($messages as $m): ?>
        <div class="msg ok"><?= $m ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="msg err"><?= $e ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <div class="warn">
            ⚠️ <strong>مهم:</strong> احذف هذا الملف (<code>migrate_multi_teacher.php</code>) الآن لأسباب أمنية!
        </div>
        <div style="text-align:center">
            <a href="app.html" class="btn">🚀 الدخول للنظام</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
