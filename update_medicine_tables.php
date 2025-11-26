<?php
// Database update script for Medicine Management System
require_once 'config/database.php';

echo "<!DOCTYPE html><html><head><title>Medicine Tables Migration</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>";
echo "</head><body class='container mt-5'>";

echo "<h2>Database Update - Adding Medicine Management System</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h3>Step 1: Creating medicines table...</h3>";
    
    // Check if medicines table exists
    $stmt = $db->query("SHOW TABLES LIKE 'medicines'");
    if ($stmt->rowCount() == 0) {
        $sql = "
        CREATE TABLE medicines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            category ENUM('minor', 'major') DEFAULT 'minor',
            requires_approval BOOLEAN DEFAULT FALSE,
            stock_quantity INT DEFAULT 0,
            price DECIMAL(10,2) DEFAULT 0,
            usage_instructions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $db->exec($sql);
        echo "✅ medicines table created successfully!<br>";
    } else {
        echo "ℹ️ medicines table already exists.<br>";
    }
    
    echo "<h3>Step 2: Creating medicine_orders table...</h3>";
    
    // Check if medicine_orders table exists
    $stmt = $db->query("SHOW TABLES LIKE 'medicine_orders'");
    if ($stmt->rowCount() == 0) {
        $sql = "
        CREATE TABLE medicine_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            medicine_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            status ENUM('pending', 'approved', 'rejected', 'auto_approved', 'fulfilled') DEFAULT 'pending',
            patient_notes TEXT,
            review_notes TEXT,
            doctor_id INT,
            approved_by INT,
            requires_appointment BOOLEAN DEFAULT FALSE,
            approved_at DATETIME,
            fulfilled_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        $db->exec($sql);
        echo "✅ medicine_orders table created successfully!<br>";
    } else {
        echo "ℹ️ medicine_orders table already exists.<br>";
    }
    
    echo "<h3>Step 3: Adding sample medicines...</h3>";
    
    // Check if medicines exist
    $stmt = $db->query("SELECT COUNT(*) as count FROM medicines");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $sql = "
        INSERT INTO medicines (name, description, category, requires_approval, stock_quantity, price, usage_instructions) VALUES
        ('Paracetamol 500mg', 'Pain reliever and fever reducer suitable for mild symptoms.', 'minor', FALSE, 200, 4.50, 'Take 1 tablet every 6 hours as needed. Do not exceed 4 tablets per day.'),
        ('Cough Syrup', 'Non-drowsy cough suppressant for dry cough.', 'minor', FALSE, 120, 6.00, 'Take 10ml every 8 hours. Shake well before use.'),
        ('Antibiotic A20', 'Broad spectrum antibiotic requiring physician oversight.', 'major', TRUE, 40, 18.75, 'As directed by physician. Complete the full course even if symptoms improve.'),
        ('Hypertension Control Pack', 'Prescription medication pack for hypertension patients.', 'major', TRUE, 25, 32.00, 'Take dosage as instructed by your doctor. Monitor blood pressure daily.'),
        ('Ibuprofen 400mg', 'Anti-inflammatory pain reliever for minor aches and pains.', 'minor', FALSE, 150, 5.50, 'Take 1 tablet every 6-8 hours with food. Maximum 3 tablets per day.'),
        ('Antihistamine Tablets', 'For allergy relief and hay fever symptoms.', 'minor', FALSE, 100, 7.25, 'Take 1 tablet daily. May cause drowsiness.'),
        ('Diabetes Medication Type B', 'Prescription medication for diabetes management.', 'major', TRUE, 30, 45.00, 'Take as prescribed by your doctor. Monitor blood sugar levels regularly.'),
        ('Heart Medication Pack', 'Cardiac medication requiring medical supervision.', 'major', TRUE, 20, 55.00, 'Follow doctor\'s instructions precisely. Regular checkups required.')
        ";
        
        $db->exec($sql);
        echo "✅ Sample medicines added successfully!<br>";
    } else {
        echo "ℹ️ Medicines already exist ($count medicines found).<br>";
    }
    
    echo "<h3>Step 4: Updating notifications table for medicine type...</h3>";
    
    // Check if notifications table has 'medicine' type
    $stmt = $db->query("SHOW COLUMNS FROM notifications LIKE 'type'");
    $column = $stmt->fetch();
    
    if ($column) {
        // Check current ENUM values
        $stmt = $db->query("SHOW COLUMNS FROM notifications WHERE Field = 'type'");
        $typeColumn = $stmt->fetch();
        
        if (strpos($typeColumn['Type'], 'medicine') === false) {
            // Add medicine type to notifications
            $sql = "ALTER TABLE notifications MODIFY type ENUM('appointment', 'reminder', 'system', 'success', 'warning', 'medicine') NOT NULL";
            $db->exec($sql);
            echo "✅ Notifications table updated with 'medicine' type!<br>";
        } else {
            echo "ℹ️ Notifications table already has 'medicine' type.<br>";
        }
    } else {
        echo "⚠️ Could not find notifications.type column.<br>";
    }
    
    echo "<h3>✅ Database Update Complete!</h3>";
    echo "<p><strong>New Features Added:</strong></p>";
    echo "<ul>";
    echo "<li>💊 <strong>Medicines Table:</strong> Catalog of available medicines with stock management</li>";
    echo "<li>📋 <strong>Medicine Orders:</strong> Patient medicine requests and doctor approvals</li>";
    echo "<li>🔄 <strong>Auto-Approval:</strong> Minor condition medicines can be purchased directly</li>";
    echo "<li>✅ <strong>Doctor Approval:</strong> Major condition medicines require doctor approval</li>";
    echo "<li>📦 <strong>Stock Management:</strong> Automatic inventory tracking</li>";
    echo "</ul>";
    
    echo "<p><strong>New Pages Available:</strong></p>";
    echo "<ul>";
    echo "<li><a href='medicines.php'>💊 Medicines Page</a> - Browse and purchase medicines (Patients)</li>";
    echo "<li><a href='medicine-requests.php'>📋 Medicine Requests</a> - Review and approve requests (Doctors)</li>";
    echo "</ul>";
    
    echo "<br><p><a href='medicines.php' class='btn btn-primary'>🚀 Go to Medicines Page</a> ";
    echo "<a href='dashboard.php' class='btn btn-secondary'>🏠 Go to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error updating database:</h3>";
    echo "<div class='alert alert-danger'><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
    echo "<p>Please check your database connection and try again.</p>";
}

echo "</body></html>";
?>

