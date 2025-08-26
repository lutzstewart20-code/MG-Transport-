<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
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

// Handle receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = sanitizeInput($_POST['transaction_id']);
    $amount_paid = sanitizeInput($_POST['amount_paid']);
    $payment_date = sanitizeInput($_POST['payment_date']);
    $bank_name = sanitizeInput($_POST['bank_name']);
    $account_number = sanitizeInput($_POST['account_number']);
    $reference_number = sanitizeInput($_POST['reference_number']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Handle file upload
    $receipt_file = $_FILES['receipt_file'];
    $upload_dir = 'uploads/receipts/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($receipt_file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        redirectWithMessage('sms_banking.php?booking_id=' . $booking_id, 'Invalid file type. Please upload JPG, PNG, or PDF files only.', 'error');
    }
    
    if ($receipt_file['size'] > 5 * 1024 * 1024) { // 5MB limit
        redirectWithMessage('sms_banking.php?booking_id=' . $booking_id, 'File size too large. Please upload a file smaller than 5MB.', 'error');
    }
    
    // Generate unique filename
    $filename = 'receipt_' . $booking_id . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($receipt_file['tmp_name'], $filepath)) {
        // Store receipt information in database
        $receipt_query = "INSERT INTO payment_receipts (booking_id, transaction_id, amount_paid, 
                         payment_date, bank_name, account_number, reference_number, receipt_file, 
                         notes, status, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $receipt_stmt = mysqli_prepare($conn, $receipt_query);
        mysqli_stmt_bind_param($receipt_stmt, "issssssss", $booking_id, $transaction_id, $amount_paid, 
                              $payment_date, $bank_name, $account_number, $reference_number, $filename, $notes);
        
        if (mysqli_stmt_execute($receipt_stmt)) {
            $receipt_id = mysqli_insert_id($conn);
            
            // Update booking payment method and status
            $update_booking = "UPDATE bookings SET payment_method = 'sms_transfer', 
                              payment_status = 'paid' WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_booking);
            mysqli_stmt_bind_param($update_stmt, "i", $booking_id);
            mysqli_stmt_execute($update_stmt);
            
            // Send notification to admin
            $admin_notification = "New SMS banking receipt uploaded for booking #$booking_id. 
                                  Amount: " . formatCurrency($amount_paid) . ", 
                                  Transaction ID: $transaction_id. Status: Payment Made - Receipt Verification Required";
            
            // Get admin users
            $admin_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
            $admin_result = mysqli_query($conn, $admin_query);
            
            while ($admin = mysqli_fetch_assoc($admin_result)) {
                createNotification($admin['id'], 'New Payment Receipt', $admin_notification, 'info', $conn);
            }
            
            // Send email to admin
            $admin_email_query = "SELECT email FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1";
            $admin_email_result = mysqli_query($conn, $admin_email_query);
            $admin_email = mysqli_fetch_assoc($admin_email_result);
            
            if ($admin_email) {
                $subject = "New SMS Banking Receipt - Booking #$booking_id";
                $message = "
                <h2>New SMS Banking Receipt Uploaded</h2>
                <p>A customer has uploaded a payment receipt for booking #$booking_id.</p>
                <h3>Receipt Details:</h3>
                <ul>
                    <li><strong>Transaction ID:</strong> $transaction_id</li>
                    <li><strong>Amount Paid:</strong> " . formatCurrency($amount_paid) . "</li>
                    <li><strong>Payment Date:</strong> $payment_date</li>
                    <li><strong>Bank:</strong> $bank_name</li>
                    <li><strong>Account:</strong> $account_number</li>
                    <li><strong>Reference:</strong> $reference_number</li>
                </ul>
                <p><a href='http://localhost/MG%20Transport/admin/bookings.php'>View Booking Details</a></p>";
                
                sendEmail($admin_email['email'], $subject, $message);
            }
            
            // Set success notification for my-bookings.php
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_message'] = "SMS banking receipt uploaded successfully! Your payment status is now 'Paid' and your booking is confirmed.";
            $_SESSION['payment_type'] = 'success';
            
            redirectWithMessage('my-bookings.php', 'Receipt uploaded successfully! Your payment status is now "Paid" and your booking is confirmed.', 'success');
        } else {
            // Set error notification
            $_SESSION['payment_success'] = false;
            $_SESSION['payment_message'] = 'Error uploading receipt. Please try again.';
            $_SESSION['payment_type'] = 'error';
            
            redirectWithMessage('sms_banking.php?booking_id=' . $booking_id, 'Error uploading receipt. Please try again.', 'error');
        }
    } else {
        // Set error notification
        $_SESSION['payment_success'] = false;
        $_SESSION['payment_message'] = 'Error uploading file. Please try again.';
        $_SESSION['payment_type'] = 'error';
        
        redirectWithMessage('sms_banking.php?booking_id=' . $booking_id, 'Error uploading file. Please try again.', 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Banking Receipt Upload - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>SMS Banking Receipt Upload</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Booking Details</h6>
                            <p class="mb-1"><strong>Vehicle:</strong> <?php echo htmlspecialchars($booking['vehicle_name']); ?></p>
                            <p class="mb-1"><strong>Amount Due:</strong> <?php echo formatCurrency($booking['total_amount']); ?></p>
                            <p class="mb-0"><strong>Booking ID:</strong> #<?php echo $booking['id']; ?></p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Instructions</h6>
                            <ol class="mb-0">
                                <li>Complete your SMS banking transaction</li>
                                <li>Take a screenshot or photo of your transaction receipt</li>
                                <li>Fill in the details below and upload the receipt</li>
                                <li>Admin will verify and confirm your booking</li>
                            </ol>
                        </div>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="transaction_id" class="form-label">Transaction ID *</label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           placeholder="Enter transaction ID from SMS" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid (PGK) *</label>
                                    <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                                           step="0.01" min="0" value="<?php echo $booking['total_amount']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="bank_name" class="form-label">Bank Name *</label>
                                    <select class="form-select" id="bank_name" name="bank_name" required>
                                        <option value="">Select Bank</option>
                                        <option value="BSP">Bank South Pacific (BSP)</option>
                                        <option value="ANZ">ANZ Bank</option>
                                        <option value="Westpac">Westpac Bank</option>
                                        <option value="Kina Bank">Kina Bank</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="account_number" class="form-label">Account Number *</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number" 
                                           placeholder="Your account number" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reference_number" class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                           placeholder="Transaction reference (optional)">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="receipt_file" class="form-label">Receipt File *</label>
                                <input type="file" class="form-control" id="receipt_file" name="receipt_file" 
                                       accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="form-text">
                                    Upload screenshot or photo of your SMS banking receipt. 
                                    Accepted formats: JPG, PNG, PDF (max 5MB)
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Any additional information about your payment..."></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-upload me-2"></i>Upload Receipt
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

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 