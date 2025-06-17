<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $student_id = $_SESSION['user_id'];
    $response = [];

    // Get student profile information
    $stmt = $conn->prepare("
        SELECT first_name, last_name, email, phone, department, year_level
        FROM users 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['profile'] = $result->fetch_assoc();

    // Get upcoming events
    $stmt = $conn->prepare("
        SELECT e.*, 
               CASE 
                   WHEN r.id IS NOT NULL THEN 'registered'
                   WHEN e.registration_deadline < NOW() THEN 'closed'
                   ELSE 'open'
               END as registration_status
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id AND r.user_id = ?
        WHERE e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['upcoming_events'] = [];
    while ($row = $result->fetch_assoc()) {
        $row['event_date'] = date('F j, Y', strtotime($row['event_date']));
        $row['registration_deadline'] = date('F j, Y', strtotime($row['registration_deadline']));
        $response['upcoming_events'][] = $row;
    }

    // Get recent registrations
    $stmt = $conn->prepare("
        SELECT r.*, e.title as event_title, e.event_date
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.user_id = ?
        ORDER BY r.registration_date DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['recent_registrations'] = [];
    while ($row = $result->fetch_assoc()) {
        $row['registration_date'] = date('F j, Y', strtotime($row['registration_date']));
        $row['event_date'] = date('F j, Y', strtotime($row['event_date']));
        $response['recent_registrations'][] = $row;
    }

    // Get registration statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_registrations,
            SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as active_registrations,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_registrations
        FROM registrations
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['statistics'] = $result->fetch_assoc();

    // Get available events for registration
    $stmt = $conn->prepare("
        SELECT e.*
        FROM events e
        LEFT JOIN registrations r ON e.id = r.event_id AND r.user_id = ?
        WHERE e.registration_deadline >= CURDATE()
        AND r.id IS NULL
        ORDER BY e.registration_deadline ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['available_events'] = [];
    while ($row = $result->fetch_assoc()) {
        $row['event_date'] = date('F j, Y', strtotime($row['event_date']));
        $row['registration_deadline'] = date('F j, Y', strtotime($row['registration_deadline']));
        $response['available_events'][] = $row;
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error in handleStudents.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching data'
    ]);
}
?>
