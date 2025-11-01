<?php
/**
 * Application Configuration
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS

// Session configuration.venv\Scripts\Activate.ps1
session_start();

// URLs
define('URL_ROOT', 'http://localhost/SAMS');
define('SITE_NAME', 'Student Attendance Management System');

// Attendance settings
define('ATTENDANCE_EDIT_HOURS', 24); // Hours within which teachers can edit attendance

// Minimum required attendance percentage
define('MIN_ATTENDANCE_PERCENTAGE', 75);

// Maximum login attempts
define('MAX_LOGIN_ATTEMPTS', 5);

// Time (in seconds) to lock account after failed login attempts
define('LOGIN_LOCKOUT_TIME', 1800); // 30 minutes

// Debug mode
define('DEBUG_MODE', false);

?>
