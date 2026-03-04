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
            $stmt = $db->prepare("SELECT id, student_number, name, 'student' as role, class_id, school_id FROM students WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        } elseif ($_SESSION['user_role'] === 'parent') {
            $stmt = $db->prepare("SELECT id, username, name, 'parent' as role, school_id FROM parents WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $db->prepare("SELECT id, username, name, role, school_id FROM users WHERE id = ? AND active = 1");
            $stmt->execute([$_SESSION['user_id']]);
        }
        
        $user = $stmt->fetch();
        if ($user) {
            // Include school info if available
            if (!empty($user['school_id']) && Tenant::isSaasMode()) {
                $school = Tenant::school();
                if ($school) {
                    $user['school_name'] = $school['name'];
                    $user['school_slug'] = $school['slug'];
                    $user['school_logo'] = $school['logo_url'];
                }
                // Include subscription info
                $user['subscription'] = Subscription::getInfo($user['school_id']);
            }
            
            // Flag if the super admin is impersonating this account
            if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true) {
                $user['is_impersonating'] = true;
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

    // If school slug provided, resolve it first
    $schoolFilter = '';
    $schoolIdResolved = Tenant::id(); // From subdomain or session

    if (!empty($schoolSlug) && Tenant::isSaasMode()) {
        $stmt = $db->prepare("SELECT id FROM schools WHERE slug = ? AND active = 1");
        $stmt->execute([$schoolSlug]);
        $school = $stmt->fetch();
        if ($school) {
            $schoolIdResolved = (int)$school['id'];
        } else {
            jsonError('المدرسة غير موجودة');
        }
    }

    // Build school filter for queries
    if ($schoolIdResolved && Tenant::isSaasMode()) {
        $schoolFilter = " AND school_id = $schoolIdResolved";
    }

    // 1. Try Staff (users table)
    $stmt = $db->prepare("SELECT id, username, password, name, role, school_id FROM users WHERE username = ? AND active = 1" . $schoolFilter);
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        if ($user['school_id']) {
            Tenant::setId((int)$user['school_id']);
        }

        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        logActivity('login', 'user', $user['id']);
        unset($user['password']);
        
        // Include school slug for redirection
        if ($user['school_id']) {
            $school = Tenant::school();
            if ($school) $user['school_slug'] = $school['slug'];
        }
        
        jsonSuccess($user, 'تم تسجيل الدخول بنجاح');
        return;
    }

    // 2. Try Student (students table)
    $stmt = $db->prepare("SELECT id, student_number, password, name, class_id, school_id FROM students WHERE student_number = ? AND active = 1" . $schoolFilter);
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
        logActivity('student_login', 'student', $student['id']);
        
        $response = [
            'id' => $student['id'],
            'username' => $student['student_number'],
            'name' => $student['name'],
            'role' => 'student',
            'class_id' => $student['class_id'],
            'school_id' => $student['school_id'],
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
        logActivity('parent_login', 'parent', $parent['id']);
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
    session_destroy();
    jsonSuccess(null, 'تم تسجيل الخروج');
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

    $plainText = "مرحباً $name،\n\nرمز استعادة كلمة المرور الخاص بك هو: $otp\n\nهذا الرمز صالح لمدة 15 دقيقة فقط.\n\nإذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذه الرسالة.";

    // ── SMTP via PHPMailer (if enabled and library exists) ──────────────────
    if (MAIL_USE_SMTP && !empty(MAIL_USERNAME) && !empty(MAIL_PASSWORD)) {
        $mailerPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($mailerPath)) {
            require_once $mailerPath;
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->CharSet    = 'UTF-8';

                $fromEmail = !empty(MAIL_FROM_EMAIL) ? MAIL_FROM_EMAIL : MAIL_USERNAME;
                $mail->setFrom($fromEmail, MAIL_FROM_NAME);
                $mail->addAddress($email, $name);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlMessage;
                $mail->AltBody = $plainText;

                $mail->send();
                return true;
            } catch (\Exception $e) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log('[SMTP Error] ' . $e->getMessage());
                }
                // Fall through to mail() fallback
            }
        }
    }

    // ── Fallback: PHP mail() for local/dev environments ────────────────────
    $fromEmail = !empty(MAIL_FROM_EMAIL) ? MAIL_FROM_EMAIL : 'noreply@pesmart.local';
    $headers  = "From: " . MAIL_FROM_NAME . " <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlMessage, $headers);
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

    // Provide OTP in response ONLY in debug mode for testing (or keep it clean)
    $msg = 'أرسلنا رمز الاستعادة إلى بريدك الإلكتروني (صالح لمدة 15 دقيقة)';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $msg .= " - وضع التطوير، الرمز: $otp";
    }

    jsonSuccess(null, $msg);
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
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    } elseif ($userType === 'parent') {
        $db->prepare("UPDATE parents SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    } elseif ($userType === 'platform_admin') {
        $db->prepare("UPDATE platform_admins SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    }

    // Delete used OTP
    $db->prepare("DELETE FROM password_resets WHERE id = ?")->execute([$reset['id']]);

    jsonSuccess(null, 'تم تغيير كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.');
}
