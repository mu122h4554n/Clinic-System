<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get activity log for current user (or all if admin)
if (hasRole('admin')) {
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name, u.role 
        FROM activity_log al 
        JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name, u.role 
        FROM activity_log al 
        JOIN users u ON al.user_id = u.id 
        WHERE al.user_id = ? 
        ORDER BY al.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
}

$activities = $stmt->fetchAll();

$pageTitle = 'Activity Log';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-history me-2"></i>
        Activity Log
        <?php if (hasRole('admin')): ?>
            <small class="text-muted">(All Users)</small>
        <?php endif; ?>
    </h1>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($activities)): ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Activity Found</h5>
                <p class="text-muted">No activities have been logged yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <?php if (hasRole('admin')): ?>
                                <th>User</th>
                            <?php endif; ?>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <?php if (hasRole('admin')): ?>
                                    <td>
                                        <strong><?php echo getFullName($activity['first_name'], $activity['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo ucfirst($activity['role']); ?></small>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-<?php 
                                        $actionColors = [
                                            'login' => 'success',
                                            'logout' => 'secondary',
                                            'create' => 'primary',
                                            'add' => 'primary',
                                            'update' => 'warning',
                                            'edit' => 'warning',
                                            'delete' => 'danger'
                                        ];
                                        $actionLower = strtolower($activity['action']);
                                        foreach ($actionColors as $key => $color) {
                                            if (strpos($actionLower, $key) !== false) {
                                                echo $color;
                                                break;
                                            }
                                        }
                                        if (!isset($color)) echo 'info';
                                    ?>">
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['details'] ?: 'No details'); ?></td>
                                <td><code><?php echo htmlspecialchars($activity['ip_address']); ?></code></td>
                                <td><?php echo formatDateTime($activity['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
