<?php
/**
 * PE Smart School System - Database Installation Script
 * Run this ONCE to create all tables and default admin user
 */

// Use root connection without database first
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS pe_smart_school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE pe_smart_school");
    
    // ========== USERS TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin','teacher','viewer') NOT NULL DEFAULT 'teacher',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    
    // ========== GRADES TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    
    // ========== CLASSES TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_id INT NOT NULL,
        teacher_id INT DEFAULT NULL,
        name VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (grade_id) REFERENCES grades(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_class (grade_id, name, teacher_id)
    ) ENGINE=InnoDB");
    
    // ========== STUDENTS TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_number VARCHAR(20) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        class_id INT NOT NULL,
        date_of_birth DATE,
        health_notes TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    
    // ========== ATTENDANCE TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
        notes VARCHAR(255),
        recorded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id),
        UNIQUE KEY unique_attendance (student_id, attendance_date)
    ) ENGINE=InnoDB");
    
    // ========== FITNESS TESTS TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS fitness_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        unit VARCHAR(30) NOT NULL,
        max_score DECIMAL(5,2) DEFAULT 100,
        min_value DECIMAL(10,2) DEFAULT 0,
        max_value DECIMAL(10,2) DEFAULT 100,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    
    // ========== STUDENT FITNESS RESULTS ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_fitness (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        test_id INT NOT NULL,
        class_id INT NOT NULL,
        raw_value DECIMAL(10,2) NOT NULL,
        calculated_score DECIMAL(5,2) NOT NULL,
        test_date DATE NOT NULL,
        recorded_by INT NOT NULL,
        notes VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (test_id) REFERENCES fitness_tests(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES users(id),
        UNIQUE KEY unique_result (student_id, test_id, test_date)
    ) ENGINE=InnoDB");
    
    // ========== CLASS POINTS TABLE ==========
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        total_points DECIMAL(10,2) DEFAULT 0,
        average_score DECIMAL(5,2) DEFAULT 0,
        rank_position INT DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_class_points (class_id)
    ) ENGINE=InnoDB");
    
    // ========== INSERT DEFAULT ADMIN ==========
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPass, 'مدير النظام', 'admin']);
    
    // Insert demo teacher
    $teacherPass = password_hash('teacher123', PASSWORD_DEFAULT);
    $stmt->execute(['teacher', $teacherPass, 'أحمد المعلم', 'teacher']);
    
    // Insert demo viewer
    $viewerPass = password_hash('viewer123', PASSWORD_DEFAULT);
    $stmt->execute(['viewer', $viewerPass, 'قائد المدرسة', 'viewer']);
    
    // ========== INSERT DEFAULT FITNESS TESTS ==========
    $pdo->exec("INSERT IGNORE INTO fitness_tests (name, description, unit, max_score, min_value, max_value) VALUES 
        ('الجري 50 متر', 'اختبار سرعة الجري لمسافة 50 متر', 'ثانية', 100, 5, 15),
        ('الضغط', 'اختبار تمارين الضغط في دقيقة واحدة', 'عدد', 100, 0, 60),
        ('المرونة', 'اختبار الجلوس والوصول للأمام', 'سنتيمتر', 100, 0, 50),
        ('الجري المكوكي', 'اختبار الجري المكوكي 4×10 متر', 'ثانية', 100, 8, 20),
        ('القفز العمودي', 'اختبار القفز العمودي من الثبات', 'سنتيمتر', 100, 10, 60),
        ('الجلوس من الرقود', 'اختبار البطن في دقيقة واحدة', 'عدد', 100, 0, 50)
    ");
    
    // ========== INSERT SAMPLE GRADES ==========
    $pdo->exec("INSERT IGNORE INTO grades (name, description) VALUES 
        ('الصف الأول الثانوي', 'الصف الأول'),
        ('الصف الثاني الثانوي', 'الصف الثاني'),
        ('الصف الثالث الثانوي', 'الصف الثالث')
    ");
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم تثبيت قاعدة البيانات بنجاح! يمكنك الآن تسجيل الدخول بـ admin/admin123'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'خطأ في التثبيت: ' . $e->getMessage()
    ]);
}
