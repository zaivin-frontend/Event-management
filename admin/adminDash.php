<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../home.php");
    exit();
}

// Include database connection
require_once '../database/config.php';

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

// Initialize statistics array
$stats = [
    'total_events' => 0,
    'total_users' => 0,
    'total_registrations' => 0,
    'active_events' => 0,
    'total_payments' => 0,
    'pending_payments' => 0,
    'approved_payments' => 0,
    'rejected_payments' => 0
];

try {
    // Get total events
    $query = "SELECT COUNT(*) as count FROM events";
    $result = $conn->query($query);
    if ($result) {
        $stats['total_events'] = $result->fetch_assoc()['count'];
    }

    // Get total users
    $query = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($query);
    if ($result) {
        $stats['total_users'] = $result->fetch_assoc()['count'];
    }

    // Get total registrations
    $query = "SELECT COUNT(*) as count FROM event_registrations";
    $result = $conn->query($query);
    if ($result) {
        $stats['total_registrations'] = $result->fetch_assoc()['count'];
    }

    // Get active events
    $query = "SELECT COUNT(*) as count FROM events WHERE status = 'active'";
    $result = $conn->query($query);
    if ($result) {
        $stats['active_events'] = $result->fetch_assoc()['count'];
    }

    // Get payment statistics
    $query = "SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_payments,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_payments
        FROM event_payments";
    $result = $conn->query($query);
    if ($result) {
        $payment_stats = $result->fetch_assoc();
        $stats['total_payments'] = $payment_stats['total_payments'];
        $stats['pending_payments'] = $payment_stats['pending_payments'];
        $stats['approved_payments'] = $payment_stats['approved_payments'];
        $stats['rejected_payments'] = $payment_stats['rejected_payments'];
    }

    // Get recent events with registration count
    $query = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
        DATE_FORMAT(e.start_date, '%Y-%m-%d') as formatted_start_date,
        DATE_FORMAT(e.end_date, '%Y-%m-%d') as formatted_end_date
        FROM events e 
        ORDER BY e.created_at DESC LIMIT 5";
    $result = $conn->query($query);
    $recent_events = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_events[] = $row;
        }
    }

    // Get recent registrations with user and event details
    $query = "SELECT er.*, u.first_name, u.last_name, e.title as event_title,
        DATE_FORMAT(er.registration_date, '%Y-%m-%d %H:%i') as formatted_date
        FROM event_registrations er 
        JOIN users u ON er.user_id = u.id 
        JOIN events e ON er.event_id = e.id 
        ORDER BY er.registration_date DESC LIMIT 5";
    $result = $conn->query($query);
    $recent_registrations = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_registrations[] = $row;
        }
    }

    // Get recent payments with details
    $query = "SELECT ep.*, e.title as event_title, u.first_name, u.last_name,
        DATE_FORMAT(ep.created_at, '%Y-%m-%d %H:%i') as payment_date,
        DATE_FORMAT(ep.updated_at, '%Y-%m-%d %H:%i') as updated_date
        FROM event_payments ep
        JOIN events e ON ep.event_id = e.id
        JOIN users u ON ep.user_id = u.id
        ORDER BY ep.created_at DESC LIMIT 5";
    $result = $conn->query($query);
    $recent_payments = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_payments[] = $row;
        }
    }

    // Get registration data for chart
    $query = "SELECT DATE(registration_date) as date, COUNT(*) as count 
        FROM event_registrations 
        WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        GROUP BY DATE(registration_date) 
        ORDER BY date";
    $result = $conn->query($query);
    $registration_data = [
        'labels' => [],
        'values' => []
    ];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $registration_data['labels'][] = date('M d', strtotime($row['date']));
            $registration_data['values'][] = $row['count'];
        }
    }

    // Get event status distribution
    $query = "SELECT status, COUNT(*) as count 
        FROM events 
        GROUP BY status 
        ORDER BY count DESC";
    $result = $conn->query($query);
    $category_data = [
        'labels' => [],
        'values' => []
    ];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $category_data['labels'][] = ucfirst($row['status']);
            $category_data['values'][] = $row['count'];
        }
    }

} catch (Exception $e) {
    // Log error and set default values
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard data.";
}

// Get chart data
$query = "SELECT DATE(registration_date) as date, COUNT(*) as count 
          FROM event_registrations 
          WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
          GROUP BY DATE(registration_date) 
          ORDER BY date";
$result = $conn->query($query);
$registration_data = [
    'labels' => [],
    'values' => []
];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $registration_data['labels'][] = date('M d', strtotime($row['date']));
        $registration_data['values'][] = $row['count'];
    }
}

// Get event status distribution
$query = "SELECT status, COUNT(*) as count 
          FROM events 
          GROUP BY status 
          ORDER BY count DESC";
$result = $conn->query($query);
$category_data = [
    'labels' => [],
    'values' => []
];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category_data['labels'][] = ucfirst($row['status']);
        $category_data['values'][] = $row['count'];
    }
}

// Get payment statistics
$payment_stats = [
    'total_payments' => 0,
    'pending_payments' => 0,
    'approved_payments' => 0,
    'rejected_payments' => 0
];

// Get total payments
$query = "SELECT COUNT(*) as count FROM event_payments";
$result = $conn->query($query);
if ($result) {
    $payment_stats['total_payments'] = $result->fetch_assoc()['count'];
}

// Get pending payments
$query = "SELECT COUNT(*) as count FROM event_payments WHERE status = 'pending'";
$result = $conn->query($query);
if ($result) {
    $payment_stats['pending_payments'] = $result->fetch_assoc()['count'];
}

// Get approved payments
$query = "SELECT COUNT(*) as count FROM event_payments WHERE status = 'approved'";
$result = $conn->query($query);
if ($result) {
    $payment_stats['approved_payments'] = $result->fetch_assoc()['count'];
}

// Get rejected payments
$query = "SELECT COUNT(*) as count FROM event_payments WHERE status = 'rejected'";
$result = $conn->query($query);
if ($result) {
    $payment_stats['rejected_payments'] = $result->fetch_assoc()['count'];
}

// Get recent payments
$query = "SELECT ep.*, e.title as event_title, u.first_name, u.last_name,
          DATE_FORMAT(ep.created_at, '%Y-%m-%d %H:%i') as payment_date
          FROM event_payments ep
          JOIN events e ON ep.event_id = e.id
          JOIN users u ON ep.user_id = u.id
          ORDER BY ep.created_at DESC LIMIT 5";
$result = $conn->query($query);
$recent_payments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_payments[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard - CDM Event Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 70px;
            --primary-color: #044721;
            --secondary-color: #6366f1;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --dark-color: #1f2937;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar.collapsed .sidebar-brand span,
        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .notification-badge,
        .sidebar.collapsed .sidebar-user-info {
            display: none;
        }

        .sidebar.collapsed .sidebar-link {
            padding: 0.875rem;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-link i {
            margin: 0;
            font-size: 1.5rem;
        }

        .sidebar.collapsed .sidebar-user {
            justify-content: center;
            padding: 0.5rem;
        }

        .sidebar.collapsed .sidebar-user-avatar {
            margin: 0;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: opacity 0.3s ease;
        }

        .sidebar-brand:hover {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }

        .sidebar-item {
            margin: 0.25rem 0;
            position: relative;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
            font-weight: 500;
        }

        .sidebar-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            text-decoration: none;
        }

        .sidebar-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
        }

        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: white;
        }

        .sidebar-link i {
            font-size: 1.25rem;
            width: 1.5rem;
            text-align: center;
        }

        .notification-badge {
            position: absolute;
            top: 50%;
            right: 1.5rem;
            transform: translateY(-50%);
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.25rem;
        }

        .sidebar-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            text-decoration: none;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .sidebar-user:hover {
            background: rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: white;
        }

        .sidebar-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-weight: 600;
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        /* Sidebar Toggle Button */
        #sidebarToggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        #sidebarToggle:hover {
            background: var(--primary-color);
            color: white;
        }

        #sidebarToggle i {
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed + #sidebarToggle {
            left: 70px;
        }

        .sidebar.collapsed + #sidebarToggle i {
            transform: rotate(180deg);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        .topbar {
            background: white;
            height: var(--topbar-height);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .content-area {
            padding: 1.5rem;
        }

        /* Enhanced Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .stat-card .card-body {
            padding: 1.5rem;
        }

        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .trend-indicator {
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Enhanced Tables */
        .enhanced-table {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .table-responsive {
            border-radius: 12px;
        }

        .table th {
            background: var(--light-bg);
            border: none;
            font-weight: 600;
            color: var(--dark-color);
            padding: 1rem;
        }

        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-btn {
            background: white;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .quick-action-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
                width: var(--sidebar-width);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .main-content.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Loading and Error States */
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Enhanced Badges */
        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
            border-radius: 6px;
        }

        /* Status indicators */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="adminDash.php" class="sidebar-brand">
                <i class="bi bi-calendar-event"></i>
                <span>CDM Events</span>
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="adminDash.php" class="sidebar-link active">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="manageEvents.php" class="sidebar-link">
                    <i class="bi bi-calendar-plus"></i>
                    <span>Events</span>
                    <span class="notification-badge">3</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="./users/manageUsers.php" class="sidebar-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="./reports/reports.php" class="sidebar-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="./analytics/analytics.php" class="sidebar-link">
                    <i class="bi bi-bar-chart"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="./payments/manage-payments.php" class="sidebar-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="./settings/settings.php" class="sidebar-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="profile.php" class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                    <div class="sidebar-user-role">Administrator</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Sidebar Toggle Button -->
    <button class="btn btn-link" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <h1 class="topbar-title">Dashboard</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Search -->
                <div class="position-relative d-none d-sm-block">
                    <input type="text" class="form-control" placeholder="Search..." style="width: 250px; padding-left: 2.5rem;">
                    <i class="bi bi-search position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                </div>
                
                <!-- Notifications -->
                <div class="position-relative">
                    <button class="btn btn-light" id="notificationBtn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge">5</span>
                    </button>
                </div>
                
                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($admin_name); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">
                            <i class="bi bi-person me-2"></i>Profile
                        </a></li>
                        <li><a class="dropdown-item" href="./settings/settings.php">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../pages/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Error Alert -->
            <div id="errorAlert" class="alert alert-danger d-none" role="alert"></div>

            <!-- Dashboard Content -->
            <div id="dashboardContent" class="d-none">
                <!-- Quick Actions -->
                <div class="quick-actions mb-4">
                    <a href="manageEvents.php?action=create" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <div>
                            <strong>Create Event</strong>
                            <small class="text-muted d-block">Add new event</small>
                        </div>
                    </a>
                    <a href="./users/manageUsers.php" class="quick-action-btn">
                        <div class="quick-action-icon bg-success">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div>
                            <strong>Invite Users</strong>
                            <small class="text-muted d-block">Send invitations</small>
                        </div>
                    </a>
                    <a href="./reports/reports.php" class="quick-action-btn">
                        <div class="quick-action-icon bg-info">
                            <i class="bi bi-download"></i>
                        </div>
                        <div>
                            <strong>Export Data</strong>
                            <small class="text-muted d-block">Generate reports</small>
                        </div>
                    </a>
                    <a href="./settings/settings.php" class="quick-action-btn">
                        <div class="quick-action-icon bg-warning">
                            <i class="bi bi-gear"></i>
                        </div>
                        <div>
                            <strong>Settings</strong>
                            <small class="text-muted d-block">System config</small>
                        </div>
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4" id="statisticsCards"></div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="chart-container">
                            <h5 class="mb-3">Event Registrations Over Time</h5>
                            <canvas id="registrationsChart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="chart-container">
                            <h5 class="mb-3">Event Categories</h5>
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row g-4">
                    <!-- Recent Events -->
                    <div class="col-lg-6">
                        <div class="enhanced-table">
                            <div class="card-header bg-white border-0 p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Recent Events</h5>
                                    <a href="manageEvents.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table" id="recentEventsTable">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Category</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Registrations -->
                    <div class="col-lg-6">
                        <div class="enhanced-table">
                            <div class="card-header bg-white border-0 p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Recent Registrations</h5>
                                    <a href="./reports/reports.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table" id="recentRegistrationsTable">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add this after the Recent Registrations section -->
                <div class="col-lg-12 mt-4">
                    <div class="enhanced-table">
                        <div class="card-header bg-white border-0 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Payment Management</h5>
                                <a href="./payments/manage-payments.php" class="btn btn-sm btn-primary">View All Payments</a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table" id="recentPaymentsTable">
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
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['event_title']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                            <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                            <td><?php echo $payment['payment_date']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $payment['status'] === 'approved' ? 'success' : 
                                                        ($payment['status'] === 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($payment['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-success" onclick="verifyPayment(<?php echo $payment['id']; ?>)">
                                                            <i class="bi bi-check-circle"></i> Verify
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)">
                                                            <i class="bi bi-x-circle"></i> Reject
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-primary" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            setupSidebar();
            setupEventHandlers();
        });

        function initializeDashboard() {
            try {
                const dashboardData = {
                    statistics: <?php echo json_encode($stats); ?>,
                    recent_events: <?php echo json_encode($recent_events); ?>,
                    recent_registrations: <?php echo json_encode($recent_registrations); ?>,
                    registration_data: <?php echo json_encode($registration_data); ?>,
                    category_data: <?php echo json_encode($category_data); ?>,
                    recent_payments: <?php echo json_encode($recent_payments); ?>
                };

                displayDashboard(dashboardData);
                setupNotifications();
            } catch (error) {
                console.error('Error initializing dashboard:', error);
                showError('Failed to initialize dashboard. Please refresh the page.');
            }
        }

        function setupEventHandlers() {
            // Payment approval handler
            window.approvePayment = function(paymentId) {
                if (!confirm('Are you sure you want to approve this payment?')) {
                    return;
                }
                
                showLoading();
                
                fetch('../api/approve-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: paymentId,
                        notes: document.getElementById('approvalNotes')?.value || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showSuccess('Payment approved successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message || 'Failed to approve payment');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showError('Failed to approve payment. Please try again later.');
                });
            };

            // Payment rejection handler
            window.rejectPayment = function(paymentId) {
                if (!confirm('Are you sure you want to reject this payment?')) {
                    return;
                }
                
                showLoading();
                
                fetch('../api/reject-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: paymentId,
                        reason: document.getElementById('rejectionReason')?.value || null,
                        notes: document.getElementById('rejectionNotes')?.value || null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showSuccess('Payment rejected successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message || 'Failed to reject payment');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showError('Failed to reject payment. Please try again later.');
                });
            };

            // View payment handler
            window.viewPayment = function(paymentId) {
                window.location.href = `./payments/view-payment.php?id=${paymentId}`;
            };
        }

        function displayDashboard(data) {
            try {
                // Hide loading spinner and show content
                document.getElementById('loadingSpinner').classList.add('d-none');
                document.getElementById('dashboardContent').classList.remove('d-none');

                // Display statistics
                displayStatistics(data.statistics);
                
                // Display tables
                displayRecentEvents(data.recent_events);
                displayRecentRegistrations(data.recent_registrations);
                displayRecentPayments(data.recent_payments);
                
                // Initialize charts
                initializeRegistrationsChart(data.registration_data);
                initializeCategoriesChart(data.category_data);
            } catch (error) {
                console.error('Error displaying dashboard:', error);
                showError('Failed to display dashboard data. Please refresh the page.');
            }
        }

        function displayStatistics(stats) {
            const statsHtml = `
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-0 opacity-75">Total Events</h6>
                                    <h2 class="mt-2 mb-0">${stats.total_events}</h2>
                                </div>
                                <i class="bi bi-calendar-event stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-0 opacity-75">Total Users</h6>
                                    <h2 class="mt-2 mb-0">${stats.total_users}</h2>
                                </div>
                                <i class="bi bi-people stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-0 opacity-75">Total Registrations</h6>
                                    <h2 class="mt-2 mb-0">${stats.total_registrations}</h2>
                                </div>
                                <i class="bi bi-ticket-perforated stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-0 opacity-75">Active Events</h6>
                                    <h2 class="mt-2 mb-0">${stats.active_events}</h2>
                                </div>
                                <i class="bi bi-lightning stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('statisticsCards').innerHTML = statsHtml;
        }

        function displayRecentEvents(events) {
            const eventsTable = document.querySelector('#recentEventsTable tbody');
            eventsTable.innerHTML = events.map(event => `
                <tr>
                    <td>
                        <div>
                            <strong>${escapeHtml(event.title)}</strong>
                            <div class="text-muted small">${event.registration_count || 0} registrations</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">${escapeHtml(event.event_category || 'General')}</span>
                    </td>
                    <td>${formatDate(event.formatted_start_date)}</td>
                    <td>
                        <span class="status-dot bg-${getStatusColor(event.status)}"></span>
                        <span class="badge bg-${getStatusColor(event.status)}">
                            ${event.status.charAt(0).toUpperCase() + event.status.slice(1)}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewEvent(${event.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary" onclick="editEvent(${event.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function displayRecentRegistrations(registrations) {
            const registrationsTable = document.querySelector('#recentRegistrationsTable tbody');
            registrationsTable.innerHTML = registrations.map(registration => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                ${registration.first_name.charAt(0).toUpperCase()}
                            </div>
                            ${escapeHtml(registration.first_name)} ${escapeHtml(registration.last_name)}
                        </div>
                    </td>
                    <td>${escapeHtml(registration.event_title)}</td>
                    <td>${registration.formatted_date}</td>
                    <td>
                        <span class="status-dot bg-${getRegistrationStatusColor(registration.status)}"></span>
                        <span class="badge bg-${getRegistrationStatusColor(registration.status)}">
                            ${registration.status.charAt(0).toUpperCase() + registration.status.slice(1)}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="viewRegistration(${registration.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="approveRegistration(${registration.id})">
                                <i class="bi bi-check"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function initializeCharts() {
            // Fetch chart data from the server
            fetch('get_dashboard_charts.php')
                .then(response => response.json())
                .then(data => {
                    initializeRegistrationsChart(data.registrations);
                    initializeCategoriesChart(data.categories);
                })
                .catch(error => {
                    console.error('Error fetching chart data:', error);
                    showToast('Error loading chart data', 'danger');
                });
        }

        function initializeRegistrationsChart(data) {
            const ctx1 = document.getElementById('registrationsChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Registrations',
                        data: data.values,
                        borderColor: '#044721',
                        backgroundColor: 'rgba(4, 71, 33, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return `Registrations: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        function initializeCategoriesChart(data) {
            const ctx2 = document.getElementById('categoriesChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#044721',
                            '#10b981',
                            '#f59e0b',
                            '#ef4444',
                            '#06b6d4',
                            '#6366f1',
                            '#8b5cf6'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }

        function displayRecentPayments(payments) {
            const paymentsTable = document.querySelector('#recentPaymentsTable tbody');
            if (!paymentsTable) return;

            paymentsTable.innerHTML = payments.map(payment => `
                <tr>
                    <td>${escapeHtml(payment.event_title)}</td>
                    <td>${escapeHtml(payment.first_name)} ${escapeHtml(payment.last_name)}</td>
                    <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
                    <td>${escapeHtml(payment.payment_method)}</td>
                    <td>${escapeHtml(payment.reference_number)}</td>
                    <td>${escapeHtml(payment.payment_date)}</td>
                    <td>
                        <span class="badge bg-${getPaymentStatusColor(payment.status)}">
                            ${escapeHtml(payment.status.charAt(0).toUpperCase() + payment.status.slice(1))}
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${payment.status === 'pending' ? `
                                <button class="btn btn-outline-success" onclick="verifyPayment(${payment.id})">
                                    <i class="bi bi-check-circle"></i> Verify
                                </button>
                                <button class="btn btn-outline-danger" onclick="rejectPayment(${payment.id})">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            ` : ''}
                            <button class="btn btn-outline-primary" onclick="viewPaymentDetails(${payment.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getPaymentStatusColor(status) {
            switch (status?.toLowerCase()) {
                case 'approved':
                    return 'success';
                case 'pending':
                    return 'warning';
                case 'rejected':
                    return 'danger';
                default:
                    return 'secondary';
            }
        }

        // Utility functions
        function showLoading() {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.classList.remove('d-none');
        }

        function hideLoading() {
            const spinner = document.getElementById('loadingSpinner');
            if (spinner) spinner.classList.add('d-none');
        }

        function showError(message) {
            const alert = document.getElementById('errorAlert');
            if (alert) {
                alert.textContent = message;
                alert.classList.remove('d-none');
                setTimeout(() => alert.classList.add('d-none'), 5000);
            }
        }

        function showSuccess(message) {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-3';
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                document.body.removeChild(toast);
            });
        }

        function escapeHtml(unsafe) {
            if (unsafe == null) return '';
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function getStatusColor(status) {
            switch (status?.toLowerCase()) {
                case 'upcoming':
                    return 'info';
                case 'ongoing':
                    return 'success';
                case 'completed':
                    return 'secondary';
                case 'cancelled':
                    return 'danger';
                default:
                    return 'primary';
            }
        }

        function getRegistrationStatusColor(status) {
            switch (status.toLowerCase()) {
                case 'confirmed':
                    return 'success';
                case 'pending':
                    return 'warning';
                case 'cancelled':
                    return 'danger';
                default:
                    return 'primary';
            }
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                document.body.removeChild(toast);
            });
        }

        // Event handlers
        function viewEvent(id) {
            window.location.href = `manageEvents.php?action=view&id=${id}`;
        }

        function editEvent(id) {
            window.location.href = `manageEvents.php?action=edit&id=${id}`;
        }

        function viewRegistration(id) {
            window.location.href = `./reports/reports.php?type=registration&id=${id}`;
        }

        function approveRegistration(id) {
            // Instead of simulating API call and showing toast, display a modal
            const approvalModal = new bootstrap.Modal(document.getElementById('registrationApprovalModal'));
            
            // You can optionally pass the ID to the modal for more dynamic content
            // For now, we'll just show the generic "coming soon" message.
            document.getElementById('registrationIdForApproval').textContent = id;
            approvalModal.show();
        }

        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const isMobile = window.innerWidth <= 768;
            
            // Set initial state
            if (isMobile) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                const isMobile = window.innerWidth <= 768;
                if (isMobile) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                }
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target) && 
                    !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                }
            });

            // Set active menu item based on current page
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.sidebar-link');
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPath.split('/').pop()) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Add tooltips for collapsed state
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            sidebarLinks.forEach(link => {
                const text = link.querySelector('span').textContent;
                link.setAttribute('title', text);
            });
        }

        function setupNotifications() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));
            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    notificationModal.show();
                });
            }
        }

        function verifyPayment(paymentId) {
            // Show loading state
            showLoading();
            
            // Fetch payment details
            fetch(`../api/get-payment-details.php?id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        // Populate modal with payment details
                        document.getElementById('paymentId').value = paymentId;
                        document.getElementById('paymentDetails').innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Event:</strong> ${escapeHtml(data.payment.event_title)}</p>
                                    <p><strong>Student:</strong> ${escapeHtml(data.payment.student_name)}</p>
                                    <p><strong>Amount:</strong> ₱${parseFloat(data.payment.amount).toFixed(2)}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Method:</strong> ${escapeHtml(data.payment.payment_method)}</p>
                                    <p><strong>Reference:</strong> ${escapeHtml(data.payment.reference_number)}</p>
                                    <p><strong>Date:</strong> ${escapeHtml(data.payment.payment_date)}</p>
                                </div>
                            </div>
                        `;
                        
                        // Show the modal
                        const modal = new bootstrap.Modal(document.getElementById('paymentVerificationModal'));
                        modal.show();
                    } else {
                        showError(data.message || 'Failed to load payment details');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showError('Failed to load payment details');
                });
        }

        function confirmPaymentVerification() {
            const paymentId = document.getElementById('paymentId').value;
            const notes = document.getElementById('verificationNotes').value;
            
            showLoading();
            
            fetch('../api/verify-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId,
                    notes: notes
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Payment verified successfully!');
                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('paymentVerificationModal')).hide();
                    // Refresh the page to show updated status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message || 'Failed to verify payment');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Failed to verify payment');
            });
        }

        function rejectPayment(paymentId) {
            if (!confirm('Are you sure you want to reject this payment? This action cannot be undone.')) {
                return;
            }
            
            showLoading();
            
            fetch('../api/reject-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: paymentId,
                    reason: 'Payment verification failed'
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Payment rejected successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message || 'Failed to reject payment');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Failed to reject payment');
            });
        }

        function viewPaymentDetails(paymentId) {
            window.location.href = `./payments/view-payment.php?id=${paymentId}`;
        }
    </script>

    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This is where your notifications will appear.</p>
                    <p><strong>Notifications feature coming soon!</strong></p>
                    <!-- Example notifications -->
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action d-flex gap-2 py-2" aria-current="true">
                            <i class="bi bi-info-circle-fill text-primary"></i>
                            <div class="d-flex gap-2 w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-0">System Update</h6>
                                    <p class="mb-0 opacity-75">A new version is available. Click to update.</p>
                                </div>
                                <small class="opacity-50 text-nowrap">Just now</small>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex gap-2 py-2" aria-current="true">
                            <i class="bi bi-calendar-event-fill text-success"></i>
                            <div class="d-flex gap-2 w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-0">New Event Created</h6>
                                    <p class="mb-0 opacity-75">'Community Gala' has been successfully published.</p>
                                </div>
                                <small class="opacity-50 text-nowrap">1 day ago</small>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex gap-2 py-2" aria-current="true">
                            <i class="bi bi-person-fill-add text-info"></i>
                            <div class="d-flex gap-2 w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-0">New User Registration</h6>
                                    <p class="mb-0 opacity-75">John Doe just registered an account.</p>
                                </div>
                                <small class="opacity-50 text-nowrap">3 days ago</small>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">View All Notifications</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Approval Modal -->
    <div class="modal fade" id="registrationApprovalModal" tabindex="-1" aria-labelledby="registrationApprovalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registrationApprovalModalLabel">Approve Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve registration ID: <span id="registrationIdForApproval"></span>?</p>
                    <p><strong>Registration approval feature coming soon!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Payment Verification Modal -->
    <div class="modal fade" id="paymentVerificationModal" tabindex="-1" aria-labelledby="paymentVerificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentVerificationModalLabel">Verify Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentVerificationForm">
                        <input type="hidden" id="paymentId" name="paymentId">
                        <div class="mb-3">
                            <label for="verificationNotes" class="form-label">Verification Notes</label>
                            <textarea class="form-control" id="verificationNotes" name="verificationNotes" rows="3" placeholder="Add any notes about the payment verification..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Details</label>
                            <div id="paymentDetails" class="p-3 bg-light rounded">
                                <!-- Payment details will be loaded here -->
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmPaymentVerification()">Confirm Verification</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

