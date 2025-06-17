<?php
session_start();
require_once '../database/config.php';

// Initialize response array
$response = [
    'success' => false,
    'errors' => []
];

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate password
function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Function to validate student ID format
function validate_student_id($student_id) {
    return preg_match('/^\d{2}-\d{5}$/', $student_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $student_id = sanitize_input($_POST['student_id'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($first_name)) {
        $response['errors']['first_name'] = 'First name is required';
    }
    if (empty($last_name)) {
        $response['errors']['last_name'] = 'Last name is required';
    }
    if (empty($student_id)) {
        $response['errors']['student_id'] = 'Student ID is required';
    } elseif (!preg_match('/^\d{2}-\d{5}$/', $student_id)) {
        $response['errors']['student_id'] = 'Invalid student ID format (XX-XXXXX)';
    }
    if (empty($email)) {
        $response['errors']['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['errors']['email'] = 'Invalid email format';
    }
    if (empty($password)) {
        $response['errors']['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $response['errors']['password'] = 'Password must be at least 8 characters long';
    }
    if ($password !== $confirm_password) {
        $response['errors']['confirm_password'] = 'Passwords do not match';
    }

    // Check if student ID or email already exists
    if (empty($response['errors'])) {
        try {
            // Check student ID
            $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response['errors']['student_id'] = 'Student ID already registered';
            }
            $stmt->close();

            // Check email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response['errors']['email'] = 'Email already registered';
            }
            $stmt->close();

            // If no errors, proceed with registration
            if (empty($response['errors'])) {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $stmt = $conn->prepare("
                    INSERT INTO users (student_id, email, password, first_name, last_name, role)
                    VALUES (?, ?, ?, ?, ?, 'student')
                ");
                $stmt->bind_param("sssss", $student_id, $email, $hashed_password, $first_name, $last_name);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $_SESSION['success'] = 'Registration successful! You can now login.';
                    header("Location: ../pages/login.php");
                    exit();
                } else {
                    $response['errors']['general'] = 'Registration failed. Please try again.';
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $response['errors']['general'] = 'An error occurred. Please try again later.';
        }
    }

    // If there are errors, store them in session and redirect back
    if (!empty($response['errors'])) {
        $_SESSION['errors'] = $response['errors'];
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'student_id' => $student_id,
            'email' => $email
        ];
        header("Location: ../pages/register.php");
        exit();
    }
}

// If not POST request, redirect to registration page
header("Location: ../pages/register.php");
exit();
?>
