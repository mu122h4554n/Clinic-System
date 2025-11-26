<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

requireRole('patient');

$database = new Database();
$db = $database->getConnection();

// Get patient ID
$stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = 'Patient record not found. Please contact the administrator.';
    header('Location: dashboard.php');
    exit();
}

$patient_id = $patient['id'];

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $appointment_id = intval($_POST['appointment_id']);
    
    // Verify appointment belongs to this patient and can be cancelled
    $stmt = $db->prepare("
        SELECT id, appointment_date, appointment_time, status 
        FROM appointments 
        WHERE id = ? AND patient_id = ? AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        // Check if appointment is at least 24 hours away
        $appointmentDateTime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
        $appointmentTimestamp = strtotime($appointmentDateTime);
        $currentTimestamp = time();
        $hoursDifference = ($appointmentTimestamp - $currentTimestamp) / 3600;
        
        if ($hoursDifference >= 24) {
            try {
                $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$appointment_id]);
                $_SESSION['success'] = 'Appointment cancelled successfully.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error cancelling appointment: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Appointments can only be cancelled at least 24 hours in advance. Please contact the clinic.';
        }
    } else {
        $_SESSION['error'] = 'Appointment not found or cannot be cancelled.';
    }
    
    header('Location: my-appointments.php');
    exit();
}

// Get patient's appointments
$stmt = $db->prepare("
    SELECT a.*, 
           d.first_name as doctor_first, d.last_name as doctor_last,
           dt.specialization, dt.consultation_fee
    FROM appointments a
    JOIN doctors dt ON a.doctor_id = dt.id
    JOIN users d ON dt.user_id = d.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$patient_id]);
$appointments = $stmt->fetchAll();

$pageTitle = 'My Appointments';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-check me-2"></i>
        My Appointments
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="book-appointment.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Book New Appointment
        </a>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-3" id="appointmentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button">
            Upcoming
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button">
            Completed
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button">
            Cancelled
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
            All
        </button>
    </li>
</ul>

<div class="tab-content" id="appointmentTabsContent">
    <!-- Upcoming Appointments -->
    <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <?php 
                $upcomingAppointments = array_filter($appointments, function($apt) {
                    return $apt['status'] != 'completed' && $apt['status'] != 'cancelled';
                });
                ?>
                
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No upcoming appointments</h5>
                        <p class="text-muted">You don't have any upcoming appointments.</p>
                        <a href="book-appointment.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Book Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0">
                                            Dr. <?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?>
                                        </h5>
                                        <span class="badge bg-<?php 
                                            $statusColors = [
                                                'scheduled' => 'primary',
                                                'confirmed' => 'info',
                                                'in_progress' => 'warning'
                                            ];
                                            echo $statusColors[$appointment['status']] ?? 'secondary';
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($appointment['specialization']): ?>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($appointment['specialization']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-calendar me-2 text-primary"></i>
                                        <strong><?php echo formatDate($appointment['appointment_date']); ?></strong>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-clock me-2 text-primary"></i>
                                        <strong><?php echo formatTime($appointment['appointment_time']); ?></strong>
                                    </div>
                                    
                                    <?php if ($appointment['reason']): ?>
                                        <div class="mb-3">
                                            <i class="fas fa-notes-medical me-2 text-primary"></i>
                                            <small><?php echo htmlspecialchars($appointment['reason']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['consultation_fee']): ?>
                                        <div class="mb-3">
                                            <i class="fas fa-dollar-sign me-2 text-success"></i>
                                            <small>Fee: $<?php echo number_format($appointment['consultation_fee'], 2); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['status'] != 'in_progress'): ?>
                                        <?php
                                        $appointmentDateTime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
                                        $appointmentTimestamp = strtotime($appointmentDateTime);
                                        $currentTimestamp = time();
                                        $hoursDifference = ($appointmentTimestamp - $currentTimestamp) / 3600;
                                        ?>
                                        
                                        <?php if ($hoursDifference >= 24): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-times me-1"></i>Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Contact clinic to cancel (less than 24h notice)
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Completed Appointments -->
    <div class="tab-pane fade" id="completed" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <?php 
                $completedAppointments = array_filter($appointments, function($apt) {
                    return $apt['status'] == 'completed';
                });
                ?>
                
                <?php if (empty($completedAppointments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No completed appointments</h5>
                        <p class="text-muted">You don't have any completed appointments yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Reason</th>
                                    <th>Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedAppointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                        <small class="text-muted"><?php echo formatTime($appointment['appointment_time']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Dr. <?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?></strong>
                                        <?php if ($appointment['specialization']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $appointment['reason'] ? htmlspecialchars($appointment['reason']) : '<em class="text-muted">No reason specified</em>'; ?></td>
                                    <td>
                                        <?php if ($appointment['consultation_fee']): ?>
                                            $<?php echo number_format($appointment['consultation_fee'], 2); ?>
                                        <?php else: ?>
                                            <em class="text-muted">N/A</em>
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
    
    <!-- Cancelled Appointments -->
    <div class="tab-pane fade" id="cancelled" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <?php 
                $cancelledAppointments = array_filter($appointments, function($apt) {
                    return $apt['status'] == 'cancelled';
                });
                ?>
                
                <?php if (empty($cancelledAppointments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-ban fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No cancelled appointments</h5>
                        <p class="text-muted">You don't have any cancelled appointments.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Reason</th>
                                    <th>Cancelled On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cancelledAppointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                        <small class="text-muted"><?php echo formatTime($appointment['appointment_time']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Dr. <?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?></strong>
                                        <?php if ($appointment['specialization']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $appointment['reason'] ? htmlspecialchars($appointment['reason']) : '<em class="text-muted">No reason specified</em>'; ?></td>
                                    <td><?php echo formatDate($appointment['updated_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- All Appointments -->
    <div class="tab-pane fade" id="all" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No appointments found</h5>
                        <p class="text-muted">You haven't booked any appointments yet.</p>
                        <a href="book-appointment.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Book Your First Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                        <small class="text-muted"><?php echo formatTime($appointment['appointment_time']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Dr. <?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?></strong>
                                        <?php if ($appointment['specialization']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $appointment['reason'] ? htmlspecialchars($appointment['reason']) : '<em class="text-muted">No reason specified</em>'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $statusColors = [
                                                'scheduled' => 'primary',
                                                'confirmed' => 'info',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            echo $statusColors[$appointment['status']] ?? 'secondary';
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($appointment['consultation_fee']): ?>
                                            $<?php echo number_format($appointment['consultation_fee'], 2); ?>
                                        <?php else: ?>
                                            <em class="text-muted">N/A</em>
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
