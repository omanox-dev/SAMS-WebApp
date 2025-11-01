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

// Get subjects for student's class
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

// Initialize filters
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get current subject name if filtering by subject
$current_subject = null;
if ($subject_id > 0) {
    foreach ($subjects as $subject) {
        if ($subject['id'] == $subject_id) {
            $current_subject = $subject;
            break;
        }
    }
}

// Build query based on filters
$params = [$student_id];
$query = "SELECT a.id, a.date, a.status, a.remarks, 
                 s.name as subject_name, s.code as subject_code 
          FROM attendance a 
          JOIN subjects s ON a.subject_id = s.id 
          WHERE a.student_id = ?";

if ($subject_id > 0) {
    $query .= " AND a.subject_id = ?";
    $params[] = $subject_id;
}

if ($month) {
    $query .= " AND DATE_FORMAT(a.date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY a.date DESC, s.name";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load attendance records.';
    $attendance_records = [];
}

// Prepare monthly stats
$months = [];
$current_month = date('Y-m');
for ($i = 0; $i < 12; $i++) {
    $month_key = date('Y-m', strtotime("-$i months"));
    $month_name = date('F Y', strtotime("-$i months"));
    $months[$month_key] = $month_name;
}

// Monthly attendance trend functionality removed
?>

<!-- Student Attendance View -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-calendar-check me-2"></i>My Attendance
            <?php if ($current_subject): ?>
                <small class="text-muted">- <?php echo $current_subject['name']; ?> (<?php echo $current_subject['code']; ?>)</small>
            <?php endif; ?>
        </h2>
        <?php if ($current_subject): ?>
            <div class="mt-2">
                <span class="badge bg-primary">
                    <i class="fas fa-filter me-1"></i>Filtered by Subject
                </span>
                <a href="<?php echo URL_ROOT; ?>/student/view-attendance.php" class="badge bg-secondary text-decoration-none ms-2">
                    <i class="fas fa-times me-1"></i>Clear Filter
                </a>
            </div>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($current_subject): ?>
            <a href="<?php echo URL_ROOT; ?>/student/subject-charts.php" class="btn btn-outline-success me-2">
                <i class="fas fa-chart-bar"></i> Back to Overview
            </a>
        <?php else: ?>
            <a href="<?php echo URL_ROOT; ?>/student/subject-charts.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-chart-bar"></i> Attendance Overview
            </a>
        <?php endif; ?>
        <a href="<?php echo URL_ROOT; ?>/student/dashboard.php" class="btn btn-secondary me-2">
            <i class="fas fa-arrow-left"></i> Dashboard
        </a>
    </div>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <!-- Subject Filter -->
            <div class="col-md-4">
                <label for="subject_id" class="form-label">Subject</label>
                <select name="subject_id" id="subject_id" class="form-select">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo $subject['name'] . ' (' . $subject['code'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Month Filter -->
            <div class="col-md-4">
                <label for="month" class="form-label">Month</label>
                <select name="month" id="month" class="form-select">
                    <option value="">All Time</option>
                    <?php foreach ($months as $month_key => $month_name): ?>
                        <option value="<?php echo $month_key; ?>" <?php echo $month == $month_key ? 'selected' : ''; ?>>
                            <?php echo $month_name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="present" <?php echo $status == 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="absent" <?php echo $status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                    <option value="late" <?php echo $status == 'late' ? 'selected' : ''; ?>>Late</option>
                </select>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-undo me-2"></i>Reset Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Attendance Records</h5>
        <span class="badge bg-primary"><?php echo count($attendance_records); ?> Records</span>
    </div>
    <div class="card-body">
        <?php if (!empty($attendance_records)): ?>
            <div class="table-responsive">
                <table id="attendanceTable" class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo formatDate($record['date']); ?></td>
                                <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                <td><?php echo $record['subject_name'] . ' (' . $record['subject_code'] . ')'; ?></td>
                                <td>
                                    <span class="badge <?php echo getAttendanceStatusClass($record['status']); ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $record['remarks'] ? $record['remarks'] : 'No remarks'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No attendance records found matching your filters.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search: "<i class='fas fa-search'></i>",
            searchPlaceholder: "Search records"
        }
    });
});

</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
