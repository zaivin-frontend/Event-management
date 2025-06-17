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

// Get event details with category, registration count, and user's registration/payment status
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count,
           er.status as user_registration_status,
           ep.status as payment_status
    FROM events e
    LEFT JOIN event_categories ec ON e.id = ec.event_id
    LEFT JOIN categories c ON ec.category_id = c.id
    LEFT JOIN event_registrations er ON e.id = er.event_id AND er.user_id = ?
    LEFT JOIN event_payments ep ON e.id = ep.event_id AND ep.user_id = ?
    WHERE e.id = ?
");
$stmt->bind_param("iii", $user_id, $user_id, $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    header("Location: dashboard.php");
    exit();
}

// Handle registration - Removed direct POST handling. Will use JavaScript quickRegister function.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Event Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/styles/students.css">
    <style>
        .strong {
            color: black;
        }
        .event-header {
            position: relative;
            height: 400px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .event-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }

        .event-header:hover img {
            transform: scale(1);
        }

        .event-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.7));
            display: flex;
            align-items: flex-end;
            padding: 2rem 0;
        }

        .event-overlay h1 {
            color: white;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .event-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .event-meta-item {
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }

        .event-meta-item i {
            font-size: 1.3rem;
        }

        .event-schedule-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .event-schedule-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .event-schedule-item i {
            font-size: 1.5rem;
            color: #044721;
        }

        .event-schedule-item .schedule-label {
            font-weight: 600;
            color: #333;
            margin-right: 0.5rem;
        }

        .event-schedule-item .schedule-time {
            color: #044721;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .event-location-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .event-location-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .event-location-item i {
            font-size: 1.5rem;
            color: #044721;
        }

        .event-location-item .location-label {
            font-weight: 600;
            color: #333;
            margin-right: 0.5rem;
        }

        .event-location-item .location-name {
            color: #044721;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .registration-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .registration-info-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .registration-info-item i {
            font-size: 1.5rem;
            color: #044721;
        }

        .registration-info-item .info-label {
            font-weight: 600;
            color: #333;
            margin-right: 0.5rem;
        }

        .registration-info-item .info-value {
            color: #044721;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .registration-status {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            background: #f8f9fa;
            color: #044721;
            border: 2px solid #044721;
        }

        .registration-card h3 {
            color: #044721;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #044721;
            display: inline-block;
        }

        .event-details-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .event-details-card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .event-description {
            color: #666;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .category-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: rgba(255,255,255,0.9);
            color: #333;
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }

        .share-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .share-buttons button {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.2s;
        }

        .share-buttons button:hover {
            transform: translateY(-2px);
        }

        .share-facebook {
            background-color: #1877f2;
        }

        .share-twitter {
            background-color: #1da1f2;
        }

        .share-whatsapp {
            background-color: #25d366;
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

        .back-button {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
            text-decoration: none;
            backdrop-filter: blur(5px);
            transition: transform 0.2s;
        }

        .back-button:hover {
            transform: translateX(-5px);
            color: #333;
        }

        .event-map {
            height: 300px;
            border-radius: 15px;
            margin-top: 1rem;
            overflow: hidden;
        }

        .registration-card {
            position: sticky;
            top: 2rem;
        }

        .registration-card .btn {
            padding: 1rem;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .registration-card .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .registration-card .alert {
            border-radius: 10px;
            padding: 1rem;
        }

        @media (max-width: 768px) {
            .event-header {
                height: 300px;
            }

            .event-overlay h1 {
                font-size: 2rem;
            }

            .event-meta {
                gap: 1rem;
            }

            .event-meta-item {
                font-size: 1rem;
            }

            .event-details-card {
                padding: 1.5rem;
            }

            .registration-card {
                position: static;
                margin-top: 2rem;
            }
        }
    </style>
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
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h2 class="card-title mb-3">About this Event</h2>
                        <p class="card-text"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                        <?php if ($event['payment_required'] && $event['payment_amount'] > 0) : ?>
                            <p class="card-text"><strong>Price:</strong> PHP <?php echo number_format($event['payment_amount'], 2); ?></p>
                            <p class="card-text mb-0"><strong>Payment Methods:</strong></p>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <?php
                                $payment_methods_arr = explode(',', $event['payment_methods']);
                                foreach ($payment_methods_arr as $method) {
                                    $method_display = trim($method);
                                    $icon_class = '';
                                    switch (strtolower($method_display)) {
                                        case 'gcash':
                                            $icon_class = 'bi bi-phone-fill'; // Example icon, consider custom if needed
                                            break;
                                        case 'paymaya':
                                            $icon_class = 'bi bi-phone'; // Example icon, consider custom if needed
                                            break;
                                        case 'card':
                                            $icon_class = 'bi bi-credit-card-fill';
                                            break;
                                        default:
                                            $icon_class = 'bi bi-wallet';
                                            break;
                                    }
                                    echo '<span class="badge bg-info text-dark"><i class="' . $icon_class . ' me-1"></i>' . ucfirst($method_display) . '</span>';
                                }
                                ?>
                            </div>
                        <?php else : ?>
                            <p class="card-text"><strong>Price:</strong> Free</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h3 class="card-title mb-3">Schedule</h3>
                        <div class="event-schedule-item">
                            <i class="bi bi-calendar-event"></i>
                            <div>
                                <span class="schedule-label">Starts:</span>
                                <span class="schedule-time"><?php echo date('F j, Y h:i A', strtotime($event['start_date'])); ?></span>
                            </div>
                        </div>
                        <div class="event-schedule-item">
                            <i class="bi bi-calendar-event-fill"></i>
                            <div>
                                <span class="schedule-label">Ends:</span>
                                <span class="schedule-time"><?php echo date('F j, Y h:i A', strtotime($event['end_date'])); ?></span>
                            </div>
                        </div>
                        <div class="event-schedule-item">
                            <i class="bi bi-clock"></i>
                            <div>
                                <span class="schedule-label">Registration Deadline:</span>
                                <span class="schedule-time"><?php echo date('F j, Y h:i A', strtotime($event['registration_deadline'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h3 class="card-title mb-3">Location</h3>
                        <div class="event-location-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <span class="location-label">Venue:</span>
                                <span class="location-name"><?php echo htmlspecialchars($event['location']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm mb-4 sidebar-card">
                    <div class="card-body">
                        <h3 class="card-title mb-3">Registration Info</h3>
                        <div class="registration-info-item">
                            <i class="bi bi-people-fill"></i>
                            <div>
                                <span class="info-label">Capacity:</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['capacity']); ?></span>
                            </div>
                        </div>
                        <div class="registration-info-item">
                            <i class="bi bi-person-check-fill"></i>
                            <div>
                                <span class="info-label">Registered:</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['registration_count']); ?></span>
                            </div>
                        </div>
                        <div class="registration-info-item">
                            <i class="bi bi-person-fill"></i>
                            <div>
                                <span class="info-label">Available Slots:</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['capacity'] - $event['registration_count']); ?></span>
                            </div>
                        </div>
                        <div class="registration-info-item">
                            <i class="bi bi-calendar-check"></i>
                            <div>
                                <span class="info-label">Your Status:</span>
                                <span class="info-value user-registration-status">
                                    <?php
                                    if ($event['user_registration_status'] === 'confirmed') {
                                        echo '<span class="badge bg-success">Confirmed</span>';
                                    } elseif ($event['user_registration_status'] === 'pending' && $event['payment_required'] && $event['payment_amount'] > 0 && $event['payment_status'] !== 'approved') {
                                        echo '<span class="badge bg-warning text-dark">Payment Pending</span>';
                                    } elseif ($event['user_registration_status'] === 'pending') {
                                        echo '<span class="badge bg-info text-dark">Pending Approval</span>';
                                    } elseif ($event['user_registration_status'] === 'cancelled') {
                                        echo '<span class="badge bg-danger">Cancelled</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">Not Registered</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!$event['user_registration_status']) : ?>
                            <?php if ($event['registration_count'] < $event['capacity'] && strtotime($event['registration_deadline']) > time()) : ?>
                                <button type="button" class="btn btn-success btn-lg w-100 mb-3" onclick="quickRegister(<?php echo $event['id']; ?>)">
                                    Register for this Event
                                </button>
                            <?php else : ?>
                                <button class="btn btn-secondary btn-lg w-100 mb-3" disabled>
                                    Registration Closed / Event Full
                                </button>
                            <?php endif; ?>
                        <?php elseif ($event['user_registration_status'] === 'pending' && $event['payment_required'] && $event['payment_amount'] > 0 && $event['payment_status'] !== 'approved') : ?>
                            <button type="button" class="btn btn-primary btn-lg w-100 mb-3" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                Proceed to Payment
                            </button>
                        <?php endif; ?>

                        <?php if ($event['user_registration_status']) : ?>
                            <button type="button" class="btn btn-danger btn-lg w-100" id="cancelRegistrationBtn" data-event-id="<?php echo $event['id']; ?>">
                                Cancel Registration
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap&loading=async" async defer></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        // Quick registration function (copied from dashboard.php)
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
                    console.log('Event Details - quickRegister data received:', data);
                    if (data.payment_required) {
                        hideLoading();
                        showPaymentModal(data);
                    } else {
                        proceedWithRegistration(eventId);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Event Details - Error in quickRegister fetch:', error);
                    alert('Failed to check payment status. Please try again later.');
                });
        }

        // Show payment modal (dynamically create and show, similar to dashboard.php)
        function showPaymentModal(data) {
            console.log('Event Details - showPaymentModal called with data:', data);

            // Remove any existing modal
            const existingModal = document.getElementById('paymentModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Clone the template
            const template = document.getElementById('paymentModalTemplate');
            const modal = template.cloneNode(true);
            modal.id = 'paymentModal';
            modal.style.display = 'block';

            // Update modal content
            modal.querySelector('#modalEventTitle').textContent = data.event.title;
            modal.querySelector('#modalAmount').textContent = parseFloat(data.payment.amount).toFixed(2);
            modal.querySelector('#paymentEventId').value = data.event.id;

            // Add to document
            document.body.appendChild(modal);

            // Initialize Bootstrap Modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();

            // Payment method card selection logic
            const methodCards = modal.querySelectorAll('.payment-method-card');
            const paymentForm = modal.querySelector('#paymentForm');
            const proceedButton = modal.querySelector('#proceedButton');
            
            methodCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selection from all cards
                    methodCards.forEach(c => {
                        c.classList.remove('selected');
                        c.querySelector('.card').classList.remove('border-success');
                    });
                    
                    // Add selection to clicked card
                    this.classList.add('selected');
                    this.querySelector('.card').classList.add('border-success');
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
                    
                    // Enable proceed button
                    // Note: proceedButton might not exist if it's the submit button of the form.
                    // If it's the form's submit button, it will be enabled automatically when fields are valid.
                    // If there's a separate proceed button, re-enable it here.
                    if (proceedButton) {
                         proceedButton.disabled = false;
                    }
                });
            });

            // Ensure previous listeners are removed to prevent multiple bindings
            $('#paymentForm').off('submit').on('submit', function(e) {
                e.preventDefault();

                const eventId = $('#paymentEventId').val();
                const paymentMethod = modal.querySelector('input[name="paymentMethod"]:checked').value;
                let referenceNumber;

                // Get the correct reference number based on the selected payment method
                if (paymentMethod === 'gcash') {
                    referenceNumber = $('#gcashReferenceNumber').val();
                } else if (paymentMethod === 'paymaya') {
                    referenceNumber = $('#paymayaReferenceNumber').val();
                } else if (paymentMethod === 'card') {
                    referenceNumber = $('#cardReferenceNumber').val();
                }

                // Get other input values based on the selected payment method
                let paymentNumberInput;
                if (paymentMethod === 'gcash') {
                    paymentNumberInput = modal.querySelector('input[name="gcash_number"]');
                } else if (paymentMethod === 'paymaya') {
                    paymentNumberInput = modal.querySelector('input[name="paymaya_number"]');
                } else if (paymentMethod === 'card') {
                    paymentNumberInput = modal.querySelector('input[name="card_number"]');
                }
                const paymentNumber = paymentNumberInput ? paymentNumberInput.value : '';

                $.ajax({
                    url: '../api/submit-payment.php',
                    type: 'POST',
                    data: {
                        event_id: eventId,
                        payment_method: paymentMethod,
                        reference_number: referenceNumber,
                        payment_number: paymentNumber, // Add the payment number here
                        amount: parseFloat(data.payment.amount)
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showSuccess('Payment submitted successfully! Your payment is pending verification.');
                            bsModal.hide();
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showError(response.message || 'Failed to process payment. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error in payment submission:', error, xhr.responseText);
                        showError('An error occurred during payment submission. Please try again later.');
                    }
                });
            });

            // Clean up modal when hidden
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
        }

        // Proceed with registration (copied from dashboard.php)
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
                    alert('Registration successful!');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    alert(data.message || 'Failed to register. Please try again.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('Registration failed. Please try again later.');
            });
        }

        $(document).ready(function() {
            // Handle cancel registration
            $('#cancelRegistrationBtn').on('click', function() {
                const eventId = $(this).data('event-id');

                if (confirm('Are you sure you want to cancel your registration for this event?')) {
                    $.ajax({
                        url: '../api/cancel-registration.php',
                        type: 'POST',
                        data: {
                            event_id: eventId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Registration cancelled successfully.');
                                location.reload();
                            } else {
                                alert('Cancellation failed: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(xhr.responseText);
                            alert('An error occurred during cancellation.');
                        }
                    });
                }
            });
        });
    </script>

    <!-- Add this static modal template at the end of the body -->
    <div class="modal fade" id="paymentModalTemplate" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="display: none;">
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
                        <h6 class="text-success mb-3">Event: <span id="modalEventTitle"></span></h6>
                        <p class="mb-2"><strong class="text-dark">Amount to Pay:</strong> ₱<span id="modalAmount"></span></p>
                        <p class="mb-0"><strong class="text-dark">Payment Status:</strong> <span class="">Pending</span></p>
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
                        <input type="hidden" id="paymentEventId">
                        <input type="hidden" id="paymentMethod" name="paymentMethod">
                        
                        <div id="gcashForm" class="payment-form">
                            <div class="mb-3">
                                <label class="form-label">GCash Number</label>
                                <input type="text" class="form-control" name="gcash_number" placeholder="Enter your GCash number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" id="gcashReferenceNumber" class="form-control" name="gcash_reference_number" placeholder="Enter reference number">
                            </div>
                        </div>

                        <div id="paymayaForm" class="payment-form" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">PayMaya Number</label>
                                <input type="text" class="form-control" name="paymaya_number" placeholder="Enter your PayMaya number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" id="paymayaReferenceNumber" class="form-control" name="paymaya_reference_number" placeholder="Enter reference number">
                            </div>
                        </div>

                        <div id="cardForm" class="payment-form" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Card Number</label>
                                <input type="text" class="form-control" name="card_number" placeholder="Enter card number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" id="cardReferenceNumber" class="form-control" name="card_reference_number" placeholder="Enter reference number">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Submit Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
