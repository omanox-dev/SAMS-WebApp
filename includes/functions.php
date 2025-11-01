<?php
/**
 * Utility Functions
 */

/**
 * Sanitize input data
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a secure hashed password
 * @param string $password The password to hash
 * @return string The hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * @param string $password The password to verify
 * @param string $hash The hash to check against
 * @return bool True if password matches hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a random token
 * @param int $length The length of the token
 * @return string The generated token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 * @param string $role The role to check
 * @return bool True if user has the specified role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirect user
 * @param string $location The URL to redirect to
 * @return void
 */
function redirect($location) {
    header("Location: {$location}");
    exit;
}

/**
 * Log user activity
 * @param string $action The action performed
 * @param string $details Additional details
 * @param int $user_id User ID (default: current user)
 * @return bool True if logged successfully
 */
function logActivity($action, $details = '', $user_id = null) {
    global $db;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $date = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs 
                               (user_id, action, details, ip_address, date) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip, $date]);
        return true;
    } catch (PDOException $e) {
        // Log error
        file_put_contents(
            BASE_PATH . "/logs/app_error.log",
            date('Y-m-d H:i:s') . " : " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        return false;
    }
}

/**
 * Format date for display
 * @param string $date The date to format
 * @param string $format The format to use (default: Y-m-d)
 * @return string The formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Calculate attendance percentage
 * @param int $present The number of days present
 * @param int $total The total number of days
 * @return float The attendance percentage
 */
function calculateAttendancePercentage($present, $total) {
    if ($total == 0) {
        return 0;
    }
    return round(($present / $total) * 100, 2);
}

/**
 * Check if attendance is below minimum required
 * @param float $percentage The attendance percentage
 * @return bool True if attendance is below minimum
 */
function isLowAttendance($percentage) {
    return $percentage < MIN_ATTENDANCE_PERCENTAGE;
}

/**
 * Get CSS class for attendance status
 * @param string $status The attendance status
 * @return string The CSS class
 */
function getAttendanceStatusClass($status) {
    switch ($status) {
        case 'present':
            return 'bg-success';
        case 'absent':
            return 'bg-danger';
        case 'late':
            return 'bg-warning';
        default:
            return '';
    }
}

/**
 * Check if editing period is still valid
 * @param string $date The date to check
 * @return bool True if still within editing period
 */
function canEditAttendance($date) {
    $date = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($date);
    $hours = $diff->h + ($diff->days * 24);
    
    return $hours <= ATTENDANCE_EDIT_HOURS;
}

/**
 * Validate date format
 * @param string $date The date to validate
 * @param string $format The expected format
 * @return bool True if date is valid
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Log system errors
 * @param string $message Error message
 * @param string $file File where error occurred
 * @param int $line Line number where error occurred
 * @return void
 */
function logError($message, $file = '', $line = 0) {
    $log = date('Y-m-d H:i:s') . " | " . $message;
    
    if ($file) {
        $log .= " | File: " . $file;
    }
    
    if ($line) {
        $log .= " | Line: " . $line;
    }
    
    $log .= "\n";
    
    file_put_contents(
        BASE_PATH . "/logs/app_error.log",
        $log,
        FILE_APPEND
    );
}
?>
