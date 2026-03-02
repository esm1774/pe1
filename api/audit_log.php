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

    // Fix: Add pagination to prevent loading 500 records at once
    $page  = max(1, (int)getParam('page', 1));
    $limit = min(100, max(10, (int)getParam('limit', 50))); // Between 10-100, default 50
    $offset = ($page - 1) * $limit;

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
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Count total for pagination info
    $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log a $schoolFilter");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    jsonSuccess([
        'logs'  => $logs,
        'total' => $total,
        'page'  => $page,
        'pages' => ceil($total / $limit)
    ]);
}
