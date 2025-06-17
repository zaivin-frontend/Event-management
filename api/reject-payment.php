<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['payment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit();
}

$payment_id = $data['payment_id'];
$reason = $data['reason'] ?? 'Payment verification failed';
$notes = $data['notes'] ?? null;
$admin_id = $_SESSION['admin_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Update payment status
    $query = "UPDATE event_payments 
              SET status = 'rejected', 
                  verified_by = ?, 
                  verification_notes = ?,
                  rejection_reason = ?,
                  verified_at = NOW()
              WHERE id = ? AND status = 'pending'";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $admin_id, $notes, $reason, $payment_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Payment not found or already processed');
    }
    
    // Get the event_id and user_id for this payment
    $query = "SELECT event_id, user_id FROM event_payments WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    
    // Update registration status
    $query = "UPDATE event_registrations 
              SET status = 'pending', 
                  payment_status = 'rejected'
              WHERE event_id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $payment['event_id'], $payment['user_id']);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment rejected successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Error in reject-payment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while rejecting payment']);
}
?> 