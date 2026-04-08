<?php
// check_db_schema.php
require_once 'config.php';

$db = getDB();

$required_schema = [
    'student_fitness' => [
        'id',
        'school_id',
        'student_id',
        'test_id',
        'test_date',
        'value',
        'score',
        'recorded_by'
    ],
    'student_measurements' => [
        'id',
        'school_id',
        'student_id',
        'measurement_date',
        'height_cm',
        'weight_kg',
        'bmi',
        'bmi_category',
        'waist_cm',
        'resting_heart_rate',
        'notes',
        'recorded_by'
    ]
];

echo "<h2>فحص بنية قاعدة البيانات</h2>";

foreach ($required_schema as $table => $columns) {
    echo "<h3>الجدول: $table</h3>";

    // فحص وجود الجدول
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color:red;'>⚠️ خطأ: الجدول غير موجود!</p>";
        continue;
    }

    // فحص الأعمدة
    $existing_columns = [];
    $stmt = $db->query("DESCRIBE $table");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }

    echo "<ul>";
    foreach ($columns as $column) {
        if (in_array($column, $existing_columns)) {
            echo "<li style='color:green;'>✅ العمود '$column' موجود.</li>";
        } else {
            echo "<li style='color:red;'>❌ العمود '$column' مفقود!</li>";
        }
    }
    echo "</ul>";

    // فحص القيود الفريدة (Unique Indexes)
    echo "<h4>القيود الفريدة (Unique Indexes):</h4>";
    $stmt = $db->query("SHOW INDEX FROM $table WHERE Non_unique = 0");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($indexes) {
        echo "<ul>";
        foreach ($indexes as $index) {
            echo "<li>الفهرس: <b>{$index['Key_name']}</b> (العمود: {$index['Column_name']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange;'>⚠️ لا توجد قيود فريدة معرفة (قد يسبب ذلك تكراراً في البيانات).</p>";
    }
}


// ============================================================
// ترحيل تلقائي: تعديل UNIQUE KEY لجدول student_fitness
// لدعم تسجيل جلسات متعددة لنفس الطالب والاختبار
// ============================================================
echo "<h2>ترحيل: UNIQUE KEY لجدول student_fitness</h2>";

try {
    // فحص إذا كان uk_student_test القديم ما زال موجوداً
    $checkOld = $db->query("SHOW INDEX FROM student_fitness WHERE Key_name = 'uk_student_test'")->rowCount();
    if ($checkOld > 0) {
        $db->exec("ALTER TABLE student_fitness DROP INDEX uk_student_test");
        echo "<p style='color:orange;'>⚠️ تم حذف القيد القديم (uk_student_test).</p>";
    }

    // فحص إذا كان uk_student_test_date الجديد موجوداً
    $checkNew = $db->query("SHOW INDEX FROM student_fitness WHERE Key_name = 'uk_student_test_date'")->rowCount();
    if ($checkNew == 0) {
        $db->exec("ALTER TABLE student_fitness ADD UNIQUE KEY uk_student_test_date (student_id, test_id, test_date)");
        echo "<p style='color:green;'>✅ تم إضافة القيد الجديد (uk_student_test_date) بنجاح.</p>";
    } else {
        echo "<p style='color:green;'>✅ القيد الجديد (uk_student_test_date) موجود بالفعل.</p>";
    }

    // فحص idx_test_date
    $checkIdx = $db->query("SHOW INDEX FROM student_fitness WHERE Key_name = 'idx_test_date'")->rowCount();
    if ($checkIdx == 0) {
        $db->exec("ALTER TABLE student_fitness ADD INDEX idx_test_date (test_date)");
        echo "<p style='color:green;'>✅ تم إضافة فهرس idx_test_date للأداء.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ خطأ في الترحيل: " . $e->getMessage() . "</p>";
}

echo "<hr><p>تم الانتهاء من الفحص.</p>";
