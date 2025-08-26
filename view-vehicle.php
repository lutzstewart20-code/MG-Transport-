<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get vehicle ID
$vehicle_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vehicle_id) {
    redirectWithMessage('vehicles.php', 'Invalid vehicle ID.', 'error');
}

// Get vehicle data
$query = "SELECT * FROM vehicles WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$vehicle = mysqli_fetch_assoc($result);

if (!$vehicle) {
    redirectWithMessage('vehicles.php', 'Vehicle not found.', 'error');
}

// Get vehicle statistics
$stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_bookings,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_booking_value,
    SUM(total_days) as total_days_booked
    FROM bookings WHERE vehicle_id = ?";

$stats_stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stats_stmt, "i", $vehicle_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent bookings for this vehicle
$bookings_query = "SELECT b.*, u.first_name, u.last_name, u.email
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.vehicle_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10";

$bookings_stmt = mysqli_prepare($conn, $bookings_query);
mysqli_stmt_bind_param($bookings_stmt, "i", $vehicle_id);
mysqli_stmt_execute($bookings_stmt);
$bookings_result = mysqli_stmt_get_result($bookings_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Vehicle - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2">
                <span class="d-none d-md-inline">MG Transport Services Admin</span>
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
                        <a class="nav-link active" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
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
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Vehicle Details</h1>
                    <div class="btn-group" role="group">
                        <a href="edit-vehicle.php?id=<?php echo $vehicle['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Vehicle
                        </a>
                        <a href="vehicles.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Vehicles
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Vehicle Information -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Vehicle Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <?php if ($vehicle['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($vehicle['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($vehicle['name']); ?>"
                                         class="img-fluid rounded mb-3" style="max-height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" 
                                         style="height: 200px;">
                                        <i class="fas fa-car fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($vehicle['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($vehicle['model']); ?> (<?php echo $vehicle['year']; ?>)</p>
                                
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p><strong>Registration:</strong> <?php echo htmlspecialchars($vehicle['registration_number']); ?></p>
                                        <p><strong>Type:</strong> <span class="badge bg-primary"><?php echo ucfirst($vehicle['vehicle_type']); ?></span></p>
                                        <p><strong>Rate:</strong> <?php echo formatCurrency($vehicle['rate_per_day']); ?>/day</p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php echo $vehicle['status'] === 'available' ? 'success' : ($vehicle['status'] === 'maintenance' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($vehicle['status']); ?>
                                            </span>
                                        </p>
                                        <p><strong>Created:</strong> <?php echo formatDate($vehicle['created_at']); ?></p>
                                        <?php if ($vehicle['updated_at']): ?>
                                            <p><strong>Updated:</strong> <?php echo formatDate($vehicle['updated_at']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($vehicle['description']): ?>
                                    <hr>
                                    <h6>Description</h6>
                                    <p><?php echo nl2br(htmlspecialchars($vehicle['description'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($vehicle['next_service_date']): ?>
                                    <hr>
                                    <h6>Maintenance</h6>
                                    <?php 
                                    $service_date = new DateTime($vehicle['next_service_date']);
                                    $today = new DateTime();
                                    $days_until = $today->diff($service_date)->days;
                                    $badge_class = $days_until <= 7 ? 'danger' : ($days_until <= 30 ? 'warning' : 'success');
                                    ?>
                                    <p><strong>Next Service:</strong> 
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo formatDate($vehicle['next_service_date']); ?>
                                        </span>
                                        (<?php echo $days_until; ?> days <?php echo $days_until > 0 ? 'remaining' : 'overdue'; ?>)
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Statistics -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Vehicle Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-primary"><?php echo $stats['total_bookings']; ?></h4>
                                    <small class="text-muted">Total Bookings</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-success"><?php echo formatCurrency($stats['total_revenue']); ?></h4>
                                    <small class="text-muted">Total Revenue</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info"><?php echo $stats['total_days_booked']; ?></h4>
                                    <small class="text-muted">Days Booked</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-warning"><?php echo formatCurrency($stats['avg_booking_value']); ?></h4>
                                    <small class="text-muted">Avg. Booking</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-6">
                                <p><strong>Completed:</strong> <?php echo $stats['completed_bookings']; ?></p>
                                <p><strong>Active:</strong> <?php echo $stats['active_bookings']; ?></p>
                            </div>
                            <div class="col-6">
                                <p><strong>Completion Rate:</strong> 
                                    <?php echo $stats['total_bookings'] > 0 ? round(($stats['completed_bookings'] / $stats['total_bookings']) * 100, 1) : 0; ?>%
                                </p>
                                <p><strong>Utilization:</strong> 
                                    <?php 
                                    $days_since_created = (new DateTime())->diff(new DateTime($vehicle['created_at']))->days;
                                    echo $days_since_created > 0 ? round(($stats['total_days_booked'] / $days_since_created) * 100, 1) : 0; 
                                    ?>%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Customer</th>
                                            <th>Dates</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                                        <tr>
                                            <td>#<?php echo $booking['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo formatDate($booking['start_date']); ?> - <?php echo formatDate($booking['end_date']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo $booking['total_days']; ?> days</small>
                                            </td>
                                            <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $booking['status'] === 'active' ? 'success' : 
                                                        ($booking['status'] === 'confirmed' ? 'primary' : 
                                                        ($booking['status'] === 'completed' ? 'info' : 
                                                        ($booking['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($booking['created_at']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No bookings found for this vehicle</h5>
                                <p class="text-muted">This vehicle hasn't been booked yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 