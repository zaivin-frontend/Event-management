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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                $site_name = $_POST['site_name'] ?? '';
                $site_description = $_POST['site_description'] ?? '';
                $contact_email = $_POST['contact_email'] ?? '';
                
                $query = "UPDATE settings SET 
                         setting_value = CASE 
                            WHEN setting_key = 'site_name' THEN ?
                            WHEN setting_key = 'site_description' THEN ?
                            WHEN setting_key = 'contact_email' THEN ?
                         END
                         WHERE setting_key IN ('site_name', 'site_description', 'contact_email')";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sss", $site_name, $site_description, $contact_email);
                
                if ($stmt->execute()) {
                    $success = true;
                    $message = 'General settings updated successfully';
                } else {
                    $message = 'Error updating general settings';
                }
                break;

            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $registration_notifications = isset($_POST['registration_notifications']) ? 1 : 0;
                $event_reminders = isset($_POST['event_reminders']) ? 1 : 0;
                
                $query = "UPDATE settings SET 
                         setting_value = CASE 
                            WHEN setting_key = 'email_notifications' THEN ?
                            WHEN setting_key = 'registration_notifications' THEN ?
                            WHEN setting_key = 'event_reminders' THEN ?
                         END
                         WHERE setting_key IN ('email_notifications', 'registration_notifications', 'event_reminders')";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iii", $email_notifications, $registration_notifications, $event_reminders);
                
                if ($stmt->execute()) {
                    $success = true;
                    $message = 'Notification settings updated successfully';
                } else {
                    $message = 'Error updating notification settings';
                }
                break;

            case 'update_security':
                $password_min_length = $_POST['password_min_length'] ?? 8;
                $require_special_chars = isset($_POST['require_special_chars']) ? 1 : 0;
                $session_timeout = $_POST['session_timeout'] ?? 30;
                
                $query = "UPDATE settings SET 
                         setting_value = CASE 
                            WHEN setting_key = 'password_min_length' THEN ?
                            WHEN setting_key = 'require_special_chars' THEN ?
                            WHEN setting_key = 'session_timeout' THEN ?
                         END
                         WHERE setting_key IN ('password_min_length', 'require_special_chars', 'session_timeout')";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iii", $password_min_length, $require_special_chars, $session_timeout);
                
                if ($stmt->execute()) {
                    $success = true;
                    $message = 'Security settings updated successfully';
                } else {
                    $message = 'Error updating security settings';
                }
                break;
        }
    }
}

// Get current settings
$query = "SELECT setting_key, setting_value FROM settings";
$result = $conn->query($query);
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Settings - CDM Event Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
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

        /* Settings Cards */
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .settings-card .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }

        .settings-card .card-body {
            padding: 1.5rem;
        }

        /* Form Controls */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(4, 71, 33, 0.25);
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
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
                <a href="../analytics/analytics.php" class="sidebar-link">
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
                <a href="settings.php" class="sidebar-link active">
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
                <h1 class="topbar-title">Settings</h1>
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
                        <li><a class="dropdown-item" href="settings.php">
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
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- General Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_general">
                        <div class="mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" name="site_name" 
                                   value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Site Description</label>
                            <textarea class="form-control" name="site_description" rows="3"><?php 
                                echo htmlspecialchars($settings['site_description'] ?? ''); 
                            ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" name="contact_email" 
                                   value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Notification Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_notifications">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_notifications" 
                                       <?php echo ($settings['email_notifications'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Enable Email Notifications</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="registration_notifications" 
                                       <?php echo ($settings['registration_notifications'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Enable Registration Notifications</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="event_reminders" 
                                       <?php echo ($settings['event_reminders'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Enable Event Reminders</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Security Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_security">
                        <div class="mb-3">
                            <label class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-control" name="password_min_length" 
                                   value="<?php echo htmlspecialchars($settings['password_min_length'] ?? '8'); ?>" 
                                   min="6" max="32" required>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="require_special_chars" 
                                       <?php echo ($settings['require_special_chars'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Require Special Characters in Passwords</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" name="session_timeout" 
                                   value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" 
                                   min="5" max="1440" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebar();
            setupNotifications();
        });

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

        // You may need a showToast function if you use it elsewhere in settings.php
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
</body>
</html>
