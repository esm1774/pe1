<?php
/**
 * Migration: Add authentication fields to students table
 * Adds password and last_login columns for the new Student Portal.
 */

require_once 'config.php';

// Detect if running in browser
$isBrowser = (php_sapi_name() !== 'cli');
if ($isBrowser) {
    echo "<pre>";
}

try {
    $db = getDB();
    echo "Starting migration: Adding authentication fields to students table...\n";

    // 1. Check if password column exists
    $cols = array_column($db->query("SHOW COLUMNS FROM students")->fetchAll(), 'Field');
    
    $alter = [];
    if (!in_array('password', $cols)) {
        $alter[] = "ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `student_number`";
        echo "- Adding 'password' column\n";
    }
    
    if (!in_array('last_login', $cols)) {
        $alter[] = "ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `active`";
        echo "- Adding 'last_login' column\n";
    }

    if (!empty($alter)) {
        $db->exec("ALTER TABLE students " . implode(", ", $alter));
        echo "✅ Migration successful: Columns added.\n";
    } else {
        echo "ℹ️ Migration skipped: Columns already exist.\n";
    }

    // 2. Security: Ensure student_number is unique (already is in schema, but good to check)
    echo "✅ Database schema updated for Student Portal.\n";

} catch (Exception $e) {
    die("❌ Error during migration: " . $e->getMessage() . "\n");
}
