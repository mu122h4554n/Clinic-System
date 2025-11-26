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

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_id = intval($_POST['doctor_id']);
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason = sanitizeInput($_POST['reason']);
    
    // Validate the booking
    $errors = [];
    
    // Check if date is not in the past
    if ($appointment_date < date('Y-m-d')) {
        $errors[] = 'Cannot book appointment for past dates.';
    }
    
    // Check if doctor exists and is active
    $stmt = $db->prepare("
        SELECT d.id, u.first_name, u.last_name 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ? AND u.is_active = 1
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        $errors[] = 'Selected doctor is not available.';
    }
    
    // Check for conflicting appointments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
    $conflict = $stmt->fetch();
    
    if ($conflict['count'] > 0) {
        $errors[] = 'This time slot is already booked. Please select another time.';
    }
    
    // Check if patient already has appointment on same date
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE patient_id = ? AND appointment_date = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $stmt->execute([$patient_id, $appointment_date]);
    $existing = $stmt->fetch();
    
    if ($existing['count'] > 0) {
        $errors[] = 'You already have an appointment on this date.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $reason, $_SESSION['user_id']]);
            
            // Get doctor user ID for notification
            $stmt = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
            $stmt->execute([$doctor_id]);
            $doctor_user_id = $stmt->fetch()['user_id'];
            
            // Log activity
            logActivity($db, 'book_appointment', "Patient booked appointment with doctor ID: $doctor_id for $appointment_date at $appointment_time");
            
            // Send notifications
            notifyAppointmentBooked($db, $_SESSION['user_id'], $doctor_user_id, $appointment_date, $appointment_time);
            
            $_SESSION['success'] = 'Appointment booked successfully! You will receive a confirmation soon.';
            header('Location: my-appointments.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error booking appointment: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Get available doctors
$stmt = $db->query("
    SELECT d.id, u.first_name, u.last_name, d.specialization, d.consultation_fee,
           d.available_days, d.available_hours
    FROM doctors d 
    JOIN users u ON d.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.first_name, u.last_name
");
$doctors = $stmt->fetchAll();

$pageTitle = 'Book Appointment';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-plus me-2"></i>
        Book Appointment
    </h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calendar-alt me-2"></i>Schedule New Appointment</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="doctor_id" class="form-label">Select Doctor *</label>
                            <select class="form-control" id="doctor_id" name="doctor_id" required onchange="updateDoctorInfo()">
                                <option value="">Choose a doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        data-specialization="<?php echo htmlspecialchars($doctor['specialization']); ?>"
                                        data-fee="<?php echo $doctor['consultation_fee']; ?>"
                                        data-days="<?php echo htmlspecialchars($doctor['available_days']); ?>"
                                        data-hours="<?php echo htmlspecialchars($doctor['available_hours']); ?>">
                                    Dr. <?php echo getFullName($doctor['first_name'], $doctor['last_name']); ?>
                                    <?php if ($doctor['specialization']): ?>
                                        - <?php echo $doctor['specialization']; ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="appointment_date" class="form-label">Appointment Date *</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="appointment_time" class="form-label">Appointment Time *</label>
                            <select class="form-control" id="appointment_time" name="appointment_time" required>
                                <option value="">Select time</option>
                                <option value="">8.00 a.m. - 9.00 a.m.</option>
                                <option value="">9.00 a.m. - 10.00 a.m.</option>
                                <option value="">10.00 a.m. - 11.00 a.m.</option>
                                <option value="">11.00 a.m. - 12.00 p.m.</option>
                                <option value="">12.00 p.m. - 1.00 p.m.</option>
                                <option value="">1.00 p.m. - 2.00 p.m.</option>
                                <option value="">2.00 p.m. - 3.00 p.m.</option>
                                <option value="">3.00 p.m. - 4.00 p.m.</option>
                                <option value="">4.00 p.m. - 5.00 p.m.</option>
                                <option value="">5.00 p.m. - 6.00 p.m.</option>
                                <!-- Time slots will be populated by JavaScript -->
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div id="doctor-info" class="alert alert-info" style="display: none;">
                                <h6>Doctor Information</h6>
                                <div id="doctor-details"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="reason" name="reason" rows="4" 
                                  placeholder="Please describe your symptoms or reason for the appointment"></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check me-2"></i>Book Appointment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Booking Information</h5>
            </div>
            <div class="card-body">
                <h6><i class="fas fa-clock me-2"></i>Appointment Guidelines</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Appointments can be booked up to 30 days in advance</li>
                    <li><i class="fas fa-check text-success me-2"></i>Please arrive 15 minutes before your appointment</li>
                    <li><i class="fas fa-check text-success me-2"></i>Bring your ID and insurance card</li>
                    <li><i class="fas fa-check text-success me-2"></i>You can reschedule up to 24 hours before</li>
                </ul>
                
                <hr>
                
                <h6><i class="fas fa-phone me-2"></i>Need Help?</h6>
                <p class="small text-muted">
                    If you need to cancel or reschedule your appointment, please contact our reception at:
                    <br><strong>(555) 123-4567</strong>
                </p>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-user-md me-2"></i>Available Doctors</h5>
            </div>
            <div class="card-body">
                <?php foreach ($doctors as $doctor): ?>
                <div class="mb-3 p-2 border rounded">
                    <h6 class="mb-1">Dr. <?php echo getFullName($doctor['first_name'], $doctor['last_name']); ?></h6>
                    <?php if ($doctor['specialization']): ?>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                    <?php endif; ?>
                    <?php if ($doctor['consultation_fee']): ?>
                        <small class="text-success d-block">Fee: $<?php echo number_format($doctor['consultation_fee'], 2); ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateDoctorInfo() {
    const select = document.getElementById('doctor_id');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('doctor-info');
    const detailsDiv = document.getElementById('doctor-details');
    
    if (option.value) {
        const specialization = option.dataset.specialization;
        const fee = option.dataset.fee;
        
        let details = '';
        if (specialization) {
            details += `<strong>Specialization:</strong> ${specialization}<br>`;
        }
        if (fee && fee > 0) {
            details += `<strong>Consultation Fee:</strong> $${parseFloat(fee).toFixed(2)}`;
        }
        
        detailsDiv.innerHTML = details;
        infoDiv.style.display = 'block';
        
        // Update time slots
        updateTimeSlots();
    } else {
        infoDiv.style.display = 'none';
        document.getElementById('appointment_time').innerHTML = '<option value="">Select time</option>';
    }
}

function updateTimeSlots() {
    const timeSelect = document.getElementById('appointment_time');
    timeSelect.innerHTML = '<option value="">Select time</option>';
    
    // Generate time slots from 9 AM to 5 PM (excluding lunch 12-1 PM)
    const times = [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
    ];
    
    times.forEach(time => {
        const option = document.createElement('option');
        option.value = time;
        option.textContent = formatTime(time);
        timeSelect.appendChild(option);
    });
}

function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
    return `${displayHour}:${minutes} ${ampm}`;
}

// Set minimum date to tomorrow
document.addEventListener('DOMContentLoaded', function() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    
    const dateInput = document.getElementById('appointment_date');
    dateInput.min = tomorrow.toISOString().split('T')[0];
    dateInput.max = maxDate.toISOString().split('T')[0];
});
</script>

<?php include 'includes/footer.php'; ?>
