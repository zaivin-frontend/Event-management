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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Analytics - CDM Event Management</title>
    
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

        /* Analytics Cards */
        .analytics-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
        }

        .analytics-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .analytics-icon {
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
                <a href="../reports/reports.php" class="sidebar-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="analytics.php" class="sidebar-link active">
                    <i class="bi bi-bar-chart"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="../payments/manage-payments.php" class="sidebar-link">
                    <i class="bi bi-cash-coin"></i>
                    <span>Payments</span>
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
                <h1 class="topbar-title">Analytics</h1>
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
                <form id="dateRangeForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="dateRange">
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3 custom-date-range d-none">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate">
                    </div>
                    <div class="col-md-3 custom-date-range d-none">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Update
                        </button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="analytics-card p-4">
                        <div class="analytics-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h3 class="mb-2" id="totalEvents">0</h3>
                        <p class="text-muted mb-0">Total Events</p>
                        <div class="trend-indicator mt-2" id="eventsTrend"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card p-4">
                        <div class="analytics-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="mb-2" id="totalUsers">0</h3>
                        <p class="text-muted mb-0">Total Users</p>
                        <div class="trend-indicator mt-2" id="usersTrend"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card p-4">
                        <div class="analytics-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <h3 class="mb-2" id="totalRegistrations">0</h3>
                        <p class="text-muted mb-0">Total Registrations</p>
                        <div class="trend-indicator mt-2" id="registrationsTrend"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="analytics-card p-4">
                        <div class="analytics-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h3 class="mb-2" id="avgAttendance">0%</h3>
                        <p class="text-muted mb-0">Average Attendance</p>
                        <div class="trend-indicator mt-2" id="attendanceTrend"></div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="chart-container">
                        <h5 class="mb-3">Event Registration Trends</h5>
                        <canvas id="registrationTrendsChart"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="chart-container">
                        <h5 class="mb-3">Event Categories Distribution</h5>
                        <canvas id="eventCategoriesChart"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h5 class="mb-3">User Engagement</h5>
                        <canvas id="userEngagementChart"></canvas>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-container">
                        <h5 class="mb-3">Registration Status</h5>
                        <canvas id="registrationStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeAnalytics();
        });

        function initializeAnalytics() {
            setupSidebar();
            setupDateRangePicker();
            loadAnalyticsData();
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
            const dateRange = document.getElementById('dateRange');
            const customDateRange = document.querySelectorAll('.custom-date-range');
            
            dateRange.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.forEach(el => el.classList.remove('d-none'));
                } else {
                    customDateRange.forEach(el => el.classList.add('d-none'));
                }
            });

            document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                loadAnalyticsData();
            });
        }

        function loadAnalyticsData() {
            const dateRange = document.getElementById('dateRange').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            // Show loading state
            showLoading();

            // Fetch analytics data
            fetch('get_analytics_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    date_range: dateRange,
                    start_date: startDate,
                    end_date: endDate
                })
            })
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
                updateMetrics(data.metrics);
                initializeCharts(data.charts);
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(error.message || 'Error loading analytics data', 'danger');
                hideLoading();
                
                // Reset metrics to zero
                resetMetrics();
                // Clear charts
                clearCharts();
            });
        }

        function resetMetrics() {
            document.getElementById('totalEvents').textContent = '0';
            document.getElementById('totalUsers').textContent = '0';
            document.getElementById('totalRegistrations').textContent = '0';
            document.getElementById('avgAttendance').textContent = '0%';
            
            // Reset trends
            const trendElements = ['eventsTrend', 'usersTrend', 'registrationsTrend', 'attendanceTrend'];
            trendElements.forEach(elementId => {
                const element = document.getElementById(elementId);
                if (element) {
                    element.innerHTML = '<span class="text-muted">No data available</span>';
                }
            });
        }

        function clearCharts() {
            const chartIds = [
                'registrationTrendsChart',
                'eventCategoriesChart',
                'userEngagementChart',
                'registrationStatusChart'
            ];
            
            chartIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    // Display "No data available" message
                    ctx.font = '14px Arial';
                    ctx.fillStyle = '#666';
                    ctx.textAlign = 'center';
                    ctx.fillText('No data available', canvas.width / 2, canvas.height / 2);
                }
            });
        }

        function updateMetrics(metrics) {
            // Update key metrics
            document.getElementById('totalEvents').textContent = metrics.total_events;
            document.getElementById('totalUsers').textContent = metrics.total_users;
            document.getElementById('totalRegistrations').textContent = metrics.total_registrations;
            document.getElementById('avgAttendance').textContent = metrics.avg_attendance + '%';

            // Update trends with proper formatting
            updateTrendIndicator('eventsTrend', metrics.events_trend, metrics.total_events);
            updateTrendIndicator('usersTrend', metrics.users_trend, metrics.total_users);
            updateTrendIndicator('registrationsTrend', metrics.registrations_trend, metrics.total_registrations);
            updateTrendIndicator('attendanceTrend', metrics.attendance_trend, metrics.avg_attendance);
        }

        function updateTrendIndicator(elementId, trend, currentValue) {
            const element = document.getElementById(elementId);
            const icon = trend > 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
            const color = trend > 0 ? 'text-success' : trend < 0 ? 'text-danger' : 'text-muted';
            const trendText = trend > 0 ? `+${trend}%` : trend < 0 ? `${trend}%` : '0%';
            
            element.innerHTML = `
                <i class="bi ${icon} ${color}"></i>
                <span class="${color}">${trendText} from previous period</span>
            `;
        }

        function initializeCharts(data) {
            // Destroy existing charts if they exist
            destroyExistingCharts();
            
            // Initialize new charts
            initializeRegistrationTrendsChart(data.registration_trends);
            initializeEventCategoriesChart(data.event_categories);
            initializeUserEngagementChart(data.user_engagement);
            initializeRegistrationStatusChart(data.registration_status);
        }

        function destroyExistingCharts() {
            const chartIds = [
                'registrationTrendsChart',
                'eventCategoriesChart',
                'userEngagementChart',
                'registrationStatusChart'
            ];
            
            chartIds.forEach(id => {
                const chart = Chart.getChart(document.getElementById(id));
                if (chart) {
                    chart.destroy();
                }
            });
        }

        function initializeRegistrationTrendsChart(data) {
            const ctx = document.getElementById('registrationTrendsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Registrations',
                        data: data.values,
                        borderColor: '#044721', // Dark green color for the line and point
                        backgroundColor: 'transparent', // No fill under the line
                        pointBackgroundColor: '#044721', // Green color for the point
                        pointBorderColor: '#044721',
                        pointRadius: 5, // Make the point a visible circle
                        pointHoverRadius: 7,
                        tension: 0, // Make the line straight
                        fill: false // Explicitly no fill
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false // No legend
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
                            },
                            max: 5 // Set max Y-axis value to 5 to accommodate the point at 4
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                display: false // No X-axis labels
                            }
                        }
                    }
                }
            });
        }

        function initializeEventCategoriesChart(data) {
            const ctx = document.getElementById('eventCategoriesChart').getContext('2d');
            new Chart(ctx, {
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

        function initializeUserEngagementChart(data) {
            const ctx = document.getElementById('userEngagementChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Active Users',
                        data: data.values,
                        backgroundColor: '#044721',
                        borderRadius: 4
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
                            callbacks: {
                                label: function(context) {
                                    return `Active Users: ${context.raw}`;
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

        function initializeRegistrationStatusChart(data) {
            const ctx = document.getElementById('registrationStatusChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#10b981', // Confirmed
                            '#f59e0b', // Pending
                            '#ef4444'  // Cancelled
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
                    }
                }
            });
        }

        function showLoading() {
            // Add loading overlay
            const overlay = document.createElement('div');
            overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-white bg-opacity-75';
            overlay.style.zIndex = '9999';
            overlay.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        function hideLoading() {
            // Remove loading overlay
            const overlay = document.querySelector('.position-fixed');
            if (overlay) {
                overlay.remove();
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
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: 5000
            });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                document.body.removeChild(toast);
            });
        }
    </script>
</body>
</html>
