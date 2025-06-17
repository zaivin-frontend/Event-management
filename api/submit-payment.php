<?php
ob_start(); // Start output buffering
ini_set('display_errors', 'Off'); // Suppress display of errors
error_reporting(0); // Turn off all error reporting
session_start();
require_once '../database/config.php';
require_once '../vendor/autoload.php'; // For TCPDF and Guzzle
require_once 'payment-gateways.php';

use TCPDF;

// Define TCPDF constants
define('PDF_PAGE_ORIENTATION', 'P');
define('PDF_UNIT', 'mm');
define('PDF_PAGE_FORMAT', 'A4');
define('PDF_CREATOR', 'Event Management System');

// Ensure we're sending JSON response
header('Content-Type: application/json');

// Error handling function
function sendJsonResponse($success, $message, $data = null) {
    // Clean any output that might have been buffered before sending JSON
    ob_clean(); 
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['payment_data'] = $data;
    }
    echo json_encode($response);
    // ob_end_flush(); // No need to end flush here, let the script end naturally or manually if needed
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, 'User not logged in');
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

// Get and validate required parameters
$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? null;
$payment_method = $_POST['payment_method'] ?? null;
$amount = $_POST['amount'] ?? null;
$reference_number = $_POST['reference_number'] ?? null;

if (!$event_id || !$payment_method || !$amount || !$reference_number) {
    sendJsonResponse(false, 'Missing required parameters');
}

try {
    // Get event details, user's registration status, and user's payment status
    $stmt = $conn->prepare("
        SELECT e.*, 
               er.status as user_registration_status,
               ep.status as user_payment_status
        FROM events e 
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.user_id = ?
        LEFT JOIN event_payments ep ON e.id = ep.event_id AND ep.user_id = ?
        WHERE e.id = ?
    ");
    
    $stmt->bind_param("iii", $user_id, $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();

    if (!$event) {
        sendJsonResponse(false, 'Event not found');
    }

    $user_registration_status = $event['user_registration_status'] ?? null;
    $user_payment_status = $event['user_payment_status'] ?? null;

    // Check if user is already fully registered (confirmed)
    if ($user_registration_status === 'confirmed') {
        sendJsonResponse(false, 'You are already confirmed for this event.');
    }

    // Check if user has a pending payment
    if ($user_payment_status === 'pending') {
        sendJsonResponse(false, 'You have a pending payment for this event. Please wait for verification or contact support.');
    }

    // Check if user has an approved payment (already paid)
    if ($user_payment_status === 'approved') {
        sendJsonResponse(false, 'Payment already completed for this event.');
    }

    // Validate payment amount
    if ($amount != $event['payment_amount']) {
        sendJsonResponse(false, 'Invalid payment amount');
    }

    // Validate payment method
    $allowed_methods = explode(',', $event['payment_methods']);
    if (!in_array($payment_method, $allowed_methods)) {
        sendJsonResponse(false, 'Invalid payment method');
    }

    // Check if reference number already exists
    $stmt = $conn->prepare("SELECT id FROM event_payments WHERE reference_number = ?");
    $stmt->bind_param("s", $reference_number);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendJsonResponse(false, 'Reference number already exists');
    }

    // Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO event_payments (
            event_id, user_id, payment_method, reference_number, 
            amount, status, created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->bind_param("iissd", $event_id, $user_id, $payment_method, $reference_number, $amount);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record payment");
    }

    $payment_id = $conn->insert_id;

    // After successful payment record, also ensure event_registrations reflects pending status
    // Check if a registration already exists
    $stmt_check_reg = $conn->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt_check_reg->bind_param("ii", $event_id, $user_id);
    $stmt_check_reg->execute();
    $existing_registration = $stmt_check_reg->get_result()->fetch_assoc();

    if ($existing_registration) {
        // If registration exists, update its status to 'pending' (if not already 'confirmed')
        $stmt_update_reg = $conn->prepare("UPDATE event_registrations SET status = 'pending' WHERE id = ? AND status != 'confirmed'");
        $stmt_update_reg->bind_param("i", $existing_registration['id']);
        $stmt_update_reg->execute();
    } else {
        // If no registration exists, insert a new one with 'pending' status
        $stmt_insert_reg = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, status, registration_date) VALUES (?, ?, 'pending', NOW())");
        $stmt_insert_reg->bind_param("iis", $event_id, $user_id, $status = 'pending');
        $stmt_insert_reg->execute();
    }

    // Generate PDF receipt
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Event Management System');
    $pdf->SetTitle('Payment Receipt');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Set font
    $pdf->SetFont('helvetica', '', 12);

    // Add content to PDF
    $html = '
    <h1 style="text-align:center;">Payment Receipt</h1>
    <hr>
    <p><strong>Receipt No:</strong> ' . $reference_number . '</p>
    <p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>
    <p><strong>Event:</strong> ' . $event['title'] . '</p>
    <p><strong>Amount Paid:</strong> ₱' . number_format($amount, 2) . '</p>
    <p><strong>Payment Method:</strong> ' . ucfirst($payment_method) . '</p>
    <p><strong>Paid By:</strong> ' . $user['first_name'] . ' ' . $user['last_name'] . '</p>
    <hr>
    <p style="text-align:center;">Thank you for your payment!</p>
    <p style="text-align:center; color: #666;">Note: This payment is pending verification by the administrator.</p>
    ';

    $pdf->writeHTML($html, true, false, true, false, '');

    // Save PDF to server
    $pdf_path = '../receipts/' . $reference_number . '.pdf';
    if (!file_exists('../receipts')) {
        mkdir('../receipts', 0777, true);
    }
    $pdf->Output($pdf_path, 'F');

    // Return success response with payment data
    sendJsonResponse(true, 'Payment submitted successfully', [
        'payment_id' => $payment_id,
        'reference_number' => $reference_number,
        'receipt_url' => 'receipts/' . $reference_number . '.pdf',
        'status' => 'pending'
    ]);

} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    sendJsonResponse(false, 'Payment failed: ' . $e->getMessage());
}
ob_end_flush(); // Flush the output buffer at the very end
?>
