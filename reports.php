<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$GLOBALS['db'] = $db; // Make available for header.php

// Get statistics
$stats = [];

// User statistics
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
$userStats = $stmt->fetchAll();
foreach ($userStats as $stat) {
    $stats['users'][$stat['role']] = $stat['count'];
}

// Appointment statistics
$stmt = $db->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
$appointmentStats = $stmt->fetchAll();
foreach ($appointmentStats as $stat) {
    $stats['appointments'][$stat['status']] = $stat['count'];
}

// Monthly appointment trends
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(appointment_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM appointments 
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
    ORDER BY month
");
$monthlyStats = $stmt->fetchAll();

// Recent activities
$stmt = $db->query("
    SELECT 
        u.first_name, u.last_name, u.role,
        a.appointment_date, a.appointment_time, a.status,
        p.first_name as patient_first, p.last_name as patient_last,
        d.first_name as doctor_first, d.last_name as doctor_last
    FROM appointments a
    JOIN patients pt ON a.patient_id = pt.id
    JOIN users p ON pt.user_id = p.id
    JOIN doctors dt ON a.doctor_id = dt.id
    JOIN users d ON dt.user_id = d.id
    ORDER BY a.created_at DESC
    LIMIT 10
");
$recentAppointments = $stmt->fetchAll();

$pageTitle = 'Reports';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        System Reports
    </h1>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card" style="background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%);">
            <div class="card-body text-center">
                <i class="fas fa-users fa-3x mb-3"></i>
                <h3><?php echo array_sum($stats['users'] ?? []); ?></h3>
                <p>Total Active Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card" style="background: linear-gradient(135deg, #C9ACB2 0%, #ACC9C3 100%);">
            <div class="card-body text-center">
                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                <h3><?php echo array_sum($stats['appointments'] ?? []); ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card" style="background: linear-gradient(135deg, #ACC9C3 0%, #B3C9AD 100%);">
            <div class="card-body text-center">
                <i class="fas fa-user-md fa-3x mb-3"></i>
                <h3><?php echo $stats['users']['doctor'] ?? 0; ?></h3>
                <p>Active Doctors</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card dashboard-card" style="background: linear-gradient(135deg, #B3C9AD 0%, #ACC9C3 100%);">
            <div class="card-body text-center">
                <i class="fas fa-user-injured fa-3x mb-3"></i>
                <h3><?php echo $stats['users']['patient'] ?? 0; ?></h3>
                <p>Registered Patients</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- User Distribution -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-pie-chart me-2"></i>User Distribution</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalUsers = array_sum($stats['users'] ?? []);
                            foreach ($stats['users'] ?? [] as $role => $count): 
                                $percentage = $totalUsers > 0 ? round(($count / $totalUsers) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php 
                                        $roleColors = [
                                            'admin' => 'danger',
                                            'doctor' => 'primary',
                                            'receptionist' => 'warning',
                                            'patient' => 'info'
                                        ];
                                        echo $roleColors[$role] ?? 'secondary';
                                    ?>">
                                        <?php echo ucfirst($role); ?>
                                    </span>
                                </td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Appointment Status -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Appointment Status</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalAppointments = array_sum($stats['appointments'] ?? []);
                            foreach ($stats['appointments'] ?? [] as $status => $count): 
                                $percentage = $totalAppointments > 0 ? round(($count / $totalAppointments) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php 
                                        $statusColors = [
                                            'scheduled' => 'primary',
                                            'confirmed' => 'info',
                                            'in_progress' => 'warning',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        echo $statusColors[$status] ?? 'secondary';
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Trends -->
<?php if (!empty($monthlyStats)): ?>
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-area me-2"></i>Monthly Appointment Trends (Last 6 Months)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Appointments</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyStats as $index => $stat): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($stat['month'] . '-01')); ?></td>
                                <td><?php echo $stat['count']; ?></td>
                                <td>
                                    <?php if ($index > 0): ?>
                                        <?php 
                                        $prevCount = $monthlyStats[$index - 1]['count'];
                                        $change = $stat['count'] - $prevCount;
                                        $changePercent = $prevCount > 0 ? round(($change / $prevCount) * 100, 1) : 0;
                                        ?>
                                        <?php if ($change > 0): ?>
                                            <span class="text-success">
                                                <i class="fas fa-arrow-up"></i> +<?php echo $changePercent; ?>%
                                            </span>
                                        <?php elseif ($change < 0): ?>
                                            <span class="text-danger">
                                                <i class="fas fa-arrow-down"></i> <?php echo $changePercent; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-minus"></i> 0%
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Appointments -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2"></i>Recent Appointments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAppointments as $appointment): ?>
                            <tr>
                                <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                <td><?php echo formatTime($appointment['appointment_time']); ?></td>
                                <td><?php echo getFullName($appointment['patient_first'], $appointment['patient_last']); ?></td>
                                <td><?php echo getFullName($appointment['doctor_first'], $appointment['doctor_last']); ?></td>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
