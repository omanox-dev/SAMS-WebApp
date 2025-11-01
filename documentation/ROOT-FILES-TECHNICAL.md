# SAMS Root Level Files - Technical Documentation

## 🏗️ **System Architecture Overview**

The root level files form the foundational layer of the SAMS application, handling authentication, session management, database initialization, and system diagnostics. These files implement the core security model and provide entry points for role-based access control.

---

## 🔐 **index.php - Authentication Gateway**

### **Security Architecture**

#### **Multi-Layer Authentication System**
```php
// 1. Dependency Loading
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// 2. Database Connection
$database = new Database();
$db = $database->getConnection();

// 3. Session State Check
if (isLoggedIn()) {
    // Role-based redirection for authenticated users
    switch ($_SESSION['user_role']) {
        case 'admin':
            redirect(URL_ROOT . '/admin/dashboard.php');
        case 'teacher':
            redirect(URL_ROOT . '/teacher/dashboard.php');
        case 'student':
            redirect(URL_ROOT . '/student/dashboard.php');
    }
}
```

**Authentication Flow Design**:
- **Dependency Injection**: Core configuration and utilities loaded first
- **Connection Establishment**: Database connectivity verified before processing
- **Session Validation**: Existing authentication checked before login form
- **Role-Based Routing**: Automatic redirection to appropriate dashboards

#### **Input Validation Framework**
```php
// Sanitization and Validation
$email = sanitize($_POST['email']);
$password = $_POST['password'];

// Multi-layer Validation
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($password)) {
    $errors[] = 'Password is required';
}
```

**Validation Strategy**:
- **Input Sanitization**: XSS prevention through `sanitize()` function
- **Email Validation**: PHP filter for format verification
- **Required Field Validation**: Empty field detection
- **Error Accumulation**: Multiple validation errors collected and displayed

### **Database Authentication Process**

#### **Secure User Lookup**
```php
$stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Account Status Verification
    if ($user['status'] !== 'active') {
        $errors[] = 'Your account is inactive. Please contact the administrator.';
    } else {
        // Password Verification
        if (verifyPassword($password, $user['password'])) {
            // Successful authentication
        }
    }
}
```

**Security Implementation Features**:
- **Prepared Statements**: SQL injection prevention
- **Limited Data Selection**: Only necessary fields retrieved
- **Account Status Check**: Inactive account protection
- **Password Hash Verification**: BCrypt verification through `verifyPassword()`

#### **Session Establishment**
```php
// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

// Activity Logging
logActivity('Login', 'User logged in successfully');

// Role-based Redirection
switch ($user['role']) {
    case 'admin':
        redirect(URL_ROOT . '/admin/dashboard.php');
    case 'teacher':
        redirect(URL_ROOT . '/teacher/dashboard.php');
    case 'student':
        redirect(URL_ROOT . '/student/dashboard.php');
}
```

**Session Security Features**:
- **Minimal Session Data**: Only essential information stored
- **Activity Logging**: Login events tracked for audit trail
- **Immediate Redirection**: Prevents session fixation attacks
- **Role-Based Routing**: Ensures users land on appropriate interfaces

### **Frontend Security Implementation**

#### **Form Security Features**
```html
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
    <div class="mb-3">
        <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
        <div class="invalid-feedback">Please enter a valid email address.</div>
    </div>
    
    <div class="mb-3">
        <input type="password" class="form-control" id="password" name="password" required>
        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
            <i class="fas fa-eye"></i>
        </button>
    </div>
</form>
```

**Frontend Security Elements**:
- **Self-Posting Form**: Prevents CSRF through same-page submission
- **HTML5 Validation**: Browser-level validation for immediate feedback
- **Bootstrap Validation**: Visual feedback for validation states
- **Password Toggle**: Secure password visibility control

---

## 🚪 **logout.php - Secure Session Termination**

### **Complete Session Cleanup Architecture**

#### **Multi-Stage Logout Process**
```php
session_start();

// Stage 1: Clear session array
$_SESSION = array();

// Stage 2: Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Stage 3: Destroy session
session_destroy();

// Stage 4: Redirect to login
header("Location: index.php");
exit;
```

**Security Implementation Analysis**:

#### **Stage 1: Session Data Clearing**
- **Complete Array Reset**: `$_SESSION = array()` removes all session variables
- **Memory Cleanup**: Prevents data leakage between sessions
- **Immediate Effect**: Data cleared before cookie removal

#### **Stage 2: Cookie Destruction**
- **Parameter Preservation**: Uses original cookie parameters for proper deletion
- **Time Manipulation**: `time() - 42000` ensures cookie expiration
- **Security Flag Preservation**: Maintains secure and httponly flags during deletion
- **Domain/Path Specific**: Ensures cookie deleted from correct scope

#### **Stage 3: Session Destruction**
- **Server-side Cleanup**: `session_destroy()` removes session file from server
- **Complete Termination**: No session data remains on server
- **Resource Cleanup**: Frees server resources

#### **Stage 4: Secure Redirection**
- **Immediate Redirect**: Prevents accidental page access
- **Location Header**: Standard HTTP redirection
- **Exit Statement**: Ensures no further code execution

### **Security Benefits**

#### **Anti-Session Fixation**
- **Complete Session Reset**: New session required for re-authentication
- **Cookie Invalidation**: Old session cookies cannot be reused
- **Server Cleanup**: Session files removed from server storage

#### **Data Protection**
- **No Residual Data**: No session information remains accessible
- **Browser Security**: Session cookies properly invalidated
- **Memory Protection**: Session variables cleared from server memory

---

## 🛠️ **db_check.php - Database Diagnostic Tool**

### **Database Connectivity Verification**

#### **Connection Testing Framework**
```php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'activity_logs'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
```

**Diagnostic Capabilities**:
- **Configuration Validation**: Tests database configuration settings
- **Connection Verification**: Confirms database connectivity
- **Table Existence Check**: Verifies required database schema
- **Error Reporting**: Detailed error messages for troubleshooting

#### **Automatic Table Creation**
```php
if (!$tableExists) {
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
}
```

**Schema Management Features**:
- **DDL Execution**: Direct SQL execution for table creation
- **Schema Validation**: Ensures required table structure exists
- **Automatic Repair**: Creates missing tables without manual intervention
- **Progress Reporting**: User feedback during table creation process

### **Admin User Management**

#### **Password Hash Generation**
```php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Admin user password hash: " . $hash;
```

**Security Features**:
- **BCrypt Algorithm**: Industry-standard password hashing
- **Cost Factor 12**: High security with 4096 iterations
- **Secure Hash Output**: Safe for database storage
- **Administrative Access**: Provides initial admin account setup

#### **User Existence Verification**
```php
$stmt = $db->prepare("SELECT * FROM users WHERE email = 'admin@example.com'");
$stmt->execute();
$adminExists = $stmt->rowCount() > 0;

if ($adminExists) {
    echo "Admin user exists in the database.";
} else {
    echo "Admin user does not exist. Please create one.";
}
```

**Account Management**:
- **Admin Detection**: Checks for existing administrative accounts
- **Setup Guidance**: Provides instructions for admin account creation
- **Database Query**: Safe parameterized query for user lookup
- **Status Reporting**: Clear feedback on admin account status

---

## 🔑 **admin_hash.php - Password Hash Utility**

### **Cryptographic Hash Generation**

#### **BCrypt Implementation**
```php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Password hash for '$password': $hash";
```

**Cryptographic Analysis**:
- **Algorithm Selection**: BCrypt chosen for adaptive hashing
- **Cost Parameter**: Factor 12 provides 4096 iterations
- **Salt Generation**: Automatic random salt generation
- **Output Format**: Standard BCrypt hash format

### **Security Considerations**

#### **Development vs Production Usage**
- **Development Tool**: Intended for initial setup and testing
- **Production Risk**: Should be removed or access-restricted in production
- **Default Password**: 'admin123' should be changed immediately
- **Hash Uniqueness**: Each execution generates unique hash due to random salt

#### **Best Practices**
- **One-Time Use**: Generate hash and remove/protect file
- **Secure Transmission**: Use HTTPS when accessing hash generation
- **Password Policy**: Implement strong password requirements
- **Access Control**: Restrict file access to authorized personnel

---

## 📊 **test.php - System Diagnostic Tool**

### **PHP Environment Analysis**

#### **Information Disclosure**
```php
phpinfo();
```

**Diagnostic Information**:
- **PHP Version**: Current PHP version and build information
- **Extensions**: Loaded PHP extensions and their configurations
- **Server Variables**: Environment and server configuration
- **Database Support**: PDO and database driver availability

### **Security Implications**

#### **Information Exposure Risks**
- **System Details**: Reveals server configuration details
- **Security Settings**: Exposes security-related configurations
- **File Paths**: Shows system file locations
- **Version Information**: Reveals software versions

#### **Production Security**
- **Access Restriction**: Should be removed or access-controlled in production
- **Temporary Usage**: Only for development and troubleshooting
- **Alternative Tools**: Use proper monitoring tools in production
- **Regular Cleanup**: Remove diagnostic files after use

---

## 🔄 **System Integration Architecture**

### **File Interdependencies**

#### **Dependency Chain**
```
index.php (Entry Point)
├── config/config.php (Application Constants)
├── config/database.php (Database Connection)
├── includes/functions.php (Utility Functions)
└── Role-based Dashboard Redirects

logout.php (Session Termination)
├── Session Management
├── Cookie Cleanup
└── Redirect to index.php

db_check.php (Database Diagnostics)
├── config/database.php
├── Schema Validation
└── Admin User Management

admin_hash.php (Password Utility)
└── Cryptographic Hash Generation

test.php (Environment Diagnostics)
└── PHP Configuration Display
```

### **Security Architecture Integration**

#### **Authentication Flow**
1. **Entry Point**: `index.php` serves as authentication gateway
2. **Session Management**: Secure session establishment and validation
3. **Role-Based Routing**: Automatic redirection to appropriate interfaces
4. **Session Termination**: `logout.php` provides secure logout functionality

#### **Development Tools**
1. **Database Setup**: `db_check.php` ensures proper database configuration
2. **Password Management**: `admin_hash.php` generates secure password hashes
3. **Environment Testing**: `test.php` validates PHP environment

---

## 🛡️ **Security Best Practices**

### **Production Deployment**

#### **File Security**
- **Remove Diagnostic Tools**: Delete or restrict access to `test.php` and `admin_hash.php`
- **Protect Utilities**: Secure access to `db_check.php` for authorized personnel only
- **Regular Audits**: Periodically review file permissions and access
- **Version Control**: Exclude sensitive files from version control

#### **Session Security**
- **HTTPS Enforcement**: Use HTTPS in production for encrypted communication
- **Cookie Security**: Ensure secure cookie flags are properly configured
- **Session Timeout**: Implement appropriate session timeout values
- **Cross-Site Protection**: Implement CSRF protection for forms

### **Monitoring & Maintenance**

#### **Log Analysis**
- **Authentication Logs**: Monitor login attempts and failures
- **Error Logs**: Regular review of system error logs
- **Activity Tracking**: Use activity logs for audit trails
- **Performance Monitoring**: Monitor system performance and resource usage

#### **Database Security**
- **Connection Security**: Use secure database connections
- **User Privileges**: Implement least-privilege database access
- **Regular Backups**: Maintain regular database backups
- **Schema Updates**: Keep database schema updated and optimized

This technical documentation provides comprehensive insight into the root level files that form the foundation of the SAMS application, demonstrating sophisticated security implementation and system architecture design.