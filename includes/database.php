<?php
/**
 * PE Smart School System - Database Connection Class
 */

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                DB_HOST, DB_NAME
            );

            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        }
        catch (PDOException $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Database Connection Error: " . $e->getMessage());
            }
            die(json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات']));
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    private function __clone()
    {
    }
}