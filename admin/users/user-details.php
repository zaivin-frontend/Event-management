<?php
session_start();
require_once '../../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../home.php");
    exit();
}

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: manageUsers.php");
    exit();
}

// Get user details
$stmt = $conn->prepare("
    SELECT u.*, 
           CONCAT(u.first_name, ' ', u.last_name) as full_name,
           COUNT(DISTINCT er.id) as total_registrations,
           COUNT(DISTINCT CASE WHEN er.status = 'confirmed' THEN er.id END) as confirmed_registrations,
           COALESCE(u.last_login, u.created_at) as last_login
    FROM users u
    LEFT JOIN event_registrations er ON u.id = er.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: manageUsers.php");
    exit();
}

// Get user's event registrations
$stmt = $conn->prepare("
    SELECT er.*, 
           e.title as event_title, 
           e.start_date, 
           e.end_date, 
           e.location,
           er.registration_date
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    WHERE er.user_id = ?
    ORDER BY er.registration_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle status update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $new_status = $_POST['status'];
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $user_id);
                
                if ($stmt->execute()) {
                    $message = "User status updated successfully.";
                    $message_type = "success";
                    $user['status'] = $new_status;
                } else {
                    $message = "Error updating user status.";
                    $message_type = "danger";
                }
                break;

            case 'update_registration_status':
                $registration_id = $_POST['registration_id'];
                $new_status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE event_registrations SET status = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("sii", $new_status, $registration_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Registration status updated successfully.";
                    $message_type = "success";
                } else {
                    $message = "Error updating registration status.";
                    $message_type = "danger";
                }
                break;

            case 'export_registrations':
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="user_registrations_' . $user_id . '_' . date('Y-m-d') . '.csv"');
                
                // Create output stream
                $output = fopen('php://output', 'w');
                
                // Add UTF-8 BOM for proper Excel encoding
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Add CSV headers
                fputcsv($output, [
                    'Event Title',
                    'Start Date',
                    'End Date',
                    'Location',
                    'Registration Status',
                    'Registration Date',
                    'Notes'
                ]);
                
                // Get all registrations for export
                $stmt = $conn->prepare("
                    SELECT er.*, e.title as event_title, e.start_date, e.end_date, e.location
                    FROM event_registrations er
                    JOIN events e ON er.event_id = e.id
                    WHERE er.user_id = ?
                    ORDER BY er.registration_date DESC
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $export_registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Add registration data
                foreach ($export_registrations as $reg) {
                    fputcsv($output, [
                        $reg['event_title'],
                        $reg['start_date'] ? date('Y-m-d H:i', strtotime($reg['start_date'])) : 'N/A',
                        $reg['end_date'] ? date('Y-m-d H:i', strtotime($reg['end_date'])) : 'N/A',
                        $reg['location'] ?? 'N/A',
                        ucfirst($reg['status']),
                        $reg['registration_date'] ? date('Y-m-d H:i', strtotime($reg['registration_date'])) : 'N/A',
                        $reg['notes'] ?? ''
                    ]);
                }
                
                fclose($output);
                exit();
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>User Details - CDM Event Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Table Styles */
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

        /* Status indicators */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
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
                <a href="manageUsers.php" class="sidebar-link active">
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
                <h1 class="topbar-title">User Details</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Back Button -->
                <a href="manageUsers.php" class="btn btn-light">
                    <i class="bi bi-arrow-left me-2"></i>Back to Users
                </a>
                
                <!-- User Dropdown -->
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
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- User Profile Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                                <h4 class="card-title mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                                <div class="d-flex justify-content-center gap-2">
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Contact Information</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-envelope me-2"></i>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                <?php if (!empty($user['phone'])): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-telephone me-2"></i>
                                    <span><?php echo htmlspecialchars($user['phone']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Registration Statistics</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded">
                                            <h3 class="mb-0"><?php echo $user['total_registrations']; ?></h3>
                                            <small class="text-muted">Total Events</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 bg-light rounded">
                                            <h3 class="mb-0"><?php echo $user['confirmed_registrations']; ?></h3>
                                            <small class="text-muted">Confirmed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-muted mb-3">Account Information</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-calendar me-2"></i>
                                    <span>Joined <?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-clock-history me-2"></i>
                                    <span>Last login <?php echo date('F j, Y', strtotime($user['last_login'] ?? $user['created_at'] ?? 'now')); ?></span>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="editUser(<?php echo $user_id; ?>)">
                                    <i class="bi bi-pencil me-2"></i>Edit User
                                </button>
                                <form method="POST" class="d-grid">
                                    <input type="hidden" name="action" value="update_status">
                                    <select name="status" class="form-select mb-2" onchange="this.form.submit()">
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </form>
                                <button class="btn btn-danger" onclick="deleteUser(<?php echo $user_id; ?>)">
                                    <i class="bi bi-trash me-2"></i>Delete User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Registrations -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white border-0 p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Event Registrations</h5>
                                <div class="btn-group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="export_registrations">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-download me-2"></i>Export
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($registration['event_title']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $start_date = $registration['start_date'] ? date('M j, Y', strtotime($registration['start_date'])) : 'N/A';
                                            $end_date = $registration['end_date'] ? date('M j, Y', strtotime($registration['end_date'])) : 'N/A';
                                            echo $start_date;
                                            if ($registration['end_date'] && $registration['end_date'] !== $registration['start_date']): 
                                            ?>
                                                <br>
                                                <small class="text-muted">to <?php echo $end_date; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($registration['location'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-dot bg-<?php echo $registration['status'] === 'confirmed' ? 'success' : 'warning'; ?>"></span>
                                            <span class="badge bg-<?php echo $registration['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($registration['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($registration['registration_date'] ?? 'now')); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewEvent(<?php echo $registration['event_id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success" onclick="updateRegistrationStatus(<?php echo $registration['id']; ?>, 'confirmed')">
                                                    <i class="bi bi-check"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="updateRegistrationStatus(<?php echo $registration['id']; ?>, 'cancelled')">
                                                    <i class="bi bi-x"></i>
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

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                    <form id="deleteUserForm" method="POST">
                        <input type="hidden" name="action" value="delete_user">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteUserForm" class="btn btn-danger">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebar();
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

        function editUser(id) {
            window.location.href = `edit-user.php?id=${id}`;
        }

        function deleteUser(id) {
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        function viewEvent(id) {
            window.location.href = `../manageEvents.php?action=view&id=${id}`;
        }

        function updateRegistrationStatus(id, status) {
            // Create form data
            const formData = new FormData();
            formData.append('action', 'update_registration_status');
            formData.append('registration_id', id);
            formData.append('status', status);

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Show success message
                showToast(`Registration status updated to ${status}`, 'success');
                
                // Reload the page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating registration status', 'danger');
            });
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
    </script>
</body>
</html>
