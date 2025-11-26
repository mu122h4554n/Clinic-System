<?php
// Helper function to get correct dashboard path
function getDashboardPath() {
    $scriptPath = $_SERVER['PHP_SELF'];
    $scriptDir = dirname($scriptPath);
    
    // Remove leading slash and count directory separators
    $scriptDir = ltrim($scriptDir, '/');
    
    // Count how many directory levels deep we are
    $depth = substr_count($scriptDir, '/');
    
    if ($depth > 0) {
        // We're in a subdirectory, go up to root
        return str_repeat('../', $depth) . 'dashboard.php';
    }
    
    // We're in root, use direct path
    return 'dashboard.php';
}

// Helper function to get correct admin paths
function getAdminPath($file) {
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    if ($currentDir === 'admin') {
        return $file; // We're in admin, use relative path
    } else {
        return 'admin/' . $file; // We're in root, use admin/ prefix
    }
}

// Helper function to get correct logo path
function getLogoPath() {
    $scriptPath = $_SERVER['PHP_SELF'];
    $scriptDir = dirname($scriptPath);
    
    // Remove leading slash and count directory separators
    $scriptDir = ltrim($scriptDir, '/');
    
    // Count how many directory levels deep we are
    $depth = substr_count($scriptDir, '/');
    
    if ($depth > 0) {
        // We're in a subdirectory, go up to root
        return str_repeat('../', $depth) . 'img/LOGO_CLINIC-removebg-preview.png';
    }
    
    // We're in root, use direct path
    return 'img/LOGO_CLINIC-removebg-preview.png';
}

// Helper function to get root-relative path (if not already defined)
if (!function_exists('getRootPath')) {
    function getRootPath($file) {
        // Use PHP_SELF for consistency with other path functions
        $scriptPath = $_SERVER['PHP_SELF'];
        $scriptDir = dirname($scriptPath);
        
        // Remove leading slash and count directory separators
        $scriptDir = ltrim($scriptDir, '/');
        
        // Count how many directory levels deep we are
        // Example: "Clinic-System-2/admin" = 1 level, "Clinic-System-2/doctor" = 1 level
        $depth = substr_count($scriptDir, '/');
        
        if ($depth > 0) {
            // We're in a subdirectory, go up to root
            return str_repeat('../', $depth) . $file;
        }
        
        // We're in root, use direct path
        return $file;
    }
}

// Helper function to get correct role-specific paths
function getRolePath($file) {
    if (!isset($_SESSION['role'])) {
        return $file;
    }
    
    $role = $_SESSION['role'];
    $scriptPath = $_SERVER['PHP_SELF'];
    $scriptDir = dirname($scriptPath);
    
    // Remove leading slash and get directory name
    $scriptDir = ltrim($scriptDir, '/');
    $currentDir = $scriptDir ? basename($scriptDir) : '';
    
    // Map files to their role-specific locations
    $roleFiles = [
        'appointments.php' => ['doctor' => 'doctor/', 'receptionist' => 'receptionist/'],
        'patients.php' => ['receptionist' => 'receptionist/', 'doctor' => 'receptionist/'],
        'medical-records.php' => ['doctor' => 'doctor/'],
        'checkups.php' => ['doctor' => 'doctor/']
    ];
    
    // If file doesn't have a role-specific location, return as-is
    if (!isset($roleFiles[$file][$role])) {
        return $file;
    }
    
    $targetDir = $roleFiles[$file][$role];
    $targetDirName = rtrim($targetDir, '/');
    
    // If we're already in the target directory, use relative path
    if ($currentDir === $targetDirName) {
        return $file;
    }
    
    // Calculate depth (how many directories deep we are)
    $depth = substr_count($scriptDir, '/');
    
    // If we're in root (depth 0), use direct path
    if ($depth === 0) {
        return $targetDir . $file;
    }
    
    // If we're in a subdirectory, go up to root first
    $upPath = str_repeat('../', $depth);
    return $upPath . $targetDir . $file;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Clinic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ACC0C9;
            --secondary-color: #ACC9C3;
            --success-color: #B3C9AD;
            --warning-color: #C9ACB2;
            --danger-color: #C9ACB2;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
        }
        
        .navbar-brand .logo-img {
            height: 60px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            transition: all 0.3s ease;
        }
        
        .navbar-brand:hover .logo-img {
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        }
        
        @media (max-width: 768px) {
            .navbar-brand .logo-img {
                height: 45px;
            }
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(172, 192, 201, 0.4);
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: var(--dark-text);
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .dashboard-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: scale(1.05);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(172, 201, 195, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .badge {
            border-radius: 20px;
            padding: 8px 15px;
        }
        
        /* Notification Styles */
        .notification-dropdown .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item {
            white-space: normal;
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
        }

        .notification-item:hover {
            border-left-color: var(--primary-color);
        }
        
        .notification-item h6 {
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .notification-item p {
            font-size: 0.8rem;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo getRootPath('index.php'); ?>">
                <img src="<?php echo getLogoPath(); ?>" alt="CareAid Clinic Logo" class="logo-img">
                <span class="d-none d-md-inline">CareAid Clinic</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Notifications Dropdown -->
                    <li class="nav-item dropdown me-3">
                        <?php 
                        $notificationCount = isset($db) ? getNotificationCount($db, $_SESSION['user_id']) : 0;
                        $unreadNotifications = isset($db) ? getUnreadNotifications($db, $_SESSION['user_id']) : [];
                        ?>
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($notificationCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span>Notifications</span>
                                <?php if ($notificationCount > 0): ?>
                                    <a href="<?php echo getRootPath('notifications.php'); ?>?action=mark_all_read" class="btn btn-sm btn-outline-primary">Mark All Read</a>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <?php if (empty($unreadNotifications)): ?>
                                <li class="dropdown-item-text text-center text-muted py-3">
                                    <i class="fas fa-bell-slash fa-2x mb-2"></i><br>
                                    No new notifications
                                </li>
                            <?php else: ?>
                                <?php foreach (array_slice($unreadNotifications, 0, 5) as $notification): ?>
                                    <li>
                                        <a class="dropdown-item notification-item" href="<?php echo getRootPath('notifications.php'); ?>?read=<?php echo $notification['id']; ?>">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-<?php 
                                                        $iconColors = [
                                                            'appointment' => 'calendar-alt text-primary',
                                                            'reminder' => 'clock text-warning',
                                                            'system' => 'cog text-info',
                                                            'success' => 'check-circle text-success',
                                                            'warning' => 'exclamation-triangle text-warning'
                                                        ];
                                                        echo $iconColors[$notification['type']] ?? 'info-circle text-info';
                                                    ?>"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-2">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                    <p class="mb-1 small text-muted"><?php echo htmlspecialchars(substr($notification['message'], 0, 80)) . (strlen($notification['message']) > 80 ? '...' : ''); ?></p>
                                                    <small class="text-muted"><?php echo formatDateTime($notification['created_at']); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endforeach; ?>
                                
                                <?php if (count($unreadNotifications) > 5): ?>
                                    <li class="dropdown-item text-center">
                                        <a href="<?php echo getRootPath('notifications.php'); ?>" class="btn btn-sm btn-primary">View All Notifications</a>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <!-- User Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php 
                            if (isset($db)) {
                                $currentUser = getCurrentUser($db);
                                echo getFullName($currentUser['first_name'], $currentUser['last_name']);
                            } else {
                                echo $_SESSION['full_name'] ?? 'User';
                            }
                            ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo getRootPath('profile.php'); ?>"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo getRootPath('notifications.php'); ?>"><i class="fas fa-bell me-2"></i>Notifications</a></li>
                            <li><a class="dropdown-item" href="<?php echo getRootPath('activity-log.php'); ?>"><i class="fas fa-history me-2"></i>Activity Log</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo getRootPath('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php if (isLoggedIn()): ?>
            <nav class="col-md-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getDashboardPath(); ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasRole('admin')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getAdminPath('users.php'); ?>">
                                <i class="fas fa-users me-2"></i>
                                Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getAdminPath('doctor_approvals.php'); ?>">
                                <i class="fas fa-user-md me-2"></i>
                                Doctor Approvals
                                <?php
                                // Ensure approval columns exist, then show badge for pending approvals
                                if (isset($GLOBALS['db'])) {
                                    try {
                                        ensureDoctorApprovalColumns($GLOBALS['db']);
                                        $stmt = $GLOBALS['db']->query("SELECT COUNT(*) as count FROM doctors WHERE approval_status = 'pending'");
                                        $pending_count = $stmt->fetch()['count'];
                                        if ($pending_count > 0):
                                        ?>
                                        <span class="badge bg-warning text-dark ms-1"><?php echo $pending_count; ?></span>
                                        <?php endif;
                                    } catch (Exception $e) {
                                        // Silently fail if there's an error
                                    }
                                }
                                ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getAdminPath('reports.php'); ?>">
                                <i class="fas fa-chart-bar me-2"></i>
                                Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('doctor')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('appointments.php'); ?>">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('patients.php'); ?>">
                                <i class="fas fa-user-injured me-2"></i>
                                Patients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('medical-records.php'); ?>">
                                <i class="fas fa-file-medical me-2"></i>
                                Medical Records
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('checkups.php'); ?>">
                                <i class="fas fa-stethoscope me-2"></i>
                                Preliminary Checkups
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRootPath('medicine-requests.php'); ?>">
                                <i class="fas fa-prescription-bottle me-2"></i>
                                Medicine Requests
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('receptionist')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('appointments.php'); ?>">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo getRolePath('patients.php'); ?>">
                                <i class="fas fa-user-injured me-2"></i>
                                Patients
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasRole('patient')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="book-appointment.php">
                                <i class="fas fa-calendar-plus me-2"></i>
                                Book Appointment
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-appointments.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                My Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-records.php">
                                <i class="fas fa-file-medical-alt me-2"></i>
                                Medical History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="medicines.php">
                                <i class="fas fa-pills me-2"></i>
                                Medicines
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-10 ms-sm-auto px-md-4">
            <?php else: ?>
            <main class="col-12">
            <?php endif; ?>
                <div class="pt-3 pb-2 mb-3">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>
