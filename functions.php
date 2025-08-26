<?php
// Helper functions for MG Transport Services Booking System

// Function to get system setting
function getSystemSetting($key, $conn) {
    // Map setting keys to actual column names
    $column_map = [
        'gst_rate' => 'gst_rate',
        'company_name' => 'company_name',
        'company_email' => 'company_email',
        'company_phone' => 'company_phone',
        'company_address' => 'company_address',
        'smtp_host' => 'smtp_host',
        'smtp_port' => 'smtp_port',
        'smtp_username' => 'smtp_username',
        'smtp_password' => 'smtp_password',
        'smtp_encryption' => 'smtp_encryption',
        'maintenance_mode' => 'maintenance_mode',
        'max_booking_days' => 'max_booking_days',
        'currency' => 'currency',
        'timezone' => 'timezone'
    ];
    
    if (!isset($column_map[$key])) {
        return null;
    }
    
    $column = $column_map[$key];
    
    // First check if the column exists
    $check_column = "SHOW COLUMNS FROM system_settings LIKE '$column'";
    $column_result = mysqli_query($conn, $check_column);
    
    if (mysqli_num_rows($column_result) == 0) {
        // Column doesn't exist, return default value based on key
        $defaults = [
            'gst_rate' => 10.0,
            'company_name' => 'MG Transport Services',
            'company_email' => 'info@mgtransport.com',
            'company_phone' => '+675 1234 5678',
            'company_address' => 'Torokina Estate, Madang Province, North Coast, Papua New Guinea',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => '587',
            'smtp_username' => 'noreply@mgtransport.com',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'maintenance_mode' => 0,
            'max_booking_days' => 30,
            'currency' => 'PGK',
            'timezone' => 'Pacific/Port_Moresby'
        ];
        
        return $defaults[$key] ?? null;
    }
    
    $query = "SELECT $column FROM system_settings WHERE id = 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row[$column];
    }
    return null;
}

// Function to calculate GST
function calculateGST($amount, $conn) {
    $gst_rate = getSystemSetting('gst_rate', $conn);
    if ($gst_rate === null) {
        $gst_rate = 10.0; // Default GST rate
    }
    
    // Ensure gst_rate is numeric
    $gst_rate = floatval($gst_rate);
    $amount = floatval($amount);
    
    return ($amount * $gst_rate) / 100;
}

// Function to calculate total booking amount
function calculateBookingTotal($rate_per_day, $total_days, $conn) {
    // Ensure inputs are numeric
    $rate_per_day = floatval($rate_per_day);
    $total_days = intval($total_days);
    
    $subtotal = $rate_per_day * $total_days;
    $gst_amount = calculateGST($subtotal, $conn);
    $total_amount = $subtotal + $gst_amount;
    
    return [
        'subtotal' => $subtotal,
        'gst_amount' => $gst_amount,
        'total_amount' => $total_amount
    ];
}

// Function to generate invoice number
function generateInvoiceNumber($conn) {
    $prefix = 'INV';
    $year = date('Y');
    $month = date('m');
    
    $query = "SELECT COUNT(*) as count FROM invoices WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $year, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $count = $row['count'] + 1;
    return $prefix . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin($conn) {
    if (!isLoggedIn()) return false;
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    return $user && in_array($user['role'], ['admin', 'super_admin']);
}

// Function to check if user is super admin
function isSuperAdmin($conn) {
    if (!isLoggedIn()) return false;
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    return $user && $user['role'] === 'super_admin';
}

// Function to redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

// Function to display message
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'info';
        $message = $_SESSION['message'];
        
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($message);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to check if vehicle is available for given dates
function isVehicleAvailable($vehicle_id, $start_date, $end_date, $conn) {
    $query = "SELECT COUNT(*) as count FROM bookings 
              WHERE vehicle_id = ? AND status NOT IN ('cancelled', 'completed') 
              AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?) 
              OR (start_date >= ? AND end_date <= ?))";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "issssss", $vehicle_id, $end_date, $start_date, $end_date, $start_date, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] == 0;
}

// Function to get vehicle details
function getVehicleDetails($vehicle_id, $conn) {
    $query = "SELECT * FROM vehicles WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Function to get user details
function getUserDetails($user_id, $conn) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

// Function to create notification
function createNotification($user_id, $title, $message, $type = 'info', $conn) {
    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $title, $message, $type);
    return mysqli_stmt_execute($stmt);
}

// Function to get unread notifications count
function getUnreadNotificationsCount($user_id, $conn) {
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// Function to send email (placeholder - implement actual email sending)
function sendEmail($to, $subject, $message) {
    // For now, just log the email
    error_log("Email to: $to, Subject: $subject, Message: $message");
    return true;
}

// Function to format currency
function formatCurrency($amount) {
    return 'PGK ' . number_format($amount, 2);
}

// Function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to format datetime
function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

// Function to get days between two dates
function getDaysBetween($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    return $interval->days + 1; // Include both start and end dates
}

// Function to check if date is in the past
function isDateInPast($date) {
    return strtotime($date) < strtotime(date('Y-m-d'));
}

// Function to check if date is today
function isDateToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

// Function to get maintenance due vehicles
function getMaintenanceDueVehicles($conn) {
    $query = "SELECT * FROM vehicles WHERE next_service_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get insurance due vehicles
function getInsuranceDueVehicles($conn) {
    $query = "SELECT * FROM vehicles WHERE insurance_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get registration due vehicles
function getRegistrationDueVehicles($conn) {
    $query = "SELECT * FROM vehicles WHERE registration_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to log activity
function logActivity($user_id, $action, $details = '', $conn) {
    // Validate user_id exists before creating notification
    if ($user_id && is_numeric($user_id)) {
        $check_query = "SELECT id FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // User exists, create notification
            createNotification($user_id, 'Activity Log', $action . ': ' . $details, 'info', $conn);
        }
        // If user doesn't exist, silently skip the notification
    }
    // If user_id is invalid, silently skip the notification
}

// Function to validate booking dates
function validateBookingDates($start_date, $end_date) {
    if (isDateInPast($start_date)) {
        return ['valid' => false, 'message' => 'Start date cannot be in the past'];
    }
    
    if (isDateInPast($end_date)) {
        return ['valid' => false, 'message' => 'End date cannot be in the past'];
    }
    
    if (strtotime($start_date) > strtotime($end_date)) {
        return ['valid' => false, 'message' => 'Start date cannot be after end date'];
    }
    
    return ['valid' => true, 'message' => 'Dates are valid'];
}

// Function to get booking statistics
function getBookingStats($conn) {
    $stats = [];
    
    // Total bookings
    $query = "SELECT COUNT(*) as total FROM bookings";
    $result = mysqli_query($conn, $query);
    $stats['total_bookings'] = mysqli_fetch_assoc($result)['total'];
    
    // Active bookings
    $query = "SELECT COUNT(*) as active FROM bookings WHERE status = 'active'";
    $result = mysqli_query($conn, $query);
    $stats['active_bookings'] = mysqli_fetch_assoc($result)['active'];
    
    // Total revenue
    $query = "SELECT SUM(total_amount) as revenue FROM bookings WHERE payment_status = 'paid'";
    $result = mysqli_query($conn, $query);
    $stats['total_revenue'] = mysqli_fetch_assoc($result)['revenue'] ?? 0;
    
    // Available vehicles
    $query = "SELECT COUNT(*) as available FROM vehicles WHERE status = 'available'";
    $result = mysqli_query($conn, $query);
    $stats['available_vehicles'] = mysqli_fetch_assoc($result)['available'];
    
    // Total users
    $query = "SELECT COUNT(*) as total FROM users";
    $result = mysqli_query($conn, $query);
    $stats['total_users'] = mysqli_fetch_assoc($result)['total'];
    
    return $stats;
}

// Function to get available vehicles count
function getAvailableVehiclesCount($conn) {
    $query = "SELECT COUNT(*) as count FROM vehicles WHERE status = 'available'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// Function to get status color for badges
function getStatusColor($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'confirmed':
            return 'info';
        case 'active':
            return 'primary';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

// Function to get notification type for alerts
function getNotificationType($type) {
    switch ($type) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        case 'error':
            return 'danger';
        case 'info':
        default:
            return 'info';
    }
}

// Function to format vehicle type for display
function formatVehicleType($vehicle_type) {
    switch ($vehicle_type) {
        case 'land_cruiser_78_series':
            return 'Land Cruiser 78 Series';
        case 'land_cruiser_79_series':
            return 'Land Cruiser 79 Series';
        case 'land_cruiser_10_seater':
            return 'Land Cruiser 10-Seater';
        case 'land_cruiser_open_back':
            return 'Land Cruiser Open Back';
        case 'land_cruiser_4_door':
            return 'Land Cruiser 4-Door';
        case 'land_cruiser_5_door':
            return 'Land Cruiser 5-Door';
        case 'hilux':
            return 'Hilux';
        case 'harrier':
            return 'Harrier';
        case 'bus':
            return 'Bus';
        case 'sedan':
            return 'Sedan';
        case 'suv':
            return 'SUV';
        case 'truck':
            return 'Truck';
        case 'van':
            return 'Van';
        default:
            return ucfirst(str_replace('_', ' ', $vehicle_type));
    }
}

// Function to get specifications by type from database
function getSpecificationsByType($conn, $type) {
    $query = "SELECT * FROM vehicle_specifications WHERE type = ? ORDER BY value";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get vehicle specifications from database
function getVehicleSpecificationsFromDB($vehicle_id, $conn) {
    $query = "SELECT fuel_type, body_color, transmission, seat_capacity 
              FROM vehicles WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $vehicle = mysqli_fetch_assoc($result);
    
    if ($vehicle) {
        return [
            'fuel_type' => $vehicle['fuel_type'] ?? 'N/A',
            'body_color' => $vehicle['body_color'] ?? 'N/A',
            'transmission' => $vehicle['transmission'] ?? 'N/A',
            'seat_capacity' => $vehicle['seat_capacity'] ?? 'N/A'
        ];
    }
    
    return [
        'fuel_type' => 'N/A',
        'body_color' => 'N/A',
        'transmission' => 'N/A',
        'seat_capacity' => 'N/A'
    ];
}


?> 