<?php
// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/attendance_functions.php';

// Validate user is admin
session_start();
if (!isLoggedIn() || !hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to access this page.';
    redirect(URL_ROOT . '/index.php');
}

/**
 * Send attendance notifications to students/parents
 */
function sendAttendanceNotifications() {
    global $db;
    
    $today = date('Y-m-d');
    
    try {
        // Get students with attendance marked today
        $stmt = $db->prepare("SELECT DISTINCT a.student_id, 
                                u.name as student_name, 
                                u.email as student_email,
                                u.parent_email,
                                c.name as class_name
                            FROM attendance a
                            JOIN users u ON a.student_id = u.id
                            JOIN classes c ON u.class_id = c.id
                            WHERE a.date = ? AND u.status = 'active'
                            ORDER BY u.name");
        $stmt->execute([$today]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sent_count = 0;
        
        foreach ($students as $student) {
            // Get today's attendance for this student
            $stmt = $db->prepare("SELECT a.status, 
                                    s.name as subject_name,
                                    s.code as subject_code
                                FROM attendance a
                                JOIN subjects s ON a.subject_id = s.id
                                WHERE a.student_id = ? AND a.date = ?
                                ORDER BY s.name");
            $stmt->execute([$student['student_id'], $today]);
            $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count status types
            $status_count = [
                'present' => 0,
                'absent' => 0,
                'late' => 0
            ];
            
            $absent_subjects = [];
            
            foreach ($attendance_records as $record) {
                $status_count[$record['status']]++;
                
                if ($record['status'] == 'absent') {
                    $absent_subjects[] = $record['subject_name'] . ' (' . $record['subject_code'] . ')';
                }
            }
            
            // Only send notification if student was absent in any subject
            if ($status_count['absent'] > 0) {
                // Prepare email content
                $subject = "Attendance Alert - " . $student['student_name'] . " was absent today";
                
                $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #f5f5f5; padding: 10px; border-bottom: 2px solid #ddd; }
                        .content { padding: 20px 0; }
                        .footer { font-size: 12px; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
                        .absent { color: #dc3545; }
                        ul { padding-left: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>" . SITE_NAME . " - Attendance Notification</h2>
                        </div>
                        <div class='content'>
                            <p>Dear Parent/Guardian,</p>
                            <p>This is to inform you that <strong>" . $student['student_name'] . "</strong> of <strong>" . $student['class_name'] . "</strong> was absent for the following subjects today (" . date('d-m-Y') . "):</p>
                            <ul class='absent'>";
                            
                foreach ($absent_subjects as $subject) {
                    $message .= "<li>" . $subject . "</li>";
                }
                            
                $message .= "
                            </ul>
                            <p>Today's attendance summary:</p>
                            <ul>
                                <li>Present: " . $status_count['present'] . " subject(s)</li>
                                <li>Absent: " . $status_count['absent'] . " subject(s)</li>
                                <li>Late: " . $status_count['late'] . " subject(s)</li>
                            </ul>
                            <p>Please note that regular attendance is important for academic progress. If this absence was unplanned, please ensure that your child attends all classes regularly.</p>
                            <p>For more details, please log in to the attendance portal or contact the class teacher.</p>
                            <p>Thank you,<br>Administration Team<br>" . SITE_NAME . "</p>
                        </div>
                        <div class='footer'>
                            This is an automated message. Please do not reply to this email.
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Send email to student and parent if email addresses are available
                $recipients = [];
                
                if (!empty($student['student_email'])) {
                    $recipients[] = $student['student_email'];
                }
                
                if (!empty($student['parent_email'])) {
                    $recipients[] = $student['parent_email'];
                }
                
                if (!empty($recipients)) {
                    $to = implode(',', $recipients);
                    if (sendEmail($to, $subject, $message)) {
                        $sent_count++;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'sent_count' => $sent_count,
            'total_students' => count($students)
        ];
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send low attendance warnings
 */
function sendLowAttendanceWarnings() {
    global $db;
    
    try {
        // Get students with attendance below minimum threshold
        $stmt = $db->prepare("SELECT u.id, 
                                u.name as student_name,
                                u.email as student_email,
                                u.parent_email,
                                c.name as class_name,
                                (
                                    SELECT ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 2)
                                    FROM attendance 
                                    WHERE student_id = u.id
                                ) as attendance_percentage
                            FROM users u
                            JOIN classes c ON u.class_id = c.id
                            WHERE u.role = 'student' AND u.status = 'active'
                            HAVING attendance_percentage < ?
                            ORDER BY attendance_percentage ASC");
        $stmt->execute([MIN_ATTENDANCE_PERCENTAGE]);
        $low_attendance_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sent_count = 0;
        
        foreach ($low_attendance_students as $student) {
            // Get subject-wise attendance for this student
            $subject_attendance = getSubjectWiseAttendance($db, $student['id']);
            
            $low_subjects = [];
            foreach ($subject_attendance as $subject) {
                if ($subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE) {
                    $low_subjects[] = [
                        'name' => $subject['subject_name'],
                        'code' => $subject['subject_code'],
                        'percentage' => $subject['attendance_percentage']
                    ];
                }
            }
            
            // Prepare email content
            $subject = "Low Attendance Warning - " . $student['student_name'];
            
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #f8d7da; padding: 10px; border-bottom: 2px solid #f5c6cb; }
                    .content { padding: 20px 0; }
                    .footer { font-size: 12px; color: #777; border-top: 1px solid #ddd; padding-top: 10px; }
                    .warning { color: #721c24; background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; }
                    table { width: 100%; border-collapse: collapse; }
                    table, th, td { border: 1px solid #ddd; }
                    th, td { padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .low-percentage { color: #dc3545; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>" . SITE_NAME . " - Low Attendance Warning</h2>
                    </div>
                    <div class='content'>
                        <p>Dear Student/Parent,</p>
                        <div class='warning'>
                            <p>This is to inform you that <strong>" . $student['student_name'] . "</strong> of <strong>" . $student['class_name'] . "</strong> has an overall attendance of <strong class='low-percentage'>" . $student['attendance_percentage'] . "%</strong>, which is below the minimum required percentage of <strong>" . MIN_ATTENDANCE_PERCENTAGE . "%</strong>.</p>
                        </div>
                        
                        <p>Please note that maintaining adequate attendance is essential for academic success and is also a requirement to be eligible for examinations.</p>
                        
                        <p>The following subjects have attendance below the minimum requirement:</p>
                        
                        <table>
                            <tr>
                                <th>Subject</th>
                                <th>Attendance Percentage</th>
                            </tr>";
                            
            foreach ($low_subjects as $subject) {
                $message .= "
                            <tr>
                                <td>" . $subject['name'] . " (" . $subject['code'] . ")</td>
                                <td class='low-percentage'>" . $subject['percentage'] . "%</td>
                            </tr>";
            }
                            
            $message .= "
                        </table>
                        
                        <p>Action Required:</p>
                        <ul>
                            <li>Ensure regular attendance in all classes going forward</li>
                            <li>Meet with respective subject teachers to discuss ways to improve</li>
                            <li>If there were legitimate reasons for absence (health issues, etc.), please submit relevant documentation to the administration office</li>
                        </ul>
                        
                        <p>Please log in to the attendance portal for more detailed information or contact the class teacher.</p>
                        <p>Thank you,<br>Administration Team<br>" . SITE_NAME . "</p>
                    </div>
                    <div class='footer'>
                        This is an automated message. Please do not reply to this email.
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Send email to student and parent if email addresses are available
            $recipients = [];
            
            if (!empty($student['student_email'])) {
                $recipients[] = $student['student_email'];
            }
            
            if (!empty($student['parent_email'])) {
                $recipients[] = $student['parent_email'];
            }
            
            if (!empty($recipients)) {
                $to = implode(',', $recipients);
                if (sendEmail($to, $subject, $message)) {
                    $sent_count++;
                }
            }
        }
        
        return [
            'success' => true,
            'sent_count' => $sent_count,
            'total_students' => count($low_attendance_students)
        ];
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $result = [];
    
    if ($action === 'attendance_notifications') {
        $result = sendAttendanceNotifications();
    } elseif ($action === 'low_attendance_warnings') {
        $result = sendLowAttendanceWarnings();
    }
    
    // Set session message based on result
    if (isset($result['success']) && $result['success']) {
        $_SESSION['success'] = 'Notifications sent successfully. ' . $result['sent_count'] . ' email(s) sent out of ' . $result['total_students'] . ' student(s).';
    } else {
        $_SESSION['error'] = 'Failed to send notifications. ' . (isset($result['error']) ? $result['error'] : 'Unknown error.');
    }
    
    // Redirect back to the notifications page
    redirect(URL_ROOT . '/admin/notifications.php');
}

// Include header
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
    <a href="<?php echo URL_ROOT; ?>/admin/dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="row">
    <!-- Daily Attendance Notifications -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-envelope me-2"></i>Daily Absence Notifications</h5>
            </div>
            <div class="card-body">
                <p>Send email notifications to students/parents about today's absences.</p>
                <ul>
                    <li>Notifies only for students who were absent today</li>
                    <li>Includes subject-wise absence details</li>
                    <li>Sent to both student and parent emails (if available)</li>
                </ul>
                
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" onsubmit="return confirm('Are you sure you want to send absence notifications for today?');">
                    <input type="hidden" name="action" value="attendance_notifications">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Absence Notifications
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Low Attendance Warnings -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Low Attendance Warnings</h5>
            </div>
            <div class="card-body">
                <p>Send warnings to students/parents whose overall attendance is below the minimum requirement (<?php echo MIN_ATTENDANCE_PERCENTAGE; ?>%).</p>
                <ul>
                    <li>Identifies students with low overall attendance</li>
                    <li>Includes subject-wise attendance details</li>
                    <li>Suggests actions to improve attendance</li>
                </ul>
                
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" onsubmit="return confirm('Are you sure you want to send low attendance warnings?');">
                    <input type="hidden" name="action" value="low_attendance_warnings">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Send Low Attendance Warnings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Automated Notifications -->
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>Schedule Automated Notifications</h5>
    </div>
    <div class="card-body">
        <p>Configure the system to automatically send notifications based on your preferred schedule.</p>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> To enable automated notifications, you need to set up a cron job on your server that runs the notification scripts at specified intervals.
        </div>
        
        <h6 class="mt-4">Sample Cron Job Configuration:</h6>
        <div class="bg-light p-3 border rounded">
            <code>
                # Send daily absence notifications at 6:00 PM every weekday<br>
                0 18 * * 1-5 /usr/bin/php <?php echo DIR_ROOT; ?>/admin/cron_notifications.php daily<br><br>
                
                # Send weekly low attendance warnings every Sunday at 10:00 AM<br>
                0 10 * * 0 /usr/bin/php <?php echo DIR_ROOT; ?>/admin/cron_notifications.php weekly
            </code>
        </div>
        
        <div class="mt-4">
            <p>Please provide these instructions to your system administrator to set up automated notifications.</p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../includes/footer.php';
?>
