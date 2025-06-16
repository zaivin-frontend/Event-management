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
           e.payment_required, e.payment_amount, e.payment_methods
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

// Get user's registered events with payment status
$stmt = $conn->prepare("
    SELECT e.*, c.name as category_name, er.status as registration_status,
           ep.status as payment_status, ep.payment_method, ep.reference_number,
           ep.amount as payment_amount
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
    <link rel="stylesheet" href="../assets/css-students/students.css">
    <link rel="stylesheet" href="../assets/css-students/dashboard.css">
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
                            <?php if ($event['image']): ?>
                                <img src="../assets/images/events/<?php echo htmlspecialchars($event['image']); ?>" 
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
                                        <button class="btn btn-primary btn-sm" 
                                                onclick="quickRegister(<?php echo $event['id']; ?>)">
                                            <i class="bi bi-plus-circle"></i> Register
                                        </button>
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
                                        <?php echo $event['registration_count']; ?> registered
                                    </span>
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
    
    <script>
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
                .then(response => response.json())
                .then(data => {
                    if (data.payment_required) {
                        hideLoading();
                        showPaymentModal(data);
                    } else {
                        proceedWithRegistration(eventId);
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showError('Failed to check payment status. Please try again later.');
                });
        }

        function showPaymentModal(data) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'paymentModal';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-credit-card me-2"></i>Payment Required
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="payment-amount text-center mb-4">
                                <div class="amount-display bg-success text-white p-4 rounded-3">
                                    <h6 class="mb-2">Payment Amount</h6>
                                    <h2 class="mb-0">₱${data.payment_amount}</h2>
                                </div>
                            </div>
                            <div class="payment-methods">
                                <h6 class="text-success mb-3">Select Payment Method:</h6>
                                ${data.payment_methods.map(method => `
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="${method}" value="${method}" required>
                                        <label class="form-check-label" for="${method}">
                                            <i class="bi bi-${method === 'gcash' ? 'wallet2' : 'credit-card'} me-2"></i>
                                            ${method.charAt(0).toUpperCase() + method.slice(1)}
                                        </label>
                                    </div>
                                `).join('')}
                            </div>
                            <div class="payment-details mt-4" style="display: none;">
                                <h6 class="text-success mb-3">Payment Details:</h6>
                                <div id="gcashDetails" class="payment-info p-4 bg-success text-white rounded-3 shadow">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="bi bi-wallet2 fs-4 me-2"></i>
                                        <h5 class="mb-0">GCash Payment</h5>
                                    </div>
                                    <div class="bg-white text-dark p-3 rounded">
                                        <p class="mb-2"><strong>GCash Number:</strong> 09123456789</p>
                                        <p class="mb-0"><strong>Account Name:</strong> Event Management System</p>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-white-50">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Please send the exact amount and keep your reference number
                                        </small>
                                    </div>
                                </div>
                                <div id="paymayaDetails" class="payment-info p-3 bg-light rounded">
                                    <p class="mb-2"><strong>PayMaya Number:</strong> 09123456789</p>
                                    <p class="mb-0"><strong>Account Name:</strong> Event Management System</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label for="referenceNumber" class="form-label text-success">
                                    <i class="bi bi-upc me-2"></i>Reference Number
                                </label>
                                <input type="text" class="form-control" id="referenceNumber" 
                                       placeholder="Enter your payment reference number" required>
                                <small class="text-muted">Please enter the reference number from your payment app</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-success" onclick="submitPayment(${data.event_id})">
                                <i class="bi bi-check-circle me-2"></i>Submit Payment
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();

            // Show payment details when method is selected
            const paymentMethods = modal.querySelectorAll('input[name="payment_method"]');
            const paymentDetails = modal.querySelector('.payment-details');
            
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    paymentDetails.style.display = 'block';
                    document.querySelectorAll('.payment-info').forEach(info => {
                        info.style.display = 'none';
                    });
                    document.getElementById(`${this.value}Details`).style.display = 'block';
                });
            });

            // Clean up modal when hidden
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
        }

        function submitPayment(eventId) {
            const modal = document.getElementById('paymentModal');
            const paymentMethod = modal.querySelector('input[name="payment_method"]:checked');
            const referenceNumber = modal.querySelector('#referenceNumber').value;

            if (!paymentMethod || !referenceNumber) {
                showError('Please select a payment method and enter the reference number.');
                return;
            }

            showLoading();
            
            const formData = new FormData();
            formData.append('event_id', eventId);
            formData.append('payment_method', paymentMethod.value);
            formData.append('reference_number', referenceNumber);
            
            fetch('../api/submit-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showSuccess('Payment submitted successfully!');
                    bootstrap.Modal.getInstance(modal).hide();
                    proceedWithRegistration(eventId);
                } else {
                    showError(data.message || 'Failed to submit payment. Please try again.');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showError('Payment submission failed. Please try again later.');
            });
        }

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