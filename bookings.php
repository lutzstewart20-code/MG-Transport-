<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'confirm':
            $status = 'confirmed';
            $payment_status = 'paid';
            break;
        case 'activate':
            $status = 'active';
            break;
        case 'complete':
            $status = 'completed';
            break;
        case 'cancel':
            $status = 'cancelled';
            break;
        case 'verify_payment':
            $status = 'confirmed';
            $payment_status = 'paid';
            break;
        case 'verify_receipt':
            $status = 'confirmed';
            $payment_status = 'paid';
            break;
        case 'verify_bank_transfer':
            $status = 'confirmed';
            $payment_status = 'paid';
            break;
        case 'confirm_cash_payment':
            $status = 'confirmed';
            $payment_status = 'paid';
            break;
        default:
            redirectWithMessage('bookings.php', 'Invalid action.', 'error');
    }
    
    if ($action === 'verify_payment' || $action === 'verify_receipt' || $action === 'verify_bank_transfer' || $action === 'confirm_cash_payment') {
        $update_query = "UPDATE bookings SET status = ?, payment_status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $status, $payment_status, $booking_id);
        
        // If verifying receipt, also update receipt status
        if ($action === 'verify_receipt') {
            $update_receipt = "UPDATE payment_receipts SET status = 'verified', 
                              verified_by = ?, verified_at = NOW() 
                              WHERE booking_id = ? AND status = 'pending'";
            $receipt_stmt = mysqli_prepare($conn, $update_receipt);
            mysqli_stmt_bind_param($receipt_stmt, "ii", $_SESSION['user_id'], $booking_id);
            mysqli_stmt_execute($receipt_stmt);
        }
        
        // If verifying bank transfer, also update receipt status
        if ($action === 'verify_bank_transfer') {
            $update_receipt = "UPDATE payment_receipts SET status = 'verified', 
                              verified_by = ?, verified_at = NOW() 
                              WHERE booking_id = ? AND status = 'pending'";
            $receipt_stmt = mysqli_prepare($conn, $update_receipt);
            mysqli_stmt_bind_param($receipt_stmt, "ii", $_SESSION['user_id'], $booking_id);
            mysqli_stmt_execute($receipt_stmt);
        }
    } else {
        $update_query = "UPDATE bookings SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        // Get booking details for notification
        $booking_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name, u.email 
                         FROM bookings b 
                         JOIN vehicles v ON b.vehicle_id = v.id 
                         JOIN users u ON b.user_id = u.id 
                         WHERE b.id = ?";
        $booking_stmt = mysqli_prepare($conn, $booking_query);
        mysqli_stmt_bind_param($booking_stmt, "i", $booking_id);
        mysqli_stmt_execute($booking_stmt);
        $booking_result = mysqli_stmt_get_result($booking_stmt);
        $booking = mysqli_fetch_assoc($booking_result);
        
        // Send email notification
        if ($action === 'verify_payment') {
            $subject = "Payment Verified - Booking Confirmed - MG Transport Services";
            $message = "
            <h2>Payment Verified - Booking Confirmed</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Great news! Your bank transfer payment has been verified and your booking is now confirmed:</p>
            <ul>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Booking ID:</strong> #{$booking['id']}</li>
                <li><strong>Status:</strong> Confirmed</li>
                <li><strong>Start Date:</strong> " . formatDate($booking['start_date']) . "</li>
                <li><strong>End Date:</strong> " . formatDate($booking['end_date']) . "</li>
                <li><strong>Total Amount:</strong> " . formatCurrency($booking['total_amount']) . "</li>
            </ul>
            <p>Your vehicle will be ready for pickup on the scheduled date. Please bring your ID and booking confirmation.</p>
            <p>Thank you for choosing MG Transport Services!</p>";
        } elseif ($action === 'verify_bank_transfer') {
            $subject = "Bank Transfer Verified - Booking Confirmed - MG Transport Services";
            $message = "
            <h2>Bank Transfer Verified - Booking Confirmed</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Great news! Your bank transfer payment has been verified and your booking is now confirmed:</p>
            <ul>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Booking ID:</strong> #{$booking['id']}</li>
                <li><strong>Status:</strong> Confirmed</li>
                <li><strong>Start Date:</strong> " . formatDate($booking['start_date']) . "</li>
                <li><strong>End Date:</strong> " . formatDate($booking['end_date']) . "</li>
                <li><strong>Total Amount:</strong> " . formatCurrency($booking['total_amount']) . "</li>
            </ul>
            <p>Your vehicle will be ready for pickup on the scheduled date. Please bring your ID and booking confirmation.</p>
            <p>Thank you for choosing MG Transport Services!</p>";
        } elseif ($action === 'confirm_cash_payment') {
            $subject = "Cash Payment Confirmed - Booking Confirmed - MG Transport Services";
            $message = "
            <h2>Cash Payment Confirmed - Booking Confirmed</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Great news! Your cash payment has been confirmed and your booking is now confirmed:</p>
            <ul>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Status:</strong> Confirmed</li>
                <li><strong>Start Date:</strong> " . formatDate($booking['start_date']) . "</li>
                <li><strong>End Date:</strong> " . formatDate($booking['end_date']) . "</li>
                <li><strong>Total Amount:</strong> " . formatCurrency($booking['total_amount']) . "</li>
            </ul>
            <p>Your vehicle will be ready for pickup on the scheduled date. Please bring your ID, booking confirmation, and the cash payment amount.</p>
            <p>Thank you for choosing MG Transport Services!</p>";
        } else {
            $subject = "Booking Status Updated - MG Transport Services";
            $message = "
            <h2>Booking Status Update</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Your booking status has been updated:</p>
            <ul>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Booking ID:</strong> #{$booking['id']}</li>
                <li><strong>New Status:</strong> " . ucfirst($status) . "</li>
                <li><strong>Start Date:</strong> " . formatDate($booking['start_date']) . "</li>
                <li><strong>End Date:</strong> " . formatDate($booking['end_date']) . "</li>
            </ul>
            <p>Thank you for choosing MG Transport Services!</p>";
        }
        
        sendEmail($booking['email'], $subject, $message);
        
        // Create enhanced notification
        if ($action === 'verify_payment' || $action === 'verify_bank_transfer' || $action === 'confirm_cash_payment') {
            createPaymentNotification($conn, $booking['user_id'], $booking_id, 'verified');
        } else {
            createBookingNotification($conn, $booking['user_id'], $booking_id, $action);
        }
        
        redirectWithMessage('bookings.php', 'Booking status updated successfully.', 'success');
    } else {
        redirectWithMessage('bookings.php', 'Error updating booking status.', 'error');
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter) {
    $where_conditions[] = "b.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(b.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT b.*, v.name as vehicle_name, v.registration_number, 
          u.first_name, u.last_name, u.email, u.phone,
          pr.transaction_id as receipt_transaction_id, pr.amount_paid as receipt_amount,
          pr.bank_name as receipt_bank, pr.receipt_file, pr.status as receipt_status
          FROM bookings b 
          JOIN vehicles v ON b.vehicle_id = v.id 
          JOIN users u ON b.user_id = u.id 
          LEFT JOIN payment_receipts pr ON b.id = pr.booking_id
          $where_clause 
          ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$bookings_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/admin-compact.css" rel="stylesheet">
    <style>
        .modal-dialog {
            max-width: 800px;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        .booking-details-table {
            font-size: 0.9rem;
        }
        .booking-details-table td {
            padding: 0.5rem;
            vertical-align: top;
        }
        .modal-content {
            border-radius: 10px;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .btn-view-booking {
            transition: all 0.2s ease;
        }
        .btn-view-booking:hover {
            transform: scale(1.05);
        }
    </style>
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
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="bookings.php">Bookings</a>
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
                    <h1 class="h3 mb-0">Booking Management</h1>
                    <div class="btn-group" role="group">
                        <a href="reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar"></i> Generate Reports
                        </a>
                        <a href="export-bookings.php" class="btn btn-outline-success">
                            <i class="fas fa-download"></i> Export Data
                        </a>
                    </div>
                </div>
                <?php displayMessage(); ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status Filter</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-select" id="date_filter" name="date_filter">
                                    <option value="">All Dates</option>
                                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="bookings.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Vehicle</th>
                                        <th>Dates</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                                    <tr>
                                        <td>#<?php echo $booking['id']; ?></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['vehicle_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($booking['registration_number']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo formatDate($booking['start_date']); ?></strong>
                                                <br>
                                                <small class="text-muted">to <?php echo formatDate($booking['end_date']); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo $booking['total_days']; ?> days</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo formatCurrency($booking['total_amount']); ?></strong>
                                                <br>
                                                <small class="text-muted">Rate: <?php echo formatCurrency($booking['rate_per_day']); ?>/day</small>
                                            </div>
                                        </td>
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
                                        <td>
                                            <?php 
                                            $payment_status_text = $booking['payment_status'];
                                            $payment_status_color = 'secondary';
                                            
                                            if ($booking['payment_method'] === 'sms_transfer' && $booking['payment_receipt_path']) {
                                                $payment_status_text = 'Payment Made - Awaiting Approval';
                                                $payment_status_color = 'info';
                                            } elseif ($booking['payment_status'] === 'paid') {
                                                $payment_status_color = 'success';
                                            } elseif ($booking['payment_status'] === 'pending') {
                                                $payment_status_color = 'warning';
                                            } elseif ($booking['payment_status'] === 'failed') {
                                                $payment_status_color = 'danger';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $payment_status_color; ?>">
                                                <?php echo ucfirst($payment_status_text); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($booking['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-view-booking" 
                                                        onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="generate_invoice.php?booking_id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" target="_blank" title="View Invoice">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <?php if ($booking['payment_method'] === 'bank_transfer'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="verify_bank_transfer">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Verify bank transfer payment and confirm booking?')" title="Verify Bank Transfer">
                                                            <i class="fas fa-university"></i>
                                                        </button>
                                                    </form>
                                                    <?php elseif ($booking['payment_method'] === 'sms_transfer' && $booking['payment_receipt_path']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="verify_receipt">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Verify receipt and confirm booking?')" title="Verify Receipt">
                                                            <i class="fas fa-receipt"></i>
                                                        </button>
                                                    </form>
                                                    <?php elseif ($booking['payment_method'] === 'cash'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="confirm_cash_payment">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm cash payment and confirm booking?')" title="Confirm Cash Payment">
                                                            <i class="fas fa-money-bill-wave"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="action" value="confirm">
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm this booking?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                <?php if ($booking['payment_status'] === 'pending'): ?>
                                                <span class="badge bg-warning me-2" title="Payment Required">
                                                    <i class="fas fa-credit-card me-1"></i>Payment Required
                                                </span>
                                                <?php elseif ($booking['payment_status'] === 'pending_verification'): ?>
                                                <span class="badge bg-info me-2" title="Payment Pending Verification">
                                                    <i class="fas fa-clock me-1"></i>Payment Pending Verification
                                                </span>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Activate this booking?')" <?php echo ($booking['payment_status'] === 'pending') ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <?php if ($booking['status'] === 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="complete">
                                                    <button type="submit" class="btn btn-sm btn-info" onclick="return confirm('Complete this booking?')">
                                                        <i class="fas fa-flag-checkered"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <?php if (in_array($booking['status'], ['pending', 'confirmed'])): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1" aria-labelledby="bookingDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingDetailsModalLabel">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookingDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store booking data for modal
        const bookingData = <?php 
            // Reset the result pointer
            mysqli_data_seek($bookings_result, 0);
            $bookings_array = [];
            while ($booking = mysqli_fetch_assoc($bookings_result)) {
                $bookings_array[] = $booking;
            }
            echo json_encode($bookings_array);
        ?>;

        function viewBookingDetails(bookingId) {
            const booking = bookingData.find(b => b.id == bookingId);
            if (!booking) {
                alert('Booking not found');
                return;
            }

            const modal = new bootstrap.Modal(document.getElementById('bookingDetailsModal'));
            const content = document.getElementById('bookingDetailsContent');
            const title = document.getElementById('bookingDetailsModalLabel');

            // Update modal title
            title.textContent = `Booking Details #${booking.id}`;

            // Build modal content
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary"><i class="fas fa-user me-2"></i>Customer Information</h6>
                        <table class="table table-sm booking-details-table">
                            <tr><td><strong>Name:</strong></td><td>${escapeHtml(booking.first_name + ' ' + booking.last_name)}</td></tr>
                            <tr><td><strong>Email:</strong></td><td>${escapeHtml(booking.email)}</td></tr>
                            <tr><td><strong>Phone:</strong></td><td>${escapeHtml(booking.phone)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="fas fa-car me-2"></i>Vehicle Information</h6>
                        <table class="table table-sm booking-details-table">
                            <tr><td><strong>Vehicle:</strong></td><td>${escapeHtml(booking.vehicle_name)}</td></tr>
                            <tr><td><strong>Registration:</strong></td><td>${escapeHtml(booking.registration_number)}</td></tr>
                            <tr><td><strong>Rate:</strong></td><td>${formatCurrency(booking.rate_per_day)}/day</td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-info"><i class="fas fa-calendar me-2"></i>Booking Details</h6>
                        <table class="table table-sm booking-details-table">
                            <tr><td><strong>Start Date:</strong></td><td>${formatDate(booking.start_date)}</td></tr>
                            <tr><td><strong>End Date:</strong></td><td>${formatDate(booking.end_date)}</td></tr>
                            <tr><td><strong>Total Days:</strong></td><td>${booking.total_days}</td></tr>
                            <tr><td><strong>Pickup Location:</strong></td><td>${escapeHtml(booking.pickup_location)}</td></tr>
                            <tr><td><strong>Dropoff Location:</strong></td><td>${escapeHtml(booking.dropoff_location)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-warning"><i class="fas fa-money-bill me-2"></i>Financial Details</h6>
                        <table class="table table-sm booking-details-table">
                            <tr><td><strong>Subtotal:</strong></td><td>${formatCurrency(booking.subtotal)}</td></tr>
                            <tr><td><strong>GST Amount:</strong></td><td>${formatCurrency(booking.gst_amount)}</td></tr>
                            <tr><td><strong>Total Amount:</strong></td><td><strong>${formatCurrency(booking.total_amount)}</strong></td></tr>
                            <tr><td><strong>Payment Method:</strong></td><td>${formatPaymentMethod(booking.payment_method)}</td></tr>
                            <tr><td><strong>Payment Status:</strong></td><td><span class="badge bg-${getPaymentStatusColor(booking.payment_status)}">${formatPaymentStatus(booking.payment_status)}</span></td></tr>
                        </table>
                    </div>
                </div>`;

            // Add payment details if available
            if (booking.payment_method === 'bank_transfer' && booking.payment_details) {
                try {
                    const paymentDetails = JSON.parse(booking.payment_details);
                    html += `
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="fas fa-university me-2"></i>Bank Transfer Details</h6>
                                <table class="table table-sm booking-details-table">
                                    <tr><td><strong>Account Name:</strong></td><td>${escapeHtml(paymentDetails.account_name || '')}</td></tr>
                                    <tr><td><strong>Account Number:</strong></td><td>${escapeHtml(paymentDetails.account_number || '')}</td></tr>
                                    <tr><td><strong>Bank Name:</strong></td><td>${escapeHtml(paymentDetails.bank_name || '')}</td></tr>
                                    <tr><td><strong>Reference Number:</strong></td><td>${escapeHtml(paymentDetails.reference_number || '')}</td></tr>
                                </table>
                            </div>
                        </div>`;
                } catch (e) {
                    console.error('Error parsing payment details:', e);
                }
            }

            // Add SMS payment details if available
            if (booking.payment_method === 'sms_transfer' && booking.payment_details) {
                try {
                    const paymentDetails = JSON.parse(booking.payment_details);
                    html += `
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-success"><i class="fas fa-mobile-alt me-2"></i>SMS Transfer Details</h6>
                                <table class="table table-sm booking-details-table">
                                    <tr><td><strong>Phone Number:</strong></td><td>${escapeHtml(paymentDetails.phone_number || '')}</td></tr>
                                    <tr><td><strong>Reference Number:</strong></td><td>${escapeHtml(paymentDetails.reference_number || '')}</td></tr>
                                </table>
                            </div>
                        </div>`;
                } catch (e) {
                    console.error('Error parsing SMS payment details:', e);
                }
            }

            // Add payment receipt if available
            if (booking.payment_method === 'sms_transfer' && booking.payment_receipt_path) {
                html += `
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-success"><i class="fas fa-receipt me-2"></i>Payment Receipt</h6>
                            <div class="text-center">
                                <img src="../${booking.payment_receipt_path}" alt="Payment Receipt" class="img-fluid" style="max-width: 400px; border: 1px solid #ddd; border-radius: 8px;">
                                <br><br>
                                <a href="../${booking.payment_receipt_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i> View Full Size
                                </a>
                            </div>
                        </div>
                    </div>`;
            }
                            <table class="table table-sm booking-details-table">
                                <tr><td><strong>Transaction ID:</strong></td><td>${escapeHtml(booking.receipt_transaction_id || '')}</td></tr>
                                <tr><td><strong>Amount Paid:</strong></td><td>${formatCurrency(booking.receipt_amount || 0)}</td></tr>
                                <tr><td><strong>Bank:</strong></td><td>${escapeHtml(booking.receipt_bank || '')}</td></tr>
                                <tr><td><strong>Receipt Status:</strong></td><td><span class="badge bg-${getReceiptStatusColor(booking.receipt_status)}">${formatReceiptStatus(booking.receipt_status)}</span></td></tr>
                                <tr><td><strong>Receipt File:</strong></td><td><a href="../uploads/receipts/${escapeHtml(booking.receipt_file)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>View Receipt</a></td></tr>
                            </table>
                        </div>
                    </div>`;
            }

            // Add special requests if available
            if (booking.special_requests) {
                html += `
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-info"><i class="fas fa-comment me-2"></i>Special Requests</h6>
                            <div class="alert alert-info">
                                ${escapeHtml(booking.special_requests).replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    </div>`;
            }

            content.innerHTML = html;
            modal.show();
        }

        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PG', {
                style: 'currency',
                currency: 'PGK'
            }).format(amount);
        }

        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-PG');
        }

        function formatPaymentMethod(method) {
            return method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        function formatPaymentStatus(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        }

        function formatReceiptStatus(status) {
            return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Pending';
        }

        function getPaymentStatusColor(status) {
            return status === 'paid' ? 'success' : 
                   status === 'pending' ? 'warning' : 
                   status === 'failed' ? 'danger' : 'secondary';
        }

        function getReceiptStatusColor(status) {
            return status === 'verified' ? 'success' : 
                   status === 'rejected' ? 'danger' : 'warning';
        }

        // Prevent modal from closing when clicking inside
        document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                e.stopPropagation();
            }
        });
    </script>
</body>
</html> 