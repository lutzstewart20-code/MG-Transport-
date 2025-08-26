<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
session_start();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get tracking data for user's active bookings
    $query = "SELECT b.id as booking_id, v.name as vehicle_name, v.vehicle_type,
              t.latitude, t.longitude, t.speed, t.status as tracking_status, 
              t.last_updated, b.status as booking_status
              FROM bookings b 
              LEFT JOIN vehicles v ON b.vehicle_id = v.id 
              LEFT JOIN vehicle_tracking t ON v.id = t.vehicle_id 
              WHERE b.user_id = ? AND b.status IN ('confirmed', 'in_progress', 'completed')
              ORDER BY t.last_updated DESC";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $tracking_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tracking_data[] = [
            'booking_id' => $row['booking_id'],
            'vehicle_name' => $row['vehicle_name'],
            'vehicle_type' => $row['vehicle_type'],
            'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
            'speed' => $row['speed'] ? (int)$row['speed'] : 0,
            'status' => ucfirst(str_replace('_', ' ', $row['booking_status'])),
            'tracking_status' => $row['tracking_status'],
            'last_updated' => $row['last_updated'] ? date('H:i:s', strtotime($row['last_updated'])) : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tracking_data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?> 