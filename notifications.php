<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'mark_all_read':
            markAllNotificationsAsRead($db, $_SESSION['user_id']);
            $_SESSION['success'] = 'All notifications marked as read.';
            header('Location: notifications.php');
            exit();
    }
}

if (isset($_GET['read'])) {
    $notification_id = intval($_GET['read']);
    markNotificationAsRead($db, $notification_id, $_SESSION['user_id']);
    header('Location: notifications.php');
    exit();
}

// Get all notifications for current user
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bell me-2"></i>
        Notifications
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="notifications.php?action=mark_all_read" class="btn btn-outline-primary">
            <i class="fas fa-check me-2"></i>Mark All as Read
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if (empty($notifications)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Notifications</h5>
                    <p class="text-muted">You don't have any notifications yet.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="card mb-3 <?php echo $notification['is_read'] ? 'opacity-75' : 'border-primary'; ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="fas fa-<?php 
                                    $iconColors = [
                                        'appointment' => 'calendar-alt text-primary',
                                        'reminder' => 'clock text-warning',
                                        'system' => 'cog text-info',
                                        'success' => 'check-circle text-success',
                                        'warning' => 'exclamation-triangle text-warning'
                                    ];
                                    echo $iconColors[$notification['type']] ?? 'info-circle text-info';
                                ?> fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title mb-1">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary ms-2">New</span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                        <a href="notifications.php?read=<?php echo $notification['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo formatDateTime($notification['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
