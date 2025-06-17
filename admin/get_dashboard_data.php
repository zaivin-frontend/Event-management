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

// Get date range for statistics
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get statistics
$query = "SELECT 
            COUNT(DISTINCT e.id) as total_events,
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT r.id) as total_registrations,
            COUNT(DISTINCT CASE WHEN e.start_date >= CURDATE() THEN e.id END) as active_events,
            ROUND(AVG(CASE WHEN e.end_date < CURDATE() THEN 
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'confirmed') * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM registrations WHERE event_id = e.id), 0)
            END), 1) as avg_attendance
          FROM events e
          LEFT JOIN users u ON 1=1
          LEFT JOIN registrations r ON 1=1
          WHERE e.created_at BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$statistics = $stmt->get_result()->fetch_assoc();

// Get recent events
$query = "SELECT 
            e.id,
            e.title,
            c.name as category_name,
            e.start_date as formatted_date,
            CASE 
                WHEN e.start_date > CURDATE() THEN 'upcoming'
                WHEN e.end_date < CURDATE() THEN 'completed'
                ELSE 'ongoing'
            END as status,
            (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registrations
          FROM events e
          LEFT JOIN categories c ON e.category_id = c.id
          ORDER BY e.created_at DESC
          LIMIT 5";

$recent_events = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get recent registrations
$query = "SELECT 
            r.id,
            u.name as full_name,
            e.title as event_title,
            r.created_at as formatted_date,
            r.status
          FROM registrations r
          JOIN users u ON r.user_id = u.id
          JOIN events e ON r.event_id = e.id
          ORDER BY r.created_at DESC
          LIMIT 5";

$recent_registrations = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get registration trends
$query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
          FROM registrations
          WHERE created_at BETWEEN ? AND ?
          GROUP BY DATE(created_at)
          ORDER BY date";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$registration_trends = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event categories
$query = "SELECT 
            c.name as category,
            COUNT(e.id) as count
          FROM categories c
          LEFT JOIN events e ON c.id = e.category_id
          GROUP BY c.id, c.name
          ORDER BY count DESC";

$categories = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Calculate trends
$query = "SELECT 
            COUNT(DISTINCT e.id) as current_events,
            COUNT(DISTINCT u.id) as current_users,
            COUNT(DISTINCT r.id) as current_registrations,
            ROUND(AVG(CASE WHEN e.end_date < CURDATE() THEN 
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'confirmed') * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM registrations WHERE event_id = e.id), 0)
            END), 1) as current_attendance
          FROM events e
          LEFT JOIN users u ON 1=1
          LEFT JOIN registrations r ON 1=1
          WHERE e.created_at BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$current_stats = $stmt->get_result()->fetch_assoc();

$query = "SELECT 
            COUNT(DISTINCT e.id) as previous_events,
            COUNT(DISTINCT u.id) as previous_users,
            COUNT(DISTINCT r.id) as previous_registrations,
            ROUND(AVG(CASE WHEN e.end_date < CURDATE() THEN 
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status = 'confirmed') * 100.0 / 
                NULLIF((SELECT COUNT(*) FROM registrations WHERE event_id = e.id), 0)
            END), 1) as previous_attendance
          FROM events e
          LEFT JOIN users u ON 1=1
          LEFT JOIN registrations r ON 1=1
          WHERE e.created_at BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND DATE_SUB(?, INTERVAL 30 DAY)";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$previous_stats = $stmt->get_result()->fetch_assoc();

// Calculate percentage changes
$trends = [
    'events' => $previous_stats['previous_events'] ? 
        round(($current_stats['current_events'] - $previous_stats['previous_events']) * 100 / $previous_stats['previous_events']) : 0,
    'users' => $previous_stats['previous_users'] ? 
        round(($current_stats['current_users'] - $previous_stats['previous_users']) * 100 / $previous_stats['previous_users']) : 0,
    'registrations' => $previous_stats['previous_registrations'] ? 
        round(($current_stats['current_registrations'] - $previous_stats['previous_registrations']) * 100 / $previous_stats['previous_registrations']) : 0,
    'attendance' => $previous_stats['previous_attendance'] ? 
        round(($current_stats['current_attendance'] - $previous_stats['previous_attendance']) * 100 / $previous_stats['previous_attendance']) : 0
];

// Prepare response
$response = [
    'statistics' => $statistics,
    'trends' => $trends,
    'recent_events' => $recent_events,
    'recent_registrations' => $recent_registrations,
    'registration_trends' => [
        'labels' => array_column($registration_trends, 'date'),
        'values' => array_column($registration_trends, 'count')
    ],
    'categories' => [
        'labels' => array_column($categories, 'category'),
        'values' => array_column($categories, 'count')
    ]
];

// Send response
header('Content-Type: application/json');
echo json_encode($response); 