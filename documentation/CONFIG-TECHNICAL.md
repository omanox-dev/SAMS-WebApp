# SAMS Configuration - Technical Documentation

## 🏗️ **Configuration Architecture Overview**

### **Directory Structure**
```
config/
├── config.php                # Main application configuration
└── database.php              # Database connection management
```

### **Configuration Strategy**
- **Centralized Settings**: All system constants in single location
- **Environment Awareness**: Development vs production configurations
- **Security-First Design**: Secure defaults with hardening options
- **Extensible Architecture**: Easy addition of new configuration options

---

## 📄 **config.php - Application Configuration Analysis**

### **File Structure & Organization**
```php
<?php
// 1. Path Configuration
// 2. Environment Settings  
// 3. Error Handling
// 4. Session Security
// 5. Application Constants
// 6. Business Logic Settings
// 7. Security Parameters
?>
```

### **1. Path Configuration System**
```php
define('BASE_PATH', dirname(__DIR__));
```
**Implementation Details**:
- **Dynamic Path Resolution**: Uses `dirname(__DIR__)` for portable installation
- **Cross-Platform Compatibility**: Works on Windows, Linux, macOS
- **Relative Path Safety**: Prevents issues with symbolic links
- **Installation Flexibility**: No hardcoded paths requiring manual updates

**Technical Benefits**:
- **Portability**: Easy migration between environments
- **Version Control**: No environment-specific paths in repository
- **Docker Compatibility**: Works in containerized environments
- **Security**: Prevents path traversal vulnerabilities

### **2. Timezone Management**
```php
date_default_timezone_set('Asia/Kolkata');
```
**Configuration Strategy**:
- **Global Setting**: Affects all PHP date/time functions
- **Database Consistency**: Ensures consistent timestamps
- **User Experience**: Displays times in local timezone
- **Internationalization Ready**: Easy to modify for different regions

**Technical Implementation**:
- **PHP Internal**: Sets default timezone for entire application
- **MySQL Coordination**: Should match database timezone settings
- **Logging Consistency**: All log timestamps use same timezone
- **Report Accuracy**: Ensures accurate time-based reporting

### **3. Error Handling Architecture**
```php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');
```
**Multi-Layer Error Strategy**:
- **Comprehensive Reporting**: E_ALL captures all error types
- **Production Safety**: display_errors = 0 hides errors from users
- **Developer Support**: Detailed logging for troubleshooting
- **Centralized Logging**: Single error log location

**Security Considerations**:
- **Information Disclosure Prevention**: No error details to users
- **Log File Security**: Error logs outside web root
- **Sensitive Data Protection**: Avoid logging passwords/tokens
- **Log Rotation**: Prevents log files from growing too large

### **4. Session Security Framework**
```php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
session_start();
```
**Security Configuration Analysis**:

#### **session.cookie_httponly = 1**
- **XSS Protection**: Prevents JavaScript access to session cookies
- **Attack Mitigation**: Reduces impact of cross-site scripting
- **Browser Enforcement**: Modern browsers enforce this setting
- **Standard Compliance**: Follows security best practices

#### **session.use_only_cookies = 1**
- **URL Tampering Prevention**: Eliminates session ID in URLs
- **Referrer Safety**: Prevents session leakage through HTTP referrer
- **Clean URLs**: No session IDs visible in browser address bar
- **Cache Safety**: Prevents session IDs in cached pages

#### **session.cookie_secure Configuration**
```php
// Development: HTTP allowed
ini_set('session.cookie_secure', 0);

// Production: HTTPS required
ini_set('session.cookie_secure', 1);
```
**Environment-Specific Security**:
- **Development Flexibility**: Works with HTTP for local development
- **Production Security**: Forces HTTPS in live environment
- **Deployment Strategy**: Requires configuration change for production
- **Transport Security**: Ensures encrypted session transmission

### **5. Application Constants System**
```php
define('URL_ROOT', 'http://localhost/SAMS');
define('SITE_NAME', 'Student Attendance Management System');
```
**Constant Management Strategy**:

#### **URL_ROOT Configuration**
- **Base URL**: Foundation for all application links
- **Environment Specific**: Different values for dev/staging/production
- **Protocol Flexibility**: HTTP for development, HTTPS for production
- **Path Independence**: Works with subdirectory installations

#### **SITE_NAME Usage**
- **Branding Consistency**: Used throughout application interface
- **Email Templates**: Included in notification emails
- **Report Headers**: Appears on printed reports
- **Page Titles**: Consistent site identification

### **6. Business Logic Configuration**
```php
define('ATTENDANCE_EDIT_HOURS', 24);
define('MIN_ATTENDANCE_PERCENTAGE', 75);
```
**Configurable Business Rules**:

#### **ATTENDANCE_EDIT_HOURS = 24**
- **Teacher Flexibility**: Allows corrections within reasonable timeframe
- **Data Integrity**: Prevents excessive retroactive changes
- **Audit Trail**: System tracks when attendance was marked vs edited
- **Institutional Policy**: Configurable to match school policies

**Implementation Impact**:
```php
// Usage in attendance validation
$edit_deadline = strtotime($attendance_date . ' +' . ATTENDANCE_EDIT_HOURS . ' hours');
if (time() > $edit_deadline) {
    throw new Exception('Attendance edit window has expired');
}
```

#### **MIN_ATTENDANCE_PERCENTAGE = 75**
- **Academic Standards**: Configurable minimum attendance requirement
- **Alert Triggers**: Automated warnings for low attendance
- **Report Generation**: Color-coding based on this threshold
- **Institutional Compliance**: Matches educational board requirements

**System Integration**:
```php
// Usage in attendance calculations
$attendance_percentage = ($present_days / $total_days) * 100;
$status = ($attendance_percentage >= MIN_ATTENDANCE_PERCENTAGE) ? 'GOOD' : 'LOW';
```

### **7. Security Parameter Configuration**
```php
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 1800); // 30 minutes
```
**Brute Force Protection Strategy**:

#### **MAX_LOGIN_ATTEMPTS = 5**
- **Attack Prevention**: Limits password guessing attempts
- **User Balance**: Not too restrictive for legitimate users
- **Configurable Defense**: Adjustable based on security requirements
- **Account Protection**: Prevents credential stuffing attacks

#### **LOGIN_LOCKOUT_TIME = 1800 (30 minutes)**
- **Temporary Lockout**: Account temporarily disabled after failed attempts
- **Attack Deterrent**: Makes brute force attacks impractical
- **Administrative Balance**: Long enough to deter attacks, short enough for legitimate users
- **Automatic Recovery**: No administrator intervention required

**Security Implementation**:
```php
// Usage in login system
$failed_attempts = getFailedLoginAttempts($email);
if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
    $lockout_until = time() + LOGIN_LOCKOUT_TIME;
    throw new Exception('Account temporarily locked due to failed login attempts');
}
```

### **8. Debug Mode Configuration**
```php
define('DEBUG_MODE', false);
```
**Development vs Production**:
- **Development**: DEBUG_MODE = true enables detailed error output
- **Production**: DEBUG_MODE = false hides sensitive information
- **Conditional Logic**: Code can check this flag for debug-specific behavior
- **Performance Impact**: Debug mode may slow down application

---

## 🗄️ **database.php - Database Configuration Analysis**

### **Class-Based Database Architecture**
```php
class Database {
    private $host = "localhost";
    private $db_name = "attendance_system";
    private $username = "root";
    private $password = "";
    public $conn;
}
```

### **Object-Oriented Design Benefits**
- **Encapsulation**: Database credentials protected as private properties
- **Reusability**: Single instance can be used throughout application
- **Maintainability**: Centralized connection logic
- **Testability**: Easy to mock for unit testing

### **Connection Method Implementation**
```php
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
        // Error handling implementation
    }
    
    return $this->conn;
}
```

### **PDO Configuration Analysis**

#### **Connection String Construction**
```php
"mysql:host=" . $this->host . ";dbname=" . $this->db_name
```
- **Driver Specification**: Explicitly uses MySQL driver
- **Host Configuration**: Supports localhost or remote database servers
- **Database Selection**: Automatically connects to specified database
- **Port Flexibility**: Can be extended to include custom ports

#### **PDO Attribute Configuration**
```php
$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```
**Error Handling Strategy**:
- **Exception Mode**: Throws exceptions for database errors
- **Consistent Error Handling**: Standardized error response across application
- **Developer Friendly**: Detailed error information for debugging
- **Production Safety**: Exceptions can be caught and logged securely

#### **Character Encoding Setup**
```php
$this->conn->exec("set names utf8");
```
**UTF-8 Configuration Benefits**:
- **International Character Support**: Handles non-English characters
- **Data Integrity**: Prevents character encoding issues
- **Unicode Compliance**: Supports emoji and special characters
- **Database Consistency**: Matches application character encoding

### **Error Handling & Logging System**
```php
catch(PDOException $e) {
    file_put_contents(
        dirname(__DIR__) . "/logs/db_error.log", 
        date('Y-m-d H:i:s') . " : " . $e->getMessage() . "\n", 
        FILE_APPEND
    );
    echo "Connection error: " . $e->getMessage();
}
```

**Error Logging Strategy**:
- **Separate Log File**: Database errors logged to dedicated file
- **Timestamp Inclusion**: Each error entry includes timestamp
- **Append Mode**: Preserves previous error entries
- **File Path Resolution**: Uses relative path for portability

**Security Considerations**:
- **Sensitive Information**: Database errors may contain sensitive paths/info
- **Production vs Development**: Different error exposure strategies needed
- **Log Rotation**: Large error logs should be rotated periodically
- **Access Control**: Error logs should not be web-accessible

---

## 🔧 **Configuration Integration Patterns**

### **Application-Wide Usage**
```php
// In any PHP file
require_once 'config/config.php';
require_once 'config/database.php';

// Database connection
$database = new Database();
$db = $database->getConnection();

// Use configuration constants
$base_url = URL_ROOT;
$site_title = SITE_NAME;
$edit_window = ATTENDANCE_EDIT_HOURS;
```

### **Environment-Specific Configuration**
```php
// Environment detection
$environment = getenv('ENVIRONMENT') ?: 'development';

switch($environment) {
    case 'production':
        define('DEBUG_MODE', false);
        define('URL_ROOT', 'https://school.edu/attendance');
        ini_set('session.cookie_secure', 1);
        break;
    case 'staging':
        define('DEBUG_MODE', true);
        define('URL_ROOT', 'https://staging.school.edu/attendance');
        ini_set('session.cookie_secure', 1);
        break;
    default: // development
        define('DEBUG_MODE', true);
        define('URL_ROOT', 'http://localhost/SAMS');
        ini_set('session.cookie_secure', 0);
}
```

### **Configuration Validation**
```php
// Configuration validation function
function validateConfiguration() {
    $errors = [];
    
    // Check required constants
    if (!defined('URL_ROOT')) {
        $errors[] = 'URL_ROOT not defined';
    }
    
    // Validate database connection
    try {
        $database = new Database();
        $db = $database->getConnection();
        if (!$db) {
            $errors[] = 'Database connection failed';
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    // Check file permissions
    if (!is_writable(BASE_PATH . '/logs/')) {
        $errors[] = 'Logs directory not writable';
    }
    
    return $errors;
}
```

---

## 🛡️ **Security Architecture**

### **Configuration Security Best Practices**

#### **1. Credential Management**
```php
// Development
private $password = "";

// Production (recommended)
private $password = getenv('DB_PASSWORD');
```
**Environment Variable Strategy**:
- **Credential Separation**: Database passwords not in code
- **Version Control Safety**: Sensitive data not committed to repository
- **Deployment Security**: Production credentials managed separately
- **Access Control**: Limited access to environment variables

#### **2. File Permission Strategy**
```bash
# Recommended file permissions
chmod 644 config.php          # Read-only for web server
chmod 600 database.php        # More restrictive for credentials
chmod 755 logs/               # Write access for log files
```

#### **3. Configuration Validation**
```php
// Validate security settings
if (!ini_get('session.cookie_httponly')) {
    trigger_error('Session cookies should be HTTP-only', E_USER_WARNING);
}

if (DEBUG_MODE && $_SERVER['HTTP_HOST'] !== 'localhost') {
    trigger_error('Debug mode should be disabled in production', E_USER_ERROR);
}
```

### **Attack Prevention Mechanisms**

#### **1. SQL Injection Prevention**
- **PDO Prepared Statements**: All queries use parameter binding
- **Input Validation**: All user inputs validated before database operations
- **Least Privilege**: Database user has minimal required permissions
- **Query Logging**: Monitor for suspicious database activity

#### **2. Session Security Implementation**
- **Secure Cookies**: HTTPS-only session cookies in production
- **Session Regeneration**: Session ID regenerated after login
- **Timeout Management**: Automatic session expiration
- **CSRF Protection**: Token-based request validation

#### **3. Error Information Disclosure Prevention**
- **Production Error Hiding**: No error details shown to users
- **Sanitized Logging**: Sensitive data removed from logs
- **Generic Error Messages**: User-friendly error messages
- **Debug Mode Control**: Detailed errors only in development

---

## 🚀 **Performance Optimization**

### **Database Connection Optimization**
```php
class Database {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Singleton pattern for connection reuse
}
```

### **Configuration Caching**
```php
// Cache frequently accessed configuration
class ConfigCache {
    private static $cache = [];
    
    public static function get($key, $default = null) {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = defined($key) ? constant($key) : $default;
        }
        return self::$cache[$key];
    }
}
```

### **Memory Management**
- **Connection Pooling**: Reuse database connections
- **Configuration Caching**: Cache parsed configuration values
- **Resource Cleanup**: Properly close database connections
- **Memory Monitoring**: Track configuration-related memory usage

---

## 📊 **Monitoring & Maintenance**

### **Configuration Health Checks**
```php
function configHealthCheck() {
    return [
        'database_connection' => testDatabaseConnection(),
        'file_permissions' => checkFilePermissions(),
        'session_security' => validateSessionConfig(),
        'error_logging' => testErrorLogging(),
        'timezone_config' => validateTimezone()
    ];
}
```

### **Automated Configuration Testing**
```php
// Unit tests for configuration
class ConfigurationTest extends PHPUnit\Framework\TestCase {
    public function testDatabaseConnection() {
        $database = new Database();
        $connection = $database->getConnection();
        $this->assertNotNull($connection);
    }
    
    public function testSecuritySettings() {
        $this->assertEquals(1, ini_get('session.cookie_httponly'));
        $this->assertEquals(1, ini_get('session.use_only_cookies'));
    }
}
```

### **Performance Monitoring**
- **Connection Time Tracking**: Monitor database connection performance
- **Configuration Load Time**: Track configuration parsing time
- **Memory Usage**: Monitor configuration-related memory consumption
- **Error Rate Monitoring**: Track configuration-related errors

This technical documentation provides comprehensive understanding of the SAMS configuration architecture, security implementation, and operational procedures for effective system management and development.