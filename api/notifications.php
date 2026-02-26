<?php
/**
 * PE Smart School System - Notifications API
 */

/**
 * Get all notifications for the current parent
 */
function getNotifications() {
    $user = requireRole(['parent']);
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT n.*, s.name as student_name
        FROM notifications n
        LEFT JOIN students s ON n.student_id = s.id
        WHERE n.parent_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    jsonSuccess($stmt->fetchAll());
}

/**
 * Mark a notification as read
 */
function markNotificationRead() {
    $user = requireRole(['parent']);
    $id = getParam('id');
    if (!$id) jsonError('معرّف غير صالح');

    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND parent_id = ?");
    $stmt->execute([$id, $user['id']]);
    jsonSuccess(null, 'تم التحديث');
}

/**
 * Mark all notifications as read for the current parent
 */
function markAllNotificationsRead() {
    $user = requireRole(['parent']);
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE parent_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    jsonSuccess(null, 'تم تحديث جميع التنبيهات');
}

/**
 * Get count of unread notifications for the parent
 */
function getUnreadNotificationsCount() {
    $user = requireRole(['parent']);
    $db = getDB();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE parent_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    jsonSuccess(['count' => (int)$stmt->fetchColumn()]);
}

/**
 * Utility to send a notification (used internally or by admin)
 * 
 * @param int $parentId
 * @param string $type ('attendance', 'fitness', 'health', 'general')
 * @param string $title
 * @param string $message
 * @param int|null $studentId
 */
function createNotification($parentId, $type, $title, $message, $studentId = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (parent_id, student_id, type, title, message) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$parentId, $studentId, $type, $title, $message]);
}

/**
 * Notify all active parents of a student
 */
function notifyStudentParents($studentId, $type, $title, $message) {
    $db = getDB();
    $pStmt = $db->prepare("SELECT p.id FROM parents p 
                          JOIN parent_students ps ON p.id = ps.parent_id 
                          WHERE ps.student_id = ? AND p.active = 1");
    $pStmt->execute([$studentId]);
    $parents = $pStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($parents as $parentId) {
        createNotification($parentId, $type, $title, $message, $studentId);
    }
}

/**
 * Notify all parents of students in a specific class
 */
function notifyClassParents($classId, $type, $title, $message) {
    $db = getDB();
    $pStmt = $db->prepare("SELECT DISTINCT p.id FROM parents p 
                          JOIN parent_students ps ON p.id = ps.parent_id 
                          JOIN students s ON ps.student_id = s.id
                          WHERE s.class_id = ? AND p.active = 1");
    $pStmt->execute([$classId]);
    $parents = $pStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($parents as $parentId) {
        createNotification($parentId, $type, $title, $message, null);
    }
}
