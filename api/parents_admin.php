<?php
/**
 * PE Smart School System - Parents Administration API
 */

/**
 * List all parents for the admin view
 */
function getParents() {
    requireRole(['admin']);
    $db = getDB();
    $sid = schoolId();
    // Fix: Use prepared statement instead of string interpolation
    $sql = "SELECT id, username, name, email, phone, active, last_login, created_at FROM parents WHERE 1=1";
    $params = [];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $parents = $stmt->fetchAll();
    
    // For each parent, get count of linked students
    foreach ($parents as &$p) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM parent_students WHERE parent_id = ?");
        $stmt->execute([$p['id']]);
        $p['students_count'] = $stmt->fetchColumn();
    }
    
    jsonSuccess($parents);
}

/**
 * Save (Create/Update) parent account
 */
function saveParent() {
    requireRole(['admin']);
    $data = getPostData();
    validateRequired($data, ['name', 'username']);

    $db = getDB();
    $id = $data['id'] ?? null;
    $name = sanitize($data['name']);
    $username = sanitize($data['username']);
    $password = $data['password'] ?? '';
    $email = sanitize($data['email'] ?? null);
    $phone = sanitize($data['phone'] ?? null);
    $sid = schoolId();

    // Check unique username scoped to school
    $dupSql = "SELECT id FROM parents WHERE username = ? AND id != ?";
    $dupParams = [$username, $id ?? 0];
    if ($sid) { $dupSql .= " AND school_id = ?"; $dupParams[] = $sid; }
    $stmt = $db->prepare($dupSql);
    $stmt->execute($dupParams);
    if ($stmt->fetch()) jsonError('اسم المستخدم مستخدم بالفعل');

    if ($id) {
        // Update
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $stmt = $db->prepare("UPDATE parents SET name=?, username=?, password=?, email=?, phone=?, must_change_password = 0 WHERE id=?");
            $stmt->execute([$name, $username, $hash, $email, $phone, $id]);
        } else {
            $stmt = $db->prepare("UPDATE parents SET name=?, username=?, email=?, phone=? WHERE id=?");
            $stmt->execute([$name, $username, $email, $phone, $id]);
        }
        logActivity('update_parent', 'parents', $id, $name);
    } else {
        // Create
        if (empty($password)) jsonError('كلمة المرور مطلوبة للمستخدم الجديد');
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $stmt = $db->prepare("INSERT INTO parents (school_id, name, username, password, email, phone, must_change_password) VALUES (?,?,?,?,?,?,1)");
        $stmt->execute([$sid, $name, $username, $hash, $email, $phone]);
        $id = $db->lastInsertId();
        logActivity('create_parent', 'parents', $id, $name);
    }

    jsonSuccess(['id' => (int)$id], 'تم حفظ بيانات ولي الأمر بنجاح');
}

/**
 * Delete (deactivate) parent account
 */
function deleteParent() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    $sid = schoolId();
    $sql = "UPDATE parents SET active = 0 WHERE id = ?";
    $params = [$id];
    if ($sid) { $sql .= " AND school_id = ?"; $params[] = $sid; }
    $db->prepare($sql)->execute($params);
    logActivity('delete_parent', 'parents', $id);
    jsonSuccess(null, 'تم تعطيل حساب ولي الأمر');
}

/**
 * Get students linked to a specific parent
 */
function getParentLinkedStudents() {
    requireRole(['admin']);
    $parentId = getParam('parent_id');
    if (!$parentId) jsonError('معرّف ولي الأمر مطلوب');

    $db = getDB();
    $sid = schoolId();

    // Fix: Ensure parent belongs to current school before returning their linked students
    if ($sid) {
        $check = $db->prepare("SELECT id FROM parents WHERE id = ? AND school_id = ?");
        $check->execute([$parentId, $sid]);
        if (!$check->fetch()) jsonError('ولي الأمر غير موجود أو ليس ضمن صلاحياتك', 403);
    }

    $stmt = $db->prepare("
        SELECT s.id, s.name, s.student_number, CONCAT(g.name, ' - ', c.name) as class_name
        FROM parent_students ps
        JOIN students s ON ps.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE ps.parent_id = ?
    ");
    $stmt->execute([$parentId]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * Search students for linking
 */
function searchStudentsForLinking() {
    requireRole(['admin']);
    $query = getParam('q');
    if (strlen($query) < 2) jsonSuccess([]);

    $db = getDB();
    $sid = schoolId();
    $sql = "
        SELECT s.id, s.name, s.student_number, CONCAT(g.name, ' - ', c.name) as class_name
        FROM students s
        JOIN classes c ON s.class_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE (s.name LIKE ? OR s.student_number LIKE ?) AND s.active = 1";
    $searchTerm = "%$query%";
    $sqlParams = [$searchTerm, $searchTerm];
    // Fix: Use prepared statement instead of string interpolation
    if ($sid) { $sql .= " AND s.school_id = ?"; $sqlParams[] = $sid; }
    $sql .= " LIMIT 10";
    $stmt = $db->prepare($sql);
    $stmt->execute($sqlParams);
    jsonSuccess($stmt->fetchAll());
}

/**
 * Link parent to student
 */
function linkParentStudent() {
    requireRole(['admin']);
    $data = getPostData();
    $parentId = $data['parent_id'] ?? null;
    $studentId = $data['student_id'] ?? null;

    if (!$parentId || !$studentId) jsonError('البيانات غير مكتملة');

    $db = getDB();
    $sid = schoolId();

    // Fix: Verify parent and student both belong to the current school
    if ($sid) {
        $parentCheck = $db->prepare("SELECT id FROM parents WHERE id = ? AND school_id = ?");
        $parentCheck->execute([$parentId, $sid]);
        if (!$parentCheck->fetch()) jsonError('ولي الأمر غير موجود أو ليس ضمن مدرستك', 403);

        $studentCheck = $db->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
        $studentCheck->execute([$studentId, $sid]);
        if (!$studentCheck->fetch()) jsonError('الطالب غير موجود أو ليس ضمن مدرستك', 403);
    }

    try {
        $stmt = $db->prepare("INSERT IGNORE INTO parent_students (parent_id, student_id) VALUES (?, ?)");
        $stmt->execute([$parentId, $studentId]);
        logActivity('link_parent_student', 'parent_students', $parentId, "Student: $studentId");
        jsonSuccess(null, 'تم الربط بنجاح');
    } catch (Exception $e) {
        jsonError('فشل الربط: ' . $e->getMessage());
    }
}

/**
 * Unlink parent from student
 */
function unlinkParentStudent() {
    requireRole(['admin']);
    $data = getPostData();
    $parentId = $data['parent_id'] ?? null;
    $studentId = $data['student_id'] ?? null;

    if (!$parentId || !$studentId) jsonError('البيانات غير مكتملة');

    $db = getDB();
    $stmt = $db->prepare("DELETE FROM parent_students WHERE parent_id = ? AND student_id = ?");
    $stmt->execute([$parentId, $studentId]);
    logActivity('unlink_parent_student', 'parent_students', $parentId, "Student: $studentId");
    jsonSuccess(null, 'تم فك الربط بنجاح');
}
