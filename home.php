<?php 
session_start();
require_once 'database/config.php';

    // fetch upcoming events
    try {
        $stmt = $conn->prepare("
            SELECT e.*, c.name as category_name, 
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count
            FROM events e
            LEFT JOIN event_categories ec ON e.id = ec.event_id
            LEFT JOIN categories c ON ec.category_id = c.id
            WHERE e.start_date >= CURDATE()
            GROUP BY e.id
            ORDER BY e.start_date ASC
            LIMIT 3
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $upcoming_events = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $upcoming_events = [];
        error_log("Error fetching upcoming events: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CDM Event Management System - Join us in creating memorable experiences through our diverse range of events">
    <title>CDM Event Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link href="home.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="home.php">
                <i class="bi bi-calendar"></i>
                <span>CDM Events</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="home.php">
                            <i class="bi bi-house"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./pages/view.php">
                            <i class="bi bi-calendar"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./pages/login.php">
                            <i class="bi bi-box-arrow-in-right"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-dark text-primary px-3 ms-2" href="./pages/register.php">
                            <i class="bi bi-person-plus"></i>Register
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="./pages/adminLogin.php">
                            <i class="bi bi-person-fill-lock"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Welcome to CDM Event Management</h1>
                    <p class="lead mb-4">Join us in creating memorable experiences through our diverse range of events. From academic competitions to cultural celebrations, we bring the CDM community together.</p>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="pages/register.php" class="btn btn-primary btn-lg px-4 me-md-2">
                            <i class="bi bi-person-plus me-2"></i>Get Started
                        </a>
                        <a href="#events" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-calendar me-2"></i>View Events
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="assets/images/hero-image.jpg" alt="CDM Events" class="img-fluid rounded shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section id="events" class="py-5 bg-light">
        <div class="container">
            <div class="section-header">
                <h2>Upcoming Events</h2>
                <span class="event-count"><?php echo count($upcoming_events); ?> events</span>
            </div>
            <div class="row g-4">
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <div class="col-md-4">
                            <div class="card event-card">
                                <?php if ($event['image']): ?>
                                    <img src="assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                                         class="card-img-top event-image" 
                                         alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <?php else: ?>
                                    <img src="assets/images/events/default.jpg" 
                                         class="card-img-top event-image" 
                                         alt="Default Event Image">
                                <?php endif; ?>
                                
                                <span class="category-badge"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                    <p class="event-date">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                                    </p>
                                    <p class="event-location">
                                        <i class="bi bi-geo-alt"></i> 
                                        <?php echo htmlspecialchars($event['location']); ?>
                                    </p>
                                    <p class="event-description"><?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="badge bg-primary">
                                            <i class="bi bi-people"></i> 
                                            <?php echo $event['registration_count']; ?> registered
                                        </span>
                                        <a href="pages/event-details.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-primary">
                                            <i class="bi bi-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="no-events">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No upcoming events</h4>
                            <p class="text-muted">Check back later for new events!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-4">
                <a href="./pages/view.php" class="btn btn-outline-primary">
                    <i class="bi bi-calendar me-2"></i>View All Events
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose CDM Events?</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-calendar-check fs-1 mb-3"></i>
                            <h3 class="h5 mb-3">Easy Registration</h3>
                            <p class="text-muted mb-0">Simple and quick registration process for all events. Join with just a few clicks!</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-people fs-1 mb-3"></i>
                            <h3 class="h5 mb-3">Team Management</h3>
                            <p class="text-muted mb-0">Create and manage teams for various competitions. Collaborate effectively!</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="bi bi-trophy fs-1 mb-3"></i>
                            <h3 class="h5 mb-3">Track Achievements</h3>
                            <p class="text-muted mb-0">Keep track of your team's accomplishments and progress. Celebrate success!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join our community and be part of exciting events!</p>
            <a href="pages/register.php" class="btn btn-light btn-lg px-4">
                <i class="bi bi-person-plus me-2"></i>Register Now
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="bi bi-calendar me-2"></i>CDM Events</h5>
                    <p class="mb-0">Creating memorable experiences through events.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#events" class="text-light text-decoration-none">Events</a></li>
                        <li><a href="#features" class="text-light text-decoration-none">Features</a></li>
                        <li><a href="./pages/login.php" class="text-light text-decoration-none">Login</a></li>
                        <li><a href="./pages/register.php" class="text-light text-decoration-none">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-4 text-md-end">
                    <h5>Contact Us</h5>
                    <p class="mb-0">
                        <i class="bi bi-envelope me-2"></i>events@cdm.edu<br>
                        <i class="bi bi-telephone me-2"></i>+63-905-272-0918
                    </p>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> CDM Event Management. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-light me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-light me-3"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-light me-3"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-light"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
