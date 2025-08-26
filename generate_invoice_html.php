<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Get booking ID from URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    die('Invalid booking ID');
}

// Get booking details with user and vehicle information
$booking_query = "
SELECT b.*, u.username, u.email, u.first_name, u.last_name, u.phone,
       v.name as vehicle_name, v.image_url, v.rate_per_day,
       v.vehicle_type, v.description as vehicle_description
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN vehicles v ON b.vehicle_id = v.id
WHERE b.id = ?
";

$stmt = mysqli_prepare($conn, $booking_query);
mysqli_stmt_bind_param($stmt, "i", $booking_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    die('Booking not found');
}

// Calculate days
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$days = $start_date->diff($end_date)->days + 1;

// Calculate GST
$gst_rate = getSystemSetting('gst_rate', $conn);
$gst_amount = ($booking['total_amount'] * $gst_rate) / 100;
$subtotal = $booking['total_amount'] - $gst_amount;

// Get payment details if available
$payment_details = [];
if (!empty($booking['payment_details'])) {
    $payment_details = json_decode($booking['payment_details'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Invoice #<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?> - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 20px; }
            .invoice-container { box-shadow: none; border: none; }
        }
        .invoice-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .invoice-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .invoice-body {
            padding: 30px;
        }
        .invoice-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .invoice-section:last-child {
            border-bottom: none;
        }
        .section-title {
            color: #dc3545;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .total-row {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.1em;
        }
        .logo {
            max-width: 100px;
            max-height: 100px;
            margin-bottom: 15px;
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .download-btn {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Print and Download Buttons -->
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary print-btn">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button onclick="downloadInvoice()" class="btn btn-success download-btn">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <a href="../admin/bookings.php" class="btn btn-secondary" style="position: fixed; top: 120px; right: 20px; z-index: 1000;">
            <i class="fas fa-arrow-left"></i> Back to Admin
        </a>
    </div>

    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="logo" onerror="this.style.display='none'">
            <h1>MG Transport Services</h1>
            <h3>ADMIN INVOICE</h3>
            <p class="mb-0">Administrative Invoice - Internal Use</p>
        </div>

        <!-- Body -->
        <div class="invoice-body">
            <!-- Invoice Details -->
            <div class="invoice-section">
                <h4 class="section-title">Admin Invoice Details</h4>
                <div class="info-row">
                    <span class="info-label">Invoice Number:</span>
                    <span class="info-value">INV-<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Invoice Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Generated By:</span>
                    <span class="info-value">Admin - <?php echo date('F d, Y g:i A'); ?></span>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="invoice-section">
                <h4 class="section-title">Customer Information</h4>
                <div class="info-row">
                    <span class="info-label">Customer ID:</span>
                    <span class="info-value"><?php echo $booking['user_id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Username:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                </div>
            </div>

            <!-- Booking Details -->
            <div class="invoice-section">
                <h4 class="section-title">Booking Details</h4>
                <div class="info-row">
                    <span class="info-label">Booking ID:</span>
                    <span class="info-value"><?php echo $booking['id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle ID:</span>
                    <span class="info-value"><?php echo $booking['vehicle_id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vehicle:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['vehicle_name'] . ' (' . $booking['vehicle_type'] . ')'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Pickup Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['start_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Return Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($booking['end_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Duration:</span>
                    <span class="info-value"><?php echo $days; ?> day(s)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value"><?php echo ucfirst($booking['status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo ucfirst($booking['payment_method']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Created At:</span>
                    <span class="info-value"><?php echo date('F d, Y g:i A', strtotime($booking['created_at'])); ?></span>
                </div>
            </div>

            <!-- Payment Details (if available) -->
            <?php if (!empty($payment_details)): ?>
            <div class="invoice-section">
                <h4 class="section-title">Payment Details</h4>
                <?php foreach ($payment_details as $key => $value): ?>
                <div class="info-row">
                    <span class="info-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                    <span class="info-value"><?php echo htmlspecialchars($value); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Item Details -->
            <div class="invoice-section">
                <h4 class="section-title">Item Details</h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-danger">
                            <tr>
                                <th>Description</th>
                                <th>Rate</th>
                                <th>Days</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['vehicle_name'] . ' (' . $booking['vehicle_type'] . ')'); ?></td>
                                <td><?php echo formatCurrency($booking['rate_per_day']); ?></td>
                                <td><?php echo $days; ?></td>
                                <td><?php echo formatCurrency($booking['rate_per_day'] * $days); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="invoice-section">
                <h4 class="section-title">Payment Summary</h4>
                <div class="info-row">
                    <span class="info-label">Subtotal:</span>
                    <span class="info-value"><?php echo formatCurrency($subtotal); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">GST (<?php echo $gst_rate; ?>%):</span>
                    <span class="info-value"><?php echo formatCurrency($gst_amount); ?></span>
                </div>
                <div class="info-row total-row">
                    <span class="info-label">Total Amount:</span>
                    <span class="info-value"><?php echo formatCurrency($booking['total_amount']); ?></span>
                </div>
            </div>

            <!-- Admin Notes -->
            <div class="invoice-section">
                <h4 class="section-title">Admin Notes</h4>
                <ul>
                    <li>This is an administrative invoice for internal use</li>
                    <li>Customer ID: <?php echo $booking['user_id']; ?></li>
                    <li>Booking ID: <?php echo $booking['id']; ?></li>
                    <li>Generated on: <?php echo date('F d, Y g:i A'); ?></li>
                    <li>For any queries, contact the system administrator</li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="text-center text-muted mt-4">
                <p><strong>MG Transport Services - Administrative Invoice</strong></p>
                <p>This is a computer-generated invoice for administrative purposes.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadInvoice() {
            const element = document.querySelector('.invoice-container');
            const opt = {
                margin: 1,
                filename: 'Admin-Invoice-<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html> 