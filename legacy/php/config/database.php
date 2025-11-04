<?php
// config/database.php
// Database configuration for Campus chat

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'campus_chat');
define('DB_USER', 'root');
define('DB_PASS', ''); // Change this if you have a password set

// Database connection using PDO
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn = null;

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
            exit();
        }
        return $this->conn;
    }
    
    // Close connection
    public function closeConnection() {
        $this->conn = null;
    }
}

// Create global database instance
$database = new Database();
$pdo = $database->getConnection();

// Test connection function
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch(PDOException $e) {
        return false;
    }
}
?>