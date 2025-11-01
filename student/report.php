<?php
// Include header
require_once '../includes/header.php';

// Enforce student role
if (!hasRole('student')) {
    $_SESSION['error'] = 'Unauthorized access.';
    redirect(URL_ROOT . '/index.php');
}

$student_id = $_SESSION['user_id'];

// Input filters
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : date('Y-m-01');
$date_to   = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : date('Y-m-d');

// Validate dates (fallback if invalid)
if (!validateDate($date_from)) { $date_from = date('Y-m-01'); }
if (!validateDate($date_to)) { $date_to = date('Y-m-d'); }

$errors = [];
$success_message = '';

// Fetch subjects available to this student's class (via class_subject mapping)
$subjects = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT s.id, s.name, s.code
                           FROM users u
                           JOIN class_subject cs ON u.class_id = cs.class_id
                           JOIN subjects s ON cs.subject_id = s.id
                           WHERE u.id = ?
                           ORDER BY s.name");
    $stmt->execute([$student_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
}

// Build attendance query scoped to this student
$attendance_rows = [];
$summary = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0,
    'percentage' => 0
];

if (isset($_GET['generate'])) {
    try {
        $query = "SELECT a.date, a.status, s.name AS subject_name, s.code AS subject_code, s.id AS subject_id
                  FROM attendance a
                  JOIN subjects s ON a.subject_id = s.id
                  WHERE a.student_id = ? AND a.date BETWEEN ? AND ?";
        $params = [$student_id, $date_from, $date_to];
        if ($subject_id > 0) {
            $query .= " AND s.id = ?";
            $params[] = $subject_id;
        }
        $query .= " ORDER BY a.date DESC, s.name";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $attendance_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($attendance_rows) {
            foreach ($attendance_rows as $r) {
                $summary['total']++;
                if ($r['status'] === 'present') $summary['present']++;
                elseif ($r['status'] === 'absent') $summary['absent']++;
                elseif ($r['status'] === 'late') $summary['late']++;
            }
            if ($summary['total'] > 0) {
                $summary['percentage'] = round(($summary['present'] / $summary['total']) * 100, 2);
            }
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Failed to load attendance data.';
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-file-alt me-2"></i>My Attendance Report</h2>
    <a href="<?php echo URL_ROOT; ?>/student/dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="card-title mb-0">Filters</h5></div>
    <div class="card-body">
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <input type="hidden" name="generate" value="true">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo $date_from; ?>" required>
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo $date_to; ?>" required>
            </div>
            <div class="col-md-3">
                <label for="subject_id" class="form-label">Subject</label>
                <select id="subject_id" name="subject_id" class="form-select">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($subject_id == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo $s['name']; ?> (<?php echo $s['code']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Generate</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['generate'])): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white"><div class="card-body text-center"><h6>Present</h6><h3><?php echo $summary['present']; ?></h3></div></div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white"><div class="card-body text-center"><h6>Absent</h6><h3><?php echo $summary['absent']; ?></h3></div></div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark"><div class="card-body text-center"><h6>Late</h6><h3><?php echo $summary['late']; ?></h3></div></div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white"><div class="card-body text-center"><h6>Attendance %</h6><h3><?php echo $summary['percentage']; ?>%</h3></div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Detailed Records</h5>
        </div>
        <div class="card-body">
            <?php if ($attendance_rows): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_rows as $r): ?>
                                <tr>
                                    <td><?php echo formatDate($r['date']); ?></td>
                                    <td><?php echo $r['subject_name']; ?></td>
                                    <td><?php echo $r['subject_code']; ?></td>
                                    <td><span class="badge <?php echo getAttendanceStatusClass($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No records found for the selected range.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
