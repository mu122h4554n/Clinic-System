<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

requireAnyRole(['doctor', 'receptionist']);

$database = new Database();
$db = $database->getConnection();

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $appointment_id = intval($_POST['appointment_id']);
            $status = sanitizeInput($_POST['status']);
            
            try {
                // Get appointment details for logging and notifications
                $stmt = $db->prepare("
                    SELECT a.*, pt.user_id as patient_user_id, dt.user_id as doctor_user_id
                    FROM appointments a
                    JOIN patients pt ON a.patient_id = pt.id
                    JOIN doctors dt ON a.doctor_id = dt.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$appointment_id]);
                $appointment = $stmt->fetch();
                
                if ($appointment) {
                    $stmt = $db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $appointment_id]);
                    
                    // Log activity
                    logActivity($db, 'update_appointment', "Appointment status changed to: $status for appointment ID: $appointment_id");
                    
                    // Send notification to patient
                    notifyAppointmentStatusChange($db, $appointment['patient_user_id'], $status, 
                        $appointment['appointment_date'], $appointment['appointment_time']);
                    
                    $_SESSION['success'] = 'Appointment status updated successfully!';
                } else {
                    $_SESSION['error'] = 'Appointment not found.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error updating appointment: ' . $e->getMessage();
            }
            break;
            
        case 'add_appointment':
            if (hasRole('receptionist')) {
                $patient_id = intval($_POST['patient_id']);
                $doctor_id = intval($_POST['doctor_id']);
                $appointment_date = $_POST['appointment_date'];
                $appointment_time = $_POST['appointment_time'];
                $reason = sanitizeInput($_POST['reason']);
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $reason, $_SESSION['user_id']]);
                    
                    // Get user IDs for notifications
                    $stmt = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
                    $stmt->execute([$patient_id]);
                    $patient_user_id = $stmt->fetch()['user_id'];
                    
                    $stmt = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
                    $stmt->execute([$doctor_id]);
                    $doctor_user_id = $stmt->fetch()['user_id'];
                    
                    // Log activity
                    logActivity($db, 'create_appointment', "Appointment scheduled for patient ID: $patient_id with doctor ID: $doctor_id");
                    
                    // Send notifications
                    notifyAppointmentBooked($db, $patient_user_id, $doctor_user_id, $appointment_date, $appointment_time);
                    
                    $_SESSION['success'] = 'Appointment scheduled successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error scheduling appointment: ' . $e->getMessage();
                }
            }
            break;
    }
    
    header('Location: appointments.php');
    exit();
}

// Get appointments based on user role
$appointments = [];

if (hasRole('doctor')) {
    // Doctor sees only their appointments
    $stmt = $db->prepare("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               pt.date_of_birth, pt.gender, p.phone as patient_phone,
               d.first_name as doctor_first, d.last_name as doctor_last
        FROM appointments a
        JOIN patients pt ON a.patient_id = pt.id
        JOIN users p ON pt.user_id = p.id
        JOIN doctors dt ON a.doctor_id = dt.id
        JOIN users d ON dt.user_id = d.id
        WHERE dt.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $appointments = $stmt->fetchAll();
    
} else {
    // Receptionist sees all appointments
    $stmt = $db->query("
        SELECT a.*, 
               p.first_name as patient_first, p.last_name as patient_last,
               pt.date_of_birth, pt.gender, p.phone as patient_phone,
               d.first_name as doctor_first, d.last_name as doctor_last
        FROM appointments a
        JOIN patients pt ON a.patient_id = pt.id
        JOIN users p ON pt.user_id = p.id
        JOIN doctors dt ON a.doctor_id = dt.id
        JOIN users d ON dt.user_id = d.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $appointments = $stmt->fetchAll();
}

// Get patients and doctors for receptionist
$patients = [];
$doctors = [];

if (hasRole('receptionist')) {
    $stmt = $db->query("
        SELECT p.id, u.first_name, u.last_name, u.phone 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE u.is_active = 1 
        ORDER BY u.first_name, u.last_name
    ");
    $patients = $stmt->fetchAll();
    
    $stmt = $db->query("
        SELECT d.id, u.first_name, u.last_name, d.specialization 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.is_active = 1 
        ORDER BY u.first_name, u.last_name
    ");
    $doctors = $stmt->fetchAll();
}

$pageTitle = 'Appointments';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>
        Appointments
    </h1>
    <?php if (hasRole('receptionist')): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
            <i class="fas fa-plus me-2"></i>Schedule Appointment
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-3" id="appointmentTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button">
            All Appointments
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="today-tab" data-bs-toggle="tab" data-bs-target="#today" type="button">
            Today
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button">
            Upcoming
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button">
            Completed
        </button>
    </li>
</ul>

<div class="tab-content" id="appointmentTabsContent">
    <div class="tab-pane fade show active" id="all" role="tabpanel">
        <?php include '../includes/appointments-table.php'; ?>
    </div>
    <div class="tab-pane fade" id="today" role="tabpanel">
        <?php
        $filteredAppointments = array_filter($appointments, fn($apt) => $apt['appointment_date'] == date('Y-m-d'));
        include '../includes/appointments-table.php';
        ?>
    </div>
    <div class="tab-pane fade" id="upcoming" role="tabpanel">
        <?php
        $filteredAppointments = array_filter($appointments, fn($apt) => $apt['appointment_date'] > date('Y-m-d') && $apt['status'] != 'completed' && $apt['status'] != 'cancelled');
        include '../includes/appointments-table.php';
        ?>
    </div>
    <div class="tab-pane fade" id="completed" role="tabpanel">
        <?php
        $filteredAppointments = array_filter($appointments, fn($apt) => $apt['status'] == 'completed');
        include '../includes/appointments-table.php';
        ?>
    </div>
</div>

<?php if (hasRole('receptionist')): ?>
<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_appointment">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-control" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo getFullName($patient['first_name'], $patient['last_name']); ?>
                                    <?php if ($patient['phone']): ?>
                                        - <?php echo $patient['phone']; ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="doctor_id" class="form-label">Doctor *</label>
                            <select class="form-control" id="doctor_id" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo getFullName($doctor['first_name'], $doctor['last_name']); ?>
                                    <?php if ($doctor['specialization']): ?>
                                        - <?php echo $doctor['specialization']; ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="appointment_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="appointment_time" class="form-label">Time *</label>
                            <input type="time" class="form-control" id="appointment_time" name="appointment_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" 
                                  placeholder="Brief description of the reason for the appointment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
