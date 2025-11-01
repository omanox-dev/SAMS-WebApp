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

// Process class addition/edit (stay on page)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Class name is required';
    }
    
    // Check if class name already exists
    try {
        if ($class_id > 0) {
            // Editing existing class
            $stmt = $db->prepare("SELECT id FROM classes WHERE name = ? AND id != ?");
            $stmt->execute([$name, $class_id]);
        } else {
            // Adding new class
            $stmt = $db->prepare("SELECT id FROM classes WHERE name = ?");
            $stmt->execute([$name]);
        }
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Class name already exists';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Error checking class name: ' . $e->getMessage();
    }
    
    // If no validation errors, add/update the class
    if (empty($errors)) {
        try {
            if ($class_id > 0) {
                // Update existing class
                $stmt = $db->prepare("UPDATE classes SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $class_id]);
                $success_message = "Class '{$name}' has been updated successfully.";
                logActivity('Edit Class', "Updated class: {$name} (ID: {$class_id})");
            } else {
                // Add new class
                $stmt = $db->prepare("INSERT INTO classes (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $description]);
                $success_message = "Class '{$name}' has been added successfully.";
                logActivity('Add Class', "Added new class: {$name}");
                // Clear form fields after adding
                if ($class_id === 0) { $name = $description = ''; }
            }
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $errors[] = 'Failed to ' . ($class_id > 0 ? 'update' : 'add') . ' class: ' . $e->getMessage();
        }
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        $error_list = $errors;
    }
}

// Process class deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $class_id = (int)$_GET['id'];
    
    try {
        // Check if class exists
        $stmt = $db->prepare("SELECT name FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($class) {
            // Check if class has students
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE class_id = ?");
            $stmt->execute([$class_id]);
            $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($student_count > 0) {
                $error_list[] = "Cannot delete class '{$class['name']}'. It has {$student_count} student(s) assigned to it.";
            } else {
                // Delete subject-class assignments first
                $stmt = $db->prepare("DELETE FROM class_subject WHERE class_id = ?");
                $stmt->execute([$class_id]);
                
                // Delete class
                $stmt = $db->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$class_id]);
                
                $success_message = "Class '{$class['name']}' has been deleted successfully.";
                logActivity('Delete Class', "Deleted class: {$class['name']} (ID: {$class_id})");
            }
        } else {
            $error_list[] = 'Class not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_list[] = 'Failed to delete class: ' . $e->getMessage();
    }
}

// Get class data for edit
$edit_class = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $class_id = (int)$_GET['id'];
    
    try {
        $stmt = $db->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $edit_class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_class) {
            $error_list[] = 'Class not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_list[] = 'Failed to get class data: ' . $e->getMessage();
    }
}

// Get all classes
try {
    $stmt = $db->query("SELECT c.*, 
                              (SELECT COUNT(*) FROM users WHERE class_id = c.id) as student_count,
                              (SELECT COUNT(*) FROM class_subject WHERE class_id = c.id) as subject_count
                       FROM classes c
                       ORDER BY c.name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $error_list[] = 'Failed to load classes data.';
    $classes = [];
}
?>

<!-- Classes Management -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-school me-2"></i>Classes Management</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
        <i class="fas fa-plus me-2"></i>Add New Class
    </button>
</div>

<!-- Classes Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Class Name</th>
                        <th>Description</th>
                        <th>Students</th>
                        <th>Subjects</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($classes)): ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo $class['id']; ?></td>
                                <td><?php echo $class['name']; ?></td>
                                <td><?php echo $class['description'] ?? 'N/A'; ?></td>
                                <td><?php echo $class['student_count']; ?></td>
                                <td><?php echo $class['subject_count']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo URL_ROOT; ?>/admin/classes.php?action=edit&id=<?php echo $class['id']; ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($class['student_count'] == 0): ?>
                                            <button onclick="confirmDelete('<?php echo URL_ROOT; ?>/admin/classes.php?action=delete&id=<?php echo $class['id']; ?>', 'Delete Class', 'Are you sure you want to delete this class? This action cannot be undone.')" class="btn btn-danger" title="Delete">
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
                            <td colspan="6" class="text-center">No classes found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                <?php if ($edit_class): ?>
                    <input type="hidden" name="class_id" value="<?php echo $edit_class['id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title" id="addClassModalLabel">
                        <?php echo $edit_class ? 'Edit Class' : 'Add New Class'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Class Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo $edit_class ? $edit_class['name'] : ''; ?>" required>
                        <div class="invalid-feedback">Please enter a class name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_class ? $edit_class['description'] : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        <?php echo $edit_class ? 'Update Class' : 'Add Class'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($edit_class): ?>
<script>
    // Automatically open modal for editing
    document.addEventListener('DOMContentLoaded', function() {
        const addClassModal = new bootstrap.Modal(document.getElementById('addClassModal'));
        addClassModal.show();
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
