<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirectWithMessage('login.php', 'Please login to access this page.', 'warning');
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

// Check if payment was successful
if (!isset($_SESSION['payment_success']) || !$_SESSION['payment_success']) {
    redirectWithMessage('my-bookings.php', 'No successful payment found.', 'error');
}

// Get booking details
$booking_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name, u.email 
                 FROM bookings b 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 JOIN users u ON b.user_id = u.id 
                 WHERE b.id = ? AND b.user_id = ?";
$stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($stmt, "ii", $booking_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$booking_result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($booking_result);

if (!$booking) {
    redirectWithMessage('my-bookings.php', 'Booking not found.', 'error');
}

// Clear payment session data
$payment_success = $_SESSION['payment_success'];
$payment_message = $_SESSION['payment_message'];
$payment_type = $_SESSION['payment_type'];
$transaction_id = $_SESSION['transaction_id'] ?? 'N/A';

unset($_SESSION['payment_success'], $_SESSION['payment_message'], $_SESSION['payment_type'], $_SESSION['transaction_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .success-animation {
            animation: successPulse 2s ease-in-out infinite;
        }
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .payment-details {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 25px;
        }
        .status-badge {
            font-size: 1.1rem;
            padding: 10px 20px;
        }
        .next-steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
        }
        .download-receipt {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            transition: all 0.3s ease;
        }
        .download-receipt:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Success Header -->
                <div class="text-center mb-5">
                    <div class="success-animation mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h1 class="text-success mb-3">Payment Successful!</h1>
                    <p class="lead text-muted">Your booking has been confirmed and payment has been processed successfully.</p>
                </div>

                <!-- Payment Details Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Payment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="payment-details">
                                    <h6 class="text-muted mb-3">Transaction Information</h6>
                                    <div class="mb-2">
                                        <strong>Transaction ID:</strong> 
                                        <span class="text-primary"><?php echo htmlspecialchars($transaction_id); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Payment Method:</strong> 
                                        <span class="text-capitalize"><?php echo str_replace('_', ' ', htmlspecialchars($payment_type)); ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Payment Status:</strong> 
                                        <span class="badge bg-success status-badge">Completed</span>
                                    </div>
                                    <div class="mb-0">
                                        <strong>Payment Date:</strong> 
                                        <?php echo date('M d, Y H:i A'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-details">
                                    <h6 class="text-muted mb-3">Booking Information</h6>
                                    <div class="mb-2">
                                        <strong>Booking ID:</strong> 
                                        <span class="text-primary">#<?php echo $booking['id']; ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Vehicle:</strong> 
                                        <?php echo htmlspecialchars($booking['vehicle_name']); ?>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Amount Paid:</strong> 
                                        <span class="text-success fw-bold"><?php echo formatCurrency($booking['total_amount']); ?></span>
                                    </div>
                                    <div class="mb-0">
                                        <strong>Booking Date:</strong> 
                                        <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-arrow-right me-2"></i>What Happens Next?</h5>
                    </div>
                    <div class="card-body">
                        <div class="next-steps">
                            <?php if ($payment_type === 'cash'): ?>
                                <h6><i class="fas fa-info-circle me-2"></i>Cash Payment Instructions</h6>
                                <ul class="mb-0">
                                    <li>Your booking is now pending admin confirmation</li>
                                    <li>Please bring exact cash amount: <strong><?php echo formatCurrency($booking['total_amount']); ?></strong></li>
                                    <li>Payment will be collected when you pick up the vehicle</li>
                                    <li>You will receive a confirmation email once admin verifies your booking</li>
                                </ul>
                            <?php else: ?>
                                <h6><i class="fas fa-check-circle me-2"></i>Payment Confirmed</h6>
                                <ul class="mb-0">
                                    <li>Your payment has been processed and verified</li>
                                    <li>Your booking is now confirmed and active</li>
                                    <li>You will receive a confirmation email shortly</li>
                                    <li>Your vehicle will be ready for pickup on the scheduled date</li>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <?php if ($payment_type !== 'cash'): ?>
                        <a href="generate_invoice.php?booking_id=<?php echo $booking_id; ?>" class="btn download-receipt me-3">
                            <i class="fas fa-download me-2"></i>Download Receipt
                        </a>
                    <?php endif; ?>
                    
                    <a href="my-bookings.php" class="btn btn-outline-primary me-3">
                        <i class="fas fa-list me-2"></i>View My Bookings
                    </a>
                    
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>

                <!-- Additional Information -->
                <div class="mt-5">
                    <div class="alert alert-light border">
                        <h6><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                        <p class="mb-2">If you have any questions about your payment or booking, please contact our support team:</p>
                        <ul class="mb-0">
                            <li><strong>Email:</strong> support@mgtransport.com</li>
                            <li><strong>Phone:</strong> +675 1234 5678</li>
                            <li><strong>WhatsApp:</strong> +675 9876 5432</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to top
        window.scrollTo(0, 0);
        
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
            
            // Show success message if exists
            if ('<?php echo $payment_message; ?>') {
                console.log('Payment Message: <?php echo $payment_message; ?>');
            }
        });
    </script>
</body>
</html>

