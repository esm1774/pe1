<?php
require 'config.php';
$db = getDB();

echo "--- Users --- \n";
$users = $db->query("SELECT id, username, role FROM users")->fetchAll();
foreach ($users as $u) echo "{$u['id']} | {$u['username']} | {$u['role']}\n";

echo "\n--- Parents --- \n";
$parents = $db->query("SELECT id, username FROM parents")->fetchAll();
foreach ($parents as $p) echo "{$p['id']} | {$p['username']}\n";

echo "\n--- Parent Students --- \n";
$ps = $db->query("SELECT id, parent_id, student_id FROM parent_students")->fetchAll();
foreach ($ps as $r) echo "ID: {$r['id']} | ParentID: {$r['parent_id']} | StudentID: {$r['student_id']}\n";
