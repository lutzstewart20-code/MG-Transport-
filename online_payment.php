<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if online booking ID is set
if (!isset($_SESSION['online_booking_id'])) {
    header('Location: my-bookings.php');
    exit();
}

$booking_id = (int)$_SESSION['online_booking_id'];

// Get booking details
$booking_query = "SELECT b.*, v.name as vehicle_name, v.registration_number, u.first_name, u.last_name, u.email 
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
    header('Location: my-bookings.php');
    exit();
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = trim($_POST['card_number']);
    $card_holder = trim($_POST['card_holder']);
    $expiry_month = $_POST['expiry_month'];
    $expiry_year = $_POST['expiry_year'];
    $cvv = trim($_POST['cvv']);
    $billing_address = trim($_POST['billing_address']);
    
    // Validation
    if (empty($card_number) || empty($card_holder) || empty($expiry_month) || empty($expiry_year) || empty($cvv) || empty($billing_address)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (strlen($card_number) < 13 || strlen($card_number) > 19) {
        $error_message = 'Please enter a valid card number.';
    } elseif (strlen($cvv) < 3 || strlen($cvv) > 4) {
        $error_message = 'Please enter a valid CVV.';
    } else {
        // Simulate payment processing (in real implementation, this would connect to a payment gateway)
        $payment_successful = true; // Simulate successful payment
        
        if ($payment_successful) {
            // Generate transaction ID
            $transaction_id = 'ONL' . time() . rand(1000, 9999);
            
            // Insert payment record
            $payment_query = "INSERT INTO payments (booking_id, payment_method, amount, reference_number, 
                            payment_date, receipt_file, status, created_at) 
                            VALUES (?, 'online_payment', ?, ?, NOW(), ?, 'completed', NOW())";
            $payment_stmt = mysqli_prepare($conn, $payment_query);
            $receipt_file = 'online_payment_' . $transaction_id;
            mysqli_stmt_bind_param($payment_stmt, "idss", $booking_id, $booking['total_amount'], $transaction_id, $receipt_file);
            
            if (mysqli_stmt_execute($payment_stmt)) {
                // Update booking status
                $update_query = "UPDATE bookings SET payment_status = 'paid' WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
                mysqli_stmt_execute($update_stmt);
                
                $success_message = 'Payment processed successfully! Your booking is now confirmed.';
                unset($_SESSION['online_booking_id']); // Clear session
            } else {
                $error_message = 'Error processing payment. Please try again.';
            }
        } else {
            $error_message = 'Payment was declined. Please check your card details and try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Payment - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-form {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .form-header {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .form-body {
            padding: 2rem;
        }
        .booking-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card-input {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .card-input:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        .card-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .btn-submit {
            background: linear-gradient(135deg, #17a2b8, #138496);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            transform: translateY(-2px);
        }
        .security-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="payment-form">
                    <!-- Form Header -->
                    <div class="form-header">
                        <h1 class="mb-2">
                            <i class="fas fa-globe me-3"></i>
                            Online Payment
                        </h1>
                        <p class="mb-0">Complete your payment securely online</p>
                    </div>

                    <!-- Form Body -->
                    <div class="form-body">
                        <!-- Success/Error Messages -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Summary -->
                        <div class="booking-summary">
                            <h5><i class="fas fa-info-circle me-2"></i>Booking Summary</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                                    <p class="mb-1"><strong>Registration:</strong> <?php echo htmlspecialchars($booking['registration_number']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Amount:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
                                    <p class="mb-0"><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Security Notice -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-shield-alt me-2"></i>Secure Payment</h6>
                            <p class="mb-2">Your payment information is encrypted and secure. We use industry-standard SSL encryption to protect your data.</p>
                            <div class="d-flex align-items-center gap-3">
                                <span class="security-badge">
                                    <i class="fas fa-lock me-1"></i>SSL Encrypted
                                </span>
                                <span class="security-badge">
                                    <i class="fas fa-shield-alt me-1"></i>PCI Compliant
                                </span>
                                <span class="security-badge">
                                    <i class="fas fa-check-circle me-1"></i>Secure Gateway
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="" id="paymentForm">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="card_holder" class="form-label">Cardholder Name *</label>
                                    <input type="text" class="form-control card-input" id="card_holder" name="card_holder" 
                                           value="<?php echo htmlspecialchars($_POST['card_holder'] ?? ''); ?>" 
                                           placeholder="Name as it appears on card" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="card_number" class="form-label">Card Number *</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control card-input" id="card_number" name="card_number" 
                                               value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>" 
                                               placeholder="1234 5678 9012 3456" maxlength="19" required>
                                        <i class="fas fa-credit-card card-icon"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="expiry_month" class="form-label">Expiry Month *</label>
                                    <select class="form-control card-input" id="expiry_month" name="expiry_month" required>
                                        <option value="">Month</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>" 
                                                    <?php echo ($_POST['expiry_month'] ?? '') == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="expiry_year" class="form-label">Expiry Year *</label>
                                    <select class="form-control card-input" id="expiry_year" name="expiry_year" required>
                                        <option value="">Year</option>
                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                    <?php echo ($_POST['expiry_year'] ?? '') == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cvv" class="form-label">CVV *</label>
                                    <input type="text" class="form-control card-input" id="cvv" name="cvv" 
                                           value="<?php echo htmlspecialchars($_POST['cvv'] ?? ''); ?>" 
                                           placeholder="123" maxlength="4" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="billing_address" class="form-label">Billing Address *</label>
                                    <textarea class="form-control card-input" id="billing_address" name="billing_address" 
                                              rows="3" placeholder="Enter your billing address" required><?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-info btn-submit me-3">
                                    <i class="fas fa-lock me-2"></i>Pay Securely
                                </button>
                                <a href="my-bookings.php" class="btn btn-outline-secondary">
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
        // Card number formatting
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue;
        });
        
        // CVV validation (numbers only)
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
        
        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const cvv = document.getElementById('cvv').value;
            
            if (cardNumber.length < 13 || cardNumber.length > 19) {
                e.preventDefault();
                alert('Please enter a valid card number.');
                return false;
            }
            
            if (cvv.length < 3 || cvv.length > 4) {
                e.preventDefault();
                alert('Please enter a valid CVV.');
                return false;
            }
            
            // Show processing state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
