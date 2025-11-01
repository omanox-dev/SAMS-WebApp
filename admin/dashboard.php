<?php
// Include header file
require_once '../includes/header.php';
?>

<style>
/* Admin Dashboard Custom Styles */
.stats-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    overflow: hidden;
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
    margin-bottom: 0.5rem;
}

.stats-label {
    font-size: 1rem;
    color: #6c757d;
    font-weight: 500;
    margin-bottom: 1rem;
}

.stats-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.stats-link:hover {
    color: #0056b3;
}

.chart-container {
    position: relative;
    height: 320px;
}

.chart-container-small {
    position: relative;
    height: 240px;
}

.activity-item {
    border-bottom: 1px solid #f1f3f4 !important;
    padding: 1rem 0;
}

.activity-item:last-child {
    border-bottom: none !important;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    margin-right: 1rem;
}

.low-attendance-item {
    transition: all 0.2s;
    border: 1px solid #e9ecef !important;
    background-color: #fff;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    padding: 0.75rem;
}

.low-attendance-item:hover {
    background-color: #fff5f5 !important;
    border-color: #fecaca !important;
    transform: translateY(-1px);
}

.progress-sm {
    height: 8px;
}

.card {
    transition: box-shadow 0.2s;
    border: none;
    border-radius: 12px;
}

.card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.08) !important;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
}

.card-header {
    background: transparent !important;
    border-bottom: 1px solid #f1f3f4;
    padding: 1.5rem 1.5rem 1rem;
}

.card-body {
    padding: 1.5rem;
}

@media (max-width: 768px) {
    .stats-number {
        font-size: 2rem;
    }
    
    .stats-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
}
</style>

<?php

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get counts for dashboard
try {
    // Count total students
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count total teachers
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $teacher_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count total classes
    $stmt = $db->query("SELECT COUNT(*) as count FROM classes");
    $class_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count total subjects
    $stmt = $db->query("SELECT COUNT(*) as count FROM subjects");
    $subject_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent activity logs
    $stmt = $db->query("SELECT al.*, u.name FROM activity_logs al 
                        LEFT JOIN users u ON al.user_id = u.id 
                        ORDER BY al.date DESC LIMIT 10");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance overview (last 30 days)
    $stmt = $db->query("SELECT 
                            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count
                        FROM attendance
                        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get classes with lowest attendance
    $stmt = $db->query("SELECT c.name as class_name, 
                               ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 2) as attendance_percentage
                        FROM classes c
                        LEFT JOIN users u ON u.class_id = c.id
                        LEFT JOIN attendance a ON a.student_id = u.id
                        GROUP BY c.id
                        ORDER BY attendance_percentage ASC
                        LIMIT 5");
    $low_attendance_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load dashboard data.';
}
?>

<!-- Dashboard Header -->
<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 fw-bold text-dark">
                <i class="fas fa-tachometer-alt me-3 text-primary"></i>Administrative Dashboard
            </h1>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-2"></i>Today is <?php echo date('l, F j, Y'); ?>
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="<?php echo URL_ROOT; ?>/admin/add-user.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New User
            </a>
        </div>
    </div>

    <!-- Dashboard Statistics -->
    <div class="row g-4 mb-4">
        <!-- Students Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-primary mx-auto">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $student_count; ?></div>
                    <div class="stats-label">Total Students</div>
                    <a href="<?php echo URL_ROOT; ?>/admin/users.php?role=student" class="stats-link">
                        View All Students <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Teachers Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-success mx-auto">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $teacher_count; ?></div>
                    <div class="stats-label">Total Teachers</div>
                    <a href="<?php echo URL_ROOT; ?>/admin/users.php?role=teacher" class="stats-link">
                        View All Teachers <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Classes Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-info mx-auto">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $class_count; ?></div>
                    <div class="stats-label">Total Classes</div>
                    <a href="<?php echo URL_ROOT; ?>/admin/classes.php" class="stats-link">
                        Manage Classes <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Subjects Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-warning mx-auto">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stats-number text-dark"><?php echo $subject_count; ?></div>
                    <div class="stats-label">Total Subjects</div>
                    <a href="<?php echo URL_ROOT; ?>/admin/subjects.php" class="stats-link">
                        Manage Subjects <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Overview & Analytics -->
    <div class="row g-4 mb-4">
        <!-- Attendance Chart -->
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-bar me-2 text-primary"></i>Attendance Overview
                        </h5>
                        <span class="badge bg-light text-dark">Last 30 Days</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-4 text-center">
                            <div class="stats-mini">
                                <div class="h5 mb-1 text-success"><?php echo $attendance_stats['present_count'] ?? 0; ?></div>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="stats-mini">
                                <div class="h5 mb-1 text-danger"><?php echo $attendance_stats['absent_count'] ?? 0; ?></div>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="stats-mini">
                                <div class="h5 mb-1 text-warning"><?php echo $attendance_stats['late_count'] ?? 0; ?></div>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container-small">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Activities Column -->
        <div class="col-lg-7">
            <!-- Low Attendance Classes -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Attendance Alert
                            </h5>
                            <small class="text-muted">Classes requiring attention</small>
                        </div>
                        <?php if (count($low_attendance_classes) > 2): ?>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lowAttendanceCollapse" aria-expanded="false" aria-controls="lowAttendanceCollapse">
                                <span class="badge bg-warning text-dark me-2"><?php echo count($low_attendance_classes); ?></span>
                                <i class="fas fa-chevron-down" id="lowAttendanceIcon"></i>
                            </button>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?php echo count($low_attendance_classes); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($low_attendance_classes)): ?>
                        <div class="low-attendance-list">
                            <!-- Always show first 2 entries -->
                            <?php foreach (array_slice($low_attendance_classes, 0, 2) as $class): ?>
                                <div class="low-attendance-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-medium"><?php echo $class['class_name']; ?></span>
                                        <span class="badge <?php echo ($class['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE) ? 'bg-danger' : 'bg-warning'; ?> rounded-pill">
                                            <?php echo $class['attendance_percentage']; ?>%
                                        </span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar <?php echo ($class['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE) ? 'bg-danger' : 'bg-warning'; ?>" 
                                             style="width: <?php echo $class['attendance_percentage']; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Collapsible section for remaining entries -->
                            <?php if (count($low_attendance_classes) > 2): ?>
                                <div class="collapse" id="lowAttendanceCollapse">
                                    <?php foreach (array_slice($low_attendance_classes, 2) as $class): ?>
                                        <div class="low-attendance-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-medium"><?php echo $class['class_name']; ?></span>
                                                <span class="badge <?php echo ($class['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE) ? 'bg-danger' : 'bg-warning'; ?> rounded-pill">
                                                    <?php echo $class['attendance_percentage']; ?>%
                                                </span>
                                            </div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar <?php echo ($class['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE) ? 'bg-danger' : 'bg-warning'; ?>" 
                                                     style="width: <?php echo $class['attendance_percentage']; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?php echo URL_ROOT; ?>/admin/reports.php" class="btn btn-outline-primary btn-sm">
                                View Detailed Reports
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h6 class="text-muted">All Good!</h6>
                            <p class="text-muted mb-0">No attendance issues detected.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2 text-primary"></i>Recent Activity
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary rounded-pill me-3"><?php echo count($activities); ?> activities</span>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#recentActivitiesCollapse" aria-expanded="false" aria-controls="recentActivitiesCollapse">
                                <i class="fas fa-chevron-down" id="collapseIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="collapse" id="recentActivitiesCollapse">
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($activities)): ?>
                            <div class="activity-feed">
                                <?php foreach($activities as $activity): ?>
                                    <div class="activity-item d-flex">
                                        <div class="activity-icon bg-primary">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="activity-content flex-grow-1">
                                            <div class="activity-header d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo $activity['name'] ?? 'Unknown User'; ?></strong>
                                                    <span class="text-muted">performed</span>
                                                    <strong class="text-primary"><?php echo $activity['action']; ?></strong>
                                                </div>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo formatDate($activity['date'], 'M j, g:i A'); ?>
                                                </span>
                                            </div>
                                            <div class="activity-details text-muted mt-1">
                                                <?php echo $activity['details']; ?>
                                            </div>
                                            <div class="activity-meta mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>IP: <?php echo $activity['ip_address']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-2"></i>View All Activities
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times text-muted fa-3x mb-3"></i>
                                <h6 class="text-muted">No Recent Activities</h6>
                                <p class="text-muted mb-0">System activities will appear here as they occur.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history me-2 text-primary"></i>Recent System Activity
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary rounded-pill me-3"><?php echo count($activities); ?> activities</span>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#recentActivitiesCollapse" aria-expanded="false" aria-controls="recentActivitiesCollapse">
                                <i class="fas fa-chevron-down" id="collapseIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="collapse" id="recentActivitiesCollapse">
                    <div class="card-body">
                        <?php if (!empty($activities)): ?>
                            <div class="activity-feed">
                                <?php foreach($activities as $activity): ?>
                                    <div class="activity-item d-flex">
                                        <div class="activity-icon bg-primary">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="activity-content flex-grow-1">
                                            <div class="activity-header d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo $activity['name'] ?? 'Unknown User'; ?></strong>
                                                    <span class="text-muted">performed</span>
                                                    <strong class="text-primary"><?php echo $activity['action']; ?></strong>
                                                </div>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo formatDate($activity['date'], 'M j, g:i A'); ?>
                                                </span>
                                            </div>
                                            <div class="activity-details text-muted mt-1">
                                                <?php echo $activity['details']; ?>
                                            </div>
                                            <div class="activity-meta mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>IP: <?php echo $activity['ip_address']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-2"></i>View All Activities
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times text-muted fa-3x mb-3"></i>
                                <h6 class="text-muted">No Recent Activities</h6>
                                <p class="text-muted mb-0">System activities will appear here as they occur.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize attendance chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                label: 'Attendance Count',
                data: [
                    <?php echo $attendance_stats['present_count'] ?? 0; ?>,
                    <?php echo $attendance_stats['absent_count'] ?? 0; ?>,
                    <?php echo $attendance_stats['late_count'] ?? 0; ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(255, 193, 7, 0.7)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Handle collapse icon rotation for recent activities
    const collapseElement = document.getElementById('recentActivitiesCollapse');
    const collapseIcon = document.getElementById('collapseIcon');
    
    if (collapseElement && collapseIcon) {
        collapseElement.addEventListener('show.bs.collapse', function () {
            collapseIcon.classList.remove('fa-chevron-down');
            collapseIcon.classList.add('fa-chevron-up');
        });
        
        collapseElement.addEventListener('hide.bs.collapse', function () {
            collapseIcon.classList.remove('fa-chevron-up');
            collapseIcon.classList.add('fa-chevron-down');
        });
    }
    
    // Handle collapse icon rotation for low attendance alert
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
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
