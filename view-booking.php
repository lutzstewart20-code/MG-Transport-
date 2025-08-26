<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    redirectWithMessage('bookings.php', 'Invalid booking ID.', 'error');
}

// Get booking details with user and vehicle information
$booking_query = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone,
                         v.name as vehicle_name, v.model, v.year, v.registration_number,
                         v.vehicle_type, v.image_url
                  FROM bookings b
                  JOIN users u ON b.user_id = u.id
                  JOIN vehicles v ON b.vehicle_id = v.id
                  WHERE b.id = ?";

$stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    redirectWithMessage('bookings.php', 'Booking not found.', 'error');
}

// Get invoice information
$invoice_query = "SELECT * FROM invoices WHERE booking_id = ?";
$invoice_stmt = mysqli_prepare($conn, $invoice_query);
mysqli_stmt_bind_param($invoice_stmt, "i", $booking_id);
mysqli_stmt_execute($invoice_stmt);
$invoice_result = mysqli_stmt_get_result($invoice_stmt);
$invoice = mysqli_fetch_assoc($invoice_result);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $new_status = sanitizeInput($_POST['status']);
        $update_query = "UPDATE bookings SET status = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $booking_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Create notification for user
            createNotification($booking['user_id'], 'Booking Status Updated', 
                            "Your booking for {$booking['vehicle_name']} has been updated to: " . ucfirst($new_status), 
                            'info', $conn);
            
            redirectWithMessage("view-booking.php?id=$booking_id", 'Booking status updated successfully.', 'success');
        } else {
            redirectWithMessage("view-booking.php?id=$booking_id", 'Error updating booking status.', 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Booking - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
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
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-check text-primary"></i> Booking Details</h2>
            <div>
                <a href="bookings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>
        </div>

        <!-- Display Messages -->
        <?php displayMessage(); ?>

        <div class="row">
            <!-- Booking Information -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Booking Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Customer Information</h6>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($booking['phone']); ?></p>
                                <p><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Vehicle Information</h6>
                                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                                <p><strong>Model:</strong> <?php echo htmlspecialchars($booking['model'] . ' ' . $booking['year']); ?></p>
                                <p><strong>Registration:</strong> <?php echo htmlspecialchars($booking['registration_number']); ?></p>
                                <p><strong>Type:</strong> <?php echo ucfirst(htmlspecialchars($booking['vehicle_type'])); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Booking Details</h6>
                                <p><strong>Start Date:</strong> <?php echo formatDate($booking['start_date']); ?></p>
                                <p><strong>End Date:</strong> <?php echo formatDate($booking['end_date']); ?></p>
                                <p><strong>Total Days:</strong> <?php echo $booking['total_days']; ?> days</p>
                                <p><strong>Daily Rate:</strong> <?php echo formatCurrency($booking['rate_per_day']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Location & Payment</h6>
                                <p><strong>Pickup:</strong> <?php echo htmlspecialchars($booking['pickup_location']); ?></p>
                                <p><strong>Dropoff:</strong> <?php echo htmlspecialchars($booking['dropoff_location']); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($booking['payment_method']))); ?></p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge bg-<?php echo $booking['payment_status'] === 'paid' ? 'success' : ($booking['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($booking['payment_status'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($booking['special_requests']): ?>
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <h6>Special Requests</h6>
                                    <p><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Financial Information -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Financial Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <span><?php echo formatCurrency($booking['subtotal']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>GST (10%):</span>
                                    <span><?php echo formatCurrency($booking['gst_amount']); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total Amount:</span>
                                    <span class="text-primary"><?php echo formatCurrency($booking['total_amount']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Invoice Information</h6>
                                <?php if ($invoice): ?>
                                    <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                                    <p><strong>Issue Date:</strong> <?php echo formatDate($invoice['issue_date']); ?></p>
                                    <p><strong>Due Date:</strong> <?php echo formatDate($invoice['due_date']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php echo $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'sent' ? 'info' : 'warning'); ?>">
                                            <?php echo ucfirst(htmlspecialchars($invoice['status'])); ?>
                                        </span>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted">No invoice generated yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Management -->
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Status Management</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Current Status:</strong>
                            <span class="badge bg-<?php echo $booking['status'] === 'completed' ? 'success' : ($booking['status'] === 'active' ? 'info' : ($booking['status'] === 'confirmed' ? 'primary' : ($booking['status'] === 'cancelled' ? 'danger' : 'warning'))); ?>">
                                <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                            </span>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <div class="mb-3">
                                <label for="status" class="form-label">Update Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="active" <?php echo $booking['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Booking Timeline -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Timeline</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6>Booking Created</h6>
                                    <small class="text-muted"><?php echo formatDateTime($booking['created_at']); ?></small>
                                </div>
                            </div>
                            <?php if ($booking['updated_at'] !== $booking['created_at']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6>Last Updated</h6>
                                        <small class="text-muted"><?php echo formatDateTime($booking['updated_at']); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope"></i> Contact Customer
                            </a>
                            <a href="view-vehicle.php?id=<?php echo $booking['vehicle_id']; ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-car"></i> View Vehicle
                            </a>
                            <a href="view-user.php?id=<?php echo $booking['user_id']; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user"></i> View Customer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -35px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #e3e6f0;
        }
        .timeline-content h6 {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .timeline-content small {
            font-size: 0.8rem;
        }
    </style>
</body>
</html> 