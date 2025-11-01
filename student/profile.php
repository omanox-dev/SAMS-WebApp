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

// Get student details
try {
    $stmt = $db->prepare("SELECT u.*, c.name as class_name 
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
    $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate form data
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    // Check if email exists for another user
    if (!empty($email) && $email !== $student['email']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email already exists for another user.';
        }
    }
    
    // Check if password change is requested
    $update_password = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Validate password
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password.';
        } else {
            // Verify current password
            if (!password_verify($current_password, $student['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirm password do not match.';
        }
        
        $update_password = true;
    }
    
    // Process profile update if no errors
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            // Update user profile (excluding parent information)
            $stmt = $db->prepare("UPDATE users SET 
                                    name = ?,
                                    email = ?,
                                    phone = ?,
                                    address = ?,
                                    updated_at = NOW()
                                WHERE id = ?");
            $stmt->execute([
                $name, $email, $phone, $address, $student_id
            ]);
            // Update password if requested
            if ($update_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $student_id]);
            }
            $db->commit();
            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            $_SESSION['success'] = 'Profile updated successfully.';
            // Refresh student data to reflect changes
            $stmt = $db->prepare("SELECT u.*, c.name as class_name 
                                  FROM users u 
                                  LEFT JOIN classes c ON u.class_id = c.id 
                                  WHERE u.id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $db->rollBack();
            logError($e->getMessage(), __FILE__, __LINE__);
            $_SESSION['error'] = 'Failed to update profile.';
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>

<!-- Student Profile -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-circle me-2"></i>My Profile</h2>
    <a href="<?php echo URL_ROOT; ?>/student/dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Student Information</h5>
            </div>
            <div class="card-body text-center">
                <img src="https://via.placeholder.com/150" alt="Student Profile" class="profile-img mb-3">
                <h4><?php echo $student['name']; ?></h4>
                <p class="text-muted"><?php echo $student['email']; ?></p>
                <hr>
                <div class="text-start">
                    <p><strong>Roll Number:</strong> <?php echo $student['roll_number']; ?></p>
                    <p><strong>Class:</strong> <?php echo $student['class_name'] ?? 'Not Assigned'; ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($student['status']); ?>
                        </span>
                    </p>
                    <p><strong>Joined:</strong> <?php echo date('d M Y', strtotime($student['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?php echo $student['name']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?php echo $student['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo $student['phone']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="roll_number" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" value="<?php echo $student['roll_number']; ?>" readonly>
                            <small class="text-muted">Roll number cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea name="address" id="address" class="form-control" rows="3"><?php echo $student['address']; ?></textarea>
                    </div>
                    
                    <hr>
                    <h5>Parent/Guardian Information <small class="text-muted">(Managed by Administration)</small></h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Parent/Guardian Name</label>
                            <div class="form-control-plaintext border rounded px-3 py-2 bg-light">
                                <?php echo !empty($student['parent_name']) ? $student['parent_name'] : '<em class="text-muted">Not specified</em>'; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent/Guardian Phone</label>
                            <div class="form-control-plaintext border rounded px-3 py-2 bg-light">
                                <?php echo !empty($student['parent_phone']) ? $student['parent_phone'] : '<em class="text-muted">Not specified</em>'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Parent/Guardian Email</label>
                        <div class="form-control-plaintext border rounded px-3 py-2 bg-light">
                            <?php echo !empty($student['parent_email']) ? $student['parent_email'] : '<em class="text-muted">Not specified</em>'; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Attendance notifications are sent to this email. Contact admin to update parent information.
                        </small>
                    </div>
                    
                    <hr>
                    <h5>Change Password <small class="text-muted">(Leave blank to keep current password)</small></h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" name="current_password" id="current_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php
// Include footer file
require_once '../includes/footer.php';
?>
