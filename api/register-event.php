<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to register for events']);
    exit();
}

// Check if event_id is provided
if (!isset($_POST['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = intval($_POST['event_id']);

try {
    // Check if event exists and is upcoming
    $stmt = $conn->prepare("SELECT id FROM events WHERE id = ? AND start_date >= CURDATE()");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Event not found or has already passed']);
        exit();
    }

    // Check if user is already registered
    $stmt = $conn->prepare("SELECT id FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You are already registered for this event']);
        exit();
    }

    // Register user for the event
    $stmt = $conn->prepare("INSERT INTO event_registrations (user_id, event_id, status, registration_date) VALUES (?, ?, 'pending', NOW())");
    $stmt->bind_param("ii", $user_id, $event_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully registered for the event']);
    } else {
        throw new Exception("Failed to register for the event");
    }
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while registering for the event']);
}
?> 