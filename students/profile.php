<?php
session_start();
require_once '../database/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

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

// Initialize user data with default values if not set
$user['first_name'] = $user['first_name'] ?? '';
$user['last_name'] = $user['last_name'] ?? '';
$user['email'] = $user['email'] ?? '';
$user['phone'] = $user['phone'] ?? '';
$user['address'] = $user['address'] ?? '';
$user['avatar'] = $user['avatar'] ?? null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_avatar'])) {
        // Handle avatar upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                $error_message = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
            } elseif ($_FILES['avatar']['size'] > $max_size) {
                $error_message = "File size too large. Maximum size is 5MB.";
            } else {
                $upload_dir = '../assets/images/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Delete old avatar if exists
                    if ($user['avatar'] && file_exists($upload_dir . $user['avatar'])) {
                        unlink($upload_dir . $user['avatar']);
                    }
                    
                    // Update avatar in database
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_filename, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Profile picture updated successfully!";
                        // Refresh user data
                        $stmt = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                    } else {
                        $error_message = "Failed to update profile picture. Please try again.";
                        // Delete the uploaded file if database update fails
                        if (file_exists($upload_path)) {
                            unlink($upload_path);
                        }
                    }
                } else {
                    $error_message = "Failed to upload avatar. Please try again.";
                }
            }
        } else {
            $error_message = "Please select an image to upload.";
        }
    } elseif (isset($_POST['update_profile'])) {
        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';

        // Validate input
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "First name, last name, and email are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "This email address is already in use.";
            } else {
                // Update profile
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message = "Failed to update profile. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();

            if (password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/styles/students.css">
    <style>
        .profile-section {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #044721;
        }

        .profile-avatar i {
            font-size: 4rem;
            color: #044721;
        }

        .profile-name {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .profile-email {
            color: #666;
            font-size: 1.1rem;
        }

        .section-title {
            color: #044721;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #044721;
        }

        .form-label {
            color: #333;
            font-weight: 500;
        }

        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #044721;
            box-shadow: 0 0 0 0.2rem rgba(4, 71, 33, 0.25);
        }

        .btn-primary {
            background-color: #044721;
            border-color: #044721;
            padding: 0.8rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #033318;
            border-color: #033318;
            transform: translateY(-2px);
        }

        .btn-outline-primary {
            color: #044721;
            border-color: #044721;
            padding: 0.8rem 2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-outline-primary:hover {
            background-color: #044721;
            border-color: #044721;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 2rem;
            color: #044721;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #044721;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }

        .avatar-edit-btn:hover {
            transform: scale(1.1);
            background-color: #033318;
        }

        .avatar-edit-btn i {
            font-size: 1.2rem;
            color: white;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-bottom: 2px solid #044721;
            padding: 1rem 1.5rem;
        }

        .modal-title {
            color: #044721;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        #avatar {
            border: 2px dashed #ddd;
            padding: 1rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        #avatar:hover {
            border-color: #044721;
        }

        .form-text {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .profile-section {
                padding: 1.5rem;
            }

            .profile-avatar-container {
                width: 120px;
                height: 120px;
            }

            .profile-avatar i {
                font-size: 3rem;
            }

            .avatar-edit-btn {
                width: 35px;
                height: 35px;
            }

            .avatar-edit-btn i {
                font-size: 1rem;
            }
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
                        <a class="nav-link" href="my-events.php">My Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">Profile</a>
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
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-section">
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <?php if ($user['avatar'] && file_exists('../assets/images/avatars/' . $user['avatar'])): ?>
                        <img src="../assets/images/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" 
                             alt="Profile Avatar" class="profile-avatar">
                    <?php else: ?>
                        <div class="profile-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-primary avatar-edit-btn" 
                            data-bs-toggle="modal" data-bs-target="#avatarModal">
                        <i class="bi bi-camera"></i>
                    </button>
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <!-- Profile Stats -->
            <div class="profile-stats">
                <?php
                // Get total registered events
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM event_registrations WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $total_events = $stmt->get_result()->fetch_assoc()['total'];

                // Get upcoming events
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as total 
                    FROM event_registrations er 
                    JOIN events e ON er.event_id = e.id 
                    WHERE er.user_id = ? AND e.start_date >= CURDATE()
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $upcoming_events = $stmt->get_result()->fetch_assoc()['total'];
                ?>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_events; ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value"><?php echo $upcoming_events; ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>
            </div>
        </div>

        <!-- Avatar Upload Modal -->
        <div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="avatarModalLabel">Update Profile Picture</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                            <div class="mb-3">
                                <label for="avatar" class="form-label">Choose Image</label>
                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" required>
                                <div class="form-text">Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF</div>
                            </div>
                            <div id="avatarPreview" class="text-center mb-3" style="display: none;">
                                <img src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 50%;">
                            </div>
                            <div class="text-center">
                                <button type="submit" name="update_avatar" class="btn btn-primary">Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="profile-section">
            <h2 class="section-title">Update Profile</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="profile-section">
            <h2 class="section-title">Change Password</h2>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password validation
        document.querySelector('form[name="change_password"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
            } else if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Avatar preview
        document.getElementById('avatar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('avatarPreview');
            const previewImg = preview.querySelector('img');
            
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum size is 5MB.');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        // Reset form and preview when modal is closed
        const avatarModal = document.getElementById('avatarModal');
        if (avatarModal) {
            avatarModal.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('avatarForm');
                const preview = document.getElementById('avatarPreview');
                if (form) {
                    form.reset();
                    preview.style.display = 'none';
                }
            });
        }

        // Show success message and refresh page after successful upload
        <?php if ($success_message && isset($_POST['update_avatar'])): ?>
        setTimeout(function() {
            window.location.reload();
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>
