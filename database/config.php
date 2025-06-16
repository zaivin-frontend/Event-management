<?php
// Error reporting for development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Environment configuration
$env = 'development'; // Change to 'production' for live environment

// Database configuration
$config = [
    'development' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cdm_events'
    ],
    'production' => [
        'host' => 'localhost',
        'username' => '', // Set your production username
        'password' => '', // Set your production password
        'database' => 'cdm_events'
    ]
];

// Get current environment configuration
$db_config = $config[$env];

// Create connection without database selection first
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `{$db_config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!$conn->query($sql)) {
    error_log("Database creation failed: " . $conn->error);
    die("Database creation failed: " . $conn->error);
}

// Select the database
if (!$conn->select_db($db_config['database'])) {
    error_log("Database selection failed: " . $conn->error);
    die("Database selection failed: " . $conn->error);
}

// Set charset to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
    die("Error setting charset: " . $conn->error);
}

// Set timezone
date_default_timezone_set('UTC');

// Function to get database connection
function getConnection() {
    global $conn;
    return $conn;
}
?>