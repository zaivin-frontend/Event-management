<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once '../../database/config.php';

// Cache configuration
$cache_duration = 300; // 5 minutes in seconds
$cache_file = __DIR__ . '/chart_cache.json';

// Check if cache exists and is valid
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
    header('Content-Type: application/json');
    echo file_get_contents($cache_file);
    exit();
}

// Check database connection
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit();
}

// Check if required tables exist
$required_tables = ['events', 'event_registrations'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    error_log("Missing required tables: " . implode(', ', $missing_tables));
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required tables: ' . implode(', ', $missing_tables)]);
    exit();
}

// Get registration trends data
function getRegistrationTrends($conn) {
    try {
        $query = "SELECT 
                    DATE_FORMAT(registration_date, '%Y-%m-%d') as date,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count
                  FROM event_registrations
                  WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY DATE(registration_date)
                  ORDER BY date ASC";
                  
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Total Registrations',
                    'data' => [],
                    'backgroundColor' => '#044721',
                    'borderColor' => '#044721',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'Confirmed Registrations',
                    'data' => [],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1
                ]
            ]
        ];
        
        // If no data, return empty chart data
        if ($result->num_rows === 0) {
            // Fill with last 30 days of zeros
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
            $current_date = $start_date;
            
            while ($current_date <= $end_date) {
                $data['labels'][] = date('M d', strtotime($current_date));
                $data['datasets'][0]['data'][] = 0;
                $data['datasets'][1]['data'][] = 0;
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            return $data;
        }
        
        while ($row = $result->fetch_assoc()) {
            $data['labels'][] = date('M d', strtotime($row['date']));
            $data['datasets'][0]['data'][] = (int)$row['count'];
            $data['datasets'][1]['data'][] = (int)$row['confirmed_count'];
        }

        // Fill in missing dates with zeros
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        $current_date = $start_date;
        
        while ($current_date <= $end_date) {
            $formatted_date = date('M d', strtotime($current_date));
            if (!in_array($formatted_date, $data['labels'])) {
                $data['labels'][] = $formatted_date;
                $data['datasets'][0]['data'][] = 0;
                $data['datasets'][1]['data'][] = 0;
            }
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        // Sort labels and data
        array_multisort($data['labels'], $data['datasets'][0]['data'], $data['datasets'][1]['data']);
        
        return $data;
    } catch (Exception $e) {
        error_log("Error in getRegistrationTrends: " . $e->getMessage());
        throw $e;
    }
}

// Get event status distribution
function getEventStatusDistribution($conn) {
    try {
        $query = "SELECT 
                    CASE 
                        WHEN start_date > NOW() THEN 'upcoming'
                        WHEN end_date < NOW() THEN 'completed'
                        WHEN status = 'cancelled' THEN 'cancelled'
                        ELSE 'active'
                    END as status,
                    COUNT(*) as count
                  FROM events
                  GROUP BY status
                  ORDER BY count DESC";
                  
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Events',
                    'data' => [],
                    'backgroundColor' => [],
                    'borderColor' => [],
                    'borderWidth' => 1
                ]
            ]
        ];
        
        // If no data, return empty chart data
        if ($result->num_rows === 0) {
            $data['labels'] = ['No Events'];
            $data['datasets'][0]['data'] = [1];
            $data['datasets'][0]['backgroundColor'] = ['#6b7280'];
            $data['datasets'][0]['borderColor'] = ['#6b7280'];
            return $data;
        }
        
        while ($row = $result->fetch_assoc()) {
            $data['labels'][] = ucfirst($row['status']);
            $data['datasets'][0]['data'][] = (int)$row['count'];
            $color = getStatusColor($row['status']);
            $data['datasets'][0]['backgroundColor'][] = $color;
            $data['datasets'][0]['borderColor'][] = $color;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error in getEventStatusDistribution: " . $e->getMessage());
        throw $e;
    }
}

// Get event registration trends by category
function getEventCategoryTrends($conn) {
    try {
        $query = "SELECT 
                    c.name as category,
                    DATE_FORMAT(er.registration_date, '%Y-%m') as month,
                    COUNT(*) as count,
                    SUM(CASE WHEN er.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count
                  FROM events e
                  JOIN event_categories ec ON e.id = ec.event_id
                  JOIN categories c ON ec.category_id = c.id
                  JOIN event_registrations er ON e.id = er.event_id
                  WHERE er.registration_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY c.name, month
                  ORDER BY month ASC, c.name";
                  
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $data = [
            'labels' => [],
            'datasets' => []
        ];
        
        // If no data, return empty chart data
        if ($result->num_rows === 0) {
            // Fill with last 6 months of zeros
            $start_month = date('Y-m', strtotime('-6 months'));
            $end_month = date('Y-m');
            $current_month = $start_month;
            
            while ($current_month <= $end_month) {
                $data['labels'][] = date('M Y', strtotime($current_month . '-01'));
                $current_month = date('Y-m', strtotime($current_month . ' +1 month'));
            }
            
            $data['datasets'][] = [
                'label' => 'No Data',
                'data' => array_fill(0, count($data['labels']), 0),
                'backgroundColor' => '#6b7280',
                'borderColor' => '#6b7280',
                'borderWidth' => 1
            ];
            
            return $data;
        }
        
        $categories = [];
        $months = [];
        $values = [];
        $confirmed_values = [];
        
        while ($row = $result->fetch_assoc()) {
            if (!in_array($row['month'], $months)) {
                $months[] = $row['month'];
                $data['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
            }
            if (!in_array($row['category'], $categories)) {
                $categories[] = $row['category'];
            }
            $values[$row['category']][$row['month']] = (int)$row['count'];
            $confirmed_values[$row['category']][$row['month']] = (int)$row['confirmed_count'];
        }
        
        // Fill in missing months with zeros
        $start_month = date('Y-m', strtotime('-6 months'));
        $end_month = date('Y-m');
        $current_month = $start_month;
        
        while ($current_month <= $end_month) {
            $formatted_month = date('M Y', strtotime($current_month . '-01'));
            if (!in_array($formatted_month, $data['labels'])) {
                $data['labels'][] = $formatted_month;
            }
            $current_month = date('Y-m', strtotime($current_month . ' +1 month'));
        }
        
        // Sort labels
        sort($data['labels']);
        
        foreach ($categories as $category) {
            $color = getCategoryColor($category);
            $confirmedColor = getCategoryColor($category, true);
            
            $data['datasets'][] = [
                'label' => ucfirst($category) . ' - Total',
                'data' => array_map(function($month) use ($values, $category) {
                    return $values[$category][$month] ?? 0;
                }, $months),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'borderWidth' => 1
            ];
            
            $data['datasets'][] = [
                'label' => ucfirst($category) . ' - Confirmed',
                'data' => array_map(function($month) use ($confirmed_values, $category) {
                    return $confirmed_values[$category][$month] ?? 0;
                }, $months),
                'backgroundColor' => $confirmedColor,
                'borderColor' => $confirmedColor,
                'borderWidth' => 1
            ];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error in getEventCategoryTrends: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to get status color
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'active':
            return '#10b981';
        case 'upcoming':
            return '#6366f1';
        case 'completed':
            return '#6b7280';
        case 'cancelled':
            return '#ef4444';
        default:
            return '#044721';
    }
}

// Helper function to get category color
function getCategoryColor($category, $isConfirmed = false) {
    $colors = [
        'conference' => ['#044721', '#10b981'],
        'workshop' => ['#6366f1', '#818cf8'],
        'seminar' => ['#f59e0b', '#fbbf24'],
        'training' => ['#ef4444', '#f87171'],
        'meeting' => ['#6b7280', '#9ca3af'],
        'other' => ['#8b5cf6', '#a78bfa']
    ];
    
    $category = strtolower($category);
    $colorSet = $colors[$category] ?? $colors['other'];
    return $isConfirmed ? $colorSet[1] : $colorSet[0];
}

// Prepare response data
try {
    $response = [
        'registration_trends' => getRegistrationTrends($conn),
        'event_status' => getEventStatusDistribution($conn),
        'category_trends' => getEventCategoryTrends($conn)
    ];

    // Cache the response
    file_put_contents($cache_file, json_encode($response));

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error preparing chart data: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate chart data: ' . $e->getMessage()]);
}
