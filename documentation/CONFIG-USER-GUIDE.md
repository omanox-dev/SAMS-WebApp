# SAMS Configuration - User Guide

## 📋 **Configuration Overview**
The config folder contains the core configuration files that control how the Student Attendance Management System operates. These files define database connections, application settings, security parameters, and system behavior.

## 🔧 **Configuration Files**

### **config.php - Main Application Settings**
This file contains all the essential system-wide settings and constants used throughout the application.

#### **System Settings**
- **Site Name**: "Student Attendance Management System" 
- **Base URL**: Configure your domain/server URL
- **Timezone**: Set to "Asia/Kolkata" (Indian Standard Time)
- **Debug Mode**: Enable/disable debugging features

#### **Security Configuration**
- **Session Security**: HTTP-only cookies, secure transmission settings
- **Login Protection**: Maximum login attempts (5) and lockout time (30 minutes)
- **Error Logging**: Comprehensive error tracking and logging

#### **Attendance Rules**
- **Edit Window**: Teachers can edit attendance within 24 hours
- **Minimum Attendance**: 75% required attendance percentage
- **Attendance Thresholds**: Automatic alerts for low attendance

#### **File Paths**
- **Base Path**: Automatically detects system installation directory
- **Log Directory**: Error logs stored in `/logs/` folder
- **Asset Paths**: Static files (CSS, JS, images) locations

### **database.php - Database Connection**
Manages all database connectivity and connection parameters.

#### **Database Credentials**
- **Host**: Database server location (default: localhost)
- **Database Name**: "attendance_system"
- **Username**: Database user (default: root)
- **Password**: Database password (customize for security)

#### **Connection Features**
- **PDO Integration**: Modern PHP database interface
- **UTF-8 Support**: Proper character encoding
- **Error Handling**: Automatic error logging
- **Connection Security**: Prepared statements to prevent SQL injection

## ⚙️ **Configuration Setup Guide**

### **Initial System Setup**

#### **1. Database Configuration**
```
1. Create a MySQL database named "attendance_system"
2. Import the database schema (SQL file)
3. Update database credentials in database.php:
   - Change username/password from default values
   - Update host if using remote database
   - Modify database name if different
```

#### **2. Application Settings**
```
1. Update URL_ROOT in config.php to match your domain:
   - Local: http://localhost/SAMS
   - Production: https://yourschool.com/attendance
2. Set proper timezone for your location
3. Configure HTTPS settings for production
```

#### **3. Security Configuration**
```
1. Enable HTTPS in production:
   - Set session.cookie_secure to 1
   - Update URL_ROOT to use https://
2. Customize login security:
   - Adjust MAX_LOGIN_ATTEMPTS as needed
   - Modify LOGIN_LOCKOUT_TIME for your requirements
3. Set up proper error logging:
   - Ensure logs/ directory is writable
   - Configure log rotation if needed
```

### **Environment-Specific Settings**

#### **Development Environment**
- **Debug Mode**: Enable (DEBUG_MODE = true)
- **Error Display**: Show errors for debugging
- **HTTPS**: Optional for local development
- **Database**: Local MySQL installation

#### **Production Environment**  
- **Debug Mode**: Disable (DEBUG_MODE = false)
- **Error Display**: Hide errors from users
- **HTTPS**: Required for security
- **Database**: Production database server

#### **Testing Environment**
- **Separate Database**: Use test database
- **Relaxed Security**: Shorter lockout times
- **Enhanced Logging**: Detailed error tracking
- **Mock Settings**: Test-specific configurations

## 🛡️ **Security Best Practices**

### **Database Security**
1. **Strong Credentials**: Use complex database passwords
2. **Limited Privileges**: Database user should have minimal required permissions
3. **Network Security**: Restrict database access to application server only
4. **Regular Backups**: Automated database backup procedures

### **Application Security**
1. **HTTPS Only**: Force secure connections in production
2. **Session Security**: HTTP-only, secure cookies
3. **Error Handling**: Log errors securely, don't expose sensitive information
4. **Input Validation**: All user inputs sanitized and validated

### **File Permissions**
1. **Configuration Files**: Read-only for web server
2. **Log Directory**: Write permissions for error logging
3. **Upload Directories**: Restricted file type uploads
4. **Backup Security**: Secure backup file storage

## 🔍 **Configuration Monitoring**

### **Health Checks**
- **Database Connectivity**: Regular connection tests
- **File Permissions**: Verify proper access rights
- **Log File Sizes**: Monitor and rotate large log files
- **Configuration Validation**: Check for required settings

### **Performance Monitoring**
- **Database Performance**: Query execution times
- **Session Management**: Active session tracking
- **Error Rates**: Monitor application error frequency
- **Resource Usage**: Server resource consumption

### **Security Monitoring**
- **Failed Login Attempts**: Track brute force attempts
- **Configuration Changes**: Log any setting modifications
- **Access Patterns**: Monitor unusual access patterns
- **Error Analysis**: Review security-related errors

## 🔧 **Troubleshooting Common Issues**

### **Database Connection Problems**
```
Error: "Connection error: SQLSTATE[HY000] [1045] Access denied"
Solution: 
1. Verify database credentials in database.php
2. Ensure MySQL server is running
3. Check user permissions in database
4. Confirm database name exists
```

### **Session Issues**
```
Error: User logged out unexpectedly
Solution:
1. Check session settings in config.php
2. Verify server session storage permissions
3. Ensure consistent domain/subdomain usage
4. Check session timeout settings
```

### **File Permission Errors**
```
Error: "Permission denied" for log files
Solution:
1. Set proper permissions on logs/ directory
2. Ensure web server can write to log files
3. Check file ownership settings
4. Verify BASE_PATH is correct
```

### **URL/Path Issues**
```
Error: Assets not loading, broken links
Solution:
1. Verify URL_ROOT matches your domain
2. Check file paths and directory structure
3. Ensure BASE_PATH is properly set
4. Confirm .htaccess configuration
```

## 📊 **Configuration Validation**

### **System Requirements Check**
- **PHP Version**: 7.4+ required
- **MySQL Version**: 5.7+ or MariaDB 10.2+
- **PHP Extensions**: PDO, MySQL, Session support
- **File Permissions**: Proper read/write access

### **Connectivity Tests**
- **Database Connection**: Test database connectivity
- **File System Access**: Verify file read/write operations
- **Session Functionality**: Test session creation and management
- **Error Logging**: Confirm error logging works

### **Security Validation**
- **HTTPS Configuration**: Verify secure connections
- **Session Security**: Test cookie settings
- **Input Sanitization**: Confirm data cleaning functions
- **Access Controls**: Verify role-based permissions

## 💡 **Optimization Tips**

### **Performance Optimization**
1. **Database Connections**: Use connection pooling
2. **Session Storage**: Consider Redis for session storage
3. **Caching**: Implement application-level caching
4. **Asset Optimization**: Minify CSS/JS files

### **Security Hardening**
1. **Configuration Files**: Move outside web root
2. **Database Security**: Use SSL connections
3. **Access Logging**: Enhanced security logging
4. **Regular Updates**: Keep dependencies current

### **Maintenance Procedures**
1. **Regular Backups**: Automated configuration backups
2. **Log Rotation**: Prevent log files from growing too large
3. **Health Monitoring**: Automated system health checks
4. **Documentation**: Keep configuration changes documented

This configuration system provides a robust foundation for the SAMS application with proper security, performance, and maintainability considerations.