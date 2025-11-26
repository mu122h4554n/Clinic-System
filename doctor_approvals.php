<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$GLOBALS['db'] = $db; // Make available for header.php

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $action = $_POST['action'];
    
    try {
        if ($action == 'approve') {
            // Approve the doctor
            $stmt = $db->prepare("
                UPDATE doctors 
                SET approval_status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ");
            $approval_notes = sanitizeInput($_POST['approval_notes'] ?? 'Approved by administrator');
            $stmt->execute([$_SESSION['user_id'], $approval_notes, $doctor_id]);
            
            // Activate the user account
            $stmt = $db->prepare("UPDATE users u JOIN doctors d ON u.id = d.user_id SET u.is_active = 1 WHERE d.id = ?");
            $stmt->execute([$doctor_id]);
            
            // Get doctor user_id and info
            $stmt = $db->prepare("
                SELECT u.id as user_id, u.first_name, u.last_name, u.email, d.specialization 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.id = ?
            ");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch();
            
            // Send notification to doctor
            createNotification($db, $doctor['user_id'], 'Account Approved!', 
                'Congratulations! Your doctor account has been approved. You can now log in and start practicing.', 'success');
            
            // Log activity
            logActivity($db, 'approve_doctor', "Approved doctor registration: Dr. {$doctor['first_name']} {$doctor['last_name']} ({$doctor['specialization']})");
            
            $_SESSION['success'] = "Dr. {$doctor['first_name']} {$doctor['last_name']} has been approved successfully!";
            
        } elseif ($action == 'reject') {
            // Reject the doctor
            $stmt = $db->prepare("
                UPDATE doctors 
                SET approval_status = 'rejected', 
                    approved_by = ?, 
                    approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ");
            $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? 'Registration rejected by administrator');
            $stmt->execute([$_SESSION['user_id'], $rejection_reason, $doctor_id]);
            
            // Get doctor user_id and info
            $stmt = $db->prepare("
                SELECT u.id as user_id, u.first_name, u.last_name 
                FROM doctors d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.id = ?
            ");
            $stmt->execute([$doctor_id]);
            $doctor = $stmt->fetch();
            
            // Send notification to doctor
            createNotification($db, $doctor['user_id'], 'Registration Status Update', 
                "Your doctor registration has been reviewed. Reason: $rejection_reason. Please contact administration for more information.", 'warning');
            
            // Log activity
            logActivity($db, 'reject_doctor', "Rejected doctor registration: Dr. {$doctor['first_name']} {$doctor['last_name']}");
            
            $_SESSION['success'] = "Registration has been rejected.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error processing request: ' . $e->getMessage();
    }
    
    header('Location: doctor_approvals.php');
    exit();
}

// Get all pending doctor registrations
$stmt = $db->query("
    SELECT d.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.address, u.created_at as registration_date
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.approval_status = 'pending'
    ORDER BY u.created_at DESC
");
$pending_doctors = $stmt->fetchAll();

// Get approved doctors
$stmt = $db->query("
    SELECT d.*, u.username, u.email, u.first_name, u.last_name, u.phone, u.is_active,
           approver.first_name as approver_first, approver.last_name as approver_last
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users approver ON d.approved_by = approver.id
    WHERE d.approval_status = 'approved'
    ORDER BY d.approved_at DESC
    LIMIT 20
");
$approved_doctors = $stmt->fetchAll();

// Get rejected doctors
$stmt = $db->query("
    SELECT d.*, u.username, u.email, u.first_name, u.last_name, u.phone,
           approver.first_name as approver_first, approver.last_name as approver_last
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users approver ON d.approved_by = approver.id
    WHERE d.approval_status = 'rejected'
    ORDER BY d.approved_at DESC
    LIMIT 20
");
$rejected_doctors = $stmt->fetchAll();

$pageTitle = 'Doctor Approvals';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-md me-2"></i>
        Doctor Registration Approvals
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge bg-warning fs-6">
            <?php echo count($pending_doctors); ?> Pending
        </span>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php 
        echo $_SESSION['error']; 
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Pending Approvals -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Registrations (<?php echo count($pending_doctors); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($pending_doctors)): ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <p class="text-muted">No pending doctor registrations at this time.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Doctor Info</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>License</th>
                            <th>Experience</th>
                            <th>Fee</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_doctors as $doctor): ?>
                        <tr>
                            <td>
                                <strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong><br>
                                <small class="text-muted">@<?php echo htmlspecialchars($doctor['username']); ?></small>
                            </td>
                            <td>
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($doctor['email']); ?><br>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?>
                            </td>
                            <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                            <td><code><?php echo htmlspecialchars($doctor['license_number']); ?></code></td>
                            <td><?php echo $doctor['years_experience']; ?> years</td>
                            <td>$<?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                            <td><?php echo formatDate($doctor['registration_date']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                        data-bs-target="#viewModal<?php echo $doctor['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                        data-bs-target="#approveModal<?php echo $doctor['id']; ?>">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#rejectModal<?php echo $doctor['id']; ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>

                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewModal<?php echo $doctor['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Doctor Registration Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Personal Information</h6>
                                                <p><strong>Name:</strong> Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></p>
                                                <p><strong>Username:</strong> <?php echo htmlspecialchars($doctor['username']); ?></p>
                                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></p>
                                                <p><strong>Address:</strong> <?php echo htmlspecialchars($doctor['address'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Professional Information</h6>
                                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                                <p><strong>License Number:</strong> <code><?php echo htmlspecialchars($doctor['license_number']); ?></code></p>
                                                <p><strong>Years of Experience:</strong> <?php echo $doctor['years_experience']; ?> years</p>
                                                <p><strong>Consultation Fee:</strong> $<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                                                <p><strong>Available Days:</strong>
                                                    <?php
                                                    $days = json_decode($doctor['available_days'], true);
                                                    echo $days ? implode(', ', array_map('ucfirst', $days)) : 'Not specified';
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                        <hr>
                                        <p><strong>Registration Date:</strong> <?php echo formatDateTime($doctor['registration_date']); ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?php echo $doctor['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Approve Doctor Registration</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <p>Are you sure you want to approve the registration for:</p>
                                            <p class="text-center"><strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($doctor['specialization']); ?></small></p>

                                            <div class="mb-3">
                                                <label for="approval_notes<?php echo $doctor['id']; ?>" class="form-label">Approval Notes (Optional)</label>
                                                <textarea class="form-control" id="approval_notes<?php echo $doctor['id']; ?>"
                                                          name="approval_notes" rows="2"
                                                          placeholder="Add any notes about this approval..."></textarea>
                                            </div>

                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check me-2"></i>Approve Registration
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $doctor['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">Reject Doctor Registration</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <p>Are you sure you want to reject the registration for:</p>
                                            <p class="text-center"><strong>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></strong></p>

                                            <div class="mb-3">
                                                <label for="rejection_reason<?php echo $doctor['id']; ?>" class="form-label">Rejection Reason *</label>
                                                <textarea class="form-control" id="rejection_reason<?php echo $doctor['id']; ?>"
                                                          name="rejection_reason" rows="3" required
                                                          placeholder="Please provide a reason for rejection..."></textarea>
                                            </div>

                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-times me-2"></i>Reject Registration
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
