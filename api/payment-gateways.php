<?php
require_once '../database/config.php';
require_once '../vendor/autoload.php';

use Adyen\Client;
use Adyen\Environment;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\CheckoutPaymentMethod;
use Adyen\Model\Checkout\PaymentRequest;
use Adyen\Service\Checkout\PaymentsApi;
use GuzzleHttp\Client as GuzzleClient;

class PaymentGateway {
    private $conn;
    private $adyen_client;
    private $adyen_merchant_account;
    private $paymaya_client;
    private $paymaya_secret_key;
    private $is_test_mode;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->is_test_mode = true; // Set to false in production
        
        // Initialize Adyen client
        $this->adyen_client = new Client();
        $this->adyen_client->setXApiKey(getenv('ADYEN_API_KEY') ?: 'AQE1hmfxL4PJahZCw0m/n3Q5qf3VaY9UCJ1+XWZe9W27jmlZiv4PD4jhfNMofnLr2K5i8/0QwV1bDb7kfNy1WIxIIkxgBw==-lUKXT9IQ5GZ6d6RH4nnuOG4Bu//eJZxvoBAkXqgRH2I=-lUKXT9IQ5GZ6d6RH4nnuOG4Bu//eJZxvoBAkXqgRH2I=');
        $this->adyen_client->setEnvironment($this->is_test_mode ? Environment::TEST : Environment::LIVE);
        
        $this->adyen_merchant_account = getenv('ADYEN_MERCHANT_ACCOUNT') ?: 'TestMerchant';

        // Initialize PayMaya client
        $this->paymaya_secret_key = getenv('PAYMAYA_SECRET_KEY') ?: 'sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcwMYk39jInSl';
        $this->paymaya_client = new GuzzleClient([
            'base_uri' => $this->is_test_mode ? 'https://pg-sandbox.paymaya.com' : 'https://pg.paymaya.com',
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'authorization' => 'Basic ' . base64_encode($this->paymaya_secret_key . ':')
            ]
        ]);
    }

    public function processPayment($amount, $user_id, $event_id, $payment_method, $payment_details) {
        switch ($payment_method) {
            case 'gcash':
                return $this->processGCashPayment($amount, $user_id, $event_id, $payment_details['account_number']);
            case 'paymaya':
                return $this->processPayMayaPayment($amount, $user_id, $event_id, $payment_details['account_number']);
            case 'card':
                return $this->processCardPayment($amount, $user_id, $event_id, $payment_details);
            default:
                throw new Exception('Invalid payment method');
        }
    }

    public function processGCashPayment($amount, $user_id, $event_id, $account_number) {
        try {
            if ($this->is_test_mode) {
                // Validate test account
                if ($account_number !== '09123456789') {
                    throw new Exception('Invalid test account number');
                }

                // Generate reference number
                $reference_number = 'GCASH' . time() . rand(1000, 9999);

                // Create Adyen payment request
                $amount_obj = new Amount();
                $amount_obj->setCurrency("PHP")
                          ->setValue(intval($amount * 100)); // Convert to cents

                $payment_method = new CheckoutPaymentMethod();
                $payment_method->setType("gcash");

                $payment_request = new PaymentRequest();
                $payment_request->setReference($reference_number)
                              ->setAmount($amount_obj)
                              ->setMerchantAccount($this->adyen_merchant_account)
                              ->setPaymentMethod($payment_method)
                              ->setReturnUrl('https://' . $_SERVER['HTTP_HOST'] . '/api/verify-payment-redirect.php?reference=' . $reference_number);

                // Send payment request
                $service = new PaymentsApi($this->adyen_client);
                $response = $service->payments($payment_request, ['idempotencyKey' => uniqid()]);

                // Store payment reference in database
                $stmt = $this->conn->prepare("
                    INSERT INTO event_payments (
                        event_id, user_id, payment_method, reference_number,
                        amount, status, payment_details, created_at
                    ) VALUES (?, ?, 'gcash', ?, ?, 'pending', ?, NOW())
                ");
                
                $payment_details = json_encode([
                    'number' => $account_number,
                    'adyen_response' => $response
                ]);
                $stmt->bind_param("iidss", $event_id, $user_id, $reference_number, $amount, $payment_details);
                $stmt->execute();

                // Check if redirect is required
                if ($response['resultCode'] === 'RedirectShopper' && isset($response['action'])) {
                    return [
                        'success' => true,
                        'message' => 'Redirect required for payment',
                        'reference_number' => $reference_number,
                        'redirect' => [
                            'url' => $response['action']['url'],
                            'method' => $response['action']['method'],
                            'type' => $response['action']['type']
                        ]
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Payment initiated',
                    'reference_number' => $reference_number,
                    'payment_url' => $response['action']['url'] ?? null
                ];
            }

            throw new Exception('Payment processing not configured for production');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process GCash payment: ' . $e->getMessage()
            ];
        }
    }

    public function processPayMayaPayment($amount, $user_id, $event_id, $account_number) {
        try {
            if ($this->is_test_mode) {
                // Validate test account
                if ($account_number !== '09187654321') {
                    throw new Exception('Invalid test account number');
                }

                // Generate reference number
                $reference_number = 'PAYMAYA' . time() . rand(1000, 9999);

                // Create PayMaya checkout request
                $checkout_data = [
                    'totalAmount' => [
                        'amount' => $amount,
                        'currency' => 'PHP'
                    ],
                    'requestReferenceNumber' => $reference_number,
                    'items' => [
                        [
                            'name' => 'Event Registration',
                            'quantity' => 1,
                            'code' => 'EVENT-' . $event_id,
                            'amount' => [
                                'value' => $amount,
                                'currency' => 'PHP'
                            ],
                            'totalAmount' => [
                                'value' => $amount,
                                'currency' => 'PHP'
                            ]
                        ]
                    ],
                    'redirectUrl' => [
                        'success' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/verify-payment-redirect.php?reference=' . $reference_number,
                        'failure' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/failed?reference=' . $reference_number,
                        'cancel' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/cancelled?reference=' . $reference_number
                    ],
                    'buyer' => [
                        'contact' => [
                            'phone' => $account_number,
                            'email' => $_SESSION['user_email'] ?? ''
                        ]
                    ]
                ];

                // Send checkout request to PayMaya
                $response = $this->paymaya_client->request('POST', '/checkout/v1/checkouts', [
                    'json' => $checkout_data
                ]);

                $result = json_decode($response->getBody(), true);

                // Store payment reference in database
                $stmt = $this->conn->prepare("
                    INSERT INTO event_payments (
                        event_id, user_id, payment_method, reference_number,
                        amount, status, payment_details, created_at
                    ) VALUES (?, ?, 'paymaya', ?, ?, 'pending', ?, NOW())
                ");
                
                $payment_details = json_encode([
                    'number' => $account_number,
                    'paymaya_response' => $result
                ]);
                $stmt->bind_param("iidss", $event_id, $user_id, $reference_number, $amount, $payment_details);
                $stmt->execute();

                return [
                    'success' => true,
                    'message' => 'Payment initiated',
                    'reference_number' => $reference_number,
                    'checkout_url' => $result['redirectUrl'] ?? null
                ];
            }

            throw new Exception('Payment processing not configured for production');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process PayMaya payment: ' . $e->getMessage()
            ];
        }
    }

    public function processCardPayment($amount, $user_id, $event_id, $card_details) {
        try {
            if ($this->is_test_mode) {
                // Validate test card details
                if (!$this->validateTestCard($card_details)) {
                    throw new Exception('Invalid card details');
                }

                // Generate reference number
                $reference_number = 'CARD' . time() . rand(1000, 9999);

                // Create Adyen payment request
                $amount_obj = new Amount();
                $amount_obj->setCurrency("PHP")
                          ->setValue(intval($amount * 100)); // Convert to cents

                $payment_method = new CheckoutPaymentMethod();
                $payment_method->setType("scheme")
                             ->setNumber($card_details['number'])
                             ->setExpiryMonth(substr($card_details['expiry'], 0, 2))
                             ->setExpiryYear('20' . substr($card_details['expiry'], 3, 2))
                             ->setCvc($card_details['cvc']);

                $payment_request = new PaymentRequest();
                $payment_request->setReference($reference_number)
                              ->setAmount($amount_obj)
                              ->setMerchantAccount($this->adyen_merchant_account)
                              ->setPaymentMethod($payment_method)
                              ->setReturnUrl('https://' . $_SERVER['HTTP_HOST'] . '/api/verify-payment-redirect.php?reference=' . $reference_number);

                // Send payment request
                $service = new PaymentsApi($this->adyen_client);
                $response = $service->payments($payment_request, ['idempotencyKey' => uniqid()]);
                
                // Store payment reference in database
                $stmt = $this->conn->prepare("
                    INSERT INTO event_payments (
                        event_id, user_id, payment_method, reference_number,
                        amount, status, payment_details, created_at
                    ) VALUES (?, ?, 'card', ?, ?, 'pending', ?, NOW())
                ");
                
                $payment_details = json_encode([
                    'number' => $card_details['number'],
                    'last4' => substr($card_details['number'], -4),
                    'adyen_response' => $response
                ]);
                $stmt->bind_param("iidss", $event_id, $user_id, $reference_number, $amount, $payment_details);
                $stmt->execute();

                return [
                    'success' => true,
                    'message' => 'Payment initiated',
                    'reference_number' => $reference_number,
                    'result' => $response
                ];
            }

            throw new Exception('Payment processing not configured for production');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process card payment: ' . $e->getMessage()
            ];
        }
    }

    private function validateTestCard($card_details) {
        // Basic validation for test card
        $number = str_replace(' ', '', $card_details['number']);
        $expiry = explode('/', $card_details['expiry']);
        
        // Check if card number is valid test number
        if ($number !== '4242424242424242') {
            return false;
        }

        // Check if expiry date is valid
        if (count($expiry) !== 2 || 
            !is_numeric($expiry[0]) || 
            !is_numeric($expiry[1]) || 
            $expiry[0] < 1 || 
            $expiry[0] > 12) {
            return false;
        }

        // Check if CVC is valid
        if (!is_numeric($card_details['cvc']) || 
            strlen($card_details['cvc']) !== 3) {
            return false;
        }

        return true;
    }

    public function handleWebhook($payload, $signature, $source) {
        try {
            if ($source === 'adyen') {
                // Process Adyen webhook
                $data = json_decode($payload, true);
                $notification = $data['notificationItems'][0]['NotificationRequestItem'] ?? null;

                if ($notification) {
                    $reference = $notification['merchantReference'];
                    $status = $notification['eventCode'] === 'AUTHORISATION' ? 'completed' : 'failed';

                    // Update payment status
                    $stmt = $this->conn->prepare("
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
                        $this->updateRegistrationStatus($reference);
                    }

                    return ['success' => true];
                }
            } elseif ($source === 'paymaya') {
                // Process PayMaya webhook
                $data = json_decode($payload, true);
                $reference = $data['requestReferenceNumber'] ?? null;
                $status = $data['status'] === 'COMPLETED' ? 'completed' : 'failed';

                if ($reference) {
                    // Update payment status
                    $stmt = $this->conn->prepare("
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
                        $this->updateRegistrationStatus($reference);
                    }

                    return ['success' => true];
                }
            }

            throw new Exception('Invalid webhook source');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Webhook processing failed: ' . $e->getMessage()
            ];
        }
    }

    private function updateRegistrationStatus($reference) {
        // Get payment details
        $stmt = $this->conn->prepare("
            SELECT event_id, user_id 
            FROM event_payments 
            WHERE reference_number = ?
        ");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();

        if ($payment) {
            // Update registration status
            $stmt = $this->conn->prepare("
                UPDATE event_registrations 
                SET status = 'confirmed' 
                WHERE event_id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $payment['event_id'], $payment['user_id']);
            $stmt->execute();
        }
    }

    public function handlePaymentRedirect($reference_number, $redirect_result) {
        try {
            // Verify payment details
            $verification = $this->verifyPaymentDetails($redirect_result);
            if (!$verification['success']) {
                throw new Exception($verification['message']);
            }

            $result = $verification['result'];
            
            // Check for successful authorization
            if ($result['resultCode'] === 'Authorised') {
                $status = 'completed';
                $psp_reference = $result['pspReference'] ?? null;
                
                // Update payment status with PSP reference
                $stmt = $this->conn->prepare("
                    UPDATE event_payments 
                    SET status = ?, 
                        payment_details = JSON_MERGE_PATCH(payment_details, ?),
                        psp_reference = ?,
                        updated_at = NOW()
                    WHERE reference_number = ?
                ");
                
                $payment_details = json_encode([
                    'adyen_verification' => $result,
                    'redirect_result' => $redirect_result,
                    'psp_reference' => $psp_reference
                ]);
                $stmt->bind_param("ssss", $status, $payment_details, $psp_reference, $reference_number);
                $stmt->execute();

                // Update registration status
                $this->updateRegistrationStatus($reference_number);

                return [
                    'success' => true,
                    'status' => $status,
                    'psp_reference' => $psp_reference,
                    'result' => $result
                ];
            } else {
                // Payment not authorized
                $status = 'failed';
                
                // Update payment status
                $stmt = $this->conn->prepare("
                    UPDATE event_payments 
                    SET status = ?, 
                        payment_details = JSON_MERGE_PATCH(payment_details, ?),
                        updated_at = NOW()
                    WHERE reference_number = ?
                ");
                
                $payment_details = json_encode([
                    'adyen_verification' => $result,
                    'redirect_result' => $redirect_result,
                    'failure_reason' => $result['resultCode']
                ]);
                $stmt->bind_param("sss", $status, $payment_details, $reference_number);
                $stmt->execute();

                return [
                    'success' => true,
                    'status' => $status,
                    'result' => $result
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to handle payment redirect: ' . $e->getMessage()
            ];
        }
    }

    private function verifyPaymentDetails($redirectResult) {
        try {
            // Create details request
            $details = [
                'details' => [
                    'redirectResult' => $redirectResult
                ]
            ];

            // Send details request to Adyen
            $service = new PaymentsApi($this->adyen_client);
            $response = $service->paymentsDetails($details);

            return [
                'success' => true,
                'result' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to verify payment details: ' . $e->getMessage()
            ];
        }
    }
} 