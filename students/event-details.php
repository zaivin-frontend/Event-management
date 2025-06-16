<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_GET['id'] ?? null;

if (!$event_id) {
    header("Location: dashboard.php");
    exit();
}

// Get user information
$stmt = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get event details with category and registration count
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
           (SELECT status FROM event_registrations WHERE event_id = e.id AND user_id = ?) as user_registration_status
    FROM events e
    LEFT JOIN event_categories ec ON e.id = ec.event_id
    LEFT JOIN categories c ON ec.category_id = c.id
    WHERE e.id = ?
");
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    header("Location: dashboard.php");
    exit();
}

// Handle registration
$registration_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if ($event['registration_count'] >= $event['capacity']) {
        $registration_message = '<div class="alert alert-danger">Sorry, this event is already full.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $event_id, $user_id);
        if ($stmt->execute()) {
            $registration_message = '<div class="alert alert-success">Registration successful! Your registration is pending approval.</div>';
            // Refresh event data
            $stmt = $conn->prepare("
                SELECT e.*, c.name as category_name,
                       (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
                       (SELECT status FROM event_registrations WHERE event_id = e.id AND user_id = ?) as user_registration_status
                FROM events e
                LEFT JOIN event_categories ec ON e.id = ec.event_id
                LEFT JOIN categories c ON ec.category_id = c.id
                WHERE e.id = ?
            ");
            $stmt->bind_param("ii", $user_id, $event_id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc();
        } else {
            $registration_message = '<div class="alert alert-danger">Registration failed. Please try again.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Event Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css-students/students.css">
    <link rel="stylesheet" href="../assets/css-students/event-detail.css">
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="spinner"></div>
    </div>

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
                        <a class="nav-link" href="my-events.php">My Events</a>
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

    <!-- Event Header -->
    <div class="event-header">
        <a href="dashboard.php" class="back-button">
            <i class="bi bi-arrow-left"></i> Back to Events
        </a>
        <?php if ($event['image']): ?>
            <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
                 alt="<?php echo htmlspecialchars($event['title']); ?>">
        <?php else: ?>
            <img src="../assets/images/events/default.jpg" alt="Default Event Image">
        <?php endif; ?>
        <span class="category-badge">
            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($event['category_name']); ?>
        </span>
        <div class="event-overlay">
            <div class="container">
                <h1 class="display-4"><?php echo htmlspecialchars($event['title']); ?></h1>
                <div class="event-meta">
                    <div class="event-meta-item">
                        <i class="bi bi-calendar"></i>
                        <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                    </div>
                    <div class="event-meta-item">
                        <i class="bi bi-geo-alt"></i>
                        <?php echo htmlspecialchars($event['location']); ?>
                    </div>
                    <div class="event-meta-item">
                        <i class="bi bi-people"></i>
                        <?php echo $event['registration_count']; ?> / <?php echo $event['capacity']; ?> registered
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <?php if ($registration_message): ?>
            <div class="alert alert-dismissible fade show <?php echo strpos($registration_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>" role="alert">
                <?php echo strip_tags($registration_message, '<div>'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Event Details -->
                <div class="event-details-card">
                    <h2>About This Event</h2>
                    <p class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    
                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <button class="share-facebook" onclick="shareEvent('facebook')">
                            <i class="bi bi-facebook"></i> Share
                        </button>
                        <button class="share-twitter" onclick="shareEvent('twitter')">
                            <i class="bi bi-twitter"></i> Tweet
                        </button>
                        <button class="share-whatsapp" onclick="shareEvent('whatsapp')">
                            <i class="bi bi-whatsapp"></i> Share
                        </button>
                    </div>
                </div>

                <!-- Event Schedule -->
                <div class="event-details-card">
                    <h2>Event Schedule</h2>
                    <div class="event-schedule-item">
                        <i class="bi bi-calendar-check"></i>
                        <span class="schedule-label">Start:</span>
                        <span class="schedule-time"><?php echo date('F j, Y g:i A', strtotime($event['start_date'])); ?></span>
                    </div>
                    <div class="event-schedule-item">
                        <i class="bi bi-calendar-x"></i>
                        <span class="schedule-label">End:</span>
                        <span class="schedule-time"><?php echo date('F j, Y g:i A', strtotime($event['end_date'])); ?></span>
                    </div>
                </div>

                <!-- Event Location -->
                <div class="event-details-card">
                    <h2>Event Location</h2>
                    <div class="event-location-item">
                        <i class="bi bi-geo-alt"></i>
                        <span class="location-label">Venue:</span>
                        <span class="location-name"><?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                    <div class="event-map" id="eventMap"></div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Registration Card -->
                <div class="event-details-card registration-card">
                    <h3>Registration</h3>
                    <?php if ($event['user_registration_status']): ?>
                        <div class="mb-3">
                            <span class="registration-status">
                                <i class="bi bi-check-circle"></i> 
                                <?php echo ucfirst($event['user_registration_status']); ?>
                            </span>
                        </div>
                        <?php if ($event['user_registration_status'] === 'pending'): ?>
                            <button type="button" onclick="cancelRegistration(<?php echo $event_id; ?>)" 
                                    class="btn btn-danger w-100">
                                Cancel Registration
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($event['registration_count'] < $event['capacity']): ?>
                            <?php if (strtotime($event['registration_deadline']) > time()): ?>
                                <button type="button" onclick="registerForEvent(<?php echo $event_id; ?>)" 
                                        class="btn btn-primary w-100">
                                    Register Now
                                </button>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Registration deadline has passed.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                This event is full.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <hr>

                    <div class="registration-info-item">
                        <i class="bi bi-people"></i>
                        <span class="info-label">Capacity:</span>
                        <span class="info-value"><?php echo $event['capacity']; ?> people</span>
                    </div>
                    <div class="registration-info-item">
                        <i class="bi bi-clock"></i>
                        <span class="info-label">Registration Deadline:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($event['registration_deadline'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
    
    <script>
        // Show loading overlay
        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
        }

        // Initialize Google Map
        function initMap() {
            const location = "<?php echo htmlspecialchars($event['location']); ?>";
            const geocoder = new google.maps.Geocoder();
            
            geocoder.geocode({ address: location }, (results, status) => {
                if (status === "OK") {
                    const map = new google.maps.Map(document.getElementById("eventMap"), {
                        zoom: 15,
                        center: results[0].geometry.location,
                    });
                    
                    new google.maps.Marker({
                        position: results[0].geometry.location,
                        map: map,
                        title: "<?php echo htmlspecialchars($event['title']); ?>"
                    });
                }
            });
        }

        // Share event function
        function shareEvent(platform) {
            const eventTitle = "<?php echo htmlspecialchars($event['title']); ?>";
            const eventUrl = window.location.href;
            let shareUrl = '';

            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(eventUrl)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(eventTitle)}&url=${encodeURIComponent(eventUrl)}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(eventTitle + ' ' + eventUrl)}`;
                    break;
            }

            window.open(shareUrl, '_blank', 'width=600,height=400');
        }

        // Register for event
        function registerForEvent(eventId) {
            if (confirm('Are you sure you want to register for this event?')) {
                showLoading();
                
                fetch('../api/register-event.php', {
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
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to register. Please try again.');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }

        // Cancel registration
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
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel registration. Please try again.');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }
    </script>
</body>
</html>
