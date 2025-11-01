<?php
// Include header file
require_once '../includes/header.php';

// Check if user has teacher role
if (!hasRole('teacher')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Get filter parameters
$class_id = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d'); // Default to today

// Check if date is valid
if (!validateDate($date)) {
    $_SESSION['error'] = 'Invalid date format.';
    $date = date('Y-m-d');
}

// Get assigned classes for dropdown
try {
    $stmt = $db->prepare("SELECT DISTINCT c.id, c.name
                          FROM classes c
                          JOIN class_subject cs ON c.id = cs.class_id
                          WHERE cs.teacher_id = ?
                          ORDER BY c.name");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $classes = [];
}

// Get assigned subjects based on selected class
$subjects = [];
if ($class_id > 0) {
    try {
        $stmt = $db->prepare("SELECT DISTINCT s.id, s.name, s.code
                              FROM subjects s
                              JOIN class_subject cs ON s.id = cs.subject_id
                              WHERE cs.teacher_id = ? AND cs.class_id = ?
                              ORDER BY s.name");
        $stmt->execute([$teacher_id, $class_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
    }
}

// Get students and attendance status if class and subject are selected
$students = [];
if ($class_id > 0 && $subject_id > 0) {
    try {
        // Verify that this class and subject are assigned to this teacher
        $stmt = $db->prepare("SELECT id FROM class_subject 
                              WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
        $stmt->execute([$teacher_id, $class_id, $subject_id]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = 'You are not assigned to this class and subject.';
            redirect(URL_ROOT . '/teacher/mark-attendance.php');
        }
        
        // Get students in the class with their attendance status
        $stmt = $db->prepare("SELECT u.id, u.name, u.email, 
                                    COALESCE(a.status, 'unmarked') as status,
                                    a.id as attendance_id
                              FROM users u
                              LEFT JOIN attendance a ON (u.id = a.student_id AND a.date = ? AND a.subject_id = ?)
                              WHERE u.role = 'student' AND u.class_id = ? AND u.status = 'active'
                              ORDER BY u.name");
        $stmt->execute([$date, $subject_id, $class_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
    }
}

// Process attendance marking form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    // Verify teacher's assignment to this class and subject
    try {
        $stmt = $db->prepare("SELECT id FROM class_subject 
                              WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
        $stmt->execute([$teacher_id, $_POST['class_id'], $_POST['subject_id']]);
        
        if ($stmt->rowCount() === 0) {
            $_SESSION['error'] = 'You are not assigned to this class and subject.';
            redirect(URL_ROOT . '/teacher/mark-attendance.php');
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $_SESSION['error'] = 'Failed to verify assignment. Please try again.';
        redirect(URL_ROOT . '/teacher/mark-attendance.php');
    }
    
    // Validate attendance date
    $attendance_date = sanitize($_POST['attendance_date']);
    if (!validateDate($attendance_date)) {
        $_SESSION['error'] = 'Invalid date format.';
        redirect(URL_ROOT . '/teacher/mark-attendance.php?class=' . $_POST['class_id'] . '&subject=' . $_POST['subject_id']);
    }
    
    // Check if the date is in the future
    if (strtotime($attendance_date) > strtotime(date('Y-m-d'))) {
        $_SESSION['error'] = 'Cannot mark attendance for future dates.';
        redirect(URL_ROOT . '/teacher/mark-attendance.php?class=' . $_POST['class_id'] . '&subject=' . $_POST['subject_id'] . '&date=' . $attendance_date);
    }
    
    // Mark attendance for each student
    $success_count = 0;
    $error_count = 0;
    
    foreach ($_POST['attendance'] as $student_id => $status) {
        // Validate status
        if (!in_array($status, ['present', 'absent', 'late'])) {
            continue;
        }
        
        try {
            // Check if attendance record already exists
            $stmt = $db->prepare("SELECT id FROM attendance 
                                  WHERE student_id = ? AND date = ? AND subject_id = ?");
            $stmt->execute([$student_id, $attendance_date, $_POST['subject_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("UPDATE attendance 
                                      SET status = ?, updated_at = NOW() 
                                      WHERE id = ?");
                $stmt->execute([$status, $existing['id']]);
            } else {
                // Insert new record
                $stmt = $db->prepare("INSERT INTO attendance 
                                      (student_id, subject_id, date, status, created_at, updated_at) 
                                      VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$student_id, $_POST['subject_id'], $attendance_date, $status]);
            }
            
            $success_count++;
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $error_count++;
        }
    }
    
    // Set success/error messages
    if ($success_count > 0) {
        $_SESSION['success'] = "Attendance marked successfully for {$success_count} student(s).";
        
        // Log activity
        try {
            // Get class name
            $stmt = $db->prepare("SELECT name FROM classes WHERE id = ?");
            $stmt->execute([$_POST['class_id']]);
            $class_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
            
            // Get subject name
            $stmt = $db->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt->execute([$_POST['subject_id']]);
            $subject_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
            
            logActivity('Mark Attendance', "Marked attendance for {$class_name}, {$subject_name} on {$attendance_date}");
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
    
    if ($error_count > 0) {
        $_SESSION['error'] = "Failed to mark attendance for {$error_count} student(s).";
    }
    
    // Redirect to the same page to refresh data
    redirect(URL_ROOT . '/teacher/mark-attendance.php?class=' . $_POST['class_id'] . '&subject=' . $_POST['subject_id'] . '&date=' . $attendance_date);
}
?>

<!-- Mark Attendance -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-check-square me-2"></i>Mark Attendance</h2>
    <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php" class="btn btn-secondary">
        <i class="fas fa-history me-2"></i>Attendance History
    </a>
</div>

<!-- Attendance Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Select Class, Subject & Date</h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <div class="col-md-4">
                <label for="class" class="form-label">Class</label>
                <select class="form-select" id="class" name="class" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo $class['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="subject" class="form-label">Subject</label>
                <select class="form-select" id="subject" name="subject" <?php echo empty($subjects) ? 'disabled' : ''; ?> required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo ($subject_id == $subject['id']) ? 'selected' : ''; ?>>
                            <?php echo $subject['name']; ?> (<?php echo $subject['code']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($class_id > 0 && $subject_id > 0 && !empty($students)): ?>
    <!-- Mark Attendance Form -->
    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="attendanceForm">
        <input type="hidden" name="mark_attendance" value="1">
        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
        <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
        <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Mark Attendance for <?php echo date('F j, Y', strtotime($date)); ?>
                </h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm me-2" onclick="markAll('present')">
                        <i class="fas fa-check me-1"></i>Mark All Present
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="markAll('absent')">
                        <i class="fas fa-times me-1"></i>Mark All Absent
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover attendance-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Quick Mark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo $student['name']; ?></td>
                                    <td><?php echo $student['email']; ?></td>
                                    <td id="status-<?php echo $student['id']; ?>" class="<?php echo getAttendanceStatusClass($student['status']); ?>">
                                        <?php if ($student['status'] === 'unmarked'): ?>
                                            <span class="text-muted">Not Marked</span>
                                        <?php else: ?>
                                            <?php if ($student['status'] === 'present'): ?>
                                                <i class="fas fa-check-circle"></i> Present
                                            <?php elseif ($student['status'] === 'absent'): ?>
                                                <i class="fas fa-times-circle"></i> Absent
                                            <?php elseif ($student['status'] === 'late'): ?>
                                                <i class="fas fa-clock"></i> Late
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="quick-mark">
                                            <button type="button" class="btn btn-success btn-quick-mark" 
                                                    data-status="present" 
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    onclick="updateStatus(<?php echo $student['id']; ?>, 'present')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-quick-mark" 
                                                    data-status="absent" 
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    onclick="updateStatus(<?php echo $student['id']; ?>, 'absent')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button type="button" class="btn btn-warning btn-quick-mark" 
                                                    data-status="late" 
                                                    data-student-id="<?php echo $student['id']; ?>"
                                                    onclick="updateStatus(<?php echo $student['id']; ?>, 'late')">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                            
                                            <input type="hidden" name="attendance[<?php echo $student['id']; ?>]" 
                                                   id="attendance-<?php echo $student['id']; ?>" 
                                                   value="<?php echo $student['status'] === 'unmarked' ? '' : $student['status']; ?>">
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Attendance
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <script>
        // Function to update status
        function updateStatus(studentId, status) {
            const statusCell = document.getElementById('status-' + studentId);
            const inputField = document.getElementById('attendance-' + studentId);
            
            // Update hidden input value
            inputField.value = status;
            
            // Update status cell
            statusCell.className = '';
            
            if (status === 'present') {
                statusCell.className = 'bg-success text-white';
                statusCell.innerHTML = '<i class="fas fa-check-circle"></i> Present';
            } else if (status === 'absent') {
                statusCell.className = 'bg-danger text-white';
                statusCell.innerHTML = '<i class="fas fa-times-circle"></i> Absent';
            } else if (status === 'late') {
                statusCell.className = 'bg-warning text-dark';
                statusCell.innerHTML = '<i class="fas fa-clock"></i> Late';
            }
        }
        
        // Function to mark all students with the same status
        function markAll(status) {
            const students = document.querySelectorAll('input[name^="attendance["]');
            students.forEach(function(student) {
                const studentId = student.id.split('-')[1];
                updateStatus(studentId, status);
            });
        }
        
        // When class changes, update subject dropdown
        document.getElementById('class').addEventListener('change', function() {
            const classId = this.value;
            if (classId) {
                window.location.href = '<?php echo URL_ROOT; ?>/teacher/mark-attendance.php?class=' + classId;
            } else {
                window.location.href = '<?php echo URL_ROOT; ?>/teacher/mark-attendance.php';
            }
        });
    </script>
<?php elseif ($class_id > 0 && $subject_id > 0 && empty($students)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No students found in this class.
    </div>
<?php elseif ($class_id > 0 && empty($subjects)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No subjects assigned to you for this class.
    </div>
<?php endif; ?>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
