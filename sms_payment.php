<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if SMS booking ID is set
if (!isset($_SESSION['sms_booking_id'])) {
    header('Location: my-bookings.php');
    exit();
}

$booking_id = (int)$_SESSION['sms_booking_id'];

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
    $sms_reference = trim($_POST['sms_reference']);
    $payment_amount = trim($_POST['payment_amount']);
    $payment_date = $_POST['payment_date'];
    $receipt_file = $_FILES['receipt'];
    
    // Validation
    if (empty($sms_reference) || empty($payment_amount) || empty($payment_date)) {
        $error_message = 'Please fill in all required fields.';
    } elseif ($payment_amount != $booking['total_amount']) {
        $error_message = 'Payment amount must match the booking total.';
    } elseif (empty($receipt_file['name'])) {
        $error_message = 'Please upload your payment receipt.';
    } else {
        // Handle file upload
        $upload_dir = 'uploads/payment_receipts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($receipt_file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_message = 'Only JPG, PNG, and PDF files are allowed.';
        } elseif ($receipt_file['size'] > 5000000) { // 5MB limit
            $error_message = 'File size must be less than 5MB.';
        } else {
            $filename = uniqid() . '_receipt.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($receipt_file['tmp_name'], $filepath)) {
                // Insert payment record
                $payment_query = "INSERT INTO payments (booking_id, payment_method, amount, reference_number, 
                                payment_date, receipt_file, status, created_at) 
                                VALUES (?, 'sms_payment', ?, ?, ?, ?, 'pending', NOW())";
                $payment_stmt = mysqli_prepare($conn, $payment_query);
                mysqli_stmt_bind_param($payment_stmt, "idsss", $booking_id, $payment_amount, $sms_reference, $payment_date, $filename);
                
                if (mysqli_stmt_execute($payment_stmt)) {
                    // Update booking status
                    $update_query = "UPDATE bookings SET payment_status = 'pending_verification' WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
                    mysqli_stmt_execute($update_stmt);
                    
                    $success_message = 'SMS payment submitted successfully! Your payment is pending verification.';
                    unset($_SESSION['sms_booking_id']); // Clear session
                } else {
                    $error_message = 'Error saving payment details. Please try again.';
                }
            } else {
                $error_message = 'Error uploading file. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Payment - MG Transport Services</title>
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
            background: linear-gradient(135deg, #28a745, #20c997);
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
        .btn-submit {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            transform: translateY(-2px);
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
                            <i class="fas fa-mobile-alt me-3"></i>
                            SMS Payment
                        </h1>
                        <p class="mb-0">Complete your SMS banking payment</p>
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

                        <!-- SMS Payment Instructions -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>SMS Payment Instructions</h6>
                            <ol class="mb-0">
                                <li>Send SMS to your bank's SMS banking number</li>
                                <li>Include the amount: <strong><?php echo formatCurrency($booking['total_amount']); ?></strong></li>
                                <li>Reference: <strong>MG<?php echo $booking['id']; ?></strong></li>
                                <li>Wait for confirmation SMS from your bank</li>
                                <li>Upload the payment receipt below</li>
                            </ol>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sms_reference" class="form-label">SMS Reference Number *</label>
                                    <input type="text" class="form-control" id="sms_reference" name="sms_reference" 
                                           value="<?php echo htmlspecialchars($_POST['sms_reference'] ?? ''); ?>" 
                                           placeholder="e.g., SMS123456789" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="payment_amount" class="form-label">Payment Amount *</label>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                           value="<?php echo htmlspecialchars($_POST['payment_amount'] ?? $booking['total_amount']); ?>" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="receipt" class="form-label">Payment Receipt *</label>
                                    <input type="file" class="form-control" id="receipt" name="receipt" 
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="text-muted">Upload SMS confirmation or bank receipt (JPG, PNG, PDF, max 5MB)</small>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-submit me-3">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Payment
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
</body>
</html>
