<?php
/**
 * PE Smart School System - Users API
 * (Multi-Teacher Support: teacher class assignment management)
 */

function getUsers() {
    requireRole(['admin']);
    $sid = schoolId();
    // Fix #10: Use prepared statement instead of direct interpolation
    $sql = "SELECT id, username, name, role, active, last_login, created_at FROM users WHERE active = 1";
    $params = [];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    $sql .= " ORDER BY id";
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function saveUser() {
    requireRole(['admin']);
    $data     = getPostData();
    validateRequired($data, ['name', 'username', 'role']);
    $db       = getDB();
    $id       = $data['id'] ?? null;
    $name     = sanitize($data['name']);
    $username = sanitize($data['username']);
    $role     = sanitize($data['role']);
    $password = $data['password'] ?? '';
    $sid      = schoolId();
    if (!in_array($role, ['admin', 'teacher', 'viewer', 'supervisor'])) jsonError('دور غير صالح');

    // Fix #5: Allow each school admin to create admins within their own school ONLY
    if ($role === 'admin' && $sid) {
        // Platform admin (no school context) can create admins anywhere
        // School admin can only create admins for their own school
        if (isset($_SESSION['school_id']) && $_SESSION['school_id'] != $sid) {
            jsonError('لا تملك صلاحية إنشاء مستخدم بدور مدير في مدرسة أخرى');
        }
    }

    // Duplicate check scoped to school
    $dupSql = "SELECT id FROM users WHERE username = ? AND id != ?";
    $dupParams = [$username, $id ?? 0];
    if ($sid) { $dupSql .= " AND school_id = ?"; $dupParams[] = $sid; }
    $stmt = $db->prepare($dupSql);
    $stmt->execute($dupParams);
    if ($stmt->fetch()) jsonError('اسم المستخدم مستخدم بالفعل');

    if ($id) {
        // Security: Verify the user being edited belongs to the current school
        if ($sid) {
            $ownerCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND school_id = ?");
            $ownerCheck->execute([$id, $sid]);
            if (!$ownerCheck->fetch()) jsonError('لا تملك صلاحية تعديل هذا المستخدم', 403);
        }
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $db->prepare("UPDATE users SET name=?, username=?, password=?, role=? WHERE id=?")->execute([$name, $username, $hash, $role, $id]);
        } else {
            $db->prepare("UPDATE users SET name=?, username=?, role=? WHERE id=?")->execute([$name, $username, $role, $id]);
        }
    } else {
        Subscription::requireLimit('teachers');
        if (empty($password)) jsonError('كلمة المرور مطلوبة');
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $db->prepare("INSERT INTO users (school_id, username, password, name, role) VALUES (?,?,?,?,?)")->execute([$sid, $username, $hash, $name, $role]);
        $id = $db->lastInsertId();
    }
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
        $stmt = $db->prepare("SELECT id, username, name, email, phone, NULL as specialization, NULL as education, NULL as experience_years, NULL as bio, NULL as birth_date, 'parent' as role, created_at FROM parents WHERE id = ?");
    } elseif ($role === 'student') {
        $stmt = $db->prepare("SELECT id, student_number as username, name, NULL as email, NULL as phone, NULL as specialization, NULL as education, NULL as experience_years, NULL as bio, date_of_birth as birth_date, 'student' as role, created_at FROM students WHERE id = ?");
    } else {
        $stmt = $db->prepare("SELECT id, username, name, email, phone, specialization, education, experience_years, bio, birth_date, role, created_at FROM users WHERE id = ?");
    }

    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u) jsonError('المستخدم غير موجود');
    
    jsonSuccess($u);
}

function updateMyProfile() {
    requireLogin();
    $data = getPostData();
    validateRequired($data, ['name']); // Name is mandatory, others optional

    $db = getDB();
    $role = $_SESSION['user_role'] ?? '';
    $uid = $_SESSION['user_id'] ?? 0;

    if ($role === 'parent') {
        $sql = "UPDATE parents SET name = ?, email = ?, phone = ? WHERE id = ?";
        $params = [
            sanitize($data['name']),
            sanitize($data['email'] ?? null),
            sanitize($data['phone'] ?? null),
            $uid
        ];
    } elseif ($role === 'student') {
        $sql = "UPDATE students SET name = ?, date_of_birth = ? WHERE id = ?";
        $params = [
            sanitize($data['name']),
            !empty($data['birth_date']) ? sanitize($data['birth_date']) : null,
            $uid
        ];
    } else {
        $sql = "UPDATE users SET 
                name = ?, 
                email = ?, 
                phone = ?, 
                specialization = ?, 
                education = ?, 
                experience_years = ?, 
                bio = ?, 
                birth_date = ? 
                WHERE id = ?";
        $params = [
            sanitize($data['name']),
            sanitize($data['email'] ?? null),
            sanitize($data['phone'] ?? null),
            sanitize($data['specialization'] ?? null),
            sanitize($data['education'] ?? null),
            isset($data['experience_years']) ? (int)$data['experience_years'] : null,
            sanitize($data['bio'] ?? null),
            !empty($data['birth_date']) ? sanitize($data['birth_date']) : null,
            $uid
        ];
    }

    $db->prepare($sql)->execute($params);
    
    // Update name in session too
    $_SESSION['user_name'] = $data['name'];
    
    logActivity('update_profile', $role, $uid);
    jsonSuccess(null, 'تم تحديث ملفك الشخصي بنجاح');
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
