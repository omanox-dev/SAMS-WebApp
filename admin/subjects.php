<?php
// Include header file
require_once '../includes/header.php';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Initialize local inline messages
$success_message = '';
$error_list = [];

// Process subject addition/edit (stay on same page, no redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    $name = sanitize($_POST['name']);
    $code = sanitize($_POST['code']);
    $description = sanitize($_POST['description']);
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Subject name is required';
    }
    
    if (empty($code)) {
        $errors[] = 'Subject code is required';
    }
    
    // Check if subject code already exists
    try {
        if ($subject_id > 0) {
            // Editing existing subject
            $stmt = $db->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
            $stmt->execute([$code, $subject_id]);
        } else {
            // Adding new subject
            $stmt = $db->prepare("SELECT id FROM subjects WHERE code = ?");
            $stmt->execute([$code]);
        }
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Subject code already exists';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Error checking subject code: ' . $e->getMessage();
    }
    
    // If no validation errors, add/update the subject
    if (empty($errors)) {
        try {
            if ($subject_id > 0) {
                // Update existing subject
                $stmt = $db->prepare("UPDATE subjects SET name = ?, code = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $code, $description, $subject_id]);
                $success_message = "Subject '{$name}' has been updated successfully.";
                logActivity('Edit Subject', "Updated subject: {$name} (ID: {$subject_id})");
            } else {
                // Add new subject
                $stmt = $db->prepare("INSERT INTO subjects (name, code, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $description]);
                $success_message = "Subject '{$name}' has been added successfully.";
                logActivity('Add Subject', "Added new subject: {$name}");
                // Clear form fields after successful add
                if ($subject_id === 0) {
                    $name = $code = $description = '';
                }
            }
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $errors[] = 'Failed to ' . ($subject_id > 0 ? 'update' : 'add') . ' subject: ' . $e->getMessage();
        }
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        $error_list = $errors;
    }
}

// Process subject deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $subject_id = (int)$_GET['id'];
    
    try {
        // Check if subject exists
        $stmt = $db->prepare("SELECT name FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subject) {
            // Check if subject is assigned to any class
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM class_subject WHERE subject_id = ?");
            $stmt->execute([$subject_id]);
            $assignment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($assignment_count > 0) {
                $_SESSION['error'] = "Cannot delete subject '{$subject['name']}'. It is assigned to {$assignment_count} class(es).";
            } else {
                // Delete subject
                $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
                
                $success_message = "Subject '{$subject['name']}' has been deleted successfully.";
                logActivity('Delete Subject', "Deleted subject: {$subject['name']} (ID: {$subject_id})");
            }
        } else {
            $error_list[] = 'Subject not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_list[] = 'Failed to delete subject: ' . $e->getMessage();
    }
}

// Get subject data for edit
$edit_subject = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $subject_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $edit_subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_subject) {
            $error_list[] = 'Subject not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_list[] = 'Failed to get subject data: ' . $e->getMessage();
    }
}

// Get all subjects
try {
    $stmt = $db->query("SELECT s.*,
                              (SELECT COUNT(DISTINCT class_id) FROM class_subject WHERE subject_id = s.id) as class_count,
                              (SELECT COUNT(DISTINCT teacher_id) FROM class_subject WHERE subject_id = s.id) as teacher_count
                       FROM subjects s
                       ORDER BY s.name");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to load subjects data.';
    $subjects = [];
}
?>

<!-- Subjects Management -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-book me-2"></i>Subjects Management</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="fas fa-plus me-2"></i>Add New Subject
    </button>
</div>

<!-- Subjects Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Description</th>
                        <th>Classes</th>
                        <th>Teachers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subjects)): ?>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo $subject['id']; ?></td>
                                <td><?php echo $subject['code']; ?></td>
                                <td><?php echo $subject['name']; ?></td>
                                <td><?php echo $subject['description'] ?? 'N/A'; ?></td>
                                <td><?php echo $subject['class_count']; ?></td>
                                <td><?php echo $subject['teacher_count']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo URL_ROOT; ?>/admin/subjects.php?action=edit&id=<?php echo $subject['id']; ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($subject['class_count'] == 0): ?>
                                            <button onclick="confirmDelete('<?php echo URL_ROOT; ?>/admin/subjects.php?action=delete&id=<?php echo $subject['id']; ?>', 'Delete Subject', 'Are you sure you want to delete this subject? This action cannot be undone.')" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-danger" title="Delete" disabled>
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No subjects found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <?php if ($edit_subject): ?>
                    <input type="hidden" name="subject_id" value="<?php echo $edit_subject['id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addSubjectModalLabel">
                        <?php echo $edit_subject ? 'Edit Subject' : 'Add New Subject'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $edit_subject ? $edit_subject['name'] : ''; ?>" required>
                        <div class="invalid-feedback">Please enter a subject name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="code" name="code" value="<?php echo $edit_subject ? $edit_subject['code'] : ''; ?>" required>
                        <div class="invalid-feedback">Please enter a subject code.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_subject ? $edit_subject['description'] : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        <?php echo $edit_subject ? 'Update Subject' : 'Add Subject'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_subject): ?>
<script>
    // Automatically open modal for editing
    document.addEventListener('DOMContentLoaded', function() {
        const addSubjectModal = new bootstrap.Modal(document.getElementById('addSubjectModal'));
        addSubjectModal.show();
    });
</script>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
<div class="alert alert-success alert-dismissible fade show mt-3">
    <?php echo $success_message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($error_list)): ?>
<div class="alert alert-danger alert-dismissible fade show mt-3">
    <ul class="mb-0">
        <?php foreach($error_list as $err): ?>
            <li><?php echo $err; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
