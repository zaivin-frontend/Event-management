<?php
session_start();
header('Content-Type: application/json');

require_once '../database/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $payment_id = filter_var($input['payment_id'] ?? null, FILTER_SANITIZE_NUMBER_INT);
    $notes = filter_var($input['notes'] ?? null, FILTER_SANITIZE_STRING);

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Payment ID is required.']);
        exit();
    }

    $conn->begin_transaction();

    try {
        // 1. Update payment status to approved
        $stmt = $conn->prepare("UPDATE event_payments SET status = 'approved', notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $notes, $payment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update payment status: " . $stmt->error);
        }
        $stmt->close();

        // 2. Get registration ID associated with this payment
        $stmt = $conn->prepare("SELECT registration_id FROM event_payments WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment_data = $result->fetch_assoc();
        $stmt->close();

        if (!$payment_data || !$payment_data['registration_id']) {
            throw new Exception("Registration not found for this payment.");
        }

        $registration_id = $payment_data['registration_id'];

        // 3. Update registration status to confirmed
        $stmt = $conn->prepare("UPDATE event_registrations SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $registration_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update registration status: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Payment verified and registration confirmed.']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Payment verification error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?> 