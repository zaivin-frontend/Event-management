<?php
error_log("get_analytics_data.php: Script execution started."); // Diagnostic log
session_start();

// Ensure output is JSON in case of early exit or error
header('Content-Type: application/json');

try {
    // Check if user is logged in and is an admin
    if (!isset($_SESSION['admin_id'])) {
        // Send a 401 Unauthorized status code and throw an exception
        http_response_code(401);
        throw new Exception('Unauthorized access.');
    }
    error_log("get_analytics_data.php: Admin session confirmed."); // Diagnostic log

    // Include database connection
    require_once '../../database/config.php';

    // Verify database connection immediately
    if (!$conn) {
        // Log error and throw exception for database connection failure
        error_log("get_analytics_data.php: Database connection failed.");
        throw new Exception('Database connection failed.');
    }
    error_log("get_analytics_data.php: Database connected successfully."); // Diagnostic log

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Throw exception for invalid JSON input
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    $dateRange = $data['date_range'] ?? '30';
    $startDate = $data['start_date'] ?? null;
    $endDate = $data['end_date'] ?? null;
    error_log("get_analytics_data.php: Received date range: " . $dateRange . ", start: " . ($startDate ?? 'N/A') . ", end: " . ($endDate ?? 'N/A')); // Diagnostic log

    // Calculate date range
    if ($dateRange === 'custom' && $startDate && $endDate) {
        $start = date('Y-m-d 00:00:00', strtotime($startDate));
        $end = date('Y-m-d 23:59:59', strtotime($endDate));
    } else {
        $end = date('Y-m-d 23:59:59');
        $start = date('Y-m-d 00:00:00', strtotime("-{$dateRange} days"));
    }
    error_log("get_analytics_data.php: Calculated date range: " . $start . " to " . $end); // Diagnostic log

    // Get metrics data
    function getMetrics($conn, $start, $end) {
        try {
            // Get total events
            $query = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN start_date >= NOW() THEN 1 END) as active_events
                      FROM events 
                      WHERE created_at BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing events query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing events query: " . $stmt->error);
            }
            $result = $stmt->get_result()->fetch_assoc();
            $totalEvents = $result['total'] ?? 0;
            $activeEvents = $result['active_events'] ?? 0;

            // Get total users
            $query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing users query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing users query: " . $stmt->error);
            }
            $totalUsers = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

            // Get total registrations
            $query = "SELECT 
                        COUNT(*) as total,
                        COUNT(DISTINCT user_id) as unique_users
                      FROM event_registrations 
                      WHERE registration_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing registrations query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing registrations query: " . $stmt->error);
            }
            $result = $stmt->get_result()->fetch_assoc();
            $totalRegistrations = $result['total'] ?? 0;
            $uniqueUsers = $result['unique_users'] ?? 0;

            // Get average attendance
            $query = "SELECT 
                        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as avg_attendance
                      FROM event_registrations
                      WHERE registration_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing attendance query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing attendance query: " . $stmt->error);
            }
            $avgAttendance = round($stmt->get_result()->fetch_assoc()['avg_attendance'] ?? 0, 1);

            // Calculate trends
            $previousStart = date('Y-m-d 00:00:00', strtotime($start . ' -' . (strtotime($end) - strtotime($start)) . ' days'));
            $previousEnd = date('Y-m-d 23:59:59', strtotime($start . ' -1 day'));

            // Get previous period metrics
            $query = "SELECT COUNT(*) as total FROM events WHERE created_at BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing previous events query: " . $conn->error);
            }
            $stmt->bind_param("ss", $previousStart, $previousEnd);
            if (!$stmt->execute()) {
                throw new Exception("Error executing previous events query: " . $stmt->error);
            }
            $prevEvents = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

            $query = "SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing previous users query: " . $conn->error);
            }
            $stmt->bind_param("ss", $previousStart, $previousEnd);
            if (!$stmt->execute()) {
                throw new Exception("Error executing previous users query: " . $stmt->error);
            }
            $prevUsers = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

            $query = "SELECT COUNT(*) as total FROM event_registrations WHERE registration_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing previous registrations query: " . $conn->error);
            }
            $stmt->bind_param("ss", $previousStart, $previousEnd);
            if (!$stmt->execute()) {
                throw new Exception("Error executing previous registrations query: " . $stmt->error);
            }
            $prevRegistrations = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

            $query = "SELECT 
                        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as avg_attendance
                      FROM event_registrations
                      WHERE registration_date BETWEEN ? AND ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing previous attendance query: " . $conn->error);
            }
            $stmt->bind_param("ss", $previousStart, $previousEnd);
            if (!$stmt->execute()) {
                throw new Exception("Error executing previous attendance query: " . $stmt->error);
            }
            $prevAttendance = round($stmt->get_result()->fetch_assoc()['avg_attendance'] ?? 0, 1);

            // Calculate trends
            $eventsTrend = $prevEvents ? round(($totalEvents - $prevEvents) * 100 / $prevEvents) : 0;
            $usersTrend = $prevUsers ? round(($totalUsers - $prevUsers) * 100 / $prevUsers) : 0;
            $registrationsTrend = $prevRegistrations ? round(($totalRegistrations - $prevRegistrations) * 100 / $prevRegistrations) : 0;
            $attendanceTrend = $prevAttendance ? round(($avgAttendance - $prevAttendance) * 100 / $prevAttendance) : 0;

            return [
                'total_events' => $totalEvents,
                'active_events' => $activeEvents,
                'total_users' => $totalUsers,
                'total_registrations' => $totalRegistrations,
                'unique_users' => $uniqueUsers,
                'avg_attendance' => $avgAttendance,
                'events_trend' => $eventsTrend,
                'users_trend' => $usersTrend,
                'registrations_trend' => $registrationsTrend,
                'attendance_trend' => $attendanceTrend
            ];
        } catch (Exception $e) {
            error_log("Error in getMetrics: " . $e->getMessage());
            throw $e;
        }
    }

    // Get registration trends data
    function getRegistrationTrends($conn, $start, $end) {
        try {
            $query = "SELECT 
                        DATE_FORMAT(registration_date, '%Y-%m-%d') as date,
                        COUNT(*) as count
                      FROM event_registrations
                      WHERE registration_date BETWEEN ? AND ?
                      GROUP BY date
                      ORDER BY date ASC";
                      
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing registration trends query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing registration trends query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            
            $data = ['labels' => [], 'values' => []];
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = date('M d', strtotime($row['date']));
                $data['values'][] = (int)$row['count'];
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getRegistrationTrends: " . $e->getMessage());
            throw $e;
        }
    }

    // Get event categories data
    function getEventCategories($conn, $start, $end) {
        try {
            $query = "SELECT 
                        c.name as category,
                        COUNT(DISTINCT e.id) as event_count,
                        COUNT(DISTINCT er.id) as registration_count
                      FROM events e
                      LEFT JOIN event_categories ec ON e.id = ec.event_id
                      LEFT JOIN categories c ON ec.category_id = c.id
                      LEFT JOIN event_registrations er ON e.id = er.event_id
                      WHERE e.start_date BETWEEN ? AND ?
                      GROUP BY c.name
                      ORDER BY registration_count DESC";
                      
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing event categories query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing event categories query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            
            $data = ['labels' => [], 'values' => []];
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = ucfirst($row['category'] ?? 'Uncategorized');
                $data['values'][] = (int)$row['registration_count'];
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getEventCategories: " . $e->getMessage());
            throw $e;
        }
    }

    // Get user engagement data
    function getUserEngagement($conn, $start, $end) {
        try {
            $query = "SELECT 
                        DATE_FORMAT(registration_date, '%Y-%m-%d') as date,
                        COUNT(DISTINCT user_id) as count
                      FROM event_registrations
                      WHERE registration_date BETWEEN ? AND ?
                      GROUP BY date
                      ORDER BY date ASC";
                      
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing user engagement query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing user engagement query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            
            $data = ['labels' => [], 'values' => []];
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = date('M d', strtotime($row['date']));
                $data['values'][] = (int)$row['count'];
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getUserEngagement: " . $e->getMessage());
            throw $e;
        }
    }

    // Get registration status data
    function getRegistrationStatus($conn, $start, $end) {
        try {
            $query = "SELECT 
                        status,
                        COUNT(*) as count
                      FROM event_registrations
                      WHERE registration_date BETWEEN ? AND ?
                      GROUP BY status
                      ORDER BY count DESC";
                      
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparing registration status query: " . $conn->error);
            }
            $stmt->bind_param("ss", $start, $end);
            if (!$stmt->execute()) {
                throw new Exception("Error executing registration status query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            
            $data = ['labels' => [], 'values' => []];
            while ($row = $result->fetch_assoc()) {
                $data['labels'][] = ucfirst($row['status']);
                $data['values'][] = (int)$row['count'];
            }
            
            return $data;
        } catch (Exception $e) {
            error_log("Error in getRegistrationStatus: " . $e->getMessage());
            throw $e;
        }
    }

    // Prepare response data
    $response = [
        'metrics' => getMetrics($conn, $start, $end),
        'charts' => [
            'registration_trends' => getRegistrationTrends($conn, $start, $end),
            'event_categories' => getEventCategories($conn, $start, $end),
            'user_engagement' => getUserEngagement($conn, $start, $end),
            'registration_status' => getRegistrationStatus($conn, $start, $end)
        ]
    ];
    error_log("get_analytics_data.php: Data prepared, sending response."); // Diagnostic log
    echo json_encode($response);
    die(); // Ensure no further output

} catch (Exception $e) {
    // Log the error with file and line number for debugging
    error_log("Analytics data generation error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    // Send a 500 Internal Server Error status code if not already sent by http_response_code(401) earlier
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['error' => 'Failed to load analytics data: ' . $e->getMessage()]);
    die(); // Ensure no further output
} catch (Error $e) { // Catch fatal errors (e.g., parse errors, type errors) in PHP 7+
    error_log("Analytics data fatal error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo json_encode(['error' => 'A critical server error occurred: ' . $e->getMessage()]);
    die(); // Ensure no further output
} 