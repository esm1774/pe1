<?php
require_once 'config.php';
$db = getDB();

echo "--- SCHOOL 2 INFO ---\n";
try {
    $stmt = $db->query("SELECT id, name, subscription_status, trial_ends_at, subscription_ends_at, active FROM schools WHERE id = 2");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- GRADES FOR SCHOOL 2 ---\n";
try {
    $stmt = $db->query("SELECT * FROM grades WHERE school_id = 2");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
