<?php
// Include header file
require_once '../includes/header.php';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'User ID is required.';
    redirect(URL_ROOT . '/admin/users.php');
}

$user_id = (int) $_GET['id'];

// Get classes for dropdown
try {
    $stmt = $db->query("SELECT id, name FROM classes ORDER BY name");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $classes = [];
}

// Get user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error'] = 'User not found.';
        redirect(URL_ROOT . '/admin/users.php');
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize form variables with user data
    $name = $user['name'];
    $email = $user['email'];
    $role = $user['role'];
    $class_id = $user['class_id'];
    $status = $user['status'];
    
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $_SESSION['error'] = 'Failed to get user data.';
    redirect(URL_ROOT . '/admin/users.php');
}

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $role = sanitize($_POST['role']);
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $status = sanitize($_POST['status']);
    $password = $_POST['password'] ?? '';
    
    // Validate form data
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($role) || !in_array($role, ['admin', 'teacher', 'student'])) {
        $errors[] = 'Valid role is required';
    }
    
    if ($role === 'student' && empty($class_id)) {
        $errors[] = 'Class is required for students';
    }
    
    if (empty($status) || !in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Valid status is required';
    }
    
    // Check if email already exists (for other users)
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Email already exists for another user';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $errors[] = 'Error checking email: ' . $e->getMessage();
    }
    
    // If no validation errors, update the user
    if (empty($errors)) {
        try {
            // Update user data (with or without password)
            if (!empty($password)) {
                // Hash password
                $hashed_password = hashPassword($password);
                
                // Update with password
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, class_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $role, ($role === 'student' ? $class_id : null), $status, $user_id]);
            } else {
                // Update without password
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, role = ?, class_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, ($role === 'student' ? $class_id : null), $status, $user_id]);
            }
            
            // Log activity
            logActivity('Edit User', "Updated user: {$name} (ID: {$user_id})");
            
            // Set success message for inline display
            $success_message = "User '{$name}' has been updated successfully.";
        } catch (PDOException $e) {
            logError($e->getMessage(), __FILE__, __LINE__);
            $errors[] = 'Failed to update user: ' . $e->getMessage();
        }
    }
}
?>

<!-- Edit User Form -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-edit me-2"></i>Edit User</h2>
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
        
        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?id=<?php echo $user_id; ?>" class="needs-validation" novalidate>
            <div class="row">
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
                        <input type="password" class="form-control" id="password" name="password">
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="text-muted">Leave empty to keep current password. New password must be at least 6 characters.</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
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
                    <select class="form-select" id="class_id" name="class_id" <?php echo ($role === 'student') ? 'required' : ''; ?>>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_id == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a class for the student.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="">Select Status</option>
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <div class="invalid-feedback">Please select a status.</div>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="reset" class="btn btn-secondary">Reset</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Update User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide class field based on role selection
    document.getElementById('role').addEventListener('change', function() {
        const classField = document.querySelector('.class-field');
        const classSelect = document.getElementById('class_id');
        
        if (this.value === 'student') {
            classField.style.display = 'block';
            classSelect.setAttribute('required', '');
        } else {
            classField.style.display = 'none';
            classSelect.removeAttribute('required');
            classSelect.value = '';
        }
    });
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
