<?php
/**
 * Database Configuration
 */
class Database {
    // Database credentials - change these according to your setup
    private $host = "localhost";
    private $db_name = "attendance_system";
    private $username = "root";
    private $password = "";
    public $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            // Log error
            file_put_contents(
                dirname(__DIR__) . "/logs/db_error.log", 
                date('Y-m-d H:i:s') . " : " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }
}
?>
