<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Unauthorized Access';
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                    <h2 class="card-title">Access Denied</h2>
                    <p class="card-text">You don't have permission to access this page.</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>
                        Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
