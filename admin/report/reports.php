<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../home.php");
    exit();
}

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

// Include database connection
require_once '../../database/config.php';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_report':
            $report_type = $_POST['report_type'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $format = $_POST['format'] ?? 'csv';
            
            // Generate report based on type
            switch ($report_type) {
                case 'event_registrations':
                    generateEventRegistrationsReport($conn, $start_date, $end_date, $format);
                    break;
                case 'user_activity':
                    generateUserActivityReport($conn, $start_date, $end_date, $format);
                    break;
                case 'event_analytics':
                    generateEventAnalyticsReport($conn, $start_date, $end_date, $format);
                    break;
            }
            break;
    }
}

// Function to generate event registrations report
function generateEventRegistrationsReport($conn, $start_date, $end_date, $format) {
    // Validate dates
    if (!validateDates($start_date, $end_date)) {
        die("Invalid date range");
    }

    $query = "SELECT e.title, e.start_date, e.end_date, e.location, e.status,
                     COUNT(er.id) as total_registrations,
                     SUM(CASE WHEN er.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_registrations,
                     e.created_at,
                     GROUP_CONCAT(c.name) as event_categories
              FROM events e
              LEFT JOIN event_registrations er ON e.id = er.event_id
              LEFT JOIN event_categories ec ON e.id = ec.event_id
              LEFT JOIN categories c ON ec.category_id = c.id
              WHERE e.start_date BETWEEN ? AND ?
              GROUP BY e.id
              ORDER BY e.start_date DESC";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="event_registrations_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Event Title', 'Start Date', 'End Date', 'Location', 'Status', 'Category', 'Total Registrations', 'Confirmed Registrations', 'Created Date']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['title'],
                date('Y-m-d', strtotime($row['start_date'])),
                date('Y-m-d', strtotime($row['end_date'])),
                $row['location'],
                ucfirst($row['status']),
                ucfirst($row['event_categories']),
                $row['total_registrations'],
                $row['confirmed_registrations'],
                date('Y-m-d', strtotime($row['created_at']))
            ]);
        }
        
        fclose($output);
    }
    exit();
}

// Function to generate user activity report
function generateUserActivityReport($conn, $start_date, $end_date, $format) {
    // Validate dates
    if (!validateDates($start_date, $end_date)) {
        die("Invalid date range");
    }

    $query = "SELECT u.first_name, u.last_name, u.email,
                     COUNT(DISTINCT er.id) as total_registrations,
                     COUNT(DISTINCT CASE WHEN er.status = 'confirmed' THEN er.id END) as confirmed_registrations,
                     MAX(er.registration_date) as last_registration,
                     u.created_at,
                     GROUP_CONCAT(DISTINCT e.title) as registered_events
              FROM users u
              LEFT JOIN event_registrations er ON u.id = er.user_id
              LEFT JOIN events e ON er.event_id = e.id
              WHERE er.registration_date BETWEEN ? AND ?
              GROUP BY u.id
              ORDER BY total_registrations DESC";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="user_activity_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Total Registrations', 'Confirmed Registrations', 'Last Registration', 'Account Created', 'Registered Events']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['first_name'] . ' ' . $row['last_name'],
                $row['email'],
                $row['total_registrations'],
                $row['confirmed_registrations'],
                date('Y-m-d', strtotime($row['last_registration'])),
                date('Y-m-d', strtotime($row['created_at'])),
                $row['registered_events']
            ]);
        }
        
        fclose($output);
    }
    exit();
}

// Function to generate event analytics report
function generateEventAnalyticsReport($conn, $start_date, $end_date, $format) {
    // Validate dates
    if (!validateDates($start_date, $end_date)) {
        die("Invalid date range");
    }

    $query = "SELECT e.title, e.status, e.event_category,
                     COUNT(DISTINCT er.id) as total_registrations,
                     COUNT(DISTINCT CASE WHEN er.status = 'confirmed' THEN er.id END) as confirmed_registrations,
                     AVG(TIMESTAMPDIFF(DAY, e.created_at, er.registration_date)) as avg_registration_time,
                     e.created_at,
                     e.start_date,
                     e.end_date,
                     e.location
              FROM events e
              LEFT JOIN event_registrations er ON e.id = er.event_id
              WHERE e.start_date BETWEEN ? AND ?
              GROUP BY e.id
              ORDER BY total_registrations DESC";
              
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $start_date, $end_date);
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="event_analytics_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Event Title', 'Status', 'Category', 'Total Registrations', 'Confirmed Registrations', 'Avg Registration Time (Days)', 'Created Date', 'Start Date', 'End Date', 'Location']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['title'],
                ucfirst($row['status']),
                ucfirst($row['event_category']),
                $row['total_registrations'],
                $row['confirmed_registrations'],
                round($row['avg_registration_time'], 1),
                date('Y-m-d', strtotime($row['created_at'])),
                date('Y-m-d', strtotime($row['start_date'])),
                date('Y-m-d', strtotime($row['end_date'])),
                $row['location']
            ]);
        }
        
        fclose($output);
    }
    exit();
}

// Helper function to validate dates
function validateDates($start_date, $end_date) {
    if (!$start_date || !$end_date) {
        return false;
    }
    
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    
    if (!$start || !$end) {
        return false;
    }
    
    if ($end < $start) {
        return false;
    }
    
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Reports - CDM Event Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link rel="stylesheet" href="../assets/css-admin/adminDash.css">
    <style>
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

        /* Report Cards */
        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
        }

        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .report-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Charts */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }

        /* Date Range Picker */
        .date-range-picker {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../adminDash.php" class="sidebar-brand">
                <i class="bi bi-calendar-event"></i>
                <span>CDM Events</span>
            </a>
        </div>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="../adminDash.php" class="sidebar-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../manageEvents.php" class="sidebar-link">
                    <i class="bi bi-calendar-plus"></i>
                    <span>Events</span>
                    <span class="notification-badge">3</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../users/manageUsers.php" class="sidebar-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports.php" class="sidebar-link active">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../analytics/analytics.php" class="sidebar-link">
                    <i class="bi bi-bar-chart"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../settings/settings.php" class="sidebar-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="../profile.php" class="sidebar-user">
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
                <h1 class="topbar-title">Reports</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($admin_name); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../profile.php">
                            <i class="bi bi-person me-2"></i>Profile
                        </a></li>
                        <li><a class="dropdown-item" href="../settings/settings.php">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../../pages/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Date Range Picker -->
            <div class="date-range-picker">
                <form id="reportForm" method="POST" class="row g-3">
                    <input type="hidden" name="action" value="generate_report">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select" required>
                            <option value="event_registrations">Event Registrations</option>
                            <option value="user_activity">User Activity</option>
                            <option value="event_analytics">Event Analytics</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Format</label>
                        <select name="format" class="form-select">
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF (Coming Soon)</option>
                            <option value="excel">Excel (Coming Soon)</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="report-card p-4">
                        <div class="report-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h5>Event Registrations</h5>
                        <p class="text-muted mb-3">Generate detailed reports of event registrations including attendance and status.</p>
                        <button class="btn btn-outline-primary" onclick="showReportDetails('event_registrations')">
                            View Details
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="report-card p-4">
                        <div class="report-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5>User Activity</h5>
                        <p class="text-muted mb-3">Track user engagement and participation across all events.</p>
                        <button class="btn btn-outline-success" onclick="showReportDetails('user_activity')">
                            View Details
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="report-card p-4">
                        <div class="report-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h5>Event Analytics</h5>
                        <p class="text-muted mb-3">Analyze event performance and registration trends.</p>
                        <button class="btn btn-outline-info" onclick="showReportDetails('event_analytics')">
                            View Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <h5 class="mb-3">Registration Trends</h5>
                        <canvas id="registrationTrendsChart" height="300"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-container">
                        <h5 class="mb-3">Event Status</h5>
                        <canvas id="eventStatusChart" height="300"></canvas>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="chart-container">
                        <h5 class="mb-3">Category Trends</h5>
                        <canvas id="categoryTrendsChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeReports();
        });

        function initializeReports() {
            setupSidebar();
            initializeCharts();
            setupDateRangePicker();
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

        function setupDateRangePicker() {
            // Set default date range (last 30 days)
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            document.querySelector('input[name="start_date"]').value = thirtyDaysAgo.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = today.toISOString().split('T')[0];
        }

        function initializeCharts() {
            // Show loading state
            showToast('Loading chart data...', 'info');
            
            // Store chart instances
            let registrationChart = null;
            let statusChart = null;
            let categoryChart = null;
            
            // Fetch chart data from the server
            fetch('get_chart_data.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Initialize each chart with error handling
                    try {
                        if (data.registration_trends) {
                            if (registrationChart) {
                                registrationChart.destroy();
                            }
                            registrationChart = initializeRegistrationTrendsChart(data.registration_trends);
                        }
                        
                        if (data.event_status) {
                            if (statusChart) {
                                statusChart.destroy();
                            }
                            statusChart = initializeEventStatusChart(data.event_status);
                        }
                        
                        if (data.category_trends) {
                            if (categoryChart) {
                                categoryChart.destroy();
                            }
                            categoryChart = initializeCategoryTrendsChart(data.category_trends);
                        }
                        
                        // Show success message
                        showToast('Charts loaded successfully', 'success');
                    } catch (error) {
                        console.error('Error initializing charts:', error);
                        showToast('Error initializing charts: ' + error.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error fetching chart data:', error);
                    showToast('Error loading chart data: ' + error.message, 'danger');
                });
        }

        function initializeRegistrationTrendsChart(data) {
            const ctx = document.getElementById('registrationTrendsChart').getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
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
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    }
                }
            });
        }

        function initializeEventStatusChart(data) {
            const ctx = document.getElementById('eventStatusChart').getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
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
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    }
                }
            });
        }

        function initializeCategoryTrendsChart(data) {
            const ctx = document.getElementById('categoryTrendsChart').getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
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
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    }
                }
            });
        }

        function showReportDetails(reportType) {
            // Update form with selected report type
            document.querySelector('select[name="report_type"]').value = reportType;
            
            // Scroll to date range picker
            document.querySelector('.date-range-picker').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Form submission handling
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const startDate = new Date(this.start_date.value);
            const endDate = new Date(this.end_date.value);
            
            if (endDate < startDate) {
                alert('End date cannot be earlier than start date');
                return;
            }
            
            this.submit();
        });

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
    </script>
</body>
</html>
