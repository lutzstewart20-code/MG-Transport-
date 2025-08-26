<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's bookings with enhanced payment status
$bookings_query = "SELECT b.*, v.name as vehicle_name, v.image_url, v.registration_number,
                   CASE 
                       WHEN b.payment_status = 'paid' 
                       THEN 'paid'
                       WHEN b.payment_status = 'payment_made_awaiting_approval' AND b.payment_method = 'sms_transfer'
                       THEN 'pending_sms'
                       WHEN b.payment_status = 'payment_made_awaiting_approval' AND b.payment_method = 'bank_transfer'
                       THEN 'payment_made_awaiting_approval'
                       ELSE b.payment_status 
                   END as display_status
                   FROM bookings b 
                   JOIN vehicles v ON b.vehicle_id = v.id 
                   WHERE b.user_id = ? 
                   ORDER BY b.created_at DESC";
$stmt = mysqli_prepare($conn, $bookings_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$bookings_result = mysqli_stmt_get_result($stmt);

// Check for payment success notifications
$payment_success = $_SESSION['payment_success'] ?? null;
$payment_message = $_SESSION['payment_message'] ?? null;
$payment_type = $_SESSION['payment_type'] ?? null;

// Clear session notifications after retrieving them
unset($_SESSION['payment_success'], $_SESSION['payment_message'], $_SESSION['payment_type']);

// Function to get status color and text
function getPaymentStatusInfo($status, $payment_method) {
    switch ($status) {
        case 'paid':
            return ['color' => 'success', 'text' => 'Paid', 'icon' => 'check-circle'];
        case 'payment_made_awaiting_approval':
            if ($payment_method === 'bank_transfer') {
                return ['color' => 'info', 'text' => 'Bank Transfer - Awaiting Verification', 'icon' => 'university'];
            } else {
                return ['color' => 'info', 'text' => 'Payment Made - Awaiting Approval', 'icon' => 'clock'];
            }
        case 'pending_sms':
            return ['color' => 'warning', 'text' => 'Pending - Upload Receipt', 'icon' => 'upload'];
        case 'pending':
            return ['color' => 'warning', 'text' => 'Pending Payment', 'icon' => 'credit-card'];
        case 'failed':
            return ['color' => 'danger', 'text' => 'Payment Failed', 'icon' => 'times-circle'];
        default:
            return ['color' => 'secondary', 'text' => 'Unknown', 'icon' => 'question-circle'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .success-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .success-notification.hide {
            animation: slideOutRight 0.5s ease-in forwards;
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .payment-success-card {
            border-left: 4px solid #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }
        
        .payment-pending-card {
            border-left: 4px solid #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        
        .payment-awaiting-approval-card {
            border-left: 4px solid #17a2b8;
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        }
        
        .payment-failed-card {
            border-left: 4px solid #dc3545;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        }
        
        .booking-card {
            transition: all 0.3s ease;
        }
        
        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .payment-amount {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .btn-payment {
            transition: all 0.2s ease;
        }
        
        .btn-payment:hover {
            transform: scale(1.05);
        }
        
        .status-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Success Notification Toast -->
    <?php if ($payment_success): ?>
    <div class="success-notification" id="successNotification">
        <div class="alert alert-<?php echo $payment_success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-<?php echo $payment_success ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <div>
                    <strong><?php echo $payment_success ? 'Success!' : 'Error!'; ?></strong>
                    <div class="small"><?php echo htmlspecialchars($payment_message); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
                            <a class="navbar-brand" href="index.php">
                    <img src="assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2">
                    <span class="d-none d-md-inline">MG Transport Services</span>
                </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking.php">Book Now</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="my-bookings.php">My Bookings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Bookings</h2>
                    <a href="booking.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                </div>
            </div>
        </div>

        <?php if (mysqli_num_rows($bookings_result) > 0): ?>
            <div class="row">
                <?php while ($booking = mysqli_fetch_assoc($bookings_result)): ?>
                <?php 
                    $status_info = getPaymentStatusInfo($booking['display_status'], $booking['payment_method']);
                    $card_class = '';
                    switch ($booking['display_status']) {
                        case 'paid':
                            $card_class = 'payment-success-card';
                            break;
                        case 'payment_made_awaiting_approval':
                            $card_class = 'payment-awaiting-approval-card';
                            break;
                        case 'pending':
                        case 'pending_sms':
                            $card_class = 'payment-pending-card';
                            break;
                        case 'failed':
                            $card_class = 'payment-failed-card';
                            break;
                    }
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm booking-card <?php echo $card_class; ?>">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Booking #<?php echo $booking['id']; ?></h6>
                                <span class="badge bg-<?php echo $status_info['color']; ?> status-badge">
                                    <i class="fas fa-<?php echo $status_info['icon']; ?> me-1"></i>
                                    <?php echo $status_info['text']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($booking['vehicle_name']); ?>" 
                                     class="rounded me-3" width="60" height="45">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($booking['vehicle_name']); ?></h6>
                                    <small class="text-muted">Reg: <?php echo htmlspecialchars($booking['registration_number']); ?></small>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Start Date</small>
                                    <div class="fw-bold"><?php echo formatDate($booking['start_date']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">End Date</small>
                                    <div class="fw-bold"><?php echo formatDate($booking['end_date']); ?></div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Duration</small>
                                    <div class="fw-bold"><?php echo $booking['total_days']; ?> days</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Rate/Day</small>
                                    <div class="fw-bold"><?php echo formatCurrency($booking['rate_per_day']); ?></div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Subtotal</small>
                                    <div class="fw-bold"><?php echo formatCurrency($booking['subtotal']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">GST</small>
                                    <div class="fw-bold"><?php echo formatCurrency($booking['gst_amount']); ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="payment-amount text-primary">Total: <?php echo formatCurrency($booking['total_amount']); ?></span>
                                </div>
                                
                                <!-- Payment Method Info -->
                                <?php if ($booking['payment_method']): ?>
                                <div class="mt-2">
                                    <small class="status-info">
                                        <i class="fas fa-credit-card me-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Messages -->
                                <?php if ($booking['display_status'] === 'payment_made_awaiting_approval'): ?>
                                    <?php if ($booking['payment_method'] === 'bank_transfer'): ?>
                                    <div class="alert alert-info alert-sm mt-2 mb-2">
                                        <i class="fas fa-university me-1"></i>
                                        <small>Your bank transfer payment has been confirmed and is awaiting admin verification.</small>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info alert-sm mt-2 mb-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <small>Your payment receipt has been uploaded and is awaiting admin approval.</small>
                                    </div>
                                    <?php endif; ?>
                                <?php elseif ($booking['display_status'] === 'pending_sms'): ?>
                                <div class="alert alert-warning alert-sm mt-2 mb-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <small>Please upload your SMS banking receipt to complete the payment.</small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <?php if ($booking['status'] === 'confirmed' && $booking['payment_status'] === 'pending'): ?>
                                        <a href="vehicle-agreement.php?booking_id=<?php echo $booking['id']; ?>&payment_integration=1" 
                                           class="btn btn-primary btn-sm btn-payment">
                                            <i class="fas fa-file-contract me-1"></i>Fill Agreement & Make Payment
                                        </a>
                                    <?php elseif ($booking['payment_status'] === 'paid'): ?>
                                        <a href="vehicle-agreement.php?vehicle_id=<?php echo $booking['vehicle_id']; ?>" 
                                           class="btn btn-success btn-sm btn-payment">
                                            <i class="fas fa-file-contract me-1"></i>Fill Agreement Form
                                        </a>
                                    <?php elseif ($booking['payment_status'] === 'pending_verification'): ?>
                                        <span class="badge bg-warning me-2">
                                            <i class="fas fa-clock me-1"></i>Payment Pending Verification
                                        </span>
                                    <?php endif; ?>
                                    
                                    <a href="generate_invoice.php?booking_id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-info btn-sm ms-2" target="_blank">
                                        <i class="fas fa-file-pdf me-1"></i>View Invoice
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($booking['pickup_location'] || $booking['dropoff_location']): ?>
                        <div class="card-footer bg-light">
                            <?php if ($booking['pickup_location']): ?>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt"></i> Pickup: <?php echo htmlspecialchars($booking['pickup_location']); ?>
                            </small>
                            <?php endif; ?>
                            <?php if ($booking['dropoff_location']): ?>
                            <br><small class="text-muted">
                                <i class="fas fa-map-marker-alt"></i> Dropoff: <?php echo htmlspecialchars($booking['dropoff_location']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No Bookings Found</h4>
                            <p class="text-muted">You haven't made any bookings yet.</p>
                            <a href="booking.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Make Your First Booking
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Include notification display -->
    <?php include 'includes/notification_display.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide success notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successNotification = document.getElementById('successNotification');
            if (successNotification) {
                setTimeout(function() {
                    successNotification.classList.add('hide');
                    setTimeout(function() {
                        successNotification.remove();
                    }, 500);
                }, 5000);
            }
            
            // Add click to dismiss functionality
            if (successNotification) {
                successNotification.addEventListener('click', function() {
                    this.classList.add('hide');
                    setTimeout(() => this.remove(), 500);
                });
            }
        });
        
        // Payment success notification function
        function showPaymentSuccess(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = 'success-notification';
            notification.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                        <div>
                            <strong>${type === 'success' ? 'Success!' : 'Error!'}</strong>
                            <div class="small">${message}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                notification.classList.add('hide');
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }, 5000);
            
            // Click to dismiss
            notification.addEventListener('click', function() {
                this.classList.add('hide');
                setTimeout(() => this.remove(), 500);
            });
        }
        
        // Listen for payment success events from other pages
        window.addEventListener('message', function(event) {
            if (event.data.type === 'payment_success') {
                showPaymentSuccess(event.data.message, event.data.status);
            }
        });
        
        // Check URL parameters for payment success
        const urlParams = new URLSearchParams(window.location.search);
        const paymentStatus = urlParams.get('payment_status');
        const paymentMessage = urlParams.get('payment_message');
        
        if (paymentStatus && paymentMessage) {
            showPaymentSuccess(decodeURIComponent(paymentMessage), paymentStatus);
            // Clean up URL
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('payment_status');
            newUrl.searchParams.delete('payment_message');
            window.history.replaceState({}, '', newUrl);
        }
    </script>
</body>
</html> 