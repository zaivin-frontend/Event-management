<?php
session_start();
require_once '../database/config.php';

// Get initial categories for the filter
try {
    $stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get upcoming events with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$events_per_page = 6;
$offset = ($page - 1) * $events_per_page;

try {
    $stmt = $conn->prepare("
        SELECT e.*, c.name as category_name, 
               (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
               (SELECT COUNT(*) FROM events WHERE start_date >= CURDATE()) as total_events
        FROM events e
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN categories c ON ec.category_id = c.id
        WHERE e.start_date >= CURDATE()
        ORDER BY e.start_date ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $events_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $upcoming_events = $result->fetch_all(MYSQLI_ASSOC);

    // Get total pages
    $total_events = $upcoming_events[0]['total_events'] ?? 0;
    $total_pages = ceil($total_events / $events_per_page);
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $upcoming_events = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>CDM View Events - Event Management System</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../home.css">
    <link rel="stylesheet" href="../assets/css/view.css" />
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../home.php">
                <i class="bi bi-calendar"></i>
                <span>CDM Events</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../home.php">
                            <i class="bi bi-house"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="./view.php">
                            <i class="bi bi-calendar"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./login.php">
                            <i class="bi bi-box-arrow-in-right"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-dark text-primary px-3 ms-2" href="./register.php">
                            <i class="bi bi-person-plus"></i>Register
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#adminLoginModal">
                            <i class="bi bi-person-fill-lock"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Login Modal -->
    <div class="modal fade" id="adminLoginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content admin-login-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-fill-lock me-2"></i>Admin Portal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-fill-lock fs-1 text-primary mb-3"></i>
                        <h4 class="fw-bold">Secure Login</h4>
                        <p class="text-muted">Enter your credentials to access the admin panel</p>
                    </div>
                    <form id="adminLoginForm" action="../handle/handleLogin.php" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="admin_login" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-envelope me-2"></i>Email
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope text-muted"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       placeholder="Enter your email"
                                       required 
                                       autocomplete="email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-key me-2"></i>Password
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       name="password" 
                                       placeholder="Enter your password"
                                       required 
                                       autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">Please enter your password</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                                <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                            </div>
                            <a href="admin/forgot-password.php" class="text-primary text-decoration-none">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield me-2"></i>Secure connection
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <main class="container mt-5 pt-4">
        <div id="errorAlert" class="alert alert-danger alert-dismissible fade d-none" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><span id="errorMessage"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="filter-section">
                    <h5 class="mb-3">Filter Events</h5>
                    <form id="filterForm">
                        <!-- Search -->
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input 
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    placeholder="Search events..."
                                />
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter-circle-fill me-2"></i>Apply Filters
                        </button>
                    </form>
                </div>
            </div>

            <!-- Events Grid -->
            <div class="col-lg-9">
                <div class="section-header">
                    <h2>Events</h2>
                    <span class="event-count"><?php echo count($upcoming_events); ?> events</span>
                </div>

                <div class="row row-cols-1 row-cols-md-3 g-4 mb-5">
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
                                    <?php if ($event['image']): ?>
                                        <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                                             class="card-img-top event-image" 
                                             alt="<?php echo htmlspecialchars($event['title']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/events/default.jpg" 
                                             class="card-img-top event-image" 
                                             alt="Default Event Image">
                                    <?php endif; ?>
                                    
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
                                                <?php echo $event['registration_count']; ?> registered
                                            </span>
                                            <a href="event-details.php?id=<?php echo $event['id']; ?>" 
                                               class="btn btn-primary">
                                                <i class="bi bi-eye"></i> View Details
                                            </a>
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
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>CDM Events</h5>
                    <p class="text-muted">Your premier event management platform</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> CDM Events. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            const errorAlert = document.getElementById('errorAlert');
            const errorMessage = document.getElementById('errorMessage');

            // Function to show error
            function showError(message) {
                errorMessage.textContent = message;
                errorAlert.classList.remove('d-none');
                setTimeout(() => {
                    errorAlert.classList.add('d-none');
                }, 5000);
            }

            // Handle form submission
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(filterForm);
                const params = {};
                for (let [key, value] of formData.entries()) {
                    if (value) params[key] = value;
                }
                
                // Redirect to the filtered page
                const queryString = new URLSearchParams(params).toString();
                window.location.href = `view.php?${queryString}`;
            });
        });

        // Function to toggle password visibility
        function togglePassword(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
</body>
</html>
