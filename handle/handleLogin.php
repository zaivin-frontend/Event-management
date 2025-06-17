<?php
session_start();
require_once '../database/config.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get and sanitize input
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$student_id = filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_STRING);
$is_admin_login = isset($_POST['admin_login']) && $_POST['admin_login'] === '1';
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

// Validate input
if ($is_admin_login) {
    if (!$email || !$password) {
        echo json_encode([
            'success' => false,
            'error' => 'Please provide both email and password'
        ]);
        exit();
    }
} else {
    // Prevent admin login through student form
    if (isset($_POST['email'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid login attempt'
        ]);
        exit();
    }

    // Validate student ID format (XX-XXXXX)
    if (!$student_id || !preg_match('/^\d{2}-\d{5}$/', $student_id)) {
        echo json_encode([
            'success' => false,
            'error' => 'Please enter a valid student ID in the format XX-XXXXX'
        ]);
        exit();
    }
    if (!$password) {
        echo json_encode([
            'success' => false,
            'error' => 'Please enter your password'
        ]);
        exit();
    }
}

try {
    $conn = getConnection();
    
    // Prepare the query based on login type
    if ($is_admin_login) {
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, email, password, role, status
            FROM users 
            WHERE email = ? AND role = 'admin'
        ");
        $stmt->bind_param("s", $email);
    } else {
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, email, password, role, status, student_id
            FROM users 
            WHERE student_id = ? AND role = 'student'
        ");
        $stmt->bind_param("s", $student_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Check if account is active
        if ($user['status'] !== 'active') {
            echo json_encode([
                'success' => false,
                'error' => 'Your account is inactive. Please contact the administrator.'
            ]);
            exit();
        }

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Reset login attempts on successful login
            $stmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = 0,
                    last_login = CURRENT_TIMESTAMP,
                    last_attempt = NULL
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            if ($user['role'] === 'student') {
                $_SESSION['student_id'] = $user['student_id'];
            }

            // Handle remember me functionality
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Store token in database
                $stmt = $conn->prepare("
                    INSERT INTO remember_tokens (user_id, token, expires_at)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $user['id'], $token, $expires);
                $stmt->execute();

                // Set secure cookie
                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => strtotime('+30 days'),
                        'path' => '/',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            }

            // Return success response
            echo json_encode([
                'success' => true,
                'role' => $user['role'],
                'redirect' => $user['role'] === 'admin' ? '../admin/adminDash.php' : '../students/dashboard.php'
            ]);
        } else {
            // Increment login attempts
            $stmt = $conn->prepare("
                UPDATE users 
                SET login_attempts = login_attempts + 1,
                    last_attempt = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();

            echo json_encode([
                'success' => false,
                'error' => $is_admin_login ? 'Invalid email or password' : 'Invalid student ID or password'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => $is_admin_login ? 'Invalid email or password' : 'Invalid student ID or password'
        ]);
    }
} catch (Exception $e) {
    // Log error
    error_log("Login error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during login. Please try again.'
    ]);
}
?>
