<?php
// Simple installation helper
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic System - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="fas fa-clinic-medical me-2"></i>Clinic Management System - Installation</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Installation Steps</h5>
                            <p>Follow these steps to set up your clinic management system:</p>
                        </div>

                        <div class="step mb-4">
                            <h5><span class="badge bg-primary me-2">1</span>Database Setup</h5>
                            <ul>
                                <li>Start XAMPP and ensure MySQL is running</li>
                                <li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>
                                <li>Import the file: <code>database/clinic_system.sql</code></li>
                                <li>This will create the database and insert sample users</li>
                            </ul>
                        </div>

                        <div class="step mb-4">
                            <h5><span class="badge bg-primary me-2">2</span>Configuration</h5>
                            <ul>
                                <li>Open <code>config/database.php</code></li>
                                <li>Update database credentials if needed (default: root with no password)</li>
                            </ul>
                        </div>

                        <div class="step mb-4">
                            <h5><span class="badge bg-primary me-2">3</span>Test System</h5>
                            <p>
                                <a href="test_system.php" class="btn btn-warning" target="_blank">
                                    <i class="fas fa-vial me-2"></i>Run System Test
                                </a>
                            </p>
                        </div>

                        <div class="step mb-4">
                            <h5><span class="badge bg-primary me-2">4</span>Default Login Credentials</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Username</th>
                                            <th>Password</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-danger">Admin</span></td>
                                            <td>admin</td>
                                            <td>password</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-primary">Doctor</span></td>
                                            <td>dr.smith</td>
                                            <td>password</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-primary">Doctor</span></td>
                                            <td>dr.jane</td>
                                            <td>password</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-warning">Receptionist</span></td>
                                            <td>receptionist</td>
                                            <td>password</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info">Patient</span></td>
                                            <td>patient1</td>
                                            <td>password</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="step mb-4">
                            <h5><span class="badge bg-success me-2">5</span>Start Using the System</h5>
                            <p>
                                <a href="login.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                                </a>
                            </p>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                            <ul class="mb-0">
                                <li>Change default passwords after first login</li>
                                <li>This system is for educational/demo purposes</li>
                                <li>Ensure proper security measures for production use</li>
                                <li>Regular database backups are recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-question-circle me-2"></i>Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Common Issues:</strong></p>
                        <ul>
                            <li><strong>Database connection error:</strong> Check if MySQL is running and credentials are correct</li>
                            <li><strong>Login issues:</strong> Ensure database is imported and use default credentials</li>
                            <li><strong>Permission errors:</strong> Check file permissions and ensure all files are present</li>
                        </ul>
                        <p>Check the <code>README.md</code> file for detailed documentation.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
