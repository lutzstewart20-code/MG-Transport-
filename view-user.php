<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$message_type = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user details - including photo columns if they exist
$user_query = "SELECT id, username, email, role, first_name, last_name, phone, address, created_at";

// Check if photo columns exist and add them to query
$columns_result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'user_photo'");
if (mysqli_num_rows($columns_result) > 0) {
    $user_query .= ", user_photo";
}

$columns_result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'driver_license_photo'");
if (mysqli_num_rows($columns_result) > 0) {
    $user_query .= ", driver_license_photo";
}

$user_query .= " FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
if (!$stmt) {
    die("Error preparing statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($stmt));
}
$user_result = mysqli_stmt_get_result($stmt);
if (!$user_result) {
    die("Error getting result: " . mysqli_error($conn));
}
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header('Location: users.php');
    exit();
}

// Get user's bookings
$bookings_query = "SELECT b.*, v.name as vehicle_name, v.image_url 
                   FROM bookings b 
                   JOIN vehicles v ON b.vehicle_id = v.id 
                   WHERE b.user_id = ? 
                   ORDER BY b.created_at DESC";
$stmt = mysqli_prepare($conn, $bookings_query);
if (!$stmt) {
    die("Error preparing bookings statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing bookings statement: " . mysqli_stmt_error($stmt));
}
$bookings_result = mysqli_stmt_get_result($stmt);
if (!$bookings_result) {
    die("Error getting bookings result: " . mysqli_error($conn));
}

// Get user statistics
$stats_query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_spent,
                    AVG(CASE WHEN payment_status = 'paid' THEN total_amount ELSE NULL END) as avg_booking_amount
                FROM bookings 
                WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $stats_query);
if (!$stmt) {
    die("Error preparing stats statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing stats statement: " . mysqli_stmt_error($stmt));
}
$stats_result = mysqli_stmt_get_result($stmt);
if (!$stats_result) {
    die("Error getting stats result: " . mysqli_error($conn));
}
$stats = mysqli_fetch_assoc($stats_result);

// Get recent activity (last 10 bookings)
$recent_bookings_query = "SELECT b.*, v.name as vehicle_name, v.image_url 
                          FROM bookings b 
                          JOIN vehicles v ON b.vehicle_id = v.id 
                          WHERE b.user_id = ? 
                          ORDER BY b.created_at DESC 
                          LIMIT 10";
$stmt = mysqli_prepare($conn, $recent_bookings_query);
if (!$stmt) {
    die("Error preparing recent bookings statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing recent bookings statement: " . mysqli_stmt_error($stmt));
}
$recent_bookings_result = mysqli_stmt_get_result($stmt);
if (!$recent_bookings_result) {
    die("Error getting recent bookings result: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .stats-card {
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
        }
        
        .booking-status {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        
        .vehicle-image {
            width: 50px;
            height: 35px;
            object-fit: cover;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services">
                <span class="ms-2">MG Transport Services Admin</span>
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
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="pt-3 pb-2 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0">User Details</h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                                    <li class="breadcrumb-item active">View User</li>
                                </ol>
                            </nav>
                        </div>
                        <div>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Users
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- User Profile Section -->
                <div class="row mb-4">
                    <div class="col-lg-4">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="user-avatar mx-auto mb-3">
                                    <?php echo strtoupper(substr($user['first_name'] ?? $user['username'], 0, 1)); ?>
                                </div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'super_admin' ? 'warning' : 'primary'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-8">
                        <div class="card stats-card">
                            <div class="card-header">
                                <h5 class="mb-0">User Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                        <p><strong>Last Login:</strong> 
                                            <?php echo isset($user['last_login']) && $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Not tracked'; ?>
                                        </p>
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo (isset($user['status']) ? $user['status'] : 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst(isset($user['status']) ? $user['status'] : 'active'); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- User Photos Section -->
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user me-2"></i>User Photo</h6>
                                        <?php if (isset($user['user_photo']) && $user['user_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($user['user_photo']); ?>" 
                                                 alt="User Photo" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                        <?php else: ?>
                                            <div class="text-muted">
                                                <i class="fas fa-image me-2"></i><?php echo isset($user['user_photo']) ? 'No photo uploaded' : 'Photo upload not enabled'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-id-card me-2"></i>Driver's License</h6>
                                        <?php if (isset($user['driver_license_photo']) && $user['driver_license_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($user['driver_license_photo']); ?>" 
                                                 alt="Driver's License" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                        <?php else: ?>
                                            <div class="text-muted">
                                                <i class="fas fa-image me-2"></i><?php echo isset($user['driver_license_photo']) ? 'No license uploaded' : 'License upload not enabled'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Bookings</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_bookings'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Active Bookings</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_bookings'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-car fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Spent</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['total_spent'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Avg. Booking</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['avg_booking_amount'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card stats-card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($recent_bookings_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Dates</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = mysqli_fetch_assoc($recent_bookings_result)): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($booking['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($booking['vehicle_name']); ?>" 
                                                         class="vehicle-image me-2"
                                                         onerror="this.src='../assets/images/no-vehicle-image.jpg'">
                                                <?php else: ?>
                                                    <div class="vehicle-image me-2 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-car text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($booking['vehicle_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-sm">
                                                <div class="fw-bold"><?php echo date('M d', strtotime($booking['start_date'])); ?></div>
                                                <div class="text-muted">to <?php echo date('M d', strtotime($booking['end_date'])); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary"><?php echo formatCurrency($booking['total_amount']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $booking['status'] === 'active' ? 'success' : ($booking['status'] === 'completed' ? 'primary' : 'secondary'); ?> booking-status">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $booking['payment_status'] === 'paid' ? 'success' : 'warning'; ?> booking-status">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view-booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No bookings found for this user.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 