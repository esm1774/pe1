<?php
/**
 * PE Smart School System - Grades & Classes API
 * (Multi-Teacher Support)
 */

// ============================================================
// GRADES
// ============================================================
function getGrades() {
    requireLogin();
    $db = getDB();
    $teacherClassIds = getTeacherClassIds(); // null = admin

    if ($teacherClassIds === null) {
        // Admin: sees all grades with all classes
        $stmt = $db->query("
            SELECT g.*, COUNT(DISTINCT c.id) as class_count, COUNT(DISTINCT s.id) as student_count
            FROM grades g
            LEFT JOIN classes c ON c.grade_id = g.id AND c.active = 1
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            WHERE g.active = 1 GROUP BY g.id ORDER BY g.sort_order, g.id
        ");
        $grades = $stmt->fetchAll();
        foreach ($grades as &$grade) {
            $stmt2 = $db->prepare("
                SELECT c.*, COUNT(s.id) as student_count FROM classes c
                LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
                WHERE c.grade_id = ? AND c.active = 1 GROUP BY c.id ORDER BY c.section
            ");
            $stmt2->execute([$grade['id']]);
            $grade['classes'] = $stmt2->fetchAll();
        }
    } else {
        // Teacher: only grades that contain their assigned classes
        if (empty($teacherClassIds)) {
            jsonSuccess([]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT g.*, COUNT(DISTINCT c.id) as class_count, COUNT(DISTINCT s.id) as student_count
            FROM grades g
            JOIN classes c ON c.grade_id = g.id AND c.active = 1 AND c.id IN ($placeholders)
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            WHERE g.active = 1 GROUP BY g.id ORDER BY g.sort_order, g.id
        ");
        $stmt->execute($teacherClassIds);
        $grades = $stmt->fetchAll();
        foreach ($grades as &$grade) {
            $stmt2 = $db->prepare("
                SELECT c.*, COUNT(s.id) as student_count FROM classes c
                LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
                WHERE c.grade_id = ? AND c.active = 1 AND c.id IN ($placeholders)
                GROUP BY c.id ORDER BY c.section
            ");
            $stmt2->execute(array_merge([$grade['id']], $teacherClassIds));
            $grade['classes'] = $stmt2->fetchAll();
        }
    }
    jsonSuccess($grades);
}

function saveGrade() {
    requireRole(['admin']);
    $data = getPostData();
    validateRequired($data, ['name', 'code']);
    $db = getDB();
    $id   = $data['id'] ?? null;
    $name = sanitize($data['name']);
    $code = sanitize($data['code']);
    if ($id) {
        $db->prepare("UPDATE grades SET name = ?, code = ? WHERE id = ?")->execute([$name, $code, $id]);
    } else {
        $db->prepare("INSERT INTO grades (name, code) VALUES (?, ?)")->execute([$name, $code]);
        $id = $db->lastInsertId();
    }
    logActivity($id ? 'update' : 'create', 'grade', $id, $name);
    jsonSuccess(['id' => (int)$id], 'تم حفظ الصف بنجاح');
}

function deleteGrade() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    $db->prepare("UPDATE grades SET active = 0 WHERE id = ?")->execute([$id]);
    $db->prepare("UPDATE classes SET active = 0 WHERE grade_id = ?")->execute([$id]);
    logActivity('delete', 'grade', $id);
    jsonSuccess(null, 'تم حذف الصف');
}

// ============================================================
// CLASSES
// ============================================================
function getClasses() {
    requireLogin();
    $db = getDB();
    $gradeId        = getParam('grade_id');
    $teacherClassIds = getTeacherClassIds();

    $sql    = "SELECT c.*, g.name as grade_name, g.code as grade_code,
               CONCAT(g.name, ' - ', c.name) as full_name,
               COUNT(s.id) as student_count
            FROM classes c JOIN grades g ON c.grade_id = g.id
            LEFT JOIN students s ON s.class_id = c.id AND s.active = 1
            WHERE c.active = 1";
    $params = [];

    if ($gradeId) { $sql .= " AND c.grade_id = ?"; $params[] = $gradeId; }

    // Teacher filter
    if ($teacherClassIds !== null) {
        if (empty($teacherClassIds)) {
            jsonSuccess([]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($teacherClassIds), '?'));
        $sql .= " AND c.id IN ($placeholders)";
        $params = array_merge($params, $teacherClassIds);
    }

    $sql .= " GROUP BY c.id ORDER BY g.sort_order, g.id, c.section";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

function saveClass() {
    requireRole(['admin', 'teacher']);
    $data = getPostData();
    validateRequired($data, ['grade_id', 'section']);
    $db      = getDB();
    $id      = $data['id'] ?? null;
    $gradeId = (int)$data['grade_id'];
    $section = sanitize($data['section']);

    // If teacher is editing, they must own the class
    if ($id && !isAdmin() && !canAccessClass((int)$id)) {
        jsonError('لا تملك صلاحية تعديل هذا الفصل', 403);
    }

    $grade = $db->prepare("SELECT code FROM grades WHERE id = ?");
    $grade->execute([$gradeId]);
    $g = $grade->fetch();
    if (!$g) jsonError('الصف غير موجود');
    $name = $g['code'] . '/' . $section;

    if ($id) {
        $db->prepare("UPDATE classes SET grade_id = ?, name = ?, section = ? WHERE id = ?")
           ->execute([$gradeId, $name, $section, $id]);
    } else {
        $db->prepare("INSERT INTO classes (grade_id, name, section, created_by) VALUES (?, ?, ?, ?)")
           ->execute([$gradeId, $name, $section, $_SESSION['user_id']]);
        $id = $db->lastInsertId();
        // Auto-assign the creating teacher to this new class
        if (!isAdmin()) {
            try {
                assignClassToTeacher((int)$_SESSION['user_id'], (int)$id);
            } catch (Exception $e) { /* migration may not have run yet */ }
        }
    }

    logActivity($id ? 'update' : 'create', 'class', $id, $name);
    jsonSuccess(['id' => (int)$id], 'تم حفظ الفصل بنجاح');
}

function deleteClass() {
    requireRole(['admin']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');
    $db = getDB();
    $db->prepare("UPDATE classes SET active = 0 WHERE id = ?")->execute([$id]);
    logActivity('delete', 'class', $id);
    jsonSuccess(null, 'تم حذف الفصل');
}
