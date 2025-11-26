<?php
// Patient medicine ordering page
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('patient');

$database = new Database();
$db = $database->getConnection();

// Get patient profile
$stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = 'Patient record not found. Please contact the administrator.';
    header('Location: dashboard.php');
    exit();
}

$patient_id = $patient['id'];
$currentUser = getCurrentUser($db);
$patientFullName = $currentUser ? getFullName($currentUser['first_name'], $currentUser['last_name']) : 'Patient';

// Handle medicine order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = intval($_POST['medicine_id'] ?? 0);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $patient_notes = isset($_POST['patient_notes']) ? sanitizeInput($_POST['patient_notes']) : null;

    try {
        $stmt = $db->prepare("SELECT * FROM medicines WHERE id = ?");
        $stmt->execute([$medicine_id]);
        $medicine = $stmt->fetch();

        if (!$medicine) {
            throw new Exception('Selected medicine not found.');
        }

        if ($quantity > $medicine['stock_quantity']) {
            throw new Exception('Requested quantity exceeds available stock.');
        }

        $requiresApproval = (bool)$medicine['requires_approval'] || $medicine['category'] === 'major';
        $appointmentRequirementMet = false;
        $assignedDoctorId = null;

        if ($requiresApproval) {
            if (empty($patient_notes)) {
                throw new Exception('Please describe your symptoms or reason for requesting this medicine.');
            }

            // Ensure patient has an upcoming appointment
            $stmt = $db->prepare("
                SELECT id, doctor_id, appointment_date, appointment_time
                FROM appointments
                WHERE patient_id = ?
                  AND status IN ('scheduled', 'confirmed', 'in_progress')
                  AND appointment_date >= CURDATE()
                ORDER BY appointment_date ASC, appointment_time ASC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $appointment = $stmt->fetch();

            if ($appointment) {
                $appointmentRequirementMet = true;
                $assignedDoctorId = $appointment['doctor_id'];
            }

            if (!$appointmentRequirementMet) {
                throw new Exception('An active appointment with a doctor is required before requesting this medicine. Please book an appointment.');
            }
        }

        if ($requiresApproval) {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO medicine_orders (patient_id, medicine_id, quantity, status, patient_notes, doctor_id, requires_appointment)
                VALUES (?, ?, ?, 'pending', ?, ?, TRUE)
            ");
            $stmt->execute([$patient_id, $medicine_id, $quantity, $patient_notes, $assignedDoctorId]);

            $orderId = $db->lastInsertId();
            $db->commit();

            logActivity($db, 'medicine_request_submit', "Patient requested {$medicine['name']} (Order #$orderId)");

            // Notify patient and assigned doctor
            notifyMedicineOrderSubmitted(
                $db,
                $_SESSION['user_id'],
                $medicine['name'],
                true,
                $assignedDoctorId,
                $patientFullName
            );

            $_SESSION['success'] = 'Your request has been sent to your doctor for approval.';
        } else {
            $db->beginTransaction();

            // Create order and mark as auto approved
            $stmt = $db->prepare("
                INSERT INTO medicine_orders (patient_id, medicine_id, quantity, status, patient_notes, requires_appointment)
                VALUES (?, ?, ?, 'auto_approved', ?, FALSE)
            ");
            $stmt->execute([$patient_id, $medicine_id, $quantity, $patient_notes]);
            $orderId = $db->lastInsertId();

            // Deduct stock
            $stmt = $db->prepare("
                UPDATE medicines
                SET stock_quantity = stock_quantity - ?
                WHERE id = ? AND stock_quantity >= ?
            ");
            $stmt->execute([$quantity, $medicine_id, $quantity]);

            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                throw new Exception('Unable to process order due to stock change. Please try again.');
            }

            $db->commit();

            logActivity($db, 'medicine_purchase', "Patient purchased {$medicine['name']} (Order #$orderId)");

            notifyMedicineOrderSubmitted(
                $db,
                $_SESSION['user_id'],
                $medicine['name'],
                false
            );

            $_SESSION['success'] = 'Medicine purchased successfully. Please follow the usage instructions provided.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: medicines.php');
    exit();
}

// Load medicines
try {
    $stmt = $db->query("SELECT * FROM medicines ORDER BY category ASC, name ASC");
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $_SESSION['error'] = 'Medicine tables not found. Please run the database migration script: <a href="update_medicine_tables.php">update_medicine_tables.php</a>';
        header('Location: dashboard.php');
        exit();
    }
    throw $e;
}

$minorMedicines = array_filter($medicines, fn($med) => !$med['requires_approval'] && $med['category'] === 'minor');
$majorMedicines = array_filter($medicines, fn($med) => $med['requires_approval'] || $med['category'] === 'major');

// Load order history
$stmt = $db->prepare("
    SELECT mo.*, m.name, m.category
    FROM medicine_orders mo
    JOIN medicines m ON mo.medicine_id = m.id
    WHERE mo.patient_id = ?
    ORDER BY mo.created_at DESC
");
$stmt->execute([$patient_id]);
$orderHistory = $stmt->fetchAll();

$pageTitle = 'Medicines';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-pills me-2"></i>
        Medicine Center
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="book-appointment.php" class="btn btn-outline-primary">
            <i class="fas fa-calendar-check me-2"></i>Book Doctor Appointment
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-hand-holding-medical me-2 text-success"></i>
                    Minor Conditions (Instant purchase)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($minorMedicines)): ?>
                    <p class="text-muted mb-0">No over-the-counter medicines are available at the moment.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($minorMedicines as $medicine): ?>
                        <div class="col-md-6 mb-4">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($medicine['name']); ?></h5>
                                    <span class="badge bg-success">Minor</span>
                                </div>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($medicine['description']); ?></p>
                                <p class="mb-1"><strong>Price:</strong> $<?php echo number_format($medicine['price'], 2); ?></p>
                                <p class="mb-2"><strong>Stock:</strong> <?php echo intval($medicine['stock_quantity']); ?> units</p>
                                <p class="small text-muted">
                                    <strong>Usage:</strong> <?php echo htmlspecialchars($medicine['usage_instructions']); ?>
                                </p>
                                <form method="POST" class="mt-3 needs-validation" novalidate>
                                    <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Quantity</label>
                                        <input type="number"
                                               class="form-control"
                                               name="quantity"
                                               min="1"
                                               max="<?php echo max(1, intval($medicine['stock_quantity'])); ?>"
                                               value="1"
                                               required
                                               <?php echo $medicine['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                        <div class="invalid-feedback">
                                            Please enter a valid quantity.
                                        </div>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary" <?php echo $medicine['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart me-2"></i>Purchase Now
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-user-md me-2 text-danger"></i>
                    Major Conditions (Doctor approval required)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    These medicines require an active appointment with a doctor. Submit a request with your symptoms and your doctor will review it during your next visit.
                </p>
                <?php if (empty($majorMedicines)): ?>
                    <p class="text-muted mb-0">No prescription medicines are available right now.</p>
                <?php else: ?>
                    <?php foreach ($majorMedicines as $medicine): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($medicine['name']); ?></h5>
                                <small class="text-muted">Doctor approval required</small>
                            </div>
                            <span class="badge bg-danger">Major</span>
                        </div>
                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($medicine['description']); ?></p>
                        <p class="mb-1"><strong>Price:</strong> $<?php echo number_format($medicine['price'], 2); ?></p>
                        <p class="mb-2"><strong>Stock:</strong> <?php echo intval($medicine['stock_quantity']); ?> units</p>
                        <p class="small text-muted">
                            <strong>Usage:</strong> <?php echo htmlspecialchars($medicine['usage_instructions']); ?>
                        </p>
                        <form method="POST" class="mt-3 needs-validation" novalidate>
                            <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Quantity</label>
                                    <input type="number"
                                           class="form-control"
                                           name="quantity"
                                           min="1"
                                           max="<?php echo max(1, intval($medicine['stock_quantity'])); ?>"
                                           value="1"
                                           required
                                           <?php echo $medicine['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <div class="invalid-feedback">
                                        Please enter a valid quantity.
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Symptoms / Notes for Doctor</label>
                                    <textarea class="form-control"
                                              name="patient_notes"
                                              rows="3"
                                              placeholder="Describe your current symptoms or why you need this medicine"
                                              required
                                              <?php echo $medicine['stock_quantity'] <= 0 ? 'disabled' : ''; ?>></textarea>
                                    <div class="invalid-feedback">
                                        Please provide details for your doctor to review.
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid mt-3">
                                <button type="submit" class="btn btn-outline-primary" <?php echo $medicine['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane me-2"></i>Request Doctor Approval
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
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>How it works</h5>
            </div>
            <div class="card-body">
                <ol class="list-group list-group-numbered">
                    <li class="list-group-item">
                        <strong>Minor medicines:</strong> Purchase instantly for mild symptoms.
                    </li>
                    <li class="list-group-item">
                        <strong>Major medicines:</strong> Require an upcoming appointment for doctor approval.
                    </li>
                    <li class="list-group-item">
                        <strong>Notifications:</strong> You'll receive updates when your request is approved or rejected.
                    </li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Order History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($orderHistory)): ?>
                    <p class="text-muted">No medicine orders yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Status</th>
                                    <th>Qty</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderHistory as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo ucfirst($order['category']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'auto_approved' => 'success',
                                            'rejected' => 'danger',
                                            'fulfilled' => 'primary'
                                        ];
                                        $status = $order['status'];
                                        ?>
                                        <span class="badge bg-<?php echo $statusColors[$status] ?? 'secondary'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo intval($order['quantity']); ?></td>
                                    <td><small><?php echo formatDateTime($order['created_at']); ?></small></td>
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

