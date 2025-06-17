<?php
require_once '../database/config.php';
require_once '../vendor/autoload.php';
require_once 'payment-gateways.php';

use Adyen\Client;
use Adyen\Environment;
use Adyen\Service\Checkout\PaymentsApi;

// Initialize payment gateway
$paymentGateway = new PaymentGateway($conn);

// Get webhook source from URL
$source = isset($_GET['source']) ? $_GET['source'] : '';

// Get webhook payload
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';

// Log webhook request
error_log("Webhook received from {$source}: " . $payload);

try {
    // Process webhook based on source
    switch ($source) {
        case 'adyen':
            // Initialize Adyen client for webhook verification
            $client = new Client();
            $client->setXApiKey(getenv('ADYEN_API_KEY') ?: 'AQE1hmfxL4PJahZCw0m/n3Q5qf3VaY9UCJ1+XWZe9W27jmlZiv4PD4jhfNMofnLr2K5i8/0QwV1bDb7kfNy1WIxIIkxgBw==-lUKXT9IQ5GZ6d6RH4nnuOG4Bu//eJZxvoBAkXqgRH2I=-lUKXT9IQ5GZ6d6RH4nnuOG4Bu//eJZxvoBAkXqgRH2I=');
            $client->setEnvironment(Environment::TEST);

            // Process Adyen notification
            $data = json_decode($payload, true);
            $notification = $data['notificationItems'][0]['NotificationRequestItem'] ?? null;

            if ($notification) {
                $reference = $notification['merchantReference'];
                $status = $notification['eventCode'] === 'AUTHORISATION' ? 'completed' : 'failed';

                // Update payment status
                $stmt = $conn->prepare("
                    UPDATE event_payments 
                    SET status = ?, 
                        payment_details = JSON_MERGE_PATCH(payment_details, ?),
                        updated_at = NOW()
                    WHERE reference_number = ?
                ");
                
                $payment_details = json_encode(['adyen_notification' => $notification]);
                $stmt->bind_param("sss", $status, $payment_details, $reference);
                $stmt->execute();

                // If payment completed, update registration
                if ($status === 'completed') {
                    $stmt = $conn->prepare("
                        SELECT event_id, user_id 
                        FROM event_payments 
                        WHERE reference_number = ?
                    ");
                    $stmt->bind_param("s", $reference);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $payment = $result->fetch_assoc();

                    if ($payment) {
                        $stmt = $conn->prepare("
                            UPDATE event_registrations 
                            SET status = 'confirmed' 
                            WHERE event_id = ? AND user_id = ?
                        ");
                        $stmt->bind_param("ii", $payment['event_id'], $payment['user_id']);
                        $stmt->execute();
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Notification processed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid notification format']);
            }
            break;

        case 'paymaya':
            // Process PayMaya webhook
            $data = json_decode($payload, true);
            $reference = $data['requestReferenceNumber'] ?? null;
            $status = $data['status'] === 'COMPLETED' ? 'completed' : 'failed';

            if ($reference) {
                // Update payment status
                $stmt = $conn->prepare("
                    UPDATE event_payments 
                    SET status = ?, 
                        payment_details = JSON_MERGE_PATCH(payment_details, ?),
                        updated_at = NOW()
                    WHERE reference_number = ?
                ");
                
                $payment_details = json_encode(['paymaya_notification' => $data]);
                $stmt->bind_param("sss", $status, $payment_details, $reference);
                $stmt->execute();

                // If payment completed, update registration
                if ($status === 'completed') {
                    $stmt = $conn->prepare("
                        SELECT event_id, user_id 
                        FROM event_payments 
                        WHERE reference_number = ?
                    ");
                    $stmt->bind_param("s", $reference);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $payment = $result->fetch_assoc();

                    if ($payment) {
                        $stmt = $conn->prepare("
                            UPDATE event_registrations 
                            SET status = 'confirmed' 
                            WHERE event_id = ? AND user_id = ?
                        ");
                        $stmt->bind_param("ii", $payment['event_id'], $payment['user_id']);
                        $stmt->execute();
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Notification processed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid reference number']);
            }
            break;

        default:
            // Process other payment gateway webhooks
            $result = $paymentGateway->handleWebhook($payload, $signature, $source);
            echo json_encode($result);
    }
} catch (Exception $e) {
    error_log("Webhook processing error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Webhook processing failed: ' . $e->getMessage()
    ]);
}

// Log webhook response
error_log("Webhook response: " . json_encode($result)); 