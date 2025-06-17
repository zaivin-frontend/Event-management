<?php
session_start();
require_once '../database/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get error messages and form data from session
$errors = isset($_SESSION['errors']) ? $_SESSION['errors'] : [];
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];

// Clear session data
unset($_SESSION['errors']);
unset($_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register - CDM Event Management</title>
    
    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/styles/login.css">
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
                        <a class="nav-link" href="./login.php">
                            <i class="bi bi-box-arrow-in-right"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active btn btn-dark text-primary px-3 ms-2" href="./register.php">
                            <i class="bi bi-person-plus"></i>Register
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../pages/adminLogin.php">
                            <i class="bi bi-person-fill-lock"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Login Modal -->
    <div class="modal fade" id="adminLoginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content admin-login-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill-lock me-2"></i>Admin Portal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-fill-lock fs-1 text-primary mb-3"></i>
                        <h4 class="fw-bold">Secure Login</h4>
                        <p class="text-muted">Enter your credentials to access the admin panel</p>
                    </div>
                    <form id="adminLoginForm" action="../handle/handleLogin.php" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="admin_login" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-envelope me-2"></i>Email
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
                                       autocomplete="email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-key me-2"></i>Password
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="bi bi-lock text-muted"></i>
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
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                                <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                            </div>
                            <a href="admin/forgot-password.php" class="text-primary text-decoration-none">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield me-2"></i>Secure connection
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus-fill fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Create Account</h2>
                            <p class="text-muted">Join CDM Events today</p>
                        </div>

                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="../handle/handleRegister.php" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-person me-2"></i>First Name
                                    </label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                           name="first_name" 
                                           value="<?php echo isset($form_data['first_name']) ? htmlspecialchars($form_data['first_name']) : ''; ?>"
                                           required>
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-person me-2"></i>Last Name
                                    </label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                           name="last_name" 
                                           value="<?php echo isset($form_data['last_name']) ? htmlspecialchars($form_data['last_name']) : ''; ?>"
                                           required>
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-person-badge-fill me-2"></i>Student ID
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-person-badge-fill text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['student_id']) ? 'is-invalid' : ''; ?>" 
                                           name="student_id" 
                                           value="<?php echo isset($form_data['student_id']) ? htmlspecialchars($form_data['student_id']) : ''; ?>"
                                           pattern="\d{2}-\d{5}"
                                           maxlength="8"
                                           placeholder="Enter your student ID (e.g., XX-XXXXX)"
                                           required
                                           oninput="formatStudentId(this)">
                                    <?php if (isset($errors['student_id'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['student_id']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Enter your student ID in the format XX-XXXXX</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-envelope-fill me-2"></i>Email
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email" 
                                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           name="email" 
                                           placeholder="Enter your email"
                                           value="<?php echo isset($form_data['email']) ? htmlspecialchars($form_data['email']) : ''; ?>"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-key-fill me-2"></i>Password
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-lock-fill text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                           name="password" 
                                           placeholder="Enter your password"
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    Password must be at least 8 characters long and include uppercase, lowercase, and numbers
                                </small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-key-fill me-2"></i>Confirm Password
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-lock-fill text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                           name="confirm_password" 
                                           placeholder="Confirm password"
                                           required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 py-2 mb-3">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>

                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    Already have an account? 
                                    <a href="login.php" class="text-primary text-decoration-none">Sign in here</a>
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
    <script src="../assets/js/register.js"></script>
    <script src="../assets/js/home.js"></script>
</body>
</html>
