<?php
session_start();
require_once '../database/config.php';

// Initialize response array
$response = [
    'success' => false,
    'events' => [],
    'categories' => [],
    'error' => null
];

try {
    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    // Get all categories
    $stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $response['categories'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Build the events query
    $query = "SELECT e.*, 
                     CASE 
                         WHEN e.start_date > NOW() THEN 'upcoming'
                         WHEN e.end_date < NOW() THEN 'completed'
                         ELSE 'ongoing'
                     END as event_timing,
                     c.name as category_name,
                     (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registration_count
              FROM events e
              LEFT JOIN event_categories ec ON e.id = ec.event_id
              LEFT JOIN categories c ON ec.category_id = c.id
              WHERE 1=1";
    $params = [];
    $types = "";

    // Add search condition
    if (!empty($search)) {
        $query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    // Add category filter
    if (!empty($category)) {
        $query .= " AND e.id IN (SELECT event_id FROM event_categories WHERE category_id = ?)";
        $params[] = $category;
        $types .= "i";
    }

    // Add status filter
    if (!empty($status)) {
        switch ($status) {
            case 'upcoming':
                $query .= " AND e.start_date > NOW()";
                break;
            case 'ongoing':
                $query .= " AND e.start_date <= NOW() AND e.end_date >= NOW()";
                break;
            case 'completed':
                $query .= " AND e.end_date < NOW()";
                break;
        }
    }

    // Add sorting
    $query .= " ORDER BY e.start_date ASC";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process events
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates
        $row['formatted_start_date'] = date('M d, Y', strtotime($row['start_date']));
        $row['formatted_end_date'] = date('M d, Y', strtotime($row['end_date']));
        
        // Truncate description
        $row['short_description'] = substr($row['description'], 0, 100) . '...';
        
        // Set default image if none exists
        if (empty($row['image_url'])) {
            $row['image_url'] = '../assets/images/event-default.jpg';
        }
        
        $events[] = $row;
    }
    
    $response['events'] = $events;
    $response['success'] = true;

} catch (Exception $e) {
    error_log("Error in handleView.php: " . $e->getMessage());
    $response['error'] = "An error occurred while fetching events. Please try again later.";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
