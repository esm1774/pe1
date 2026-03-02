<?php
// Mock session for testing
session_start();
$_SESSION['user_id'] = 14; // sch_test1 (admin of school 2)
$_SESSION['user_role'] = 'admin';
$_SESSION['user_name'] = 'test school admin';
$_SESSION['school_id'] = 2;

require_once 'config.php';

// We need to bypass the die() in jsonResponse if we want to see output in the same script
// But since we are running as a CLI script, it's fine if it exits.
// However, api/grades.php calls jsonSuccess which calls jsonResponse which calls exit.

require_once 'api/grades.php';

echo "Testing getGrades() for School ID 2 (Admin User 14)...\n";
echo "Resolved School ID: " . schoolId() . "\n";

getGrades();
// Execution stops here due to exit in jsonResponse
