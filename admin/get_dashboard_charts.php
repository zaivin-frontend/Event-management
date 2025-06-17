<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once '../database/config.php';

// Get registration trends data
function getRegistrationTrends($conn) {
    $query = "SELECT 
                DATE_FORMAT(registration_date, '%Y-%m') as month,
                COUNT(*) as count
              FROM event_registrations
              WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY month
              ORDER BY month ASC";
              
    $result = $conn->query($query);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
        $data['values'][] = (int)$row['count'];
    }
    
    return $data;
}

// Get event categories data
function getEventCategories($conn) {
    $query = "SELECT 
                e.category,
                COUNT(DISTINCT er.id) as count
              FROM events e
              LEFT JOIN event_registrations er ON e.id = er.event_id
              WHERE e.start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY e.category
              ORDER BY count DESC";
              
    $result = $conn->query($query);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data['labels'][] = ucfirst($row['category']);
        $data['values'][] = (int)$row['count'];
    }
    
    return $data;
}

// Prepare response data
$response = [
    'registrations' => getRegistrationTrends($conn),
    'categories' => getEventCategories($conn)
];

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 