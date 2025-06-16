<?php
session_start();
require_once '../../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT ep.*, e.title as event_title, u.first_name, u.last_name,
          DATE_FORMAT(ep.created_at, '%Y-%m-%d %H:%i') as payment_date
          FROM event_payments ep
          JOIN events e ON ep.event_id = e.id
          JOIN users u ON ep.user_id = u.id
          WHERE 1=1";

$params = [];
$types = "";

if ($status !== 'all') {
    $query .= " AND ep.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($payment_method !== 'all') {
    $query .= " AND ep.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

if ($date_from) {
    $query .= " AND DATE(ep.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $query .= " AND DATE(ep.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY ep.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);

// Get payment statistics
$stats = [
    'total_amount' => 0,
    'pending_amount' => 0,
    'approved_amount' => 0,
    'rejected_amount' => 0
];

foreach ($payments as $payment) {
    $stats['total_amount'] += $payment['amount'];
    if ($payment['status'] === 'pending') {
        $stats['pending_amount'] += $payment['amount'];
    } elseif ($payment['status'] === 'approved') {
        $stats['approved_amount'] += $payment['amount'];
    } elseif ($payment['status'] === 'rejected') {
        $stats['rejected_amount'] += $payment['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }

        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .payment-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .payment-table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 500;
        }

        .export-btn {
            background: #044721;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: #033318;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Payment Management</h2>
            <button class="export-btn" onclick="exportPayments()">
                <i class="bi bi-download me-2"></i>Export to Excel
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Payments</h6>
                        <h3 class="mb-0">₱<?php echo number_format($stats['total_amount'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="card-title">Pending Payments</h6>
                        <h3 class="mb-0">₱<?php echo number_format($stats['pending_amount'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-success text-white">
                    <div class="card-body">
                        <h6 class="card-title">Approved Payments</h6>
                        <h3 class="mb-0">₱<?php echo number_format($stats['approved_amount'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title">Rejected Payments</h6>
                        <h3 class="mb-0">₱<?php echo number_format($stats['rejected_amount'], 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="all" <?php echo $payment_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                        <option value="gcash" <?php echo $payment_method === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                        <option value="paymaya" <?php echo $payment_method === 'paymaya' ? 'selected' : ''; ?>>PayMaya</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="manage-payments.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="table-responsive">
            <table class="table payment-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['event_title']); ?></td>
                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                            <td><?php echo $payment['payment_date']; ?></td>
                            <td>
                                <span class="status-badge bg-<?php 
                                    echo $payment['status'] === 'approved' ? 'success' : 
                                        ($payment['status'] === 'rejected' ? 'danger' : 'warning'); 
                                ?> text-white">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <button class="btn btn-outline-success" onclick="approvePayment(<?php echo $payment['id']; ?>)">
                                            <i class="bi bi-check"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary" onclick="viewPayment(<?php echo $payment['id']; ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

        function viewPayment(paymentId) {
            window.location.href = `view-payment.php?id=${paymentId}`;
        }

        function exportPayments() {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            window.location.href = `export-payments.php?${params.toString()}`;
        }
    </script>
</body>
</html> 