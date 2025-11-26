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

// Handle patient actions (for receptionist)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && hasRole('receptionist')) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_patient':
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $phone = sanitizeInput($_POST['phone']);
                $address = sanitizeInput($_POST['address']);
                $date_of_birth = $_POST['date_of_birth'];
                $gender = sanitizeInput($_POST['gender']);
                $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name']);
                $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone']);
                $blood_type = sanitizeInput($_POST['blood_type']);
                $allergies = sanitizeInput($_POST['allergies']);
                $medical_history = sanitizeInput($_POST['medical_history']);
                
                try {
                    $db->beginTransaction();
                    
                    // Create user account
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, address) VALUES (?, ?, ?, 'patient', ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $password, $first_name, $last_name, $phone, $address]);
                    $user_id = $db->lastInsertId();
                    
                    // Create patient record
                    $stmt = $db->prepare("INSERT INTO patients (user_id, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, blood_type, allergies, medical_history) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, $blood_type, $allergies, $medical_history]);
                    
                    $db->commit();
                    $_SESSION['success'] = 'Patient registered successfully!';
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error'] = 'Error registering patient: ' . $e->getMessage();
                }
                break;
                
            case 'update_patient':
                $patient_id = intval($_POST['patient_id']);
                $user_id = intval($_POST['user_id']);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $phone = sanitizeInput($_POST['phone']);
                $address = sanitizeInput($_POST['address']);
                $date_of_birth = $_POST['date_of_birth'];
                $gender = sanitizeInput($_POST['gender']);
                $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name']);
                $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone']);
                $blood_type = sanitizeInput($_POST['blood_type']);
                $allergies = sanitizeInput($_POST['allergies']);
                $medical_history = sanitizeInput($_POST['medical_history']);
                
                try {
                    $db->beginTransaction();
                    
                    // Update user info
                    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $phone, $address, $user_id]);
                    
                    // Update patient info
                    $stmt = $db->prepare("UPDATE patients SET date_of_birth = ?, gender = ?, emergency_contact_name = ?, emergency_contact_phone = ?, blood_type = ?, allergies = ?, medical_history = ? WHERE id = ?");
                    $stmt->execute([$date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, $blood_type, $allergies, $medical_history, $patient_id]);
                    
                    $db->commit();
                    $_SESSION['success'] = 'Patient information updated successfully!';
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error'] = 'Error updating patient: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: patients.php');
        exit();
    }
}

// Get patients
$stmt = $db->query("
    SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.created_at,
           COUNT(a.id) as appointment_count,
           MAX(a.appointment_date) as last_appointment
    FROM patients p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN appointments a ON p.id = a.patient_id
    WHERE u.is_active = 1
    GROUP BY p.id
    ORDER BY u.first_name, u.last_name
");
$patients = $stmt->fetchAll();

// Get patient to edit if specified
$editPatient = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit']) && hasRole('receptionist')) {
    $stmt = $db->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.address
        FROM patients p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([intval($_GET['edit'])]);
    $editPatient = $stmt->fetch();
}

$pageTitle = 'Patients';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-injured me-2"></i>
        Patients
    </h1>
    <?php if (hasRole('receptionist')): ?>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
            <i class="fas fa-plus me-2"></i>Register New Patient
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Patients List -->
<div class="card">
    <div class="card-body">
        <?php if (empty($patients)): ?>
            <div class="text-center py-4">
                <i class="fas fa-user-injured fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No patients found</h5>
                <p class="text-muted">No patients are registered in the system.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Patient Info</th>
                            <th>Contact</th>
                            <th>Medical Info</th>
                            <th>Appointments</th>
                            <th>Registered</th>
                            <?php if (hasRole('receptionist')): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <strong><?php echo getFullName($patient['first_name'], $patient['last_name']); ?></strong>
                                <?php if ($patient['date_of_birth']): ?>
                                    <br><small class="text-muted">
                                        Age: <?php echo date_diff(date_create($patient['date_of_birth']), date_create('today'))->y; ?> years
                                        (<?php echo formatDate($patient['date_of_birth']); ?>)
                                    </small>
                                <?php endif; ?>
                                <?php if ($patient['gender']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-<?php echo $patient['gender'] == 'male' ? 'mars' : 'venus'; ?> me-1"></i>
                                        <?php echo ucfirst($patient['gender']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($patient['phone']): ?>
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($patient['phone']); ?><br>
                                <?php endif; ?>
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($patient['email']); ?>
                                <?php if ($patient['address']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($patient['address']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($patient['blood_type']): ?>
                                    <span class="badge bg-danger"><?php echo $patient['blood_type']; ?></span><br>
                                <?php endif; ?>
                                <?php if ($patient['allergies']): ?>
                                    <small class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Allergies: <?php echo htmlspecialchars($patient['allergies']); ?>
                                    </small><br>
                                <?php endif; ?>
                                <?php if ($patient['emergency_contact_name']): ?>
                                    <small class="text-muted">
                                        Emergency: <?php echo htmlspecialchars($patient['emergency_contact_name']); ?>
                                        <?php if ($patient['emergency_contact_phone']): ?>
                                            (<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>)
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $patient['appointment_count']; ?> total</span>
                                <?php if ($patient['last_appointment']): ?>
                                    <br><small class="text-muted">
                                        Last: <?php echo formatDate($patient['last_appointment']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($patient['created_at']); ?></td>
                            <?php if (hasRole('receptionist')): ?>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="?edit=<?php echo $patient['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="appointments.php?patient=<?php echo $patient['id']; ?>" class="btn btn-outline-info" title="View Appointments">
                                        <i class="fas fa-calendar-alt"></i>
                                    </a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole('receptionist')): ?>
<!-- Add/Edit Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php echo $editPatient ? 'Edit Patient Information' : 'Register New Patient'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editPatient ? 'update_patient' : 'add_patient'; ?>">
                    <?php if ($editPatient): ?>
                        <input type="hidden" name="patient_id" value="<?php echo $editPatient['id']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $editPatient['user_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Personal Information</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo $editPatient ? htmlspecialchars($editPatient['first_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo $editPatient ? htmlspecialchars($editPatient['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <?php if (!$editPatient): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $editPatient ? htmlspecialchars($editPatient['phone']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo $editPatient ? $editPatient['date_of_birth'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-control" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($editPatient && $editPatient['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($editPatient && $editPatient['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($editPatient && $editPatient['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select class="form-control" id="blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <?php 
                                $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($bloodTypes as $type): 
                                ?>
                                <option value="<?php echo $type; ?>" <?php echo ($editPatient && $editPatient['blood_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo $editPatient ? htmlspecialchars($editPatient['address']) : ''; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Emergency Contact</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?php echo $editPatient ? htmlspecialchars($editPatient['emergency_contact_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?php echo $editPatient ? htmlspecialchars($editPatient['emergency_contact_phone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Medical Information</h6>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                  placeholder="List any known allergies"><?php echo $editPatient ? htmlspecialchars($editPatient['allergies']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="medical_history" class="form-label">Medical History</label>
                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                  placeholder="Brief medical history"><?php echo $editPatient ? htmlspecialchars($editPatient['medical_history']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editPatient ? 'Update Patient' : 'Register Patient'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editPatient): ?>
<script>
// Auto-open modal for editing
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addPatientModal'));
    modal.show();
});
</script>
<?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
