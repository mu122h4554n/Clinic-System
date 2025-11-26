<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

startSession();

// Log logout activity
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    logActivity($db, 'logout', 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to home page
header('Location: index.php');
exit();
?>
