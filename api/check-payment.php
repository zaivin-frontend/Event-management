<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Event ID is required']);
    exit();
}

$event_id = $_GET['event_id'];

// Get event payment details
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
    echo json_encode(['error' => 'Event not found']);
    exit();
}

// Format response
$response = [
    'payment_required' => (bool)$event['payment_required'],
    'payment_amount' => (float)$event['payment_amount'],
    'payment_methods' => $event['payment_methods'] ? explode(',', $event['payment_methods']) : [],
    'event_id' => $event_id
];

echo json_encode($response); 