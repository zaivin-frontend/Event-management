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

// Get upcoming events with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$events_per_page = 6;
$offset = ($page - 1) * $events_per_page;

$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
           (SELECT COUNT(*) FROM events WHERE start_date >= CURDATE()) as total_events,
           e.payment_required, e.payment_amount, e.payment_methods,
           er.status as user_registration_status,
           ep.status as payment_status
    FROM events e
    LEFT JOIN event_categories ec ON e.id = ec.event_id
    LEFT JOIN categories c ON ec.category_id = c.id
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.user_id = ?
    LEFT JOIN event_payments ep ON e.id = ep.event_id AND ep.user_id = ?
    WHERE e.start_date >= CURDATE()
    ORDER BY e.start_date ASC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iiii", $user_id, $user_id, $events_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_events = $result->fetch_all(MYSQLI_ASSOC);

// Get total pages
$total_events = $upcoming_events[0]['total_events'] ?? 0;
$total_pages = ceil($total_events / $events_per_page);

// Get user's registered events with payment status
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name, er.status as registration_status,
           ep.status as payment_status, ep.payment_method, ep.reference_number,
           ep.amount as payment_amount, ep.created_at as payment_date
    FROM events e
    LEFT JOIN event_categories ec ON e.id = ec.event_id
    LEFT JOIN categories c ON ec.category_id = c.id
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.user_id = ?
    LEFT JOIN event_payments ep ON e.id = ep.event_id AND ep.user_id = ?
    WHERE er.user_id = ?
    ORDER BY e.start_date ASC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$registered_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get event categories
$stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Event Management System</title>
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

        .event-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 15px;
            border-radius: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            font-size: 0.8rem;
            backdrop-filter: blur(5px);
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

        .registration-status {
            position: absolute;
            bottom: 10px;
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

        .category-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
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
            color: #333;
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
            
            .category-filter {
                overflow-x: auto;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
            }
            
            .category-filter .btn {
                white-space: nowrap;
            }
        }

        .date-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .date-filter input {
            border-radius: 20px;
            padding: 8px 15px;
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

        .event-card {
            position: relative;
            overflow: hidden;
        }

        .event-card .card-img-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.1), rgba(0,0,0,0.7));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .event-card:hover .card-img-overlay {
            opacity: 1;
        }

        .event-card .quick-actions {
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            padding: 1rem;
            background: rgba(0,0,0,0.8);
            transition: bottom 0.3s;
        }

        .event-card:hover .quick-actions {
            bottom: 0;
        }

        .payment-method-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #fff;
        }
        .payment-method-card.selected {
            border-color: #044721;
            box-shadow: 0 0 0 2px rgba(4, 71, 33, 0.4);
            background-color: #e6ffe6;
        }
        .payment-method-card[data-method="gcash"].selected {
            border-color: #044721;
            box-shadow: 0 0 0 2px rgba(4, 71, 33, 0.6);
            background-color: #d4f8d4;
        }
        .payment-method-card[data-method="paymaya"].selected {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.4);
            background-color: #e0f2ff;
        }
        .payment-method-card[data-method="card"].selected {
            border-color: #6c757d;
            box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.4);
            background-color: #f0f0f0;
        }
        .payment-method-logo {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .payment-method-label {
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .payment-method-extra {
            margin-left: auto;
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-events.php">My Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name']); ?></span>
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

        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search events by title, description, or location...">
                </div>
            </div>
            <div class="col-md-6">
                <div class="date-filter">
                    <input type="date" class="form-control" id="startDate" placeholder="Start Date">
                    <input type="date" class="form-control" id="endDate" placeholder="End Date">
                </div>
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

        <!-- Upcoming Events Section -->
        <div class="section-header">
            <h2>Upcoming Events</h2>
            <span class="event-count"><?php echo count($upcoming_events); ?> events</span>
        </div>
        <div class="row row-cols-1 row-cols-md-3 g-4 mb-5" id="upcomingEvents">
            <?php if (empty($upcoming_events)): ?>
                <div class="col-12">
                    <div class="no-events">
                        <i class="bi bi-calendar-x"></i>
                        <h4>No upcoming events found</h4>
                        <p>Check back later for new events!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="col">
                        <div class="card event-card">
                            <?php if ($event['image']): 
                                $image_extension = pathinfo($event['image'], PATHINFO_EXTENSION); // Get extension
                                $image_filename = $image_extension ? htmlspecialchars($event['image']) : htmlspecialchars($event['image']) . '.jpg'; // Assume .jpg if no extension
                            ?>
                                <img src="../assets/images/events/<?php echo $image_filename; ?>" 
                                     class="card-img-top event-image" alt="<?php echo htmlspecialchars($event['title']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/events/default.jpg" class="card-img-top event-image" alt="Default Event Image">
                            <?php endif; ?>
                            
                            <div class="card-img-overlay">
                                <div class="quick-actions">
                                    <div class="d-flex justify-content-between">
                                        <a href="event-details.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-light btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php 
                                        $is_fully_registered = false;
                                        if ($event['payment_required']) {
                                            if ($event['user_registration_status'] && $event['payment_status'] === 'approved') {
                                                $is_fully_registered = true;
                                            }
                                        } else {
                                            if ($event['user_registration_status'] === 'confirmed') {
                                                $is_fully_registered = true;
                                            }
                                        }
                                        ?>

                                        <?php if ($is_fully_registered): ?>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="bi bi-check-circle"></i> Registered
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="quickRegister(<?php echo $event['id']; ?>)">
                                                <i class="bi bi-plus-circle"></i> Register
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <span class="category-badge"><?php echo htmlspecialchars($event['category_name']); ?></span>
                            
                            <div class="event-details">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="event-date">
                                    <i class="bi bi-calendar"></i> 
                                    <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                                </p>
                                <p class="event-location">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span class="badge bg-primary">
                                        <i class="bi bi-people"></i> 
                                        <?php echo $event['registration_count']; ?>/<?php echo $event['capacity']; ?> registered
                                    </span>
                                    <?php if ($event['payment_required'] && $event['payment_amount'] > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-currency-dollar"></i> 
                                            ₱<?php echo number_format($event['payment_amount'], 2); ?>
                                        </span>
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
            <nav aria-label="Event pagination">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

        <!-- My Registered Events Section -->
        <div class="section-header">
            <h2>My Registered Events</h2>
            <span class="event-count"><?php echo count($registered_events); ?> events</span>
        </div>
        <div class="row row-cols-1 row-cols-md-3 g-4" id="registeredEvents">
            <?php if (empty($registered_events)): ?>
                <div class="col-12">
                    <div class="no-events">
                        <i class="bi bi-calendar-check"></i>
                        <h4>No registered events</h4>
                        <p>Browse the upcoming events above to get started!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($registered_events as $event): ?>
                    <div class="col">
                        <div class="card event-card">
                            <?php if ($event['image']): 
                                $image_extension = pathinfo($event['image'], PATHINFO_EXTENSION); // Get extension
                                $image_filename = $image_extension ? htmlspecialchars($event['image']) : htmlspecialchars($event['image']) . '.jpg'; // Assume .jpg if no extension
                            ?>
                                <img src="../assets/images/events/<?php echo $image_filename; ?>" 
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
                            
                            <div class="event-details">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="event-date">
                                    <i class="bi bi-calendar"></i> 
                                    <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                                </p>
                                <p class="event-location">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                
                                <?php if ($event['payment_status']): ?>
                                    <div class="payment-info mt-2">
                                        <p class="mb-1">
                                            <i class="bi bi-credit-card"></i> 
                                            Payment Status: 
                                            <span class="badge bg-<?php echo $event['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($event['payment_status']); ?>
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
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <a href="event-details.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-outline-primary">View Details</a>
                                    <?php if ($event['registration_status'] === 'pending'): ?>
                                        <button class="btn btn-danger" 
                                                onclick="cancelRegistration(<?php echo $event['id']; ?>)">
                                            Cancel
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Initialize Stripe
        const stripe = Stripe('pk_test_your_publishable_key'); // Replace with your actual publishable key

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

        // Show loading overlay
        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
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
            const noResultsMessage = document.querySelector('.no-events');
            if (visibleCount === 0 && !noResultsMessage) {
                const section = document.querySelector('#upcomingEvents');
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
                    const section = document.querySelector('#upcomingEvents');
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

        // Date filter functionality
        document.getElementById('startDate').addEventListener('change', filterEvents);
        document.getElementById('endDate').addEventListener('change', filterEvents);

        function filterEvents() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) return;
            
            showLoading();
            
            const eventCards = document.querySelectorAll('.event-card');
            let visibleCount = 0;
            
            eventCards.forEach(card => {
                const eventDate = new Date(card.querySelector('.event-date').textContent.split(': ')[1]);
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (eventDate >= start && eventDate <= end) {
                    card.closest('.col').style.display = '';
                    visibleCount++;
                } else {
                    card.closest('.col').style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                const section = document.querySelector('#upcomingEvents');
                section.innerHTML = `
                    <div class="col-12">
                        <div class="no-events">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No events in selected date range</h4>
                            <p>Try selecting a different date range</p>
                        </div>
                    </div>
                `;
            }

            hideLoading();
        }

        // Quick registration function with improved error handling
        function quickRegister(eventId) {
            if (!confirm('Are you sure you want to register for this event?')) {
                return;
            }
            
            showLoading();
            
            // First check if payment is required
            fetch(`../api/check-payment.php?event_id=${eventId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('quickRegister data received:', data);
                    if (data.payment_required) {
                        hideLoading();
                        showPaymentModal(data);
                    } else {
                        proceedWithRegistration(eventId);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error in quickRegister fetch:', error);
                    alert('Failed to check payment status. Please try again later.');
                });
        }

        function showPaymentModal(data) {
            console.log('showPaymentModal called with data:', data);

            // Remove any existing modal
            const existingModal = document.getElementById('paymentModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Create modal container
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = `
                <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-credit-card me-2"></i>Payment Details
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="payment-info mb-4">
                                    <h6 class="text-success mb-3">Event: ${data.event.title}</h6>
                                    <p class="mb-2"><strong class="text-dark">Amount to Pay:</strong> ₱${parseFloat(data.payment.amount).toFixed(2)}</p>
                                    <p class="mb-0"><strong class="text-dark">Payment Status:</strong> <span class="text-dark">Pending</span></p>
                                </div>

                                <div class="payment-methods mb-4">
                                    <h6 class="text-dark mb-3">Select Payment Method:</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="payment-method-card" data-method="gcash">
                                                <div class="card h-100">
                                                    <div class="card-body text-center">
                                                        <i class="bi bi-phone fs-1 text-success"></i>
                                                        <h6 class="mt-2">GCash</h6>
                                                        <input type="radio" name="paymentMethod" value="gcash" class="d-none">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="payment-method-card" data-method="paymaya">
                                                <div class="card h-100">
                                                    <div class="card-body text-center">
                                                        <i class="bi bi-credit-card fs-1 text-success"></i>
                                                        <h6 class="mt-2">PayMaya</h6>
                                                        <input type="radio" name="paymentMethod" value="paymaya" class="d-none">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="payment-method-card" data-method="card">
                                                <div class="card h-100">
                                                    <div class="card-body text-center">
                                                        <i class="bi bi-bank fs-1 text-success"></i>
                                                        <h6 class="mt-2">Bank Card</h6>
                                                        <input type="radio" name="paymentMethod" value="card" class="d-none">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <form id="paymentForm" style="display: none;">
                                    <input type="hidden" id="paymentEventId" value="${data.event.id}">
                                    <input type="hidden" id="paymentAmount" value="${data.payment.amount}">
                                    <input type="hidden" id="paymentMethod" name="paymentMethod">
                                    
                                    <div id="gcashForm" class="payment-form">
                                        <div class="mb-3">
                                            <label class="form-label">GCash Number</label>
                                            <input type="text" class="form-control" name="gcash_number" 
                                                   pattern="^(09|\+639)\d{9}$" 
                                                   placeholder="Enter your GCash number (e.g., 09123456789)"
                                                   title="Please enter a valid GCash number starting with 09 or +639">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" id="gcashReferenceNumber" class="form-control" 
                                                   name="gcash_reference_number" 
                                                   pattern="^[A-Za-z0-9-]+$"
                                                   placeholder="Enter reference number"
                                                   title="Please enter a valid reference number">
                                        </div>
                                    </div>

                                    <div id="paymayaForm" class="payment-form" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">PayMaya Number</label>
                                            <input type="text" class="form-control" name="paymaya_number" 
                                                   pattern="^(09|\+639)\d{9}$"
                                                   placeholder="Enter your PayMaya number (e.g., 09123456789)"
                                                   title="Please enter a valid PayMaya number starting with 09 or +639">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" id="paymayaReferenceNumber" class="form-control" 
                                                   name="paymaya_reference_number"
                                                   pattern="^[A-Za-z0-9-]+$"
                                                   placeholder="Enter reference number"
                                                   title="Please enter a valid reference number">
                                        </div>
                                    </div>

                                    <div id="cardForm" class="payment-form" style="display: none;">
                                        <div class="mb-3">
                                            <label class="form-label">Card Number</label>
                                            <input type="text" class="form-control" name="card_number" 
                                                   pattern="^\d{16}$"
                                                   placeholder="Enter 16-digit card number"
                                                   title="Please enter a valid 16-digit card number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" id="cardReferenceNumber" class="form-control" 
                                                   name="card_reference_number"
                                                   pattern="^[A-Za-z0-9-]+$"
                                                   placeholder="Enter reference number"
                                                   title="Please enter a valid reference number">
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i class="bi bi-info-circle"></i>
                                        Please ensure all payment details are correct before submitting.
                                    </div>

                                    <button type="submit" class="btn btn-success w-100 mt-3">
                                        <i class="bi bi-check-circle me-2"></i>Submit Payment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Get the modal element
            const modal = modalContainer.firstElementChild;

            // Add to document
            document.body.appendChild(modal);

            // Initialize Bootstrap Modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Payment method card selection logic
            const methodCards = modal.querySelectorAll('.payment-method-card');
            const paymentForm = modal.querySelector('#paymentForm');
            
            methodCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selection from all cards
                    methodCards.forEach(c => {
                        c.classList.remove('selected');
                        c.querySelector('.card').classList.remove('border-success');
                    });
                    
                    // Add selection to clicked card
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                    
                    // Show payment form
                    paymentForm.style.display = 'block';

                    // Hide all payment forms and remove required attribute from their inputs
                    modal.querySelectorAll('.payment-form').forEach(form => {
                        form.style.display = 'none';
                        form.querySelectorAll('input').forEach(input => {
                            input.removeAttribute('required');
                        });
                    });
                    
                    // Show relevant payment form and add required attribute to its inputs
                    const method = this.dataset.method;
                    let currentForm;
                    if (method === 'gcash') {
                        currentForm = modal.querySelector('#gcashForm');
                    } else if (method === 'paymaya') {
                        currentForm = modal.querySelector('#paymayaForm');
                    } else if (method === 'card') {
                        currentForm = modal.querySelector('#cardForm');
                    }

                    if (currentForm) {
                        currentForm.style.display = 'block';
                        currentForm.querySelectorAll('input').forEach(input => {
                            input.setAttribute('required', 'required');
                        });
                    }
                });
            });

            // Ensure previous listeners are removed to prevent multiple bindings
            const formElement = modal.querySelector('#paymentForm');
            if (formElement) {
                // Remove existing listener to prevent duplicates
                const oldListener = formElement.__submitListener;
                if (oldListener) {
                    formElement.removeEventListener('submit', oldListener);
                }

                const newListener = function(e) {
                    e.preventDefault();

                    const eventId = modal.querySelector('#paymentEventId').value;
                    const paymentMethod = modal.querySelector('input[name="paymentMethod"]:checked').value;
                    const paymentAmount = modal.querySelector('#paymentAmount').value;
                    let referenceNumber;
                    let paymentNumber;

                    // Determine which payment form is active and get its values
                    if (paymentMethod === 'gcash') {
                        referenceNumber = modal.querySelector('#gcashReferenceNumber').value;
                        paymentNumber = modal.querySelector('input[name="gcash_number"]').value;
                    } else if (paymentMethod === 'paymaya') {
                        referenceNumber = modal.querySelector('#paymayaReferenceNumber').value;
                        paymentNumber = modal.querySelector('input[name="paymaya_number"]').value;
                    } else if (paymentMethod === 'card') {
                        referenceNumber = modal.querySelector('#cardReferenceNumber').value;
                        paymentNumber = modal.querySelector('input[name="card_number"]').value;
                    }

                    // Validate form data
                    if (!referenceNumber || !paymentNumber) {
                        showError('Please fill in all required payment details.');
                        return;
                    }

                    // Show loading state
                    const submitButton = formElement.querySelector('button[type="submit"]');
                    const originalButtonText = submitButton.innerHTML;
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>Processing...';

                    // Prepare form data
                    const formData = new FormData();
                    formData.append('event_id', eventId);
                    formData.append('payment_method', paymentMethod);
                    formData.append('reference_number', referenceNumber);
                    formData.append('payment_number', paymentNumber);
                    formData.append('amount', paymentAmount);

                    // Submit payment
                    fetch('../api/submit-payment.php', {
                        method: 'POST',
                        body: formData,
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errorData => {
                                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response.success) {
                            showSuccess('Payment submitted successfully! Your payment is pending verification.');
                            bsModal.hide();
                            
                            // If receipt URL is provided, open it in a new tab
                            if (response.payment_data && response.payment_data.receipt_url) {
                                window.open(response.payment_data.receipt_url, '_blank');
                            }
                            
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showError(response.message || 'Failed to process payment. Please try again.');
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalButtonText;
                        }
                    })
                    .catch(error => {
                        console.error('Error in payment submission:', error);
                        showError(`An error occurred during payment submission: ${error.message}. Please try again later.`);
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                    });
                };

                formElement.addEventListener('submit', newListener);
                formElement.__submitListener = newListener;
            }
            
            // Clean up modal when hidden
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
        }

        // Proceed with registration
        function proceedWithRegistration(eventId) {
            showLoading();
            
            const formData = new FormData();
            formData.append('event_id', eventId);
            
            fetch('../api/register-event.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Registration successful!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showError(data.message || 'Failed to register. Please try again.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Registration failed. Please try again later.');
            });
        }

        // Cancel registration function with improved error handling
        function cancelRegistration(eventId) {
            if (!confirm('Are you sure you want to cancel your registration for this event?')) {
                return;
            }
            
            showLoading();
            
            const formData = new FormData();
            formData.append('event_id', eventId);
            
            fetch('../api/cancel-registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Registration cancelled successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showError(data.message || 'Failed to cancel registration. Please try again.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Cancellation failed. Please try again later.');
            });
        }
    </script>
</body>
</html>