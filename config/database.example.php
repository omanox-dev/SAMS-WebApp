<?php
/**
 * Example database configuration for SAMS.
 * Copy this file to config/database_local.php and fill in real values (which is ignored by git).
 */
class Database {
    // Example/placeholder credentials
    private $host = "localhost";
    private $db_name = "attendance_system";
    private $username = "db_user";
    private $password = "db_password";
    public $conn;

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
            // Do not echo real errors in production; log instead
            file_put_contents(dirname(__DIR__) . "/logs/db_error.log", date('Y-m-d H:i:s') . " : " . $e->getMessage() . "\n", FILE_APPEND);
        }

        return $this->conn;
    }
}
?>
