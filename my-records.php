<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('patient');

$database = new Database();
$db = $database->getConnection();

// Get patient ID
$stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = 'Patient record not found. Please contact the administrator.';
    header('Location: ../dashboard.php');
    exit();
}

$patient_id = $patient['id'];

// Get patient's medical records
$stmt = $db->prepare("
    SELECT mr.*, 
           d.first_name as doctor_first, d.last_name as doctor_last,
           dt.specialization,
           a.appointment_date, a.appointment_time
    FROM medical_records mr
    JOIN doctors dt ON mr.doctor_id = dt.id
    JOIN users d ON dt.user_id = d.id
    LEFT JOIN appointments a ON mr.appointment_id = a.id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");
$stmt->execute([$patient_id]);
$records = $stmt->fetchAll();

// Get patient's preliminary checkups
$stmt = $db->prepare("
    SELECT pc.*, 
           d.first_name as doctor_first_checkup, d.last_name as doctor_last_checkup,
           a.appointment_date, a.appointment_time
    FROM preliminary_checkups pc
    JOIN doctors doc ON pc.doctor_id = doc.id
    JOIN users d ON doc.user_id = d.id
    LEFT JOIN appointments a ON pc.appointment_id = a.id
    WHERE pc.patient_id = ?
    ORDER BY pc.created_at DESC
");
$stmt->execute([$patient_id]);
$checkups = $stmt->fetchAll();

// Get patient information
$stmt = $db->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email, u.phone, u.address
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patientInfo = $stmt->fetch();

$pageTitle = 'My Medical History';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-medical-alt me-2"></i>
        My Medical History
    </h1>
</div>

<!-- Patient Information Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo getFullName($patientInfo['first_name'], $patientInfo['last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($patientInfo['email']); ?></p>
                        <?php if ($patientInfo['phone']): ?>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patientInfo['phone']); ?></p>
                        <?php endif; ?>
                        <?php if ($patientInfo['date_of_birth']): ?>
                            <p><strong>Date of Birth:</strong> <?php echo formatDate($patientInfo['date_of_birth']); ?> 
                               (Age: <?php echo date_diff(date_create($patientInfo['date_of_birth']), date_create('today'))->y; ?> years)</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($patientInfo['gender']): ?>
                            <p><strong>Gender:</strong> <?php echo ucfirst($patientInfo['gender']); ?></p>
                        <?php endif; ?>
                        <?php if ($patientInfo['blood_type']): ?>
                            <p><strong>Blood Type:</strong> <span class="badge bg-danger"><?php echo $patientInfo['blood_type']; ?></span></p>
                        <?php endif; ?>
                        <?php if ($patientInfo['emergency_contact_name']): ?>
                            <p><strong>Emergency Contact:</strong> <?php echo htmlspecialchars($patientInfo['emergency_contact_name']); ?>
                               <?php if ($patientInfo['emergency_contact_phone']): ?>
                                   (<?php echo htmlspecialchars($patientInfo['emergency_contact_phone']); ?>)
                               <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($patientInfo['allergies']): ?>
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Allergies</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($patientInfo['allergies'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($patientInfo['medical_history']): ?>
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-history me-2"></i>Medical History</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($patientInfo['medical_history'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Records and Checkups -->
<ul class="nav nav-tabs mb-3" id="historyTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="records-tab" data-bs-toggle="tab" data-bs-target="#records" type="button">
            <i class="fas fa-file-medical me-2"></i>Medical Records (<?php echo count($records); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="checkups-tab" data-bs-toggle="tab" data-bs-target="#checkups" type="button">
            <i class="fas fa-stethoscope me-2"></i>Checkups (<?php echo count($checkups); ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="historyTabsContent">
    <!-- Medical Records Tab -->
    <div class="tab-pane fade show active" id="records" role="tabpanel">
        <?php if (empty($records)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Medical Records</h5>
                    <p class="text-muted">You don't have any medical records yet. Medical records will appear here after your doctor visits.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($records as $record): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-start border-primary border-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-user-md me-2"></i>
                                    Dr. <?php echo getFullName($record['doctor_first'], $record['doctor_last']); ?>
                                </h6>
                                <small class="text-muted"><?php echo formatDate($record['created_at']); ?></small>
                            </div>
                            <?php if ($record['specialization']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($record['specialization']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($record['appointment_date']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Visit Date:</small>
                                    <strong><?php echo formatDate($record['appointment_date']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
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
                                    <div class="alert alert-success py-2">
                                        <?php echo nl2br(htmlspecialchars($record['prescriptions'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($record['notes']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Doctor's Notes:</small>
                                    <p class="mb-1 text-muted"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Checkups Tab -->
    <div class="tab-pane fade" id="checkups" role="tabpanel">
        <?php if (empty($checkups)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Checkup Records</h5>
                    <p class="text-muted">You don't have any preliminary checkup records yet. These will appear here after doctor checkups.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($checkups as $checkup): ?>
                <div class="col-md-6 mb-4">
                    <div class="card border-start border-success border-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-user-md me-2"></i>
                                    <?php echo getFullName($checkup['doctor_first_checkup'], $checkup['doctor_last_checkup']); ?> (Doctor)
                                </h6>
                                <small class="text-muted"><?php echo formatDateTime($checkup['created_at']); ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($checkup['appointment_date']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Visit Date:</small>
                                    <strong><?php echo formatDate($checkup['appointment_date']); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Vital Signs -->
                            <h6 class="mb-2">Vital Signs</h6>
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
                                    <small class="text-muted">Doctor Notes:</small>
                                    <p class="mb-1 text-muted"><?php echo nl2br(htmlspecialchars($checkup['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Statistics -->
<?php if (!empty($records) || !empty($checkups)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Health Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <h4 class="text-primary"><?php echo count($records); ?></h4>
                        <small class="text-muted">Medical Records</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <h4 class="text-success"><?php echo count($checkups); ?></h4>
                        <small class="text-muted">Checkups</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <?php 
                        $totalVisits = count($records) + count($checkups);
                        ?>
                        <h4 class="text-info"><?php echo $totalVisits; ?></h4>
                        <small class="text-muted">Total Visits</small>
                    </div>
                    <div class="col-md-3 text-center">
                        <?php 
                        $lastVisit = '';
                        $allDates = array_merge(
                            array_column($records, 'created_at'),
                            array_column($checkups, 'created_at')
                        );
                        if (!empty($allDates)) {
                            rsort($allDates);
                            $lastVisit = formatDate($allDates[0]);
                        }
                        ?>
                        <h6 class="text-warning"><?php echo $lastVisit ?: 'N/A'; ?></h6>
                        <small class="text-muted">Last Visit</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
