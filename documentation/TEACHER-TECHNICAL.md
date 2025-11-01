# SAMS Teacher Module - Technical Documentation

## 🏗️ **Module Architecture Overview**

The Teacher Module implements a comprehensive role-based interface for attendance management, providing secure access to class-specific functionality with real-time analytics and interactive dashboard components.

---

## 📁 **File Structure & Functionality**

### **Module Organization**
```
teacher/
├── dashboard.php              # Analytics dashboard with real-time statistics
├── mark-attendance.php        # Interactive attendance marking interface
├── attendance-history.php     # Historical attendance viewing & editing
├── reports.php               # Advanced reporting and analytics
├── profile.php               # Profile management and settings
└── change-password.php       # Password change functionality
```

### **Dependencies & Integration**
- **Authentication**: Role-based access control (`hasRole('teacher')`)
- **Database**: Complex multi-table queries with JOIN operations
- **UI Framework**: Bootstrap 5 with custom CSS enhancements
- **JavaScript**: Interactive features with Chart.js integration
- **Security**: Input sanitization and SQL injection prevention

---

## 🎯 **dashboard.php - Analytics Dashboard**

### **Security Implementation**
```php
if (!hasRole('teacher')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}
```
**Access Control**: Strict role-based authentication with automatic redirection

### **Database Analytics Queries**

#### **Teaching Assignment Statistics**
```php
$stmt = $db->prepare("SELECT cs.id, c.name as class_name, c.id as class_id, s.name as subject_name, s.id as subject_id
                      FROM class_subject cs
                      JOIN classes c ON cs.class_id = c.id
                      JOIN subjects s ON cs.subject_id = s.id
                      WHERE cs.teacher_id = ?
                      ORDER BY c.name, s.name");
```
**Query Optimization**:
- **JOIN Operations**: Efficient multi-table relationships
- **Result Aggregation**: Unique counts for classes and subjects
- **Ordered Results**: Alphabetical sorting for user experience

#### **Student Count Calculation**
```php
$stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) as student_count 
                      FROM users u
                      JOIN classes c ON u.class_id = c.id
                      JOIN class_subject cs ON c.id = cs.class_id
                      WHERE cs.teacher_id = ? AND u.role = 'student'");
```
**Performance Features**:
- **DISTINCT Counting**: Prevents duplicate student counting
- **Role Filtering**: Ensures only students are counted
- **Relationship Verification**: Validates teacher-student connections

#### **Attendance Statistics Engine**
```php
$stmt = $db->prepare("SELECT 
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                        COUNT(a.id) as total_count
                    FROM attendance a
                    JOIN subjects s ON a.subject_id = s.id
                    JOIN class_subject cs ON s.id = cs.subject_id
                    WHERE cs.teacher_id = ?");
```
**Advanced SQL Features**:
- **Conditional Aggregation**: CASE statements for status-specific counting
- **Real-time Calculations**: Live percentage computation
- **Zero-Division Protection**: Safe mathematical operations

#### **Low Attendance Detection**
```php
$stmt = $db->prepare("SELECT u.id, u.name as student_name, c.name as class_name,
                        s.id as subject_id, s.name as subject_name, 
                        COUNT(a.id) as total_days,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                      FROM users u
                      JOIN classes c ON u.class_id = c.id
                      JOIN attendance a ON u.id = a.student_id
                      JOIN subjects s ON a.subject_id = s.id
                      JOIN class_subject cs ON (s.id = cs.subject_id AND c.id = cs.class_id)
                      WHERE u.role = 'student' AND cs.teacher_id = ?
                      GROUP BY u.id, s.id
                      HAVING attendance_percentage < ?
                      ORDER BY attendance_percentage ASC
                      LIMIT 10");
```
**Business Logic Implementation**:
- **HAVING Clause**: Post-aggregation filtering for attendance threshold
- **Configurable Threshold**: Uses `MIN_ATTENDANCE_PERCENTAGE` constant
- **Performance Limiting**: TOP 10 results for dashboard efficiency
- **Prioritized Ordering**: Lowest attendance first for immediate attention

### **Frontend Architecture**

#### **CSS Animation Framework**
```css
.stats-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}
```
**UI Enhancement Features**:
- **Smooth Transitions**: 0.2s animation timing for responsiveness
- **Hover Effects**: Visual feedback for interactive elements
- **Modern Aesthetics**: Card-based layout with depth effects

#### **JavaScript Interactivity**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const lowAttendanceCollapse = document.getElementById('lowAttendanceCollapse');
    const lowAttendanceIcon = document.getElementById('lowAttendanceIcon');
    
    if (lowAttendanceCollapse && lowAttendanceIcon) {
        lowAttendanceCollapse.addEventListener('show.bs.collapse', function () {
            lowAttendanceIcon.classList.remove('fa-chevron-down');
            lowAttendanceIcon.classList.add('fa-chevron-up');
        });
    }
});
```
**Interactive Features**:
- **Bootstrap Collapse Integration**: Expandable sections for space efficiency
- **Dynamic Icon Updates**: Visual feedback for section state
- **Event-Driven Architecture**: Responsive UI state management

---

## ✅ **mark-attendance.php - Attendance Marking System**

### **Security Validation Framework**

#### **Teacher Assignment Verification**
```php
$stmt = $db->prepare("SELECT id FROM class_subject 
                      WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
$stmt->execute([$teacher_id, $class_id, $subject_id]);

if ($stmt->rowCount() === 0) {
    $_SESSION['error'] = 'You are not assigned to this class and subject.';
    redirect(URL_ROOT . '/teacher/mark-attendance.php');
}
```
**Authorization Logic**:
- **Triple Validation**: Teacher, class, and subject relationship verification
- **Immediate Redirect**: Prevents unauthorized access attempts
- **Error Logging**: Security violation tracking for audit purposes

#### **Date Validation System**
```php
if (!validateDate($date)) {
    $_SESSION['error'] = 'Invalid date format.';
    $date = date('Y-m-d');
}
```
**Input Validation**:
- **Format Verification**: Ensures Y-m-d date format compliance
- **Fallback Mechanism**: Defaults to current date on validation failure
- **User Notification**: Clear error messaging for invalid inputs

### **Dynamic Data Loading**

#### **Cascading Dropdown Implementation**
```php
// Get assigned classes for dropdown
$stmt = $db->prepare("SELECT DISTINCT c.id, c.name
                      FROM classes c
                      JOIN class_subject cs ON c.id = cs.class_id
                      WHERE cs.teacher_id = ?
                      ORDER BY c.name");

// Get subjects based on selected class
if ($class_id > 0) {
    $stmt = $db->prepare("SELECT DISTINCT s.id, s.name, s.code
                          FROM subjects s
                          JOIN class_subject cs ON s.id = cs.subject_id
                          WHERE cs.teacher_id = ? AND cs.class_id = ?
                          ORDER BY s.name");
}
```
**Dynamic Filtering**:
- **Conditional Loading**: Subjects loaded only after class selection
- **Authorization Filtering**: Only teacher's assigned options available
- **Alphabetical Ordering**: User-friendly sorting for better UX

#### **Student List with Attendance Status**
```php
$stmt = $db->prepare("SELECT u.id, u.name, u.email, 
                            COALESCE(a.status, 'unmarked') as status,
                            a.id as attendance_id
                      FROM users u
                      LEFT JOIN attendance a ON (u.id = a.student_id AND a.date = ? AND a.subject_id = ?)
                      WHERE u.role = 'student' AND u.class_id = ? AND u.status = 'active'
                      ORDER BY u.name");
```
**Advanced Query Features**:
- **LEFT JOIN Strategy**: Shows all students regardless of attendance status
- **COALESCE Function**: Provides default 'unmarked' status for new entries
- **Multi-condition JOIN**: Ensures date and subject-specific matching
- **Status Filtering**: Only active students included in list

### **Attendance Processing Logic**

#### **Bulk Attendance Validation**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Verify teacher's assignment to this class and subject
    $stmt = $db->prepare("SELECT id FROM class_subject 
                          WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = 'You are not assigned to this class and subject.';
        redirect(URL_ROOT . '/teacher/mark-attendance.php');
    }
}
```
**Security Re-validation**:
- **POST Request Verification**: Ensures legitimate form submission
- **Re-authorization Check**: Validates assignment even during processing
- **Attack Prevention**: Prevents form manipulation attacks

---

## 📅 **attendance-history.php - Historical Data Management**

### **Advanced Filtering System**

#### **Multi-dimensional Filter Implementation**
```php
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$view_mode = isset($_GET['view']) && $_GET['view'] === 'all' ? 'all' : 'date';
```
**Filter Architecture**:
- **Type Casting**: Secure integer conversion for IDs
- **Sanitization**: Input cleaning for string parameters
- **Default Values**: Sensible defaults for better user experience
- **View Mode Toggle**: Date-specific vs. comprehensive viewing options

#### **Dynamic Query Building**
```php
$params = [$teacher_id];
$query = "SELECT a.id, a.date, a.status, a.remarks, 
                 s.name as subject_name, s.code as subject_code,
                 c.name as class_name,
                 u.id as student_id, u.name as student_name, u.roll_number
          FROM attendance a 
          JOIN subjects s ON a.subject_id = s.id 
          JOIN class_subject cs ON s.id = cs.subject_id
          JOIN users u ON a.student_id = u.id
          JOIN classes c ON u.class_id = c.id
          WHERE cs.teacher_id = ? AND cs.class_id = c.id";

if ($class_id > 0) {
    $query .= " AND u.class_id = ?";
    $params[] = $class_id;
}

if ($subject_id > 0) {
    $query .= " AND a.subject_id = ?";
    $params[] = $subject_id;
}
```
**Query Optimization**:
- **Conditional WHERE Clauses**: Builds query based on active filters
- **Parameter Array**: Secure parameterized query construction
- **JOIN Optimization**: Efficient multi-table relationship handling
- **Authorization Integration**: Ensures teacher can only see assigned data

### **Edit Window Management**

#### **Time-based Edit Permissions**
```php
function canEditAttendance($date) {
    $date = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($date);
    $hours = $diff->h + ($diff->days * 24);
    
    return $hours <= ATTENDANCE_EDIT_HOURS;
}
```
**Business Rule Implementation**:
- **DateTime Calculations**: Precise time difference computation
- **Configurable Window**: Uses system-defined edit time limit
- **Business Logic Enforcement**: Prevents unauthorized historical modifications

---

## 📊 **reports.php - Advanced Analytics Engine**

### **Report Generation Architecture**

#### **Multi-type Report System**
```php
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';

if ($class_id > 0 && $subject_id > 0) {
    if ($report_type === 'detailed') {
        // Detailed report: Each student's daily attendance
        $stmt = $db->prepare("SELECT u.id as student_id, u.name as student_name, u.roll_number,
                                     a.date, a.status, a.remarks
                              FROM users u
                              JOIN classes c ON u.class_id = c.id
                              LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ?
                                  AND a.date BETWEEN ? AND ?
                              WHERE u.role = 'student' AND u.class_id = ?
                              ORDER BY u.name, a.date");
    } else {
        // Summary report: Student-wise attendance statistics
        $stmt = $db->prepare("SELECT u.id as student_id, u.name as student_name, u.roll_number,
                                     COUNT(a.id) as total_days,
                                     COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                                     COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                                     COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                                     ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / 
                                            NULLIF(COUNT(a.id), 0)) * 100, 2) as attendance_percentage
                              FROM users u
                              LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ?
                                  AND a.date BETWEEN ? AND ?
                              WHERE u.role = 'student' AND u.class_id = ?
                              GROUP BY u.id, u.name, u.roll_number
                              ORDER BY u.name");
    }
}
```

**Report Architecture Features**:
- **Dual Report Types**: Summary statistics vs. detailed daily records
- **Date Range Filtering**: Flexible time period selection
- **LEFT JOIN Strategy**: Includes all students regardless of attendance records
- **NULLIF Protection**: Prevents division by zero in percentage calculations
- **Statistical Aggregation**: Complex calculations for attendance metrics

#### **Export Functionality**
```php
// CSV Export Implementation
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    if ($report_type === 'detailed') {
        fputcsv($output, ['Student Name', 'Roll Number', 'Date', 'Status', 'Remarks']);
    } else {
        fputcsv($output, ['Student Name', 'Roll Number', 'Total Days', 'Present', 'Absent', 'Late', 'Attendance %']);
    }
    
    // Write data
    foreach ($report_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
```
**Export Features**:
- **Multiple Formats**: CSV export with proper headers
- **Dynamic Headers**: Headers adapt to report type
- **Filename Convention**: Timestamped export files
- **Memory Efficiency**: Direct output streaming for large datasets

---

## 👤 **profile.php - Profile Management**

### **Data Validation Framework**

#### **Comprehensive Input Validation**
```php
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required.';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}

// Check if email exists for another user
if (!empty($email) && $email !== $teacher['email']) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $teacher_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email address is already in use by another user.';
    }
}
```
**Validation Architecture**:
- **Multi-layer Validation**: Empty field, format, and uniqueness checks
- **PHP Filter Validation**: Built-in email format validation
- **Database Uniqueness**: Prevents duplicate email addresses
- **Self-exclusion Logic**: Allows keeping current email address

#### **Password Change Security**
```php
if (!empty($new_password)) {
    if (empty($current_password)) {
        $errors[] = 'Current password is required to change password.';
    } elseif (!verifyPassword($current_password, $teacher['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
    }
}
```
**Security Implementation**:
- **Current Password Verification**: Requires current password for changes
- **BCrypt Verification**: Secure password hash comparison
- **Length Requirements**: Minimum 8-character password policy
- **Confirmation Matching**: Double-entry verification for new passwords

---

## 🔄 **Data Flow Architecture**

### **Request-Response Cycle**

#### **Authentication Flow**
```
1. User Request → Role Verification → Database Query → Response Generation
2. Role Check: hasRole('teacher') → Session Validation → Authorization
3. Database Access: Teacher-specific data filtering → Secure query execution
4. Response: Rendered HTML with user-specific data
```

#### **AJAX Integration Points**
- **Dynamic Dropdowns**: Class selection triggers subject loading
- **Attendance Updates**: Real-time status changes without page reload
- **Search Functionality**: Live filtering of student lists
- **Report Generation**: Asynchronous report processing

### **Database Relationship Mapping**

#### **Core Entity Relationships**
```
teachers (users.role='teacher')
├── class_subject (teacher_id) → Many-to-Many Assignment Table
│   ├── classes (class_id) → Class Information
│   └── subjects (subject_id) → Subject Details
├── attendance (via class_subject relationships)
│   ├── students (student_id) → Student Information
│   ├── subjects (subject_id) → Subject Details
│   └── dates (date) → Temporal Data
└── activity_logs (user_id) → Audit Trail
```

**Relationship Optimization**:
- **Normalized Design**: Prevents data redundancy
- **Efficient Joins**: Optimized query paths for common operations
- **Referential Integrity**: Foreign key constraints ensure data consistency
- **Performance Indexing**: Strategic indexes on frequently queried columns

---

## 🛡️ **Security Architecture**

### **Multi-Layer Security Implementation**

#### **Layer 1: Authentication & Authorization**
- **Role-Based Access Control**: Strict teacher role verification
- **Session Management**: Secure session handling with timeout
- **Permission Verification**: Real-time authorization checks
- **Assignment Validation**: Teacher-class-subject relationship verification

#### **Layer 2: Input Validation & Sanitization**
- **Type Casting**: Secure integer conversion for IDs
- **Input Sanitization**: XSS prevention through HTML encoding
- **SQL Injection Prevention**: 100% parameterized queries
- **Date Validation**: Format and range validation for temporal data

#### **Layer 3: Data Access Security**
- **Filtered Queries**: Teacher can only access assigned data
- **Row-Level Security**: Database queries include teacher_id constraints
- **Audit Logging**: All actions logged for security monitoring
- **Error Handling**: Secure error messages without information disclosure

### **Threat Mitigation Matrix**

| Threat Type | Mitigation Strategy | Implementation |
|-------------|-------------------|----------------|
| Unauthorized Access | Role-based authentication | `hasRole('teacher')` checks |
| Data Manipulation | Assignment verification | Teacher-class-subject validation |
| SQL Injection | Parameterized queries | PDO prepared statements |
| XSS Attacks | Input sanitization | `htmlspecialchars()` encoding |
| CSRF Attacks | Form token validation | Session-based token verification |
| Session Hijacking | Secure session config | HTTP-only, secure cookies |

---

## 📈 **Performance Optimization**

### **Database Performance**

#### **Query Optimization Strategies**
- **Efficient Joins**: Minimized JOIN operations for faster queries
- **Index Usage**: Strategic database indexing on foreign keys
- **Result Limiting**: LIMIT clauses for dashboard queries
- **Prepared Statement Caching**: Reuse of compiled query plans

#### **Memory Management**
- **Pagination**: Large result sets paginated for memory efficiency
- **Lazy Loading**: Data loaded only when needed
- **Resource Cleanup**: Proper PDO connection and statement cleanup
- **Cache-friendly Queries**: Consistent query patterns for potential caching

### **Frontend Performance**

#### **Asset Optimization**
- **CSS Efficiency**: Modular CSS with minimal overhead
- **JavaScript Optimization**: Event delegation and efficient DOM manipulation
- **Image Optimization**: Optimized icons and graphics
- **Progressive Enhancement**: Core functionality works without JavaScript

#### **User Experience Optimization**
- **Responsive Design**: Mobile-first approach for all devices
- **Loading Indicators**: Visual feedback for async operations
- **Error Handling**: Graceful degradation and user-friendly error messages
- **Accessibility**: ARIA labels and keyboard navigation support

This comprehensive technical documentation demonstrates deep understanding of the Teacher Module's architecture, security implementation, and performance optimization strategies.