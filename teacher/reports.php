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

// Initialize filters
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';

// Get classes assigned to the teacher
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

// Get subjects assigned to the teacher for the selected class
try {
    $params = [$teacher_id];
    $query = "SELECT s.id, s.name, s.code
              FROM subjects s
              JOIN class_subject cs ON s.id = cs.subject_id
              WHERE cs.teacher_id = ?";
    
    if ($class_id > 0) {
        $query .= " AND cs.class_id = ?";
        $params[] = $class_id;
    }
    
    $query .= " ORDER BY s.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subjects = [];
}

// Generate report data
$report_data = [];
$report_summary = [];

if ($class_id > 0 && $subject_id > 0) {
    try {
        if ($report_type === 'detailed') {
            // Detailed report: Each student's daily attendance
            $stmt = $db->prepare("SELECT u.id as student_id, u.name as student_name, u.roll_number,
                                         a.date, a.status, a.remarks
                                  FROM users u
                                  JOIN classes c ON u.class_id = c.id
                                  LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ?
                                      AND a.date BETWEEN ? AND ?
                                  WHERE u.role = 'student' AND u.class_id = ?
                                  ORDER BY u.name, a.date");
            $stmt->execute([$subject_id, $date_from, $date_to, $class_id]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Summary report: Student-wise attendance statistics
            $stmt = $db->prepare("SELECT u.id as student_id, u.name as student_name, u.roll_number,
                                         COUNT(a.id) as total_days,
                                         COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                                         COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                                         COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                                         ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
                                  FROM users u
                                  JOIN classes c ON u.class_id = c.id
                                  LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ?
                                      AND a.date BETWEEN ? AND ?
                                  WHERE u.role = 'student' AND u.class_id = ?
                                  GROUP BY u.id, u.name, u.roll_number
                                  ORDER BY u.name");
            $stmt->execute([$subject_id, $date_from, $date_to, $class_id]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Generate overall summary
        $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) as total_students,
                                     COUNT(a.id) as total_records,
                                     COUNT(CASE WHEN a.status = 'present' THEN 1 END) as total_present,
                                     COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
                                     COUNT(CASE WHEN a.status = 'late' THEN 1 END) as total_late,
                                     ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as overall_percentage
                              FROM users u
                              JOIN classes c ON u.class_id = c.id
                              LEFT JOIN attendance a ON u.id = a.student_id AND a.subject_id = ?
                                  AND a.date BETWEEN ? AND ?
                              WHERE u.role = 'student' AND u.class_id = ?");
        $stmt->execute([$subject_id, $date_from, $date_to, $class_id]);
        $report_summary = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $report_data = [];
        $report_summary = [];
    }
}

// Get class and subject names for display
$class_name = '';
$subject_name = '';
if ($class_id > 0) {
    foreach ($classes as $class) {
        if ($class['id'] == $class_id) {
            $class_name = $class['name'];
            break;
        }
    }
}
if ($subject_id > 0) {
    foreach ($subjects as $subject) {
        if ($subject['id'] == $subject_id) {
            $subject_name = $subject['name'];
            break;
        }
    }
}
?>

<style>
.report-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .container-fluid {
        margin: 0;
        padding: 0;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<!-- Class Reports -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h2><i class="fas fa-chart-bar me-2"></i>Class Reports</h2>
        <p class="text-muted mb-0">Generate attendance reports for your classes</p>
    </div>
    <div>
        <a href="<?php echo URL_ROOT; ?>/teacher/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Filter Section -->
<div class="card mb-4 no-print">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Report Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <!-- Class Filter -->
            <div class="col-md-3">
                <label for="class_id" class="form-label">Class *</label>
                <select name="class_id" id="class_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo $class['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Subject Filter -->
            <div class="col-md-3">
                <label for="subject_id" class="form-label">Subject *</label>
                <select name="subject_id" id="subject_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo $subject['name'] . ' (' . $subject['code'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range -->
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
            
            <!-- Report Type -->
            <div class="col-md-2">
                <label for="report_type" class="form-label">Report Type</label>
                <select name="report_type" id="report_type" class="form-select">
                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary</option>
                    <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                </select>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-line me-2"></i>Generate Report
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-undo me-2"></i>Reset Filters
                </a>
                <?php if (!empty($report_data)): ?>
                    <button type="button" class="btn btn-success ms-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($class_id > 0 && $subject_id > 0): ?>
    <!-- Report Header -->
    <div class="card mb-4">
        <div class="card-body text-center">
            <h3 class="mb-3"><?php echo SITE_NAME; ?> - Attendance Report</h3>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Class:</strong> <?php echo $class_name; ?></p>
                    <p class="mb-1"><strong>Subject:</strong> <?php echo $subject_name; ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Period:</strong> <?php echo formatDate($date_from); ?> to <?php echo formatDate($date_to); ?></p>
                    <p class="mb-1"><strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($report_summary)): ?>
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card report-card h-100 border-primary">
                    <div class="card-body text-center">
                        <div class="stats-number text-primary"><?php echo $report_summary['total_students']; ?></div>
                        <div class="text-muted">Total Students</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card report-card h-100 border-success">
                    <div class="card-body text-center">
                        <div class="stats-number text-success"><?php echo $report_summary['total_present']; ?></div>
                        <div class="text-muted">Total Present</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card report-card h-100 border-danger">
                    <div class="card-body text-center">
                        <div class="stats-number text-danger"><?php echo $report_summary['total_absent']; ?></div>
                        <div class="text-muted">Total Absent</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card report-card h-100 border-info">
                    <div class="card-body text-center">
                        <div class="stats-number text-info"><?php echo $report_summary['overall_percentage']; ?>%</div>
                        <div class="text-muted">Overall Attendance</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Report Data -->
    <?php if (!empty($report_data)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>
                    <?php echo ucfirst($report_type); ?> Report
                </h5>
            </div>
            <div class="card-body">
                <?php if ($report_type === 'summary'): ?>
                    <!-- Summary Report Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Roll No.</th>
                                    <th>Student Name</th>
                                    <th>Total Days</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Attendance %</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $student): ?>
                                    <?php 
                                    $percentage = $student['attendance_percentage'] ?: 0;
                                    $status_class = $percentage >= 75 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger');
                                    $status_text = $percentage >= 75 ? 'Good' : ($percentage >= 50 ? 'Average' : 'Poor');
                                    ?>
                                    <tr>
                                        <td><?php echo $student['roll_number']; ?></td>
                                        <td><?php echo $student['student_name']; ?></td>
                                        <td><?php echo $student['total_days'] ?: 0; ?></td>
                                        <td><span class="badge bg-success"><?php echo $student['present_days'] ?: 0; ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo $student['absent_days'] ?: 0; ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo $student['late_days'] ?: 0; ?></span></td>
                                        <td><strong><?php echo $percentage; ?>%</strong></td>
                                        <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <!-- Detailed Report Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Roll No.</th>
                                    <th>Student Name</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $record): ?>
                                    <tr>
                                        <td><?php echo $record['date'] ? formatDate($record['date']) : '-'; ?></td>
                                        <td><?php echo $record['roll_number']; ?></td>
                                        <td><?php echo $record['student_name']; ?></td>
                                        <td>
                                            <?php if ($record['status']): ?>
                                                <span class="badge <?php echo getAttendanceStatusClass($record['status']); ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not marked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $record['remarks'] ?: '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No attendance data found for the selected criteria.
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Instructions -->
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle me-2"></i>How to Generate Reports</h5>
        <ol class="mb-0">
            <li>Select a <strong>Class</strong> from your assigned classes</li>
            <li>Choose a <strong>Subject</strong> that you teach for that class</li>
            <li>Set the <strong>Date Range</strong> for the report period</li>
            <li>Choose <strong>Report Type</strong>:
                <ul>
                    <li><strong>Summary:</strong> Student-wise attendance statistics</li>
                    <li><strong>Detailed:</strong> Day-by-day attendance records</li>
                </ul>
            </li>
            <li>Click <strong>Generate Report</strong> to view the results</li>
        </ol>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>