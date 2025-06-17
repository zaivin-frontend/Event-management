<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['payment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit();
}

$payment_id = $data['payment_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Get payment details
    $stmt = $conn->prepare("
        SELECT event_id, user_id, payment_method, reference_number, payment_details 
        FROM event_payments 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    // Update payment status
    $stmt = $conn->prepare("
        UPDATE event_payments 
        SET status = 'approved',
            payment_details = JSON_MERGE_PATCH(payment_details, ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $payment_details = json_encode([
        'admin_approval' => [
            'admin_id' => $_SESSION['admin_id'],
            'timestamp' => date('Y-m-d H:i:s'),
            'notes' => $data['notes'] ?? null
        ]
    ]);
    $stmt->bind_param("si", $payment_details, $payment_id);
    $stmt->execute();

    // Update registration status
    $stmt = $conn->prepare("
        UPDATE event_registrations 
        SET status = 'confirmed' 
        WHERE event_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $payment['event_id'], $payment['user_id']);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Payment approved successfully',
        'payment' => [
            'id' => $payment_id,
            'reference' => $payment['reference_number'],
            'method' => $payment['payment_method']
        ]
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to approve payment: ' . $e->getMessage()]);
} 