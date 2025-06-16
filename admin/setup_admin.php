<?php
require_once '../database/config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Function to create database if it doesn't exist
function create_database($conn, $database) {
    // Sanitize database name
    $database = preg_replace('/[^a-zA-Z0-9_]/', '', $database);
    
    // Check if database name is valid
    if (empty($database)) {
        throw new Exception("Invalid database name");
    }
    
    $sql = "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        return true;
    }
    throw new Exception("Failed to create database: " . $conn->error);
}

// Function to create required tables
function create_tables($conn) {
    $tables = [
        // Admins table
        "CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `email` VARCHAR(255) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `login_attempts` INT UNSIGNED DEFAULT 0,
            `last_attempt` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_status` (`status`),
            INDEX `idx_last_login` (`last_login`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Admin tokens table for remember me functionality
        "CREATE TABLE IF NOT EXISTS `admin_tokens` (
            `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `admin_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
            INDEX `idx_token` (`token`),
            INDEX `idx_expires` (`expires_at`),
            INDEX `idx_last_used` (`last_used_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Admin logs table for activity tracking
        "CREATE TABLE IF NOT EXISTS `admin_logs` (
            `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `admin_id` INT UNSIGNED NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `details` TEXT,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
            INDEX `idx_action` (`action`),
            INDEX `idx_created_at` (`created_at`),
            INDEX `idx_ip_address` (`ip_address`),
            INDEX `idx_admin_action` (`admin_id`, `action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Password resets table
        "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            `admin_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `used_at` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
            INDEX `idx_token` (`token`),
            INDEX `idx_expires` (`expires_at`),
            INDEX `idx_used` (`used_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    $success = true;
    $errors = [];

    foreach ($tables as $sql) {
        if (!$conn->query($sql)) {
            $success = false;
            $errors[] = "Error creating table: " . $conn->error;
        }
    }

    return ['success' => $success, 'errors' => $errors];
}

// Function to create default admin account
function create_default_admin($conn) {
    $email = 'admin@cdm.edu.ph';
    $password = 'Admin@123'; // More secure default password
    $name = 'System Administrator';
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid default admin email'];
    }
    
    // Check if admin already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Default admin account already exists'];
    }
    
    // Create new admin account with stronger password hashing
    $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    $stmt = $conn->prepare("INSERT INTO admins (email, password, name, status) VALUES (?, ?, ?, 'active')");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param("sss", $email, $hashed_password, $name);
    
    if ($stmt->execute()) {
        // Log the admin creation
        $admin_id = $conn->insert_id;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, 'account_created', 'Initial admin account created', ?, ?)");
        if ($log_stmt) {
            $log_stmt->bind_param("iss", $admin_id, $ip, $user_agent);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        return [
            'success' => true,
            'message' => 'Default admin account created successfully',
            'credentials' => [
                'email' => $email,
                'password' => $password
            ]
        ];
    } else {
        return ['success' => false, 'message' => 'Error creating default admin account: ' . $stmt->error];
    }
}

// Initialize setup results array
$setup_results = [];

try {
    // Get database connection
    $conn = getConnection();
    
    // Create tables
    $tables_result = create_tables($conn);
    $setup_results['tables'] = $tables_result;
    
    // Only proceed with admin creation if tables were created successfully
    if ($tables_result['success']) {
        $admin_result = create_default_admin($conn);
        $setup_results['admin'] = $admin_result;
    }
    
} catch (Exception $e) {
    $setup_results['error'] = "Setup failed: " . $e->getMessage();
    error_log("Setup error: " . $e->getMessage());
}

// Output results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - CDM Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .setup-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .setup-card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .setup-header {
            background-color: #0d6efd;
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .setup-body {
            padding: 2rem;
        }
        .status-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="card setup-card">
            <div class="setup-header">
                <h1 class="h3 mb-0">
                    <i class="bi bi-gear-fill me-2"></i>
                    Admin System Setup
                </h1>
            </div>
            <div class="setup-body">
                <?php if (isset($setup_results['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill status-icon"></i>
                        <?php echo htmlspecialchars($setup_results['error']); ?>
                    </div>
                <?php else: ?>
                    <!-- Database Tables Setup -->
                    <div class="mb-4">
                        <h2 class="h5 mb-3">Database Tables Setup</h2>
                        <?php if ($setup_results['tables']['success']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill status-icon"></i>
                                All required tables created successfully
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle-fill status-icon"></i>
                                Error creating tables:
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($setup_results['tables']['errors'] as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Default Admin Account Setup -->
                    <div class="mb-4">
                        <h2 class="h5 mb-3">Default Admin Account Setup</h2>
                        <?php if (isset($setup_results['admin'])): ?>
                            <?php if ($setup_results['admin']['success']): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill status-icon"></i>
                                    <?php echo htmlspecialchars($setup_results['admin']['message']); ?>
                                    
                                    <?php if (isset($setup_results['admin']['credentials'])): ?>
                                        <div class="credentials-box">
                                            <h3 class="h6 mb-3">Default Login Credentials:</h3>
                                            <p class="mb-1">
                                                <strong>Email:</strong> 
                                                <?php echo htmlspecialchars($setup_results['admin']['credentials']['email']); ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong>Password:</strong> 
                                                <?php echo htmlspecialchars($setup_results['admin']['credentials']['password']); ?>
                                            </p>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                Please change these credentials after your first login!
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill status-icon"></i>
                                    <?php echo htmlspecialchars($setup_results['admin']['message']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Next Steps -->
                <div class="text-center mt-4">
                    <a href="../home.php" class="btn btn-primary">
                        <i class="bi bi-house-fill me-2"></i>
                        Go to Homepage
                    </a>
                    <a href="adminDash.php" class="btn btn-success ms-2">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Go to Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
