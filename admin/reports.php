<?php
// Include header file
require_once '../includes/header.php';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get filter parameters
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d'); // Today

// Get classes for filter
try {
    $stmt = $db->query("SELECT id, name FROM classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $classes = [];
}

// Get subjects for filter
try {
    if ($class_id > 0) {
        $stmt = $db->prepare("SELECT s.id, s.name, s.code 
                              FROM subjects s
                              JOIN class_subject cs ON s.id = cs.subject_id
                              WHERE cs.class_id = ?
                              GROUP BY s.id
                              ORDER BY s.name");
        $stmt->execute([$class_id]);
    } else {
        $stmt = $db->query("SELECT id, name, code FROM subjects ORDER BY name");
    }
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subjects = [];
}

// Get attendance data based on filters
$attendance_data = [];
$attendance_summary = [
    'total_students' => 0,
    'total_days' => 0,
    'present_count' => 0,
    'absent_count' => 0,
    'late_count' => 0,
    'average_attendance' => 0
];

if (isset($_GET['generate']) && validateDate($date_from) && validateDate($date_to)) {
    try {
        // Build query based on filters
        $query = "SELECT u.id as student_id, u.name as student_name, 
                         c.name as class_name, c.id as class_id,
                         s.name as subject_name, s.id as subject_id,
                         COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                         COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                         COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                         COUNT(a.id) as total_days
                  FROM users u
                  JOIN classes c ON u.class_id = c.id
                  JOIN attendance a ON u.id = a.student_id
                  JOIN subjects s ON a.subject_id = s.id
                  WHERE u.role = 'student' AND a.date BETWEEN ? AND ?";
        
        $params = [$date_from, $date_to];
        
        // Add class filter if selected
        if ($class_id > 0) {
            $query .= " AND c.id = ?";
            $params[] = $class_id;
        }
        
        // Add subject filter if selected
        if ($subject_id > 0) {
            $query .= " AND s.id = ?";
            $params[] = $subject_id;
        }
        
        $query .= " GROUP BY u.id, s.id
                    ORDER BY c.name, u.name, s.name";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate overall summary
        if (!empty($attendance_data)) {
            $total_present = array_sum(array_column($attendance_data, 'present_count'));
            $total_absent = array_sum(array_column($attendance_data, 'absent_count'));
            $total_late = array_sum(array_column($attendance_data, 'late_count'));
            $total_days = array_sum(array_column($attendance_data, 'total_days'));
            $total_students = count(array_unique(array_column($attendance_data, 'student_id')));
            
            $attendance_summary = [
                'total_students' => $total_students,
                'total_days' => $total_days / ($total_students ?: 1), // Average days per student
                'present_count' => $total_present,
                'absent_count' => $total_absent,
                'late_count' => $total_late,
                'average_attendance' => ($total_days > 0) ? round(($total_present / $total_days) * 100, 2) : 0
            ];
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $_SESSION['error'] = 'Failed to generate report: ' . $e->getMessage();
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
        page-break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
    }
    
    .table {
        font-size: 12px;
    }
    
    .btn {
        display: none !important;
    }
    
    .chart-container {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        padding: 2px 6px !important;
    }
}
</style>

<!-- Attendance Reports -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h2><i class="fas fa-chart-bar me-2"></i>Attendance Reports</h2>
        <p class="text-muted mb-0">Generate comprehensive attendance reports for all classes and subjects</p>
    </div>
    <div>
        <a href="<?php echo URL_ROOT; ?>/admin/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Report Filters -->
<div class="card mb-4 no-print">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Report Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
            <input type="hidden" name="generate" value="true">
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="class_id" class="form-label">Class</label>
                    <select class="form-select" id="class_id" name="class_id">
                        <option value="0">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select class="form-select" id="subject_id" name="subject_id">
                        <option value="0">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo ($subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo $subject['name']; ?> (<?php echo $subject['code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>" required>
                    <div class="invalid-feedback">Please select a start date.</div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>" required>
                    <div class="invalid-feedback">Please select an end date.</div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-chart-line me-2"></i>Generate Report
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-undo me-2"></i>Reset Filters
                </a>
                <?php if (isset($_GET['generate']) && !empty($attendance_data)): ?>
                    <button type="button" class="btn btn-success ms-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['generate'])): ?>
    <!-- Report Header -->
    <div class="card mb-4">
        <div class="card-body text-center">
            <h3 class="mb-3"><?php echo SITE_NAME; ?> - Administrative Attendance Report</h3>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Class:</strong> <?php echo $class_id > 0 ? $classes[array_search($class_id, array_column($classes, 'id'))]['name'] : 'All Classes'; ?></p>
                    <p class="mb-1"><strong>Subject:</strong> <?php echo $subject_id > 0 ? $subjects[array_search($subject_id, array_column($subjects, 'id'))]['name'] : 'All Subjects'; ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Period:</strong> <?php echo formatDate($date_from); ?> to <?php echo formatDate($date_to); ?></p>
                    <p class="mb-1"><strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Summary -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card report-card h-100 border-primary">
                <div class="card-body text-center">
                    <div class="stats-number text-primary"><?php echo $attendance_summary['total_students']; ?></div>
                    <div class="text-muted">Total Students</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card report-card h-100 border-info">
                <div class="card-body text-center">
                    <div class="stats-number text-info"><?php echo round($attendance_summary['total_days'], 1); ?></div>
                    <div class="text-muted">Average Days</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card report-card h-100 border-success">
                <div class="card-body text-center">
                    <div class="stats-number text-success"><?php echo $attendance_summary['average_attendance']; ?>%</div>
                    <div class="text-muted">Average Attendance</div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card report-card h-100 border-warning">
                <div class="card-body text-center">
                    <div class="stats-number text-warning"><?php echo $attendance_summary['present_count'] + $attendance_summary['absent_count'] + $attendance_summary['late_count']; ?></div>
                    <div class="text-muted">Total Records</div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($attendance_data)): ?>
        <!-- Attendance Chart -->
        <div class="card mb-4 no-print">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Attendance Overview
                </h5>
            </div>
            <div class="card-body">
                <div class="attendance-chart-container">
                    <canvas id="attendanceChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <?php
            // Build at-risk list (percentage below threshold)
            $at_risk = [];
            foreach ($attendance_data as $row) {
                $total = $row['total_days'] ?: 1;
                $pct = ($row['present_count'] / $total) * 100;
                if ($pct < MIN_ATTENDANCE_PERCENTAGE) {
                    $row['attendance_pct'] = round($pct, 2);
                    $at_risk[] = $row;
                }
            }
        ?>
        <?php if (!empty($at_risk)): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2"></i>At-Risk Attendance (Below <?php echo MIN_ATTENDANCE_PERCENTAGE; ?>%)</h5>
                <span class="badge bg-dark"><?php echo count($at_risk); ?> record(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($at_risk as $risk): ?>
                                <tr class="table-danger">
                                    <td><?php echo $risk['student_name']; ?></td>
                                    <td><?php echo $risk['class_name']; ?></td>
                                    <td><?php echo $risk['subject_name']; ?></td>
                                    <td><?php echo $risk['total_days']; ?></td>
                                    <td><?php echo $risk['present_count']; ?></td>
                                    <td><?php echo $risk['absent_count']; ?></td>
                                    <td><?php echo $risk['late_count']; ?></td>
                                    <td><strong><?php echo $risk['attendance_pct']; ?>%</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted d-block mt-2">These subject-level records fall below the configured minimum attendance percentage.</small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Detailed Report -->
        <div class="card" id="reportContent">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-table me-2"></i>Detailed Attendance Report
                </h5>
                <div class="no-print">
                    <button class="btn btn-sm btn-outline-primary" onclick="printDetailedReport()">
                        <i class="fas fa-print me-2"></i>Print Table
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead class="table-dark">
                            <tr>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Late</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_data as $row): ?>
                                <?php 
                                    $total = $row['total_days'] ?: 1; // Avoid division by zero
                                    $percentage = round(($row['present_count'] / $total) * 100, 2);
                                    $status_class = $percentage < MIN_ATTENDANCE_PERCENTAGE ? 'bg-danger text-white' : 'bg-success text-white';
                                    $status_text = $percentage < MIN_ATTENDANCE_PERCENTAGE ? 'Low' : 'Good';
                                ?>
                                <tr>
                                    <td><?php echo $row['student_name']; ?></td>
                                    <td><?php echo $row['class_name']; ?></td>
                                    <td><?php echo $row['subject_name']; ?></td>
                                    <td><?php echo $row['total_days']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $row['present_count']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $row['absent_count']; ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo $row['late_count']; ?></span></td>
                                    <td><strong><?php echo $percentage; ?>%</strong></td>
                                    <td>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Print Summary for Report -->
                <div class="row mt-4 d-none d-print-block">
                    <div class="col-12">
                        <h6>Report Summary:</h6>
                        <p class="mb-1">Total Students: <strong><?php echo $attendance_summary['total_students']; ?></strong></p>
                        <p class="mb-1">Average Attendance: <strong><?php echo $attendance_summary['average_attendance']; ?>%</strong></p>
                        <p class="mb-1">Total Present: <strong><?php echo $attendance_summary['present_count']; ?></strong></p>
                        <p class="mb-1">Total Absent: <strong><?php echo $attendance_summary['absent_count']; ?></strong></p>
                        <p class="mb-0">Total Late: <strong><?php echo $attendance_summary['late_count']; ?></strong></p>
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
                                <?php echo $attendance_summary['present_count']; ?>,
                                <?php echo $attendance_summary['absent_count']; ?>,
                                <?php echo $attendance_summary['late_count']; ?>
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
                                ticks: { precision: 0 } 
                            } 
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' records';
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No attendance records found for the selected filters.
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update subjects dropdown based on selected class
    document.getElementById('class_id').addEventListener('change', function() {
        const classId = this.value;
        const currentUrl = new URL(window.location.href);
        
        currentUrl.searchParams.set('class_id', classId);
        // Reset subject_id when class changes
        currentUrl.searchParams.delete('subject_id');
        // Keep other parameters
        if (currentUrl.searchParams.has('generate')) {
            currentUrl.searchParams.set('generate', 'true');
        }
        
        window.location.href = currentUrl.toString();
    });
});

// Enhanced print function for detailed reports
function printDetailedReport() {
    const reportElement = document.getElementById('reportContent');
    if (!reportElement) {
        alert('Report content not found');
        return;
    }
    
    // Get filter information for report header
    const classSelect = document.getElementById('class_id');
    const subjectSelect = document.getElementById('subject_id');
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    
    const className = classSelect.options[classSelect.selectedIndex].text;
    const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
    
    // Create comprehensive print content
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${className} - Attendance Report</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 20px; 
                    color: #000;
                    background: white;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #000;
                }
                .header h1 { margin: 0; font-size: 24px; }
                .header h2 { margin: 5px 0; font-size: 18px; color: #666; }
                .report-info {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    font-size: 12px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-bottom: 20px;
                    font-size: 11px;
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 6px; 
                    text-align: left; 
                }
                th { 
                    background-color: #f2f2f2; 
                    font-weight: bold;
                    text-align: center;
                }
                .text-center { text-align: center; }
                .badge { 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-size: 10px;
                    border: 1px solid #ccc;
                }
                .summary-box {
                    background: #f8f9fa;
                    padding: 15px;
                    border: 1px solid #ddd;
                    margin-top: 20px;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #ccc;
                    font-size: 10px;
                    text-align: center;
                    color: #666;
                }
                @media print {
                    body { margin: 0.5cm; }
                    .page-break { page-break-before: always; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo SITE_NAME; ?></h1>
                <h2>Administrative Attendance Report</h2>
            </div>
            
            <div class="report-info">
                <div>
                    <strong>Class:</strong> ${className}<br>
                    <strong>Subject:</strong> ${subjectName}
                </div>
                <div>
                    <strong>Period:</strong> ${dateFrom} to ${dateTo}<br>
                    <strong>Generated:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                </div>
            </div>
            
            ${reportElement.querySelector('.table-responsive').innerHTML}
            
            <div class="summary-box">
                <h4>Report Summary</h4>
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>Total Students:</strong> <?php echo $attendance_summary['total_students']; ?><br>
                        <strong>Average Days:</strong> <?php echo round($attendance_summary['total_days'], 1); ?>
                    </div>
                    <div>
                        <strong>Present Records:</strong> <?php echo $attendance_summary['present_count']; ?><br>
                        <strong>Absent Records:</strong> <?php echo $attendance_summary['absent_count']; ?>
                    </div>
                    <div>
                        <strong>Late Records:</strong> <?php echo $attendance_summary['late_count']; ?><br>
                        <strong>Overall Attendance:</strong> <?php echo $attendance_summary['average_attendance']; ?>%
                    </div>
                </div>
            </div>
            
            <div class="footer">
                This report was generated automatically by the Student Attendance Management System.<br>
                For questions or concerns, please contact the system administrator.
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Print after content is loaded
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Legacy print function - now enhanced
function printReport(elementId) {
    // Use the enhanced print function instead
    printDetailedReport();
}
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
