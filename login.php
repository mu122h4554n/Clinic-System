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

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $db->prepare("SELECT id, username, password, role, first_name, last_name, is_active FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    // Check if it's a doctor with pending approval
                    if ($user['role'] === 'doctor') {
                        try {
                            // Check if approval_status column exists
                            $checkStmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approval_status'");
                            if ($checkStmt->rowCount() > 0) {
                                $stmt = $db->prepare("SELECT approval_status FROM doctors WHERE user_id = ?");
                                $stmt->execute([$user['id']]);
                                $doctor = $stmt->fetch();

                                if ($doctor && isset($doctor['approval_status'])) {
                                    if ($doctor['approval_status'] === 'pending') {
                                        $error = 'Your doctor registration is pending approval from the hospital administrator. You will be notified once your account is approved.';
                                    } elseif ($doctor['approval_status'] === 'rejected') {
                                        $error = 'Your doctor registration has been rejected. Please contact the administrator for more information.';
                                    } else {
                                        $error = 'Your account has been deactivated. Please contact the administrator.';
                                    }
                                } else {
                                    $error = 'Your account has been deactivated. Please contact the administrator.';
                                }
                            } else {
                                $error = 'Your account has been deactivated. Please contact the administrator.';
                            }
                        } catch (Exception $e) {
                            $error = 'Your account has been deactivated. Please contact the administrator.';
                        }
                    } else {
                        $error = 'Your account has been deactivated. Please contact the administrator.';
                    }
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = getFullName($user['first_name'], $user['last_name']);

                    // Log login activity
                    logActivity($db, 'login', 'User logged in successfully');

                    // Create welcome notification for first-time login today
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM activity_log WHERE user_id = ? AND action = 'login' AND DATE(created_at) = CURDATE()");
                    $stmt->execute([$user['id']]);
                    $loginCount = $stmt->fetch()['count'];

                    if ($loginCount <= 1) {
                        createNotification($db, $user['id'], 'Welcome Back!',
                            'You have successfully logged into the clinic system.', 'success');
                    }

                    // Smart redirect based on user role
                    if ($user['role'] === 'patient') {
                        header('Location: index.php');
                    } else {
                        header('Location: dashboard.php');
                    }
                    exit();
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Login';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Clinic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #ACC0C9 0%, #ACC9C3 100%);
            --secondary-gradient: linear-gradient(135deg, #ACC9C3 0%, #B3C9AD 100%);
            --success-gradient: linear-gradient(135deg, #B3C9AD 0%, #ACC9C3 100%);
            --dark-gradient: linear-gradient(135deg, #ACC0C9 0%, #C9ACB2 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --shadow-light: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --shadow-heavy: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #000;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* WebGL Shader Canvas Background */
        #shaderCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: auto;
            background: #000;
        }

        /* Animated gradient overlay for fallback */
        .gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, 
                #ACC0C9, #ACC9C3, #B3C9AD, #ACC0C9, 
                #ACC9C3, #B3C9AD, #ACC0C9);
            background-size: 400% 400%;
            animation: gradientShift 8s ease infinite;
            z-index: 1;
            opacity: 0.8;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Particle overlay effect */
        .particle-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 107, 53, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(247, 147, 30, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 210, 63, 0.2) 0%, transparent 50%);
            animation: particleFloat 12s ease-in-out infinite;
            z-index: 2;
            pointer-events: none;
        }

        @keyframes particleFloat {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg);
                opacity: 0.6;
            }
            33% { 
                transform: translateY(-20px) rotate(120deg);
                opacity: 0.8;
            }
            66% { 
                transform: translateY(10px) rotate(240deg);
                opacity: 0.4;
            }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            margin: 20px;
        }

        .login-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 165, 0, 0.3);
            box-shadow: 0 8px 32px 0 rgba(255, 107, 53, 0.3);
            overflow: hidden;
            position: relative;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.2), rgba(247, 147, 30, 0.2));
            backdrop-filter: blur(10px);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid rgba(255, 165, 0, 0.2);
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 210, 63, 0.2) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.6; }
        }

        .login-header .icon-container {
            position: relative;
            z-index: 2;
            margin-bottom: 20px;
        }

        .login-header .logo-img {
            height: 120px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
            animation: logoFloat 3s ease-in-out infinite;
            margin-bottom: 15px;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(-2deg); }
        }
        
        .login-header .logo-img:hover {
            animation-play-state: paused;
            transform: scale(1.1) rotate(2deg);
        }

        .login-header h3 {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .login-header p {
            font-weight: 300;
            opacity: 0.9;
            font-size: 1rem;
        }

        .login-body {
            padding: 40px 30px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(10px);
        }

        .form-floating {
            margin-bottom: 25px;
        }

        .form-control {
            background: rgba(0, 0, 0, 0.4);
            border: 2px solid rgba(255, 165, 0, 0.3);
            border-radius: 16px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            height: 60px;
            color: white !important;
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.6);
            border-color: #ACC0C9;
            box-shadow: 0 0 0 4px rgba(172, 192, 201, 0.2);
            transform: translateY(-2px);
            color: white !important;
        }

        .form-control:active,
        .form-control:hover {
            color: white !important;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6) !important;
        }

        /* Ensure text stays white in all states */
        .form-control,
        .form-control:focus,
        .form-control:active,
        .form-control:hover,
        .form-control:valid,
        .form-control:invalid {
            color: white !important;
            -webkit-text-fill-color: white !important;
        }

        /* Fix autofill styles */
        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover,
        .form-control:-webkit-autofill:focus,
        .form-control:-webkit-autofill:active {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0 30px rgba(0, 0, 0, 0.4) inset !important;
            box-shadow: 0 0 0 30px rgba(0, 0, 0, 0.4) inset !important;
        }

        .form-floating > label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem 0.75rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
        }

        /* When input is focused or has content, float the label */
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
        }

        /* Ensure proper positioning */
        .form-floating {
            position: relative;
        }

        .form-floating > .form-control {
            padding: 1rem 0.75rem;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-control:not(:placeholder-shown) {
            padding-top: 1.625rem;
            padding-bottom: 0.625rem;
        }

        /* Override any conflicting Bootstrap styles */
        .form-floating > label {
            z-index: 2;
            background: transparent;
        }

        /* Make sure the label doesn't disappear */
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label,
        .form-floating > .form-control:valid ~ label {
            opacity: 0.65 !important;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem) !important;
            color: rgba(255, 193, 7, 0.9) !important;
        }

        .input-group-text {
            background: transparent;
            border: none;
            color: #ACC0C9;
            font-size: 1.1rem;
        }

        /* Password toggle button styles */
        .password-toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: rgba(255, 165, 0, 0.7);
            font-size: 1.1rem;
            cursor: pointer;
            z-index: 3;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .password-toggle-btn:hover {
            color: #ACC0C9;
            background: rgba(172, 192, 201, 0.1);
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* Adjust password input padding to make room for the button */
        .form-floating.position-relative .form-control {
            padding-right: 55px;
        }

        .btn-login {
            background: linear-gradient(135deg, #ACC0C9, #ACC9C3);
            border: none;
            border-radius: 16px;
            padding: 16px 30px;
            width: 100%;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(255, 107, 53, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .demo-credentials {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.1), rgba(247, 147, 30, 0.1));
            border: 1px solid rgba(255, 165, 0, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }

        .demo-credentials h6 {
            color: #ACC0C9;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 165, 0, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .credential-item:last-child {
            border-bottom: none;
        }

        .credential-role {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .credential-login {
            font-family: 'Courier New', monospace;
            background: rgba(255, 107, 53, 0.2);
            color: rgba(255, 255, 255, 0.9);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .signup-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 165, 0, 0.2);
        }

        .btn-signup {
            background: linear-gradient(135deg, #C9ACB2, #ACC9C3);
            border: none;
            border-radius: 16px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.4);
            color: white;
            text-decoration: none;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }

        .alert-danger {
            background: rgba(201, 172, 178, 0.1);
            color: #C9ACB2;
            border: 1px solid rgba(201, 172, 178, 0.2);
        }

        /* Loading animation */
        .btn-login.loading {
            pointer-events: none;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- WebGL Shader Canvas Background -->
    <canvas id="shaderCanvas"></canvas>
    
    <!-- Animated Gradient Overlay (fallback) -->
    <div class="gradient-overlay"></div>
    
    <!-- Particle Overlay Effect -->
    <div class="particle-overlay"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-container">
                    <img src="img/LOGO_CLINIC-removebg-preview.png" alt="CareAid Clinic Logo" class="logo-img">
                </div>
                <h3>CareAid Clinic</h3>
                <p>Please sign in to continue</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="needs-validation" novalidate id="loginForm">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder=" "
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                               required>
                        <label for="username">
                            <i class="fas fa-user me-2"></i>Username or Email
                        </label>
                    </div>
                    
                    <div class="form-floating position-relative">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder=" " required>
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <button type="button" class="password-toggle-btn" id="togglePassword">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        <span class="btn-text">Sign In</span>
                    </button>
                </form>
                
                <div class="demo-credentials">
                    <h6><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                    <div class="credential-item">
                        <span class="credential-role">Admin</span>
                        <span class="credential-login">admin / password</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Doctor</span>
                        <span class="credential-login">dr.smith / password</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Doctor</span>
                        <span class="credential-login">dr.jane / password</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Receptionist</span>
                        <span class="credential-login">receptionist / password</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Patient</span>
                        <span class="credential-login">patient1 / password</span>
                    </div>
                </div>
                
                <div class="signup-section">
                    <p class="mb-3" style="color: #6c757d;">Don't have an account?</p>
                    <a href="signup.php" class="btn-signup mb-2">
                        <i class="fas fa-user-plus"></i>
                        Register as New Patient
                    </a>
                    <a href="doctor_signup.php" class="btn-signup" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-user-md"></i>
                        Register as Doctor
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // WebGL Shader Background Implementation
        class WebGLRenderer {
            constructor(canvas, scale) {
                this.canvas = canvas;
                this.scale = scale;
                this.gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
                if (!this.gl) {
                    console.warn('WebGL not supported, falling back to gradient');
                    return;
                }
                this.gl.viewport(0, 0, canvas.width * scale, canvas.height * scale);
                this.mouseMove = [0, 0];
                this.mouseCoords = [0, 0];
                this.time = 0;
                
                this.vertexSrc = `
                    attribute vec4 position;
                    void main() { gl_Position = position; }
                `;
                
                this.fragmentSrc = `
                    precision highp float;
                    uniform vec2 resolution;
                    uniform float time;
                    uniform vec2 mouse;
                    
                    float noise(vec2 p) {
                        p = fract(p * vec2(12.9898, 78.233));
                        p += dot(p, p + 34.56);
                        return fract(p.x * p.y);
                    }
                    
                    float fbm(vec2 p) {
                        float t = 0.0, a = 1.0;
                        mat2 m = mat2(1.0, -0.5, 0.2, 1.2);
                        for (int i = 0; i < 5; i++) {
                            t += a * noise(p);
                            p *= 2.0 * m;
                            a *= 0.5;
                        }
                        return t;
                    }
                    
                    float clouds(vec2 p) {
                        float d = 1.0, t = 0.0;
                        for (float i = 0.0; i < 3.0; i++) {
                            float a = d * fbm(i * 10.0 + p.x * 0.2 + 0.2 * (1.0 + i) * p.y + d + i * i + p);
                            t = mix(t, d, a);
                            d = a;
                            p *= 2.0 / (i + 1.0);
                        }
                        return t;
                    }
                    
                    void main() {
                        vec2 uv = (gl_FragCoord.xy - 0.5 * resolution) / min(resolution.x, resolution.y);
                        vec2 st = uv * vec2(2.0, 1.0);
                        vec3 col = vec3(0.0);
                        
                        float bg = clouds(vec2(st.x + time * 0.5, -st.y));
                        uv *= 1.0 - 0.3 * (sin(time * 0.2) * 0.5 + 0.5);
                        
                        for (float i = 1.0; i < 12.0; i++) {
                            uv += 0.1 * cos(i * vec2(0.1 + 0.01 * i, 0.8) + i * i + time * 0.5 + 0.1 * uv.x);
                            vec2 p = uv;
                            float d = length(p);
                            
                            // Orange/amber color scheme
                            vec3 fireColors = cos(sin(i) * vec3(1.0, 1.5, 2.0)) + 1.0;
                            fireColors *= vec3(1.0, 0.6, 0.2); // Orange tint
                            col += 0.00125 / d * fireColors;
                            
                            float b = noise(i + p + bg * 1.731);
                            col += 0.002 * b / length(max(p, vec2(b * p.x * 0.02, p.y)));
                            col = mix(col, vec3(bg * 0.4, bg * 0.25, bg * 0.1), d);
                        }
                        
                        // Add mouse interaction
                        vec2 mouseEffect = mouse / resolution;
                        col += 0.1 * exp(-length(uv - mouseEffect * 2.0 + 1.0));
                        
                        gl_FragColor = vec4(col, 1.0);
                    }
                `;
                
                this.vertices = [-1, 1, -1, -1, 1, 1, 1, -1];
                this.setup();
            }
            
            setup() {
                if (!this.gl) return;
                
                const gl = this.gl;
                
                // Create shaders
                const vs = gl.createShader(gl.VERTEX_SHADER);
                const fs = gl.createShader(gl.FRAGMENT_SHADER);
                
                gl.shaderSource(vs, this.vertexSrc);
                gl.shaderSource(fs, this.fragmentSrc);
                
                gl.compileShader(vs);
                gl.compileShader(fs);
                
                // Create program
                this.program = gl.createProgram();
                gl.attachShader(this.program, vs);
                gl.attachShader(this.program, fs);
                gl.linkProgram(this.program);
                
                // Create buffer
                this.buffer = gl.createBuffer();
                gl.bindBuffer(gl.ARRAY_BUFFER, this.buffer);
                gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(this.vertices), gl.STATIC_DRAW);
                
                // Get attribute and uniform locations
                this.positionLocation = gl.getAttribLocation(this.program, 'position');
                this.resolutionLocation = gl.getUniformLocation(this.program, 'resolution');
                this.timeLocation = gl.getUniformLocation(this.program, 'time');
                this.mouseLocation = gl.getUniformLocation(this.program, 'mouse');
                
                gl.enableVertexAttribArray(this.positionLocation);
                gl.vertexAttribPointer(this.positionLocation, 2, gl.FLOAT, false, 0, 0);
            }
            
            updateMouse(coords) {
                this.mouseCoords = coords;
            }
            
            render(now) {
                if (!this.gl || !this.program) return;
                
                const gl = this.gl;
                this.time = now * 0.001;
                
                gl.clearColor(0, 0, 0, 1);
                gl.clear(gl.COLOR_BUFFER_BIT);
                gl.useProgram(this.program);
                gl.bindBuffer(gl.ARRAY_BUFFER, this.buffer);
                
                gl.uniform2f(this.resolutionLocation, this.canvas.width, this.canvas.height);
                gl.uniform1f(this.timeLocation, this.time);
                gl.uniform2f(this.mouseLocation, this.mouseCoords[0] || 0, this.mouseCoords[1] || 0);
                
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            }
        }

        // Initialize shader background
        function initShaderBackground() {
            const canvas = document.getElementById('shaderCanvas');
            if (!canvas) return null;
            
            const dpr = Math.max(1, 0.5 * window.devicePixelRatio);
            
            function resize() {
                canvas.width = window.innerWidth * dpr;
                canvas.height = window.innerHeight * dpr;
                canvas.style.width = window.innerWidth + 'px';
                canvas.style.height = window.innerHeight + 'px';
            }
            
            resize();
            window.addEventListener('resize', resize);
            
            const renderer = new WebGLRenderer(canvas, dpr);
            
            // Mouse tracking
            let mouseCoords = [0, 0];
            canvas.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                mouseCoords = [
                    (e.clientX - rect.left) * dpr,
                    (canvas.height - (e.clientY - rect.top) * dpr)
                ];
                renderer.updateMouse(mouseCoords);
            });
            
            // Animation loop
            function animate(now) {
                renderer.render(now);
                requestAnimationFrame(animate);
            }
            
            animate(0);
            return renderer;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize shader background
            const shaderRenderer = initShaderBackground();
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const credentialItems = document.querySelectorAll('.credential-item');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');

            // Enhanced form validation
            function validateForm() {
                let isValid = true;
                const inputs = [usernameInput, passwordInput];
                
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    }
                });
                
                return isValid;
            }

            // Real-time validation
            [usernameInput, passwordInput].forEach(input => {
                input.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                });

                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Password toggle functionality
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Toggle icon
                if (type === 'text') {
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                    this.setAttribute('title', 'Hide password');
                } else {
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                    this.setAttribute('title', 'Show password');
                }
                
                // Add a subtle animation
                this.style.transform = 'translateY(-50%) scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-50%) scale(1)';
                }, 150);
                
                // Keep focus on password input
                passwordInput.focus();
            });

            // Set initial tooltip
            togglePasswordBtn.setAttribute('title', 'Show password');

            // Enhanced form submission with loading state
            loginForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                if (!validateForm()) {
                    // Shake animation for invalid form
                    loginForm.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        loginForm.style.animation = '';
                    }, 500);
                    return;
                }

                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                const btnText = loginBtn.querySelector('.btn-text');
                const originalText = btnText.textContent;
                btnText.textContent = 'Signing In...';

                // Simulate processing time for better UX
                setTimeout(() => {
                    this.submit();
                }, 800);
            });

            // Click to fill demo credentials
            credentialItems.forEach(item => {
                item.addEventListener('click', function() {
                    const credentialText = this.querySelector('.credential-login').textContent;
                    const [username, password] = credentialText.split(' / ');
                    
                    // Animate the filling
                    usernameInput.value = '';
                    passwordInput.value = '';
                    
                    // Type animation effect
                    typeText(usernameInput, username, () => {
                        typeText(passwordInput, password);
                    });
                    
                    // Visual feedback
                    this.style.background = 'rgba(102, 126, 234, 0.2)';
                    setTimeout(() => {
                        this.style.background = '';
                    }, 300);
                });

                // Hover effects
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.background = 'rgba(102, 126, 234, 0.05)';
                });

                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.background = '';
                });
            });

            // Type animation function
            function typeText(element, text, callback) {
                let i = 0;
                element.focus();
                
                const typeInterval = setInterval(() => {
                    element.value += text.charAt(i);
                    i++;
                    
                    if (i >= text.length) {
                        clearInterval(typeInterval);
                        element.dispatchEvent(new Event('input'));
                        if (callback) callback();
                    }
                }, 50);
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(event) {
                // Alt + 1-5 for quick demo credential selection
                if (event.altKey && event.key >= '1' && event.key <= '5') {
                    event.preventDefault();
                    const index = parseInt(event.key) - 1;
                    if (credentialItems[index]) {
                        credentialItems[index].click();
                    }
                }
            });

            // Add shake animation CSS
            const shakeCSS = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
            `;
            const style = document.createElement('style');
            style.textContent = shakeCSS;
            document.head.appendChild(style);

            // Enhanced visual feedback for shader interaction
            document.addEventListener('mousemove', function(event) {
                // The shader background already handles mouse interaction
                // Add subtle UI enhancements based on mouse position
                const mouseX = event.clientX / window.innerWidth;
                const mouseY = event.clientY / window.innerHeight;
                
                const loginCard = document.querySelector('.login-card');
                if (loginCard) {
                    const tiltX = (mouseY - 0.5) * 5;
                    const tiltY = (mouseX - 0.5) * -5;
                    loginCard.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`;
                }
            });

            // Add tooltip for keyboard shortcuts
            const credentialsTitle = document.querySelector('.demo-credentials h6');
            credentialsTitle.title = 'Tip: Use Alt + 1-5 to quickly select credentials, or click on any row';
        });
    </script>
</body>
</html>
