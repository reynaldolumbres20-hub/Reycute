<?php
// Para sa Railway (production) at Local (development)
$mysql_url = getenv('MYSQL_URL');

if ($mysql_url) {
    // RAILWAY: Gamitin ang environment variable
    $parts = parse_url($mysql_url);
    
    $host = $parts['host'];
    $port = $parts['port'];
    $dbname = ltrim($parts['path'], '/');
    $username = $parts['user'];
    $password = $parts['pass'];
    
    define('DB_HOST', $host);
    define('DB_USER', $username);
    define('DB_PASS', $password);
    define('DB_NAME', $dbname);
    define('DB_PORT', $port);
    
} else {
    // LOCAL: XAMPP/WAMP default
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'payroll_system');
    define('DB_PORT', 3306);
}

class Database {
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            // Gamitin ang port para sa Railway
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, 
                DB_USER, 
                DB_PASS
            );
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