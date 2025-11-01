<?php
// Include header file (without the HTML header)
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect based on user role
    switch ($_SESSION['user_role']) {
        case 'admin':
            redirect(URL_ROOT . '/admin/dashboard.php');
            break;
        case 'teacher':
            redirect(URL_ROOT . '/teacher/dashboard.php');
            break;
        case 'student':
            redirect(URL_ROOT . '/student/dashboard.php');
            break;
        default:
            redirect(URL_ROOT . '/index.php');
    }
}

// Initialize variables
$email = $password = '';
$errors = [];

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validate form data
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            // Check if user exists
            $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $errors[] = 'Your account is inactive. Please contact the administrator.';
                } else {
                    // Verify password
                    if (verifyPassword($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        
                        // Log login activity
                        logActivity('Login', 'User logged in successfully');
                        
                        // Redirect based on user role
                        switch ($user['role']) {
                            case 'admin':
                                redirect(URL_ROOT . '/admin/dashboard.php');
                                break;
                            case 'teacher':
                                redirect(URL_ROOT . '/teacher/dashboard.php');
                                break;
                            case 'student':
                                redirect(URL_ROOT . '/student/dashboard.php');
                                break;
                            default:
                                redirect(URL_ROOT . '/index.php');
                        }
                    } else {
                        $errors[] = 'Invalid email or password';
                    }
                }
            } else {
                $errors[] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $errors[] = 'An error occurred. Please try again.';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="auth-form">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">
                        <i class="fas fa-user-graduate me-2"></i> 
                        <?php echo SITE_NAME; ?>
                    </h3>
                    
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
                    
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                            </div>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </div>
                    </form>
                    
                    <p class="text-center mt-3">
                        <a href="forgot-password.php">Forgot your password?</a>
                    </p>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <p>&copy; <?php echo date("Y"); ?> <?php echo SITE_NAME; ?> - All Rights Reserved</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo URL_ROOT; ?>/assets/js/script.js"></script>
</body>
</html>
