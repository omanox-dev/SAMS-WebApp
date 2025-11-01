<?php
// Include header file
require_once '../includes/header.php';

// Initialize local message holders (avoid relying solely on session when not redirecting)
$success_message = '';
$error_message = '';

// Check if user has admin role
if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access. You do not have permission to view this page.';
    redirect(URL_ROOT . '/index.php');
}

// Filter by role if specified
$role_filter = '';
$params = [];

if (isset($_GET['role']) && in_array($_GET['role'], ['admin', 'teacher', 'student'])) {
    $role_filter = "WHERE role = ?";
    $params[] = $_GET['role'];
    $active_role = $_GET['role'];
} else {
    $active_role = 'all';
}

// Process user deletion BEFORE fetching list so refreshed list reflects removal
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    try {
        $stmt = $db->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user_id == $_SESSION['user_id']) {
                $error_message = 'You cannot delete your own account.';
            } else {
                if ($user['role'] === 'student') {
                    $delete_stmt = $db->prepare("DELETE FROM attendance WHERE student_id = ?");
                    $delete_stmt->execute([$user_id]);
                }
                $delete_stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->execute([$user_id]);
                $success_message = "User '{$user['name']}' has been deleted successfully.";
                logActivity('Delete User', "Deleted user: {$user['name']} (ID: {$user_id})");
            }
        } else {
            $error_message = 'User not found.';
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $error_message = 'Failed to delete user. Please try again.';
    }
}

// Get users from database (after potential deletion)
try {
    $query = "SELECT u.*, c.name as class_name 
              FROM users u 
              LEFT JOIN classes c ON u.class_id = c.id 
              $role_filter 
              ORDER BY u.name";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    $error_message = 'Failed to load users data.';
}
?>

<!-- Users Management -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users me-2"></i>Users Management</h2>
    <a href="<?php echo URL_ROOT; ?>/admin/add-user.php" class="btn btn-primary">
        <i class="fas fa-user-plus me-2"></i>Add New User
    </a>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo $error_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Role Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="btn-group" role="group">
            <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="btn btn-<?php echo ($active_role === 'all') ? 'primary' : 'outline-primary'; ?>">All Users</a>
            <a href="<?php echo URL_ROOT; ?>/admin/users.php?role=admin" class="btn btn-<?php echo ($active_role === 'admin') ? 'primary' : 'outline-primary'; ?>">Admins</a>
            <a href="<?php echo URL_ROOT; ?>/admin/users.php?role=teacher" class="btn btn-<?php echo ($active_role === 'teacher') ? 'primary' : 'outline-primary'; ?>">Teachers</a>
            <a href="<?php echo URL_ROOT; ?>/admin/users.php?role=student" class="btn btn-<?php echo ($active_role === 'student') ? 'primary' : 'outline-primary'; ?>">Students</a>
        </div>
        
        <div class="float-end">
            <input type="text" id="userSearch" class="form-control" placeholder="Search users...">
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo $user['name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo ($user['role'] === 'admin') ? 'danger' : 
                                            (($user['role'] === 'teacher') ? 'success' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['class_name'] ?? 'N/A'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($user['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo URL_ROOT; ?>/admin/view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo URL_ROOT; ?>/admin/edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="confirmDelete('<?php echo URL_ROOT; ?>/admin/users.php?action=delete&id=<?php echo $user['id']; ?><?php echo isset($_GET['role']) ? '&role='.$_GET['role'] : ''; ?>', 'Delete User', 'Are you sure you want to delete this user? This action cannot be undone.')" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Live search function for users table
$(document).ready(function() {
    $("#userSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".datatable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>

<?php
// Include footer file
require_once '../includes/footer.php';
?>
