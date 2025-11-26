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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validate required fields
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First name and last name are required.';
    }
    
    // If changing password, validate it
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password.';
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update basic user info
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, password = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $address, $hashed_password, $_SESSION['user_id']]);
            } else {
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $address, $_SESSION['user_id']]);
            }
            
            // Update patient-specific info if user is a patient
            if (hasRole('patient')) {
                $date_of_birth = $_POST['date_of_birth'] ?? '';
                $gender = sanitizeInput($_POST['gender'] ?? '');
                $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name'] ?? '');
                $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
                $blood_type = sanitizeInput($_POST['blood_type'] ?? '');
                $allergies = sanitizeInput($_POST['allergies'] ?? '');
                $medical_history = sanitizeInput($_POST['medical_history'] ?? '');
                
                $stmt = $db->prepare("
                    UPDATE patients 
                    SET date_of_birth = ?, gender = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
                        blood_type = ?, allergies = ?, medical_history = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, 
                               $blood_type, $allergies, $medical_history, $_SESSION['user_id']]);
            }
            
            // Update doctor-specific info if user is a doctor
            if (hasRole('doctor')) {
                $specialization = sanitizeInput($_POST['specialization'] ?? '');
                $license_number = sanitizeInput($_POST['license_number'] ?? '');
                $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
                
                $stmt = $db->prepare("
                    UPDATE doctors 
                    SET specialization = ?, license_number = ?, consultation_fee = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$specialization, $license_number, $consultation_fee, $_SESSION['user_id']]);
            }
            
            $db->commit();
            $_SESSION['success'] = 'Profile updated successfully!';
            
            // Update session name
            $_SESSION['full_name'] = getFullName($first_name, $last_name);
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
    header('Location: profile.php');
    exit();
}

// Get current user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get role-specific info
$roleInfo = null;
if (hasRole('patient')) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $roleInfo = $stmt->fetch();
} elseif (hasRole('doctor')) {
    $stmt = $db->prepare("SELECT * FROM doctors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $roleInfo = $stmt->fetch();
}

$pageTitle = 'My Profile';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>
        My Profile
    </h1>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Basic Information</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role</label>
                            <input type="text" class="form-control" id="role" 
                                   value="<?php echo ucfirst($user['role']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <?php if (hasRole('patient') && $roleInfo): ?>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Patient Information</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo $roleInfo['date_of_birth']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-control" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $roleInfo['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $roleInfo['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $roleInfo['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="blood_type" class="form-label">Blood Type</label>
                            <select class="form-control" id="blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <?php 
                                $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($bloodTypes as $type): 
                                ?>
                                <option value="<?php echo $type; ?>" <?php echo $roleInfo['blood_type'] == $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                   value="<?php echo htmlspecialchars($roleInfo['emergency_contact_name']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                   value="<?php echo htmlspecialchars($roleInfo['emergency_contact_phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                  placeholder="List any known allergies"><?php echo htmlspecialchars($roleInfo['allergies']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="medical_history" class="form-label">Medical History</label>
                        <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                  placeholder="Brief medical history"><?php echo htmlspecialchars($roleInfo['medical_history']); ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('doctor') && $roleInfo): ?>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Doctor Information</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($roleInfo['specialization']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="license_number" class="form-label">License Number</label>
                            <input type="text" class="form-control" id="license_number" name="license_number" 
                                   value="<?php echo htmlspecialchars($roleInfo['license_number']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="consultation_fee" class="form-label">Consultation Fee</label>
                            <input type="number" step="0.01" class="form-control" id="consultation_fee" name="consultation_fee" 
                                   value="<?php echo $roleInfo['consultation_fee']; ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h6 class="border-bottom pb-2">Change Password (Optional)</h6>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Account Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Account Created:</strong><br><?php echo formatDateTime($user['created_at']); ?></p>
                <p><strong>Last Updated:</strong><br><?php echo formatDateTime($user['updated_at']); ?></p>
                <p><strong>Account Status:</strong><br>
                   <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                       <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                   </span>
                </p>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-shield-alt me-2"></i>Security Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>Use a strong password</li>
                    <li><i class="fas fa-check text-success me-2"></i>Don't share your login credentials</li>
                    <li><i class="fas fa-check text-success me-2"></i>Log out when finished</li>
                    <li><i class="fas fa-check text-success me-2"></i>Keep your contact info updated</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
