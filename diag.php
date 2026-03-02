<?php
require_once 'config.php';
require_once 'includes/tenant.php';

echo "--- System Diagnostic ---\n";

// 1. Check Database connection
try {
    $db = getDB();
    echo "✅ Database Connection: OK\n";
} catch(Exception $e) {
    echo "❌ Database Connection: FAILED (" . $e->getMessage() . ")\n";
}

// 2. Check Tenant Resolution Mock
$_SERVER['REQUEST_URI'] = '/pe1/default/';
$_SERVER['SCRIPT_NAME'] = '/pe1/index.html';
Tenant::resolve();
echo "✅ Tenant Resolution (Mock /pe1/default/): " . (Tenant::id() ? "School ID: ".Tenant::id() : "FAILED") . "\n";

// 3. Check Sports Teams Tables
require_once 'modules/sports_teams/core/tables.php';
try {
    ensureSportsTeamsTables();
    echo "✅ Sports Teams Tables: Verified/Created\n";
} catch(Exception $e) {
    echo "❌ Sports Teams Tables: FAILED (" . $e->getMessage() . ")\n";
}

// 4. Check API basic response
echo "✅ API Basic Check: ";
// We can't easily perform a full HTTP request to ourselves here, but we can check if the file exists
if(file_exists('api.php')) echo "api.php exists\n";
else echo "api.php MISSING\n";

echo "--- End of Diagnostic ---\n";
?>
