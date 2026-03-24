<?php
/**
 * PE Smart School System - Auth API (SaaS-Ready)
 * ================================================
 * يدعم تسجيل الدخول مع تحديد المدرسة تلقائياً
 */

// Check Auth
function checkAuth() {
    if (isLoggedIn()) {
        $db = getDB();
        if ($_SESSION['user_role'] === 'student') {
            $stmt = $db->prepare("SELECT id, student_number, name, 'student' as role, class_id, school_id, email, must_change_password FROM students WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        } elseif ($_SESSION['user_role'] === 'parent') {
            $stmt = $db->prepare("SELECT id, username, name, 'parent' as role, school_id, email, must_change_password FROM parents WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $db->prepare("SELECT id, username, name, role, school_id, email, must_change_password FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $user = $stmt->fetch();
        if ($user) {
            // SaaS: Ensure school_id reflects the current active tenant during multi-school sessions
            if (Tenant::isSaasMode()) {
                $user['school_id'] = Tenant::id();
            }
            // Include school info if available
            if (!empty($user['school_id']) && Tenant::isSaasMode()) {
                $school = Tenant::school();
                if ($school) {
                    if ($school['active'] == 0) {
                        session_destroy();
                        jsonResponse(['success' => false, 'error' => 'هذا الحساب معطل حالياً، يرجى مراجعة مدير المنصة'], 403);
                        return;
                    }
                    $user['school_name'] = $school['name'];
                    $user['school_slug'] = $school['slug'];
                    $user['school_logo'] = $school['logo_url'];
                    // Ensure ID is consistent
                    $user['school_id'] = (int)$school['id'];
                }
                // Include subscription info
                $user['subscription'] = Subscription::getInfo($user['school_id']);
            }
            
            // Flag if the super admin is impersonating this account
            if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true) {
                $user['is_impersonating'] = true;
            }

            // Multi-School: Include schools list if requested
            if (isset($_GET['include_schools']) && $_SESSION['user_role'] !== 'student' && $_SESSION['user_role'] !== 'parent') {
                $stmtSchools = $db->prepare("
                    SELECT s.id, s.name, s.slug, usa.role, usa.is_primary
                    FROM user_school_access usa
                    JOIN schools s ON s.id = usa.school_id
                    WHERE usa.user_id = ? AND s.active = 1
                    UNION
                    SELECT s.id, s.name, s.slug, u.role, 1 as is_primary
                    FROM users u
                    JOIN schools s ON s.id = u.school_id
                    WHERE u.id = ? AND s.active = 1
                ");
                $stmtSchools->execute([$user['id'], $user['id']]);
                $user['schools'] = $stmtSchools->fetchAll();
            }
            
            jsonSuccess($user);
            return;
        }
    }
    jsonResponse(['success' => false, 'error' => 'غير مسجل'], 401);
}

function login() {
    checkCSRF();
    $data = getPostData();
    $username = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $schoolSlug = sanitize($data['school'] ?? ''); // Optional: school selector

    if (empty($username) || empty($password)) jsonError('الرجاء إدخال اسم المستخدم وكلمة المرور');

    $db = getDB();

    // --- IDENTIFICATION ---
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    $userCandidate = null;

    if ($isEmail) {
        $stmt = $db->prepare("SELECT id, username, password, name, role, school_id, email, must_change_password FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$username]);
        $userCandidate = $stmt->fetch();
        
        if ($userCandidate && password_verify($password, $userCandidate['password'])) {
            // Found a staff member with this email
            // Get all schools they have access to (Primary + Links)
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.slug, usa.role
                FROM user_school_access usa
                JOIN schools s ON s.id = usa.school_id
                WHERE usa.user_id = ? AND s.active = 1
                UNION
                SELECT s.id, s.name, s.slug, u.role
                FROM users u
                JOIN schools s ON s.id = u.school_id
                WHERE u.id = ? AND s.active = 1
            ");
            $stmt->execute([$userCandidate['id'], $userCandidate['id']]);
            $schools = $stmt->fetchAll();

            // Handle Multi-School Picker if needed
            if (count($schools) > 1 && empty($schoolSlug)) {
                jsonSuccess([
                    'requires_school_selection' => true,
                    'schools' => $schools,
                    'user_id' => $userCandidate['id']
                ], 'يرجى اختيار المدرسة');
            }

            // If a specific school was provided, verify access
            if (!empty($schoolSlug)) {
                $targetSchool = null;
                foreach ($schools as $s) {
                    if ($s['slug'] === $schoolSlug) {
                        $targetSchool = $s;
                        break;
                    }
                }
                if (!$targetSchool) jsonError('ليس لديك صلاحية للدخول لهذه المدرسة');
                
                $userCandidate['school_id'] = $targetSchool['id'];
                $userCandidate['role'] = $targetSchool['role']; // Use role specific to this school
            } elseif (count($schools) === 1) {
                // Only one school, auto-select it
                $userCandidate['school_id'] = $schools[0]['id'];
                $userCandidate['role'] = $schools[0]['role'];
            }
            
            // Log in as staff
            $user = $userCandidate;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'] ?? null;
            if ($user['school_id']) {
                Tenant::setId((int)$user['school_id']);
            }

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
            logActivity('login', 'user', $user['id']);
            
            unset($user['password']);
            if ($user['school_id']) {
                $school = Tenant::school();
                if ($school) $user['school_slug'] = $school['slug'];
            }
            
            jsonSuccess($user, 'تم تسجيل الدخول بنجاح');
            return;
        }

        // Check parents table for email
        $stmt = $db->prepare("SELECT id, username, password, name, school_id, email, must_change_password FROM parents WHERE email = ? AND active = 1");
        $stmt->execute([$username]);
        $parent = $stmt->fetch();
        if ($parent && password_verify($password, $parent['password'])) {
            $_SESSION['user_id'] = $parent['id'];
            $_SESSION['user_role'] = 'parent';
            $_SESSION['user_name'] = $parent['name'];
            if ($parent['school_id']) Tenant::setId((int)$parent['school_id']);

            $db->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?")->execute([$parent['id']]);
            $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
            logActivity('parent_login', 'parent', $parent['id']);
            
            unset($parent['password']);
            jsonSuccess($parent, 'تم تسجيل الدخول بنجاح');
            return;
        }
    }

    // --- FALLBACK TO TRADITIONAL USERNAME LOGIN ---
    // If school slug provided, resolve it first
    $schoolFilter = '';
    $schoolIdResolved = Tenant::id(); // From subdomain or session

    if (!empty($schoolSlug) && Tenant::isSaasMode()) {
        $stmt = $db->prepare("SELECT id, active FROM schools WHERE slug = ?");
        $stmt->execute([$schoolSlug]);
        $school = $stmt->fetch();
        if ($school) {
            if ($school['active'] == 0) jsonError('هذه المدرسة معطلة حالياً، يرجى مراجعة مدير المنصة');
            $schoolIdResolved = (int)$school['id'];
        } else {
            jsonError('المدرسة غير موجودة');
        }
    }

    // Build school filter for queries
    if ($schoolIdResolved && Tenant::isSaasMode()) {
        $schoolFilter = " AND school_id = $schoolIdResolved";
    }

    // --- LOGIN RATE LIMITING CHECK ---
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([$username, LOGIN_LOCKOUT_TIME]);
    if ((int)$stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
        logActivity('login_blocked', 'auth', null, "IP: $ip, User: $username");
        jsonError('تم حظر هذه المحاولة مؤقتاً لتكرار الفشل. يرجى الانتظار ١٥ دقيقة.');
    }

    // 1. Try Staff (users table)
    // Find the user record, honoring the specific school context if provided.
    // This prevents username collisions across different schools.
    if ($schoolIdResolved) {
        $stmt = $db->prepare("
            SELECT id, username, password, name, role, school_id, email, must_change_password 
            FROM users 
            WHERE username = ? AND active = 1 
            AND (school_id = ? OR id IN (SELECT user_id FROM user_school_access WHERE school_id = ?))
            LIMIT 1
        ");
        $stmt->execute([$username, $schoolIdResolved, $schoolIdResolved]);
    } else {
        $stmt = $db->prepare("SELECT id, username, password, name, role, school_id, email, must_change_password FROM users WHERE username = ? AND active = 1 LIMIT 1");
        $stmt->execute([$username]);
    }
    
    $userCandidate = $stmt->fetch();

    if ($userCandidate && password_verify($password, $userCandidate['password'])) {
        // Staff found. Now resolve which school they're logging into.
        $stmt = $db->prepare("
            SELECT s.id, s.name, s.slug, usa.role
            FROM user_school_access usa
            JOIN schools s ON s.id = usa.school_id
            WHERE usa.user_id = ? AND s.active = 1
            UNION
            SELECT s.id, s.name, s.slug, u.role
            FROM users u
            JOIN schools s ON s.id = u.school_id
            WHERE u.id = ? AND s.active = 1
        ");
        $stmt->execute([$userCandidate['id'], $userCandidate['id']]);
        $schools = $stmt->fetchAll();

        // If contextually constrained to a school (via subdomain or slug)
        if ($schoolIdResolved && $userCandidate['role'] !== 'super_admin') {
            $hasAccess = false;
            foreach ($schools as $s) {
                if ($s['id'] == $schoolIdResolved) {
                    $hasAccess = true;
                    $userCandidate['school_id'] = $s['id'];
                    $userCandidate['role'] = $s['role'];
                    break;
                }
            }
            if (!$hasAccess && $userCandidate['school_id'] != $schoolIdResolved) {
                jsonError('ليس لديك صلاحية للدخول لهذه المدرسة');
            }
        }

        // Multi-School: If at main domain and multiple schools found, trigger selection
        if (empty($schoolSlug) && !$schoolIdResolved && count($schools) > 1 && $userCandidate['role'] !== 'super_admin') {
            jsonSuccess([
                'requires_school_selection' => true,
                'schools' => $schools,
                'user_id' => $userCandidate['id']
            ], 'يرجى اختيار المدرسة');
        }

        // Auto-select if only one school
        if (empty($schoolSlug) && !$schoolIdResolved && count($schools) === 1) {
            $userCandidate['school_id'] = $schools[0]['id'];
            $userCandidate['role'] = $schools[0]['role'];
        }

        // Finalize Login
        $_SESSION['user_id'] = $userCandidate['id'];
        $_SESSION['user_role'] = $userCandidate['role'];
        $_SESSION['user_name'] = $userCandidate['name'];
        $_SESSION['user_email'] = $userCandidate['email'] ?? null;
        if ($userCandidate['school_id']) Tenant::setId((int)$userCandidate['school_id']);

        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userCandidate['id']]);
        $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
        logActivity('login', 'user', $userCandidate['id']);
        
        $userCandidate['must_change_password'] = (int)($userCandidate['must_change_password'] ?? 0);
        unset($userCandidate['password']);
        
        if ($userCandidate['school_id']) {
            $school = Tenant::school();
            if ($school) $userCandidate['school_slug'] = $school['slug'];
        }
        
        jsonSuccess($userCandidate, 'تم تسجيل الدخول بنجاح');
        return;
    }

    // 2. Try Student (students table)
    $stmt = $db->prepare("SELECT id, student_number, password, name, class_id, school_id, must_change_password FROM students WHERE student_number = ? AND active = 1" . $schoolFilter);
    $stmt->execute([$username]);
    $student = $stmt->fetch();

    if ($student && !empty($student['password']) && password_verify($password, $student['password'])) {
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['user_role'] = 'student';
        $_SESSION['user_name'] = $student['name'];
        $_SESSION['class_id'] = $student['class_id'];
        if ($student['school_id']) {
            Tenant::setId((int)$student['school_id']);
        }

        $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?")->execute([$student['id']]);
        $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
        logActivity('student_login', 'student', $student['id']);
        
        $response = [
            'id' => $student['id'],
            'username' => $student['student_number'],
            'name' => $student['name'],
            'role' => 'student',
            'class_id' => $student['class_id'],
            'school_id' => $student['school_id'],
            'must_change_password' => (int)($student['must_change_password'] ?? 0),
            'weak_password' => ($password === $student['student_number'])
        ];
        
        // Include school slug for redirection
        if ($student['school_id']) {
            $school = Tenant::school();
            if ($school) $response['school_slug'] = $school['slug'];
        }
        
        jsonSuccess($response, 'مرحباً بك في بوابة الطالب');
        return;
    }

    // 3. Try Parent (parents table)
    $stmt = $db->prepare("SELECT id, username, password, name, school_id FROM parents WHERE username = ? AND active = 1" . $schoolFilter);
    $stmt->execute([$username]);
    $parent = $stmt->fetch();

    if ($parent && password_verify($password, $parent['password'])) {
        $_SESSION['user_id'] = $parent['id'];
        $_SESSION['user_role'] = 'parent';
        $_SESSION['user_name'] = $parent['name'];
        if ($parent['school_id']) {
            Tenant::setId((int)$parent['school_id']);
        }

        $db->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?")->execute([$parent['id']]);
        $db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([$username]);
        logActivity('parent_login', 'parent', $parent['id']);
        
        $parent['must_change_password'] = (int)($parent['must_change_password'] ?? 0);
        unset($parent['password']);
        $parent['role'] = 'parent';
        
        // Include school slug for redirection
        if ($parent['school_id']) {
            $school = Tenant::school();
            if ($school) $parent['school_slug'] = $school['slug'];
        }
        
        jsonSuccess($parent, 'مرحباً بك في بوابة ولي الأمر');
        return;
    }

    // Fallback: Failed for all
    $db->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)")->execute([$ip, $username]);
    logActivity('login_failed', 'auth', null, "Input: $username");
    jsonError('بيانات الدخول غير صحيحة');
}

/**
 * Student Login
 */
function studentLogin() {
    checkCSRF(); // Fix: Added CSRF protection (was missing)
    $data = getPostData();
    $student_number = sanitize($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($student_number) || empty($password)) {
        jsonError('الرجاء إدخال رقم الطالب وكلمة المرور');
    }

    $db = getDB();

    $schoolFilter = '';
    $schoolIdResolved = Tenant::id();
    if ($schoolIdResolved && Tenant::isSaasMode()) {
        $schoolFilter = " AND school_id = $schoolIdResolved";
    }

    $stmt = $db->prepare("SELECT id, student_number, password, name, class_id, school_id FROM students WHERE student_number = ? AND active = 1" . $schoolFilter);
    $stmt->execute([$student_number]);
    $student = $stmt->fetch();

    if (!$student || empty($student['password']) || !password_verify($password, $student['password'])) {
        logActivity('student_login_failed', 'student', null, "Number: $student_number");
        jsonError('بيانات الدخول غير صحيحة');
    }

    $_SESSION['user_id'] = $student['id'];
    $_SESSION['user_role'] = 'student';
    $_SESSION['user_name'] = $student['name'];
    $_SESSION['class_id'] = $student['class_id'];
    if ($student['school_id']) {
        Tenant::setId((int)$student['school_id']);
    }

    $db->prepare("UPDATE students SET last_login = NOW() WHERE id = ?")->execute([$student['id']]);
    logActivity('student_login', 'student', $student['id']);
    
    unset($student['password']);
    jsonSuccess($student, 'مرحباً بك في بوابة الطالب');
}

function logout() {
    logActivity('logout', 'user', $_SESSION['user_id'] ?? null);
    
    // Thoroughly clear session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    jsonSuccess(null, 'تم تسجيل الخروج بنجاح');
}

/**
 * Exit Impersonation Mode
 * Used by Platform Admin to return to the admin dashboard
 */
function exitImpersonation() {
    // Only allow if actually impersonating
    if (!isset($_SESSION['is_impersonating']) || $_SESSION['is_impersonating'] !== true) {
        jsonError('أنت لست في وضع الإشراف');
    }
    
    // Clear school-level session data
    unset($_SESSION['user_id']);
    unset($_SESSION['user_role']);
    unset($_SESSION['user_name']);
    unset($_SESSION['class_id']);
    unset($_SESSION['school_id']);
    unset($_SESSION['is_impersonating']);
    
    // Reset Tenant context
    Tenant::reset();
    
    jsonSuccess(null, 'تم إنهاء وضع الإشراف');
}

/**
 * Get list of active schools (for school selector on login page)
 */
function getSchoolsList() {
    if (!Tenant::isSaasMode()) {
        jsonSuccess([]);
        return;
    }
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, name, slug, logo_url, city FROM schools WHERE active = 1 ORDER BY name");
        jsonSuccess($stmt->fetchAll());
    } catch (Exception $e) {
        jsonSuccess([]);
    }
}

/**
 * Send OTP Email Helper
 * يدعم SMTP عبر PHPMailer إذا كان مثبتاً، وإلا يستخدم mail() المحلية
 */
function sendOTP($email, $otp, $name) {
    if (empty($email)) return false;

    $subject = "رمز استعادة كلمة المرور - " . APP_NAME;

    // HTML Email Template
    $htmlMessage = "
    <!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;direction:rtl;'>
      <table width='100%' cellpadding='0' cellspacing='0' style='padding:20px 0;'>
        <tr><td align='center'>
          <table width='480' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
            <!-- Header -->
            <tr>
              <td style='background:linear-gradient(135deg,#059669,#10b981);padding:32px 24px;text-align:center;'>
                <h1 style='color:#fff;margin:0;font-size:22px;font-weight:bold;'>🔐 " . APP_NAME . "</h1>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:14px;'>استعادة كلمة المرور</p>
              </td>
            </tr>
            <!-- Body -->
            <tr>
              <td style='padding:32px 28px;'>
                <p style='color:#374151;font-size:16px;margin:0 0 8px;'>مرحباً <strong>" . htmlspecialchars($name) . "</strong>،</p>
                <p style='color:#6b7280;font-size:14px;margin:0 0 24px;'>تلقّينا طلباً لاستعادة كلمة المرور. رمز التحقق الخاص بك هو:</p>
                <!-- OTP Box -->
                <div style='background:#f0fdf4;border:2px dashed #10b981;border-radius:10px;padding:20px;text-align:center;margin:0 0 24px;'>
                  <span style='font-size:38px;font-weight:900;letter-spacing:12px;color:#065f46;font-family:monospace;'>" . $otp . "</span>
                </div>
                <p style='color:#6b7280;font-size:13px;margin:0 0 6px;'>⏱️ هذا الرمز صالح لمدة <strong>15 دقيقة</strong> فقط.</p>
                <p style='color:#9ca3af;font-size:12px;margin:0;'>إذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذه الرسالة.</p>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td style='background:#f9fafb;padding:16px 28px;text-align:center;border-top:1px solid #e5e7eb;'>
                <p style='color:#9ca3af;font-size:12px;margin:0;'>" . APP_NAME . " &mdash; نظام إدارة التربية البدنية</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>";

    $subject = 'رمز استعادة كلمة المرور';
    $message = "مرحباً $name،<br><br>رمز استعادة كلمة المرور الخاص بك هو: <b>$otp</b><br><br>هذا الرمز صالح لمدة 15 دقيقة فقط.<br><br>إذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذه الرسالة.";
    
    return sendEmail($email, $subject, $message, $name);
}


/**
 * Handle Forgot Password Request (Generate & Send OTP)
 */
function forgotPassword() {
    checkCSRF();
    $data = getPostData();
    $email = sanitize($data['email'] ?? '');
    
    if (empty($email)) jsonError('الرجاء إدخال البريد الإلكتروني');

    $db = getDB();
    $userType = null;
    $userId = null;
    $userName = '';
    $schoolId = null;

    // 1. Check users table
    $stmt = $db->prepare("SELECT id, name, school_id FROM users WHERE email = ? AND active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $userType = 'user';
        $userId = $user['id'];
        $userName = $user['name'];
        $schoolId = $user['school_id'];
    }

    // 2. Check parents table
    if (!$userType) {
        $stmt = $db->prepare("SELECT id, name, school_id FROM parents WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $parent = $stmt->fetch();
        if ($parent) {
            $userType = 'parent';
            $userId = $parent['id'];
            $userName = $parent['name'];
            $schoolId = $parent['school_id'];
        }
    }

    // 3. Check platform_admins table
    if (!$userType) {
        try {
            $stmt = $db->prepare("SELECT id, name FROM platform_admins WHERE email = ? AND active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            if ($admin) {
                $userType = 'platform_admin';
                $userId = $admin['id'];
                $userName = $admin['name'];
                $schoolId = null;
            }
        } catch (Exception $e) {}
    }

    if (!$userType) {
        // Return success anyway to prevent email enumeration
        jsonSuccess(null, 'إذا كان البريد الإلكتروني مسجلاً لدينا، فستصلك رسالة تحتوي على رمز الاستعادة');
        return;
    }

    // Fix: OTP Rate Limiting — max 3 requests per email per 10 minutes
    $recentCount = $db->prepare("SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $recentCount->execute([$email]);
    if ((int)$recentCount->fetchColumn() >= 3) {
        jsonError('تجاوزت عدد المحاولات. يرجى الانتظار 10 دقائق ثم إعادة المحاولة.');
    }

    // Generate OTP & Save
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Invalidate old OTPs for this email
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

    $db->prepare("INSERT INTO password_resets (school_id, email, user_type, user_id, otp, expires_at) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([$schoolId, $email, $userType, $userId, $otp, $expiresAt]);

    // Send Email
    sendOTP($email, $otp, $userName);

    jsonSuccess(null, 'أرسلنا رمز الاستعادة إلى بريدك الإلكتروني (صالح لمدة 15 دقيقة)');
}

/**
 * Handle Password Reset via OTP
 */
function resetPassword() {
    checkCSRF();
    $data = getPostData();
    $email = sanitize($data['email'] ?? '');
    $otp = sanitize($data['otp'] ?? '');
    $newPassword = $data['new_password'] ?? '';

    if (empty($email) || empty($otp) || empty($newPassword)) {
        jsonError('جميع الحقول مطلوبة');
    }

    if (strlen($newPassword) < 6) {
        jsonError('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id, user_type, user_id FROM password_resets WHERE email = ? AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, $otp]);
    $reset = $stmt->fetch();

    if (!$reset) {
        jsonError('رمز الاستعادة غير صحيح أو منتهي الصلاحية');
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $userType = $reset['user_type'];
    $userId = $reset['user_id'];

    if ($userType === 'user') {
        $db->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $userId]);
    } elseif ($userType === 'parent') {
        $db->prepare("UPDATE parents SET password = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $userId]);
    } elseif ($userType === 'platform_admin') {
        $db->prepare("UPDATE platform_admins SET password = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $userId]);
    } elseif ($userType === 'student') {
        $db->prepare("UPDATE students SET password = ?, must_change_password = 0 WHERE id = ?")->execute([$hash, $userId]);
    }

    // Delete used OTP
    $db->prepare("DELETE FROM password_resets WHERE id = ?")->execute([$reset['id']]);

    jsonSuccess(null, 'تم تغيير كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.');
}
