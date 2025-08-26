<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get booking data for export
$export_query = "SELECT 
    b.id,
    b.start_date,
    b.end_date,
    b.total_days,
    b.rate_per_day,
    b.subtotal,
    b.gst_amount,
    b.total_amount,
    b.pickup_location,
    b.dropoff_location,
    b.special_requests,
    b.payment_method,
    b.payment_status,
    b.status,
    b.created_at,
    v.name as vehicle_name,
    v.registration_number,
    v.model,
    v.year,
    u.first_name,
    u.last_name,
    u.email,
    u.phone
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN users u ON b.user_id = u.id
    WHERE b.created_at BETWEEN ? AND ?
    ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $export_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Set headers for CSV download
$filename = "mg_transport_bookings_" . date('Y-m-d') . "_" . $start_date . "_to_" . $end_date . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = array(
    'Booking ID',
    'Customer Name',
    'Customer Email',
    'Customer Phone',
    'Vehicle Name',
    'Vehicle Registration',
    'Vehicle Model',
    'Vehicle Year',
    'Start Date',
    'End Date',
    'Total Days',
    'Rate Per Day',
    'Subtotal',
    'GST Amount',
    'Total Amount',
    'Pickup Location',
    'Dropoff Location',
    'Special Requests',
    'Payment Method',
    'Payment Status',
    'Booking Status',
    'Created Date'
);

// Write headers
fputcsv($output, $headers);

// Write data rows
while ($row = mysqli_fetch_assoc($result)) {
    $data = array(
        $row['id'],
        $row['first_name'] . ' ' . $row['last_name'],
        $row['email'],
        $row['phone'],
        $row['vehicle_name'],
        $row['registration_number'],
        $row['model'],
        $row['year'],
        $row['start_date'],
        $row['end_date'],
        $row['total_days'],
        $row['rate_per_day'],
        $row['subtotal'],
        $row['gst_amount'],
        $row['total_amount'],
        $row['pickup_location'],
        $row['dropoff_location'],
        $row['special_requests'],
        ucfirst(str_replace('_', ' ', $row['payment_method'])),
        ucfirst($row['payment_status']),
        ucfirst($row['status']),
        $row['created_at']
    );
    
    fputcsv($output, $data);
}

// Close the file pointer
fclose($output);
exit;
?> 