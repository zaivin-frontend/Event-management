<?php
session_start();
require_once '../../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}

// Check if payment ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage-payments.php");
    exit();
}

$payment_id = $_GET['id'];

// Get payment details
$query = "SELECT ep.*, e.title as event_title, e.description as event_description,
          u.first_name, u.last_name, u.email as student_email,
          DATE_FORMAT(ep.created_at, '%Y-%m-%d %H:%i') as payment_date,
          DATE_FORMAT(ep.updated_at, '%Y-%m-%d %H:%i') as last_updated
          FROM event_payments ep
          JOIN events e ON ep.event_id = e.id
          JOIN users u ON ep.user_id = u.id
          WHERE ep.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();

if (!$payment) {
    header("Location: manage-payments.php");
    exit();
}

// Get payment verification history
$query = "SELECT pv.*, u.first_name, u.last_name,
          DATE_FORMAT(pv.created_at, '%Y-%m-%d %H:%i') as verification_date
          FROM payment_verifications pv
          JOIN users u ON pv.verified_by = u.id
          WHERE pv.payment_id = ?
          ORDER BY pv.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$verifications = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .payment-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 500;
        }

        .verification-history {
            max-height: 300px;
            overflow-y: auto;
        }

        .verification-item {
            border-left: 4px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }

        .verification-item.approved {
            border-left-color: #198754;
        }

        .verification-item.rejected {
            border-left-color: #dc3545;
        }

        .back-btn {
            background: #044721;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #033318;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Payment Details</h2>
            <a href="manage-payments.php" class="back-btn">
                <i class="bi bi-arrow-left me-2"></i>Back to Payments
            </a>
        </div>

        <div class="row">
            <!-- Payment Information -->
            <div class="col-md-8">
                <div class="payment-card card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">Payment Information</h4>
                            <span class="status-badge bg-<?php 
                                echo $payment['status'] === 'approved' ? 'success' : 
                                    ($payment['status'] === 'rejected' ? 'danger' : 'warning'); 
                            ?> text-white">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Event</p>
                                <h5><?php echo htmlspecialchars($payment['event_title']); ?></h5>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Amount</p>
                                <h5>₱<?php echo number_format($payment['amount'], 2); ?></h5>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Payment Method</p>
                                <h5><?php echo htmlspecialchars($payment['payment_method']); ?></h5>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Reference Number</p>
                                <h5><?php echo htmlspecialchars($payment['reference_number']); ?></h5>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Payment Date</p>
                                <h5><?php echo $payment['payment_date']; ?></h5>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Last Updated</p>
                                <h5><?php echo $payment['last_updated']; ?></h5>
                            </div>
                        </div>

                        <?php if ($payment['status'] === 'pending'): ?>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success" onclick="approvePayment(<?php echo $payment['id']; ?>)">
                                    <i class="bi bi-check me-2"></i>Approve Payment
                                </button>
                                <button class="btn btn-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)">
                                    <i class="bi bi-x me-2"></i>Reject Payment
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="payment-card card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Student Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Name</p>
                                <h5><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></h5>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted">Email</p>
                                <h5><?php echo htmlspecialchars($payment['student_email']); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification History -->
            <div class="col-md-4">
                <div class="payment-card card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Verification History</h4>
                        <div class="verification-history">
                            <?php if (empty($verifications)): ?>
                                <p class="text-muted">No verification history available.</p>
                            <?php else: ?>
                                <?php foreach ($verifications as $verification): ?>
                                    <div class="verification-item <?php echo $verification['status']; ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?>
                                            </h6>
                                            <small class="text-muted"><?php echo $verification['verification_date']; ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?php 
                                                echo $verification['status'] === 'approved' ? 'success' : 'danger'; 
                                            ?>">
                                                <?php echo ucfirst($verification['status']); ?>
                                            </span>
                                        </p>
                                        <?php if ($verification['notes']): ?>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($verification['notes']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approvePayment(paymentId) {
            if (!confirm('Are you sure you want to approve this payment?')) {
                return;
            }
            
            fetch('../../api/approve-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment approved successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to approve payment. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to approve payment. Please try again later.');
            });
        }

        function rejectPayment(paymentId) {
            if (!confirm('Are you sure you want to reject this payment?')) {
                return;
            }
            
            fetch('../../api/reject-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment rejected successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to reject payment. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to reject payment. Please try again later.');
            });
        }
    </script>
</body>
</html> 