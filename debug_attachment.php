<?php
require_once 'config.php';
require_once 'includes/helpers.php';

$blogDb = getBlogDB();
$prefix = defined('DB_BLOG_PREFIX') ? DB_BLOG_PREFIX : 'wp_';

// Get thumbnail ID for post 23
$stmt = $blogDb->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_thumbnail_id'");
$stmt->execute([23]);
$thumbId = $stmt->fetchColumn();

if ($thumbId) {
    // Get attached file path
    $stmt = $blogDb->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_wp_attached_file'");
    $stmt->execute([$thumbId]);
    $filePath = $stmt->fetchColumn();
    
    // Get guid for comparison
    $stmt = $blogDb->prepare("SELECT guid FROM {$prefix}posts WHERE ID = ?");
    $stmt->execute([$thumbId]);
    $guid = $stmt->fetchColumn();
    
    echo json_encode([
        'thumb_id' => $thumbId,
        'attached_file' => $filePath,
        'guid' => $guid
    ], JSON_PRETTY_PRINT);
} else {
    echo "No thumbnail ID found for post 23";
}
