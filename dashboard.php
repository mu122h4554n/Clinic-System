<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$GLOBALS['db'] = $db; // Make available for header.php

$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<style>
    .dashboard-card:hover {
        transform: translateY(-5px) !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }

    .dashboard-card {
        transition: all 0.3s ease !important;
        border: none !important;
    }

    .dashboard-card .card-body {
        transition: all 0.3s ease;
    }
</style>

<?php

// Get comprehensive dashboard statistics for all roles
$stats = [];

// System-wide statistics (visible to all)
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$stats['total_users'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor' AND is_active = 1");
$stats['total_doctors'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'receptionist' AND is_active = 1");
$stats['total_receptionists'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'patient' AND is_active = 1");
$stats['total_patients'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$stats['today_appointments'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'scheduled'");
$stats['pending_appointments'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'");
$stats['completed_appointments'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM medical_records");
$stats['total_medical_records'] = $stmt->fetch()['total'];

// Role-specific statistics
if (hasRole('doctor')) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.id 
        WHERE d.user_id = ? AND DATE(a.appointment_date) = CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_today_appointments'] = $stmt->fetch()['total'];

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM medical_records mr 
        JOIN doctors d ON mr.doctor_id = d.id 
        WHERE d.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_medical_records'] = $stmt->fetch()['total'];
}

if (hasRole('doctor')) {
    // Get doctor ID first
    $stmt = $db->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $doctor = $stmt->fetch();
    if ($doctor) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM preliminary_checkups WHERE doctor_id = ?");
        $stmt->execute([$doctor['id']]);
        $stats['my_checkups'] = $stmt->fetch()['total'];
    }
}

if (hasRole('patient')) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        WHERE p.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_appointments'] = $stmt->fetch()['total'];

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        WHERE p.user_id = ? AND a.status = 'scheduled'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_upcoming_appointments'] = $stmt->fetch()['total'];
}

$currentUser = getCurrentUser($db);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt me-2"></i>
        Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <span class="badge bg-primary fs-6">
                Welcome, <?php echo getFullName($currentUser['first_name'], $currentUser['last_name']); ?>
            </span>
        </div>
    </div>
</div>

<!-- System Overview Statistics -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3"><i class="fas fa-chart-line me-2"></i>System Overview</h4>
    </div>

    <!-- Staff Statistics -->
    <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'receptionist']) ? getAdminPath('users.php') : '#'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-user-md fa-2x mb-2"></i>
                    <h4><?php echo $stats['total_doctors']; ?></h4>
                    <p class="small mb-0">Doctors</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?php echo hasAnyRole(['admin']) ? getAdminPath('users.php?role=receptionist') : '#'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #ACC9C3 0%, #B3C9AD 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-user-tie fa-2x mb-2"></i>
                    <h4><?php echo $stats['total_receptionists']; ?></h4>
                    <p class="small mb-0">Receptionists</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'doctor', 'receptionist']) ? 'patients.php' : '#'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #B3C9AD 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-user-injured fa-2x mb-2"></i>
                    <h4><?php echo $stats['total_patients']; ?></h4>
                    <p class="small mb-0">Patients</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?php echo hasRole('admin') ? getAdminPath('users.php') : '#'; ?>" class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #C9ACB2 0%, #ACC0C9 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h4><?php echo $stats['total_users']; ?></h4>
                    <p class="small mb-0">Total Users</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'doctor', 'receptionist']) ? getRolePath('medical-records.php') : 'my-records.php'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #ACC9C3 0%, #C9ACB2 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-file-medical fa-2x mb-2"></i>
                    <h4><?php echo $stats['total_medical_records']; ?></h4>
                    <p class="small mb-0">Medical Records</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Appointments Statistics -->
<div class="row mb-4">
    <div class="col-12">
        <h4 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Appointments Overview</h4>
    </div>

    <div class="col-md-4 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'doctor', 'receptionist']) ? getRolePath('appointments.php') : 'my-appointments.php'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-calendar-day fa-3x mb-3"></i>
                    <h3><?php echo $stats['today_appointments']; ?></h3>
                    <p>Today's Appointments</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'doctor', 'receptionist']) ? getRolePath('appointments.php') . '?status=scheduled' : 'my-appointments.php?status=scheduled'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #C9ACB2 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h3><?php echo $stats['pending_appointments']; ?></h3>
                    <p>Pending Appointments</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4 mb-3">
        <a href="<?php echo hasAnyRole(['admin', 'doctor', 'receptionist']) ? getRolePath('appointments.php') . '?status=completed' : 'my-appointments.php?status=completed'; ?>"
            class="text-decoration-none">
            <div class="card dashboard-card"
                style="background: linear-gradient(135deg, #B3C9AD 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                <div class="card-body text-center text-white">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h3><?php echo $stats['completed_appointments']; ?></h3>
                    <p>Completed Appointments</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php if (hasRole('doctor') || hasRole('patient')): ?>
    <!-- Personal Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-user me-2"></i>My Statistics</h4>
        </div>

        <?php if (hasRole('doctor')): ?>
            <div class="col-md-6 mb-3">
                <a href="<?php echo getRolePath('appointments.php'); ?>" class="text-decoration-none">
                    <div class="card dashboard-card"
                        style="background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-calendar-day fa-3x mb-3"></i>
                            <h3><?php echo $stats['my_today_appointments']; ?></h3>
                            <p>My Today's Appointments</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="<?php echo getRolePath('medical-records.php'); ?>" class="text-decoration-none">
                    <div class="card dashboard-card"
                        style="background: linear-gradient(135deg, #ACC9C3 0%, #B3C9AD 100%); cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-file-medical fa-3x mb-3"></i>
                            <h3><?php echo $stats['my_medical_records']; ?></h3>
                            <p>My Medical Records</p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <?php if (hasRole('doctor')): ?>
            <div class="col-md-12 mb-3">
                <a href="<?php echo getRolePath('checkups.php'); ?>" class="text-decoration-none">
                    <div class="card dashboard-card"
                        style="background: linear-gradient(135deg, #C9ACB2 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-stethoscope fa-3x mb-3"></i>
                            <h3><?php echo $stats['my_checkups']; ?></h3>
                            <p>My Checkups Performed</p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <?php if (hasRole('patient')): ?>
            <div class="col-md-6 mb-3">
                <a href="my-appointments.php" class="text-decoration-none">
                    <div class="card dashboard-card"
                        style="background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                            <h3><?php echo $stats['my_appointments']; ?></h3>
                            <p>My Total Appointments</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 mb-3">
                <a href="my-appointments.php?status=scheduled" class="text-decoration-none">
                    <div class="card dashboard-card"
                        style="background: linear-gradient(135deg, #C9ACB2 0%, #ACC9C3 100%); cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-calendar-check fa-3x mb-3"></i>
                            <h3><?php echo $stats['my_upcoming_appointments']; ?></h3>
                            <p>My Upcoming Appointments</p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (hasRole('admin')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getAdminPath('users.php'); ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users me-2"></i>Manage Users
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getAdminPath('reports.php'); ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                        </div>
                    <?php elseif (hasRole('doctor')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('appointments.php'); ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-alt me-2"></i>View Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('patients.php'); ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-user-injured me-2"></i>View Patients
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('medical-records.php'); ?>"
                                class="btn btn-outline-success w-100">
                                <i class="fas fa-file-medical me-2"></i>Medical Records
                            </a>
                        </div>
                    <?php elseif (hasRole('doctor')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('appointments.php'); ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-alt me-2"></i>View Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('patients.php'); ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-user-injured me-2"></i>View Patients
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('checkups.php'); ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-stethoscope me-2"></i>Checkups
                            </a>
                        </div>
                    <?php elseif (hasRole('receptionist')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('appointments.php'); ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-alt me-2"></i>Manage Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="<?php echo getRolePath('patients.php'); ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-user-injured me-2"></i>Manage Patients
                            </a>
                        </div>
                    <?php elseif (hasRole('patient')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="book-appointment.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="my-appointments.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-calendar-check me-2"></i>My Appointments
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="my-records.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-medical-alt me-2"></i>Medical History
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3 mb-2">
                        <a href="profile.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>