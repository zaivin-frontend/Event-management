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

// Get admin's avatar and additional data
$query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_data = $result->fetch_assoc();

// Set default values if admin data is not found
$admin_avatar = null;
$admin_status = 'active';
$last_login = null;

if ($admin_data) {
    $admin_avatar = $admin_data['avatar'] ?? null;
    $admin_status = $admin_data['status'] ?? 'active';
    $last_login = $admin_data['last_login'] ?? null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $new_name = trim($_POST['name']);
                $new_email = trim($_POST['email']);
                $current_password = $_POST['current_password'];
                
                // Verify current password
                $query = "SELECT password FROM admins WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                
                if (password_verify($current_password, $admin['password'])) {
                    // Update profile
                    $query = "UPDATE admins SET name = ?, email = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssi", $new_name, $new_email, $admin_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['admin_name'] = $new_name;
                        $_SESSION['admin_email'] = $new_email;
                        $success = true;
                        $message = 'Profile updated successfully';
                    } else {
                        $message = 'Error updating profile';
                    }
                } else {
                    $message = 'Current password is incorrect';
                }
                break;

            case 'update_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verify current password
                $query = "SELECT password FROM admins WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                
                if (password_verify($current_password, $admin['password'])) {
                    if ($new_password === $confirm_password) {
                        // Validate password requirements
                        $min_length = get_setting('password_min_length', 8);
                        $require_special = is_setting_enabled('require_special_chars');
                        
                        if (strlen($new_password) < $min_length) {
                            $message = "Password must be at least {$min_length} characters long";
                        } elseif ($require_special && !preg_match('/[^A-Za-z0-9]/', $new_password)) {
                            $message = "Password must contain at least one special character";
                        } else {
                            // Update password
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $query = "UPDATE admins SET password = ? WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("si", $hashed_password, $admin_id);
                            
                            if ($stmt->execute()) {
                                $success = true;
                                $message = 'Password updated successfully';
                            } else {
                                $message = 'Error updating password';
                            }
                        }
                    } else {
                        $message = 'New passwords do not match';
                    }
                } else {
                    $message = 'Current password is incorrect';
                }
                break;

            case 'update_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    // Debug information
                    error_log("File upload attempt - Type: " . $_FILES['avatar']['type'] . ", Size: " . $_FILES['avatar']['size']);
                    
                    if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                        $message = 'Invalid file type. Please upload a JPG, PNG, or GIF image.';
                        error_log("Invalid file type: " . $_FILES['avatar']['type']);
                    } elseif ($_FILES['avatar']['size'] > $max_size) {
                        $message = 'File is too large. Maximum size is 5MB.';
                        error_log("File too large: " . $_FILES['avatar']['size']);
                    } else {
                        $upload_dir = '../uploads/avatars/';
                        if (!file_exists($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                $message = 'Error creating upload directory';
                                error_log("Failed to create directory: " . $upload_dir);
                            }
                        }
                        
                        if (is_writable($upload_dir)) {
                            $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                            $new_filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                                // Delete old avatar if exists
                                $query = "SELECT avatar FROM admins WHERE id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("i", $admin_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $old_avatar_data = $result->fetch_assoc();
                                
                                if ($old_avatar_data && isset($old_avatar_data['avatar']) && $old_avatar_data['avatar']) {
                                    $old_avatar_path = '../' . $old_avatar_data['avatar'];
                                    if (file_exists($old_avatar_path)) {
                                        unlink($old_avatar_path);
                                    }
                                }
                                
                                // Update database
                                $avatar_path = 'uploads/avatars/' . $new_filename;
                                $query = "UPDATE admins SET avatar = ? WHERE id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("si", $avatar_path, $admin_id);
                                
                                if ($stmt->execute()) {
                                    $_SESSION['admin_avatar'] = $avatar_path;
                                    $success = true;
                                    $message = 'Avatar updated successfully';
                                    error_log("Avatar updated successfully for admin ID: " . $admin_id);
                                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                                    exit();
                                } else {
                                    $message = 'Error updating avatar in database';
                                    error_log("Database update failed: " . $stmt->error);
                                    unlink($upload_path);
                                }
                            } else {
                                $message = 'Error uploading file. Please try again.';
                                error_log("Failed to move uploaded file to: " . $upload_path);
                            }
                        } else {
                            $message = 'Upload directory is not writable';
                            error_log("Directory not writable: " . $upload_dir);
                        }
                    }
                } else {
                    $message = 'Please select a file to upload';
                    if (isset($_FILES['avatar'])) {
                        error_log("File upload error code: " . $_FILES['avatar']['error']);
                    }
                }
                break;
        }
    }
}

// Get admin's recent activity
$query = "SELECT 
            'Registration' as type,
            CONCAT(u.first_name, ' ', u.last_name, ' - ', e.title) as description,
            er.registration_date as date
          FROM event_registrations er
          JOIN events e ON er.event_id = e.id
          JOIN users u ON er.user_id = u.id
          WHERE er.user_id = ?
          ORDER BY date DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_activity = [];

if ($result) {
    $recent_activity = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Profile - CDM Event Management</title>
    
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

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .profile-card .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }

        .profile-card .card-body {
            padding: 1.5rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1rem;
            background: var(--primary-color);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Activity Timeline */
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-color);
            opacity: 0.2;
        }

        .activity-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--primary-color);
            border: 2px solid white;
        }

        .activity-item:last-child {
            padding-bottom: 0;
        }

        .activity-item .text-muted {
            font-size: 0.875rem;
        }

        /* Form Controls */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(4, 71, 33, 0.25);
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

        .preview-container {
            margin: 1rem 0;
            text-align: center;
        }

        #avatarPreview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 50%;
            object-fit: cover;
        }

        .avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            background: var(--primary-color);
        }

        .avatar-upload-btn {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .avatar-upload-btn input[type="file"] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            cursor: pointer;
            display: block;
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
                <a href="adminDash.php" class="sidebar-link">
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
                <a href="users/manageUsers.php" class="sidebar-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="reports/reports.php" class="sidebar-link">
                    <i class="bi bi-graph-up"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="analytics/analytics.php" class="sidebar-link">
                    <i class="bi bi-bar-chart"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="settings/settings.php" class="sidebar-link">
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
                <h1 class="topbar-title">Profile</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($admin_name); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item active" href="profile.php">
                            <i class="bi bi-person me-2"></i>Profile
                        </a></li>
                        <li><a class="dropdown-item" href="settings/settings.php">
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
            <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-4">
                    <div class="profile-card">
                        <div class="card-body text-center">
                            <div class="profile-avatar">
                                <?php if ($admin_avatar && file_exists('../' . $admin_avatar)): ?>
                                    <img src="../<?php echo htmlspecialchars($admin_avatar); ?>" alt="Profile Avatar" class="rounded-circle">
                                <?php else: ?>
                                    <div class="avatar-initials">
                                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($admin_name); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($admin_email); ?></p>
                            <?php if ($last_login): ?>
                                <p class="text-muted small mb-3">Last login: <?php echo date('M d, Y H:i', strtotime($last_login)); ?></p>
                            <?php endif; ?>
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                                    <i class="bi bi-pencil me-2"></i>Edit Profile
                                </button>
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateAvatarModal">
                                    <i class="bi bi-camera me-2"></i>Change Avatar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="profile-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Account Security</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid">
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updatePasswordModal">
                                    <i class="bi bi-key me-2"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <?php if (!empty($recent_activity)): ?>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0">
                                                    <?php
                                                    $icon = 'bi-circle';
                                                    $icon_class = 'text-muted';
                                                    
                                                    switch ($activity['type']) {
                                                        case 'Registration':
                                                            $icon = 'bi-person-plus';
                                                            $icon_class = 'text-primary';
                                                            break;
                                                        default:
                                                            $icon = 'bi-circle';
                                                            $icon_class = 'text-muted';
                                                    }
                                                    ?>
                                                    <i class="bi <?php echo $icon; ?> <?php echo $icon_class; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($activity['type']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php 
                                                    if (isset($activity['date'])) {
                                                        echo date('M d, Y H:i', strtotime($activity['date']));
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <p class="text-muted mb-0">
                                                <?php echo isset($activity['description']) ? htmlspecialchars($activity['description']) : ''; ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                        No recent activity
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($admin_name); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($admin_email); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Password Modal -->
    <div class="modal fade" id="updatePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Avatar Modal -->
    <div class="modal fade" id="updateAvatarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Avatar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_avatar">
                        <div class="mb-3">
                            <label class="form-label">Choose Image</label>
                            <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/png,image/gif" required>
                            <div class="form-text">Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF</div>
                        </div>
                        <div class="text-center">
                            <div class="preview-container mb-3" style="display: none;">
                                <img id="avatarPreview" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Avatar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebar();
            
            const avatarInput = document.querySelector('input[name="avatar"]');
            const previewContainer = document.querySelector('.preview-container');
            const avatarPreview = document.getElementById('avatarPreview');
            const avatarForm = document.getElementById('avatarForm');
            
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            alert('Please select a valid image file (JPG, PNG, or GIF)');
                            this.value = '';
                            return;
                        }
                        
                        // Validate file size (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File is too large. Maximum size is 5MB.');
                            this.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            avatarPreview.src = e.target.result;
                            previewContainer.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    } else {
                        previewContainer.style.display = 'none';
                    }
                });
            }
            
            if (avatarForm) {
                avatarForm.addEventListener('submit', function(e) {
                    const fileInput = this.querySelector('input[type="file"]');
                    if (fileInput.files.length === 0) {
                        e.preventDefault();
                        alert('Please select an image to upload');
                        return;
                    }
                    
                    const file = fileInput.files[0];
                    if (file.size > 5 * 1024 * 1024) {
                        e.preventDefault();
                        alert('File is too large. Maximum size is 5MB.');
                        return;
                    }
                });
            }
        });

        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth < 768 && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle?.contains(e.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>
