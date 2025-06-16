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
$query = "SELECT ep.*, e.title as event_title, u.first_name, u.last_name, u.email as student_email,
          DATE_FORMAT(ep.created_at, '%Y-%m-%d %H:%i') as payment_date,
          DATE_FORMAT(ep.updated_at, '%Y-%m-%d %H:%i') as last_updated
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

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="payments_export_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Output Excel content
echo "Payment Export - " . date('Y-m-d H:i:s') . "\n\n";

// Headers
echo implode("\t", [
    'Event',
    'Student Name',
    'Student Email',
    'Amount',
    'Payment Method',
    'Reference Number',
    'Status',
    'Payment Date',
    'Last Updated'
]) . "\n";

// Data rows
foreach ($payments as $payment) {
    echo implode("\t", [
        $payment['event_title'],
        $payment['first_name'] . ' ' . $payment['last_name'],
        $payment['student_email'],
        number_format($payment['amount'], 2),
        $payment['payment_method'],
        $payment['reference_number'],
        $payment['status'],
        $payment['payment_date'],
        $payment['last_updated']
    ]) . "\n";
}

// Add summary
echo "\n\nSummary\n";
echo "Total Payments: " . count($payments) . "\n";

$total_amount = 0;
$pending_amount = 0;
$approved_amount = 0;
$rejected_amount = 0;

foreach ($payments as $payment) {
    $total_amount += $payment['amount'];
    if ($payment['status'] === 'pending') {
        $pending_amount += $payment['amount'];
    } elseif ($payment['status'] === 'approved') {
        $approved_amount += $payment['amount'];
    } elseif ($payment['status'] === 'rejected') {
        $rejected_amount += $payment['amount'];
    }
}

echo "Total Amount: ₱" . number_format($total_amount, 2) . "\n";
echo "Pending Amount: ₱" . number_format($pending_amount, 2) . "\n";
echo "Approved Amount: ₱" . number_format($approved_amount, 2) . "\n";
echo "Rejected Amount: ₱" . number_format($rejected_amount, 2) . "\n"; 