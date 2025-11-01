<?php 
// Include configuration files
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Check if user is logged in, if not and trying to access restricted page, redirect to login
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['index.php', 'login.php', 'register.php', 'forgot-password.php', 'reset-password.php'];

if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    redirect(URL_ROOT . '/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo URL_ROOT; ?>/assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
                <?php
                // Determine dashboard link based on user role
                $dashboard_link = URL_ROOT . '/dashboard.php';
                if (hasRole('admin')) {
                    $dashboard_link = URL_ROOT . '/admin/dashboard.php';
                } elseif (hasRole('teacher')) {
                    $dashboard_link = URL_ROOT . '/teacher/dashboard.php';
                } elseif (hasRole('student')) {
                    $dashboard_link = URL_ROOT . '/student/dashboard.php';
                }
                ?>
                <a class="navbar-brand" href="<?php echo $dashboard_link; ?>"><?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            Users
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/users.php">All Users</a></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/add-user.php">Add User</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="classDropdown" role="button" data-bs-toggle="dropdown">
                            Classes & Subjects
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/classes.php">Manage Classes</a></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/subjects.php">Manage Subjects</a></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/assign-subjects.php">Assign Subjects</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/reports.php">Reports</a>
                    </li>
                    <!-- Settings link removed: admin/settings.php does not exist -->
                    <?php endif; ?>

                    <?php if (hasRole('teacher')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/teacher/mark-attendance.php">Mark Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/teacher/attendance-history.php">Attendance History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/teacher/reports.php">Class Reports</a>
                    </li>
                    <?php endif; ?>

                    <?php if (hasRole('student')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/student/subject-charts.php">My Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/student/reports.php">My Reports</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $_SESSION['user_name'] ?? 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (hasRole('admin')): ?>
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/admin/profile.php">My Profile</a></li>
                            <?php elseif (hasRole('teacher')): ?>
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/teacher/profile.php">My Profile</a></li>
                            <?php elseif (hasRole('student')): ?>
                                <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/student/profile.php">My Profile</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container py-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
