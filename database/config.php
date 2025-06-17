<?php
// ob_start(); // Removed: Output buffering should be handled by the calling script for API responses
// Error reporting for development
// ini_set('display_errors', 1); // Moved to conditional block
// error_reporting(E_ALL); // Moved to conditional block

// Environment configuration
$env = 'development'; // Change to 'production' for live environment

// Conditionally set error reporting based on environment
if ($env === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 'Off');
    error_reporting(0);
}

// Database configuration
$config = [
    'development' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'event_management'
    ],
    'production' => [
        'host' => 'localhost',
        'username' => '', // Set your production username
        'password' => '', // Set your production password
        'database' => 'event_management'
    ]
];

// Get current environment configuration
$db_config = $config[$env];

// Create connection without database selection first
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    throw new Exception("Database connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `{$db_config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$conn->query($sql)) {
    error_log("Database creation failed: " . $conn->error);
    throw new Exception("Database creation failed: " . $conn->error);
}

// Select the database
if (!$conn->select_db($db_config['database'])) {
    error_log("Database selection failed: " . $conn->error);
    throw new Exception("Database selection failed: " . $conn->error);
}

// Set charset to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
    throw new Exception("Error setting charset: " . $conn->error);
}

// Set timezone
date_default_timezone_set('UTC');

// Function to get database connection
function getConnection() {
    global $conn;
    return $conn;
}
?>