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
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d'); // Default to today
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$view_mode = isset($_GET['view']) && $_GET['view'] === 'all' ? 'all' : 'date'; // 'date' or 'all'

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

// Build query based on filters
$params = [$teacher_id];
$query = "SELECT a.id, a.date, a.status, a.remarks, 
                 s.name as subject_name, s.code as subject_code,
                 c.name as class_name,
                 u.id as student_id, u.name as student_name, u.roll_number
          FROM attendance a 
          JOIN subjects s ON a.subject_id = s.id 
          JOIN class_subject cs ON s.id = cs.subject_id
          JOIN users u ON a.student_id = u.id
          JOIN classes c ON u.class_id = c.id
          WHERE cs.teacher_id = ? AND cs.class_id = c.id";

if ($class_id > 0) {
    $query .= " AND u.class_id = ?";
    $params[] = $class_id;
}

if ($subject_id > 0) {
    $query .= " AND a.subject_id = ?";
    $params[] = $subject_id;
}

// Date filtering logic based on view mode
if ($view_mode === 'all') {
    // Show all historical records (no date filter)
    // Only add class/subject/status filters
} else {
    // Show records for specific date
    $query .= " AND a.date = ?";
    $params[] = $selected_date;
}

if ($status) {
    $query .= " AND a.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY a.date DESC, c.name, u.name";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $attendance_records = [];
}

// Check if editing attendance
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$attendance_data = null;

if ($edit_id > 0) {
    try {
        $stmt = $db->prepare("SELECT a.*, 
                                    u.name as student_name, u.roll_number,
                                    s.name as subject_name, s.code as subject_code
                               FROM attendance a
                               JOIN users u ON a.student_id = u.id
                               JOIN subjects s ON a.subject_id = s.id
                               JOIN class_subject cs ON s.id = cs.subject_id
                               WHERE a.id = ? AND cs.teacher_id = ?");
        $stmt->execute([$edit_id, $teacher_id]);
        $attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if record exists and belongs to this teacher
        if (!$attendance_data) {
            $_SESSION['error'] = 'Invalid attendance record or you do not have permission to edit it.';
            redirect(URL_ROOT . '/teacher/attendance-history.php');
        }
        
        // Check if record is older than 24 hours
        $record_time = strtotime($attendance_data['date']);
        $current_time = time();
        $hours_diff = round(($current_time - $record_time) / 3600);
        
        if ($hours_diff > 24 && !hasRole('admin')) {
            $_SESSION['error'] = 'You can only edit attendance records within 24 hours of creation.';
            redirect(URL_ROOT . '/teacher/attendance-history.php');
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $_SESSION['error'] = 'Failed to retrieve attendance data for editing.';
        redirect(URL_ROOT . '/teacher/attendance-history.php');
    }
}

// Process form submission for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
    $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
    $remarks = isset($_POST['remarks']) ? sanitize($_POST['remarks']) : '';
    
    // Validate data
    $errors = [];
    
    if ($attendance_id <= 0) {
        $errors[] = 'Invalid attendance record.';
    }
    
    if (!in_array($status, ['present', 'absent', 'late'])) {
        $errors[] = 'Invalid attendance status.';
    }
    
    if (empty($errors)) {
        try {
            // Check if record belongs to this teacher
            $stmt = $db->prepare("SELECT a.id 
                                 FROM attendance a
                                 JOIN subjects s ON a.subject_id = s.id
                                 JOIN class_subject cs ON s.id = cs.subject_id
                                 WHERE a.id = ? AND cs.teacher_id = ?");
            $stmt->execute([$attendance_id, $teacher_id]);
            $record_exists = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record_exists) {
                $error_message = 'You do not have permission to edit this attendance record.';
            } else {
                // Update attendance record
                $stmt = $db->prepare("UPDATE attendance 
                                    SET status = ?, 
                                        remarks = ?,
                                        updated_at = NOW() 
                                    WHERE id = ?");
                $stmt->execute([$status, $remarks, $attendance_id]);
                $_SESSION['success'] = 'Attendance record has been updated successfully.';
                redirect($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['edit_id' => null])));
            }
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $error_message = 'Failed to update attendance record.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Process bulk update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $selected_ids = isset($_POST['selected_ids']) ? $_POST['selected_ids'] : [];
    $bulk_status = isset($_POST['bulk_status']) ? sanitize($_POST['bulk_status']) : '';
    $bulk_remarks = isset($_POST['bulk_remarks']) ? sanitize($_POST['bulk_remarks']) : '';
    
    // Validate data
    $errors = [];
    
    if (empty($selected_ids)) {
        $errors[] = 'No records selected for bulk update.';
    }
    
    if (!in_array($bulk_status, ['present', 'absent', 'late', ''])) {
        $errors[] = 'Invalid attendance status.';
    }
    
    if (empty($bulk_status) && empty($bulk_remarks)) {
        $errors[] = 'Please provide at least one field to update.';
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $db->beginTransaction();
            $updated_count = 0;
            foreach ($selected_ids as $id) {
                // Check if record belongs to this teacher and is within 24 hours
                $stmt = $db->prepare("SELECT a.id, a.date
                                     FROM attendance a
                                     JOIN subjects s ON a.subject_id = s.id
                                     JOIN class_subject cs ON s.id = cs.subject_id
                                     WHERE a.id = ? AND cs.teacher_id = ?");
                $stmt->execute([intval($id), $teacher_id]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$record) {
                    continue; // Skip if not found or not belonging to this teacher
                }
                // Check time constraint if not admin
                if (!hasRole('admin')) {
                    $record_time = strtotime($record['date']);
                    $current_time = time();
                    $hours_diff = round(($current_time - $record_time) / 3600);
                    if ($hours_diff > 24) {
                        continue; // Skip if older than 24 hours
                    }
                }
                // Build the update query based on provided fields
                $update_query = "UPDATE attendance SET updated_at = NOW()";
                $params = [];
                if (!empty($bulk_status)) {
                    $update_query .= ", status = ?";
                    $params[] = $bulk_status;
                }
                if (!empty($bulk_remarks)) {
                    $update_query .= ", remarks = ?";
                    $params[] = $bulk_remarks;
                }
                $update_query .= " WHERE id = ?";
                $params[] = intval($id);
                $stmt = $db->prepare($update_query);
                $stmt->execute($params);
                $updated_count++;
            }
            // Commit transaction
            $db->commit();
            if ($updated_count > 0) {
                $_SESSION['success'] = "Successfully updated $updated_count attendance record(s).";
                redirect($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
            } else {
                $warning_message = 'No records were updated. Check if the records are within 24 hours.';
            }
        } catch (PDOException $e) {
            // Rollback transaction
            $db->rollBack();
            logError($e->getMessage(), __FILE__, __LINE__);
            $error_message = 'Failed to perform bulk update.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!-- Attendance History -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-history me-2"></i>Attendance History</h2>
        <?php if ($view_mode === 'date'): ?>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-day me-1"></i>Showing records for <?php echo formatDate($selected_date); ?>
                <?php 
                $hours_diff = round((time() - strtotime($selected_date)) / 3600);
                if ($hours_diff <= 24): 
                ?>
                    <span class="badge bg-success ms-2">Editable</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-2">Read-only</span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar-week me-1"></i>Showing all historical records (recent ones are editable)
            </p>
        <?php endif; ?>
    </div>
    <div>
        <a href="<?php echo URL_ROOT; ?>/teacher/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($edit_id > 0 && $attendance_data): ?>
<!-- Edit Attendance Modal -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Attendance</h5>
    </div>
    <div class="card-body">
        <form action="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET); ?>" method="POST">
            <input type="hidden" name="attendance_id" value="<?php echo $attendance_data['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <p><strong>Student:</strong> <?php echo $attendance_data['student_name'] . ' (' . $attendance_data['roll_number'] . ')'; ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Subject:</strong> <?php echo $attendance_data['subject_name'] . ' (' . $attendance_data['subject_code'] . ')'; ?></p>
                </div>
                <div class="col-md-4">
                    <p><strong>Date:</strong> <?php echo formatDate($attendance_data['date']); ?></p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="status" class="form-label">Attendance Status</label>
                    <select name="status" id="status" class="form-select" required>
                        <option value="present" <?php echo $attendance_data['status'] === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo $attendance_data['status'] === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="late" <?php echo $attendance_data['status'] === 'late' ? 'selected' : ''; ?>>Late</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="remarks" class="form-label">Remarks</label>
                    <input type="text" name="remarks" id="remarks" class="form-control" value="<?php echo $attendance_data['remarks']; ?>" placeholder="Optional remarks">
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <a href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" name="update_attendance" class="btn btn-primary">Update Attendance</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Quick View Buttons -->
<div class="row mb-4">
    <div class="col-12">
        <div class="btn-group" role="group" aria-label="View Options">
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?view=date&date=<?php echo date('Y-m-d'); ?>&class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>&status=<?php echo urlencode($status); ?>" 
               class="btn <?php echo ($view_mode === 'date' && $selected_date === date('Y-m-d')) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <i class="fas fa-calendar-day me-2"></i>Today's Records
            </a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?view=date&date=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>&status=<?php echo urlencode($status); ?>" 
               class="btn <?php echo ($view_mode === 'date' && $selected_date === date('Y-m-d', strtotime('-1 day'))) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <i class="fas fa-calendar-minus me-2"></i>Yesterday's Records
            </a>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?view=all&class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>&status=<?php echo urlencode($status); ?>" 
               class="btn <?php echo $view_mode === 'all' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                <i class="fas fa-history me-2"></i>All Records
            </a>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Filter Records
        </h5>
    </div>
    <div class="card-body">
        <form id="filterForm" method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <!-- Hidden view parameter -->
            <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
            
            <!-- Date Picker (only for date view) -->
            <?php if ($view_mode === 'date'): ?>
            <div class="col-md-3">
                <label for="date" class="form-label">
                    <i class="fas fa-calendar me-1"></i>Select Date
                </label>
                <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
            </div>
            <?php else: ?>
            <div class="col-md-3">
                <div class="alert alert-info py-2 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>All Records Mode</strong><br>
                    <small>Showing complete attendance history</small>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Class Filter -->
            <div class="col-md-3">
                <label for="class_id" class="form-label">Class</label>
                <select name="class_id" id="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo $class['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Subject Filter -->
            <div class="col-md-3">
                <label for="subject_id" class="form-label">Subject</label>
                <select name="subject_id" id="subject_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo $subject['name'] . ' (' . $subject['code'] . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Status Filter -->
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="present" <?php echo $status == 'present' ? 'selected' : ''; ?>>Present</option>
                    <option value="absent" <?php echo $status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                    <option value="late" <?php echo $status == 'late' ? 'selected' : ''; ?>>Late</option>
                </select>
            </div>
            
            <!-- Manual Submit Button -->
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filters
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-undo me-2"></i>Reset to Today
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Records -->
<form id="bulkUpdateForm" method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET); ?>">
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
                                <th>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </div>
                                </th>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <?php 
                                    $record_time = strtotime($record['date']);
                                    $current_time = time();
                                    $hours_diff = round(($current_time - $record_time) / 3600);
                                    $can_edit = ($hours_diff <= 24 || hasRole('admin'));
                                ?>
                                <tr>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input record-checkbox" type="checkbox" 
                                                name="selected_ids[]" value="<?php echo $record['id']; ?>"
                                                <?php echo !$can_edit ? 'disabled' : ''; ?>>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($record['date']); ?></td>
                                    <td><?php echo $record['student_name'] . ' (' . $record['roll_number'] . ')'; ?></td>
                                    <td><?php echo $record['class_name']; ?></td>
                                    <td><?php echo $record['subject_name'] . ' (' . $record['subject_code'] . ')'; ?></td>
                                    <td>
                                        <span class="badge <?php echo getAttendanceStatusClass($record['status']); ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $record['remarks'] ? $record['remarks'] : '<span class="text-muted">No remarks</span>'; ?></td>
                                    <td>
                                        <?php if ($can_edit): ?>
                                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?edit_id=<?php echo $record['id']; ?>&<?php echo http_build_query($_GET); ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary" disabled title="Cannot edit records older than 24 hours">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Bulk Update Section -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Bulk Update Selected Records</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <label for="bulk_status" class="form-label">Set Status</label>
                                <select name="bulk_status" id="bulk_status" class="form-select">
                                    <option value="">No Change</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="bulk_remarks" class="form-label">Set Remarks</label>
                                <input type="text" name="bulk_remarks" id="bulk_remarks" class="form-control" placeholder="Apply to all selected records">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="bulk_update" class="btn btn-warning w-100" onclick="return confirmBulkUpdate()">
                                    <i class="fas fa-save me-2"></i>Update Selected
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 small text-muted">
                            <i class="fas fa-info-circle me-1"></i> Note: Only records within 24 hours can be edited. 
                            Bulk update will only affect eligible records.
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No attendance records found matching your filters.
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#attendanceTable').DataTable({
        responsive: true,
        order: [[1, 'desc']],
        pageLength: 25,
        language: {
            search: "<i class='fas fa-search'></i>",
            searchPlaceholder: "Search records"
        }
    });
    
    // Select all checkbox
    $('#selectAll').change(function() {
        $('.record-checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
    });
    
    // Check if any checkbox is not checked
    $('.record-checkbox').change(function() {
        if ($('.record-checkbox:checked').length === $('.record-checkbox:not(:disabled)').length) {
            $('#selectAll').prop('checked', true);
        } else {
            $('#selectAll').prop('checked', false);
        }
    });
    
    // Class filter change event
    $('#class_id').change(function() {
        $('#filterForm').submit();
    });
    
    // Reset bulk update form after successful update
    if (window.location.search.includes('updated=1')) {
        $('#bulk_status').val('');
        $('#bulk_remarks').val('');
        $('.record-checkbox').prop('checked', false);
        $('#selectAll').prop('checked', false);
    }
});

// Confirm bulk update
function confirmBulkUpdate() {
    var selectedCount = $('.record-checkbox:checked').length;
    if (selectedCount === 0) {
        alert('Please select at least one record to update.');
        return false;
    }
    
    var status = $('#bulk_status').val();
    var remarks = $('#bulk_remarks').val();
    
    if (!status && !remarks) {
        alert('Please specify either a status or remarks to update.');
        return false;
    }
    
    var message = `Are you sure you want to update ${selectedCount} selected record(s)?`;
    if (status) {
        message += `\nStatus will be changed to: ${status.charAt(0).toUpperCase() + status.slice(1)}`;
    }
    if (remarks) {
        message += `\nRemarks will be set to: "${remarks}"`;
    }
    
    return confirm(message);
}
</script>

<?php
// Display any remaining warning/error messages that weren't redirected
if (!empty($warning_message)) {
    echo '<div class="alert alert-warning alert-dismissible fade show mt-3">' . $warning_message . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
if (!empty($error_message)) {
    echo '<div class="alert alert-danger alert-dismissible fade show mt-3">' . $error_message . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}
require_once '../includes/footer.php';
?>
