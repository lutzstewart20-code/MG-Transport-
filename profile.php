<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

$user_id = $_SESSION['user_id'];
$user = getUserDetails($user_id, $conn);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $first_name = sanitizeInput($_POST['first_name']);
        $last_name = sanitizeInput($_POST['last_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage('profile.php', 'Please enter a valid email address.', 'error');
        }
        
        // Check if email already exists (excluding current user)
        $check_email_query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $check_email_query);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            redirectWithMessage('profile.php', 'Email address already exists.', 'error');
        }
        
        // Update profile
        $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            redirectWithMessage('profile.php', 'Profile updated successfully!', 'success');
        } else {
            redirectWithMessage('profile.php', 'Error updating profile. Please try again.', 'error');
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            redirectWithMessage('profile.php', 'Current password is incorrect.', 'error');
        }
        
        // Validate new password
        if (strlen($new_password) < 6) {
            redirectWithMessage('profile.php', 'New password must be at least 6 characters long.', 'error');
        }
        
        if ($new_password !== $confirm_password) {
            redirectWithMessage('profile.php', 'New passwords do not match.', 'error');
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            redirectWithMessage('profile.php', 'Password changed successfully!', 'success');
        } else {
            redirectWithMessage('profile.php', 'Error changing password. Please try again.', 'error');
        }
    }
}

// Get user statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings
FROM bookings";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.15rem rgba(251, 191, 36, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
        }
        
        /* Sidebar toggle styles */
        /* Sidebar toggle styles */
        #sidebarMenu {
            transition: all 0.3s ease;
        }
        
        #sidebarMenu.collapsed {
            transform: translateX(-100%);
            width: 0 !important;
            min-width: 0 !important;
        }
        
        main.sidebar-collapsed {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        /* Hamburger button styles */
        #sidebarToggle {
            border: none;
            background: transparent;
            padding: 0.5rem;
            transition: all 0.3s ease;
        }
        
        #sidebarToggle:hover {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.25rem;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            #sidebarMenu {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1050;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            #sidebarMenu.show {
                transform: translateX(0);
            }
            
            main {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-user-circle text-primary"></i> Admin Profile
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-edit"></i> Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" 
                                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" class="form-control" id="role" 
                                               value="<?php echo ucfirst(htmlspecialchars($user['role'] ?? '')); ?>" readonly>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-key"></i> Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics and Info -->
                    <div class="col-lg-4">
                        <!-- Account Info -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Account Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="fas fa-user-circle fa-4x text-primary"></i>
                                </div>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <strong>Member Since:</strong><br>
                                        <span class="text-muted"><?php echo date('F j, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Last Updated:</strong><br>
                                        <span class="text-muted"><?php echo date('F j, Y', strtotime($user['updated_at'] ?? 'now')); ?></span>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Account Status:</strong><br>
                                        <span class="badge bg-success">Active</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar"></i> System Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-primary"><?php echo $stats['total_bookings'] ?? 0; ?></h4>
                                            <small class="text-muted">Total Bookings</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-success"><?php echo $stats['confirmed_bookings'] ?? 0; ?></h4>
                                            <small class="text-muted">Confirmed</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-warning"><?php echo $stats['pending_bookings'] ?? 0; ?></h4>
                                            <small class="text-muted">Pending</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="text-info"><?php echo $stats['completed_bookings'] ?? 0; ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                    <a href="bookings.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-calendar-check"></i> Manage Bookings
                                    </a>
                                    <a href="vehicles.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-car"></i> Manage Vehicles
                                    </a>
                                    <a href="users.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a>
                                    <a href="settings.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-cog"></i> System Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                if (this.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        });

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebarMenu');
            const mainContent = document.querySelector('main');
            
            // Check if sidebar state is stored in localStorage (desktop only)
            if (window.innerWidth >= 768) {
                const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (sidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                }
            }
            
            sidebarToggle.addEventListener('click', function() {
                if (window.innerWidth >= 768) {
                    // Desktop behavior
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                } else {
                    // Mobile behavior
                    sidebar.classList.toggle('show');
                }
            });
            
            // Close sidebar on mobile when clicking outside
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768) {
                    if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html> 