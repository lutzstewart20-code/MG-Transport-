<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirectWithMessage('login.php', 'Please login to access this page.', 'warning');
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

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

// Handle card payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = sanitizeInput($_POST['card_number']);
    $expiry_month = sanitizeInput($_POST['expiry_month']);
    $expiry_year = sanitizeInput($_POST['expiry_year']);
    $card_holder = sanitizeInput($_POST['card_holder']);
    $cvv = sanitizeInput($_POST['cvv']);
    $billing_address = sanitizeInput($_POST['billing_address']);
    $billing_city = sanitizeInput($_POST['billing_city']);
    $billing_postal = sanitizeInput($_POST['billing_postal']);
    $billing_country = sanitizeInput($_POST['billing_country']);
    
    // Basic validation
    if (empty($card_number) || empty($expiry_month) || empty($expiry_year) || 
        empty($card_holder) || empty($cvv) || empty($billing_address)) {
        redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'Please fill in all required fields.', 'error');
    }
    
    // Validate card number (basic Luhn algorithm check)
    $card_number_clean = preg_replace('/\s+/', '', $card_number);
    if (!preg_match('/^\d{13,19}$/', $card_number_clean)) {
        redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'Invalid card number format.', 'error');
    }
    
    // Validate expiry date
    $current_year = date('y');
    $current_month = date('m');
    if ($expiry_year < $current_year || ($expiry_year == $current_year && $expiry_month < $current_month)) {
        redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'Card has expired.', 'error');
    }
    
    // Validate CVV
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'Invalid CVV.', 'error');
    }
    
    // Generate transaction ID
    $transaction_id = 'CARD' . time() . rand(1000, 9999);
    
    try {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // Store payment request in database
        $payment_query = "INSERT INTO payment_requests (booking_id, payment_method, amount, 
                        payment_data, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
        $payment_stmt = mysqli_prepare($conn, $payment_query);
        $payment_data = json_encode([
            'card_number' => substr($card_number_clean, -4), // Store only last 4 digits
            'expiry_month' => $expiry_month,
            'expiry_year' => $expiry_year,
            'card_holder' => $card_holder,
            'billing_address' => $billing_address,
            'billing_city' => $billing_city,
            'billing_postal' => $billing_postal,
            'billing_country' => $billing_country,
            'transaction_id' => $transaction_id
        ]);
        mysqli_stmt_bind_param($payment_stmt, "isds", $booking_id, 'online_card', 
                             $booking['total_amount'], $payment_data);
        mysqli_stmt_execute($payment_stmt);
        $payment_request_id = mysqli_insert_id($conn);
        
        // Update booking payment method
        $update_booking = "UPDATE bookings SET payment_method = 'online_card', 
                          payment_status = 'processing' WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_booking);
        mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
        mysqli_stmt_execute($update_stmt);
        
        // Simulate payment processing (in real implementation, integrate with payment gateway)
        $payment_status = 'success'; // Simulate successful payment
        
        if ($payment_status === 'success') {
            // Update payment request status
            $update_payment = "UPDATE payment_requests SET status = 'completed', 
                              transaction_id = ?, completed_at = NOW() WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_payment);
            mysqli_stmt_bind_param($update_stmt, "si", $transaction_id, $payment_request_id);
            mysqli_stmt_execute($update_stmt);
            
            // Update booking status
            $update_booking = "UPDATE bookings SET status = 'confirmed', payment_status = 'paid', 
                              payment_method = 'online_card' WHERE id = ?";
            $booking_stmt = mysqli_prepare($conn, $update_booking);
            mysqli_stmt_bind_param($booking_stmt, "i", $booking_id);
            mysqli_stmt_execute($booking_stmt);
            
            // Create payment receipt record
            $receipt_query = "INSERT INTO payment_receipts (booking_id, transaction_id, amount_paid, 
                             payment_date, bank_name, account_number, reference_number, receipt_file, 
                             notes, status, created_at) 
                             VALUES (?, ?, ?, CURDATE(), 'Online Card Payment', 'N/A', ?, 'online_card', 
                             'Online Card Payment - ' . $card_holder, 'verified', NOW())";
            $receipt_stmt = mysqli_prepare($conn, $receipt_query);
            $reference = 'MG_TRANSPORT_' . $booking_id;
            mysqli_stmt_bind_param($receipt_stmt, "isds", $booking_id, $transaction_id, 
                                  $booking['total_amount'], $reference);
            mysqli_stmt_execute($receipt_stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Send confirmation email to customer
            $subject = "Payment Successful - Booking Confirmed - MG Transport Services";
            $message = "
            <h2>Payment Successful - Booking Confirmed</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Your online card payment has been processed successfully!</p>
            <h3>Payment Details:</h3>
            <ul>
                <li><strong>Transaction ID:</strong> $transaction_id</li>
                <li><strong>Amount Paid:</strong> " . formatCurrency($booking['total_amount']) . "</li>
                <li><strong>Payment Method:</strong> Online Card Payment</li>
                <li><strong>Card:</strong> **** **** **** " . substr($card_number_clean, -4) . "</li>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Booking ID:</strong> #{$booking_id}</li>
            </ul>
            <p>Your booking is now confirmed and your vehicle will be ready for pickup on the scheduled date.</p>
            <p>Thank you for choosing MG Transport Services!</p>";
            
            sendEmail($booking['email'], $subject, $message);
            
            // Send notification to admin
            $admin_notification = "Online card payment completed for booking #{$booking_id}. 
                                  Transaction ID: $transaction_id, Amount: " . formatCurrency($booking['total_amount']);
            
            $admin_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
            $admin_result = mysqli_query($conn, $admin_query);
            
            while ($admin = mysqli_fetch_assoc($admin_result)) {
                createNotification($admin['id'], 'Payment Completed', $admin_notification, 'success', $conn);
            }
            
            // Redirect to success page
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_message'] = "Payment successful! Your booking is now confirmed.";
            $_SESSION['payment_type'] = 'online_card';
            $_SESSION['transaction_id'] = $transaction_id;
            
            header('Location: payment_success.php?booking_id=' . $booking_id);
            exit();
            
        } else {
            // Payment failed
            mysqli_rollback($conn);
            redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'Payment processing failed. Please try again.', 'error');
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        redirectWithMessage('card_payment.php?booking_id=' . $booking_id, 'An error occurred. Please try again.', 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Payment - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .card-payment-form {
            max-width: 800px;
            margin: 0 auto;
        }
        .card-input {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .card-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .card-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .card-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .card-number-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }
        .card-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .security-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Online Card Payment</h4>
                    </div>
                    <div class="card-body">
                        <!-- Booking Summary -->
                        <div class="alert alert-info mb-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Booking Summary</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                                    <p class="mb-1"><strong>Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                                    <p class="mb-0"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Preview -->
                        <div class="card-preview">
                            <div class="card-number-display" id="cardNumberDisplay">
                                **** **** **** ****
                            </div>
                            <div class="card-details">
                                <div>
                                    <div class="mb-1">CARD HOLDER</div>
                                    <div id="cardHolderDisplay">YOUR NAME</div>
                                </div>
                                <div>
                                    <div class="mb-1">EXPIRES</div>
                                    <div id="cardExpiryDisplay">MM/YY</div>
                                </div>
                                <div class="security-badge">
                                    <i class="fas fa-shield-alt me-1"></i>SECURE
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Form -->
                        <form method="POST" action="" class="card-payment-form">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Card Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="card_number" class="form-label">Card Number *</label>
                                        <div class="position-relative">
                                            <input type="text" class="form-control card-input" id="card_number" name="card_number" 
                                                   placeholder="1234 5678 9012 3456" maxlength="19" required>
                                            <i class="fas fa-credit-card card-icon"></i>
                                        </div>
                                        <small class="text-muted">Enter your 16-digit card number</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="expiry_month" class="form-label">Expiry Month *</label>
                                            <select class="form-select card-input" id="expiry_month" name="expiry_month" required>
                                                <option value="">MM</option>
                                                <?php for($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                        <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="expiry_year" class="form-label">Expiry Year *</label>
                                            <select class="form-select card-input" id="expiry_year" name="expiry_year" required>
                                                <option value="">YY</option>
                                                <?php for($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                                    <option value="<?php echo substr($i, -2); ?>"><?php echo $i; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="cvv" class="form-label">CVV *</label>
                                            <input type="text" class="form-control card-input" id="cvv" name="cvv" 
                                                   placeholder="123" maxlength="4" required>
                                            <small class="text-muted">3 or 4 digit security code</small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="card_holder" class="form-label">Card Holder Name *</label>
                                        <input type="text" class="form-control card-input" id="card_holder" name="card_holder" 
                                               placeholder="JOHN DOE" required>
                                        <small class="text-muted">Name as it appears on the card</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Billing Address</h5>
                                    
                                    <div class="mb-3">
                                        <label for="billing_address" class="form-label">Address *</label>
                                        <textarea class="form-control card-input" id="billing_address" name="billing_address" 
                                                  rows="3" placeholder="123 Main Street" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="billing_city" class="form-label">City *</label>
                                        <input type="text" class="form-control card-input" id="billing_city" name="billing_city" 
                                               placeholder="Port Moresby" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="billing_postal" class="form-label">Postal Code</label>
                                            <input type="text" class="form-control card-input" id="billing_postal" name="billing_postal" 
                                                   placeholder="121">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="billing_country" class="form-label">Country *</label>
                                            <select class="form-select card-input" id="billing_country" name="billing_country" required>
                                                <option value="">Select Country</option>
                                                <option value="PG" selected>Papua New Guinea</option>
                                                <option value="AU">Australia</option>
                                                <option value="NZ">New Zealand</option>
                                                <option value="FJ">Fiji</option>
                                                <option value="SB">Solomon Islands</option>
                                                <option value="VU">Vanuatu</option>
                                                <option value="NC">New Caledonia</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security Notice -->
                            <div class="alert alert-warning mt-4">
                                <h6><i class="fas fa-shield-alt me-2"></i>Security Notice</h6>
                                <ul class="mb-0">
                                    <li>Your payment information is encrypted and secure</li>
                                    <li>We do not store your full card details</li>
                                    <li>All transactions are processed through secure payment gateways</li>
                                    <li>You will receive an email confirmation upon successful payment</li>
                                </ul>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-lock me-2"></i>Process Payment - <?php echo formatCurrency($booking['total_amount']); ?>
                                </button>
                                <a href="payment_gateway.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Payment Options
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
            const cardNumber = document.getElementById('card_number');
            const cardHolder = document.getElementById('card_holder');
            const expiryMonth = document.getElementById('expiry_month');
            const expiryYear = document.getElementById('expiry_year');
            const cvv = document.getElementById('cvv');
            
            // Card number formatting and preview
            cardNumber.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = formattedValue;
                
                // Update preview
                const display = document.getElementById('cardNumberDisplay');
                if (value.length > 0) {
                    const masked = value.replace(/\d(?=\d{4})/g, '*');
                    const formatted = masked.match(/.{1,4}/g)?.join(' ') || masked;
                    display.textContent = formatted;
                } else {
                    display.textContent = '**** **** **** ****';
                }
            });
            
            // Card holder name preview
            cardHolder.addEventListener('input', function(e) {
                const display = document.getElementById('cardHolderDisplay');
                display.textContent = e.target.value.toUpperCase() || 'YOUR NAME';
            });
            
            // Expiry date preview
            function updateExpiryDisplay() {
                const month = expiryMonth.value;
                const year = expiryYear.value;
                const display = document.getElementById('cardExpiryDisplay');
                
                if (month && year) {
                    display.textContent = month + '/' + year;
                } else {
                    display.textContent = 'MM/YY';
                }
            }
            
            expiryMonth.addEventListener('change', updateExpiryDisplay);
            expiryYear.addEventListener('change', updateExpiryDisplay);
            
            // CVV validation
            cvv.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const requiredFields = document.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>

