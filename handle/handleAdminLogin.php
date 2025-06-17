<?php
session_start();
require_once '../database/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) ? true : false;

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Please enter both email and password']);
    exit();
}

try {
    $conn = getConnection();
    
    // Check if admins table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'admins'");
    if ($table_check->num_rows === 0) {
        error_log("Admins table does not exist");
        echo json_encode(['success' => false, 'error' => 'System not properly configured. Please contact administrator.']);
        exit();
    }
    
    // Get admin details
    $stmt = $conn->prepare("SELECT id, email, password, name, status, login_attempts, last_attempt FROM admins WHERE email = ?");
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        
        // Check if account is active
        if ($admin['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Your account is inactive. Please contact the administrator.']);
            exit();
        }

        // Check login attempts
        $max_attempts = 5;
        $lockout_time = 15; // minutes
        
        if ($admin['login_attempts'] >= $max_attempts) {
            $last_attempt = strtotime($admin['last_attempt']);
            $time_diff = (time() - $last_attempt) / 60;
            
            if ($time_diff < $lockout_time) {
                echo json_encode(['success' => false, 'error' => 'Account temporarily locked. Please try again in ' . ceil($lockout_time - $time_diff) . ' minutes.']);
                exit();
            } else {
                // Reset login attempts after lockout period
                $reset_stmt = $conn->prepare("UPDATE admins SET login_attempts = 0 WHERE id = ?");
                if (!$reset_stmt) {
                    error_log("Reset prepare failed: " . $conn->error);
                    throw new Exception("Database error: " . $conn->error);
                }
                $reset_stmt->bind_param("i", $admin['id']);
                $reset_stmt->execute();
            }
        }
        
        // Verify password
        if (password_verify($password, $admin['password'])) {
            // Reset login attempts on successful login
            $reset_stmt = $conn->prepare("UPDATE admins SET login_attempts = 0, last_login = CURRENT_TIMESTAMP WHERE id = ?");
            if (!$reset_stmt) {
                error_log("Reset prepare failed: " . $conn->error);
                throw new Exception("Database error: " . $conn->error);
            }
            $reset_stmt->bind_param("i", $admin['id']);
            $reset_stmt->execute();
            
            // Set session variables
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            
            // Handle remember me
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $token_stmt = $conn->prepare("INSERT INTO admin_tokens (admin_id, token, expires_at) VALUES (?, ?, ?)");
                if (!$token_stmt) {
                    error_log("Token prepare failed: " . $conn->error);
                    throw new Exception("Database error: " . $conn->error);
                }
                $token_stmt->bind_param("iss", $admin['id'], $token, $expires);
                $token_stmt->execute();
                
                setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
            }
            
            // Log successful login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, 'login', 'Successful login', ?, ?)");
            if (!$log_stmt) {
                error_log("Log prepare failed: " . $conn->error);
                throw new Exception("Database error: " . $conn->error);
            }
            $log_stmt->bind_param("iss", $admin['id'], $ip, $user_agent);
            $log_stmt->execute();
            
            echo json_encode(['success' => true, 'redirect' => '../admin/adminDash.php']);
            exit();
        } else {
            // Increment login attempts
            $attempt_stmt = $conn->prepare("UPDATE admins SET login_attempts = login_attempts + 1, last_attempt = CURRENT_TIMESTAMP WHERE id = ?");
            if (!$attempt_stmt) {
                error_log("Attempt prepare failed: " . $conn->error);
                throw new Exception("Database error: " . $conn->error);
            }
            $attempt_stmt->bind_param("i", $admin['id']);
            $attempt_stmt->execute();
            
            echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
        exit();
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again later.']);
    exit();
} 