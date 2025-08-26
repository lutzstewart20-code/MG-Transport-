<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirectWithMessage('login.php', 'Please login to access this page.', 'warning');
}

$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

// Get payment request details
$payment_query = "SELECT pr.*, b.total_amount, v.name as vehicle_name, u.first_name, u.last_name, u.email 
                 FROM payment_requests pr 
                 JOIN bookings b ON pr.booking_id = b.id 
                 JOIN vehicles v ON b.vehicle_id = v.id 
                 JOIN users u ON b.user_id = u.id 
                 WHERE pr.id = ? AND b.user_id = ?";
$stmt = mysqli_prepare($conn, $payment_query);
mysqli_stmt_bind_param($stmt, "ii", $request_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$payment_result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($payment_result);

if (!$payment) {
    redirectWithMessage('my-bookings.php', 'Payment request not found.', 'error');
}

// Handle payment completion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = 'BSP' . time() . rand(1000, 9999);
    $payment_status = $_POST['payment_status']; // 'success' or 'failed'
    
    if ($payment_status === 'success') {
        // Update payment request status
        $update_payment = "UPDATE payment_requests SET status = 'completed', 
                          transaction_id = ?, completed_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_payment);
        mysqli_stmt_bind_param($update_stmt, "si", $transaction_id, $request_id);
        mysqli_stmt_execute($update_stmt);
        
        // Update booking status
        $update_booking = "UPDATE bookings SET status = 'confirmed', payment_status = 'paid', 
                          payment_method = 'bsp_online' WHERE id = ?";
        $booking_stmt = mysqli_prepare($conn, $update_booking);
        mysqli_stmt_bind_param($booking_stmt, "i", $payment['booking_id']);
        mysqli_stmt_execute($booking_stmt);
        
        // Create payment receipt record
        $receipt_query = "INSERT INTO payment_receipts (booking_id, transaction_id, amount_paid, 
                         payment_date, bank_name, account_number, reference_number, receipt_file, 
                         notes, status, created_at) 
                         VALUES (?, ?, ?, CURDATE(), 'BSP Online Banking', 'N/A', ?, 'bsp_online', 
                         'BSP Online Banking Payment', 'verified', NOW())";
        $receipt_stmt = mysqli_prepare($conn, $receipt_query);
        $reference = 'MG_TRANSPORT_' . $payment['booking_id'];
        mysqli_stmt_bind_param($receipt_stmt, "isds", $payment['booking_id'], $transaction_id, 
                              $payment['total_amount'], $reference);
        mysqli_stmt_execute($receipt_stmt);
        
        // Send confirmation email to customer
        $subject = "Payment Successful - Booking Confirmed - MG Transport Services";
        $message = "
        <h2>Payment Successful - Booking Confirmed</h2>
        <p>Dear {$payment['first_name']} {$payment['last_name']},</p>
        <p>Your BSP online banking payment has been processed successfully!</p>
        <h3>Payment Details:</h3>
        <ul>
            <li><strong>Transaction ID:</strong> $transaction_id</li>
            <li><strong>Amount Paid:</strong> " . formatCurrency($payment['total_amount']) . "</li>
            <li><strong>Payment Method:</strong> BSP Online Banking</li>
            <li><strong>Vehicle:</strong> {$payment['vehicle_name']}</li>
            <li><strong>Booking ID:</strong> #{$payment['booking_id']}</li>
        </ul>
        <p>Your booking is now confirmed and your vehicle will be ready for pickup on the scheduled date.</p>
        <p>Thank you for choosing MG Transport Services!</p>";
        
        sendEmail($payment['email'], $subject, $message);
        
        // Send notification to admin
        $admin_notification = "BSP payment completed for booking #{$payment['booking_id']}. 
                              Transaction ID: $transaction_id, Amount: " . formatCurrency($payment['total_amount']);
        
        $admin_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
        $admin_result = mysqli_query($conn, $admin_query);
        
        while ($admin = mysqli_fetch_assoc($admin_result)) {
            createNotification($admin['id'], 'Payment Completed', $admin_notification, 'success', $conn);
        }
        
        // Send WhatsApp/Email notification to admin (simulated)
        $admin_email_query = "SELECT email FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1";
        $admin_email_result = mysqli_query($conn, $admin_email_query);
        $admin_email = mysqli_fetch_assoc($admin_email_result);
        
        if ($admin_email) {
            $admin_subject = "Payment Completed - Booking #{$payment['booking_id']}";
            $admin_message = "
            <h2>BSP Payment Completed</h2>
            <p>A customer has successfully completed payment for booking #{$payment['booking_id']}.</p>
            <h3>Payment Details:</h3>
            <ul>
                <li><strong>Transaction ID:</strong> $transaction_id</li>
                <li><strong>Amount:</strong> " . formatCurrency($payment['total_amount']) . "</li>
                <li><strong>Payment Method:</strong> BSP Online Banking</li>
                <li><strong>Customer:</strong> {$payment['first_name']} {$payment['last_name']}</li>
                <li><strong>Vehicle:</strong> {$payment['vehicle_name']}</li>
            </ul>
            <p><a href='http://localhost/MG%20Transport/admin/bookings.php'>View Booking Details</a></p>";
            
            sendEmail($admin_email['email'], $admin_subject, $admin_message);
        }
        
        // Set success message and redirect
        $_SESSION['payment_success'] = true;
        $_SESSION['payment_message'] = "BSP payment completed successfully! Your booking is now confirmed.";
        $_SESSION['payment_type'] = 'success';
        
        redirectWithMessage('my-bookings.php', 'Payment completed successfully! Your booking is now confirmed.', 'success');
    } else {
        // Payment failed
        $update_payment = "UPDATE payment_requests SET status = 'failed', completed_at = NOW() WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_payment);
        mysqli_stmt_bind_param($update_stmt, "i", $request_id);
        mysqli_stmt_execute($update_stmt);
        
        redirectWithMessage('payment_gateway.php?booking_id=' . $payment['booking_id'], 'Payment failed. Please try again.', 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSP Online Banking - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .bsp-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 0;
        }
        .bsp-logo {
            font-size: 24px;
            font-weight: bold;
        }
        .payment-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
        }
        .security-badge {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- BSP Header -->
    <div class="bsp-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="bsp-logo">
                        <img src="assets/images/vehicles/BSPlogo.png" alt="Bank South Pacific" height="40" class="me-2">
                        <span class="align-middle">Bank South Pacific</span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <span class="security-badge">
                        <i class="fas fa-shield-alt me-1"></i>Secure Payment
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>BSP Online Banking Payment</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Payment Details</h6>
                            <p class="mb-1"><strong>Merchant:</strong> MG Transport Services</p>
                            <p class="mb-1"><strong>Amount:</strong> <?php echo formatCurrency($payment['total_amount']); ?></p>
                            <p class="mb-1"><strong>Reference:</strong> MG_TRANSPORT_<?php echo $payment['booking_id']; ?></p>
                            <p class="mb-0"><strong>Description:</strong> Vehicle Booking - <?php echo htmlspecialchars($payment['vehicle_name']); ?></p>
                        </div>
                        
                        <div class="payment-form">
                            <h5 class="mb-4">Enter Your BSP Online Banking Details</h5>
                            
                            <form method="POST" action="" id="bspPaymentForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">BSP Username</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               placeholder="Enter your BSP username" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">BSP Password</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter your BSP password" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number" 
                                               placeholder="Enter account number" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="otp" class="form-label">OTP Code</label>
                                        <input type="text" class="form-control" id="otp" name="otp" 
                                               placeholder="Enter OTP from SMS" required>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Demo Mode</h6>
                                    <p class="mb-0">This is a simulation of BSP online banking. For demo purposes, you can use any values in the fields above.</p>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" name="payment_status" value="success" class="btn btn-success btn-lg me-2">
                                        <i class="fas fa-check me-2"></i>Complete Payment
                                    </button>
                                    <button type="submit" name="payment_status" value="failed" class="btn btn-danger btn-lg">
                                        <i class="fas fa-times me-2"></i>Simulate Failure
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="alert alert-secondary">
                            <h6><i class="fas fa-shield-alt me-2"></i>Security Information</h6>
                            <ul class="mb-0">
                                <li>All transactions are encrypted using SSL/TLS</li>
                                <li>Your banking credentials are not stored</li>
                                <li>Payment is processed securely through BSP</li>
                                <li>You will receive a confirmation email</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill demo values for testing
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').value = 'demo_user';
            document.getElementById('password').value = 'demo_pass';
            document.getElementById('account_number').value = '1234567890';
            document.getElementById('otp').value = '123456';
        });
    </script>
</body>
</html> 