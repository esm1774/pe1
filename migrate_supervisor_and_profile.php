<?php
/**
 * Migration: Add Supervisor Role and Profile Fields
 */
require_once 'config.php';

try {
    // Attempt explicit connection to avoid config.php JSON die on failure
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=pe_smart_school;charset=utf8mb4";
    $db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Starting migration...\n";

    // 1. Update Role ENUM
    // Note: MySQL requires re-defining the ENUM to add a value
    $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'teacher', 'viewer', 'supervisor') NOT NULL DEFAULT 'teacher'");
    echo "✅ Supervisor role added to ENUM.\n";

    // 2. Add Profile Fields
    $columns = [
        'email'            => "VARCHAR(100) DEFAULT NULL AFTER `name`",
        'phone'            => "VARCHAR(20) DEFAULT NULL AFTER `email`",
        'specialization'   => "VARCHAR(100) DEFAULT NULL AFTER `phone`",
        'education'        => "VARCHAR(100) DEFAULT NULL AFTER `specialization`",
        'experience_years' => "INT UNSIGNED DEFAULT NULL AFTER `education`",
        'bio'              => "TEXT DEFAULT NULL AFTER `experience_years`",
        'birth_date'       => "DATE DEFAULT NULL AFTER `bio`"
    ];

    foreach ($columns as $col => $definition) {
        // Check if column exists first
        $check = $db->query("SHOW COLUMNS FROM `users` LIKE '$col'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `$col` $definition");
            echo "✅ Column '$col' added.\n";
        } else {
            echo "ℹ️ Column '$col' already exists.\n";
        }
    }

    echo "🎉 Migration completed successfully!\n";
} catch (Exception $e) {
    die("❌ Migration failed: " . $e->getMessage() . "\n");
}
