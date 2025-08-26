<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// BSP Payment Callback Handler
// This file would be called by BSP's payment gateway to notify us of payment status

// Log the callback for debugging
$log_file = 'logs/payment_callback.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

$callback_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'post_data' => $_POST,
    'get_data' => $_GET
];

file_put_contents($log_file, json_encode($callback_data, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Verify BSP signature (in real implementation)
$bsp_signature = $_POST['signature'] ?? '';
$merchant_id = $_POST['merchant_id'] ?? '';
$transaction_id = $_POST['transaction_id'] ?? '';
$status = $_POST['status'] ?? '';
$amount = $_POST['amount'] ?? '';
$reference = $_POST['reference'] ?? '';

// Extract booking ID from reference
$booking_id = str_replace('MG_TRANSPORT_', '', $reference);

if ($status === 'success') {
    // Payment successful
    $update_booking = "UPDATE bookings SET status = 'confirmed', payment_status = 'paid', 
                      payment_method = 'bsp_online' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_booking);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Update payment request
        $update_payment = "UPDATE payment_requests SET status = 'completed', 
                          transaction_id = ?, completed_at = NOW() 
                          WHERE booking_id = ? AND status = 'pending'";
        $payment_stmt = mysqli_prepare($conn, $update_payment);
        mysqli_stmt_bind_param($payment_stmt, "si", $transaction_id, $booking_id);
        mysqli_stmt_execute($payment_stmt);
        
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
        
        if ($booking) {
            // Set success notification for the user
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_message'] = "BSP payment processed successfully! Your booking is now confirmed.";
            $_SESSION['payment_type'] = 'success';
            
            // Send confirmation email to customer
            $subject = "Payment Successful - Booking Confirmed - MG Transport Services";
            $message = "
            <h2>Payment Successful - Booking Confirmed</h2>
            <p>Dear {$booking['first_name']} {$booking['last_name']},</p>
            <p>Your BSP online banking payment has been processed successfully!</p>
            <h3>Payment Details:</h3>
            <ul>
                <li><strong>Transaction ID:</strong> $transaction_id</li>
                <li><strong>Amount Paid:</strong> " . formatCurrency($amount) . "</li>
                <li><strong>Payment Method:</strong> BSP Online Banking</li>
                <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                <li><strong>Booking ID:</strong> #{$booking['id']}</li>
            </ul>
            <p>Your booking is now confirmed and your vehicle will be ready for pickup on the scheduled date.</p>
            <p>Thank you for choosing MG Transport Services!</p>";
            
            sendEmail($booking['email'], $subject, $message);
            
            // Send notification to admin
            $admin_notification = "BSP payment completed for booking #{$booking['id']}. 
                                  Transaction ID: $transaction_id, Amount: " . formatCurrency($amount);
            
            $admin_query = "SELECT id FROM users WHERE role IN ('admin', 'super_admin')";
            $admin_result = mysqli_query($conn, $admin_query);
            
            while ($admin = mysqli_fetch_assoc($admin_result)) {
                createNotification($admin['id'], 'Payment Completed', $admin_notification, 'success', $conn);
            }
            
            // Send WhatsApp/Email notification to admin
            $admin_email_query = "SELECT email FROM users WHERE role IN ('admin', 'super_admin') LIMIT 1";
            $admin_email_result = mysqli_query($conn, $admin_email_query);
            $admin_email = mysqli_fetch_assoc($admin_email_result);
            
            if ($admin_email) {
                $admin_subject = "Payment Completed - Booking #{$booking['id']}";
                $admin_message = "
                <h2>BSP Payment Completed</h2>
                <p>A customer has successfully completed payment for booking #{$booking['id']}.</p>
                <h3>Payment Details:</h3>
                <ul>
                    <li><strong>Transaction ID:</strong> $transaction_id</li>
                    <li><strong>Amount:</strong> " . formatCurrency($amount) . "</li>
                    <li><strong>Payment Method:</strong> BSP Online Banking</li>
                    <li><strong>Customer:</strong> {$booking['first_name']} {$booking['last_name']}</li>
                    <li><strong>Vehicle:</strong> {$booking['vehicle_name']}</li>
                </ul>
                <p><a href='http://localhost/MG%20Transport/admin/bookings.php'>View Booking Details</a></p>";
                
                sendEmail($admin_email['email'], $admin_subject, $admin_message);
            }
        }
        
        // Return success response to BSP
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed successfully']);
    } else {
        // Set error notification
        $_SESSION['payment_success'] = false;
        $_SESSION['payment_message'] = 'Database error occurred during payment processing.';
        $_SESSION['payment_type'] = 'error';
        
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    // Payment failed
    $update_booking = "UPDATE bookings SET payment_status = 'failed' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_booking);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    
    // Update payment request
    $update_payment = "UPDATE payment_requests SET status = 'failed', completed_at = NOW() 
                      WHERE booking_id = ? AND status = 'pending'";
    $payment_stmt = mysqli_prepare($conn, $update_payment);
    mysqli_stmt_bind_param($payment_stmt, "i", $booking_id);
    mysqli_stmt_execute($payment_stmt);
    
    // Set error notification
    $_SESSION['payment_success'] = false;
    $_SESSION['payment_message'] = 'Payment failed. Please try again or contact support.';
    $_SESSION['payment_type'] = 'error';
    
    // Return failure response to BSP
    http_response_code(200);
    echo json_encode(['status' => 'failed', 'message' => 'Payment failed']);
}
?> 