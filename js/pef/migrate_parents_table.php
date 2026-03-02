<?php
require 'config.php';
$db = getDB();

try {
    $db->beginTransaction();

    // 1. Create parents table
    $db->exec("CREATE TABLE IF NOT EXISTS `parents` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `active` TINYINT(1) NOT NULL DEFAULT 1,
        `last_login` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_parent_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Table 'parents' ensured.\n";

    // 2. Migrate data (only if not already migrated)
    $stmt = $db->query("SELECT * FROM users WHERE role = 'parent'");
    $usersToMigrate = $stmt->fetchAll();

    foreach ($usersToMigrate as $p) {
        // Check if already in parents table
        $stmtCheck = $db->prepare("SELECT id FROM parents WHERE username = ?");
        $stmtCheck->execute([$p['username']]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            $stmtInsert = $db->prepare("INSERT INTO parents (username, password, name, email, phone, active, last_login, created_at) VALUES (?,?,?,?,?,?,?,?)");
            $stmtInsert->execute([
                $p['username'],
                $p['password'],
                $p['name'],
                $p['email'],
                $p['phone'],
                $p['active'],
                $p['last_login'],
                $p['created_at']
            ]);
            $newId = $db->lastInsertId();
        } else {
            $newId = $existing['id'];
        }

        $oldId = $p['id'];

        // Update parent_students to use the new parent_id
        $stmtUpdate = $db->prepare("UPDATE parent_students SET parent_id = ? WHERE parent_id = ?");
        $stmtUpdate->execute([$newId, $oldId]);
        echo "Handled migration for: " . $p['username'] . " (Old ID: $oldId -> New ID: $newId)\n";
    }

    // 3. Purge orphans in parent_students (any parent_id NOT in parents table)
    $db->exec("DELETE FROM parent_students WHERE parent_id NOT IN (SELECT id FROM parents)");
    echo "Purged orphaned records from parent_students.\n";

    // 4. Handle Foreign Key
    try {
        // Remove 'parent' from ENUM is hard via SQL alone without knowing exact definition but let's try dropping FK first
        $db->exec("ALTER TABLE parent_students DROP FOREIGN KEY fk_ps_parent");
        echo "Dropped old FK.\n";
    } catch (Exception $e) {}

    $db->exec("ALTER TABLE parent_students ADD CONSTRAINT fk_ps_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE");
    echo "Added new FK pointing to parents table.\n";

    // 5. Delete from users
    $db->exec("DELETE FROM users WHERE role = 'parent'");
    echo "Deleted parent roles from users table.\n";

    $db->commit();
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Migration failed: " . $e->getMessage());
}
