<?php
session_start();
require_once '../database/config.php';

// Check if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/adminDash.php');
    exit();
}

// Check if database tables exist
try {
    $conn = getConnection();
    $tables_exist = true;
    
    // Check for required tables
    $required_tables = ['admins', 'admin_tokens', 'admin_logs', 'password_resets'];
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $tables_exist = false;
            break;
        }
    }
    
    if (!$tables_exist) {
        header('Location: ../admin/setup_admin.php');
        exit();
    }
    
    // Check if any admin exists
    $result = $conn->query("SELECT COUNT(*) as count FROM admins");
    $row = $result->fetch_assoc();
    if ($row['count'] === 0) {
        header('Location: ../admin/setup_admin.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Database check error: " . $e->getMessage());
    header('Location: ../admin/setup_admin.php');
    exit();
}

// Get error messages if any
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$login_error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';

// Clear error messages
unset($_SESSION['error']);
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Login - CDM Event Management</title>
    
    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../home.css">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../home.php">
                <i class="bi bi-calendar"></i>
                <span>CDM Events</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../home.php">
                            <i class="bi bi-house"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./view.php">
                            <i class="bi bi-calendar"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-dark text-primary px-3 ms-2" href="register.php">
                            <i class="bi bi-person-plus"></i>Register
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="adminLogin.php">
                            <i class="bi bi-person-fill-lock"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-shield-lock-fill fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Admin Login</h2>
                            <p class="text-muted">Sign in to your admin account</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <div id="adminErrorAlert" class="alert alert-danger d-none" role="alert"></div>

                        <form id="adminLoginForm" method="POST" action="../handle/handleAdminLogin.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope-fill me-2"></i>Email Address
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           placeholder="Enter your email"
                                           required 
                                           autocomplete="off">
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-key-fill me-2"></i>Password
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-lock-fill text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           name="password" 
                                           placeholder="Enter your password"
                                           required 
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Please enter your password</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="adminRemember" name="remember">
                                    <label class="form-check-label text-muted" for="adminRemember">Remember me</label>
                                </div>
                                <a href="forgot-password.php" class="text-primary text-decoration-none">Forgot password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 py-2 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                            </button>

                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    <a href="login.php" class="text-primary text-decoration-none">
                                        <i class="bi bi-arrow-left me-2"></i>Back to Student Login
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="../home.php" class="text-muted text-decoration-none">
                        <i class="bi bi-box-arrow-in-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-calendar me-2"></i>CDM Events</h5>
                    <p class="mb-0">Creating memorable experiences through events.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> CDM Event Management. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Handle admin login form submission
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const errorAlert = document.getElementById('adminErrorAlert');
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorAlert.textContent = data.error;
                    errorAlert.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorAlert.textContent = 'An error occurred. Please try again.';
                errorAlert.classList.remove('d-none');
            });
        });
    </script>
</body>
</html>
