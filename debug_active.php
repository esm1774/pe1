<?php
require_once 'config.php';
$db = getDB();

echo "--- GRADES WITH ACTIVE STATUS ---\n";
try {
    $stmt = $db->query("SELECT id, school_id, name, active FROM grades");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n--- CLASSES WITH ACTIVE STATUS ---\n";
try {
    $stmt = $db->query("SELECT id, grade_id, school_id, active FROM classes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
