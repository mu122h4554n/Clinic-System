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

// Handle checkup actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_checkup':
                $patient_id = intval($_POST['patient_id']);
                $appointment_id = !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
                $blood_pressure = sanitizeInput($_POST['blood_pressure']);
                $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
                $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
                $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
                $pulse_rate = !empty($_POST['pulse_rate']) ? intval($_POST['pulse_rate']) : null;
                $notes = sanitizeInput($_POST['notes']);
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO preliminary_checkups (patient_id, doctor_id, appointment_id, blood_pressure, temperature, weight, height, pulse_rate, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$patient_id, $doctor_id, $appointment_id, $blood_pressure, $temperature, $weight, $height, $pulse_rate, $notes]);
                    $_SESSION['success'] = 'Preliminary checkup recorded successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error recording checkup: ' . $e->getMessage();
                }
                break;
                
            case 'update_checkup':
                $checkup_id = intval($_POST['checkup_id']);
                $blood_pressure = sanitizeInput($_POST['blood_pressure']);
                $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
                $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
                $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
                $pulse_rate = !empty($_POST['pulse_rate']) ? intval($_POST['pulse_rate']) : null;
                $notes = sanitizeInput($_POST['notes']);
                
                try {
                    $stmt = $db->prepare("
                        UPDATE preliminary_checkups 
                        SET blood_pressure = ?, temperature = ?, weight = ?, height = ?, pulse_rate = ?, notes = ?
                        WHERE id = ? AND doctor_id = ?
                    ");
                    $stmt->execute([$blood_pressure, $temperature, $weight, $height, $pulse_rate, $notes, $checkup_id, $doctor_id]);
                    $_SESSION['success'] = 'Checkup updated successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error updating checkup: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: checkups.php');
        exit();
    }
}

// Get filter parameters
$patient_filter = isset($_GET['patient']) ? intval($_GET['patient']) : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query based on filters
$whereClause = "WHERE pc.doctor_id = ?";
$params = [$doctor_id];

if ($patient_filter > 0) {
    $whereClause .= " AND pc.patient_id = ?";
    $params[] = $patient_filter;
}

if (!empty($date_filter)) {
    $whereClause .= " AND DATE(pc.created_at) = ?";
    $params[] = $date_filter;
}

// Get preliminary checkups
$stmt = $db->prepare("
    SELECT pc.*, 
           p.first_name as patient_first, p.last_name as patient_last,
           pt.date_of_birth, pt.gender, pt.blood_type, pt.allergies,
           a.appointment_date, a.appointment_time
    FROM preliminary_checkups pc
    JOIN patients pt ON pc.patient_id = pt.id
    JOIN users p ON pt.user_id = p.id
    LEFT JOIN appointments a ON pc.appointment_id = a.id
    $whereClause
    ORDER BY pc.created_at DESC
");
$stmt->execute($params);
$checkups = $stmt->fetchAll();

// Get patients for this doctor (from today's appointments)
$stmt = $db->prepare("
    SELECT DISTINCT pt.id, p.first_name, p.last_name
    FROM patients pt
    JOIN users p ON pt.user_id = p.id
    JOIN appointments a ON pt.id = a.patient_id
    WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
    ORDER BY p.first_name, p.last_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll();

// Get checkup to edit if specified
$editCheckup = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $db->prepare("
        SELECT pc.*, 
               p.first_name as patient_first, p.last_name as patient_last
        FROM preliminary_checkups pc
        JOIN patients pt ON pc.patient_id = pt.id
        JOIN users p ON pt.user_id = p.id
        WHERE pc.id = ? AND pc.doctor_id = ?
    ");
    $stmt->execute([intval($_GET['edit']), $doctor_id]);
    $editCheckup = $stmt->fetch();
}

$pageTitle = 'Preliminary Checkups';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-stethoscope me-2"></i>
        Preliminary Checkups
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCheckupModal">
            <i class="fas fa-plus me-2"></i>Record Checkup
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
                <label for="date" class="form-label">Filter by Date</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $date_filter; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                    <a href="checkups.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Checkups -->
<div class="card">
    <div class="card-body">
        <?php if (empty($checkups)): ?>
            <div class="text-center py-4">
                <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No checkups found</h5>
                <p class="text-muted">No preliminary checkups match your current filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($checkups as $checkup): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-start border-success border-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-user-injured me-2"></i>
                                <?php echo getFullName($checkup['patient_first'], $checkup['patient_last']); ?>
                            </h6>
                            <div class="btn-group btn-group-sm">
                                <a href="?edit=<?php echo $checkup['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-6">
                                    <small class="text-muted">Date & Time:</small><br>
                                    <strong><?php echo formatDateTime($checkup['created_at']); ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php if ($checkup['appointment_date']): ?>
                                        <small class="text-muted">Appointment:</small><br>
                                        <strong><?php echo formatDate($checkup['appointment_date']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Vital Signs -->
                            <div class="row mb-3">
                                <?php if ($checkup['blood_pressure']): ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Blood Pressure:</small>
                                        <div class="badge bg-info"><?php echo htmlspecialchars($checkup['blood_pressure']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($checkup['temperature']): ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Temperature:</small>
                                        <div class="badge bg-<?php echo $checkup['temperature'] > 37.5 ? 'warning' : 'success'; ?>">
                                            <?php echo $checkup['temperature']; ?>°C
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($checkup['pulse_rate']): ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Pulse Rate:</small>
                                        <div class="badge bg-primary"><?php echo $checkup['pulse_rate']; ?> bpm</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($checkup['weight']): ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Weight:</small>
                                        <div class="badge bg-secondary"><?php echo $checkup['weight']; ?> kg</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($checkup['height']): ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">Height:</small>
                                        <div class="badge bg-secondary"><?php echo $checkup['height']; ?> cm</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($checkup['weight'] && $checkup['height']): ?>
                                    <?php 
                                    $heightInMeters = $checkup['height'] / 100;
                                    $bmi = round($checkup['weight'] / ($heightInMeters * $heightInMeters), 1);
                                    $bmiClass = $bmi < 18.5 ? 'warning' : ($bmi > 25 ? 'danger' : 'success');
                                    ?>
                                    <div class="col-6 mb-2">
                                        <small class="text-muted">BMI:</small>
                                        <div class="badge bg-<?php echo $bmiClass; ?>"><?php echo $bmi; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($checkup['notes']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Notes:</small>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($checkup['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Patient Info -->
                            <div class="mt-3 pt-2 border-top">
                                <small class="text-muted">Patient Info:</small>
                                <div class="row">
                                    <?php if ($checkup['date_of_birth']): ?>
                                        <div class="col-6">
                                            <small>Age: <?php echo date_diff(date_create($checkup['date_of_birth']), date_create('today'))->y; ?> years</small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($checkup['gender']): ?>
                                        <div class="col-6">
                                            <small>Gender: <?php echo ucfirst($checkup['gender']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($checkup['blood_type']): ?>
                                        <div class="col-6">
                                            <small>Blood Type: <?php echo $checkup['blood_type']; ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($checkup['allergies']): ?>
                                        <div class="col-12">
                                            <small class="text-danger">Allergies: <?php echo htmlspecialchars($checkup['allergies']); ?></small>
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

<!-- Add/Edit Checkup Modal -->
<div class="modal fade" id="addCheckupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php echo $editCheckup ? 'Edit Preliminary Checkup' : 'Record Preliminary Checkup'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editCheckup ? 'update_checkup' : 'add_checkup'; ?>">
                    <?php if ($editCheckup): ?>
                        <input type="hidden" name="checkup_id" value="<?php echo $editCheckup['id']; ?>">
                    <?php endif; ?>
                    
                    <?php if (!$editCheckup): ?>
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
                            <strong>Patient:</strong> <?php echo getFullName($editCheckup['patient_first'], $editCheckup['patient_last']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Vital Signs</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="blood_pressure" class="form-label">Blood Pressure</label>
                            <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" 
                                   placeholder="e.g., 120/80" 
                                   value="<?php echo $editCheckup ? htmlspecialchars($editCheckup['blood_pressure']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="temperature" class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" min="30" max="45" class="form-control" 
                                   id="temperature" name="temperature" placeholder="e.g., 36.5"
                                   value="<?php echo $editCheckup ? $editCheckup['temperature'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="pulse_rate" class="form-label">Pulse Rate (bpm)</label>
                            <input type="number" min="40" max="200" class="form-control" 
                                   id="pulse_rate" name="pulse_rate" placeholder="e.g., 72"
                                   value="<?php echo $editCheckup ? $editCheckup['pulse_rate'] : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" min="1" max="300" class="form-control" 
                                   id="weight" name="weight" placeholder="e.g., 70.5"
                                   value="<?php echo $editCheckup ? $editCheckup['weight'] : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="height" class="form-label">Height (cm)</label>
                            <input type="number" step="0.1" min="50" max="250" class="form-control" 
                                   id="height" name="height" placeholder="e.g., 175"
                                   value="<?php echo $editCheckup ? $editCheckup['height'] : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Any additional observations or notes"><?php echo $editCheckup ? htmlspecialchars($editCheckup['notes']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editCheckup ? 'Update Checkup' : 'Record Checkup'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editCheckup): ?>
<script>
// Auto-open modal for editing
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('addCheckupModal'));
    modal.show();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
