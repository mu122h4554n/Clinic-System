<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
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
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First name and last name are required.';
    }
    
    if (!empty($date_of_birth)) {
        $birth_date = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $birth_date->diff($today)->y;
        
        if ($age > 120) {
            $errors[] = 'Please enter a valid date of birth.';
        }
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Username or email already exists. Please choose different ones.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error checking existing accounts. Please try again.';
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, role, first_name, last_name, phone, address) 
                VALUES (?, ?, ?, 'patient', ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone, $address]);
            $user_id = $db->lastInsertId();
            
            // Create patient record
            $stmt = $db->prepare("
                INSERT INTO patients (user_id, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, blood_type, allergies, medical_history) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone, $blood_type, $allergies, $medical_history]);
            
            $db->commit();
            
            // Log activity (temporarily set session for logging)
            $temp_user_id = $_SESSION['user_id'] ?? null;
            $_SESSION['user_id'] = $user_id;
            logActivity($db, 'register', "New patient account created: $username");
            if ($temp_user_id) {
                $_SESSION['user_id'] = $temp_user_id;
            } else {
                unset($_SESSION['user_id']);
            }
            
            // Create welcome notification
            createNotification($db, $user_id, 'Welcome to Our Clinic!', 
                'Your account has been created successfully. You can now book appointments and access your medical records.', 'system');
            
            $success = 'Account created successfully! You can now log in with your credentials.';
            
            // Clear form data on success
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error creating account: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - Clinic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .signup-container {
            padding: 40px 0;
        }
        
        .signup-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 20px 0;
        }
        
        .signup-header {
            background: linear-gradient(135deg, #ACC0C9, #ACC9C3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .signup-header .logo-img {
            height: 100px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .signup-header .logo-img:hover {
            transform: scale(1.1) rotate(-3deg);
        }
        
        .signup-body {
            padding: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #ACC0C9;
            box-shadow: 0 0 0 0.2rem rgba(172, 192, 201, 0.25);
        }
        
        .btn-signup {
            background: linear-gradient(135deg, #ACC0C9, #ACC9C3);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            width: 100%;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(172, 192, 201, 0.4);
            color: white;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 15px;
            margin: 20px -30px;
            border-left: 4px solid #ACC0C9;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container signup-container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="signup-card">
                    <div class="signup-header">
                        <img src="img/LOGO_CLINIC-removebg-preview.png" alt="CareAid Clinic Logo" class="logo-img">
                        <h3>Patient Registration</h3>
                        <p class="mb-0">Create your account to book appointments</p>
                    </div>
                    
                    <div class="signup-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <!-- Account Information -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Account Information</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                    <small class="text-muted">At least 3 characters, letters and numbers only</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">At least 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Personal Information</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo $_POST['date_of_birth'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-control" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo (($_POST['gender'] ?? '') == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo (($_POST['gender'] ?? '') == 'female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo (($_POST['gender'] ?? '') == 'other') ? 'selected' : ''; ?>>Other</option>
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
                                        <option value="<?php echo $type; ?>" <?php echo (($_POST['blood_type'] ?? '') == $type) ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Emergency Contact -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Medical Information -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Medical Information</h5>
                            </div>
                            
                            <div class="mb-3">
                                <label for="allergies" class="form-label">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                          placeholder="List any known allergies (food, medication, environmental)"><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="medical_history" class="form-label">Medical History</label>
                                <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                          placeholder="Brief medical history (chronic conditions, previous surgeries, etc.)"><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a> *
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-signup">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <?php endif; ?>
                        
                        <div class="login-link">
                            <p class="text-muted">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </a>
                            <hr class="my-3">
                            <p class="text-muted">Are you a medical professional?</p>
                            <a href="doctor_signup.php" class="btn btn-outline-success">
                                <i class="fas fa-user-md me-2"></i>Doctor Registration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Account Registration</h6>
                    <p>By creating an account, you agree to provide accurate and complete information.</p>
                    
                    <h6>2. Medical Information</h6>
                    <p>The medical information you provide will be used for healthcare purposes and will be kept confidential.</p>
                    
                    <h6>3. Appointment Booking</h6>
                    <p>You are responsible for attending scheduled appointments or canceling them at least 24 hours in advance.</p>
                    
                    <h6>4. Privacy</h6>
                    <p>Your personal and medical information will be protected according to healthcare privacy regulations.</p>
                    
                    <h6>5. System Usage</h6>
                    <p>You agree to use this system responsibly and not to share your login credentials with others.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Information Collection</h6>
                    <p>We collect personal and medical information necessary for providing healthcare services.</p>
                    
                    <h6>Information Use</h6>
                    <p>Your information is used for:</p>
                    <ul>
                        <li>Scheduling and managing appointments</li>
                        <li>Maintaining medical records</li>
                        <li>Communicating about your healthcare</li>
                        <li>Emergency contact purposes</li>
                    </ul>
                    
                    <h6>Information Protection</h6>
                    <p>We implement security measures to protect your personal and medical information.</p>
                    
                    <h6>Information Sharing</h6>
                    <p>Your information is only shared with authorized healthcare providers involved in your care.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9]+$/;
            
            if (username.length > 0 && !regex.test(username)) {
                this.setCustomValidity('Username can only contain letters and numbers');
            } else if (username.length > 0 && username.length < 3) {
                this.setCustomValidity('Username must be at least 3 characters long');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
