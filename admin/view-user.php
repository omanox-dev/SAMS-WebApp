<?php
// admin/view-user.php
require_once '../includes/header.php';

if (!hasRole('admin')) {
    $_SESSION['error'] = 'Unauthorized access.';
    redirect(URL_ROOT . '/index.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    echo '<div class="alert alert-danger">Invalid user ID.</div>';
    require_once '../includes/footer.php';
    exit;
}

try {
    $stmt = $db->prepare("SELECT u.*, c.name as class_name FROM users u LEFT JOIN classes c ON u.class_id = c.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo '<div class="alert alert-danger">User not found.</div>';
        require_once '../includes/footer.php';
        exit;
    }
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
    echo '<div class="alert alert-danger">Failed to load user data.</div>';
    require_once '../includes/footer.php';
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user me-2"></i>User Details</h2>
    <a href="<?php echo URL_ROOT; ?>/admin/users.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
</div>
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profile Information</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                <strong>Role:</strong> <?php echo ucfirst($user['role']); ?><br>
                <strong>Status:</strong> <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($user['status']); ?></span><br>
                <strong>Class:</strong> <?php echo $user['class_name'] ?? 'N/A'; ?><br>
                <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?><br>
                <strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?><br>
                <strong>Joined:</strong> <?php echo date('d M Y', strtotime($user['created_at'])); ?><br>
            </div>
            <?php if ($user['role'] === 'student'): ?>
            <div class="col-md-6">
                <strong>Roll Number:</strong> <?php echo htmlspecialchars($user['roll_number']); ?><br>
                <strong>Parent Name:</strong> <?php echo htmlspecialchars($user['parent_name']); ?><br>
                <strong>Parent Phone:</strong> <?php echo htmlspecialchars($user['parent_phone']); ?><br>
                <strong>Parent Email:</strong> <?php echo htmlspecialchars($user['parent_email']); ?><br>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
