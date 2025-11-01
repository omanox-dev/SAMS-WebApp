<?php
// Include header file
require_once '../includes/header.php';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Initialize inline messages
$success_message = '';
$error_list = [];

// Get teachers for dropdown
try {
    $stmt = $db->query("SELECT id, name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY name");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $teachers = [];
    $error_list[] = 'Failed to load teachers list.';
}

// Get classes for dropdown
try {
    $stmt = $db->query("SELECT id, name FROM classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $classes = [];
    $error_list[] = 'Failed to load classes list.';
}

// Get subjects for dropdown
try {
    $stmt = $db->query("SELECT id, name, code FROM subjects ORDER BY name");
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $subjects = [];
    $error_list[] = 'Failed to load subjects list.';
}

// Process subject assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    // Get form data
    $teacher_id = (int)$_POST['teacher_id'];
    $class_id = (int)$_POST['class_id'];
    $subject_id = (int)$_POST['subject_id'];
    
    // Validate form data
    $errors = [];
    
    if ($teacher_id <= 0) {
        $errors[] = 'Please select a teacher';
    }
    
    if ($class_id <= 0) {
        $errors[] = 'Please select a class';
    }
    
    if ($subject_id <= 0) {
        $errors[] = 'Please select a subject';
    }
    
    // Check if assignment already exists
    try {
        $stmt = $db->prepare("SELECT id FROM class_subject WHERE teacher_id = ? AND class_id = ? AND subject_id = ?");
        $stmt->execute([$teacher_id, $class_id, $subject_id]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'This assignment already exists';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Error checking assignment: ' . $e->getMessage();
    }
    
    // If no validation errors, add the assignment
    if (empty($errors)) {
        try {
            // Add assignment
            $stmt = $db->prepare("INSERT INTO class_subject (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
            $stmt->execute([$teacher_id, $class_id, $subject_id]);
            
            // Get teacher name
            $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $teacher_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
            
            // Get class name
            $stmt = $db->prepare("SELECT name FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
            
            // Get subject name
            $stmt = $db->prepare("SELECT name FROM subjects WHERE id = ?");
            $stmt->execute([$subject_id]);
            $subject_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
            
            $success_message = "Subject '{$subject_name}' has been assigned to teacher '{$teacher_name}' for class '{$class_name}' successfully.";
            logActivity('Assign Subject', "Assigned subject '{$subject_name}' to teacher '{$teacher_name}' for class '{$class_name}'");
            // Clear form selections after success
            $teacher_id = $class_id = $subject_id = 0;
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $errors[] = 'Failed to assign subject: ' . $e->getMessage();
        }
    }
    
    // If there are errors, display them
    if (!empty($errors)) {
        $error_list = array_merge($error_list, $errors);
    }
}

// Process assignment deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $assignment_id = (int)$_GET['id'];
    
    try {
        // Get assignment details before deletion
        $stmt = $db->prepare("SELECT cs.*, u.name as teacher_name, c.name as class_name, s.name as subject_name 
                              FROM class_subject cs
                              LEFT JOIN users u ON cs.teacher_id = u.id
                              LEFT JOIN classes c ON cs.class_id = c.id
                              LEFT JOIN subjects s ON cs.subject_id = s.id
                              WHERE cs.id = ?");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignment) {
            // Delete assignment
            $stmt = $db->prepare("DELETE FROM class_subject WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
            $success_message = "Assignment for subject '{$assignment['subject_name']}' has been removed successfully.";
            logActivity('Delete Assignment', "Removed subject assignment: teacher '{$assignment['teacher_name']}', class '{$assignment['class_name']}', subject '{$assignment['subject_name']}'");
        } else {
            $error_list[] = 'Assignment not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_list[] = 'Failed to delete assignment: ' . $e->getMessage();
    }
}

// Get all assignments
try {
    $stmt = $db->query("SELECT cs.id, 
                               u.name as teacher_name, 
                               c.name as class_name, 
                               s.name as subject_name,
                               s.code as subject_code
                        FROM class_subject cs
                        LEFT JOIN users u ON cs.teacher_id = u.id
                        LEFT JOIN classes c ON cs.class_id = c.id
                        LEFT JOIN subjects s ON cs.subject_id = s.id
                        ORDER BY c.name, s.name");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $error_list[] = 'Failed to load assignments data.';
    $assignments = [];
}
?>

<!-- Assign Subjects -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-clipboard-list me-2"></i>Assign Subjects</h2>
</div>

<!-- Assignment Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Create New Assignment</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="assign">
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="teacher_id" class="form-label">Teacher</label>
                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                        <option value="">Select Teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php if(!empty($teacher_id) && $teacher_id == $teacher['id']) echo 'selected'; ?>><?php echo $teacher['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a teacher.</div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="class_id" class="form-label">Class</label>
                    <select class="form-select" id="class_id" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php if(!empty($class_id) && $class_id == $class['id']) echo 'selected'; ?>><?php echo $class['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a class.</div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select class="form-select" id="subject_id" name="subject_id" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php if(!empty($subject_id) && $subject_id == $subject['id']) echo 'selected'; ?>><?php echo $subject['name']; ?> (<?php echo $subject['code']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a subject.</div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Assign Subject
                </button>
            </div>
        </form>
    </div>
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
</div>

<!-- Assignments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Current Assignments</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Subject Code</th>
                        <th>Teacher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($assignments)): ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?php echo $assignment['id']; ?></td>
                                <td><?php echo $assignment['class_name']; ?></td>
                                <td><?php echo $assignment['subject_name']; ?></td>
                                <td><?php echo $assignment['subject_code']; ?></td>
                                <td><?php echo $assignment['teacher_name']; ?></td>
                                <td>
                                    <button onclick="confirmDelete('<?php echo URL_ROOT; ?>/admin/assign-subjects.php?action=delete&id=<?php echo $assignment['id']; ?>', 'Remove Assignment', 'Are you sure you want to remove this assignment?')" class="btn btn-danger btn-sm" title="Delete">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No assignments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
