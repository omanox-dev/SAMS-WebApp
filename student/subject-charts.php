<?php
// Include header file
require_once '../includes/header.php';

// Check if user has student role
if (!hasRole('student')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get student ID
$student_id = $_SESSION['user_id'];

// Get student details with class information
try {
    $stmt = $db->prepare("SELECT u.*, c.name as class_name, c.id as class_id
                          FROM users u
                          LEFT JOIN classes c ON u.class_id = c.id
                          WHERE u.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load student data.';
    $student = [];
}

// Get subject-wise attendance data
try {
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
    $stmt->execute([$student_id]);
    $subject_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subject_attendance = [];
}
?>

<!-- Subject-wise Attendance Overview -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-bar me-2"></i>Subject-wise Attendance Overview</h2>
    <div>
        <a href="<?php echo URL_ROOT; ?>/student/view-attendance.php" class="btn btn-primary me-2">
            <i class="fas fa-table"></i> View Detailed Table
        </a>
        <a href="<?php echo URL_ROOT; ?>/student/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<?php if (empty($subject_attendance)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        No attendance data found. Please check back after some attendance has been recorded.
    </div>
<?php else: ?>
    <!-- Subject Charts Grid -->
    <div class="row">
        <?php foreach ($subject_attendance as $index => $subject): ?>
            <div class="col-md-6 mb-3">
                <div class="card subject-strip">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <!-- Subject Info -->
                            <div class="col-4">
                                <h6 class="mb-1 fw-bold"><?php echo $subject['subject_name']; ?></h6>
                                <small class="text-muted"><?php echo $subject['subject_code']; ?></small>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="col-5">
                                <div class="mb-1 d-flex justify-content-between">
                                    <small class="text-muted">Attendance</small>
                                    <small class="fw-bold text-<?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'danger' : 'success'; ?>">
                                        <?php echo $subject['attendance_percentage']; ?>%
                                    </small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'danger' : 'success'; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $subject['attendance_percentage']; ?>%">
                                    </div>
                                </div>
                                <div class="mt-1 d-flex justify-content-between">
                                    <small class="text-success">P: <?php echo $subject['present_days']; ?></small>
                                    <small class="text-danger">A: <?php echo $subject['absent_days']; ?></small>
                                    <small class="text-warning">L: <?php echo $subject['late_days']; ?></small>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <div class="col-3 text-end">
                                <a href="<?php echo URL_ROOT; ?>/student/view-attendance.php?subject_id=<?php echo $subject['id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary" 
                                   title="View attendance records for <?php echo $subject['subject_name']; ?>">
                                    <i class="fas fa-eye me-1"></i>View
                                </a>
                                <div class="mt-1">
                                    <small class="text-muted"><?php echo $subject['total_days']; ?> classes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php 
            // Add row break after every 2 subjects for better layout
            if (($index + 1) % 2 == 0 && ($index + 1) < count($subject_attendance)): 
            ?>
        </div>
        <div class="row">
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Summary Statistics -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Overall Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $total_classes = array_sum(array_column($subject_attendance, 'total_days'));
                $total_present = array_sum(array_column($subject_attendance, 'present_days'));
                $total_absent = array_sum(array_column($subject_attendance, 'absent_days'));
                $total_late = array_sum(array_column($subject_attendance, 'late_days'));
                $overall_percentage = $total_classes > 0 ? round(($total_present / $total_classes) * 100, 2) : 0;
                ?>
                
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-primary"><?php echo count($subject_attendance); ?></h4>
                        <small class="text-muted">Subjects</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-info"><?php echo $total_classes; ?></h4>
                        <small class="text-muted">Total Classes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-success"><?php echo $total_present; ?></h4>
                        <small class="text-muted">Present Days</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-<?php echo $overall_percentage < MIN_ATTENDANCE_PERCENTAGE ? 'danger' : 'success'; ?>">
                            <?php echo $overall_percentage; ?>%
                        </h4>
                        <small class="text-muted">Overall Attendance</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.subject-strip {
    transition: all 0.2s ease-in-out;
    border-left: 4px solid transparent;
}

.subject-strip:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-left-color: var(--bs-primary);
}

.progress {
    background-color: rgba(0,0,0,0.1);
}

.btn-sm {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

@media (max-width: 768px) {
    .col-md-6 {
        margin-bottom: 0.75rem;
    }
    
    .subject-strip .card-body {
        padding: 1rem !important;
    }
    
    .subject-strip .row > .col-4,
    .subject-strip .row > .col-5,
    .subject-strip .row > .col-3 {
        margin-bottom: 0.5rem;
    }
}
</style>

<?php
// Include footer file
require_once '../includes/footer.php';
?>