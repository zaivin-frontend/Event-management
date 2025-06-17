<?php
ob_start(); // Start output buffering at the very beginning
ini_set('display_errors', 'Off'); // Temporarily disable display errors for this API endpoint
error_reporting(0); // Temporarily turn off error reporting for this API endpoint
session_start();

try {
    require_once '../database/config.php';

    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit();
    }

    $event_id = $_GET['event_id'] ?? null;

    if (!$event_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit();
    }

    // Get event details
    $stmt = $conn->prepare("
        SELECT id, title, payment_required, payment_amount, payment_methods,
               start_date, end_date, location
        FROM events 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();

    if (!$event) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit();
    }

    // Log event payment details for debugging
    error_log("Check Payment: Event ID {$event_id}, Payment Required: " . ($event['payment_required'] ? 'true' : 'false') . ", Payment Amount: {$event['payment_amount']}");

    // Check if user is already registered
    $stmt = $conn->prepare("
        SELECT status, registration_date
        FROM event_registrations 
        WHERE event_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $event_id, $_SESSION['user_id']);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();

    // Only consider fully registered if status is 'confirmed'
    if ($registration && $registration['status'] === 'confirmed') {
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'You are already registered for this event',
            'registration' => $registration
        ]);
        exit();
    }

    // Check if payment is required
    if ($event['payment_required']) {
        // Get test account information
        $test_accounts = [
            'gcash' => [
                'number' => '09123456789',
                'name' => 'Test GCash Account',
                'instructions' => 'Use this account number for testing GCash payments'
            ],
            'paymaya' => [
                'number' => '09187654321',
                'name' => 'Test PayMaya Account',
                'instructions' => 'Use this account number for testing PayMaya payments'
            ],
            'card' => [
                'number' => '4242424242424242',
                'expiry' => '12/25',
                'cvc' => '123',
                'name' => 'Test Card Account',
                'instructions' => 'Use these card details for testing card payments'
            ]
        ];

        // Get available payment methods
        $available_methods = explode(',', $event['payment_methods']);
        $payment_methods = [];
        foreach ($available_methods as $method) {
            if (isset($test_accounts[$method])) {
                $payment_methods[$method] = $test_accounts[$method];
            }
        }

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'payment_required' => true,
            'event' => [
                'id' => $event['id'],
                'title' => $event['title'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'venue' => $event['location']
            ],
            'payment' => [
                'amount' => $event['payment_amount'],
                'methods' => $payment_methods
            ]
        ]);
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'payment_required' => false,
            'event' => [
                'id' => $event['id'],
                'title' => $event['title'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'venue' => $event['location']
            ],
            'payment' => [
                'amount' => $event['payment_amount']
            ]
        ]);
    }

} catch (Exception $e) {
    ob_end_clean();
    // Catch any exceptions and return a JSON error response
    error_log("Error in check-payment.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
} 