<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    if ($action === 'confirm') {
        $update_booking = "UPDATE bookings SET status = 'confirmed' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_booking);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Send notification to customer
            $customer_query = "SELECT user_id FROM bookings WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $customer_query);
            mysqli_stmt_bind_param($stmt2, "i", $booking_id);
            mysqli_stmt_execute($stmt2);
            $result = mysqli_stmt_get_result($stmt2);
            $booking = mysqli_fetch_assoc($result);
            
            if ($booking) {
                createNotification($booking['user_id'], 'Booking Confirmed', 'Your booking has been confirmed by admin!', 'success', $conn);
            }
            
            redirectWithMessage('pending-bookings.php', 'Booking confirmed successfully!', 'success');
        } else {
            redirectWithMessage('pending-bookings.php', 'Error confirming booking.', 'error');
        }
    } elseif ($action === 'cancel') {
        $update_booking = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_booking);
        mysqli_stmt_bind_param($stmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Send notification to customer
            $customer_query = "SELECT user_id FROM bookings WHERE id = ?";
            $stmt2 = mysqli_prepare($conn, $customer_query);
            mysqli_stmt_bind_param($stmt2, "i", $booking_id);
            mysqli_stmt_execute($stmt2);
            $result = mysqli_stmt_get_result($stmt2);
            $booking = mysqli_fetch_assoc($result);
            
            if ($booking) {
                createNotification($booking['user_id'], 'Booking Cancelled', 'Your booking has been cancelled by admin.', 'error', $conn);
            }
            
            redirectWithMessage('pending-bookings.php', 'Booking cancelled.', 'warning');
        } else {
            redirectWithMessage('pending-bookings.php', 'Error cancelling booking.', 'error');
        }
    }
}

// Get pending bookings
$pending_bookings_query = "SELECT b.*, v.name as vehicle_name, v.image_url, v.registration_number,
                          u.first_name, u.last_name, u.email, u.phone
                          FROM bookings b
                          JOIN vehicles v ON b.vehicle_id = v.id
                          JOIN users u ON b.user_id = u.id
                          WHERE b.status IN ('pending', 'payment_pending')
                          ORDER BY b.created_at DESC";
$pending_bookings_result = mysqli_query($conn, $pending_bookings_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Bookings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .booking-card {
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .payment-status {
            font-size: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .vehicle-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <div class="d-flex align-items-center">
                    <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-3" style="width: 50px; height: 50px; border-radius: 50%; background: #fbbf24;">
                    <div class="d-none d-md-block">
                        <div class="text-xl fw-bold text-white">MG TRANSPORT SERVICES</div>
                        <div class="text-sm text-warning">ADMIN PANEL</div>
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">
                            <i class="fas fa-car me-2"></i>Vehicles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="bookings.php">
                            <i class="fas fa-calendar-check me-2"></i>Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid admin-container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-clock me-2 text-warning"></i>Pending Bookings</h2>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $pending_count = 0;
                                            $payment_pending_count = 0;
                                            if ($pending_bookings_result) {
                                                mysqli_data_seek($pending_bookings_result, 0);
                                                while ($booking = mysqli_fetch_assoc($pending_bookings_result)) {
                                                    if ($booking['status'] === 'pending') $pending_count++;
                                                    elseif ($booking['status'] === 'payment_pending') $payment_pending_count++;
                                                }
                                            }
                                            echo $pending_count + $payment_pending_count;
                                            ?>
                                        </h4>
                                        <small>Total Pending</small>
                                    </div>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $pending_count; ?></h4>
                                        <small>Awaiting Confirmation</small>
                                    </div>
                                    <i class="fas fa-calendar-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $payment_pending_count; ?></h4>
                                        <small>Payment Pending</small>
                                    </div>
                                    <i class="fas fa-credit-card fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">
                                            <?php 
                                            $total_amount = 0;
                                            if ($pending_bookings_result) {
                                                mysqli_data_seek($pending_bookings_result, 0);
                                                while ($booking = mysqli_fetch_assoc($pending_bookings_result)) {
                                                    $total_amount += $booking['total_amount'];
                                                }
                                            }
                                            echo formatCurrency($total_amount);
                                            ?>
                                        </h4>
                                        <small>Total Value</small>
                                    </div>
                                    <i class="fas fa-dollar-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Bookings List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Pending Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_bookings_result && mysqli_num_rows($pending_bookings_result) > 0): ?>
                            <div class="row">
                                <?php 
                                mysqli_data_seek($pending_bookings_result, 0);
                                while ($booking = mysqli_fetch_assoc($pending_bookings_result)): 
                                ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card booking-card">
                                        <div class="card-header">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Booking #<?php echo $booking['id']; ?></h6>
                                                <span class="payment-status bg-<?php 
                                                    echo $booking['status'] === 'pending' ? 'warning' : 'primary'; 
                                                ?> text-white">
                                                    <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Customer Info -->
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="customer-avatar me-3">
                                                    <?php echo strtoupper(substr($booking['first_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($booking['phone']); ?></small>
                                                </div>
                                            </div>

                                            <!-- Vehicle Info -->
                                            <div class="d-flex align-items-center mb-3">
                                                <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($booking['vehicle_name']); ?>" 
                                                     class="vehicle-image me-3">
                                                <div>
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($booking['vehicle_name']); ?></h6>
                                                    <small class="text-muted">Reg: <?php echo htmlspecialchars($booking['registration_number']); ?></small>
                                                </div>
                                            </div>

                                            <!-- Booking Details -->
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Start Date:</small><br>
                                                    <strong><?php echo formatDate($booking['start_date']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">End Date:</small><br>
                                                    <strong><?php echo formatDate($booking['end_date']); ?></strong>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Duration:</small><br>
                                                    <strong><?php echo $booking['total_days']; ?> days</strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Payment Method:</small><br>
                                                    <strong><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></strong>
                                                </div>
                                            </div>

                                            <!-- Payment Details -->
                                            <div class="row mb-3">
                                                <div class="col-6">
                                                    <small class="text-muted">Subtotal:</small><br>
                                                    <strong><?php echo formatCurrency($booking['subtotal']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">GST:</small><br>
                                                    <strong><?php echo formatCurrency($booking['gst_amount']); ?></strong>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <small class="text-muted">Total Amount:</small><br>
                                                <strong class="text-primary fs-5"><?php echo formatCurrency($booking['total_amount']); ?></strong>
                                            </div>

                                            <!-- Location Details -->
                                            <?php if ($booking['pickup_location'] || $booking['dropoff_location']): ?>
                                            <div class="mb-3">
                                                <?php if ($booking['pickup_location']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i> Pickup: <?php echo htmlspecialchars($booking['pickup_location']); ?>
                                                </small><br>
                                                <?php endif; ?>
                                                <?php if ($booking['dropoff_location']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i> Dropoff: <?php echo htmlspecialchars($booking['dropoff_location']); ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Special Requests -->
                                            <?php if ($booking['special_requests']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">Special Requests:</small><br>
                                                <em><?php echo htmlspecialchars($booking['special_requests']); ?></em>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Action Buttons -->
                                            <div class="d-flex gap-2">
                                                <a href="view-booking.php?id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Details
                                                </a>
                                                
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-success btn-sm"
                                                            onclick="return confirm('Confirm this booking?')">
                                                        <i class="fas fa-check me-1"></i>Confirm
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Cancel this booking?')">
                                                        <i class="fas fa-times me-1"></i>Cancel
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h5 class="text-success">No Pending Bookings</h5>
                                <p class="text-muted">All bookings have been processed!</p>
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