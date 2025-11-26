<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

// Get doctor profile
$stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = 'Doctor profile not found.';
    header('Location: dashboard.php');
    exit();
}

$doctor_id = $doctor['id'];

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $review_notes = isset($_POST['review_notes']) ? sanitizeInput($_POST['review_notes']) : null;

    try {
        $selectBaseQuery = "
            SELECT mo.*, m.name AS medicine_name, m.stock_quantity, m.id AS med_id, p.user_id AS patient_user_id
            FROM medicine_orders mo
            JOIN medicines m ON mo.medicine_id = m.id
            JOIN patients p ON mo.patient_id = p.id
            WHERE mo.id = ? AND mo.doctor_id = ?
        ";

        if ($action === 'approve') {
            $db->beginTransaction();
            $stmt = $db->prepare($selectBaseQuery . " FOR UPDATE");
        } else {
            $stmt = $db->prepare($selectBaseQuery);
        }

        $stmt->execute([$order_id, $doctor_id]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception('Medicine request not found.');
        }

        if ($action === 'approve') {
            if ($order['status'] !== 'pending') {
                throw new Exception('Only pending requests can be approved.');
            }

            if ($order['stock_quantity'] < $order['quantity']) {
                throw new Exception('Insufficient stock to approve this request.');
            }

            $db->beginTransaction();

            $stmt = $db->prepare("
                UPDATE medicine_orders
                SET status = 'approved',
                    review_notes = ?,
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$review_notes, $_SESSION['user_id'], $order_id]);

            $stmt = $db->prepare("
                UPDATE medicines
                SET stock_quantity = stock_quantity - ?
                WHERE id = ? AND stock_quantity >= ?
            ");
            $stmt->execute([$order['quantity'], $order['med_id'], $order['quantity']]);

            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                throw new Exception('Stock changed while approving the request. Please try again.');
            }

            $db->commit();

            logActivity($db, 'medicine_request_approve', "Approved medicine order #{$order_id}");
            notifyMedicineOrderStatus($db, $order['patient_user_id'], $order['medicine_name'], 'approved');

            $_SESSION['success'] = 'Medicine request approved successfully.';
        } elseif ($action === 'reject') {
            if ($order['status'] !== 'pending') {
                throw new Exception('Only pending requests can be rejected.');
            }

            $stmt = $db->prepare("
                UPDATE medicine_orders
                SET status = 'rejected',
                    review_notes = ?,
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$review_notes, $_SESSION['user_id'], $order_id]);

            logActivity($db, 'medicine_request_reject', "Rejected medicine order #{$order_id}");
            notifyMedicineOrderStatus($db, $order['patient_user_id'], $order['medicine_name'], 'rejected');

            $_SESSION['success'] = 'Medicine request rejected.';
        } elseif ($action === 'fulfill') {
            if ($order['status'] !== 'approved') {
                throw new Exception('Only approved requests can be marked as fulfilled.');
            }

            $stmt = $db->prepare("
                UPDATE medicine_orders
                SET status = 'fulfilled',
                    fulfilled_at = NOW(),
                    review_notes = COALESCE(NULLIF(?, ''), review_notes),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$review_notes, $order_id]);

            logActivity($db, 'medicine_request_fulfill', "Fulfilled medicine order #{$order_id}");
            notifyMedicineOrderStatus($db, $order['patient_user_id'], $order['medicine_name'], 'fulfilled');

            $_SESSION['success'] = 'Order marked as fulfilled.';
        } else {
            throw new Exception('Invalid action selected.');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: medicine-requests.php');
    exit();
}

// Pending requests
try {
    $stmt = $db->prepare("
        SELECT mo.*, m.name AS medicine_name, m.price,
               u.first_name, u.last_name, u.phone,
               p.id AS patient_id
        FROM medicine_orders mo
        JOIN medicines m ON mo.medicine_id = m.id
        JOIN patients p ON mo.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE mo.doctor_id = ? AND mo.status = 'pending'
        ORDER BY mo.created_at ASC
    ");
    $stmt->execute([$doctor_id]);
    $pendingRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $_SESSION['error'] = 'Medicine tables not found. Please run the database migration script: <a href="update_medicine_tables.php">update_medicine_tables.php</a>';
        header('Location: dashboard.php');
        exit();
    }
    throw $e;
}

// Recent history
try {
    $stmt = $db->prepare("
        SELECT mo.*, m.name AS medicine_name,
               u.first_name, u.last_name
        FROM medicine_orders mo
        JOIN medicines m ON mo.medicine_id = m.id
        JOIN patients p ON mo.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE mo.doctor_id = ?
        ORDER BY mo.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor_id]);
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $_SESSION['error'] = 'Medicine tables not found. Please run the database migration script: <a href="update_medicine_tables.php">update_medicine_tables.php</a>';
        header('Location: dashboard.php');
        exit();
    }
    throw $e;
}

$pageTitle = 'Medicine Requests';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-prescription-bottle-alt me-2"></i>
        Medicine Requests
    </h1>
    <span class="badge bg-secondary fs-6">
        Pending: <?php echo count($pendingRequests); ?>
    </span>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>Pending approvals
                </h5>
                <span class="badge bg-warning text-dark"><?php echo count($pendingRequests); ?> awaiting action</span>
            </div>
            <div class="card-body">
                <?php if (empty($pendingRequests)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-smile-beam fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No pending requests</h5>
                        <p class="text-muted">Patients will submit requests as soon as they need medications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingRequests as $request): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($request['medicine_name']); ?></h5>
                                <small class="text-muted">Requested on <?php echo formatDateTime($request['created_at']); ?></small>
                            </div>
                            <span class="badge bg-warning text-dark">Pending</span>
                        </div>
                        <div class="mb-2">
                            <strong>Patient:</strong>
                            <?php echo getFullName($request['first_name'], $request['last_name']); ?>
                        </div>
                        <div class="mb-2">
                            <strong>Quantity:</strong> <?php echo intval($request['quantity']); ?>
                            <span class="ms-3"><strong>Price:</strong> $<?php echo number_format($request['price'], 2); ?></span>
                        </div>
                        <?php if (!empty($request['patient_notes'])): ?>
                        <div class="mb-3">
                            <strong>Symptoms / Notes:</strong>
                            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($request['patient_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="order_id" value="<?php echo $request['id']; ?>">
                            <div class="mb-2">
                                <label class="form-label">Doctor notes (optional)</label>
                                <textarea name="review_notes" class="form-control" rows="2" placeholder="Add instructions or rationale (optional)"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this medicine request?');">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Guidelines</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Approve only when clinically appropriate and stock is available.
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-comment-medical text-primary me-2"></i>
                        Add doctor notes for pharmacists or patients to follow.
                    </li>
                    <li>
                        <i class="fas fa-boxes text-warning me-2"></i>
                        Mark orders as fulfilled after medicine pickup or dispensing.
                    </li>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                    <p class="text-muted mb-0">No medicine orders reviewed yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Medicine</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo getFullName($order['first_name'], $order['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['medicine_name']); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'fulfilled' => 'primary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $statusColors[$order['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['status'] === 'approved'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="review_notes" value="<?php echo htmlspecialchars($order['review_notes'] ?? '', ENT_QUOTES); ?>">
                                                <button type="submit" name="action" value="fulfill" class="btn btn-sm btn-outline-primary" onclick="return confirm('Mark this order as fulfilled?');">
                                                    Fulfill
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

