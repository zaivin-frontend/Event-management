<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Validate input
if (!isset($_POST['event_id']) || !isset($_POST['payment_method']) || !isset($_POST['reference_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'];
$payment_method = $_POST['payment_method'];
$reference_number = $_POST['reference_number'];

// Get event details
$stmt = $conn->prepare("
    SELECT payment_required, payment_amount, payment_methods
    FROM events
    WHERE id = ?
");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit();
}

// Validate payment method
$allowed_methods = explode(',', $event['payment_methods']);
if (!in_array($payment_method, $allowed_methods)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit();
}

// Check if payment already exists
$stmt = $conn->prepare("
    SELECT id FROM event_payments
    WHERE event_id = ? AND user_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment already submitted']);
    exit();
}

// Insert payment record
$stmt = $conn->prepare("
    INSERT INTO event_payments (
        event_id, user_id, payment_method, reference_number, 
        amount, status, created_at
    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->bind_param("iissd", $event_id, $user_id, $payment_method, $reference_number, $event['payment_amount']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Payment submitted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to submit payment']);
} 