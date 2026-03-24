<?php
require_once 'config.php';
require_once 'includes/helpers.php';

header('Content-Type: application/json');

$posts = fetchRecentBlogPosts(5);
echo json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
