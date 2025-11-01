<?php
// Include database configuration
require_once 'config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Check if activity_logs table exists
try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'activity_logs'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "The activity_logs table does not exist. Creating it now...<br>";
        
        // Create the table
        $createTable = "CREATE TABLE activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(50),
            date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createTable);
        echo "Table created successfully!<br>";
    } else {
        echo "The activity_logs table already exists.<br>";
    }
    
    echo "<br>Your database configuration appears to be working correctly.";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}

// Test creating admin user
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "<br><br>Admin user password hash: " . $hash;
echo "<br>Use this hash for your admin user in the database.";

// Check if admin user exists
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin@example.com'");
    $stmt->execute();
    $adminExists = $stmt->rowCount() > 0;
    
    if ($adminExists) {
        echo "<br><br>Admin user exists in the database. If you cannot login, update the password with the hash above.";
    } else {
        echo "<br><br>Admin user does not exist. Please create one using the hash above.";
    }
} catch (PDOException $e) {
    echo "<br>Error checking admin user: " . $e->getMessage();
}
?>
