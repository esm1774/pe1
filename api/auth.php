<?php
/**
 * PE Smart School System - Auth API
 */

function checkAuth() {
    if (isLoggedIn()) {
        $db = getDB();
        if ($_SESSION['user_role'] === 'student') {
            $stmt = $db->prepare("SELECT id, student_number, name, 'student' as role, class_id FROM students WHERE id = ? AND active = 1");
        } elseif ($_SESSION['user_role'] === 'parent') {
            $stmt = $db->prepare("SELECT id, username, name, 'parent' as role FROM parents WHERE id = ? AND active = 1");
        } else {
            $stmt = $db->prepare("SELECT id, username, name, role FROM users WHERE id = ? AND active = 1");
        }
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) jsonSuccess($user);
    }
    jsonResponse(['success' => false, 'error' => 'غير مسجل'], 401);
}

function login() {
    $data = getPostData();
    $username = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
    if (empty($username) || empty($password)) jsonError('الرجاء إدخال اسم المستخدم وكلمة المرور');

    $db = getDB();
    
    // 1. Try Staff (users table)
    $stmt = $db->prepare("SELECT id, username, password, name, role FROM users WHERE username = ? AND active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        logActivity('login', 'user', $user['id']);
        unset($user['password']);
        jsonSuccess($user, 'تم تسجيل الدخول بنجاح');
        return;
    }

    // 2. Try Student (students table)
    $stmt = $db->prepare("SELECT id, student_number, password, name, class_id FROM students WHERE student_number = ? AND active = 1");
    $stmt->execute([$username]);
    $student = $stmt->fetch();

    if ($student && !empty($student['password']) && password_verify($password, $student['password'])) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['user_role'] = 'student';
        $_SESSION['user_name'] = $student['name'];
        $_SESSION['class_id'] = $student['class_id'];

        $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?")->execute([$student['id']]);
        logActivity('student_login', 'student', $student['id']);
        
        $response = [
            'id' => $student['id'],
            'username' => $student['student_number'],
            'name' => $student['name'],
            'role' => 'student',
            'class_id' => $student['class_id']
        ];
        jsonSuccess($response, 'مرحباً بك في بوابة الطالب');
        return;
    }

    // 3. Try Parent (parents table)
    $stmt = $db->prepare("SELECT id, username, password, name FROM parents WHERE username = ? AND active = 1");
    $stmt->execute([$username]);
    $parent = $stmt->fetch();

    if ($parent && password_verify($password, $parent['password'])) {
        $_SESSION['user_id'] = $parent['id'];
        $_SESSION['user_role'] = 'parent';
        $_SESSION['user_name'] = $parent['name'];

        $db->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?")->execute([$parent['id']]);
        logActivity('parent_login', 'parent', $parent['id']);
        unset($parent['password']);
        $parent['role'] = 'parent';
        jsonSuccess($parent, 'مرحباً بك في بوابة ولي الأمر');
        return;
    }

    // Fallback: Failed for both
    logActivity('login_failed', 'auth', null, "Input: $username");
    jsonError('بيانات الدخول غير صحيحة');
}

/**
 * Student Login
 */
function studentLogin() {
    $data = getPostData();
    $student_number = sanitize($data['username'] ?? ''); // student_number as username
    $password = $data['password'] ?? '';
    
    if (empty($student_number) || empty($password)) {
        jsonError('الرجاء إدخال رقم الطالب وكلمة المرور');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, student_number, password, name, class_id FROM students WHERE student_number = ? AND active = 1");
    $stmt->execute([$student_number]);
    $student = $stmt->fetch();

    // Note: If student has no password set (null), we might want to treat the first login 
    // differently or require admin to set it. For now, we assume a password must match.
    if (!$student || empty($student['password']) || !password_verify($password, $student['password'])) {
        logActivity('student_login_failed', 'student', null, "Number: $student_number");
        jsonError('بيانات الدخول غير صحيحة');
    }

    $_SESSION['user_id'] = $student['id'];
    $_SESSION['user_role'] = 'student';
    $_SESSION['user_name'] = $student['name'];
    $_SESSION['class_id'] = $student['class_id'];

    $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?")->execute([$student['id']]);
    logActivity('student_login', 'student', $student['id']);
    
    unset($student['password']);
    jsonSuccess($student, 'مرحباً بك في بوابة الطالب');
}

function logout() {
    logActivity('logout', 'user', $_SESSION['user_id'] ?? null);
    session_destroy();
    jsonSuccess(null, 'تم تسجيل الخروج');
}
