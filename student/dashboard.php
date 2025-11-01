<?php
// Include header file
require_once '../includes/header.php';
?>

<style>
/* Student Dashboard Custom Styles */
.student-card {
    border: none;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.student-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.profile-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
}

.profile-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.2);
    object-fit: cover;
}

.stats-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: none;
    border-radius: 12px;
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
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
}

.chart-container {
    position: relative;
    height: 280px;
}

.attendance-item {
    border-bottom: 1px solid #f1f3f4;
    padding: 0.75rem 0;
}

.attendance-item:last-child {
    border-bottom: none;
}

.subject-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
    background: #fff;
}

.subject-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 15px rgba(0,123,255,0.1);
    transform: translateY(-1px);
}

.progress-custom {
    height: 12px;
    border-radius: 6px;
}

.alert-custom {
    border: none;
    border-radius: 12px;
    padding: 1.5rem;
    border-left: 4px solid #dc3545;
}

.btn-gradient {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
    color: white;
    transition: all 0.2s;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: translateY(-1px);
    color: white;
}

.card {
    transition: box-shadow 0.2s;
    border: none;
    border-radius: 12px;
}

.card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.08) !important;
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
    .profile-section {
        text-align: center;
    }
    
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
    
    // If no class is assigned
    if (empty($student['class_id'])) {
        $_SESSION['warning'] = 'You are not assigned to any class. Please contact the administrator.';
    }
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load student data.';
    $student = [];
}

// Get subjects for the student's class
try {
    $stmt = $db->prepare("SELECT s.id, s.name, s.code
                          FROM subjects s
                          JOIN class_subject cs ON s.id = cs.subject_id
                          WHERE cs.class_id = ?
                          ORDER BY s.name");
    $stmt->execute([$student['class_id']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subjects = [];
}

// Get overall attendance statistics
try {
    $stmt = $db->prepare("SELECT 
                            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                            COUNT(a.id) as total_count
                        FROM attendance a
                        WHERE a.student_id = ?");
    $stmt->execute([$student_id]);
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate attendance percentage
    $total_count = $attendance_stats['total_count'] ?: 1; // Avoid division by zero
    $present_percentage = round(($attendance_stats['present_count'] / $total_count) * 100, 2);
    $absent_percentage = round(($attendance_stats['absent_count'] / $total_count) * 100, 2);
    $late_percentage = round(($attendance_stats['late_count'] / $total_count) * 100, 2);
    
    // Check if attendance is below minimum
    $is_low_attendance = $present_percentage < MIN_ATTENDANCE_PERCENTAGE;
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $attendance_stats = ['present_count' => 0, 'absent_count' => 0, 'late_count' => 0, 'total_count' => 0];
    $present_percentage = $absent_percentage = $late_percentage = 0;
    $is_low_attendance = false;
}

// Get subject-wise attendance
try {
    $stmt = $db->prepare("SELECT s.name as subject_name, s.code as subject_code,
                            COUNT(a.id) as total_days,
                            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                          FROM attendance a
                          JOIN subjects s ON a.subject_id = s.id
                          WHERE a.student_id = ?
                          GROUP BY s.id
                          ORDER BY attendance_percentage ASC");
    $stmt->execute([$student_id]);
    $subject_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subject_attendance = [];
}

// Get recent attendance records
try {
    $stmt = $db->prepare("SELECT a.id, a.date, a.status, s.name as subject_name
                          FROM attendance a
                          JOIN subjects s ON a.subject_id = s.id
                          WHERE a.student_id = ?
                          ORDER BY a.date DESC, s.name
                          LIMIT 10");
    $stmt->execute([$student_id]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $recent_attendance = [];
}
?>

<!-- Student Dashboard -->
<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 fw-bold text-dark">
                <i class="fas fa-graduation-cap me-3 text-primary"></i>Student Portal
            </h1>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-2"></i>Today is <?php echo date('l, F j, Y'); ?>
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="<?php echo URL_ROOT; ?>/student/subject-charts.php" class="btn btn-gradient">
                <i class="fas fa-chart-bar me-2"></i>View Attendance
            </a>
        </div>
    </div>

    <?php if ($is_low_attendance): ?>
        <div class="alert alert-danger alert-custom mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Low Attendance Alert</h5>
                    <p class="mb-0">Your overall attendance is below the minimum required percentage (<?php echo MIN_ATTENDANCE_PERCENTAGE; ?>%). 
                       Please improve your attendance to avoid any academic penalties.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Student Profile Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card student-card">
                <div class="profile-section">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <img src="https://via.placeholder.com/150" alt="Student Profile" class="profile-img mb-3">
                        </div>
                        <div class="col-md-9">
                            <h2 class="mb-2"><?php echo $student['name']; ?></h2>
                            <p class="mb-3 opacity-75">
                                <i class="fas fa-envelope me-2"></i><?php echo $student['email']; ?>
                            </p>
                            
                            <div class="row g-4">
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-school me-2"></i>
                                        <div>
                                            <small class="opacity-75">Class</small>
                                            <div class="fw-medium"><?php echo $student['class_name'] ?? 'Not Assigned'; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-check me-2"></i>
                                        <div>
                                            <small class="opacity-75">Status</small>
                                            <div>
                                                <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> bg-opacity-25 text-white">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-chart-line me-2"></i>
                                        <div>
                                            <small class="opacity-75">Attendance</small>
                                            <div>
                                                <span class="badge bg-<?php echo $present_percentage < MIN_ATTENDANCE_PERCENTAGE ? 'danger' : 'success'; ?> bg-opacity-25 text-white">
                                                    <?php echo $present_percentage; ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        <div>
                                            <small class="opacity-75">Total Days</small>
                                            <div class="fw-medium"><?php echo $attendance_stats['total_count']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="row g-4 mb-4">
        <!-- Present Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-success mx-auto">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stats-number text-success"><?php echo $attendance_stats['present_count']; ?></div>
                    <div class="stats-label">Present Days</div>
                    <small class="text-muted"><?php echo $present_percentage; ?>% of total</small>
                </div>
            </div>
        </div>
        
        <!-- Absent Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-danger mx-auto">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="stats-number text-danger"><?php echo $attendance_stats['absent_count']; ?></div>
                    <div class="stats-label">Absent Days</div>
                    <small class="text-muted"><?php echo $absent_percentage; ?>% of total</small>
                </div>
            </div>
        </div>
        
        <!-- Late Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-warning mx-auto">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number text-warning"><?php echo $attendance_stats['late_count']; ?></div>
                    <div class="stats-label">Late Days</div>
                    <small class="text-muted"><?php echo $late_percentage; ?>% of total</small>
                </div>
            </div>
        </div>
        
        <!-- Total Card -->
        <div class="col-lg-3 col-md-6">
            <div class="card stats-card shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="stats-icon bg-primary mx-auto">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stats-number text-primary"><?php echo $attendance_stats['total_count']; ?></div>
                    <div class="stats-label">Total Days</div>
                    <small class="text-muted">Academic period</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Recent Activity -->
    <div class="row g-4 mb-4">
        <!-- Attendance Chart -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2 text-primary"></i>Attendance Overview
                    </h5>
                    <small class="text-muted">Visual breakdown of your attendance</small>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history me-2 text-primary"></i>Recent Activity
                            </h5>
                            <small class="text-muted">Latest attendance records</small>
                        </div>
                        <a href="<?php echo URL_ROOT; ?>/student/view-attendance.php" class="btn btn-outline-primary btn-sm">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_attendance)): ?>
                        <div class="attendance-list">
                            <?php foreach (array_slice($recent_attendance, 0, 6) as $attendance): ?>
                                <div class="attendance-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="attendance-status me-3">
                                            <span class="badge <?php echo getAttendanceStatusClass($attendance['status']); ?> rounded-circle p-2">
                                                <i class="fas fa-<?php echo $attendance['status'] === 'present' ? 'check' : ($attendance['status'] === 'absent' ? 'times' : 'clock'); ?> fa-xs"></i>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo $attendance['subject_name']; ?></div>
                                            <small class="text-muted"><?php echo formatDate($attendance['date']); ?></small>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo getAttendanceStatusClass($attendance['status']); ?> bg-opacity-10 text-dark">
                                        <?php echo ucfirst($attendance['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times text-muted fa-3x mb-3"></i>
                            <p class="text-muted mb-0">No attendance records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject-wise Attendance -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <i class="fas fa-book me-2 text-primary"></i>Subject-wise Performance
                            </h5>
                            <small class="text-muted">Detailed breakdown by subject</small>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo count($subject_attendance); ?> subjects</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($subject_attendance)): ?>
                        <div class="row g-3">
                            <?php foreach ($subject_attendance as $subject): ?>
                                <div class="col-lg-6 col-xl-4">
                                    <div class="subject-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo $subject['subject_name']; ?></h6>
                                                <small class="text-muted"><?php echo $subject['subject_code']; ?></small>
                                            </div>
                                            <span class="badge <?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'bg-danger' : 'bg-success'; ?> bg-opacity-10 text-dark">
                                                <?php echo $subject['attendance_percentage']; ?>%
                                            </span>
                                        </div>
                                        
                                        <div class="progress progress-custom mb-3">
                                            <div class="progress-bar <?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'bg-danger' : 'bg-success'; ?>" 
                                                 style="width: <?php echo $subject['attendance_percentage']; ?>%">
                                            </div>
                                        </div>
                                        
                                        <div class="row text-center g-2">
                                            <div class="col-4">
                                                <div class="stat-mini">
                                                    <div class="fw-bold text-success"><?php echo $subject['present_days']; ?></div>
                                                    <small class="text-muted">Present</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-mini">
                                                    <div class="fw-bold text-danger"><?php echo $subject['absent_days']; ?></div>
                                                    <small class="text-muted">Absent</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-mini">
                                                    <div class="fw-bold text-warning"><?php echo $subject['late_days']; ?></div>
                                                    <small class="text-muted">Late</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center mt-3">
                                            <span class="badge <?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'bg-danger' : 'bg-success'; ?>">
                                                <?php echo $subject['attendance_percentage'] < MIN_ATTENDANCE_PERCENTAGE ? 'Needs Improvement' : 'Good Standing'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="<?php echo URL_ROOT; ?>/student/subject-charts.php" class="btn btn-gradient me-2">
                                <i class="fas fa-chart-bar me-2"></i>View Detailed Charts
                            </a>
                            <a href="<?php echo URL_ROOT; ?>/student/view-attendance.php" class="btn btn-outline-primary">
                                <i class="fas fa-table me-2"></i>View Table Format
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book-open text-muted fa-3x mb-3"></i>
                            <h6 class="text-muted">No Subject Data</h6>
                            <p class="text-muted mb-0">No attendance records found for any subject.</p>
                        </div>
                    <?php endif; ?>
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
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Late'],
            datasets: [{
                data: [
                    <?php echo $attendance_stats['present_count']; ?>,
                    <?php echo $attendance_stats['absent_count']; ?>,
                    <?php echo $attendance_stats['late_count']; ?>
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
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
