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
    
    // Doctor-specific fields
    $specialization = sanitizeInput($_POST['specialization']);
    $license_number = sanitizeInput($_POST['license_number']);
    $years_experience = intval($_POST['years_experience']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $qualifications = sanitizeInput($_POST['qualifications']);
    $available_days = $_POST['available_days'] ?? [];
    
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
    
    if (empty($specialization)) {
        $errors[] = 'Specialization is required.';
    }
    
    if (empty($license_number)) {
        $errors[] = 'Medical license number is required.';
    }
    
    if ($years_experience < 0) {
        $errors[] = 'Years of experience must be a positive number.';
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Username or email already exists. Please choose different ones.';
            }
            
            // Check if license number already exists
            $stmt = $db->prepare("SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.license_number = ?");
            $stmt->execute([$license_number]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'This medical license number is already registered.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error checking existing accounts. Please try again.';
        }
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Create user account with is_active = false (pending approval)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, role, first_name, last_name, phone, address, is_active) 
                VALUES (?, ?, ?, 'doctor', ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone, $address]);
            $user_id = $db->lastInsertId();
            
            // Create doctor record with pending approval status
            $available_days_json = json_encode($available_days);
            $stmt = $db->prepare("
                INSERT INTO doctors (user_id, approval_status, specialization, license_number, years_experience, consultation_fee, available_days) 
                VALUES (?, 'pending', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $specialization, $license_number, $years_experience, $consultation_fee, $available_days_json]);
            
            $db->commit();
            
            // Notify all admins about new doctor registration
            $stmt = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
            $admins = $stmt->fetchAll();
            
            foreach ($admins as $admin) {
                createNotification($db, $admin['id'], 'New Doctor Registration', 
                    "Dr. $first_name $last_name has registered and is awaiting approval. Specialization: $specialization", 'warning');
            }
            
            $success = 'Registration submitted successfully! Your account is pending approval from the hospital administrator. You will be notified once your account is approved.';
            
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
    <title>Doctor Registration - Clinic Management System</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .section-header {
            background: #f8f9fa;
            padding: 15px;
            margin: 20px -30px;
            border-left: 4px solid #667eea;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container signup-container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="signup-card">
                    <div class="signup-header">
                        <img src="img/LOGO_CLINIC-removebg-preview.png" alt="CareAid Clinic Logo" class="logo-img">
                        <h3><i class="fas fa-user-md me-2"></i>Doctor Registration</h3>
                        <p class="mb-0">Join our medical team - Registration requires administrator approval</p>
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
                                    <small class="text-muted">At least 3 characters</small>
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
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Professional Information</h5>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="specialization" class="form-label">Specialization *</label>
                                    <select class="form-control" id="specialization" name="specialization" required>
                                        <option value="">Select Specialization</option>
                                        <option value="General Medicine" <?php echo (($_POST['specialization'] ?? '') == 'General Medicine') ? 'selected' : ''; ?>>General Medicine</option>
                                        <option value="General Practice" <?php echo (($_POST['specialization'] ?? '') == 'General Practice') ? 'selected' : ''; ?>>General Practice</option>
                                        <option value="Cardiology" <?php echo (($_POST['specialization'] ?? '') == 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                        <option value="Dermatology" <?php echo (($_POST['specialization'] ?? '') == 'Dermatology') ? 'selected' : ''; ?>>Dermatology</option>
                                        <option value="Pediatrics" <?php echo (($_POST['specialization'] ?? '') == 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                        <option value="Orthopedics" <?php echo (($_POST['specialization'] ?? '') == 'Orthopedics') ? 'selected' : ''; ?>>Orthopedics</option>
                                        <option value="Neurology" <?php echo (($_POST['specialization'] ?? '') == 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                                        <option value="Psychiatry" <?php echo (($_POST['specialization'] ?? '') == 'Psychiatry') ? 'selected' : ''; ?>>Psychiatry</option>
                                        <option value="Ophthalmology" <?php echo (($_POST['specialization'] ?? '') == 'Ophthalmology') ? 'selected' : ''; ?>>Ophthalmology</option>
                                        <option value="ENT" <?php echo (($_POST['specialization'] ?? '') == 'ENT') ? 'selected' : ''; ?>>ENT (Ear, Nose, Throat)</option>
                                        <option value="Other" <?php echo (($_POST['specialization'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="license_number" class="form-label">Medical License Number *</label>
                                    <input type="text" class="form-control" id="license_number" name="license_number"
                                           value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>" required>
                                    <small class="text-muted">Your official medical license number</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="years_experience" class="form-label">Years of Experience *</label>
                                    <input type="number" class="form-control" id="years_experience" name="years_experience"
                                           value="<?php echo htmlspecialchars($_POST['years_experience'] ?? '0'); ?>" min="0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="consultation_fee" class="form-label">Consultation Fee (USD) *</label>
                                    <input type="number" class="form-control" id="consultation_fee" name="consultation_fee"
                                           value="<?php echo htmlspecialchars($_POST['consultation_fee'] ?? '0'); ?>" min="0" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="qualifications" class="form-label">Qualifications & Education</label>
                                <textarea class="form-control" id="qualifications" name="qualifications" rows="3"
                                          placeholder="List your degrees, certifications, and educational background"><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                            </div>

                            <!-- Availability -->
                            <div class="section-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Availability</h5>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Available Days</label>
                                <div class="row">
                                    <?php
                                    $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                                             'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
                                    $selected_days = $_POST['available_days'] ?? [];
                                    foreach ($days as $value => $label):
                                    ?>
                                    <div class="col-md-4 col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="available_days[]"
                                                   value="<?php echo $value; ?>" id="day_<?php echo $value; ?>"
                                                   <?php echo in_array($value, $selected_days) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="day_<?php echo $value; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong> Your registration will be reviewed by the hospital administrator.
                                You will receive a notification once your account is approved and you can start practicing.
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I certify that all information provided is accurate and I agree to the
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> *
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-signup">
                                <i class="fas fa-user-md me-2"></i>Submit Registration
                            </button>
                        </form>

                        <?php endif; ?>

                        <div class="login-link">
                            <p class="text-muted">Already have an account?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </a>
                            <hr class="my-3">
                            <p class="text-muted">Looking to register as a patient?</p>
                            <a href="signup.php" class="btn btn-outline-success">
                                <i class="fas fa-user-plus me-2"></i>Patient Registration
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
                    <h5 class="modal-title">Terms and Conditions for Doctors</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Professional Credentials</h6>
                    <p>You certify that all professional credentials, licenses, and qualifications provided are valid and current.</p>

                    <h6>2. Approval Process</h6>
                    <p>Your registration is subject to verification and approval by the hospital administration. This process may take 1-3 business days.</p>

                    <h6>3. Professional Conduct</h6>
                    <p>You agree to maintain the highest standards of medical ethics and professional conduct while using this system.</p>

                    <h6>4. Patient Privacy</h6>
                    <p>You agree to protect patient confidentiality and comply with all healthcare privacy regulations (HIPAA, etc.).</p>

                    <h6>5. Accurate Information</h6>
                    <p>You agree to keep your profile information, availability, and consultation fees up to date.</p>

                    <h6>6. System Usage</h6>
                    <p>You agree to use this system responsibly for legitimate medical practice purposes only.</p>
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
