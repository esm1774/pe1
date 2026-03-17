<?php
/**
 * PE Smart School System - Installer v2.1
 * With Student Measurements & Health Conditions
 * احذف هذا الملف بعد التثبيت!
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
    $tables = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `specialization` VARCHAR(100) DEFAULT NULL,
            `education` VARCHAR(100) DEFAULT NULL,
            `experience_years` INT UNSIGNED DEFAULT NULL,
            `bio` TEXT DEFAULT NULL,
            `birth_date` DATE DEFAULT NULL,
            `role` ENUM('admin','teacher','viewer','supervisor') NOT NULL DEFAULT 'teacher',
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_username` (`username`),
            INDEX `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `grades` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `code` VARCHAR(10) NOT NULL,
            `sort_order` INT UNSIGNED DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `classes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `grade_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(50) NOT NULL,
            `section` VARCHAR(10) NOT NULL,
            `created_by` INT UNSIGNED DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_grade` (`grade_id`),
            CONSTRAINT `fk_classes_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `teacher_classes` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `teacher_id`   INT UNSIGNED NOT NULL,
            `class_id`     INT UNSIGNED NOT NULL,
            `is_temporary` TINYINT(1)   NOT NULL DEFAULT 0,
            `assigned_by`  INT UNSIGNED DEFAULT NULL,
            `assigned_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            `expires_at`   DATE         DEFAULT NULL,
            UNIQUE KEY `uk_teacher_class` (`teacher_id`, `class_id`),
            INDEX `idx_tc_teacher` (`teacher_id`),
            INDEX `idx_tc_class`   (`class_id`),
            CONSTRAINT `fk_tc_teacher`  FOREIGN KEY (`teacher_id`)  REFERENCES `users`(`id`)  ON DELETE CASCADE,
            CONSTRAINT `fk_tc_class`    FOREIGN KEY (`class_id`)    REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `students` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `class_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `student_number` VARCHAR(20) NOT NULL,
            `password` VARCHAR(255) DEFAULT NULL,
            `date_of_birth` DATE DEFAULT NULL,
            `blood_type` ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
            `guardian_phone` VARCHAR(20) DEFAULT NULL,
            `medical_notes` TEXT DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_student_number` (`student_number`),
            INDEX `idx_class` (`class_id`),
            INDEX `idx_name` (`name`),
            CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `student_measurements` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `measurement_date` DATE NOT NULL,
            `height_cm` DECIMAL(5,1) DEFAULT NULL,
            `weight_kg` DECIMAL(5,1) DEFAULT NULL,
            `bmi` DECIMAL(4,1) DEFAULT NULL,
            `bmi_category` ENUM('underweight','normal','overweight','obese') DEFAULT NULL,
            `waist_cm` DECIMAL(5,1) DEFAULT NULL,
            `resting_heart_rate` INT UNSIGNED DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_student` (`student_id`),
            INDEX `idx_date` (`measurement_date`),
            CONSTRAINT `fk_measurements_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `student_health` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `condition_type` ENUM('asthma','diabetes','heart','allergy','bones','vision','exemption','other') NOT NULL,
            `condition_name` VARCHAR(150) NOT NULL,
            `severity` ENUM('mild','moderate','severe') NOT NULL DEFAULT 'mild',
            `notes` TEXT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `start_date` DATE DEFAULT NULL,
            `end_date` DATE DEFAULT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_student` (`student_id`),
            INDEX `idx_type` (`condition_type`),
            INDEX `idx_active` (`is_active`),
            CONSTRAINT `fk_health_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `attendance` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `attendance_date` DATE NOT NULL,
            `status` ENUM('present','absent','late') NOT NULL DEFAULT 'present',
            `notes` VARCHAR(255) DEFAULT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_student_date` (`student_id`, `attendance_date`),
            INDEX `idx_date` (`attendance_date`),
            CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `fitness_tests` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `unit` VARCHAR(30) NOT NULL,
            `type` ENUM('higher_better','lower_better') NOT NULL DEFAULT 'higher_better',
            `max_score` INT UNSIGNED NOT NULL DEFAULT 10,
            `description` TEXT DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `student_fitness` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT UNSIGNED NOT NULL,
            `test_id` INT UNSIGNED NOT NULL,
            `value` DECIMAL(10,2) NOT NULL,
            `score` INT UNSIGNED NOT NULL DEFAULT 0,
            `test_date` DATE NOT NULL,
            `recorded_by` INT UNSIGNED DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_student_test` (`student_id`, `test_id`),
            INDEX `idx_test` (`test_id`),
            CONSTRAINT `fk_fitness_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_fitness_test` FOREIGN KEY (`test_id`) REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `fitness_criteria` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `test_id` INT UNSIGNED NOT NULL,
            `min_value` DECIMAL(10,2) NOT NULL,
            `max_value` DECIMAL(10,2) NOT NULL,
            `score` INT NOT NULL,
            CONSTRAINT `fk_criteria_test` FOREIGN KEY (`test_id`) REFERENCES `fitness_tests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `class_points` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `class_id` INT UNSIGNED NOT NULL,
            `total_score` DECIMAL(10,2) DEFAULT 0,
            `average_score` DECIMAL(5,2) DEFAULT 0,
            `total_points` INT UNSIGNED DEFAULT 0,
            `students_count` INT UNSIGNED DEFAULT 0,
            `rank_position` INT UNSIGNED DEFAULT 0,
            `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_class` (`class_id`),
            CONSTRAINT `fk_points_class` FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `activity_log` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(50) NOT NULL,
            `entity_type` VARCHAR(50) DEFAULT NULL,
            `entity_id` INT UNSIGNED DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `parents` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) DEFAULT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_parent_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `parent_students` (
            `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id`  INT UNSIGNED NOT NULL,
            `student_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_parent_student` (`parent_id`, `student_id`),
            INDEX `idx_ps_parent` (`parent_id`),
            INDEX `idx_ps_student` (`student_id`),
            CONSTRAINT `fk_ps_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $i => $sql) {
        try {
            $db->exec($sql);
            $messages[] = "✅ تم إنشاء الجدول #" . ($i + 1);
        } catch (PDOException $e) {
            $errors[] = "❌ خطأ في الجدول #" . ($i + 1) . ": " . $e->getMessage();
            $success = false;
        }
    }

    if ($success) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
            $count = $stmt->fetch()['cnt'];

            if ($count == 0) {
                $db->beginTransaction();

                // USERS
                $users = [
                    ['admin', 'admin123', 'مدير النظام', 'admin'],
                    ['teacher', 'teacher123', 'أحمد المعلم', 'teacher'],
                    ['viewer', 'viewer123', 'قائد المدرسة', 'viewer'],
                    ['supervisor', 'super123', 'الموجه العام', 'supervisor']
                ];
                $stmt = $db->prepare("INSERT INTO users (username, password, name, role) VALUES (?,?,?,?)");
                foreach ($users as $u) $stmt->execute([$u[0], password_hash($u[1], PASSWORD_BCRYPT, ['cost' => 12]), $u[2], $u[3]]);
                $messages[] = "✅ تم إنشاء المستخدمين (4)";

                // GRADES
                $grades = [['الأول الثانوي','1',1], ['الثاني الثانوي','2',2], ['الثالث الثانوي','3',3]];
                $stmt = $db->prepare("INSERT INTO grades (name, code, sort_order) VALUES (?,?,?)");
                foreach ($grades as $g) $stmt->execute($g);
                $messages[] = "✅ تم إنشاء الصفوف (3)";

                // CLASSES
                $classes = [[1,'1/1','1'],[1,'1/2','2'],[1,'1/3','3'],[2,'2/1','1'],[2,'2/2','2'],[3,'3/1','1'],[3,'3/2','2']];
                $stmt = $db->prepare("INSERT INTO classes (grade_id, name, section, created_by) VALUES (?,?,?,2)");
                foreach ($classes as $c) $stmt->execute($c);
                $messages[] = "✅ تم إنشاء الفصول (7)";

                // INITIAL TEACHER ASSIGNMENTS (Teacher ID = 2)
                $db->exec("INSERT INTO teacher_classes (teacher_id, class_id) SELECT 2, id FROM classes");
                $messages[] = "✅ تم تعيين جميع الفصول للمعلم الافتراضي";

                // STUDENTS with new fields
                $firstNames = ['محمد','أحمد','عبدالله','خالد','فهد','سعد','عمر','يوسف','إبراهيم','عبدالرحمن','سلطان','نايف','بندر','تركي','مشاري','ماجد','وليد','حسن','علي','سعود','طلال','راشد','منصور','فيصل','عادل'];
                $lastNames = ['العتيبي','الشمري','القحطاني','الحربي','المطيري','الدوسري','الغامدي','الزهراني','السبيعي','العنزي','الشهري','البقمي','الجهني','العمري','الرشيدي'];
                $bloodTypes = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];

                $stmt = $db->prepare("INSERT INTO students (class_id, name, student_number, date_of_birth, blood_type, guardian_phone) VALUES (?,?,?,?,?,?)");
                $studentId = 1;
                $totalStudents = 0;

                for ($classId = 1; $classId <= 7; $classId++) {
                    $count = rand(5, 8);
                    for ($i = 0; $i < $count; $i++) {
                        $fname = $firstNames[array_rand($firstNames)];
                        $lname = $lastNames[array_rand($lastNames)];
                        $name = $fname . ' ' . $lname;
                        $number = str_pad(1000 + $studentId, 4, '0', STR_PAD_LEFT);
                        $year = rand(2007, 2010);
                        $month = rand(1, 12);
                        $day = rand(1, 28);
                        $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $blood = $bloodTypes[array_rand($bloodTypes)];
                        $phone = '05' . rand(10000000, 99999999);
                        $stmt->execute([$classId, $name, $number, $dob, $blood, $phone]);
                        $studentId++;
                        $totalStudents++;
                    }
                }
                $messages[] = "✅ تم إنشاء الطلاب ($totalStudents طالب) مع بيانات الميلاد وفصيلة الدم";

                // FITNESS TESTS
                $tests = [
                    ['جري 50 متر','ثانية','lower_better',10],
                    ['الضغط','عدة','higher_better',10],
                    ['الجلوس من الرقود','عدة','higher_better',10],
                    ['المرونة','سم','higher_better',10],
                    ['جري المكوك','ثانية','lower_better',10]
                ];
                $stmt = $db->prepare("INSERT INTO fitness_tests (name, unit, type, max_score) VALUES (?,?,?,?)");
                foreach ($tests as $t) $stmt->execute($t);
                $messages[] = "✅ تم إنشاء اختبارات اللياقة (5)";

                // STUDENT FITNESS RESULTS
                $allStudents = $db->query("SELECT id FROM students WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);
                $stmt = $db->prepare("INSERT INTO student_fitness (student_id, test_id, value, score, test_date) VALUES (?,?,?,?,?)");
                $resultCount = 0;
                foreach ($allStudents as $sid) {
                    for ($testId = 1; $testId <= 5; $testId++) {
                        if (rand(1, 10) > 3) {
                            if ($testId == 1 || $testId == 5) {
                                $value = round(5 + (mt_rand(0, 100) / 10), 1);
                                $score = max(1, min(10, round(10 - ($value - 5) * 0.8)));
                            } else {
                                $value = rand(5, 35);
                                $score = max(1, min(10, round($value / 3.5)));
                            }
                            $stmt->execute([$sid, $testId, $value, $score, '2025-01-15']);
                            $resultCount++;
                        }
                    }
                }
                $messages[] = "✅ تم إنشاء نتائج اللياقة ($resultCount نتيجة)";

                // ATTENDANCE
                $stmtAtt = $db->prepare("INSERT INTO attendance (student_id, attendance_date, status, recorded_by) VALUES (?,?,?,1)");
                $attCount = 0;
                for ($d = 0; $d < 5; $d++) {
                    $date = date('Y-m-d', strtotime("-$d days"));
                    foreach ($allStudents as $sid) {
                        $rand = rand(1, 100);
                        $status = $rand <= 80 ? 'present' : ($rand <= 92 ? 'late' : 'absent');
                        $stmtAtt->execute([$sid, $date, $status]);
                        $attCount++;
                    }
                }
                $messages[] = "✅ تم إنشاء سجلات الحضور ($attCount سجل)";

                // STUDENT MEASUREMENTS (sample data)
                $stmtMeas = $db->prepare("INSERT INTO student_measurements (student_id, measurement_date, height_cm, weight_kg, bmi, bmi_category, waist_cm, resting_heart_rate, recorded_by) VALUES (?,?,?,?,?,?,?,?,1)");
                $measCount = 0;
                $periods = ['2024-09-15', '2025-01-15'];
                foreach ($allStudents as $sid) {
                    $baseHeight = rand(155, 185);
                    $baseWeight = rand(45, 95);
                    foreach ($periods as $pi => $period) {
                        $height = $baseHeight + ($pi * rand(0, 3));
                        $weight = $baseWeight + ($pi * rand(-2, 3));
                        $heightM = $height / 100;
                        $bmi = round($weight / ($heightM * $heightM), 1);
                        if ($bmi < 18.5) $bmiCat = 'underweight';
                        elseif ($bmi < 25) $bmiCat = 'normal';
                        elseif ($bmi < 30) $bmiCat = 'overweight';
                        else $bmiCat = 'obese';
                        $waist = rand(60, 100);
                        $heartRate = rand(60, 95);
                        $stmtMeas->execute([$sid, $period, $height, $weight, $bmi, $bmiCat, $waist, $heartRate]);
                        $measCount++;
                    }
                }
                $messages[] = "✅ تم إنشاء القياسات الجسمية ($measCount قياس - فترتين لكل طالب)";

                // STUDENT HEALTH CONDITIONS (for some students)
                $healthConditions = [
                    ['asthma', 'ربو', 'mild', 'يحتاج بخاخ قبل الجري الطويل'],
                    ['asthma', 'ربو شديد', 'severe', 'يحتاج بخاخ دائم - تجنب الجري المطول'],
                    ['diabetes', 'سكري نوع 1', 'moderate', 'يحتاج متابعة مستوى السكر قبل التمارين'],
                    ['heart', 'عيب خلقي بالقلب', 'severe', 'تجنب التمارين العنيفة - حسب تقرير الطبيب'],
                    ['allergy', 'حساسية صدرية', 'mild', 'تظهر مع الغبار والأتربة'],
                    ['bones', 'كسر سابق بالذراع', 'mild', 'تعافى - يحتاج حذر في تمارين الذراعين'],
                    ['bones', 'خشونة بالركبة', 'moderate', 'تجنب القفز والجري الطويل'],
                    ['vision', 'ضعف نظر شديد', 'moderate', 'يرتدي نظارة - حذر في الرياضات الجماعية'],
                    ['exemption', 'إعفاء طبي جزئي', 'moderate', 'معفى من اختبارات الجري فقط'],
                    ['exemption', 'إعفاء طبي كامل', 'severe', 'معفى من جميع الاختبارات البدنية'],
                    ['other', 'صداع نصفي متكرر', 'mild', 'يحتاج راحة عند الإحساس بالصداع'],
                    ['allergy', 'حساسية من الشمس', 'mild', 'تجنب التمارين في الشمس المباشرة']
                ];
                $stmtHealth = $db->prepare("INSERT INTO student_health (student_id, condition_type, condition_name, severity, notes, is_active, start_date, recorded_by) VALUES (?,?,?,?,?,1,?,1)");
                $healthCount = 0;
                $usedStudents = [];
                foreach ($healthConditions as $hc) {
                    $sid = $allStudents[array_rand($allStudents)];
                    while (in_array($sid, $usedStudents) && count($usedStudents) < count($allStudents)) {
                        $sid = $allStudents[array_rand($allStudents)];
                    }
                    $usedStudents[] = $sid;
                    $stmtHealth->execute([$sid, $hc[0], $hc[1], $hc[2], $hc[3], '2024-09-01']);
                    $healthCount++;
                }
                $messages[] = "✅ تم إنشاء الحالات الصحية ($healthCount حالة لطلاب مختلفين)";

                $db->commit();
                $messages[] = "🎉 تم تثبيت النظام بنجاح مع جميع البيانات التجريبية!";
            } else {
                $messages[] = "ℹ️ البيانات موجودة بالفعل - تم تخطي التعبئة";
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "❌ خطأ في تعبئة البيانات: " . $e->getMessage();
            $success = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت PE Smart School System v2.1</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
        *{font-family:'Cairo',sans-serif;margin:0;padding:0;box-sizing:border-box}
        body{background:linear-gradient(135deg,#059669,#0d9488);min-height:100vh;padding:40px 20px}
        .container{max-width:700px;margin:0 auto}
        .card{background:#fff;border-radius:20px;padding:40px;box-shadow:0 20px 60px rgba(0,0,0,.15)}
        .logo{text-align:center;margin-bottom:30px}
        .logo span{font-size:60px;display:block;margin-bottom:10px}
        .logo h1{color:#1f2937;font-size:24px}
        .logo p{color:#6b7280;margin-top:5px}
        .msg{padding:10px 14px;border-radius:8px;margin-bottom:6px;font-size:13px;font-weight:600}
        .msg-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .msg-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .credentials{background:#f9fafb;border-radius:12px;padding:20px;margin:20px 0;border:1px solid #e5e7eb}
        .credentials h3{margin-bottom:10px;color:#374151}
        .cred-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e5e7eb;font-size:14px}
        .cred-item:last-child{border-bottom:none}
        .btn{display:inline-block;background:#059669;color:#fff;padding:14px 30px;border-radius:12px;text-decoration:none;font-weight:700;font-size:16px;margin-top:20px;transition:.3s}
        .btn:hover{background:#047857;transform:translateY(-2px)}
        .warning{background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:15px;border-radius:10px;margin-top:20px;font-size:14px;font-weight:600}
        .new-badge{background:#10b981;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-right:5px}
    </style>
</head>
<body>
<div class="container"><div class="card">
    <div class="logo">
        <span>🏃</span>
        <h1>PE Smart School System</h1>
        <p>معالج التثبيت - الإصدار 2.1 <span class="new-badge">+ قياسات وصحة</span></p>
    </div>

    <div class="messages">
        <?php foreach ($messages as $msg): ?><div class="msg msg-success"><?=$msg?></div><?php endforeach; ?>
        <?php foreach ($errors as $err): ?><div class="msg msg-error"><?=$err?></div><?php endforeach; ?>
    </div>

    <?php if ($success): ?>
        <div class="credentials">
            <h3>🔐 بيانات الدخول التجريبية:</h3>
            <div class="cred-item"><span>👑 مدير:</span><span style="direction:ltr;font-weight:700">admin / admin123</span></div>
            <div class="cred-item"><span>👨‍🏫 معلم:</span><span style="direction:ltr;font-weight:700">teacher / teacher123</span></div>
            <div class="cred-item"><span>👁️ مشاهد:</span><span style="direction:ltr;font-weight:700">viewer / viewer123</span></div>
        </div>
        <div class="credentials" style="border-color:#a7f3d0;background:#ecfdf5">
            <h3>📊 البيانات التجريبية تشمل:</h3>
            <p style="font-size:13px;color:#065f46;line-height:2">
                ✅ 3 صفوف دراسية و 7 فصول<br>
                ✅ طلاب مع تاريخ الميلاد وفصيلة الدم ورقم ولي الأمر<br>
                ✅ قياسات جسمية لفترتين (سبتمبر + يناير) مع حساب BMI<br>
                ✅ حالات صحية متنوعة (ربو، سكري، إعفاء طبي...)<br>
                ✅ 5 اختبارات لياقة مع نتائج<br>
                ✅ سجلات حضور لـ 5 أيام
            </p>
        </div>
        <div style="text-align:center"><a href="app.html" class="btn">🚀 الدخول إلى النظام</a></div>
        <div class="warning">⚠️ <strong>تنبيه أمني:</strong> احذف هذا الملف (install.php) بعد التثبيت!</div>
    <?php endif; ?>
</div></div>
</body>
</html>
