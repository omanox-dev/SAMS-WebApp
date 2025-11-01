<?php
// Include header file
require_once '../includes/header.php';
?>

<style>
/* Dashboard Custom Styles */
.stats-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

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

.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stats-label {
    font-size: 1rem;
    color: #6c757d;
    font-weight: 500;
}

.mini-stat-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.student-alert-card {
    transition: all 0.2s;
    background-color: #fff;
}

.student-alert-card:hover {
    background-color: #fff5f5 !important;
    border-color: #fecaca !important;
    transform: translateY(-1px);
}

.chart-container {
    position: relative;
    height: 300px;
}

.assignment-item {
    transition: all 0.2s;
    border: 1px solid #e9ecef !important;
}

.assignment-item:hover {
    background-color: #f8f9fa !important;
    border-color: #dee2e6 !important;
    transform: translateY(-1px);
}

.assignment-info h6 {
    color: #495057;
    font-weight: 600;
}

.activity-item {
    border-bottom: 1px solid #f1f3f4 !important;
}

.activity-item:last-child {
    border-bottom: none !important;
}

.activity-icon .badge {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.low-attendance-item {
    transition: all 0.2s;
    border: 1px solid #e9ecef !important;
    background-color: #fff;
}

.low-attendance-item:hover {
    background-color: #fff5f5 !important;
    border-color: #fecaca !important;
}

.progress-sm {
    height: 6px;
}

.card {
    transition: box-shadow 0.2s;
}

.card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .stats-number {
        font-size: 1.5rem;
    }
    
    .assignment-item .btn-group {
        margin-top: 0.5rem;
    }
    
    .assignment-item .col-md-6:last-child {
        text-align: left !important;
    }
}
</style>

<?php
if (!hasRole('teacher')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Get today's date
$today = date('Y-m-d');

// Get assigned classes and subjects
try {
    $stmt = $db->prepare("SELECT cs.id, c.name as class_name, c.id as class_id, s.name as subject_name, s.id as subject_id
                          FROM class_subject cs
                          JOIN classes c ON cs.class_id = c.id
                          JOIN subjects s ON cs.subject_id = s.id
                          WHERE cs.teacher_id = ?
                          ORDER BY c.name, s.name");
    $stmt->execute([$teacher_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total assignments
    $assignment_count = count($assignments);
    
    // Get unique class count
    $class_ids = array_unique(array_column($assignments, 'class_id'));
    $class_count = count($class_ids);
    
    // Get unique subject count
    $subject_ids = array_unique(array_column($assignments, 'subject_id'));
    $subject_count = count($subject_ids);
    
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load assignments data.';
    $assignments = [];
    $assignment_count = $class_count = $subject_count = 0;
}

// Get student count in teacher's classes
try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) as student_count 
                          FROM users u
                          JOIN classes c ON u.class_id = c.id
                          JOIN class_subject cs ON c.id = cs.class_id
                          WHERE cs.teacher_id = ? AND u.role = 'student'");
    $stmt->execute([$teacher_id]);
    $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $student_count = 0;
}

// Get recent attendance marked by the teacher
try {
    $stmt = $db->prepare("SELECT a.id, a.date, a.status, u.name as student_name, s.name as subject_name, c.name as class_name
                          FROM attendance a
                          JOIN users u ON a.student_id = u.id
                          JOIN subjects s ON a.subject_id = s.id
                          JOIN classes c ON u.class_id = c.id
                          JOIN class_subject cs ON (s.id = cs.subject_id AND c.id = cs.class_id)
                          WHERE cs.teacher_id = ?
                          ORDER BY a.date DESC, a.updated_at DESC
                          LIMIT 10");
    $stmt->execute([$teacher_id]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $recent_attendance = [];
}

// Get attendance statistics for teacher's subjects
try {
    $stmt = $db->prepare("SELECT 
                            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                            COUNT(a.id) as total_count
                        FROM attendance a
                        JOIN subjects s ON a.subject_id = s.id
                        JOIN class_subject cs ON s.id = cs.subject_id
                        WHERE cs.teacher_id = ?");
    $stmt->execute([$teacher_id]);
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate attendance percentage
    $total_count = $attendance_stats['total_count'] ?: 1; // Avoid division by zero
    $present_percentage = round(($attendance_stats['present_count'] / $total_count) * 100, 2);
    $absent_percentage = round(($attendance_stats['absent_count'] / $total_count) * 100, 2);
    $late_percentage = round(($attendance_stats['late_count'] / $total_count) * 100, 2);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $attendance_stats = ['present_count' => 0, 'absent_count' => 0, 'late_count' => 0, 'total_count' => 0];
    $present_percentage = $absent_percentage = $late_percentage = 0;
}

// Get low attendance students
try {
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
    $stmt->execute([$teacher_id, MIN_ATTENDANCE_PERCENTAGE]);
    $low_attendance_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $low_attendance_students = [];
}
?>

<!-- Teacher Dashboard -->
<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col-12">
            <h1 class="h3 mb-2 fw-bold text-dark">
                <i class="fas fa-chalkboard-teacher me-3 text-primary"></i>Teacher Dashboard
            </h1>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-2"></i>Today is <?php echo date('l, F j, Y'); ?> • Welcome back, <?php echo $_SESSION['user_name']; ?>
            </p>
        </div>
    </div>

    <!-- Dashboard Statistics -->
    <div class="row g-4 mb-4">
        <!-- Classes Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-primary mx-auto">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $class_count; ?></div>
                    <div class="stats-label">Classes Assigned</div>
                </div>
            </div>
        </div>
        
        <!-- Subjects Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-success mx-auto">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $subject_count; ?></div>
                    <div class="stats-label">Subjects Teaching</div>
                </div>
            </div>
        </div>
        
        <!-- Students Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-info mx-auto">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $student_count; ?></div>
                    <div class="stats-label">Total Students</div>
                </div>
            </div>
        </div>
        
        <!-- Attendance Rate Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-warning mx-auto">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $present_percentage; ?>%</div>
                    <div class="stats-label">Attendance Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & My Teaching Assignments Row -->
    <div class="row g-4 mb-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2 text-primary"></i>Quick Actions
                    </h5>
                    <small class="text-muted">Common teacher tasks</small>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php" class="btn btn-primary">
                            <i class="fas fa-history me-2"></i>View Attendance History
                        </a>
                        <a href="<?php echo URL_ROOT; ?>/teacher/reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar me-2"></i>Class Reports
                        </a>
                        <a href="<?php echo URL_ROOT; ?>/teacher/profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user-edit me-2"></i>Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- My Teaching Assignments -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tasks me-2 text-primary"></i>My Teaching Assignments
                        </h5>
                        <span class="badge bg-primary rounded-pill"><?php echo $assignment_count; ?> assignments</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($assignments)): ?>
                        <div class="assignment-grid">
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="assignment-item border rounded p-3 mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="assignment-info">
                                                <h6 class="mb-1 text-primary"><?php echo $assignment['class_name']; ?></h6>
                                                <span class="text-muted">
                                                    <i class="fas fa-book me-1"></i><?php echo $assignment['subject_name']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                            <div class="btn-group" role="group">
                                                <a href="<?php echo URL_ROOT; ?>/teacher/mark-attendance.php?class=<?php echo $assignment['class_id']; ?>&subject=<?php echo $assignment['subject_id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check-square me-1"></i>Mark Attendance
                                                </a>
                                                <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php?class=<?php echo $assignment['class_id']; ?>&subject=<?php echo $assignment['subject_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-history me-1"></i>View History
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-clipboard-list text-muted fa-3x mb-3"></i>
                            <h6 class="text-muted">No Teaching Assignments</h6>
                            <p class="text-muted mb-0">Contact the administrator to get classes and subjects assigned to you.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics & Alerts Row -->
    <div class="row g-4 mb-4">
        <!-- Today's Overview -->
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>Today's Overview
                    </h5>
                    <small class="text-muted">Quick attendance insights</small>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="mini-stat">
                                <div class="mini-stat-icon bg-success-subtle text-success rounded p-2 mb-2">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="h5 mb-1 text-success"><?php echo $attendance_stats['present_count']; ?></div>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mini-stat">
                                <div class="mini-stat-icon bg-danger-subtle text-danger rounded p-2 mb-2">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="h5 mb-1 text-danger"><?php echo $attendance_stats['absent_count']; ?></div>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mini-stat">
                                <div class="mini-stat-icon bg-warning-subtle text-warning rounded p-2 mb-2">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="h5 mb-1 text-warning"><?php echo $attendance_stats['late_count']; ?></div>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-medium">Overall Rate</span>
                            <span class="badge bg-primary"><?php echo $present_percentage; ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $present_percentage; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>Detailed Analytics
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Students Needing Attention -->
        <div class="col-lg-5 col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Students Needing Attention
                            </h5>
                            <small class="text-muted">Below <?php echo MIN_ATTENDANCE_PERCENTAGE; ?>% attendance</small>
                        </div>
                        <?php if (count($low_attendance_students) > 2): ?>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lowAttendanceCollapse" aria-expanded="false">
                                <span class="badge bg-warning text-dark me-2"><?php echo count($low_attendance_students); ?></span>
                                <i class="fas fa-chevron-down" id="lowAttendanceIcon"></i>
                            </button>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?php echo count($low_attendance_students); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($low_attendance_students)): ?>
                        <div class="row g-2">
                            <!-- Always show first 2 entries -->
                            <?php foreach (array_slice($low_attendance_students, 0, 2) as $student): ?>
                                <div class="col-12">
                                    <div class="student-alert-card border rounded p-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <div>
                                                <div class="fw-medium"><?php echo $student['student_name']; ?></div>
                                                <small class="text-muted"><?php echo $student['class_name']; ?> • <?php echo $student['subject_name']; ?></small>
                                            </div>
                                            <span class="badge bg-danger"><?php echo $student['attendance_percentage']; ?>%</span>
                                        </div>
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-danger" style="width: <?php echo $student['attendance_percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Collapsible section for remaining entries -->
                        <?php if (count($low_attendance_students) > 2): ?>
                            <div class="collapse mt-2" id="lowAttendanceCollapse">
                                <div class="row g-2">
                                    <?php foreach (array_slice($low_attendance_students, 2) as $student): ?>
                                        <div class="col-12">
                                            <div class="student-alert-card border rounded p-2 bg-light">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div>
                                                        <div class="fw-medium"><?php echo $student['student_name']; ?></div>
                                                        <small class="text-muted"><?php echo $student['class_name']; ?> • <?php echo $student['subject_name']; ?></small>
                                                    </div>
                                                    <span class="badge bg-danger"><?php echo $student['attendance_percentage']; ?>%</span>
                                                </div>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar bg-danger" style="width: <?php echo $student['attendance_percentage']; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                            <h6 class="text-success">Excellent Work!</h6>
                            <p class="text-muted mb-0 small">All students have good attendance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clock me-2 text-primary"></i>Recent Activity
                        </h5>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#recentActivityCollapse" aria-expanded="false">
                            <span class="badge bg-secondary me-2"><?php echo count($recent_attendance); ?></span>
                            <i class="fas fa-chevron-down" id="activityIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse" id="recentActivityCollapse">
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (!empty($recent_attendance)): ?>
                            <div class="activity-feed">
                                <?php foreach (array_slice($recent_attendance, 0, 6) as $attendance): ?>
                                    <div class="activity-item d-flex align-items-center py-2 border-bottom">
                                        <div class="activity-icon me-2">
                                            <span class="badge <?php echo getAttendanceStatusClass($attendance['status']); ?> rounded-circle p-1">
                                                <i class="fas fa-user fa-xs"></i>
                                            </span>
                                        </div>
                                        <div class="activity-info flex-grow-1">
                                            <div class="fw-medium small"><?php echo $attendance['student_name']; ?></div>
                                            <small class="text-muted">
                                                <?php echo $attendance['subject_name']; ?> • <?php echo formatDate($attendance['date']); ?>
                                            </small>
                                        </div>
                                        <span class="badge <?php echo getAttendanceStatusClass($attendance['status']); ?> bg-opacity-10 text-dark small">
                                            <?php echo ucfirst($attendance['status']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php" class="btn btn-outline-primary btn-sm">
                                    View All Activity
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-calendar-times text-muted fa-2x mb-2"></i>
                                <p class="text-muted mb-0 small">No recent activity to display.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize collapse functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle low attendance collapse
    const lowAttendanceCollapse = document.getElementById('lowAttendanceCollapse');
    const lowAttendanceIcon = document.getElementById('lowAttendanceIcon');
    
    if (lowAttendanceCollapse && lowAttendanceIcon) {
        lowAttendanceCollapse.addEventListener('show.bs.collapse', function () {
            lowAttendanceIcon.classList.remove('fa-chevron-down');
            lowAttendanceIcon.classList.add('fa-chevron-up');
        });
        
        lowAttendanceCollapse.addEventListener('hide.bs.collapse', function () {
            lowAttendanceIcon.classList.remove('fa-chevron-up');
            lowAttendanceIcon.classList.add('fa-chevron-down');
        });
    }
    
    // Handle recent activity collapse
    const activityCollapse = document.getElementById('recentActivityCollapse');
    const activityIcon = document.getElementById('activityIcon');
    
    if (activityCollapse && activityIcon) {
        activityCollapse.addEventListener('show.bs.collapse', function () {
            activityIcon.classList.remove('fa-chevron-down');
            activityIcon.classList.add('fa-chevron-up');
        });
        
        activityCollapse.addEventListener('hide.bs.collapse', function () {
            activityIcon.classList.remove('fa-chevron-up');
            activityIcon.classList.add('fa-chevron-down');
        });
    }
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
