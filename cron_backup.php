<?php
/**
 * PE Smart School System - Automated Backup Script
 * ================================================
 * يقوم هذا السكربت بعمل نسخة احتياطية (Dump) لقاعدة البيانات 
 * وحفظها في مجلد backups.
 * يتم تشغيله عبر Cron Job يومياً أو أسبوعياً.
 */

// إخفاء الأخطاء المباشرة لتجنب ظهورها في مخرجات الـ Cron
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config.php';

// التأكد من أن المشغل المباشر هو الـ CLI (Cron Job) أو عن طريق تمرير key سري عبر الرابط للحماية من العابثين
$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));
$secretKey = 'PESmartBackupX2026'; // غير هذا المفتاح بأي شيء سري تريده

if (!$isCli) {
    // If accessed via web browser, require the secret key
    if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
        header('HTTP/1.0 403 Forbidden');
        die('Forbidden');
    }
}

// إعدادات المسار
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// حان وقت النسخ الاحتياطي (اسم الملف بالتاريخ والوقت)
$dateStr = date('Y-m-d_H-i-s');
$backupFileName = 'db_backup_' . $dateStr . '.sql';
$backupFilePath = $backupDir . '/' . $backupFileName;

// متغيرات قاعدة البيانات (تُجلب من الكود الأساسي)
global $dbHost, $dbUser, $dbPass, $dbName;
if (!isset($dbHost)) $dbHost = 'localhost';
if (!isset($dbUser)) $dbUser = 'root';
if (!isset($dbPass)) $dbPass = '';
if (!isset($dbName)) $dbName = 'pe_smart_school'; // عدّل اسم القاعدة إن لزم الأمر

// إنشاء أمر mysqldump للنسخ الاحتياطي
// *ملاحظة في استضافة Hostinger، عادةً أمر mysqldump يكون متاحاً بشكل افتراضي*
$command = sprintf(
    'mysqldump --opt -h %s -u %s %s %s > %s',
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    ($dbPass ? '-p' . escapeshellarg($dbPass) : ''),
    escapeshellarg($dbName),
    escapeshellarg($backupFilePath)
);

// تنفيذ الأمر
$output = [];
$returnVar = NULL;
exec($command, $output, $returnVar);

// إذا تم النسخ بنجاح، نقوم بضغط الملف إلى ZIP لتوفير المساحة
if ($returnVar === 0 && file_exists($backupFilePath)) {
    $zipFileName = current(explode('.sql', $backupFileName)) . '.zip';
    $zipFilePath = $backupDir . '/' . $zipFileName;
    
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backupFilePath, $backupFileName);
        $zip->close();
        
        // مسح ملف الـ SQL لترك ملف الـ ZIP المضغوط فقط
        unlink($backupFilePath);
        
        $finalFile = $zipFileName;
    } else {
        $finalFile = $backupFileName;
    }

    // تنظيف النسخ القديمة (إبقاء آخر 14 نسخة فقط، أسبوعين)
    $files = glob($backupDir . '/db_backup_*.{sql,zip}', GLOB_BRACE);
    if (count($files) > 14) {
        array_multisort(
            array_map('filemtime', $files), SORT_NUMERIC, SORT_ASC, 
            $files
        );
        // Delete the oldest ones
        $filesToDelete = count($files) - 14;
        for ($i = 0; $i < $filesToDelete; $i++) {
            if (file_exists($files[$i])) {
                unlink($files[$i]);
            }
        }
    }

    $msg = "تم النسخ الاحتياطي بنجاح: " . $finalFile;
    if ($isCli) echo $msg . "\n";
    else echo '{"status":"success", "message":"' . $msg . '"}';

} else {
    // حاولنا عبر mysqldump وفشل (أو محظور)، نلجأ إلى كود PHP بديل ومرن (Pure PHP Dump)
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
        
        $sql = "-- PE Smart School DB Backup \n-- Date: ".date('Y-m-d H:i:s')."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = [];
        $views = [];
        $query = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $query = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $views[] = $row[0];
        }

        // 1. Process Tables (Structure + Data)
        foreach ($tables as $table) {
            $result = $db->query("SELECT * FROM `$table`");
            $numFields = $result->columnCount();

            // Structure (Strip DEFINER if exists to avoid import issues on other users)
            $row2 = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $createTableSQL = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $row2[1]);
            $sql .= "\n\n" . $createTableSQL . ";\n\n";

            // Data
            for ($i = 0; $i < $numFields; $i++) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $sql .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $numFields; $j++) {
                        if (isset($row[$j])) {
                            // PHP 8 deprecation fix: handle null natively
                            $val = addslashes($row[$j]);
                            $val = str_replace("\n", "\\n", $val);
                            $sql .= '"' . $val . '"';
                        } else {
                            $sql .= 'NULL';
                        }
                        if ($j < ($numFields - 1)) {
                            $sql .= ',';
                        }
                    }
                    $sql .= ");\n";
                }
            }
            $sql .= "\n\n\n";
        }

        // 2. Process Views (Structure ONLY)
        foreach ($views as $view) {
            $row2 = $db->query("SHOW CREATE VIEW `$view`")->fetch(PDO::FETCH_NUM);
            $createViewSQL = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $row2[1]);
            $sql .= "\n\n" . $createViewSQL . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Save file
        file_put_contents($backupFilePath, $sql);

        // ZIP IT
        $zipFileName = current(explode('.sql', $backupFileName)) . '.zip';
        $zipFilePath = $backupDir . '/' . $zipFileName;
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backupFilePath, $backupFileName);
            $zip->close();
            unlink($backupFilePath); // Remove raw sql file
            $finalFile = $zipFileName;
        } else {
            $finalFile = $backupFileName;
        }

        $msg = "تم النسخ الاحتياطي عبر Pure PHP بنجاح: " . $finalFile;
        if ($isCli) echo $msg . "\n";
        else echo '{"status":"success", "message":"' . $msg . '"}';

    } catch (Exception $e) {
        $msg = "فشل النسخ الاحتياطي الكلي: " . $e->getMessage();
        if ($isCli) echo $msg . "\n";
        else echo '{"status":"error", "message":"' . $msg . '"}';
    }
}
