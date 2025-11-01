# SAMS Admin Module - Technical Documentation

## 🏗️ **Architecture Overview**

### **Directory Structure**
```
admin/
├── dashboard.php              # Main admin dashboard with statistics and charts
├── users.php                 # User management (list, filter, delete)
├── add-user.php              # User creation form with role-specific fields
├── edit-user.php             # User modification interface
├── view-user.php             # User details view with complete profile
├── classes.php               # Class management (CRUD operations)
├── subjects.php              # Subject management (CRUD operations)
├── assign-subjects.php       # Teacher-class-subject assignment system
├── reports.php               # Comprehensive attendance reporting system
├── notifications.php         # Email notification system for attendance
├── profile.php               # Admin profile management
├── change-password.php       # Password change functionality
└── README-USER-GUIDE.md      # User documentation
```

### **Core Dependencies**
- **PHP 8.x**: Server-side scripting and business logic
- **MySQL 8.x**: Relational database management
- **Bootstrap 5**: Frontend UI framework with responsive design
- **Chart.js**: Data visualization for attendance analytics
- **Font Awesome**: Icon library for consistent UI elements
- **jQuery**: DOM manipulation and AJAX functionality

---

## 📄 **File-by-File Analysis**

### **1. dashboard.php**
**Purpose**: Central administrative control panel with real-time system overview

**Key Features**:
- **Statistics Cards**: Live counts of students, teachers, classes, subjects
- **Attendance Analytics**: Chart.js bar chart showing 30-day attendance trends
- **Low Attendance Alerts**: Collapsible section highlighting classes below MIN_ATTENDANCE_PERCENTAGE
- **Activity Feed**: Real-time system activity log with user actions and timestamps
- **Quick Actions**: Direct links to common administrative tasks

**Database Queries**:
```sql
-- Statistics Collection
SELECT COUNT(*) as count FROM users WHERE role = 'student'
SELECT COUNT(*) as count FROM users WHERE role = 'teacher'
SELECT COUNT(*) as count FROM classes
SELECT COUNT(*) as count FROM subjects

-- Attendance Analytics (Last 30 Days)
SELECT COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
       COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
       COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count
FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)

-- Low Attendance Classes
SELECT c.name as class_name, 
       ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as attendance_percentage
FROM classes c
LEFT JOIN users u ON u.class_id = c.id
LEFT JOIN attendance a ON a.student_id = u.id
GROUP BY c.id ORDER BY attendance_percentage ASC LIMIT 5

-- Recent Activity Logs
SELECT al.*, u.name FROM activity_logs al 
LEFT JOIN users u ON al.user_id = u.id 
ORDER BY al.date DESC LIMIT 10
```

**JavaScript Components**:
- **Chart.js Integration**: Responsive bar chart with attendance data
- **Collapsible Sections**: Bootstrap collapse for activity feed and alerts
- **Icon Rotation**: Dynamic chevron icons for expand/collapse states

**CSS Enhancements**:
- **Hover Effects**: Transform and shadow animations on statistics cards
- **Gradient Backgrounds**: Modern gradient styling for action buttons
- **Responsive Design**: Mobile-optimized layouts with breakpoint adjustments

---

### **2. users.php**
**Purpose**: Complete user management interface with filtering and CRUD operations

**Core Functionality**:
- **Role-Based Filtering**: URL parameter filtering (?role=admin|teacher|student)
- **Live Search**: jQuery-based real-time search across user data
- **Inline Deletion**: AJAX-powered user deletion with confirmation modals
- **Status Management**: Active/inactive user status with visual indicators

**Database Schema Interactions**:
```sql
-- User Listing with Class Information
SELECT u.*, c.name as class_name 
FROM users u 
LEFT JOIN classes c ON u.class_id = c.id 
WHERE role = ? ORDER BY u.name

-- User Deletion with Cascade Handling
DELETE FROM attendance WHERE student_id = ?  -- For students only
DELETE FROM users WHERE id = ?
```

**Security Features**:
- **Role Verification**: hasRole('admin') middleware protection
- **Self-Deletion Prevention**: Cannot delete own account
- **Input Sanitization**: All user inputs sanitized through sanitize() function
- **SQL Injection Protection**: Prepared statements for all database operations

**UI/UX Elements**:
- **Dynamic Button Groups**: Active/inactive states for role filters
- **Responsive Tables**: Bootstrap table-responsive wrapper
- **Action Button Groups**: Grouped view/edit/delete operations
- **Live Search Integration**: Real-time filtering without page refresh

---

### **3. add-user.php**
**Purpose**: Comprehensive user creation form with role-specific field handling

**Advanced Form Logic**:
- **Dynamic Field Display**: JavaScript-driven conditional field visibility
- **Role-Specific Validation**: Different validation rules for admin/teacher/student
- **Parent Information Handling**: Extended fields for student parent/guardian data
- **Duplicate Prevention**: Database-level checks for unique email and user ID

**Form Validation System**:
```php
// Multi-layered Validation
$errors = [];

// Basic Field Validation
if (empty($name)) $errors[] = 'Name is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';

// Role-Specific Validation
if ($role === 'student' && empty($class_id)) $errors[] = 'Class is required for students';
if ($role === 'student' && empty($roll_number)) $errors[] = 'Roll number is required for students';

// Database Uniqueness Checks
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) $errors[] = 'Email already exists';
```

**Database Operations**:
```sql
-- Student User Creation (Extended Fields)
INSERT INTO users (id, name, email, password, role, class_id, roll_number, 
                   parent_name, parent_phone, parent_email, status, created_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())

-- Admin/Teacher Creation (Basic Fields)
INSERT INTO users (id, name, email, password, role, class_id, status, created_at) 
VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
```

**JavaScript Enhancements**:
- **Real-time Field Toggling**: Show/hide fields based on role selection
- **Password Visibility Toggle**: Eye icon for password field
- **Form Validation**: Client-side validation before submission
- **Dynamic Attribute Management**: Required attribute manipulation

---

### **4. classes.php**
**Purpose**: Class management system with modal-based CRUD operations

**Modal-Driven Interface**:
- **Bootstrap Modal Integration**: Add/Edit operations in overlay modals
- **Inline Messaging**: Success/error messages without page redirects
- **Auto-Modal Opening**: Automatic modal display for edit operations
- **Form State Management**: Persistent form data during edit operations

**Business Logic**:
```php
// Class Creation/Update Logic
if ($class_id > 0) {
    // Update existing class
    $stmt = $db->prepare("UPDATE classes SET name = ?, description = ? WHERE id = ?");
    $stmt->execute([$name, $description, $class_id]);
} else {
    // Create new class
    $stmt = $db->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $description]);
}

// Deletion Safety Check
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE class_id = ?");
$stmt->execute([$class_id]);
$student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($student_count > 0) {
    $errors[] = "Cannot delete class. It has {$student_count} student(s) assigned.";
}
```

**Data Integrity Features**:
- **Cascade Protection**: Prevents deletion of classes with enrolled students
- **Duplicate Name Prevention**: Database-level uniqueness validation
- **Relationship Tracking**: Student and subject count per class
- **Foreign Key Cleanup**: Automatic class_subject relationship cleanup

---

### **5. subjects.php**
**Purpose**: Subject management with assignment tracking and validation

**Similar Architecture to classes.php**:
- **Modal-Based Operations**: Consistent UI pattern with classes management
- **Assignment Tracking**: Displays number of classes and teachers per subject
- **Deletion Protection**: Cannot delete subjects with active assignments

**Database Schema Integration**:
```sql
-- Subject Information with Assignment Counts
SELECT s.*,
       (SELECT COUNT(DISTINCT class_id) FROM class_subject WHERE subject_id = s.id) as class_count,
       (SELECT COUNT(DISTINCT teacher_id) FROM class_subject WHERE subject_id = s.id) as teacher_count
FROM subjects s ORDER BY s.name

-- Assignment Protection Check
SELECT COUNT(*) as count FROM class_subject WHERE subject_id = ?
```

**Unique Features**:
- **Subject Code Management**: Unique identifier system for subjects
- **Description Field**: Optional detailed subject descriptions
- **Assignment Statistics**: Real-time count of class and teacher assignments

---

### **6. assign-subjects.php**
**Purpose**: Teacher-class-subject relationship management system

**Complex Relationship Handling**:
- **Three-Way Relationships**: Links teachers, classes, and subjects
- **Duplicate Prevention**: Validates unique teacher-class-subject combinations
- **Dynamic Dropdowns**: Context-aware selection lists
- **Assignment Tracking**: Complete audit trail of all assignments

**Core Assignment Logic**:
```php
// Assignment Creation with Validation
$stmt = $db->prepare("SELECT id FROM class_subject WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
$stmt->execute([$teacher_id, $class_id, $subject_id]);

if ($stmt->rowCount() > 0) {
    $errors[] = 'This assignment already exists';
} else {
    $stmt = $db->prepare("INSERT INTO class_subject (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
    $stmt->execute([$teacher_id, $class_id, $subject_id]);
}
```

**Database Relationships**:
```sql
-- Complete Assignment View
SELECT cs.id, 
       u.name as teacher_name, 
       c.name as class_name, 
       s.name as subject_name,
       s.code as subject_code
FROM class_subject cs
LEFT JOIN users u ON cs.teacher_id = u.id
LEFT JOIN classes c ON cs.class_id = c.id
LEFT JOIN subjects s ON cs.subject_id = s.id
ORDER BY c.name, s.name
```

**Assignment Business Rules**:
- **One-to-Many**: One teacher can teach multiple subjects
- **Many-to-Many**: Multiple teachers can teach the same subject (different classes)
- **Uniqueness**: Each teacher-class-subject combination must be unique
- **Referential Integrity**: All foreign keys must reference valid records

---

### **7. reports.php**
**Purpose**: Comprehensive attendance reporting system with professional print capabilities

**Report Generation Engine**:
- **Dynamic Filtering**: Class, subject, and date range filters
- **Multiple Report Types**: Summary and detailed attendance reports
- **Professional Formatting**: Print-optimized layouts with school branding
- **Real-time Statistics**: Calculated attendance percentages and summaries

**Advanced SQL Queries**:
```sql
-- Summary Report (Student-wise Statistics)
SELECT u.id as student_id, u.name as student_name, u.roll_number,
       COUNT(a.id) as total_days,
       COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
       COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
       COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
       ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
FROM users u
JOIN classes c ON u.class_id = c.id
LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ? AND a.date BETWEEN ? AND ?
WHERE u.role = 'student' AND u.class_id = ?
GROUP BY u.id ORDER BY u.name

-- Overall Summary Statistics
SELECT COUNT(DISTINCT u.id) as total_students,
       COUNT(a.id) as total_records,
       COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
       COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
       COUNT(CASE WHEN a.status = 'late' THEN 1 END) as total_late,
       ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as overall_percentage
FROM users u JOIN classes c ON u.class_id = c.id
LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ? AND a.date BETWEEN ? AND ?
WHERE u.role = 'student' AND u.class_id = ?
```

**Print System Architecture**:
```javascript
// Enhanced Print Function
function printDetailedReport() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${className} - Attendance Report</title>
            <style>
                /* Print-optimized CSS */
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #000; }
                table { width: 100%; border-collapse: collapse; font-size: 11px; }
                th, td { border: 1px solid #ddd; padding: 6px; }
                .summary-box { background: #f8f9fa; padding: 15px; }
            </style>
        </head>
        <body>
            ${reportContent}
        </body>
        </html>
    `);
}
```

**CSS Print Optimizations**:
```css
@media print {
    .no-print { display: none !important; }
    .container-fluid { margin: 0; padding: 0; }
    .card { border: none !important; page-break-inside: avoid; }
    body { background: white !important; }
    .badge { border: 1px solid #000 !important; }
}
```

---

### **8. notifications.php**
**Purpose**: Automated email notification system for attendance alerts

**Email System Architecture**:
- **Daily Attendance Summaries**: Automated parent notifications
- **Low Attendance Alerts**: Threshold-based warning emails
- **Teacher Notifications**: Class attendance summaries
- **System Alerts**: Administrative notifications

**Email Template Engine**:
```php
function generateAttendanceEmail($student, $attendance_data) {
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background: #007bff; color: white; padding: 20px; }
            .content { padding: 20px; }
            .footer { background: #f8f9fa; padding: 10px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>" . SITE_NAME . " - Daily Attendance Report</h2>
        </div>
        <div class='content'>
            <p>Dear Parent/Guardian,</p>
            <p>Here is today's attendance summary for {$student['name']}:</p>
            <table>
                <tr><th>Subject</th><th>Status</th><th>Time</th></tr>
                {$attendance_rows}
            </table>
        </div>
        <div class='footer'>
            <p>This is an automated message from " . SITE_NAME . "</p>
        </div>
    </body>
    </html>";
    return $html;
}
```

**Notification Scheduling**:
- **Cron Job Integration**: Daily automated email sending
- **Batch Processing**: Efficient bulk email handling
- **Error Handling**: Failed email retry mechanisms
- **Delivery Tracking**: Email status monitoring

---

### **9. profile.php & change-password.php**
**Purpose**: Admin account management and security features

**Profile Management Features**:
- **Personal Information Updates**: Name, email, contact details
- **Password Change Integration**: Secure password update process
- **Activity History**: Track admin actions and login history
- **Account Settings**: System preferences and configurations

**Security Implementation**:
```php
// Password Change Validation
$stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!password_verify($current_password, $admin['password'])) {
    $errors[] = 'Current password is incorrect.';
}

if (strlen($new_password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

if ($new_password !== $confirm_password) {
    $errors[] = 'New password and confirm password do not match.';
}

// Secure Password Update
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([$hashed_password, $admin_id]);
```

---

## 🔧 **Technical Implementation Details**

### **Security Architecture**

**1. Authentication & Authorization**:
```php
// Session-Based Authentication
if (!isLoggedIn()) {
    redirect(URL_ROOT . '/index.php');
}

// Role-Based Access Control
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access';
    redirect(URL_ROOT . '/index.php');
}
```

**2. Input Validation & Sanitization**:
```php
// Multi-layer Input Protection
$name = sanitize($_POST['name']);
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

// SQL Injection Prevention
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

**3. CSRF Protection**:
- Session-based token validation
- Form token verification
- Request method validation

**4. Error Handling**:
```php
try {
    // Database operations
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Database operation failed';
}
```

### **Database Design Patterns**

**1. Relationship Management**:
```sql
-- One-to-Many: Class to Students
users.class_id → classes.id

-- Many-to-Many: Teachers to Subjects
class_subject.teacher_id → users.id
class_subject.subject_id → subjects.id
class_subject.class_id → classes.id

-- Activity Logging
activity_logs.user_id → users.id
```

**2. Data Integrity Constraints**:
- Foreign key relationships
- Unique constraints on email and user ID
- Check constraints for role validation
- Cascade delete rules for related data

**3. Performance Optimizations**:
- Indexed columns for frequent queries
- Optimized JOIN operations
- Pagination for large datasets
- Query result caching

### **Frontend Architecture**

**1. Bootstrap 5 Integration**:
- Responsive grid system
- Component-based UI elements
- Utility-first CSS approach
- Mobile-first responsive design

**2. JavaScript Enhancements**:
- jQuery for DOM manipulation
- Chart.js for data visualization
- Bootstrap JS for interactive components
- Custom JavaScript for form validation

**3. CSS Customizations**:
- Custom CSS variables for theming
- Hover effects and animations
- Print-specific stylesheets
- Mobile-responsive adjustments

### **File Upload & Media Handling**

**1. Profile Image Management**:
- Secure file upload validation
- Image format restrictions
- File size limitations
- Virus scanning integration

**2. Document Management**:
- PDF report generation
- Excel export functionality
- Image optimization
- File storage organization

### **Error Logging & Monitoring**

**1. Activity Logging System**:
```php
function logActivity($action, $details) {
    global $db;
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, date) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $action, $details, $_SERVER['REMOTE_ADDR']]);
}
```

**2. Error Tracking**:
- PHP error logging
- Database error capture
- User action monitoring
- System performance tracking

### **Performance Considerations**

**1. Database Optimization**:
- Query optimization
- Index usage
- Connection pooling
- Query result caching

**2. Frontend Performance**:
- Asset minification
- Image optimization
- Lazy loading
- CDN integration

**3. Caching Strategies**:
- Session-based caching
- Database query caching
- Static file caching
- Browser caching headers

---

## 🚀 **Deployment & Maintenance**

### **System Requirements**
- **PHP 8.0+** with extensions: PDO, MySQLi, mbstring, OpenSSL
- **MySQL 8.0+** or MariaDB 10.5+
- **Apache 2.4+** or Nginx 1.18+
- **SSL Certificate** for HTTPS encryption
- **Email Server** (SMTP) for notifications

### **Configuration Management**
- Environment-specific config files
- Database connection parameters
- Email server settings
- File upload restrictions
- Session configuration

### **Backup & Recovery**
- Daily database backups
- File system snapshots
- Configuration backups
- Recovery procedures
- Data migration scripts

### **Monitoring & Maintenance**
- Error log monitoring
- Performance metrics tracking
- Security audit procedures
- Regular software updates
- Database maintenance tasks

This technical documentation provides complete understanding of the SAMS admin module architecture, implementation details, and operational procedures for effective system management and development.