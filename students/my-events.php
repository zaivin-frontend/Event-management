<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$stmt = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../pages/login.php");
    exit();
}

// Get user's registered events with pagination and additional details
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$events_per_page = 9;
$offset = ($page - 1) * $events_per_page;

// Get total count of registered events
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM event_registrations er 
    WHERE er.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_events = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_events / $events_per_page);

// Get registered events with enhanced details
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name, er.status as registration_status,
           er.registration_date,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
           u.first_name as organizer_first_name, u.last_name as organizer_last_name,
           ep.status as payment_status, ep.payment_method, ep.reference_number,
           ep.amount as payment_amount
    FROM events e
    LEFT JOIN event_categories ec ON e.id = ec.event_id
    LEFT JOIN categories c ON ec.category_id = c.id
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.user_id = ?
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN event_payments ep ON e.id = ep.event_id AND ep.user_id = ?
    WHERE er.user_id = ?
    ORDER BY e.start_date ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $events_per_page, $offset);
$stmt->execute();
$registered_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event categories for filter
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/styles/students.css">
    <style>
        .event-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .category-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            background: rgba(255,255,255,0.9);
            color: #333;
            font-size: 0.8rem;
            backdrop-filter: blur(5px);
        }

        .registration-status {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            backdrop-filter: blur(5px);
        }

        .status-registered {
            background-color: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.9);
            color: black;
        }

        .status-cancelled {
            background-color: rgba(220, 53, 69, 0.9);
            color: white;
        }

        .event-image {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .event-card:hover .event-image {
            transform: scale(1.05);
        }

        .event-details {
            padding: 1.5rem;
        }

        .event-date, .event-location {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .event-description {
            color: #666;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .registration-date {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-box input {
            padding-left: 45px;
            border-radius: 25px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .search-box input:focus {
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            border-color: #80bdff;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .filter-section h3 {
            color: #044721;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #044721;
        }

        .status-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .status-filter .btn {
            border-radius: 20px;
            padding: 8px 20px;
            transition: all 0.3s;
        }

        .status-filter .btn.active {
            background-color: #044721;
            color: white;
            transform: scale(1.05);
        }

        .category-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .category-filter .btn {
            border-radius: 20px;
            padding: 8px 20px;
            transition: all 0.3s;
        }

        .category-filter .btn.active {
            background-color: #044721;
            color: white;
            transform: scale(1.05);
        }

        .sort-options {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .sort-options select {
            border-radius: 20px;
            padding: 8px 20px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .sort-options select:focus {
            border-color: #044721;
            box-shadow: 0 0 0 0.2rem rgba(4, 71, 33, 0.25);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #044721;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: #044721;
        }

        .event-count {
            background: #044721;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .no-events {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 1rem 0;
        }

        .no-events i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .pagination {
            margin-top: 2rem;
            justify-content: center;
        }

        .pagination .page-link {
            color: #044721;
            border-radius: 5px;
            margin: 0 3px;
        }

        .pagination .page-item.active .page-link {
            background-color: #044721;
            border-color: #044721;
        }

        @media (max-width: 768px) {
            .event-card {
                margin-bottom: 1rem;
            }
            
            .status-filter, .category-filter {
                overflow-x: auto;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }
            
            .status-filter .btn, .category-filter .btn {
                white-space: nowrap;
            }
        }

        .event-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            backdrop-filter: blur(5px);
            z-index: 1;
        }

        .status-upcoming {
            background-color: rgba(0, 123, 255, 0.9);
            color: white;
        }

        .status-ongoing {
            background-color: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .status-completed {
            background-color: rgba(108, 117, 125, 0.9);
            color: white;
        }

        .event-progress {
            height: 5px;
            background: #e9ecef;
            border-radius: 3px;
            margin: 10px 0;
            overflow: hidden;
        }

        .event-progress-bar {
            height: 100%;
            background: #044721;
            transition: width 0.3s ease;
        }

        .event-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .event-stat-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .event-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .event-action-btn {
            flex: 1;
            padding: 0.5rem;
            border-radius: 5px;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            transition: all 0.3s;
        }

        .event-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .action-view {
            background: #0d6efd;
        }

        .action-cancel {
            background: #dc3545;
        }

        .action-feedback {
            background: #6c757d;
        }

        .event-organizer {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .organizer-avatar {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #044721;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .filter-tag {
            background: #e9ecef;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .filter-tag i {
            cursor: pointer;
            color: #666;
        }

        .filter-tag i:hover {
            color: #dc3545;
        }

        .date-range-filter {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .date-range-filter input {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
        }

        .error-message {
            display: none;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            display: none;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-calendar-event"></i> Event Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-events.php">My Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                    <a href="../pages/logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Loading Overlay -->
        <div class="loading-overlay">
            <div class="spinner"></div>
        </div>

        <!-- Messages -->
        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="searchInput" 
                               placeholder="Search events by title, description, or location...">
                    </div>
                    <div class="date-range-filter">
                        <input type="date" class="form-control" id="startDate" placeholder="Start Date">
                        <input type="date" class="form-control" id="endDate" placeholder="End Date">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <h3>Status</h3>
                            <div class="status-filter">
                                <button class="btn btn-outline-primary active" data-status="all">All</button>
                                <button class="btn btn-outline-primary" data-status="pending">Pending</button>
                                <button class="btn btn-outline-primary" data-status="registered">Registered</button>
                                <button class="btn btn-outline-primary" data-status="cancelled">Cancelled</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3>Category</h3>
                            <div class="category-filter">
                                <button class="btn btn-outline-primary active" data-category="all">All</button>
                                <?php foreach ($categories as $category): ?>
                                    <button class="btn btn-outline-primary" data-category="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="filter-tags" id="activeFilters"></div>
                </div>
            </div>
        </div>

        <!-- My Events Section -->
        <div class="section-header">
            <h2>My Events</h2>
            <span class="event-count"><?php echo $total_events; ?> events</span>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4" id="registeredEvents">
            <?php if (empty($registered_events)): ?>
                <div class="col-12">
                    <div class="no-events">
                        <i class="bi bi-calendar-check"></i>
                        <h4>No registered events</h4>
                        <p>Browse the upcoming events to get started!</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">View Upcoming Events</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($registered_events as $event): ?>
                    <div class="col">
                        <div class="card event-card">
                            <?php if ($event['image']): ?>
                                <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                                     class="card-img-top event-image" alt="<?php echo htmlspecialchars($event['title']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/events/default.jpg" class="card-img-top event-image" alt="Default Event Image">
                            <?php endif; ?>
                            
                            <span class="category-badge"><?php echo htmlspecialchars($event['category_name']); ?></span>
                            <span class="registration-status status-<?php echo $event['registration_status']; ?>">
                                <?php 
                                if ($event['registration_status'] === 'pending' && $event['payment_required']) {
                                    echo 'Payment Pending';
                                } else {
                                    echo ucfirst($event['registration_status']); 
                                }
                                ?>
                            </span>
                            
                            <?php
                            $event_status = '';
                            if (strtotime($event['start_date']) > time()) {
                                $event_status = 'upcoming';
                            } elseif (strtotime($event['end_date']) < time()) {
                                $event_status = 'completed';
                            } else {
                                $event_status = 'ongoing';
                            }
                            ?>
                            <span class="event-status-badge status-<?php echo $event_status; ?>">
                                <?php echo ucfirst($event_status); ?>
                            </span>
                            
                            <div class="event-details">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                
                                <div class="event-stats">
                                    <div class="event-stat-item">
                                        <i class="bi bi-people"></i>
                                        <span><?php echo $event['registration_count']; ?>/<?php echo $event['capacity']; ?> registered</span>
                                    </div>
                                    <div class="event-stat-item">
                                        <i class="bi bi-calendar-check"></i>
                                        <span><?php echo date('M d, Y', strtotime($event['start_date'])); ?></span>
                                    </div>
                                </div>

                                <div class="event-progress">
                                    <div class="event-progress-bar" style="width: <?php echo ($event['registration_count'] / $event['capacity']) * 100; ?>%"></div>
                                </div>
                                
                                <p class="event-location">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>

                                <?php if ($event['payment_required']): ?>
                                    <div class="payment-info mt-2">
                                        <p class="mb-1">
                                            <i class="bi bi-credit-card"></i> 
                                            Payment Status: 
                                            <span class="badge bg-<?php echo $event['payment_status'] === 'approved' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($event['payment_status'] ?? 'pending'); ?>
                                            </span>
                                        </p>
                                        <?php if ($event['payment_method']): ?>
                                            <p class="mb-1">
                                                <i class="bi bi-wallet2"></i> 
                                                Method: <?php echo ucfirst($event['payment_method']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($event['reference_number']): ?>
                                            <p class="mb-1">
                                                <i class="bi bi-upc"></i> 
                                                Reference: <?php echo htmlspecialchars($event['reference_number']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-organizer">
                                    <div class="organizer-avatar">
                                        <?php 
                                        $initials = '';
                                        if (!empty($event['organizer_first_name']) && !empty($event['organizer_last_name'])) {
                                            $initials = strtoupper(substr($event['organizer_first_name'], 0, 1) . substr($event['organizer_last_name'], 0, 1));
                                        } else {
                                            $initials = 'NA';
                                        }
                                        echo $initials;
                                        ?>
                                    </div>
                                    <span>Organized by <?php 
                                        if (!empty($event['organizer_first_name']) && !empty($event['organizer_last_name'])) {
                                            echo htmlspecialchars($event['organizer_first_name'] . ' ' . $event['organizer_last_name']);
                                        } else {
                                            echo 'Not Available';
                                        }
                                    ?></span>
                                </div>
                                
                                <div class="registration-date">
                                    <i class="bi bi-clock-history"></i>
                                    Registered on: <?php echo date('F j, Y', strtotime($event['registration_date'])); ?>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="event-details.php?id=<?php echo $event['id']; ?>" 
                                       class="event-action-btn action-view">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php if ($event['registration_status'] === 'pending'): ?>
                                        <button class="event-action-btn action-cancel" 
                                                onclick="cancelRegistration(<?php echo $event['id']; ?>)">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($event_status === 'completed'): ?>
                                        <button class="event-action-btn action-feedback" 
                                                onclick="provideFeedback(<?php echo $event['id']; ?>)">
                                            <i class="bi bi-star"></i> Feedback
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show loading overlay
        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
        }

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Search functionality with debouncing
        const searchEvents = debounce(function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card');
            let visibleCount = 0;
            
            eventCards.forEach(card => {
                const title = card.querySelector('.card-title').textContent.toLowerCase();
                const description = card.querySelector('.event-description').textContent.toLowerCase();
                const location = card.querySelector('.event-location').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm) || location.includes(searchTerm)) {
                    card.closest('.col').style.display = '';
                    visibleCount++;
                } else {
                    card.closest('.col').style.display = 'none';
                }
            });

            // Show no results message if needed
            if (visibleCount === 0) {
                const section = document.querySelector('#registeredEvents');
                section.innerHTML = `
                    <div class="col-12">
                        <div class="no-events">
                            <i class="bi bi-search"></i>
                            <h4>No events found</h4>
                            <p>Try adjusting your search terms</p>
                        </div>
                    </div>
                `;
            }
        }, 300);

        document.getElementById('searchInput').addEventListener('input', searchEvents);

        // Status filter functionality
        document.querySelectorAll('.status-filter .btn').forEach(button => {
            button.addEventListener('click', function() {
                showLoading();
                
                // Remove active class from all buttons
                document.querySelectorAll('.status-filter .btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const status = this.dataset.status;
                const eventCards = document.querySelectorAll('.event-card');
                let visibleCount = 0;
                
                eventCards.forEach(card => {
                    const statusBadge = card.querySelector('.registration-status').textContent.trim().toLowerCase();
                    
                    if (status === 'all' || statusBadge === status) {
                        card.closest('.col').style.display = '';
                        visibleCount++;
                    } else {
                        card.closest('.col').style.display = 'none';
                    }
                });

                // Show no results message if needed
                if (visibleCount === 0) {
                    const section = document.querySelector('#registeredEvents');
                    section.innerHTML = `
                        <div class="col-12">
                            <div class="no-events">
                                <i class="bi bi-funnel"></i>
                                <h4>No events with this status</h4>
                                <p>Try selecting a different status</p>
                            </div>
                        </div>
                    `;
                }

                hideLoading();
            });
        });

        // Category filter functionality
        document.querySelectorAll('.category-filter .btn').forEach(button => {
            button.addEventListener('click', function() {
                showLoading();
                
                // Remove active class from all buttons
                document.querySelectorAll('.category-filter .btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                const category = this.dataset.category;
                const eventCards = document.querySelectorAll('.event-card');
                let visibleCount = 0;
                
                eventCards.forEach(card => {
                    const categoryBadge = card.querySelector('.category-badge').textContent;
                    
                    if (category === 'all' || categoryBadge === this.textContent.trim()) {
                        card.closest('.col').style.display = '';
                        visibleCount++;
                    } else {
                        card.closest('.col').style.display = 'none';
                    }
                });

                // Show no results message if needed
                if (visibleCount === 0) {
                    const section = document.querySelector('#registeredEvents');
                    section.innerHTML = `
                        <div class="col-12">
                            <div class="no-events">
                                <i class="bi bi-funnel"></i>
                                <h4>No events in this category</h4>
                                <p>Try selecting a different category</p>
                            </div>
                        </div>
                    `;
                }

                hideLoading();
            });
        });

        // Show error message
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }

        // Show success message
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 5000);
        }

        // Date range filter functionality
        document.getElementById('startDate').addEventListener('change', filterByDateRange);
        document.getElementById('endDate').addEventListener('change', filterByDateRange);

        function filterByDateRange() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) return;
            
            showLoading();
            
            const eventCards = document.querySelectorAll('.event-card');
            let visibleCount = 0;
            
            eventCards.forEach(card => {
                const eventDate = new Date(card.querySelector('.event-stat-item:nth-child(2) span').textContent);
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (eventDate >= start && eventDate <= end) {
                    card.closest('.col').style.display = '';
                    visibleCount++;
                } else {
                    card.closest('.col').style.display = 'none';
                }
            });

            updateActiveFilters('Date Range', `${startDate} to ${endDate}`);
            hideLoading();
        }

        // Update active filters display
        function updateActiveFilters(type, value) {
            const filtersContainer = document.getElementById('activeFilters');
            const filterTag = document.createElement('div');
            filterTag.className = 'filter-tag';
            filterTag.innerHTML = `
                ${type}: ${value}
                <i class="bi bi-x" onclick="removeFilter(this)"></i>
            `;
            filtersContainer.appendChild(filterTag);
        }

        // Remove filter tag
        function removeFilter(element) {
            element.parentElement.remove();
            // Reset the corresponding filter
            if (element.parentElement.textContent.includes('Date Range')) {
                document.getElementById('startDate').value = '';
                document.getElementById('endDate').value = '';
                filterByDateRange();
            }
        }

        // Provide feedback function
        function provideFeedback(eventId) {
            const rating = prompt('Please rate the event (1-5 stars):');
            if (!rating || rating < 1 || rating > 5) {
                showError('Please provide a valid rating between 1 and 5.');
                return;
            }

            const comment = prompt('Please provide your feedback (optional):');
            
            showLoading();
            
            fetch('../api/submit-feedback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    rating: rating,
                    comment: comment
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Feedback submitted successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showError(data.message || 'Failed to submit feedback.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('An error occurred. Please try again.');
            });
        }

        // Cancel registration function with improved error handling
        function cancelRegistration(eventId) {
            if (confirm('Are you sure you want to cancel your registration for this event?')) {
                showLoading();
                
                fetch('../api/cancel-registration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event_id: eventId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showSuccess('Registration cancelled successfully!');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showError(data.message || 'Failed to cancel registration.');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showError('An error occurred. Please try again.');
                });
            }
        }
    </script>
</body>
</html>
