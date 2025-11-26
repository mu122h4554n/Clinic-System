<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('doctor');

$database = new Database();
$db = $database->getConnection();

// Get doctor ID
$stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$doctor = $stmt->fetch();

if (!$doctor) {
    $_SESSION['error'] = 'Doctor record not found. Please contact the administrator.';
    header('Location: ../dashboard.php');
    exit();
}

$doctor_id = $doctor['id'];

// Handle medical record actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_record':
                $patient_id = intval($_POST['patient_id']);
                $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
                $diagnosis = sanitizeInput($_POST['diagnosis']);
                $symptoms = sanitizeInput($_POST['symptoms']);
                $treatment = sanitizeInput($_POST['treatment']);
                $prescriptions = sanitizeInput($_POST['prescriptions']);
                $notes = sanitizeInput($_POST['notes']);
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, symptoms, treatment, prescriptions, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$patient_id, $doctor_id, $appointment_id, $diagnosis, $symptoms, $treatment, $prescriptions, $notes]);
                    
                    // Log activity
                    logActivity($db, 'create_medical_record', "Medical record created for patient ID: $patient_id");
                    
                    // Notify patient about new medical record
                    $stmt = $db->prepare("SELECT user_id FROM patients WHERE id = ?");
                    $stmt->execute([$patient_id]);
                    $patient_user_id = $stmt->fetch()['user_id'];
                    
                    createNotification($db, $patient_user_id, 'New Medical Record', 
                        'A new medical record has been added to your file by your doctor.', 'appointment');
                    
                    $_SESSION['success'] = 'Medical record added successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error adding medical record: ' . $e->getMessage();
                }
                break;
                
            case 'update_record':
                $record_id = intval($_POST['record_id']);
                $diagnosis = sanitizeInput($_POST['diagnosis']);
                $symptoms = sanitizeInput($_POST['symptoms']);
                $treatment = sanitizeInput($_POST['treatment']);
                $prescriptions = sanitizeInput($_POST['prescriptions']);
                $notes = sanitizeInput($_POST['notes']);
                
                try {
                    $stmt = $db->prepare("
                        UPDATE medical_records 
                        SET diagnosis = ?, symptoms = ?, treatment = ?, prescriptions = ?, notes = ?, updated_at = NOW()
                        WHERE id = ? AND doctor_id = ?
                    ");
                    $stmt->execute([$diagnosis, $symptoms, $treatment, $prescriptions, $notes, $record_id, $doctor_id]);
                    
                    // Log activity
                    logActivity($db, 'update_medical_record', "Medical record updated for record ID: $record_id");
                    
                    $_SESSION['success'] = 'Medical record updated successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error updating medical record: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: medical-records.php');
        exit();
    }
}

// Get filter parameters
$patient_filter = isset($_GET['patient']) ? intval($_GET['patient']) : 0;
$appointment_filter = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Build query based on filters
$whereClause = "WHERE mr.doctor_id = ?";
$params = [$doctor_id];

if ($patient_filter > 0) {
    $whereClause .= " AND mr.patient_id = ?";
    $params[] = $patient_filter;
}

if ($appointment_filter > 0) {
    $whereClause .= " AND mr.appointment_id = ?";
    $params[] = $appointment_filter;
}

// Get medical records
$stmt = $db->prepare("
    SELECT mr.*, 
           p.first_name as patient_first, p.last_name as patient_last,
           pt.date_of_birth, pt.gender, pt.blood_type, pt.allergies,
           a.appointment_date, a.appointment_time
    FROM medical_records mr
    JOIN patients pt ON mr.patient_id = pt.id
    JOIN users p ON pt.user_id = p.id
    LEFT JOIN appointments a ON mr.appointment_id = a.id
    $whereClause
    ORDER BY mr.created_at DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get patients for this doctor (from appointments)
$stmt = $db->prepare("
    SELECT DISTINCT pt.id, p.first_name, p.last_name
    FROM patients pt
    JOIN users p ON pt.user_id = p.id
    JOIN appointments a ON pt.id = a.patient_id
    WHERE a.doctor_id = ?
    ORDER BY p.first_name, p.last_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll();

// Get record to edit if specified
$editRecord = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("
        SELECT mr.*, 
               p.first_name as patient_first, p.last_name as patient_last
        FROM medical_records mr
        JOIN patients pt ON mr.patient_id = pt.id
        JOIN users p ON pt.user_id = p.id
        WHERE mr.id = ? AND mr.doctor_id = ?
    ");
    $stmt->execute([intval($_GET['edit']), $doctor_id]);
    $editRecord = $stmt->fetch();
}

$pageTitle = 'Medical Records';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-medical me-2"></i>
        Medical Records
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecordModal">
            <i class="fas fa-plus me-2"></i>Add Medical Record
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="patient" class="form-label">Filter by Patient</label>
                <select class="form-control" id="patient" name="patient">
                    <option value="">All Patients</option>
                    <?php foreach ($patients as $patient): ?>
                    <option value="<?php echo $patient['id']; ?>" <?php echo $patient_filter == $patient['id'] ? 'selected' : ''; ?>>
                        <?php echo getFullName($patient['first_name'], $patient['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                    <a href="medical-records.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Medical Records -->
<div class="card">
    <div class="card-body">
        <?php if (empty($records)): ?>
            <div class="text-center py-4">
                <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No medical records found</h5>
                <p class="text-muted">No medical records match your current filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($records as $record): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-start border-info border-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-user-injured me-2"></i>
                                <?php echo getFullName($record['patient_first'], $record['patient_last']); ?>
                            </h6>
                            <div class="btn-group btn-group-sm">
                                <a href="?edit=<?php echo $record['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <small class="text-muted">Date:</small><br>
                                    <strong><?php echo formatDate($record['created_at']); ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php if ($record['appointment_date']): ?>
                                        <small class="text-muted">Appointment:</small><br>
                                        <strong><?php echo formatDate($record['appointment_date']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($record['symptoms']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Symptoms:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($record['symptoms'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['diagnosis']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Diagnosis:</small>
                                    <p class="mb-1"><strong><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></strong></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['treatment']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Treatment:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['prescriptions']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Prescriptions:</small>
                                    <p class="mb-1 text-success"><?php echo nl2br(htmlspecialchars($record['prescriptions'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['notes']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Notes:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Patient Info -->
                            <div class="mt-3 pt-2 border-top">
                                <small class="text-muted">Patient Info:</small>
                                <div class="row">
                                    <?php if ($record['date_of_birth']): ?>
                                        <div class="col-6">
                                            <small>Age: <?php echo date_diff(date_create($record['date_of_birth']), date_create('today'))->y; ?> years</small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['gender']): ?>
                                        <div class="col-6">
                                            <small>Gender: <?php echo ucfirst($record['gender']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['blood_type']): ?>
                                        <div class="col-6">
                                            <small>Blood Type: <?php echo $record['blood_type']; ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($record['allergies']): ?>
                                        <div class="col-12">
                                            <small class="text-danger">Allergies: <?php echo htmlspecialchars($record['allergies']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Medical Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php echo $editRecord ? 'Edit Medical Record' : 'Add Medical Record'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editRecord ? 'update_record' : 'add_record'; ?>">
                    <?php if ($editRecord): ?>
                        <input type="hidden" name="record_id" value="<?php echo $editRecord['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$editRecord): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-control" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo getFullName($patient['first_name'], $patient['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="appointment_id" class="form-label">Related Appointment</label>
                            <select class="form-control" id="appointment_id" name="appointment_id">
                                <option value="">No specific appointment</option>
                                <!-- Will be populated by JavaScript based on patient selection -->
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Patient:</strong> <?php echo getFullName($editRecord['patient_first'], $editRecord['patient_last']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="symptoms" class="form-label">Symptoms</label>
                        <textarea class="form-control" id="symptoms" name="symptoms" rows="3" 
                                  placeholder="Describe the patient's symptoms"><?php echo $editRecord ? htmlspecialchars($editRecord['symptoms']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis *</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="2" 
                                  placeholder="Primary diagnosis" required><?php echo $editRecord ? htmlspecialchars($editRecord['diagnosis']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="treatment" class="form-label">Treatment Plan</label>
                        <textarea class="form-control" id="treatment" name="treatment" rows="3" 
                                  placeholder="Recommended treatment plan"><?php echo $editRecord ? htmlspecialchars($editRecord['treatment']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="prescriptions" class="form-label">Prescriptions</label>
                        <textarea class="form-control" id="prescriptions" name="prescriptions" rows="3" 
                                  placeholder="Prescribed medications and dosages"><?php echo $editRecord ? htmlspecialchars($editRecord['prescriptions']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="Any additional notes or observations"><?php echo $editRecord ? htmlspecialchars($editRecord['notes']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editRecord ? 'Update Record' : 'Add Record'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editRecord): ?>
<script>
// Auto-open modal for editing
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addRecordModal'));
    modal.show();
});
</script>
<?php endif; ?>

<script>
// Load appointments when patient is selected
document.getElementById('patient_id')?.addEventListener('change', function() {
    const patientId = this.value;
    const appointmentSelect = document.getElementById('appointment_id');
    
    // Clear existing options
    appointmentSelect.innerHTML = '<option value="">No specific appointment</option>';
    
    if (patientId) {
        // Fetch appointments for this patient (you would implement this via AJAX)
        // For now, we'll keep it simple
    }
});
</script>

<?php include '../includes/footer.php'; ?>
