<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information for the confirmation message
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : 'User';
$user_role = $_SESSION['role'] ?? 'student';

// Determine the dashboard URL based on user role
$dashboard_url = $user_role === 'admin' ? '../admin/adminDash.php' : '../students/dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/styles/students.css">
    <link rel="stylesheet" href="../assets/css/logout.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $dashboard_url; ?>">
                <i class="bi bi-calendar-event"></i> Event Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $dashboard_url; ?>">Dashboard</a>
                    </li>
                    <?php if ($user_role === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../students/my-events.php">My Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../students/profile.php">Profile</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="logout-container">
        <!-- Messages -->
        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>

        <div class="card logout-card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-box-arrow-right logout-icon"></i>
                    <h2 class="fw-bold">Logout</h2>
                    <p class="text-muted">Are you sure you want to logout?</p>
                </div>

                <div class="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    You are currently logged in as <strong><?php echo htmlspecialchars($user_name); ?></strong>
                    (<?php echo ucfirst(htmlspecialchars($user_role)); ?>)
                </div>

                <div class="d-grid gap-2">
                    <button id="logoutBtn" class="btn btn-danger btn-lg">
                        <i class="bi bi-box-arrow-right me-2"></i>Yes, Logout
                    </button>
                    <a href="<?php echo $dashboard_url; ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?php echo $dashboard_url; ?>" class="back-link">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show error message
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        // Show success message
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000);
        }

        document.getElementById('logoutBtn').addEventListener('click', function() {
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';

            // Perform logout
            fetch('../handle/handleLogout.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess('Logging out successfully...');
                        // Redirect to home page after a short delay
                        setTimeout(() => {
                            window.location.href = '../home.php';
                        }, 1000);
                    } else {
                        showError(data.error || 'Failed to logout. Please try again.');
                        // Reset button state
                        this.disabled = false;
                        this.innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Yes, Logout';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('An error occurred. Please try again.');
                    // Reset button state
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Yes, Logout';
                });
        });
    </script>
</body>
</html>
