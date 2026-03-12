<?php
/**
 * PE Smart School - System Health Utility
 * Provides diagnostic info for administrators
 */

require_once __DIR__ . '/../config.php';

function getSystemHealth() {
    requireRole(['admin']);
    
    $health = [
        'database' => [
            'status' => 'ok',
            'details' => 'Connected successfully'
        ],
        'storage' => [
            'status' => 'unknown',
            'free_space' => 0,
            'total_space' => 0,
            'percent_free' => 0
        ],
        'mail' => [
            'status' => 'pending',
            'config' => defined('SMTP_HOST') ? 'SMTP' : 'Native PHP mail()'
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'debug_mode' => defined('DEBUG_MODE') ? DEBUG_MODE : false,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit')
        ]
    ];

    // 1. Database Check
    try {
        $db = getDB();
        $stmt = $db->query("SELECT VERSION()");
        $health['database']['version'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT TABLE_NAME, ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS `size_mb` 
                             FROM information_schema.TABLES 
                             WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
        $health['database']['tables'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $health['database']['status'] = 'error';
        $health['database']['details'] = $e->getMessage();
    }

    // 2. Storage Check
    $path = __DIR__;
    if (function_exists('disk_free_space')) {
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free !== false && $total !== false) {
            $health['storage']['status'] = ($free < 100 * 1024 * 1024) ? 'warning' : 'ok';
            $health['storage']['free_space'] = round($free / 1024 / 1024 / 1024, 2) . ' GB';
            $health['storage']['total_space'] = round($total / 1024 / 1024 / 1024, 2) . ' GB';
            $health['storage']['percent_free'] = round(($free / $total) * 100, 1) . '%';
        }
    }

    // 3. Mail Connection Check (Light check)
    if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
        $health['mail']['status'] = 'configured (SMTP)';
    } else {
        $health['mail']['status'] = 'configured (Native)';
    }

    return $health;
}

// If called directly via API
if (basename($_SERVER['PHP_SELF']) == 'health.php') {
    jsonSuccess(getSystemHealth());
}
