<?php
session_start();
require_once '../database/config.php';

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate a secure token
function generate_token() {
    return bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize_input($_POST['email']);
    $error = '';
    $success = '';

    // Validate email
    if (empty($email)) {
        $error = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        try {
            // Check if email exists in database
            $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Generate reset token
                $token = generate_token();
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user['user_id'], $token, $expiry);
                
                if ($stmt->execute()) {
                    // Prepare email content
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/PHP/event-management/pages/reset-password.php?token=" . $token;
                    $to = $email;
                    $subject = "CDM Events - Password Reset Request";
                    
                    $message = "
                    <html>
                    <head>
                        <title>Password Reset Request</title>
                    </head>
                    <body>
                        <h2>Password Reset Request</h2>
                        <p>Dear " . htmlspecialchars($user['full_name']) . ",</p>
                        <p>We received a request to reset your password. Click the link below to reset your password:</p>
                        <p><a href='" . $reset_link . "'>Reset Password</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                        <br>
                        <p>Best regards,<br>CDM Events Team</p>
                    </body>
                    </html>
                    ";

                    // Set email headers
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= 'From: CDM Events <noreply@cdmevents.com>' . "\r\n";

                    // Send email
                    if (mail($to, $subject, $message, $headers)) {
                        $success = "Password reset instructions have been sent to your email.";
                    } else {
                        throw new Exception("Failed to send email");
                    }
                } else {
                    throw new Exception("Failed to store reset token");
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = "If your email is registered, you will receive password reset instructions.";
            }
        } catch (Exception $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }

    // Store messages in session
    if ($error) {
        $_SESSION['error'] = $error;
    }
    if ($success) {
        $_SESSION['success'] = $success;
    }

    // Redirect back to forgot password page
    header("Location: ../pages/forgot.php");
    exit();
}

// If not POST request, redirect to forgot password page
header("Location: ../pages/forgot.php");
exit();
?>
