<?php
// Add these helper functions to the existing functions.php file

/**
 * Format the date to a more readable format
 * 
 * @param string $date Date string in Y-m-d format
 * @return string Formatted date
 */
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

/**
 * Get the appropriate Bootstrap class for attendance status
 * 
 * @param string $status The attendance status (present, absent, late)
 * @return string The Bootstrap badge class
 */
function getAttendanceStatusClass($status) {
    switch ($status) {
        case 'present':
            return 'bg-success';
        case 'absent':
            return 'bg-danger';
        case 'late':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}

/**
 * Calculate attendance percentage for a student
 * 
 * @param PDO $db Database connection
 * @param int $student_id Student ID
 * @param int|null $subject_id Subject ID (optional)
 * @return array Attendance statistics
 */
function calculateAttendanceStats($db, $student_id, $subject_id = null) {
    try {
        $params = [$student_id];
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(*) as total_count
                FROM attendance
                WHERE student_id = ?";
        
        if ($subject_id) {
            $query .= " AND subject_id = ?";
            $params[] = $subject_id;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentages
        $total = $stats['total_count'] ?: 1; // Avoid division by zero
        $stats['present_percentage'] = round(($stats['present_count'] / $total) * 100, 2);
        $stats['absent_percentage'] = round(($stats['absent_count'] / $total) * 100, 2);
        $stats['late_percentage'] = round(($stats['late_count'] / $total) * 100, 2);
        
        return $stats;
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'total_count' => 0,
            'present_percentage' => 0,
            'absent_percentage' => 0,
            'late_percentage' => 0
        ];
    }
}

/**
 * Check if a date is a weekend or holiday
 * 
 * @param string $date Date in Y-m-d format
 * @param PDO $db Database connection
 * @return bool True if weekend or holiday
 */
function isWeekendOrHoliday($date, $db) {
    // Check if weekend (Saturday or Sunday)
    $day_of_week = date('N', strtotime($date));
    if ($day_of_week >= 6) { // 6 is Saturday, 7 is Sunday
        return true;
    }
    
    // Check if holiday (requires a holidays table)
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM holidays WHERE date = ?");
        $stmt->execute([$date]);
        $is_holiday = $stmt->fetchColumn() > 0;
        return $is_holiday;
    } catch (PDOException $e) {
        // If holidays table doesn't exist or another error occurs
        return false;
    }
}

/**
 * Get list of students in a class
 * 
 * @param PDO $db Database connection
 * @param int $class_id Class ID
 * @return array List of students
 */
function getStudentsByClass($db, $class_id) {
    try {
        $stmt = $db->prepare("SELECT id, name, roll_number, status 
                             FROM users 
                             WHERE role = 'student' AND class_id = ? 
                             ORDER BY roll_number, name");
        $stmt->execute([$class_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Check if attendance has already been marked for a class, subject and date
 * 
 * @param PDO $db Database connection
 * @param int $class_id Class ID
 * @param int $subject_id Subject ID
 * @param string $date Date in Y-m-d format
 * @return bool True if attendance exists
 */
function isAttendanceMarked($db, $class_id, $subject_id, $date) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM attendance a 
                             JOIN users u ON a.student_id = u.id 
                             WHERE u.class_id = ? AND a.subject_id = ? AND a.date = ?");
        $stmt->execute([$class_id, $subject_id, $date]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

/**
 * Get a list of subjects assigned to a teacher
 * 
 * @param PDO $db Database connection
 * @param int $teacher_id Teacher ID
 * @return array List of subjects
 */
function getTeacherSubjects($db, $teacher_id) {
    try {
        $stmt = $db->prepare("SELECT s.id, s.name, s.code 
                             FROM subjects s
                             JOIN teacher_subject ts ON s.id = ts.subject_id
                             WHERE ts.teacher_id = ?
                             ORDER BY s.name");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Get classes assigned to a teacher through subjects
 * 
 * @param PDO $db Database connection
 * @param int $teacher_id Teacher ID
 * @return array List of classes
 */
function getTeacherClasses($db, $teacher_id) {
    try {
        $stmt = $db->prepare("SELECT DISTINCT c.id, c.name
                             FROM classes c
                             JOIN class_subject cs ON c.id = cs.class_id
                             JOIN teacher_subject ts ON cs.subject_id = ts.subject_id
                             WHERE ts.teacher_id = ?
                             ORDER BY c.name");
        $stmt->execute([$teacher_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Get subject-wise attendance statistics for a student
 * 
 * @param PDO $db Database connection
 * @param int $student_id Student ID
 * @return array Subject-wise attendance stats
 */
function getSubjectWiseAttendance($db, $student_id) {
    try {
        $stmt = $db->prepare("SELECT s.id, s.name as subject_name, s.code as subject_code,
                                COUNT(a.id) as total_days,
                                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                                COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                                ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / 
                                       NULLIF(COUNT(a.id), 0)) * 100, 2) as attendance_percentage
                              FROM attendance a
                              RIGHT JOIN subjects s ON a.subject_id = s.id AND a.student_id = ?
                              JOIN class_subject cs ON s.id = cs.subject_id
                              JOIN users u ON u.id = ? AND u.class_id = cs.class_id
                              GROUP BY s.id, s.name, s.code
                              ORDER BY attendance_percentage DESC");
        $stmt->execute([$student_id, $student_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Get monthly attendance summary for a student
 * 
 * @param PDO $db Database connection
 * @param int $student_id Student ID
 * @param int $limit Number of months to return
 * @return array Monthly attendance stats
 */
function getMonthlyAttendanceSummary($db, $student_id, $limit = 6) {
    try {
        $stmt = $db->prepare("SELECT DATE_FORMAT(date, '%Y-%m') as month,
                                DATE_FORMAT(date, '%b %Y') as month_name,
                                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                                COUNT(*) as total_days,
                                ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) / 
                                       COUNT(*)) * 100, 2) as percentage
                              FROM attendance
                              WHERE student_id = ?
                              GROUP BY DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%b %Y')
                              ORDER BY month DESC
                              LIMIT ?");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Get class-wise attendance statistics
 * 
 * @param PDO $db Database connection
 * @param string $date Date in Y-m-d format (optional)
 * @return array Class-wise attendance stats
 */
function getClassWiseAttendance($db, $date = null) {
    try {
        $params = [];
        $query = "SELECT c.id, c.name as class_name,
                    COUNT(DISTINCT u.id) as total_students,
                    COUNT(DISTINCT CASE WHEN a.status = 'present' THEN u.id END) as present_students,
                    COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN u.id END) as absent_students,
                    COUNT(DISTINCT CASE WHEN a.status = 'late' THEN u.id END) as late_students,
                    ROUND((COUNT(DISTINCT CASE WHEN a.status = 'present' THEN u.id END) / 
                           NULLIF(COUNT(DISTINCT u.id), 0)) * 100, 2) as attendance_percentage
                  FROM classes c
                  LEFT JOIN users u ON c.id = u.class_id AND u.role = 'student'
                  LEFT JOIN attendance a ON u.id = a.student_id";
                  
        if ($date) {
            $query .= " AND a.date = ?";
            $params[] = $date;
        }
        
        $query .= " GROUP BY c.id, c.name
                   ORDER BY attendance_percentage DESC";
                   
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

/**
 * Generate random password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $chars_length = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $chars_length - 1)];
    }
    
    return $password;
}

/**
 * Get user data by ID
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return array|null User data or null if not found
 */
function getUserById($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT u.*, c.name as class_name 
                             FROM users u 
                             LEFT JOIN classes c ON u.class_id = c.id 
                             WHERE u.id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return null;
    }
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message) {
    // This is a placeholder. Implementation depends on the email library you choose.
    // You might want to use PHPMailer, Swift Mailer, or PHP's mail() function.
    
    // Get site name and admin email from config
    $site_name = defined('SITE_NAME') ? SITE_NAME : 'Student Attendance Management System';
    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com';
    
    // Example implementation using PHP's mail() function:
    $headers = "From: " . $site_name . " <" . $admin_email . ">\r\n";
    $headers .= "Reply-To: " . $admin_email . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Check if a string contains only alpha-numeric characters and specific symbols
 * 
 * @param string $input Input string
 * @return bool True if valid, false otherwise
 */
function isValidInput($input) {
    return preg_match('/^[a-zA-Z0-9\s\-_.,:;\'\"!?()]+$/', $input);
}

/**
 * Convert CSV data to array
 * 
 * @param string $csv_file Path to CSV file
 * @param bool $has_header Whether CSV has header row
 * @return array Data from CSV
 */
function csvToArray($csv_file, $has_header = true) {
    $rows = [];
    if (($handle = fopen($csv_file, "r")) !== false) {
        if ($has_header) {
            $header = fgetcsv($handle);
        }
        
        while (($data = fgetcsv($handle)) !== false) {
            if ($has_header) {
                $row = array_combine($header, $data);
                $rows[] = $row;
            } else {
                $rows[] = $data;
            }
        }
        fclose($handle);
    }
    return $rows;
}
