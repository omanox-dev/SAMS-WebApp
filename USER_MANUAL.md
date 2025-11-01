# SAMS - Student Attendance Management System
## User Manual

### Table of Contents
1. [Getting Started](#getting-started)
2. [User Roles](#user-roles)
3. [Admin Functions](#admin-functions)
4. [Teacher Functions](#teacher-functions)
5. [Student Functions](#student-functions)
6. [Common Features](#common-features)
7. [Troubleshooting](#troubleshooting)

---

## Getting Started

### System Requirements
- Web server with PHP 8.x
- MySQL database
- Modern web browser (Chrome, Firefox, Safari, Edge)

### First Time Setup
1. Navigate to your SAMS installation URL
2. Login with the default admin credentials provided by your system administrator
3. Change your default password immediately after first login

### Login Process
1. Enter your email and password on the login page
2. Click "Login" button
3. You'll be redirected to your role-specific dashboard

---

## User Roles

The system has three main user roles:

### 1. Admin
- Full system access
- User management
- Subject and class management
- System configuration

### 2. Teacher
- Attendance management for assigned subjects
- View student lists
- Update personal profile

### 3. Student
- View personal attendance records
- Update personal profile
- View class schedules

---

## Admin Functions

### Dashboard
The admin dashboard provides an overview of:
- Total students, teachers, and subjects
- Recent activity
- Quick access to main functions

### User Management

#### Adding Users
1. Navigate to **Users** → **Add User**
2. Fill in the required information:
   - **Name**: Full name of the user
   - **Email**: Must be unique in the system
   - **Phone**: Contact number
   - **Address**: Physical address
   - **Role**: Select Admin, Teacher, or Student
   - **Status**: Active or Inactive
   - **For Students**: Enter roll number
3. Click **Add User**
4. Success message will appear without page redirect

#### Viewing Users
1. Go to **Users** → **Manage Users**
2. Use the search and filter options to find specific users
3. Click **View** to see detailed user information
4. Click **Edit** to modify user details
5. Click **Delete** to remove users (with confirmation)

#### Editing Users
1. From the users list, click **Edit** next to any user
2. Modify the required fields
3. Click **Update User**
4. Success message appears inline without redirect

### Subject Management

#### Adding Subjects
1. Navigate to **Subjects** → **Add Subject**
2. Enter subject details:
   - **Subject Name**: Name of the subject
   - **Subject Code**: Unique code for the subject
   - **Description**: Brief description
3. Click **Add Subject**

#### Managing Subjects
1. Go to **Subjects** → **Manage Subjects**
2. View all subjects in a table format
3. Edit or delete subjects as needed
4. All updates show inline messages

### Class Management

#### Adding Classes
1. Navigate to **Classes** → **Add Class**
2. Enter class information:
   - **Class Name**: Name of the class
   - **Section**: Class section (if applicable)
   - **Description**: Additional details
3. Click **Add Class**

#### Managing Classes
1. Go to **Classes** → **Manage Classes**
2. View, edit, or delete classes
3. Inline feedback for all operations

### Subject Assignment

#### Assigning Subjects to Teachers
1. Navigate to **Assign Subjects**
2. Select a teacher from the dropdown
3. Select a subject from the dropdown
4. Click **Assign Subject**
5. View current assignments in the table below
6. Use **Edit** to modify existing assignments inline
7. Use **Remove** to delete assignments

### Profile Management
1. Click on your name in the top-right corner
2. Select **Profile** from the dropdown
3. Update your personal information
4. Change password if needed
5. Click **Update Profile**
6. Success message appears without page redirect

---

## Teacher Functions

### Dashboard
Teacher dashboard shows:
- Assigned subjects
- Recent attendance records
- Quick navigation to main functions

### Attendance Management

#### Taking Attendance
1. Navigate to **Attendance** → **Take Attendance**
2. Select the subject you're teaching
3. Choose the date (defaults to today)
4. Mark each student as:
   - **Present**: Student attended class
   - **Absent**: Student was absent
   - **Late**: Student arrived late
5. Click **Submit Attendance**
6. Success message appears inline

#### Viewing Attendance History
1. Go to **Attendance** → **Attendance History**
2. Filter by:
   - Subject
   - Date range
   - Student
3. Edit attendance records if needed
4. Updates show inline feedback

### Student Management
1. Navigate to **Students**
2. View students assigned to your subjects
3. See student contact information
4. View individual student attendance records

### Profile Management
1. Access profile from the top-right menu
2. Update personal information
3. Change password
4. Save changes (no page redirect)

---

## Student Functions

### Dashboard
Student dashboard displays:
- Personal attendance summary
- Enrolled subjects
- Recent attendance records

### Viewing Attendance
1. Navigate to **My Attendance**
2. View attendance records by subject
3. See attendance percentage
4. Filter by date range or subject

### Profile Management
1. Click on your name in the top-right
2. Select **Profile**
3. Update personal information:
   - Name
   - Email
   - Phone
   - Address
4. Change password if needed
5. Click **Update Profile**
6. Confirmation appears inline

---

## Common Features

### Navigation
- **Top Navigation**: Role-specific menu items
- **Breadcrumbs**: Shows current page location
- **Dashboard Link**: Always accessible from any page

### Search and Filtering
- Most data tables include search functionality
- Use filters to narrow down results
- Pagination for large datasets

### Responsive Design
- System works on desktop, tablet, and mobile devices
- Adaptive layout based on screen size

### Notifications
- Success messages appear in green alerts
- Error messages appear in red alerts
- All messages are dismissible
- No page redirects after form submissions

### Password Security
- Passwords are encrypted
- Minimum password requirements enforced
- Password change functionality available

---

## Troubleshooting

### Common Issues

#### Cannot Login
- **Check credentials**: Verify email and password
- **Account status**: Ensure account is active
- **Contact admin**: If issues persist

#### Page Not Loading
- **Refresh browser**: Try F5 or Ctrl+R
- **Clear cache**: Clear browser cache and cookies
- **Check connection**: Verify internet connectivity

#### Form Submission Issues
- **Required fields**: Ensure all required fields are filled
- **Email format**: Use valid email format
- **Unique values**: Email addresses must be unique

#### Attendance Issues
- **Date selection**: Ensure correct date is selected
- **Subject assignment**: Verify teacher is assigned to subject
- **Student enrollment**: Check if students are enrolled in the subject

### Error Messages

#### "Unauthorized access"
- You don't have permission for this page
- Contact administrator for role clarification

#### "Email already exists"
- Choose a different email address
- Check if user already exists in system

#### "Failed to update"
- Check internet connection
- Verify all required fields are completed
- Contact administrator if issue persists

### Getting Help
- Contact your system administrator
- Check this manual for guidance
- Report bugs or issues to technical support

---

## System Information

### Features Implemented
- ✅ Role-based access control
- ✅ User management (Admin)
- ✅ Subject and class management
- ✅ Attendance tracking
- ✅ Profile management
- ✅ Inline form processing (no redirects)
- ✅ Responsive design
- ✅ Search and filtering
- ✅ Data validation
- ✅ Password encryption

### Browser Compatibility
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Security Features
- Session management
- Password hashing
- SQL injection prevention
- XSS protection
- Role-based permissions

---

*Last Updated: September 2025*
*SAMS Version: 1.0*