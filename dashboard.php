<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Get user's bookings
$bookings_query = "SELECT b.*, v.name as vehicle_name, v.image_url 
                   FROM bookings b 
                   JOIN vehicles v ON b.vehicle_id = v.id 
                   WHERE b.user_id = ? 
                   ORDER BY b.created_at DESC 
                   LIMIT 5";
$stmt = mysqli_prepare($conn, $bookings_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $bookings_result = mysqli_stmt_get_result($stmt);
} else {
    $bookings_result = false;
    error_log("MySQL Error in bookings query: " . mysqli_error($conn));
}

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $notifications_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $notifications_result = mysqli_stmt_get_result($stmt);
} else {
    $notifications_result = false;
    error_log("MySQL Error in notifications query: " . mysqli_error($conn));
}

// Get booking statistics
$total_bookings = 0;
$active_bookings = 0;
$completed_bookings = 0;

$total_bookings_query = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $total_bookings_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $total_bookings = mysqli_fetch_assoc($result)['total'] ?? 0;
    }
} else {
    error_log("MySQL Error in total bookings query: " . mysqli_error($conn));
}

$active_bookings_query = "SELECT COUNT(*) as active FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'in_progress')";
$stmt = mysqli_prepare($conn, $active_bookings_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $active_bookings = mysqli_fetch_assoc($result)['active'] ?? 0;
    }
} else {
    error_log("MySQL Error in active bookings query: " . mysqli_error($conn));
}

$completed_bookings_query = "SELECT COUNT(*) as completed FROM bookings WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $completed_bookings_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $completed_bookings = mysqli_fetch_assoc($result)['completed'] ?? 0;
    }
} else {
    error_log("MySQL Error in completed bookings query: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .dashboard-container {
            padding: 2rem 0;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(30, 58, 138, 0.2);
        }
        
        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid #fbbf24;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stats-card.success {
            border-left-color: #10b981;
        }
        
        .stats-card.warning {
            border-left-color: #f59e0b;
        }
        
        .stats-card.info {
            border-left-color: #3b82f6;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-card .stats-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #1e3a8a;
        }
        
        .stats-card.success .stats-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .stats-card.warning .stats-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .stats-card.info .stats-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .recent-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .recent-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .recent-card .card-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .booking-item {
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem;
            transition: all 0.3s ease;
        }
        
        .booking-item:hover {
            background: rgba(251, 191, 36, 0.05);
        }
        
        .booking-item:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            background: rgba(251, 191, 36, 0.05);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-time {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .quick-actions {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: none;
            color: #1e3a8a;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
            text-decoration: none;
            display: inline-block;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.5);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1e3a8a;
        }
        
        /* Recent Bookings Styles */
        .recent-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .recent-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .booking-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .booking-item:hover {
            background: rgba(251, 191, 36, 0.05);
        }
        
        .booking-item:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .status-in_progress {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .status-completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .status-cancelled {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: #1e3a8a;
            font-size: 1.5rem;
        }
        
        .stats-card.success .stats-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .stats-card.warning .stats-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .stats-card.info .stats-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="dashboard-container">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-5 fw-bold mb-3">
                            Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!
                        </h1>
                        <p class="lead mb-0">Manage your bookings and stay updated with your vehicle hire activities.</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex align-items-center justify-content-md-end">
                            <img src="assets/images/MG Logo.jpg" alt="MG Transport" class="me-3" style="width: 60px; height: 60px; border-radius: 50%; background: #fbbf24;">
                            <div>
                                <h5 class="mb-0 text-warning">MG TRANSPORT</h5>
                                <small class="text-white-50">Vehicle Hire Services</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="fw-bold mb-2"><?php echo $total_bookings; ?></h3>
                        <p class="text-muted mb-0">Total Bookings</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card success">
                        <div class="stats-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h3 class="fw-bold mb-2"><?php echo $active_bookings; ?></h3>
                        <p class="text-muted mb-0">Active Bookings</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card warning">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="fw-bold mb-2"><?php echo $active_bookings; ?></h3>
                        <p class="text-muted mb-0">Pending Bookings</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stats-card info">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="fw-bold mb-2"><?php echo $completed_bookings; ?></h3>
                        <p class="text-muted mb-0">Completed</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Bookings -->
                <div class="col-lg-8 mb-4">
                    <div class="recent-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Recent Bookings
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($bookings_result && mysqli_num_rows($bookings_result) > 0): ?>
                                <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                                    <div class="booking-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <img src="<?php echo htmlspecialchars($booking['image_url'] ?? 'assets/images/default-vehicle.jpg'); ?>" 
                                                     alt="<?php echo htmlspecialchars($booking['vehicle_name'] ?? 'Vehicle'); ?>" 
                                                     class="img-fluid rounded" style="width: 60px; height: 40px; object-fit: cover;">
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($booking['vehicle_name'] ?? 'Unknown Vehicle'); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($booking['pickup_date'] ?? 'now')); ?> - 
                                                    <?php echo date('M d, Y', strtotime($booking['return_date'] ?? 'now')); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <span class="status-badge status-<?php echo strtolower($booking['status'] ?? 'pending'); ?>">
                                                    <?php echo ucfirst($booking['status'] ?? 'Pending'); ?>
                                                </span>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <a href="my-bookings.php" class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No bookings yet</h5>
                                    <p class="text-muted">Start by booking your first vehicle!</p>
                                    <a href="booking.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Book Now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications & Quick Actions -->
                <div class="col-lg-4">
                    <!-- Notifications -->
                    <div class="recent-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bell me-2"></i>
                                Notifications
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($notifications_result && mysqli_num_rows($notifications_result) > 0): ?>
                                <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
                                    <div class="notification-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></h6>
                                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message'] ?? 'No message'); ?></p>
                                            </div>
                                            <small class="notification-time">
                                                <?php echo date('M d', strtotime($notification['created_at'] ?? 'now')); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-4 text-center">
                                    <i class="fas fa-bell-slash fa-2x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No new notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h5 class="mb-3" style="color: #1e3a8a; font-weight: 700;">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h5>
                        <div class="d-grid gap-3">
                            <a href="booking.php" class="action-btn">
                                <i class="fas fa-calendar-plus me-2"></i>New Booking
                            </a>
                            <a href="vehicles.php" class="action-btn">
                                <i class="fas fa-car me-2"></i>Browse Vehicles
                            </a>
                            <a href="my-bookings.php" class="action-btn">
                                <i class="fas fa-list me-2"></i>View All Bookings
                            </a>
                            <a href="profile.php" class="action-btn">
                                <i class="fas fa-user me-2"></i>Update Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 