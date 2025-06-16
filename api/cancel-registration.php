<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to cancel event registration']);
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
    // Check if registration exists and belongs to the user
    $stmt = $conn->prepare("SELECT id FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No registration found for this event']);
        exit();
    }

    // Cancel the registration
    $stmt = $conn->prepare("UPDATE event_registrations SET status = 'cancelled' WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully cancelled event registration']);
    } else {
        throw new Exception("Failed to cancel registration");
    }
} catch (Exception $e) {
    error_log("Cancellation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while cancelling the registration']);
}
?> 