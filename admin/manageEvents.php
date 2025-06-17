<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../home.php");
    exit();
}

// Get admin information
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

$action = $_GET['action'] ?? 'list';
$event_id = $_GET['id'] ?? null;

// Get event categories
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = $_POST['title'];
                $description = $_POST['description'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $location = $_POST['location'];
                $capacity = $_POST['capacity'];
                $registration_deadline = $_POST['registration_deadline'];
                $category_ids = $_POST['categories'] ?? [];
                $status = $_POST['status'];
                $payment_required = $_POST['payment_required'] ?? 0;
                $payment_amount = $_POST['payment_amount'] ?? 0;
                $payment_methods = $_POST['payment_methods'] ?? [];

                // Handle image upload
                $image = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../assets/images/events/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $image = uniqid() . '.' . $file_extension;
                    move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
                }

                // Insert event
                $stmt = $conn->prepare("
                    INSERT INTO events (title, description, start_date, end_date, location, 
                                      capacity, registration_deadline, status, created_by, image,
                                      payment_required, payment_amount, payment_methods)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $payment_methods_str = implode(',', $payment_methods);
                $stmt->bind_param("sssssisissids", $title, $description, $start_date, $end_date, 
                                $location, $capacity, $registration_deadline, $status, $admin_id, $image,
                                $payment_required, $payment_amount, $payment_methods_str);
                
                if ($stmt->execute()) {
                    $event_id = $conn->insert_id;
                    
                    // Insert categories
                    if (!empty($category_ids)) {
                        $stmt = $conn->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
                        foreach ($category_ids as $category_id) {
                            $stmt->bind_param("ii", $event_id, $category_id);
                            $stmt->execute();
                        }
                    }
                    
                    header("Location: manageEvents.php?success=created");
                    exit();
                }
                break;

            case 'update':
                $event_id = $_POST['event_id'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $location = $_POST['location'];
                $capacity = $_POST['capacity'];
                $registration_deadline = $_POST['registration_deadline'];
                $category_ids = $_POST['categories'] ?? [];
                $status = $_POST['status'];
                $payment_required = $_POST['payment_required'] ?? 0;
                $payment_amount = $_POST['payment_amount'] ?? 0;
                $payment_methods = $_POST['payment_methods'] ?? [];

                // Handle image upload
                $image_sql = "";
                $image = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../assets/images/events/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $image = uniqid() . '.' . $file_extension;
                    move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
                    $image_sql = ", image = ?";
                }

                // Update event
                $stmt = $conn->prepare("
                    UPDATE events 
                    SET title = ?, description = ?, start_date = ?, end_date = ?, 
                        location = ?, capacity = ?, registration_deadline = ?, status = ?{$image_sql},
                        payment_required = ?, payment_amount = ?, payment_methods = ?
                    WHERE id = ?
                ");

                $payment_methods_str = implode(',', $payment_methods);
                if ($image) {
                    $stmt->bind_param("sssssisissdsi", $title, $description, $start_date, $end_date, 
                                    $location, $capacity, $registration_deadline, $status, $image,
                                    $payment_required, $payment_amount, $payment_methods_str, $event_id);
                } else {
                    $stmt->bind_param("sssssisdsi", $title, $description, $start_date, $end_date, 
                                    $location, $capacity, $registration_deadline, $status,
                                    $payment_required, $payment_amount, $payment_methods_str, $event_id);
                }
                
                if ($stmt->execute()) {
                    // Update categories
                    $conn->query("DELETE FROM event_categories WHERE event_id = $event_id");
                    if (!empty($category_ids)) {
                        $stmt = $conn->prepare("INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)");
                        foreach ($category_ids as $category_id) {
                            $stmt->bind_param("ii", $event_id, $category_id);
                            $stmt->execute();
                        }
                    }
                    
                    header("Location: manageEvents.php?success=updated");
                    exit();
                }
                break;

            case 'delete':
                $event_id = $_POST['event_id'];
                $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
                $stmt->bind_param("i", $event_id);
                if ($stmt->execute()) {
                    header("Location: manageEvents.php?success=deleted");
                    exit();
                }
                break;
        }
    }
}

// Get event details if editing
$event = null;
if ($action === 'edit' && $event_id) {
    $stmt = $conn->prepare("
        SELECT e.*, GROUP_CONCAT(ec.category_id) as category_ids
        FROM events e
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
}

// Get all events for listing
$events = [];
if ($action === 'list') {
    $stmt = $conn->prepare("
        SELECT e.*, 
               GROUP_CONCAT(c.name) as categories,
               (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count
        FROM events e
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN categories c ON ec.category_id = c.id
        GROUP BY e.id
        ORDER BY e.start_date DESC
    ");
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manage Events - CDM Event Management</title>
    
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

        /* Event Card Styles */
        .event-card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }

        .event-image {
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--light-bg);
            color: var(--dark-color);
            border-radius: 20px;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Form Styles */
        .event-form {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .event-form .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .image-preview {
            max-width: 200px;
            margin-top: 1rem;
        }

        .image-preview img {
            width: 100%;
            border-radius: 8px;
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
                <a href="manageEvents.php" class="sidebar-link active">
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
                <h1 class="topbar-title">Manage Events</h1>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Search -->
                <div class="position-relative d-none d-sm-block">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search events..." style="width: 250px; padding-left: 2.5rem;">
                    <i class="bi bi-search position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
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
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    switch ($_GET['success']) {
                        case 'created':
                            echo "Event created successfully!";
                            break;
                        case 'updated':
                            echo "Event updated successfully!";
                            break;
                        case 'deleted':
                            echo "Event deleted successfully!";
                            break;
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Events List -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Events</h2>
                    <a href="?action=create" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create New Event
                    </a>
                </div>

                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach ($events as $event): ?>
                        <div class="col">
                            <div class="card event-card h-100">
                                <?php if ($event['image']): ?>
                                    <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                                         class="card-img-top event-image" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/events/default.jpg" class="card-img-top event-image" alt="Default Event Image">
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> 
                                            <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> 
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </small>
                                    </p>
                                    <div class="mb-2">
                                        <?php foreach (explode(',', $event['categories']) as $category): ?>
                                            <span class="category-badge"><?php echo htmlspecialchars($category); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-people"></i> 
                                            <?php echo $event['registration_count']; ?> registrations
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <a href="?action=edit&id=<?php echo $event['id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteEvent(<?php echo $event['id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Event Form -->
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card event-form">
                            <div class="card-header bg-white border-0 p-3">
                                <h3 class="card-title mb-0">
                                    <?php echo $action === 'create' ? 'Create New Event' : 'Edit Event'; ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="<?php echo $action === 'create' ? 'create' : 'update'; ?>">
                                    <?php if ($action === 'edit'): ?>
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="title" class="form-label">Event Title</label>
                                        <input type="text" class="form-control" id="title" name="title" required
                                               value="<?php echo $event['title'] ?? ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo $event['description'] ?? ''; ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">Start Date</label>
                                            <input type="datetime-local" class="form-control" id="start_date" name="start_date" required
                                                   value="<?php echo $event['start_date'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">End Date</label>
                                            <input type="datetime-local" class="form-control" id="end_date" name="end_date" required
                                                   value="<?php echo $event['end_date'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" required
                                               value="<?php echo $event['location'] ?? ''; ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="capacity" class="form-label">Capacity</label>
                                            <input type="number" class="form-control" id="capacity" name="capacity" required
                                                   value="<?php echo $event['capacity'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="registration_deadline" class="form-label">Registration Deadline</label>
                                            <input type="datetime-local" class="form-control" id="registration_deadline" name="registration_deadline" required
                                                   value="<?php echo $event['registration_deadline'] ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="categories" class="form-label">Categories</label>
                                        <select class="form-select" id="categories" name="categories[]" multiple required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"
                                                        <?php echo (isset($event['category_ids']) && in_array($category['id'], explode(',', $event['category_ids']))) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="draft" <?php echo (isset($event['status']) && $event['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo (isset($event['status']) && $event['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                            <option value="cancelled" <?php echo (isset($event['status']) && $event['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="payment_required" name="payment_required" value="1"
                                                   <?php echo (isset($event['payment_required']) && $event['payment_required']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="payment_required">
                                                Require Payment
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3 payment-fields" style="display: none;">
                                        <label for="payment_amount" class="form-label">Payment Amount (PHP)</label>
                                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" step="0.01" min="0"
                                               value="<?php echo $event['payment_amount'] ?? '0.00'; ?>">
                                    </div>

                                    <div class="mb-3 payment-fields" style="display: none;">
                                        <label class="form-label">Payment Methods</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="gcash" name="payment_methods[]" value="gcash"
                                                   <?php echo (isset($event['payment_methods']) && strpos($event['payment_methods'], 'gcash') !== false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="gcash">
                                                GCash
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="paymaya" name="payment_methods[]" value="paymaya"
                                                   <?php echo (isset($event['payment_methods']) && strpos($event['payment_methods'], 'paymaya') !== false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="paymaya">
                                                PayMaya
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">Event Image</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*"
                                               <?php echo $action === 'create' ? 'required' : ''; ?>>
                                        <?php if ($action === 'edit' && $event['image']): ?>
                                            <div class="image-preview mt-2">
                                                <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                                                     alt="Current Image">
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <a href="?action=list" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $action === 'create' ? 'Create Event' : 'Update Event'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebar();
            setupSearch();
            setupPaymentFields();
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

        function setupSearch() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const eventCards = document.querySelectorAll('.event-card');
                    
                    eventCards.forEach(card => {
                        const title = card.querySelector('.card-title').textContent.toLowerCase();
                        const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
                        
                        if (title.includes(searchTerm) || description.includes(searchTerm)) {
                            card.closest('.col').style.display = '';
                        } else {
                            card.closest('.col').style.display = 'none';
                        }
                    });
                });
            }
        }

        function setupPaymentFields() {
            const paymentRequired = document.getElementById('payment_required');
            const paymentFields = document.querySelectorAll('.payment-fields');

            function togglePaymentFields() {
                paymentFields.forEach(field => {
                    field.style.display = paymentRequired.checked ? 'block' : 'none';
                });
            }

            paymentRequired.addEventListener('change', togglePaymentFields);
            togglePaymentFields(); // Initial state
        }

        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" value="${eventId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
