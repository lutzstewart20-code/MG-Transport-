<?php
define('SECURE_ACCESS', true);
require_once 'includes/security-middleware.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_removal'])) {
    // Get customer count first
    $count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'customer'";
    $count_result = mysqli_query($conn, $count_query);
    $customer_count = mysqli_fetch_assoc($count_result)['count'];
    
    if ($customer_count == 0) {
        $message = 'No customers found in the system.';
        $message_type = 'info';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Remove related data first
            // Remove invoices
            $delete_invoices = "DELETE i FROM invoices i JOIN bookings b ON i.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
            mysqli_query($conn, $delete_invoices);
            
            // Remove verification codes
            $delete_codes = "DELETE vc FROM verification_codes vc JOIN users u ON vc.user_id = u.id WHERE u.role = 'customer'";
            mysqli_query($conn, $delete_codes);
            
            // Remove payment receipts
            $delete_receipts = "DELETE pr FROM payment_receipts pr JOIN bookings b ON pr.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
            mysqli_query($conn, $delete_receipts);
            
            // Remove payment requests
            $delete_requests = "DELETE pr FROM payment_requests pr JOIN bookings b ON pr.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
            mysqli_query($conn, $delete_requests);
            
            // Remove notifications
            $delete_notifications = "DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE role = 'customer')";
            mysqli_query($conn, $delete_notifications);
            
            // Remove bookings
            $delete_bookings = "DELETE FROM bookings WHERE user_id IN (SELECT id FROM users WHERE role = 'customer')";
            mysqli_query($conn, $delete_bookings);
            
            // Remove customers
            $delete_customers = "DELETE FROM users WHERE role = 'customer'";
            if (mysqli_query($conn, $delete_customers)) {
                mysqli_commit($conn);
                $message = "Successfully removed $customer_count customers and all related data from the system.";
                $message_type = 'success';
                
                // Log the action
                $log_message = "[" . date('Y-m-d H:i:s') . "] Admin " . $_SESSION['username'] . " removed $customer_count customers and all related data.\n";
                file_put_contents('../logs/system_operations.log', $log_message, FILE_APPEND | LOCK_EX);
            } else {
                throw new Exception("Failed to remove customers: " . mysqli_error($conn));
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get current statistics
$stats_query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins
                FROM users";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get related data counts
$bookings_count = 0;
$notifications_count = 0;
$payment_requests_count = 0;
$payment_receipts_count = 0;
$verification_codes_count = 0;
$invoices_count = 0;

if ($stats['customers'] > 0) {
    // Bookings
    $bookings_query = "SELECT COUNT(*) as count FROM bookings b JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
    $bookings_result = mysqli_query($conn, $bookings_query);
    $bookings_count = mysqli_fetch_assoc($bookings_result)['count'];
    
    // Notifications
    $notifications_query = "SELECT COUNT(*) as count FROM notifications n JOIN users u ON n.user_id = u.id WHERE u.role = 'customer'";
    $notifications_result = mysqli_query($conn, $notifications_query);
    $notifications_count = mysqli_fetch_assoc($notifications_result)['count'];
    
    // Payment requests
    $payment_requests_query = "SELECT COUNT(*) as count FROM payment_requests pr JOIN bookings b ON pr.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
    $payment_requests_result = mysqli_query($conn, $payment_requests_query);
    $payment_requests_count = mysqli_fetch_assoc($payment_requests_result)['count'];
    
    // Payment receipts
    $payment_receipts_query = "SELECT COUNT(*) as count FROM payment_receipts pr JOIN bookings b ON pr.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
    $payment_receipts_result = mysqli_query($conn, $payment_receipts_query);
    $payment_receipts_count = mysqli_fetch_assoc($payment_receipts_result)['count'];
    
    // Verification codes
    $verification_codes_query = "SELECT COUNT(*) as count FROM verification_codes vc JOIN users u ON vc.user_id = u.id WHERE u.role = 'customer'";
    $verification_codes_result = mysqli_query($conn, $verification_codes_query);
    $verification_codes_count = mysqli_fetch_assoc($verification_codes_result)['count'];
    
    // Invoices
    $invoices_query = "SELECT COUNT(*) as count FROM invoices i JOIN bookings b ON i.booking_id = b.id JOIN users u ON b.user_id = u.id WHERE u.role = 'customer'";
    $invoices_result = mysqli_query($conn, $invoices_query);
    $invoices_count = mysqli_fetch_assoc($invoices_result)['count'];
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users-slash text-danger me-2"></i>Remove All Customers
        </h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Customer Removal Confirmation
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Warning!</h5>
                        <p class="mb-0">This action will <strong>permanently remove all customer accounts</strong> from the system. This action cannot be undone.</p>
                    </div>
                    
                    <h6>What will be removed:</h6>
                    <ul>
                        <li>All customer user accounts (<?php echo $stats['customers']; ?>)</li>
                        <li>All customer bookings (<?php echo $bookings_count; ?>)</li>
                        <li>All customer notifications (<?php echo $notifications_count; ?>)</li>
                        <li>All customer payment records (<?php echo $payment_requests_count + $payment_receipts_count; ?>)</li>
                        <li>All customer verification codes (<?php echo $verification_codes_count; ?>)</li>
                        <li>All customer invoices (<?php echo $invoices_count; ?>)</li>
                    </ul>
                    
                    <h6>What will be preserved:</h6>
                    <ul>
                        <li>All admin and super admin accounts (<?php echo $stats['admins'] + $stats['super_admins']; ?>)</li>
                        <li>All vehicles and maintenance records</li>
                        <li>All system settings</li>
                        <li>All tracking data</li>
                    </ul>
                    
                    <?php if ($stats['customers'] > 0): ?>
                        <form method="POST" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone!');">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                                <label class="form-check-label" for="confirmCheck">
                                    I understand that this action will permanently delete all customer data and cannot be undone.
                                </label>
                            </div>
                        
                            <div class="d-grid gap-2">
                                <button type="submit" name="confirm_removal" value="yes" class="btn btn-danger btn-lg" id="removeBtn" disabled>
                                    <i class="fas fa-trash me-2"></i>Remove All Customers
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No customers found in the system. There's nothing to remove.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current System Status</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-danger"><?php echo $stats['customers']; ?></h4>
                                <small class="text-muted">Customers</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-warning"><?php echo $stats['admins']; ?></h4>
                                <small class="text-muted">Admins</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-info"><?php echo $stats['super_admins']; ?></h4>
                                <small class="text-muted">Super Admins</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary"><?php echo $stats['total_users']; ?></h4>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Related Data Summary</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Bookings:</span>
                            <span class="badge bg-secondary"><?php echo $bookings_count; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Notifications:</span>
                            <span class="badge bg-secondary"><?php echo $notifications_count; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Payment Records:</span>
                            <span class="badge bg-secondary"><?php echo $payment_requests_count + $payment_receipts_count; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Verification Codes:</span>
                            <span class="badge bg-secondary"><?php echo $verification_codes_count; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Invoices:</span>
                            <span class="badge bg-secondary"><?php echo $invoices_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('confirmCheck').addEventListener('change', function() {
    document.getElementById('removeBtn').disabled = !this.checked;
});
</script>

<?php include 'includes/footer.php'; ?>
