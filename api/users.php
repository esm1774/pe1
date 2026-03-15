<?php
/**
 * PE Smart School System - Users API
 * (Multi-Teacher Support: teacher class assignment management)
 */

function getUsers() {
    requireRole(['admin']);
    $sid = schoolId();
    // Fix #10: Use prepared statement instead of direct interpolation
    $sql = "SELECT id, username, name, email, role, active, last_login, created_at FROM users WHERE active = 1";
    $params = [];
    if ($sid) { 
        $sql .= " AND (school_id = ? OR id IN (SELECT user_id FROM user_school_access WHERE school_id = ?))"; 
        $params = [$sid, $sid]; 
    }
    $sql .= " ORDER BY id";
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function saveUser() {
    requireRole(['admin']);
    $data     = getPostData();
    validateRequired($data, ['name', 'username', 'role', 'email']);
    $db       = getDB();
    $id       = $data['id'] ?? null;
    $name     = sanitize($data['name']);
    $username = sanitize($data['username']);
    $email    = sanitize($data['email']);
    $role     = sanitize($data['role']);
    $password = $data['password'] ?? '';
    $sid      = schoolId();
    if (!in_array($role, ['admin', 'teacher', 'viewer', 'supervisor'])) jsonError('دور غير صالح');

    // Platform-wide Email Uniqueness check
    $emailStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $emailStmt->execute([$email]);
    $existingGlobalUser = $emailStmt->fetch();

    if (!$id && $existingGlobalUser) {
        // User already exists in the system (another school). 
        // We link them to this school instead of creating a duplicate user record.
        $db->prepare("INSERT IGNORE INTO user_school_access (user_id, school_id, role, is_primary) VALUES (?, ?, ?, 0)")
           ->execute([$existingGlobalUser['id'], $sid, $role]);
        logActivity('link_user', 'user', $existingGlobalUser['id'], "Linked to school: $sid");
        jsonSuccess(['id' => (int)$existingGlobalUser['id']], 'تم العثور على المستخدم وربطه بالمدرسة بنجاح');
        return;
    }

    if ($email && $existingGlobalUser && (int)$existingGlobalUser['id'] !== (int)$id) {
        if ($id) {
            // MERGE LOGIC: The admin is setting an email for an existing local account, 
            // but this email already belongs to a global account.
            // We migrate access to the global account and deactivate the local one.
            $globalId = (int)$existingGlobalUser['id'];
            $localId = (int)$id;

            // 1. Transfer School Access
            $db->prepare("INSERT IGNORE INTO user_school_access (user_id, school_id, role, is_primary) 
                          SELECT ?, school_id, role, 0 FROM user_school_access WHERE user_id = ?")
               ->execute([$globalId, $localId]);
            
            // Ensure the specific school being edited is also linked
            $db->prepare("INSERT IGNORE INTO user_school_access (user_id, school_id, role, is_primary) VALUES (?, ?, ?, 0)")
               ->execute([$globalId, $sid, $role]);

            // 2. Transfer Teacher Assignments
            $db->prepare("UPDATE teacher_assignments SET teacher_id = ? WHERE teacher_id = ?")->execute([$globalId, $localId]);

            // 3. Deactivate local record
            $db->prepare("UPDATE users SET active = 0, email = CONCAT(email, '_merged_', ?) WHERE id = ?")->execute([$localId, $localId]);
            
            logActivity('merge_user', 'user', $globalId, "Merged from local ID: $localId");
            jsonSuccess(['id' => $globalId], 'تم دمج هذا الحساب مع الحساب العالمي الموجود مسبقاً بهذا البريد الإلكتروني');
            return;
        } else {
            // New user but email exists -> Handled by the next block (Linking)
        }
    }

    // Duplicate check scoped to school for username
    $dupSql = "SELECT id FROM users WHERE username = ? AND id != ?";
    $dupParams = [$username, $id ?? 0];
    if ($sid) { $dupSql .= " AND school_id = ?"; $dupParams[] = $sid; }
    $stmt = $db->prepare($dupSql);
    $stmt->execute($dupParams);
    if ($stmt->fetch()) jsonError('اسم المستخدم مستخدم بالفعل في هذه المدرسة');

    if ($id) {
        // Security: Verify the user being edited belongs to the current school
        if ($sid) {
            $ownerCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND (school_id = ? OR id IN (SELECT user_id FROM user_school_access WHERE school_id = ?))");
            $ownerCheck->execute([$id, $sid, $sid]);
            if (!$ownerCheck->fetch()) jsonError('لا تملك صلاحية تعديل هذا المستخدم', 403);
        }
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $db->prepare("UPDATE users SET name=?, username=?, email=?, password=?, role=?, must_change_password = 0 WHERE id=?")->execute([$name, $username, $email, $hash, $role, $id]);
        } else {
            $db->prepare("UPDATE users SET name=?, username=?, email=?, role=? WHERE id=?")->execute([$name, $username, $email, $role, $id]);
        }
    } else {
        Subscription::requireLimit('teachers');
        if (empty($password)) jsonError('كلمة المرور مطلوبة');
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $db->prepare("INSERT INTO users (school_id, username, email, password, name, role, must_change_password) VALUES (?,?,?,?,?,?,1)")->execute([$sid, $username, $email, $hash, $name, $role]);
        $id = $db->lastInsertId();

        // Ensure primary school access record
        $db->prepare("INSERT IGNORE INTO user_school_access (user_id, school_id, role, is_primary) VALUES (?, ?, ?, 1)")
           ->execute([$id, $sid, $role]);
    }

    // SaaS: Sync identity changes across schools
    $syncData = ['name' => $name, 'email' => $email];
    if (!empty($password)) {
        $pwCheck = validatePasswordStrength($password);
        if ($pwCheck !== true) jsonError($pwCheck);
        $syncData['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    }
    syncGlobalUserIdentity($id, $syncData);

    logActivity($id ? 'update' : 'create', 'user', $id, $name);
    jsonSuccess(['id' => (int)$id], 'تم حفظ المستخدم');
}

function deleteUser() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    if ($id == 1) jsonError('لا يمكن حذف المدير الرئيسي');
    if ($id == $_SESSION['user_id']) jsonError('لا يمكنك حذف حسابك');
    $sid = schoolId();
    $sql = "UPDATE users SET active = 0 WHERE id = ?";
    $params = [$id];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    getDB()->prepare($sql)->execute($params);
    logActivity('delete', 'user', $id);
    jsonSuccess(null, 'تم حذف المستخدم');
}

// ============================================================
// TEACHER CLASS ASSIGNMENT (Admin Only)
// ============================================================

/**
 * Get all teachers with their assigned classes.
 * Returns: array of teachers, each with 'classes' array.
 */
function getTeacherAssignments() {
    requireRole(['admin']);
    $db = getDB();
    $sid = schoolId();

    // Fix: Scope teachers to current school to prevent cross-school data leakage
    $sql = "SELECT id, name, username, role FROM users WHERE role = 'teacher' AND active = 1";
    $params = [];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    $sql .= " ORDER BY name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll();

    foreach ($teachers as &$teacher) {
        $stmt = $db->prepare("
            SELECT c.id, CONCAT(g.name, ' - ', c.name) as full_name,
                   tc.is_temporary, tc.expires_at, tc.assigned_at,
                   u.name as assigned_by_name
            FROM teacher_classes tc
            JOIN classes c ON tc.class_id = c.id
            JOIN grades g ON c.grade_id = g.id
            LEFT JOIN users u ON tc.assigned_by = u.id
            WHERE tc.teacher_id = ? AND c.active = 1"
            . ($sid ? " AND c.school_id = ?" : "") .
            " ORDER BY g.sort_order, c.section
        ");
        $stmtParams = [$teacher['id']];
        if ($sid) $stmtParams[] = $sid;
        $stmt->execute($stmtParams);
        $teacher['classes'] = $stmt->fetchAll();
    }

    // Return only classes belonging to current school
    $sql2 = "SELECT c.id, CONCAT(g.name, ' - ', c.name) as full_name
             FROM classes c JOIN grades g ON c.grade_id = g.id
             WHERE c.active = 1";
    $params2 = [];
    if ($sid) { $sql2 .= " AND c.school_id = ?"; $params2[] = $sid; }
    $sql2 .= " ORDER BY g.sort_order, c.section";
    $stmtAll = $db->prepare($sql2);
    $stmtAll->execute($params2);
    $allClasses = $stmtAll->fetchAll();

    jsonSuccess(['teachers' => $teachers, 'all_classes' => $allClasses]);
}

/**
 * Assign a class to a teacher.
 * Supports permanent and temporary (with expiry) assignments.
 */
function assignTeacherClass() {
    requireRole(['admin']);
    $data        = getPostData();
    $teacherId   = (int)($data['teacher_id']   ?? 0);
    $classId     = (int)($data['class_id']     ?? 0);
    $isTemporary = !empty($data['is_temporary']) ? 1 : 0;
    $expiresAt   = !empty($data['expires_at']) ? sanitize($data['expires_at']) : null;

    if (!$teacherId || !$classId) jsonError('يجب تحديد المعلم والفصل');

    // Validate teacher exists and is a teacher
    $db   = getDB();
    $user = $db->prepare("SELECT id, role FROM users WHERE id = ? AND active = 1");
    $user->execute([$teacherId]);
    $u = $user->fetch();
    if (!$u || $u['role'] !== 'teacher') jsonError('المستخدم المحدد ليس معلماً');

    // Validate class exists
    $cls = $db->prepare("SELECT id FROM classes WHERE id = ? AND active = 1");
    $cls->execute([$classId]);
    if (!$cls->fetch()) jsonError('الفصل غير موجود');

    assignClassToTeacher($teacherId, $classId, (bool)$isTemporary, $expiresAt);

    $msg = $isTemporary ? 'تم التعيين المؤقت للفصل' : 'تم تعيين الفصل للمعلم بشكل دائم';
    if ($expiresAt) $msg .= " (ينتهي في $expiresAt)";

    logActivity('assign_class', 'teacher_classes', $teacherId, "Class: $classId, Temp: $isTemporary");
    jsonSuccess(null, $msg);
}

/**
 * Remove a teacher's access to a specific class.
 */
function unassignTeacherClass() {
    requireRole(['admin']);
    $data      = getPostData();
    $teacherId = (int)($data['teacher_id'] ?? 0);
    $classId   = (int)($data['class_id']   ?? 0);
    if (!$teacherId || !$classId) jsonError('يجب تحديد المعلم والفصل');

    unassignClassFromTeacher($teacherId, $classId);
    logActivity('unassign_class', 'teacher_classes', $teacherId, "Class: $classId");
    jsonSuccess(null, 'تم إلغاء تعيين الفصل من المعلم');
}

// ============================================================
// MY PROFILE / CV (Self-management)
// ============================================================

function getMyProfile() {
    requireLogin();
    $db = getDB();
    $role = $_SESSION['user_role'] ?? '';
    $uid = $_SESSION['user_id'] ?? 0;

    if ($role === 'parent') {
        $stmt = $db->prepare("SELECT id, username, name, photo_url, email, phone, NULL as specialization, NULL as education, NULL as experience_years, NULL as bio, NULL as birth_date, 'parent' as role, created_at FROM parents WHERE id = ?");
    } elseif ($role === 'student') {
        $stmt = $db->prepare("SELECT id, student_number as username, name, photo_url, email, phone, NULL as specialization, NULL as education, NULL as experience_years, NULL as bio, date_of_birth as birth_date, 'student' as role, created_at FROM students WHERE id = ?");
    } else {
        $stmt = $db->prepare("SELECT id, username, name, photo_url, email, phone, specialization, education, experience_years, bio, birth_date, role, created_at FROM users WHERE id = ?");
    }

    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u) jsonError('المستخدم غير موجود');
    
    // SaaS: Ensure school_id reflects the current active tenant during multi-school sessions
    if (Tenant::isSaasMode()) {
        $u['school_id'] = Tenant::id();
    }
    
    jsonSuccess($u);
}

function updateMyProfile() {
    requireLogin();
    $data = getPostData();
    
    if (empty($data['name']) && empty($data['password'])) {
        // We still allow updating phone/email if provided
    }

    $db = getDB();
    $role = $_SESSION['user_role'] ?? '';
    $uid = $_SESSION['user_id'] ?? 0;
    
    $fields = [];
    $params = [];

    // Common fields
    if (isset($data['name']) && !empty($data['name'])) {
        $fields[] = "name = ?";
        $params[] = sanitize($data['name']);
        $_SESSION['user_name'] = $data['name'];
    }
    if (isset($data['email'])) {
        $email = sanitize($data['email']);
        // Verify unique if staff
        if ($role !== 'parent' && $role !== 'student' && !empty($email)) {
            $dup = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dup->execute([$email, $uid]);
            if ($dup->fetch()) jsonError('البريد الإلكتروني مسجل لنظام مستخدم آخر');
        }
        $fields[] = "email = ?";
        $params[] = $email;
    }
    if (isset($data['phone'])) {
        $fields[] = "phone = ?";
        $params[] = sanitize($data['phone']);
    }
    if (!empty($data['password'])) {
        $pwCheck = validatePasswordStrength($data['password']);
        if ($pwCheck !== true) jsonError($pwCheck);
        $fields[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $fields[] = "must_change_password = 0";
    }

    // Role-specific fields
    if ($role === 'student') {
        if (isset($data['birth_date'])) {
            $fields[] = "date_of_birth = ?";
            $params[] = !empty($data['birth_date']) ? sanitize($data['birth_date']) : null;
        }
        $table = "students";
    } elseif ($role === 'parent') {
        $table = "parents";
    } else {
        // admin, teacher, supervisor
        if (isset($data['specialization'])) {
            $fields[] = "specialization = ?";
            $params[] = sanitize($data['specialization']);
        }
        if (isset($data['education'])) {
            $fields[] = "education = ?";
            $params[] = sanitize($data['education']);
        }
        if (isset($data['experience_years'])) {
            $fields[] = "experience_years = ?";
            $params[] = (int)$data['experience_years'];
        }
        if (isset($data['bio'])) {
            $fields[] = "bio = ?";
            $params[] = sanitize($data['bio']);
        }
        if (isset($data['birth_date'])) {
            $fields[] = "birth_date = ?";
            $params[] = !empty($data['birth_date']) ? sanitize($data['birth_date']) : null;
        }
        $table = "users";
    }

    if (empty($fields)) {
        jsonError('لا توجد بيانات للتحديث');
    }

    $sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE id = ?";
    $params[] = $uid;

    $db->prepare($sql)->execute($params);
    
    // SaaS: Sync identity changes across schools if applicable
    $syncData = [];
    if (isset($data['name'])) $syncData['name'] = $data['name'];
    if (isset($data['email'])) {
        $syncData['email'] = $data['email'];
        $_SESSION['user_email'] = $data['email']; // Update current session
    }
    if (!empty($data['password'])) {
        $syncData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (!empty($syncData)) {
        syncGlobalUserIdentity($uid, $syncData);
    }

    logActivity('update_profile', $role, $uid, !empty($data['password']) ? "Password updated" : "Info updated");
    jsonSuccess(null, 'تم تحديث البيانات بنجاح');
}

/**
 * Bulk: Replace all class assignments for a teacher at once.
 * The admin sends the full array of class IDs they want the teacher to have.
 */
function saveTeacherAssignments() {
    requireRole(['admin']);
    $data      = getPostData();
    $teacherId = (int)($data['teacher_id'] ?? 0);
    $classIds  = $data['class_ids'] ?? [];            // permanent assignments
    $tempAssignments = $data['temp_assignments'] ?? []; // [{class_id, expires_at}]

    if (!$teacherId) jsonError('يجب تحديد المعلم');

    $db = getDB();

    // Validate teacher
    $user = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND active = 1");
    $user->execute([$teacherId]);
    if (!$user->fetch()) jsonError('المعلم غير موجود');

    $db->beginTransaction();
    try {
        // Remove all existing assignments for this teacher
        $db->prepare("DELETE FROM teacher_classes WHERE teacher_id = ?")->execute([$teacherId]);

        // Add permanent assignments
        foreach ($classIds as $classId) {
            $classId = (int)$classId;
            if ($classId) assignClassToTeacher($teacherId, $classId, false, null);
        }
        // Add temporary assignments
        foreach ($tempAssignments as $ta) {
            $classId   = (int)($ta['class_id'] ?? 0);
            $expiresAt = !empty($ta['expires_at']) ? sanitize($ta['expires_at']) : null;
            if ($classId) assignClassToTeacher($teacherId, $classId, true, $expiresAt);
        }

        $db->commit();
        logActivity('save_assignments', 'teacher_classes', $teacherId, "Classes: " . implode(',', $classIds));
        jsonSuccess(null, 'تم حفظ تعيينات المعلم بنجاح');
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Upload Profile Photo
 */
function uploadProfilePhoto() {
    $user = requireLogin();
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonError('فشل رفع الملف أو لم يتم اختيار ملف.');
    }

    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowed)) {
        jsonError('نوع الملف غير مدعوم. يرجى اختيار صورة (JPEG, PNG, WEBP).');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        jsonError('حجم الصورة كبير جداً. الحد الأقصى 2 ميجابايت.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $prefix = ($user['role'] === 'student') ? 'std_' : (($user['role'] === 'parent') ? 'par_' : 'usr_');
    $filename = $prefix . $user['id'] . '_' . time() . '.' . $ext;
    
    $targetDir = __DIR__ . '/../uploads/profiles/';
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $db = getDB();
        $table = ($user['role'] === 'student') ? 'students' : (($user['role'] === 'parent') ? 'parents' : 'users');
        
        // Delete old photo if exists
        $stmt = $db->prepare("SELECT photo_url FROM `$table` WHERE id = ?");
        $stmt->execute([$user['id']]);
        $oldPhoto = $stmt->fetchColumn();
        if ($oldPhoto && file_exists(__DIR__ . '/../' . $oldPhoto)) {
            @unlink(__DIR__ . '/../' . $oldPhoto);
        }

        $photoUrl = 'uploads/profiles/' . $filename;
        $db->prepare("UPDATE `$table` SET photo_url = ? WHERE id = ?")->execute([$photoUrl, $user['id']]);

        logActivity('update', 'profile_photo', $user['id'], 'تم تحديث الصورة الشخصية');
        jsonSuccess(['photo_url' => $photoUrl], 'تم تحديث الصورة الشخصية بنجاح');
    } else {
        jsonError('حدث خطأ أثناء حفظ الملف على الخادم.');
    }
}

/**
 * Switch current school context for a user with multiple schools
 */
function switchSchool() {
    requireLogin();
    $data = getPostData();
    $targetSchoolId = (int)($data['school_id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$targetSchoolId) jsonError('يجب تحديد المدرسة');

    $db = getDB();
    
    // Verify user has access to this school (check both primary and secondary schools)
    $stmt = $db->prepare("
        SELECT usa.role, s.slug
        FROM user_school_access usa
        JOIN schools s ON s.id = usa.school_id
        WHERE usa.user_id = ? AND usa.school_id = ? AND s.active = 1
        UNION
        SELECT u.role, s.slug
        FROM users u
        JOIN schools s ON s.id = u.school_id
        WHERE u.id = ? AND u.school_id = ? AND s.active = 1
    ");
    $stmt->execute([$userId, $targetSchoolId, $userId, $targetSchoolId]);
    $access = $stmt->fetch();

    if (!$access) {
        jsonError('ليس لديك صلاحية للدخول لهذه المدرسة');
    }

    // Update session
    $_SESSION['school_id'] = $targetSchoolId;
    $_SESSION['user_role'] = $access['role'];
    Tenant::setId($targetSchoolId);

    logActivity('switch_school', 'auth', $userId, "Switched to: " . $access['slug']);
    
    jsonSuccess([
        'school_id' => $targetSchoolId,
        'school_slug' => $access['slug'],
        'role' => $access['role']
    ], 'تم تبديل المدرسة بنجاح');
}
/**
 * SaaS: Synchronize a user's global identity (name, email, password) across all their records in all schools.
 */
function syncGlobalUserIdentity($userId, $data) {
    $db = getDB();
    // 1. Fetch current record to get identification markers
    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $current = $stmt->fetch();
    if (!$current) return;

    $username = $current['username'];
    $email = $current['email'];

    // 2. Build the cross-school update
    $fields = [];
    $params = [];
    if (isset($data['name'])) { $fields[] = "name = ?"; $params[] = $data['name']; }
    if (isset($data['email'])) { $fields[] = "email = ?"; $params[] = $data['email']; }
    if (isset($data['password_hash'])) { $fields[] = "password = ?"; $params[] = $data['password_hash']; }

    if (empty($fields)) return;

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id != ?";
    $params[] = $userId;

    // 3. Identification: link by same email OR same username
    // (In SaaS mode, these represent the same physical person)
    $where = [];
    $whereParams = [];
    if (!empty($email)) {
        $where[] = "email = ?";
        $whereParams[] = $email;
    }
    if (!empty($username)) {
        $where[] = "username = ?";
        $whereParams[] = $username;
    }

    if (empty($where)) return;

    $sql .= " AND (" . implode(" OR ", $where) . ")";
    $db->prepare($sql)->execute(array_merge($params, $whereParams));
}
