<?php
session_start();
require_once '../database/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get error and success messages from session
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';

// Clear session messages
unset($_SESSION['error']);
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>CDM Forgot Password - Event Management System</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/styles/forgot.css" />
    <link rel="stylesheet" href="../home.css" />

</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-header text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-key me-2"></i>Forgot Password
                        </h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <i class="bi bi-envelope-paper fs-1 text-primary mb-3"></i>
                            <h4 class="fw-bold">Reset Your Password</h4>
                            <p class="text-muted">Enter your email address and we'll send you instructions to reset your password.</p>
                        </div>

                        <form action="../handle/handleForgot.php" method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope me-2"></i>Email Address
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           placeholder="Enter your email"
                                           required 
                                           autocomplete="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                    <div class="invalid-feedback">Please enter a valid email address</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 py-2 mb-3">
                                <i class="bi bi-send me-2"></i>Send Reset Link
                            </button>

                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <p class="text-muted mb-0">
                            <i class="bi bi-shield me-2"></i>Secure password recovery
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/home.js"></script>
    <script src="../assets/js/view.js"></script>
</body>
</html>
