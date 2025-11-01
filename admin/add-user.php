<?php
// Include header file
require_once '../includes/header.php';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Get classes for dropdown
try {
    $stmt = $db->query("SELECT id, name FROM classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $classes = [];
}

// Initialize variables
$id = $name = $email = $role = $class_id = $status = $roll_number = '';
$parent_name = $parent_phone = $parent_email = '';
$errors = [];
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $id = isset($_POST['id']) ? (int)$_POST['id'] : '';
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $status = sanitize($_POST['status']);
    $roll_number = isset($_POST['roll_number']) ? sanitize($_POST['roll_number']) : '';
    $parent_name = isset($_POST['parent_name']) ? sanitize($_POST['parent_name']) : '';
    $parent_phone = isset($_POST['parent_phone']) ? sanitize($_POST['parent_phone']) : '';
    $parent_email = isset($_POST['parent_email']) ? sanitize($_POST['parent_email']) : '';
    
    // Validate form data

    if (empty($id) || !is_numeric($id) || $id <= 0) {
        $errors[] = 'User ID must be a positive integer';
    }

    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Validate parent email if provided
    if (!empty($parent_email) && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid parent email format';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($role) || !in_array($role, ['admin', 'teacher', 'student'])) {
        $errors[] = 'Valid role is required';
    }
    
    if ($role === 'student' && empty($class_id)) {
    if ($role === 'student' && empty($roll_number)) {
        $errors[] = 'Roll number is required for students';
    }
        $errors[] = 'Class is required for students';
    }
    
    if (empty($status) || !in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Valid status is required';
    }
    
    // Check if email or ID already exist
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) { $errors[] = 'Email already exists'; }
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) { $errors[] = 'User ID already exists'; }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Error checking email or ID: ' . $e->getMessage();
    }
    
    // If no validation errors, add the user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = hashPassword($password);
            if ($role === 'student') {
                $stmt = $db->prepare("INSERT INTO users (id, name, email, password, role, class_id, roll_number, parent_name, parent_phone, parent_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $name, $email, $hashed_password, $role, $class_id, $roll_number, $parent_name, $parent_phone, $parent_email, $status]);
            } else {
                $stmt = $db->prepare("INSERT INTO users (id, name, email, password, role, class_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $name, $email, $hashed_password, $role, null, $status]);
            }
            // Log activity
            logActivity('Add User', "Added new user: {$name} (Role: {$role})");
            // Set success message (no redirect)
            $success_message = "User '{$name}' (ID: {$id}) has been added successfully.";
            // Optionally clear form fields after success
            $id = $name = $email = $role = $class_id = $status = $roll_number = '';
            $parent_name = $parent_phone = $parent_email = '';
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $errors[] = 'Failed to add user: ' . $e->getMessage();
        }
    }
}
?>

<!-- Add User Form -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-plus me-2"></i>Add New User</h2>
    <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back to Users
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id" class="form-label">User ID</label>
                    <input type="number" class="form-control" id="id" name="id" value="<?php echo $id; ?>" required min="1">
                    <div class="invalid-feedback">Please enter a unique positive integer User ID.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>" required>
                    <div class="invalid-feedback">Please enter the user's full name.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Password must be at least 6 characters.</div>
                    <small class="text-muted">Must be at least 6 characters.</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="" <?php echo empty($role) ? 'selected' : ''; ?>>Select Role</option>
                        <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="teacher" <?php echo ($role === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                        <option value="student" <?php echo ($role === 'student') ? 'selected' : ''; ?>>Student</option>
                    </select>
                    <div class="invalid-feedback">Please select a role.</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3 class-field" style="<?php echo ($role !== 'student') ? 'display: none;' : ''; ?>">
                    <label for="class_id" class="form-label">Class (for Students)</label>
                    <select class="form-select" id="class_id" name="class_id" <?php echo ($role === 'student') ? 'required' : ''; ?> >
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?> >
                                <?php echo $class['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a class for the student.</div>
                </div>
                <div class="col-md-6 mb-3 roll-field" style="<?php echo ($role !== 'student') ? 'display: none;' : ''; ?>">
                    <label for="roll_number" class="form-label">Roll Number (for Students)</label>
                    <input type="text" class="form-control" id="roll_number" name="roll_number" value="<?php echo $roll_number; ?>" <?php echo ($role === 'student') ? 'required' : ''; ?> >
                    <div class="invalid-feedback">Please enter a roll number for the student.</div>
                </div>
            </div>
            
            <!-- Parent Information Section (for Students only) -->
            <div class="parent-fields" style="<?php echo ($role !== 'student') ? 'display: none;' : ''; ?>">
                <hr>
                <h5 class="mb-3">Parent/Guardian Information <small class="text-muted">(for Students)</small></h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                        <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo $parent_name; ?>">
                        <small class="text-muted">Full name of parent or guardian</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="parent_phone" class="form-label">Parent/Guardian Phone</label>
                        <input type="tel" class="form-control" id="parent_phone" name="parent_phone" value="<?php echo $parent_phone; ?>">
                        <small class="text-muted">Contact number for emergencies</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="parent_email" class="form-label">Parent/Guardian Email</label>
                        <input type="email" class="form-control" id="parent_email" name="parent_email" value="<?php echo $parent_email; ?>">
                        <small class="text-muted">Attendance notifications will be sent here</small>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="" <?php echo empty($status) ? 'selected' : ''; ?>>Select Status</option>
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <div class="invalid-feedback">Please select a status.</div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary">Reset</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Add User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide class, roll, and parent fields based on role selection
    document.getElementById('role').addEventListener('change', function() {
        const classField = document.querySelector('.class-field');
        const classSelect = document.getElementById('class_id');
        const rollField = document.querySelector('.roll-field');
        const rollInput = document.getElementById('roll_number');
        const parentFields = document.querySelector('.parent-fields');
        
        if (this.value === 'student') {
            classField.style.display = 'block';
            classSelect.setAttribute('required', '');
            rollField.style.display = 'block';
            rollInput.setAttribute('required', '');
            parentFields.style.display = 'block';
        } else {
            classField.style.display = 'none';
            classSelect.removeAttribute('required');
            classSelect.value = '';
            rollField.style.display = 'none';
            rollInput.removeAttribute('required');
            rollInput.value = '';
            parentFields.style.display = 'none';
        }
    });
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
