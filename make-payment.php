<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectWithMessage('login.php', 'Please login to make payment', 'warning');
}

$user_id = $_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    redirectWithMessage('my-bookings.php', 'Invalid booking ID', 'error');
}

// Get confirmed booking details
$booking_query = "SELECT b.*, v.name as vehicle_name, v.model as vehicle_model, v.image_url, v.registration_number
                  FROM bookings b 
                  JOIN vehicles v ON b.vehicle_id = v.id 
                  WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'";
$booking_stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($booking_stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($booking_stmt);
$booking_result = mysqli_stmt_get_result($booking_stmt);
$confirmed_booking = mysqli_fetch_assoc($booking_result);

if (!$confirmed_booking) {
    redirectWithMessage('my-bookings.php', 'Booking not found or not confirmed', 'error');
}

// Check if payment is already completed
if ($confirmed_booking['payment_status'] === 'paid') {
    redirectWithMessage('my-bookings.php', 'Payment already completed for this booking', 'info');
}

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitizeInput($_POST['payment_method']);
    
    if (in_array($payment_method, ['bank_transfer', 'sms_payment', 'online_payment', 'cash'])) {
        // Store payment method in session for vehicle agreement form
        $_SESSION['selected_payment_method'] = $payment_method;
        $_SESSION['payment_booking_id'] = $booking_id;
        
        // Redirect to vehicle agreement form with payment integration
        header('Location: vehicle-agreement.php?booking_id=' . $booking_id . '&payment_integration=1');
        exit();
    } else {
        $error_message = 'Please select a valid payment method.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-option {
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1rem;
        }
        
        .payment-option:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .payment-option.selected {
            border-color: #007bff;
            background-color: #f0f8ff;
        }
        
        .payment-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .booking-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h2 class="mb-2">
                            <i class="fas fa-credit-card me-3"></i>
                            Make Payment for Confirmed Booking
                        </h2>
                        <p class="mb-0">Your booking has been confirmed! Please select a payment method to proceed.</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Success/Error Messages -->
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Summary -->
                        <div class="booking-summary mb-4">
                            <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Booking Details</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Vehicle:</strong> <?php echo htmlspecialchars($confirmed_booking['vehicle_name']); ?></p>
                                    <p class="mb-1"><strong>Model:</strong> <?php echo htmlspecialchars($confirmed_booking['vehicle_model']); ?></p>
                                    <p class="mb-1"><strong>Registration:</strong> <?php echo htmlspecialchars($confirmed_booking['registration_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($confirmed_booking['start_date'])); ?></p>
                                    <p class="mb-1"><strong>End Date:</strong> <?php echo date('M j, Y', strtotime($confirmed_booking['end_date'])); ?></p>
                                    <p class="mb-1"><strong>Total Amount:</strong> <?php echo formatCurrency($confirmed_booking['total_amount']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method Selection -->
                        <form method="POST" action="" id="paymentForm">
                            <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Select Payment Method</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="payment-option" data-method="bank_transfer">
                                        <div class="text-center">
                                            <div class="payment-icon bg-primary">
                                                <i class="fas fa-university"></i>
                                            </div>
                                            <h6 class="mb-2">Bank Transfer</h6>
                                            <p class="text-muted small mb-0">Transfer to our bank account</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="payment-option" data-method="sms_payment">
                                        <div class="text-center">
                                            <div class="payment-icon bg-success">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <h6 class="mb-2">SMS Payment</h6>
                                            <p class="text-muted small mb-0">Send SMS with payment details</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="payment-option" data-method="online_payment">
                                        <div class="text-center">
                                            <div class="payment-icon bg-info">
                                                <i class="fas fa-globe"></i>
                                            </div>
                                            <h6 class="mb-2">Online Payment</h6>
                                            <p class="text-muted small mb-0">Pay with card online</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="payment-option" data-method="cash">
                                        <div class="text-center">
                                            <div class="payment-icon bg-warning">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <h6 class="mb-2">Cash Payment</h6>
                                            <p class="text-muted small mb-0">Pay cash when collecting vehicle</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="payment_method" id="selectedPaymentMethod" required>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="proceedBtn" disabled>
                                    <i class="fas fa-arrow-right me-2"></i>Proceed with Payment
                                </button>
                                <a href="my-bookings.php" class="btn btn-outline-secondary btn-lg ms-3">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const selectedPaymentMethod = document.getElementById('selectedPaymentMethod');
            const proceedBtn = document.getElementById('proceedBtn');
            
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Set the selected payment method
                    const method = this.dataset.method;
                    selectedPaymentMethod.value = method;
                    
                    // Enable proceed button
                    proceedBtn.disabled = false;
                    
                    // Update button text based on method
                    if (method === 'cash') {
                        proceedBtn.innerHTML = '<i class="fas fa-check me-2"></i>Confirm Cash Payment';
                    } else {
                        proceedBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Proceed with Payment';
                    }
                });
            });
        });
    </script>
</body>
</html>
