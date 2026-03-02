<?php
require_once 'config.php';
$db = getDB();

echo "--- GRADES TABLE ---\n";
try {
    $stmt = $db->query("SELECT id, school_id, name, code FROM grades LIMIT 20");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- USERS (Sample) ---\n";
try {
    $stmt = $db->query("SELECT id, school_id, username, role FROM users WHERE role = 'admin' LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- SCHOOLS ---\n";
try {
    $stmt = $db->query("SELECT id, name, slug FROM schools LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
