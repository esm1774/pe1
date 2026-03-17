<?php
/**
 * PE Smart School System - Database Updater v3.1
 * قم برفع هذا الملف وتشغيله من المتصفح لتحديث قاعدة البيانات
 */

require_once 'config.php';

// تأمين الملف ليكون متاحاً للأدمن فقط أو عبر كلمة مرور بسيطة (اختياري)
// هنا سنقوم فقط بالتأكد من وجود الجداول

header('Content-Type: text/html; charset=utf-8');

echo '<html><head><title>Database Update</title><style>
    body { font-family: sans-serif; background: #f0f2f5; padding: 40px; text-align: center; }
    .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); display: inline-block; max-width: 500px; width: 100%; }
    .success { color: #10b981; font-weight: bold; margin: 10px 0; }
    .info { color: #3b82f6; font-size: 14px; margin: 20px 0; text-align: right; direction: rtl; }
    .error { color: #ef4444; font-weight: bold; }
    .btn { background: #059669; color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; display: inline-block; margin-top: 20px; }
</style></head><body>';

echo '<div class="card">';
echo '<h2>⚙️ تحديث قاعدة البيانات - v3.1</h2>';

try {
    $db = getDB();
    
    // 1. إنشاء جدول معايير اللياقة (Fitness Criteria)
    $sql = "CREATE TABLE IF NOT EXISTS `fitness_criteria` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `test_id` INT UNSIGNED NOT NULL,
        `min_value` DECIMAL(10,2) NOT NULL,
        `max_value` DECIMAL(10,2) NOT NULL,
        `score` INT NOT NULL,
        CONSTRAINT `fk_update_criteria_test` FOREIGN KEY (`test_id`) REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo '<div class="success">✅ تم إنشاء وتحديث جدول معايير اللياقة بنجاح</div>';
    
    echo '<div class="info">';
    echo '• تم ربط المعايير بنظام الرصد الآلي.<br>';
    echo '• تم ضمان استمرارية العمل مع نسخة v3.1.<br>';
    echo '• قاعدة البيانات الآن جاهزة للعمل.';
    echo '</div>';

    echo '<p>يمكنك الآن العودة للنظام والاستمتاع بالميزات الجديدة.</p>';
    echo '<a href="app.html" class="btn">العودة للوحة التحكم</a>';
    
    // تأكيد الحذف
    echo '<p style="font-size: 11px; color: #999; margin-top: 30px;">⚠️ من أجل الأمان، يفضل حذف هذا الملف (update_db.php) بعد الانتهاء.</p>';

} catch (Exception $e) {
    echo '<div class="error">❌ حدث خطأ أثناء التحديث:</div>';
    echo '<pre style="text-align:left; background:#fff1f2; padding:10px; border-radius:10px; margin-top:10px;">' . $e->getMessage() . '</pre>';
}

echo '</div></body></html>';
