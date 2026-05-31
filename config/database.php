<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'payroll_system');

class Database {
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            die("Connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

if (session_status() == PHP_SESSION_NONE) session_start();

function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['roleID']) && $_SESSION['roleID'] == 1; }
function isHR() { return isset($_SESSION['roleID']) && $_SESSION['roleID'] == 2; }

function requireLogin() {
    if (!isLoggedIn()) { header("Location: ../login.php"); exit(); }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) { header("Location: dashboard.php"); exit(); }
}
function getUserRoleName() {
    if (isAdmin()) return 'ADMIN';
    if (isHR()) return 'HR';
    return 'STAFF';
}
?>