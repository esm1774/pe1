<?php
require_once 'config.php';
$db = getDB();

echo "--- SCHOOL 1 INFO ---\n";
try {
    $stmt = $db->query("SELECT id, name, subscription_status, trial_ends_at, subscription_ends_at, active FROM schools WHERE id = 1");
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($school) {
        print_r($school);
        echo "isActive: " . (Subscription::isActive(1) ? 'YES' : 'NO') . "\n";
    } else {
        echo "School 1 not found in schools table!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- GRADES FOR SCHOOL 1 ---\n";
try {
    $stmt = $db->query("SELECT id, name, school_id FROM grades WHERE school_id = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
