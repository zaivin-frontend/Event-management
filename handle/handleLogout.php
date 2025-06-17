<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No active session found'
    ]);
    exit();
}

// Store user info for logging
$user_role = $_SESSION['role'] ?? null;
$user_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : '';

try {
    // Clear remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        require_once '../database/config.php';
        
        // Delete the token from database
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        
        // Clear the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();

    // Log the logout action
    error_log("User {$user_name} ({$user_role}) logged out successfully");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("Logout error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred during logout'
    ]);
}
?>
