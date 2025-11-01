# SAMS Student Module - Technical Documentation

## 🏗️ **Module Architecture Overview**

The Student Module implements a read-only, analytics-focused interface designed for students to monitor their attendance performance, generate reports, and manage personal profiles. The architecture emphasizes data visualization, user experience, and secure access to student-specific information.

---

## 📁 **File Structure & Functionality**

### **Module Organization**
```
student/
├── dashboard.php           # Comprehensive analytics dashboard with charts
├── subject-charts.php      # Subject-specific attendance visualization
├── view-attendance.php     # Detailed attendance history table
├── report.php             # Report generation and export functionality
├── reports.php            # Alias wrapper for backward compatibility
└── profile.php            # Profile management and password change
```

### **Architecture Characteristics**
- **Read-Only Nature**: Students cannot modify attendance data
- **Visualization Focus**: Heavy emphasis on charts and graphical representations
- **Personal Scope**: All queries filtered by student_id for data isolation
- **Mobile-First Design**: Responsive interface optimized for student devices
- **Real-time Analytics**: Live calculation of attendance percentages and trends

---

## 🎯 **dashboard.php - Student Analytics Dashboard**

### **Security Implementation**
```php
if (!hasRole('student')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}
```
**Access Control**: Strict role-based authentication with automatic redirection

### **CSS Framework & Animation System**

#### **Modern Card-Based Design**
```css
.student-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.student-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.profile-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
}
```
**Design Features**:
- **Gradient Backgrounds**: Modern visual appeal with CSS gradients
- **Smooth Transitions**: 0.3s ease transitions for hover effects
- **Card Elevation**: Box-shadow depth effects for material design
- **Border Radius**: 16px rounded corners for modern aesthetics

#### **Interactive Statistics Cards**
```css
.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.stats-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 1rem;
}
```
**Interactive Elements**:
- **Hover Animations**: Transform and shadow effects on interaction
- **Icon Standardization**: Consistent 56px icon containers
- **Flexbox Centering**: Perfect alignment for icons and text
- **Color Coding**: Visual differentiation for different statistics

### **Database Query Architecture**

#### **Student Profile Data Retrieval**
```php
$stmt = $db->prepare("SELECT u.*, c.name as class_name, c.id as class_id
                      FROM users u
                      LEFT JOIN classes c ON u.class_id = c.id
                      WHERE u.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
```
**Query Optimization**:
- **LEFT JOIN Strategy**: Ensures student data retrieved even without class assignment
- **Single Query**: Efficient data retrieval with minimal database calls
- **Security Filtering**: Student ID parameter prevents unauthorized access

#### **Overall Attendance Statistics**
```php
$stmt = $db->prepare("SELECT 
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                        COUNT(*) as total_count
                      FROM attendance 
                      WHERE student_id = ?");
```
**Statistical Analysis**:
- **Conditional Aggregation**: CASE statements for status-specific counting
- **Single Query Efficiency**: All statistics calculated in one database call
- **Real-time Calculations**: Live percentage computation in PHP

#### **Recent Attendance History**
```php
$stmt = $db->prepare("SELECT a.date, a.status, s.name as subject_name, s.code as subject_code
                      FROM attendance a
                      JOIN subjects s ON a.subject_id = s.id
                      WHERE a.student_id = ?
                      ORDER BY a.date DESC
                      LIMIT 10");
```
**Performance Features**:
- **LIMIT Clause**: Restricts results for dashboard performance
- **Ordered Results**: Most recent attendance first
- **JOIN Efficiency**: Single query for attendance and subject data

---

## 📊 **subject-charts.php - Subject Visualization Engine**

### **Subject-wise Analytics Query**
```php
$stmt = $db->prepare("SELECT s.id, s.name as subject_name, s.code as subject_code,
                        COUNT(a.id) as total_days,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                        ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                      FROM attendance a
                      JOIN subjects s ON a.subject_id = s.id
                      WHERE a.student_id = ?
                      GROUP BY s.id, s.name, s.code
                      ORDER BY s.name");
```

**Advanced SQL Features**:
- **Complex Aggregation**: Multiple CASE statements for comprehensive statistics
- **Percentage Calculation**: Real-time percentage computation in SQL
- **GROUP BY Optimization**: Efficient grouping for subject-wise analysis
- **Alphabetical Ordering**: User-friendly subject sorting

### **Chart Visualization Architecture**

#### **Subject Performance Cards**
```php
foreach ($subject_attendance as $index => $subject): ?>
    <div class="col-md-6 mb-3">
        <div class="card subject-strip">
            <div class="card-body p-3">
                <div class="row align-items-center">
                    <div class="col-4">
                        <h6 class="mb-1 fw-bold"><?php echo $subject['subject_name']; ?></h6>
                        <small class="text-muted"><?php echo $subject['subject_code']; ?></small>
                    </div>
                    <div class="col-5">
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" style="width: <?php echo $subject['attendance_percentage']; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-3 text-end">
                        <span class="fw-bold"><?php echo $subject['attendance_percentage']; ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach;
```

**Visualization Features**:
- **Bootstrap Grid**: Responsive 2-column layout for optimal viewing
- **Progress Bars**: Visual representation of attendance percentages
- **Dynamic Styling**: Progress bar width calculated from attendance data
- **Information Hierarchy**: Subject name, code, and percentage clearly displayed

#### **Color-Coded Performance Indicators**
```php
// CSS classes based on attendance percentage
function getAttendanceClass($percentage) {
    if ($percentage >= 90) return 'bg-success';
    elseif ($percentage >= 75) return 'bg-warning';
    else return 'bg-danger';
}
```
**Performance Classification**:
- **Green (90%+)**: Excellent attendance performance
- **Yellow (75-89%)**: Good attendance, room for improvement
- **Red (<75%)**: Poor attendance, requires attention

---

## 📅 **view-attendance.php - Detailed History Interface**

### **Advanced Filtering System**

#### **Multi-dimensional Filter Implementation**
```php
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query based on filters
$params = [$student_id];
$query = "SELECT a.id, a.date, a.status, a.remarks, 
                 s.name as subject_name, s.code as subject_code 
          FROM attendance a 
          JOIN subjects s ON a.subject_id = s.id 
          WHERE a.student_id = ?";

if ($subject_id > 0) {
    $query .= " AND a.subject_id = ?";
    $params[] = $subject_id;
}

if ($month) {
    $query .= " AND DATE_FORMAT(a.date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}
```

**Dynamic Query Construction**:
- **Conditional WHERE Clauses**: Filters applied only when specified
- **Parameter Array Management**: Secure parameterized query building
- **Date Format Functions**: SQL date formatting for month filtering
- **Security Validation**: Input sanitization for all filter parameters

#### **Subject Dropdown Population**
```php
$stmt = $db->prepare("SELECT s.id, s.name, s.code 
                      FROM subjects s 
                      JOIN class_subject cs ON s.id = cs.subject_id 
                      WHERE cs.class_id = ? 
                      ORDER BY s.name");
$stmt->execute([$student['class_id']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
```
**Authorization-Based Filtering**:
- **Class-based Subject Access**: Only subjects available to student's class
- **JOIN Operation**: Links subjects through class_subject assignment table
- **Alphabetical Ordering**: User-friendly dropdown organization

---

## 📊 **report.php - Report Generation Engine**

### **Report Parameter Processing**

#### **Input Validation & Sanitization**
```php
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to   = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

// Validate dates (fallback if invalid)
if (!validateDate($date_from)) { $date_from = date('Y-m-01'); }
if (!validateDate($date_to)) { $date_to = date('Y-m-d'); }
```
**Input Security**:
- **Type Casting**: Secure integer conversion for subject ID
- **Date Validation**: Custom validation function for date format
- **Fallback Mechanisms**: Default values for invalid inputs
- **Sanitization**: XSS prevention for string inputs

#### **Dynamic Report Query**
```php
$query = "SELECT a.date, a.status, s.name AS subject_name, s.code AS subject_code, s.id AS subject_id
          FROM attendance a
          JOIN subjects s ON a.subject_id = s.id
          WHERE a.student_id = ? AND a.date BETWEEN ? AND ?";
$params = [$student_id, $date_from, $date_to];

if ($subject_id > 0) {
    $query .= " AND s.id = ?";
    $params[] = $subject_id;
}
$query .= " ORDER BY a.date DESC, s.name";
```
**Query Features**:
- **Date Range Filtering**: BETWEEN clause for flexible time periods
- **Optional Subject Filter**: Conditional subject-specific filtering
- **Ordered Results**: Chronological and alphabetical ordering
- **Security Scope**: Always filtered by student_id for data isolation

#### **Statistical Summary Calculation**
```php
$summary = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0,
    'percentage' => 0
];

if ($attendance_rows) {
    foreach ($attendance_rows as $r) {
        $summary['total']++;
        if ($r['status'] === 'present') $summary['present']++;
        elseif ($r['status'] === 'absent') $summary['absent']++;
        elseif ($r['status'] === 'late') $summary['late']++;
    }
    if ($summary['total'] > 0) {
        $summary['percentage'] = round(($summary['present'] / $summary['total']) * 100, 2);
    }
}
```
**Summary Features**:
- **Real-time Calculation**: Statistics computed from query results
- **Status Categorization**: Separate counting for each attendance status
- **Percentage Accuracy**: 2-decimal precision for attendance percentage
- **Zero Division Protection**: Prevents division by zero errors

---

## 👤 **profile.php - Profile Management**

### **Profile Data Retrieval**
```php
$stmt = $db->prepare("SELECT u.*, c.name as class_name 
                      FROM users u 
                      LEFT JOIN classes c ON u.class_id = c.id 
                      WHERE u.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
```
**Data Architecture**:
- **LEFT JOIN Strategy**: Handles students without class assignments
- **Complete Profile**: User data combined with class information
- **Security Filtering**: Student ID ensures data isolation

### **Profile Update Validation**

#### **Email Uniqueness Verification**
```php
if (!empty($email) && $email !== $student['email']) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $student_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email address is already in use by another user.';
    }
}
```
**Validation Logic**:
- **Uniqueness Check**: Prevents duplicate email addresses
- **Self-Exclusion**: Allows keeping current email address
- **Database Verification**: Real-time duplicate detection

#### **Password Change Security**
```php
if (!empty($new_password)) {
    if (empty($current_password)) {
        $errors[] = 'Current password is required to change password.';
    } elseif (!verifyPassword($current_password, $student['password'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
    }
}
```
**Security Framework**:
- **Current Password Verification**: BCrypt hash comparison
- **Length Requirements**: Minimum 8-character password policy
- **Confirmation Matching**: Double-entry verification system
- **Error Accumulation**: Comprehensive validation error collection

---

## 🔄 **Data Flow Architecture**

### **Student-Centric Data Model**

#### **Data Access Pattern**
```
Student Login → Role Verification → Student ID Extraction → Data Filtering
├── Dashboard Queries: All data filtered by student_id
├── Subject Charts: Attendance grouped by subject for student
├── Attendance History: Chronological records for student
├── Reports: Date-range filtered data for student
└── Profile: Personal information for student only
```

#### **Security Boundaries**
- **Horizontal Access Control**: Students can only access their own data
- **Query-level Filtering**: Every database query includes student_id constraint
- **Session-based Identity**: Student ID extracted from session, not URL parameters
- **Read-only Operations**: Students cannot modify attendance records

### **Mobile-First Responsive Architecture**

#### **Responsive Design Implementation**
```css
@media (max-width: 768px) {
    .stats-number {
        font-size: 1.5rem;
    }
    
    .chart-container {
        height: 200px;
    }
    
    .subject-card {
        margin-bottom: 0.5rem;
    }
}
```
**Mobile Optimization**:
- **Breakpoint Strategy**: Tablet and mobile-specific layouts
- **Touch-Friendly**: Larger touch targets for mobile devices
- **Performance Optimization**: Reduced chart sizes for mobile
- **Content Prioritization**: Most important information prominently displayed

---

## 📊 **Chart Integration & Visualization**

### **Chart.js Implementation**

#### **Subject Performance Charts**
```javascript
// Chart configuration for subject-wise attendance
const chartConfig = {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent', 'Late'],
        datasets: [{
            data: [present_days, absent_days, late_days],
            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
};
```
**Visualization Features**:
- **Doughnut Charts**: Modern circular progress visualization
- **Color Coding**: Consistent colors for attendance statuses
- **Responsive Design**: Charts adapt to container size
- **Interactive Elements**: Hover effects and tooltips

#### **Trend Analysis Charts**
```javascript
// Line chart for attendance trends over time
const trendConfig = {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Attendance Percentage',
            data: attendanceData,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
};
```
**Trend Analysis**:
- **Time Series Visualization**: Month-over-month attendance trends
- **Percentage Scale**: Y-axis scaled from 0-100%
- **Smooth Curves**: Tension setting for smooth line curves
- **Interactive Tooltips**: Detailed information on hover

---

## 🛡️ **Security Implementation**

### **Student-Specific Security Model**

#### **Data Isolation Strategy**
- **Session-based Identity**: Student ID from authenticated session
- **Query-level Security**: All queries include student_id constraints
- **Read-only Access**: No data modification capabilities
- **Horizontal Privilege Separation**: Students cannot access other students' data

#### **Input Validation Framework**
```php
// Comprehensive input validation for report parameters
function validateReportInputs($subject_id, $date_from, $date_to) {
    $errors = [];
    
    // Validate subject ID
    if ($subject_id < 0) {
        $errors[] = 'Invalid subject selection';
    }
    
    // Validate date range
    if (!validateDate($date_from) || !validateDate($date_to)) {
        $errors[] = 'Invalid date format';
    }
    
    // Validate logical date range
    if (strtotime($date_from) > strtotime($date_to)) {
        $errors[] = 'Start date cannot be after end date';
    }
    
    return $errors;
}
```

**Validation Architecture**:
- **Type Validation**: Ensures proper data types for all inputs
- **Range Validation**: Logical validation for date ranges
- **Format Validation**: Date format and structure verification
- **Business Logic Validation**: Application-specific rule enforcement

---

## 📈 **Performance Optimization**

### **Database Performance**

#### **Query Optimization Strategies**
- **Index Usage**: Leverages indexes on student_id, date, and subject_id
- **JOIN Optimization**: Efficient table relationships for data retrieval
- **Result Limiting**: Appropriate LIMIT clauses for dashboard queries
- **Aggregation Efficiency**: Single queries for statistical calculations

#### **Caching Opportunities**
- **Subject Lists**: Class-specific subject lists rarely change
- **Statistical Summaries**: Attendance percentages could be cached
- **Profile Data**: User profile information updates infrequently
- **Chart Data**: Subject-wise statistics suitable for caching

### **Frontend Performance**

#### **Asset Optimization**
- **Minified CSS**: Compressed stylesheets for faster loading
- **Optimized Images**: Compressed icons and graphics
- **Lazy Loading**: Charts loaded only when visible
- **Progressive Enhancement**: Core functionality works without JavaScript

#### **Mobile Performance**
- **Reduced Chart Complexity**: Simplified charts for mobile devices
- **Touch Optimization**: Large touch targets and gestures
- **Data Compression**: Minimal data transfer for mobile connections
- **Progressive Loading**: Critical content loads first

This comprehensive technical documentation demonstrates the sophisticated architecture underlying the Student Module, showcasing secure data access, advanced visualization capabilities, and optimized performance for educational attendance monitoring.