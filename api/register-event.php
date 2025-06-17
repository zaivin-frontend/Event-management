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
    // Get event details to check payment requirements and capacity
    $stmt = $conn->prepare("SELECT id, payment_required, payment_amount, capacity FROM events WHERE id = ? AND start_date >= CURDATE()");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or has already passed']);
        exit();
    }

    // Check if user is already registered with a confirmed status
    $stmt = $conn->prepare("SELECT status FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $existing_registration = $stmt->get_result()->fetch_assoc();
    
    if ($existing_registration && $existing_registration['status'] === 'confirmed') {
        echo json_encode(['success' => false, 'message' => 'You are already registered and confirmed for this event']);
        exit();
    }

    // Check if event is full (only applicable for confirmed registrations)
    $stmt = $conn->prepare("SELECT COUNT(*) as registered_count FROM event_registrations WHERE event_id = ? AND status = 'confirmed'");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $registered_count = $stmt->get_result()->fetch_assoc()['registered_count'];

    if ($registered_count >= $event['capacity']) {
        echo json_encode(['success' => false, 'message' => 'Sorry, this event is already full.']);
        exit();
    }

    // If payment is required, this API call should not directly register.
    // Registration for paid events happens upon payment approval.
    if ($event['payment_required'] && $event['payment_amount'] > 0) {
        if ($existing_registration && $existing_registration['status'] === 'pending') {
            // If there's a pending registration for a paid event
            echo json_encode(['success' => false, 'message' => 'You have already submitted payment for this event, and your registration is pending verification.']);
        } else {
            // If no existing registration, or if cancelled for a paid event
            echo json_encode(['success' => false, 'message' => 'Payment is required for this event. Please proceed with payment first.']);
        }
        exit();
    }

    // Register user for free event (status will always be 'confirmed')
    $status = 'confirmed';
    $stmt = $conn->prepare("INSERT INTO event_registrations (user_id, event_id, status, registration_date) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $user_id, $event_id, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully registered for the event', 'status' => $status]);
    } else {
        throw new Exception("Failed to register for the event");
    }
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while registering for the event']);
}
?> 