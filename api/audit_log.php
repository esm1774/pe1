<?php
/**
 * PE Smart School System - Audit Log API
 * =====================================
 * عرض سجل الأنشطة (Audit Log) للمشرفين والمدراء
 */

function getAuditLogs() {
    requireLogin();
    // Allow only managerial roles
    requireRole(['admin', 'supervisor']);

    $db = getDB();
    $sid = schoolId();
    $params = [];
    $schoolFilter = "";

    // Security: Only super_admin logic can bypass school_id check, but for SaaS, $sid should be present for regular admins.
    if ($sid) {
        $schoolFilter = "WHERE a.school_id = ?";
        $params[] = $sid;
    } else {
        // If some entity creates logs without school_id and an admin is checking... typically should not happen for school-level admins.
        $schoolFilter = "WHERE a.school_id IS NULL";
    }

    $stmt = $db->prepare("
        SELECT a.id, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at,
               u.name as user_name, u.role as user_role
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        $schoolFilter
        ORDER BY a.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    jsonSuccess($logs);
}
