# SAMS Includes Module - User Guide

## 🔧 **Shared System Components Overview**

The **includes** folder contains the foundational components that power the entire SAMS application. These shared utilities ensure consistent functionality, security, and user experience across all modules.

---

## 🏗️ **System Architecture Components**

### **📄 Core Files Structure**
```
includes/
├── header.php              # Global navigation & authentication
├── footer.php              # Scripts & copyright footer
├── functions.php           # Core utility functions
├── attendance_functions.php # Attendance-specific helpers
└── load_helpers.php        # Helper file loader
```

---

## 🎯 **Navigation & User Interface**

### **🧭 Dynamic Navigation System**

The system automatically adapts the navigation menu based on your role:

#### **👨‍💼 Administrator Navigation**
- **Dashboard** - System overview and analytics
- **Users Dropdown**
  - All Users - View and manage all system users
  - Add User - Create new accounts
- **Classes & Subjects Dropdown**
  - Manage Classes - Add/edit class information
  - Manage Subjects - Add/edit subject details
  - Assign Subjects - Link subjects to classes and teachers
- **Reports** - Comprehensive system reports

#### **👨‍🏫 Teacher Navigation**
- **Dashboard** - Teaching overview and class insights
- **Mark Attendance** - Daily attendance marking interface
- **Attendance History** - View past attendance records
- **Class Reports** - Generate class-specific reports

#### **👨‍🎓 Student Navigation**
- **Dashboard** - Personal attendance overview
- **My Attendance** - Visual attendance charts and statistics
- **My Reports** - Personal attendance reports

### **🔐 Authentication & Access Control**

#### **Automatic Page Protection**
- **Public Pages**: Login, registration, password reset - accessible to all
- **Protected Pages**: All other pages require authentication
- **Automatic Redirection**: Unauthorized users redirected to login page
- **Role-Based Access**: Content automatically adapts based on user role

#### **Session Management**
- **Secure Sessions**: HTTP-only cookies prevent XSS attacks
- **Automatic Timeout**: Sessions expire for security
- **Cross-Page Persistence**: Login state maintained across all pages

---

## 📊 **Attendance System Features**

### **📈 Real-time Statistics**

#### **Individual Student Analytics**
- **Overall Percentage**: Total attendance across all subjects
- **Subject-wise Breakdown**: Detailed attendance per subject
- **Monthly Trends**: Attendance patterns over time
- **Status Categories**: Present, Absent, Late tracking

#### **Class-level Analytics**
- **Class Attendance Rates**: Overall class performance
- **Subject Comparison**: Attendance patterns by subject
- **Student Rankings**: Attendance-based performance metrics
- **Trend Analysis**: Monthly and weekly patterns

### **🎯 Smart Attendance Tracking**

#### **Flexible Marking System**
- **Multiple Status Options**: Present, Absent, Late
- **Edit Window**: 24-hour correction period (configurable)
- **Bulk Operations**: Mark entire class attendance quickly
- **Date Validation**: Prevents future or invalid date entries

#### **Automatic Calculations**
- **Real-time Percentages**: Instant calculation of attendance rates
- **Low Attendance Alerts**: Automatic flagging below 75% threshold
- **Monthly Summaries**: Automated monthly attendance reports
- **Subject-wise Statistics**: Individual subject performance tracking

---

## 🛡️ **Security & Data Protection**

### **🔒 Data Security Features**

#### **Input Sanitization**
- **XSS Prevention**: All user inputs automatically cleaned
- **SQL Injection Protection**: Prepared statements for database queries
- **HTML Encoding**: Special characters safely encoded
- **Trim & Validation**: Whitespace and format validation

#### **Password Security**
- **Strong Hashing**: BCrypt algorithm with cost factor 12
- **Secure Verification**: Built-in password verification
- **Token Generation**: Secure random tokens for password resets
- **Length Requirements**: Configurable password complexity

### **🔍 Activity Monitoring**

#### **Comprehensive Logging**
- **User Actions**: All user activities tracked with timestamps
- **IP Address Logging**: Source IP recorded for security
- **Error Tracking**: System errors automatically logged
- **Audit Trail**: Complete activity history for compliance

#### **Automatic Error Handling**
- **Silent Error Management**: Errors logged without exposing details
- **User-friendly Messages**: Generic error messages for users
- **Developer Logs**: Detailed error information for troubleshooting
- **System Monitoring**: Performance and error rate tracking

---

## 📅 **Date & Time Management**

### **⏰ Smart Date Handling**

#### **Timezone Configuration**
- **Default Timezone**: Asia/Kolkata (configurable in settings)
- **Consistent Timestamps**: All system times use same timezone
- **Date Formatting**: Multiple format options for different contexts
- **Calendar Integration**: School calendar and holiday support

#### **Business Rules**
- **Weekend Detection**: Automatic weekend identification
- **Holiday Management**: Holiday calendar integration
- **Edit Deadlines**: Time-based editing restrictions
- **Academic Calendar**: School year and term support

---

## 📈 **Reporting & Analytics**

### **📊 Data Export Features**

#### **CSV Generation**
- **Attendance Reports**: Export attendance data to Excel
- **Custom Date Ranges**: Select specific time periods
- **Multiple Formats**: Student-wise, class-wise, subject-wise reports
- **Automated Headers**: Properly formatted CSV files

#### **Visual Analytics**
- **Chart Integration**: Chart.js powered visualizations
- **Trend Analysis**: Monthly and yearly attendance trends
- **Comparative Reports**: Class and subject comparisons
- **Real-time Updates**: Live data visualization

---

## 🎨 **User Experience Features**

### **📱 Responsive Design**

#### **Mobile-First Approach**
- **Bootstrap 5**: Modern responsive framework
- **Touch-Friendly**: Large buttons and touch targets
- **Adaptive Layout**: Optimal viewing on all devices
- **Fast Loading**: Optimized for mobile connections

#### **Interactive Elements**
- **DataTables**: Sortable, searchable tables
- **SweetAlert**: Beautiful confirmation dialogs
- **Auto-dismiss**: Automatic alert message timeout
- **Loading Indicators**: Visual feedback for actions

### **♿ Accessibility Features**

#### **User-Friendly Interface**
- **Clear Navigation**: Intuitive menu structure
- **Status Indicators**: Color-coded attendance status
- **Error Messages**: Clear, actionable error messages
- **Success Confirmations**: Positive feedback for actions

---

## 🔧 **System Utilities**

### **🛠️ Helper Functions**

#### **Data Validation**
- **Date Validation**: Proper date format checking
- **Input Validation**: Alphanumeric and symbol validation
- **Range Checking**: Percentage and number validation
- **Format Verification**: Email and phone number validation

#### **File Operations**
- **CSV Processing**: Import and export CSV files
- **File Upload**: Secure file upload handling
- **Directory Management**: Automatic folder creation
- **File Type Validation**: Secure file type checking

---

## 🚨 **Troubleshooting Guide**

### **Common Issues & Solutions**

#### **Navigation Problems**
- **Issue**: Menu not showing correctly
- **Solution**: Check user login status and role permissions
- **Prevention**: Ensure proper session management

#### **Attendance Calculation Issues**
- **Issue**: Incorrect percentage calculations
- **Solution**: Verify date ranges and status values
- **Prevention**: Use built-in calculation functions

#### **Date/Time Problems**
- **Issue**: Wrong timezone displayed
- **Solution**: Check timezone configuration in config.php
- **Prevention**: Use system date functions consistently

#### **Permission Errors**
- **Issue**: Access denied to certain pages
- **Solution**: Verify user role and login status
- **Prevention**: Use proper authentication checks

### **🔍 Debug Information**

#### **Checking System Status**
- **Login Status**: Use `isLoggedIn()` function
- **User Role**: Check with `hasRole('role_name')`
- **Session Data**: Verify `$_SESSION` variables
- **Database Connection**: Test database connectivity

#### **Error Logging**
- **Application Logs**: Check `/logs/app_error.log`
- **System Logs**: Review `/logs/error.log`
- **Activity Logs**: Database activity_logs table
- **Debug Mode**: Enable in config.php for development

---

## 📞 **Support & Best Practices**

### **🎯 Usage Recommendations**

#### **For Administrators**
- **Regular Monitoring**: Check error logs weekly
- **User Management**: Regular user activity review
- **System Updates**: Keep system components updated
- **Backup Procedures**: Regular database backups

#### **For Teachers**
- **Daily Attendance**: Mark attendance promptly
- **Correction Window**: Use 24-hour edit window for corrections
- **Report Generation**: Generate regular class reports
- **Student Monitoring**: Track low attendance students

#### **For Students**
- **Regular Checking**: Monitor attendance regularly
- **Report Access**: Download monthly reports
- **Profile Updates**: Keep profile information current
- **Password Security**: Use strong passwords

### **🛡️ Security Best Practices**

- **Strong Passwords**: Use complex passwords with mixed characters
- **Regular Logout**: Log out when session complete
- **Secure Access**: Access system from trusted devices only
- **Report Issues**: Report suspicious activity immediately

This user guide provides comprehensive understanding of the shared system components that make SAMS a robust and user-friendly attendance management solution.