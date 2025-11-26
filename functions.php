<?php
// Common functions for the clinic system

// Start session if not already started
function startSession() {
    if (session_status() == PHP_SESSION_NONE) {
        // Check if headers have already been sent
        if (!headers_sent()) {
            session_start();
        }
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Check if user has specific role
function hasRole($role) {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    startSession();
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

// Helper function to get root-relative path
function getRootPath($file) {
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptPath);
    
    // Remove leading slash and count directory separators
    $scriptDir = ltrim($scriptDir, '/');
    
    // Count how many directory levels deep we are
    // /Clinic-System-2/patients -> "Clinic-System-2/patients" -> 1 slash -> 1 level up
    $depth = substr_count($scriptDir, '/');
    
    if ($depth > 0) {
        // We're in a subdirectory, go up to root
        return str_repeat('../', $depth) . $file;
    }
    
    // We're in root, use direct path
    return $file;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getRootPath('login.php'));
        exit();
    }
}

// Redirect if user doesn't have required role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . getRootPath('unauthorized.php'));
        exit();
    }
}

// Redirect if user doesn't have any of the required roles
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header('Location: ' . getRootPath('unauthorized.php'));
        exit();
    }
}

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format date for display
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Format datetime for display
function formatDateTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

// Format time for display
function formatTime($time) {
    return date('g:i A', strtotime($time));
}

// Generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Get user's full name
function getFullName($firstName, $lastName) {
    return trim($firstName . ' ' . $lastName);
}

// Get current user info
function getCurrentUser($db) {
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Enhanced activity logging
function logActivity($db, $action, $details = '') {
    if (!isLoggedIn()) {
        return;
    }
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $action, $details, $ip_address]);
    } catch (Exception $e) {
        // Silently fail if logging fails
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Notification functions
function createNotification($db, $user_id, $title, $message, $type = 'system') {
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type]);
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function getUnreadNotifications($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getNotificationCount($db, $user_id) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function markNotificationAsRead($db, $notification_id, $user_id) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function markAllNotificationsAsRead($db, $user_id) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Notification helpers for common actions
function notifyAppointmentBooked($db, $patient_user_id, $doctor_user_id, $appointment_date, $appointment_time) {
    // Notify patient
    createNotification($db, $patient_user_id, 'Appointment Booked', 
        "Your appointment has been scheduled for " . formatDate($appointment_date) . " at " . formatTime($appointment_time), 'appointment');
    
    // Notify doctor
    createNotification($db, $doctor_user_id, 'New Appointment', 
        "A new appointment has been scheduled for " . formatDate($appointment_date) . " at " . formatTime($appointment_time), 'appointment');
}

function notifyAppointmentStatusChange($db, $patient_user_id, $status, $appointment_date, $appointment_time) {
    $statusMessages = [
        'confirmed' => 'Your appointment has been confirmed',
        'cancelled' => 'Your appointment has been cancelled',
        'completed' => 'Your appointment has been completed'
    ];
    
    $message = ($statusMessages[$status] ?? 'Your appointment status has been updated') . 
               " for " . formatDate($appointment_date) . " at " . formatTime($appointment_time);
    
    createNotification($db, $patient_user_id, 'Appointment Update', $message, 'appointment');
}

// Helper to fetch doctor user id
function getDoctorUserId($db, $doctor_id) {
    try {
        $stmt = $db->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch();
        return $doctor['user_id'] ?? null;
    } catch (Exception $e) {
        return null;
    }
}

// Notify patient (and doctor if needed) when medicine order is submitted
function notifyMedicineOrderSubmitted($db, $patient_user_id, $medicine_name, $requiresApproval = false, $doctor_id = null, $patient_name = null) {
    $title = $requiresApproval ? 'Medicine Approval Requested' : 'Medicine Order Confirmed';
    $message = $requiresApproval
        ? "Your request for {$medicine_name} has been sent to your doctor for approval."
        : "Your order for {$medicine_name} has been confirmed. Please follow the usage instructions.";

    createNotification($db, $patient_user_id, $title, $message, $requiresApproval ? 'warning' : 'success');

    if ($requiresApproval && $doctor_id) {
        $doctor_user_id = getDoctorUserId($db, $doctor_id);
        if ($doctor_user_id) {
            $patientLabel = $patient_name ?: 'A patient';
            $doctorMessage = "{$patientLabel} requested {$medicine_name} and needs your approval.";
            createNotification($db, $doctor_user_id, 'Medicine Approval Needed', $doctorMessage, 'warning');
        }
    }
}

// Notify patient when doctor updates medicine order status
function notifyMedicineOrderStatus($db, $patient_user_id, $medicine_name, $status) {
    $statusMessages = [
        'approved' => "Your request for {$medicine_name} has been approved.",
        'rejected' => "Your request for {$medicine_name} has been rejected. Please consult your doctor for alternatives.",
        'fulfilled' => "{$medicine_name} has been marked as fulfilled and will be ready for pickup shortly."
    ];

    $message = $statusMessages[$status] ?? "Your order for {$medicine_name} has been updated.";
    $typeMap = [
        'approved' => 'success',
        'rejected' => 'warning',
        'fulfilled' => 'success'
    ];
    $notificationType = $typeMap[$status] ?? 'system';

    createNotification($db, $patient_user_id, 'Medicine Order Update', $message, $notificationType);
}

// Helper function to ensure doctor approval columns exist
function ensureDoctorApprovalColumns($db) {
    try {
        // Check if approval_status column exists
        $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approval_status'");
        if ($stmt->rowCount() == 0) {
            // Column doesn't exist, add it
            $db->exec("ALTER TABLE doctors ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER user_id");
            
            // Add other related columns if they don't exist
            $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approval_notes'");
            if ($stmt->rowCount() == 0) {
                $db->exec("ALTER TABLE doctors ADD COLUMN approval_notes TEXT AFTER approval_status");
            }
            
            $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approved_by'");
            if ($stmt->rowCount() == 0) {
                $db->exec("ALTER TABLE doctors ADD COLUMN approved_by INT AFTER approval_notes");
            }
            
            $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approved_at'");
            if ($stmt->rowCount() == 0) {
                $db->exec("ALTER TABLE doctors ADD COLUMN approved_at DATETIME AFTER approved_by");
            }
            
            // Update existing doctors to approved (they were added by admin)
            $db->exec("UPDATE doctors SET approval_status = 'approved', approved_at = NOW() WHERE approval_status IS NULL OR approval_status = ''");
            
            // Create index if it doesn't exist
            try {
                $db->exec("CREATE INDEX idx_approval_status ON doctors(approval_status)");
            } catch (Exception $e) {
                // Index might already exist, ignore
            }
        }
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring doctor approval columns: " . $e->getMessage());
        return false;
    }
}
?>
