<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['booking_id']) || !isset($_POST['payment_method'])) {
        $_SESSION['payment_error'] = 'Missing required payment information. Please try again.';
        header('Location: payment_gateway.php?booking_id=' . ($_GET['booking_id'] ?? ''));
        exit();
    }
    
    $booking_id = (int)$_POST['booking_id'];
    $payment_method = sanitizeInput($_POST['payment_method']);
    
    $valid_methods = ['bank_transfer', 'sms_payment', 'online_payment'];
    if (!in_array($payment_method, $valid_methods)) {
        $_SESSION['payment_error'] = 'Invalid payment method selected. Please try again.';
        header('Location: payment_gateway.php?booking_id=' . $booking_id);
        exit();
    }
    
    // Get booking details
    $booking_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name, u.email 
                     FROM bookings b 
                     JOIN vehicles v ON b.vehicle_id = v.id 
                     JOIN users u ON b.user_id = u.id 
                     WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $booking_query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $booking_result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($booking_result);
    
    if (!$booking) {
        $_SESSION['payment_error'] = 'Booking not found.';
        header('Location: my-bookings.php');
        exit();
    }
    
    try {
        // Update booking with payment method
        $update_query = "UPDATE bookings SET payment_method = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $payment_method, $booking_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Set success message and redirect based on payment method
            $_SESSION['payment_success'] = true;
            
            switch ($payment_method) {
                case 'bank_transfer':
                    $_SESSION['payment_message'] = "Bank transfer payment method selected successfully! Redirecting to bank transfer details...";
                    $_SESSION['bank_transfer_booking_id'] = $booking_id;
                    header('Location: bank_transfer_confirmation.php?booking_id=' . $booking_id);
                    exit();
                    
                case 'sms_payment':
                    $_SESSION['payment_message'] = "SMS payment method selected successfully! Redirecting to SMS payment...";
                    $_SESSION['sms_booking_id'] = $booking_id;
                    header('Location: sms_payment.php?booking_id=' . $booking_id);
                    exit();
                    
                case 'online_payment':
                    $_SESSION['payment_message'] = "Online payment method selected successfully! Redirecting to online payment...";
                    $_SESSION['online_booking_id'] = $booking_id;
                    header('Location: online_payment.php?booking_id=' . $booking_id);
                    exit();
                    
                default:
                    $_SESSION['payment_error'] = 'Invalid payment method.';
                    header('Location: payment_gateway.php?booking_id=' . $booking_id);
                    exit();
            }
        } else {
            $_SESSION['payment_error'] = 'Failed to update booking. Please try again.';
            header('Location: payment_gateway.php?booking_id=' . $booking_id);
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['payment_error'] = 'An error occurred. Please try again.';
        header('Location: payment_gateway.php?booking_id=' . $booking_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Options - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-option {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid #e9ecef;
        }
        .payment-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .payment-option.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .payment-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Popup Notification Styles */
        .popup-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
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
        
        .popup-notification.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .popup-notification.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .popup-notification.info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Choose Payment Method</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
                        if (!$booking_id) {
                            redirectWithMessage('my-bookings.php', 'Invalid booking ID.', 'error');
                        }
                        
                        $booking_query = "SELECT b.*, v.name as vehicle_name, u.first_name, u.last_name 
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
                        ?>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Booking Summary</h6>
                            <p class="mb-1"><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                            <p class="mb-1"><strong>Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
                            <p class="mb-0"><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                        </div>
                        
                        <!-- Success/Error Messages -->
                        <?php if (isset($_SESSION['payment_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($_SESSION['payment_message'] ?? 'Payment method selected successfully!'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['payment_success'], $_SESSION['payment_message']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['payment_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($_SESSION['payment_error'] ?? 'An error occurred. Please try again.'); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['payment_error']); ?>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="paymentForm">
                            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="">
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="mb-3"><i class="fas fa-list me-2"></i>Select Payment Method</h5>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="payment-option card h-100" data-method="bank_transfer">
                                        <div class="card-body text-center">
                                            <i class="fas fa-university card-icon text-primary"></i>
                                            <h5>Bank Transfer</h5>
                                            <p class="payment-description">Pay via bank transfer to our account</p>
                                            <div class="mt-3">
                                                <span class="badge bg-primary">Bank Transfer</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="payment-option card h-100" data-method="sms_payment">
                                        <div class="card-body text-center">
                                            <i class="fas fa-mobile-alt card-icon text-success"></i>
                                            <h5>SMS Payment</h5>
                                            <p class="payment-description">Pay via SMS banking service</p>
                                            <div class="mt-3">
                                                <span class="badge bg-success">SMS Banking</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="payment-option card h-100" data-method="online_payment">
                                        <div class="card-body text-center">
                                            <i class="fas fa-globe card-icon text-info"></i>
                                            <h5>Online Payment</h5>
                                            <p class="payment-description">Pay securely online with cards</p>
                                            <div class="mt-3">
                                                <span class="badge bg-info">Online Cards</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" style="display: none;">
                                    <i class="fas fa-arrow-right me-2"></i>Continue to Payment
                                </button>
                                <a href="my-bookings.php" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Popup Notification Container -->
    <div id="popupContainer"></div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Popup notification function
        function showPopup(message, type = 'info', duration = 5000) {
            const popupContainer = document.getElementById('popupContainer');
            
            // Create popup element
            const popup = document.createElement('div');
            popup.className = `popup-notification ${type} alert alert-dismissible fade show`;
            popup.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            // Add to container
            popupContainer.appendChild(popup);
            
            // Auto-remove after duration
            setTimeout(() => {
                if (popup.parentElement) {
                    popup.remove();
                }
            }, duration);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const submitBtn = document.getElementById('submitBtn');
            const selectedPaymentMethod = document.getElementById('selectedPaymentMethod');
            
            // Show success/error messages as popups if they exist
            <?php if (isset($_SESSION['payment_success'])): ?>
                showPopup('<?php echo addslashes($_SESSION['payment_message'] ?? 'Payment method selected successfully!'); ?>', 'success');
            <?php endif; ?>
            
            <?php if (isset($_SESSION['payment_error'])): ?>
                showPopup('<?php echo addslashes($_SESSION['payment_error'] ?? 'An error occurred. Please try again.'); ?>', 'error');
            <?php endif; ?>
            
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selection from all options
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Select current option
                    this.classList.add('selected');
                    
                    // Set the payment method
                    const method = this.dataset.method;
                    selectedPaymentMethod.value = method;
                    
                    // Show submit button
                    submitBtn.style.display = 'inline-block';
                    
                    // Show info popup
                    const methodNames = {
                        'bank_transfer': 'Bank Transfer',
                        'sms_payment': 'SMS Payment',
                        'online_payment': 'Online Payment'
                    };
                    showPopup(`Selected: ${methodNames[method]}`, 'info', 3000);
                });
            });
            
            // Form validation and submission
            document.getElementById('paymentForm').addEventListener('submit', function(e) {
                if (!selectedPaymentMethod.value) {
                    e.preventDefault();
                    showPopup('Please select a payment method first.', 'error');
                    return;
                }
                
                // Show processing popup
                const methodNames = {
                    'bank_transfer': 'Bank Transfer',
                    'sms_payment': 'SMS Payment',
                    'online_payment': 'Online Payment'
                };
                showPopup(`Processing ${methodNames[selectedPaymentMethod.value]}...`, 'info', 2000);
                
                // Disable submit button to prevent double submission
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            });
        });
    </script>
</body>
</html>
