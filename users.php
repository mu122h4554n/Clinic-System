<?php
// Start session first before any output
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

requireRole('admin');

$database = new Database();
$db = $database->getConnection();
$GLOBALS['db'] = $db; // Make available for header.php

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = sanitizeInput($_POST['role']);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $phone = sanitizeInput($_POST['phone']);
                $address = sanitizeInput($_POST['address']);
                
                try {
                    $stmt = $db->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $password, $role, $first_name, $last_name, $phone, $address]);
                    
                    $user_id = $db->lastInsertId();
                    
                    // If user is a patient, create patient record
                    if ($role == 'patient') {
                        $stmt = $db->prepare("INSERT INTO patients (user_id) VALUES (?)");
                        $stmt->execute([$user_id]);
                    }
                    
                    // If user is a doctor, create doctor record
                    if ($role == 'doctor') {
                        $specialization = sanitizeInput($_POST['specialization'] ?? '');
                        $license_number = sanitizeInput($_POST['license_number'] ?? '');
                        $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
                        
                        $stmt = $db->prepare("INSERT INTO doctors (user_id, specialization, license_number, consultation_fee) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$user_id, $specialization, $license_number, $consultation_fee]);
                    }
                    
                    $_SESSION['success'] = 'User added successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error adding user: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                $user_id = intval($_POST['user_id']);
                $current_status = intval($_POST['current_status']);
                $new_status = $current_status ? 0 : 1;
                
                try {
                    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    $_SESSION['success'] = 'User status updated successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error updating user status: ' . $e->getMessage();
                }
                break;
                
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                
                try {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                    $stmt->execute([$user_id, $_SESSION['user_id']]); // Prevent self-deletion
                    $_SESSION['success'] = 'User deleted successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error deleting user: ' . $e->getMessage();
                }
                break;
        }
        
        header('Location: users.php');
        exit();
    }
}

// Get all users
$stmt = $db->query("
    SELECT u.*, 
           d.specialization, d.license_number, d.consultation_fee,
           p.date_of_birth, p.gender
    FROM users u
    LEFT JOIN doctors d ON u.id = d.user_id
    LEFT JOIN patients p ON u.id = p.user_id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Manage Users
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-2"></i>Add New User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <strong><?php echo getFullName($user['first_name'], $user['last_name']); ?></strong>
                            <?php if ($user['role'] == 'doctor' && $user['specialization']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($user['specialization']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                $roleColors = [
                                    'admin' => 'danger',
                                    'doctor' => 'primary',
                                    'receptionist' => 'warning',
                                    'patient' => 'info'
                                ];
                                echo $roleColors[$user['role']] ?? 'secondary';
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                    <button type="submit" class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                            title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this user?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="badge bg-info">Current User</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
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
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-control" id="role" name="role" required onchange="toggleRoleFields()">
                                <option value="">Select Role</option>
                                <option value="doctor">Doctor</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    
                    <!-- Doctor specific fields -->
                    <div id="doctorFields" style="display: none;">
                        <hr>
                        <h6>Doctor Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee</label>
                                <input type="number" step="0.01" class="form-control" id="consultation_fee" name="consultation_fee">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleRoleFields() {
    const role = document.getElementById('role').value;
    const doctorFields = document.getElementById('doctorFields');
    
    if (role === 'doctor') {
        doctorFields.style.display = 'block';
    } else {
        doctorFields.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
