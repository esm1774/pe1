<?php
/**
 * Test script for grades API hang
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Mocking School 1 Admin (based on Ismail's school)
$_SESSION['user_id'] = 2; // Assuming 2 is the ismail admin
$_SESSION['user_role'] = 'admin';
$_SESSION['school_id'] = 1;

require_once 'config.php';

echo "DEBUG: Tenant ID: " . Tenant::id() . "\n";
echo "DEBUG: School Name: " . Tenant::name() . "\n";

$_GET['action'] = 'grades';

// Buffer output to catch pre-JSON junk
ob_start();
require_once 'api/grades.php';

echo "DEBUG: Calling getGrades()...\n";
getGrades();

$output = ob_get_clean();
echo "--- RAW OUTPUT ---\n";
echo $output;
echo "\n--- END ---\n";
