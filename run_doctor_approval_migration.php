<?php
/**
 * Migration Script: Add Doctor Approval System
 * 
 * This script adds the approval workflow for doctor registrations.
 * Run this once to update your database schema.
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Doctor Approval Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        h1 { color: #333; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Doctor Approval System Migration</h1>
";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Starting Migration...</h2>";
    
    // Check if columns already exist
    $stmt = $db->query("SHOW COLUMNS FROM doctors LIKE 'approval_status'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='info'>⚠️ Migration already applied. Columns already exist.</p>";
    } else {
        echo "<h3>Step 1: Adding approval_status column...</h3>";
        $db->exec("ALTER TABLE doctors ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER user_id");
        echo "<p class='success'>✅ approval_status column added</p>";
        
        echo "<h3>Step 2: Adding approval_notes column...</h3>";
        $db->exec("ALTER TABLE doctors ADD COLUMN approval_notes TEXT AFTER approval_status");
        echo "<p class='success'>✅ approval_notes column added</p>";
        
        echo "<h3>Step 3: Adding approved_by column...</h3>";
        $db->exec("ALTER TABLE doctors ADD COLUMN approved_by INT AFTER approval_notes");
        echo "<p class='success'>✅ approved_by column added</p>";
        
        echo "<h3>Step 4: Adding approved_at column...</h3>";
        $db->exec("ALTER TABLE doctors ADD COLUMN approved_at DATETIME AFTER approved_by");
        echo "<p class='success'>✅ approved_at column added</p>";
        
        echo "<h3>Step 5: Adding foreign key constraint...</h3>";
        $db->exec("ALTER TABLE doctors ADD CONSTRAINT fk_doctors_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "<p class='success'>✅ Foreign key constraint added</p>";
        
        echo "<h3>Step 6: Updating existing doctors to approved status...</h3>";
        $db->exec("UPDATE doctors SET approval_status = 'approved', approved_at = NOW() WHERE approval_status = 'pending'");
        echo "<p class='success'>✅ Existing doctors marked as approved</p>";
        
        echo "<h3>Step 7: Creating index for faster queries...</h3>";
        $db->exec("CREATE INDEX idx_approval_status ON doctors(approval_status)");
        echo "<p class='success'>✅ Index created</p>";
    }
    
    // Show current status
    echo "<h2>Current Status:</h2>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM doctors WHERE approval_status = 'pending'");
    $pending = $stmt->fetch()['count'];
    echo "<p>Pending Doctors: <strong>$pending</strong></p>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM doctors WHERE approval_status = 'approved'");
    $approved = $stmt->fetch()['count'];
    echo "<p>Approved Doctors: <strong>$approved</strong></p>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM doctors WHERE approval_status = 'rejected'");
    $rejected = $stmt->fetch()['count'];
    echo "<p>Rejected Doctors: <strong>$rejected</strong></p>";
    
    echo "<h2 class='success'>✅ Migration Completed Successfully!</h2>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>Access doctor registration at: <a href='../doctor_signup.php'>doctor_signup.php</a></li>";
    echo "<li>Manage approvals at: <a href='../admin/doctor_approvals.php'>admin/doctor_approvals.php</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Migration Failed</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>

