<?php
// Database update script for History Tracking and Notification System
require_once 'config/database.php';

echo "<h2>Database Update - Adding History Tracking and Notification System</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h3>Step 1: Creating activity_log table...</h3>";
    
    // Check if activity_log table exists
    $stmt = $db->query("SHOW TABLES LIKE 'activity_log'");
    if ($stmt->rowCount() == 0) {
        $sql = "
        CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $db->exec($sql);
        echo "✅ activity_log table created successfully!<br>";
    } else {
        echo "ℹ️ activity_log table already exists.<br>";
    }
    
    echo "<h3>Step 2: Creating notifications table...</h3>";
    
    // Check if notifications table exists
    $stmt = $db->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() == 0) {
        $sql = "
        CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('appointment', 'reminder', 'system', 'success', 'warning') NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $db->exec($sql);
        echo "✅ notifications table created successfully!<br>";
    } else {
        echo "ℹ️ notifications table already exists.<br>";
    }
    
    echo "<h3>Step 3: Adding sample notifications...</h3>";
    
    // Check if sample notifications exist
    $stmt = $db->query("SELECT COUNT(*) as count FROM notifications");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $sql = "
        INSERT INTO notifications (user_id, title, message, type) VALUES
        (1, 'Welcome to Clinic System', 'Your admin account has been set up successfully. You now have access to history tracking and notifications!', 'system'),
        (2, 'New Features Available', 'History tracking and notification system have been added to your account.', 'system'),
        (3, 'System Update', 'The clinic system has been updated with new features for better user experience.', 'system'),
        (4, 'System Update', 'The clinic system has been updated with new features for better user experience.', 'system'),
        (5, 'Welcome Patient', 'Welcome to our clinic system! You can now receive notifications about your appointments.', 'system')
        ";
        
        $db->exec($sql);
        echo "✅ Sample notifications added successfully!<br>";
    } else {
        echo "ℹ️ Notifications already exist ($count notifications found).<br>";
    }
    
    echo "<h3>Step 4: Testing the system...</h3>";
    
    // Test activity logging
    try {
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (1, 'system_update', 'Database updated with history tracking and notifications', '127.0.0.1')");
        $stmt->execute();
        echo "✅ Activity logging test successful!<br>";
    } catch (Exception $e) {
        echo "❌ Activity logging test failed: " . $e->getMessage() . "<br>";
    }
    
    // Test notification creation
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (1, 'System Test', 'This is a test notification to verify the system is working.', 'system')");
        $stmt->execute();
        echo "✅ Notification creation test successful!<br>";
    } catch (Exception $e) {
        echo "❌ Notification creation test failed: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>✅ Database Update Complete!</h3>";
    echo "<p><strong>New Features Added:</strong></p>";
    echo "<ul>";
    echo "<li>📊 <strong>Activity Log:</strong> Track all user actions and system events</li>";
    echo "<li>🔔 <strong>Notifications:</strong> Real-time notifications for appointments and system updates</li>";
    echo "<li>🎨 <strong>Enhanced UI:</strong> Notification bell in header with dropdown</li>";
    echo "<li>📱 <strong>Responsive Design:</strong> Works on all devices</li>";
    echo "</ul>";
    
    echo "<p><strong>New Pages Available:</strong></p>";
    echo "<ul>";
    echo "<li><a href='notifications.php'>📧 Notifications Page</a> - View and manage all notifications</li>";
    echo "<li><a href='activity-log.php'>📋 Activity Log Page</a> - View your activity history</li>";
    echo "</ul>";
    
    echo "<p><strong>What's New:</strong></p>";
    echo "<ul>";
    echo "<li>🔔 Notification bell in header shows unread count</li>";
    echo "<li>📱 Automatic notifications for appointment bookings and status changes</li>";
    echo "<li>📊 Complete activity tracking for all user actions</li>";
    echo "<li>🎯 Role-based access (admins see all activities, users see their own)</li>";
    echo "</ul>";
    
    echo "<br><p><a href='login.php' class='btn btn-primary'>🚀 Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error updating database:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
