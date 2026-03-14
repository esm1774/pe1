<?php
define('DEBUG_MODE', true);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'send_report_email',
    'email' => 'test@test.com',
    'pdfData' => 'data:application/pdf;filename=test.pdf;base64,JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+',
    'title' => 'Test Report'
];
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'mock';
session_start();
$_SESSION['csrf_token'] = 'mock';
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['platform_admin'] = true;

require_once 'config.php';
require_once 'api/reports_mail.php';

function getPostData() { return $_POST; }
function validateRequired($data, $fields) {}

try {
    sendReportEmail();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
