# SAMS Root Level Files - User Guide

## 🏠 **System Entry Points & Utilities**

The root level files serve as the main entry points and utility tools for the SAMS application. This guide covers the core system files that handle authentication, system management, and application initialization.

---

## 🔐 **Login System (index.php)**

### **🎯 Main Application Entry Point**

The `index.php` file serves as the primary gateway to the SAMS system, handling user authentication and role-based redirection.

#### **Login Process**
1. **Access the System** - Navigate to your SAMS URL
2. **Enter Credentials** - Provide your email and password
3. **Automatic Redirection** - System redirects you based on your role:
   - **Administrators** → Admin Dashboard
   - **Teachers** → Teacher Dashboard  
   - **Students** → Student Dashboard

#### **Login Features**
- **Secure Authentication** - BCrypt password verification
- **Role-Based Access** - Automatic dashboard routing
- **Remember Me** - Optional session persistence
- **Error Handling** - Clear error messages for login issues
- **Account Status Check** - Verifies active account status

### **🔧 User Interface Features**

#### **Responsive Design**
- **Mobile-Friendly** - Works on all device sizes
- **Bootstrap 5** - Modern, professional interface
- **Font Awesome Icons** - Clear visual indicators
- **Accessibility** - Form validation and screen reader support

#### **Security Features**
- **Password Visibility Toggle** - Show/hide password option
- **Form Validation** - Client and server-side validation
- **Anti-CSRF Protection** - Secure form submission
- **Session Security** - Secure session management

---

## 🚪 **Logout System (logout.php)**

### **🔒 Secure Session Termination**

The logout system ensures complete and secure session cleanup when users end their session.

#### **Logout Process**
1. **Click Logout** - From any dashboard or page
2. **Session Cleanup** - All session data cleared
3. **Cookie Removal** - Session cookies deleted
4. **Redirect to Login** - Return to main login page

#### **Security Features**
- **Complete Session Destruction** - All session variables cleared
- **Cookie Cleanup** - Session cookies properly removed
- **Secure Redirect** - Returns to login page
- **No Data Retention** - No sensitive data left in browser

---

## 🛠️ **System Administration Tools**

### **🔧 Database Check (db_check.php)**

#### **Purpose**
System diagnostic tool for checking database connectivity and table structure.

#### **Features**
- **Database Connection Test** - Verifies database connectivity
- **Table Existence Check** - Ensures required tables exist
- **Table Creation** - Creates missing tables automatically
- **Admin User Verification** - Checks if admin account exists
- **Password Hash Generation** - Provides secure password hashes

#### **Usage Instructions**
1. **Access the Tool** - Navigate to `/db_check.php`
2. **Review Results** - Check database status messages
3. **Copy Password Hash** - Use provided hash for admin account
4. **Create Admin User** - Add admin user to database if needed

#### **Troubleshooting**
- **Connection Errors** - Check database credentials in config
- **Missing Tables** - Tool automatically creates required tables
- **Admin Access** - Use generated hash to create admin account

### **🔑 Admin Hash Generator (admin_hash.php)**

#### **Purpose**
Simple utility for generating secure password hashes for administrator accounts.

#### **Usage**
1. **Run the Script** - Access `/admin_hash.php`
2. **Copy the Hash** - Copy the generated password hash
3. **Update Database** - Use hash in admin user password field
4. **Login** - Use original password to login

#### **Security Notes**
- **BCrypt Algorithm** - Uses secure BCrypt hashing
- **Cost Factor 12** - High security with balanced performance
- **One-Time Use** - Generate hash and remove/protect file
- **Default Password** - Change default 'admin123' password immediately

### **📊 System Test (test.php)**

#### **Purpose**
PHP environment diagnostic tool using `phpinfo()` function.

#### **Information Provided**
- **PHP Version** - Current PHP version and configuration
- **Extensions** - Loaded PHP extensions and modules
- **Server Information** - Web server details and settings
- **Database Support** - PDO and MySQL extension status

#### **Security Warning**
⚠️ **IMPORTANT**: Remove or restrict access to this file in production environments as it exposes sensitive system information.

---

## 📋 **Access Guidelines**

### **👥 User Roles & Access**

#### **Administrator Access**
- **Full System Access** - All modules and features
- **User Management** - Create and manage all user accounts
- **System Configuration** - Access to all settings
- **Reports & Analytics** - System-wide reporting capabilities

#### **Teacher Access**
- **Teaching Modules** - Attendance marking and class management
- **Assigned Classes** - Only classes assigned by administrator
- **Reports** - Class and subject-specific reports
- **Profile Management** - Personal profile updates

#### **Student Access**
- **Personal Dashboard** - Individual attendance overview
- **Attendance History** - Personal attendance records
- **Reports** - Personal attendance reports
- **Profile Management** - Limited profile updates

### **🔐 Security Best Practices**

#### **Password Security**
- **Strong Passwords** - Use complex passwords with mixed characters
- **Regular Updates** - Change passwords periodically
- **Unique Passwords** - Don't reuse passwords from other systems
- **Secure Storage** - Never share or write down passwords

#### **Session Security**
- **Proper Logout** - Always logout when finished
- **Shared Computers** - Don't use "Remember Me" on shared devices
- **Session Timeout** - Sessions automatically expire for security
- **Browser Security** - Keep browser updated and secure

---

## 🚨 **Troubleshooting Guide**

### **🔧 Login Issues**

#### **Cannot Login**
- **Check Credentials** - Verify email and password are correct
- **Account Status** - Ensure account is active (contact admin)
- **Browser Issues** - Clear cache and cookies
- **JavaScript Enabled** - Ensure JavaScript is enabled

#### **Forgot Password**
- **Contact Administrator** - Request password reset
- **Verify Email** - Ensure email address is correct
- **Account Recovery** - Administrator can reset your password
- **New Account** - May need new account if email changed

#### **Account Locked**
- **Too Many Attempts** - Account may be temporarily locked
- **Wait Period** - Wait before attempting login again
- **Contact Support** - Administrator can unlock account
- **Reset Password** - May require password reset

### **🛠️ System Access Issues**

#### **Page Not Loading**
- **Network Connection** - Check internet connectivity
- **Server Status** - Verify server is running
- **URL Correct** - Ensure correct website address
- **Browser Compatibility** - Use modern, updated browser

#### **Missing Features**
- **Role Permissions** - Check if you have access to the feature
- **Browser Compatibility** - Update browser to latest version
- **JavaScript Enabled** - Ensure JavaScript is not blocked
- **Page Refresh** - Try refreshing the page

#### **Data Not Displaying**
- **Database Connection** - May be database connectivity issue
- **Page Refresh** - Try refreshing the page
- **Clear Cache** - Clear browser cache and cookies
- **Contact Support** - Report persistent issues to administrator

---

## 📞 **Support & Maintenance**

### **🎯 Getting Help**

#### **For Students**
- **Contact Teachers** - For attendance or academic issues
- **Student Services** - For account or access problems
- **IT Support** - For technical difficulties
- **User Manual** - Reference documentation and guides

#### **For Teachers**
- **IT Support** - For technical issues and system problems
- **Administrator** - For account permissions and assignments
- **Training Resources** - Additional training materials
- **Peer Support** - Collaborate with other teachers

#### **For Administrators**
- **System Documentation** - Technical guides and manuals
- **Database Management** - Database administration guides
- **Security Guidelines** - Security best practices
- **Vendor Support** - Contact system developers if needed

### **🔧 Maintenance Tasks**

#### **Regular Tasks**
- **Password Updates** - Change passwords regularly
- **Profile Reviews** - Keep profile information current
- **Data Backup** - Ensure regular system backups
- **Security Monitoring** - Monitor for unusual activity

#### **System Health**
- **Database Checks** - Run db_check.php periodically
- **Log Monitoring** - Review error logs regularly
- **Performance Monitoring** - Check system performance
- **Security Updates** - Keep system updated

---

## 📊 **System Requirements**

### **💻 Technical Requirements**

#### **Server Requirements**
- **PHP Version** - PHP 8.0 or higher
- **Database** - MySQL 5.7 or higher
- **Web Server** - Apache or Nginx
- **Extensions** - PDO, MySQL, mbstring

#### **Browser Requirements**
- **Modern Browsers** - Chrome, Firefox, Safari, Edge
- **JavaScript** - Must be enabled
- **Cookies** - Must be enabled for sessions
- **CSS3 Support** - For proper styling

#### **Network Requirements**
- **Internet Connection** - Required for system access
- **HTTPS** - Recommended for production environments
- **Firewall** - Configure appropriate access rules
- **Backup** - Regular backup procedures

This user guide provides comprehensive information for accessing and using the core SAMS system files, ensuring users can successfully authenticate and navigate the system while maintaining security best practices.