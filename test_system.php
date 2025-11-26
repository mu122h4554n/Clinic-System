<?php
// Simple system test script
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Clinic Management System - Test Results</h2>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Test if tables exist
    $tables = ['users', 'patients', 'doctors', 'appointments', 'medical_records', 'preliminary_checkups'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test user accounts
echo "<h3>2. User Accounts Test</h3>";
try {
    $stmt = $db->query("SELECT username, role FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll();
    
    if (count($users) >= 5) {
        echo "✅ Found " . count($users) . " active users<br>";
        foreach ($users as $user) {
            echo "- " . $user['username'] . " (" . ucfirst($user['role']) . ")<br>";
        }
    } else {
        echo "❌ Not enough users found<br>";
    }
} catch (Exception $e) {
    echo "❌ User test failed: " . $e->getMessage() . "<br>";
}

// Test password verification
echo "<h3>3. Password Hash Test</h3>";
try {
    $stmt = $db->query("SELECT username, password FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if (password_verify('password', $user['password'])) {
        echo "✅ Password hashing works correctly<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Password test failed: " . $e->getMessage() . "<br>";
}

// Test functions
echo "<h3>4. Functions Test</h3>";
try {
    // Test sanitizeInput
    $test_input = "<script>alert('test')</script>";
    $sanitized = sanitizeInput($test_input);
    if ($sanitized !== $test_input) {
        echo "✅ sanitizeInput() works correctly<br>";
    } else {
        echo "❌ sanitizeInput() not working<br>";
    }
    
    // Test formatDate
    $formatted = formatDate('2024-01-15');
    if (strpos($formatted, 'Jan') !== false) {
        echo "✅ formatDate() works correctly<br>";
    } else {
        echo "❌ formatDate() not working<br>";
    }
    
    // Test getFullName
    $fullName = getFullName('John', 'Doe');
    if ($fullName === 'John Doe') {
        echo "✅ getFullName() works correctly<br>";
    } else {
        echo "❌ getFullName() not working<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Functions test failed: " . $e->getMessage() . "<br>";
}

// Test file structure
echo "<h3>5. File Structure Test</h3>";
$required_files = [
    'index.php',
    'login.php',
    'logout.php',
    'dashboard.php',
    'appointments.php',
    'book-appointment.php',
    'my-appointments.php',
    'patients.php',
    'medical-records.php',
    'my-records.php',
    'checkups.php',
    'profile.php',
    'unauthorized.php',
    'admin/users.php',
    'admin/reports.php',
    'config/database.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "✅ All required files present<br>";
} else {
    echo "❌ Missing files:<br>";
    foreach ($missing_files as $file) {
        echo "- $file<br>";
    }
}

echo "<h3>6. System Summary</h3>";
if (empty($missing_files) && isset($db)) {
    echo "✅ <strong>System appears to be ready for use!</strong><br>";
    echo "<br><strong>Next Steps:</strong><br>";
    echo "1. Import the database: database/clinic_system.sql<br>";
    echo "2. Start XAMPP (Apache + MySQL)<br>";
    echo "3. Access: http://localhost/Clinic-System-2/<br>";
    echo "4. Use the default login credentials from README.md<br>";
} else {
    echo "❌ <strong>System has issues that need to be resolved</strong><br>";
}

echo "<br><em>Test completed at: " . date('Y-m-d H:i:s') . "</em>";
?>
