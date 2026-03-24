<?php
require_once 'config.php';
require_once 'includes/helpers.php';

$blogDb = getBlogDB();
if (!$blogDb) {
    die("No blog DB connection");
}

$prefix = defined('DB_BLOG_PREFIX') ? DB_BLOG_PREFIX : 'wp_';
$sql = "SELECT option_name, option_value FROM {$prefix}options WHERE option_name IN ('siteurl', 'home')";
$stmt = $blogDb->query($sql);
$options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
