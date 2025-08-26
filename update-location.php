<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check admin authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$required_fields = ['vehicle_id', 'latitude', 'longitude'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Extract and validate data
$vehicle_id = (int)$input['vehicle_id'];
$latitude = (float)$input['latitude'];
$longitude = (float)$input['longitude'];
$speed = isset($input['speed']) ? (float)$input['speed'] : 0.00;
$status = isset($input['status']) ? $input['status'] : 'moving';
$fuel_level = isset($input['fuel_level']) ? (float)$input['fuel_level'] : null;
$heading = isset($input['heading']) ? (float)$input['heading'] : 0.00;
$altitude = isset($input['altitude']) ? (float)$input['altitude'] : 0.00;
$gps_signal_strength = isset($input['gps_signal_strength']) ? $input['gps_signal_strength'] : 'good';
$battery_level = isset($input['battery_level']) ? (float)$input['battery_level'] : null;
$engine_status = isset($input['engine_status']) ? $input['engine_status'] : 'running';

// Validate GPS coordinates
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates']);
    exit;
}

// Validate speed (reasonable range: 0-200 km/h)
if ($speed < 0 || $speed > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid speed value']);
    exit;
}

// Validate fuel level (0-100%)
if ($fuel_level !== null && ($fuel_level < 0 || $fuel_level > 100)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid fuel level']);
    exit;
}

// Validate battery level (0-100%)
if ($battery_level !== null && ($battery_level < 0 || $battery_level > 100)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid battery level']);
    exit;
}

try {
    // Update vehicle tracking table
    $update_query = "INSERT INTO vehicle_tracking (vehicle_id, latitude, longitude, speed, status, fuel_level, heading, altitude, gps_signal_strength, battery_level, engine_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE 
                     latitude = VALUES(latitude), 
                     longitude = VALUES(longitude), 
                     speed = VALUES(speed), 
                     status = VALUES(status), 
                     fuel_level = VALUES(fuel_level), 
                     heading = VALUES(heading), 
                     altitude = VALUES(altitude), 
                     gps_signal_strength = VALUES(gps_signal_strength), 
                     battery_level = VALUES(battery_level), 
                     engine_status = VALUES(engine_status), 
                     last_updated = CURRENT_TIMESTAMP";
    
    $update_stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($update_stmt, "idddsdddsds", $vehicle_id, $latitude, $longitude, $speed, $status, $fuel_level, $heading, $altitude, $gps_signal_strength, $battery_level, $engine_status);
    
    if (!mysqli_stmt_execute($update_stmt)) {
        throw new Exception("Failed to update vehicle tracking: " . mysqli_error($conn));
    }

    // Insert into tracking history
    $history_query = "INSERT INTO vehicle_tracking_history (vehicle_id, latitude, longitude, speed, status, fuel_level, heading, altitude, gps_signal_strength, battery_level, engine_status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $history_stmt = mysqli_prepare($conn, $history_query);
    mysqli_stmt_bind_param($history_stmt, "idddsdddsds", $vehicle_id, $latitude, $longitude, $speed, $status, $fuel_level, $heading, $altitude, $gps_signal_strength, $battery_level, $engine_status);
    
    if (!mysqli_stmt_execute($history_stmt)) {
        error_log("Failed to insert tracking history: " . mysqli_error($conn));
    }

    // Update vehicles table
    $vehicle_update_query = "UPDATE vehicles SET is_tracked = TRUE, last_tracking_update = CURRENT_TIMESTAMP WHERE id = ?";
    $vehicle_stmt = mysqli_prepare($conn, $vehicle_update_query);
    mysqli_stmt_bind_param($vehicle_stmt, "i", $vehicle_id);
    mysqli_stmt_execute($vehicle_stmt);

    // Generate alerts based on tracking data
    $alerts = [];
    
    // Speed limit alert (over 80 km/h)
    if ($speed > 80) {
        $alert_query = "INSERT INTO tracking_alerts (vehicle_id, alert_type, severity, message, latitude, longitude) 
                        VALUES (?, 'speed_limit', 'high', 'Vehicle exceeding speed limit: {$speed} km/h', ?, ?)";
        $alert_stmt = mysqli_prepare($conn, $alert_query);
        mysqli_stmt_bind_param($alert_stmt, "idd", $vehicle_id, $latitude, $longitude);
        mysqli_stmt_execute($alert_stmt);
        $alerts[] = 'Speed limit exceeded';
    }

    // Fuel level alert (if fuel < 20%)
    if ($fuel_level !== null && $fuel_level < 20) {
        $alert_message = "Fuel level low: {$fuel_level}%";
        $alert_query = "INSERT INTO tracking_alerts (vehicle_id, alert_type, severity, message, latitude, longitude) 
                        VALUES (?, 'fuel_low', 'medium', ?, ?, ?)";
        $alert_stmt = mysqli_prepare($conn, $alert_query);
        mysqli_stmt_bind_param($alert_stmt, "isdd", $vehicle_id, $alert_message, $latitude, $longitude);
        mysqli_stmt_execute($alert_stmt);
        $alerts[] = 'Fuel level low';
    }

    // Battery level alert (if battery < 15%)
    if ($battery_level !== null && $battery_level < 15) {
        $alert_query = "INSERT INTO tracking_alerts (vehicle_id, alert_type, severity, message, latitude, longitude) 
                        VALUES (?, 'battery_low', 'medium', 'Battery level low: {$battery_level}%', ?, ?)";
        $alert_stmt = mysqli_prepare($conn, $alert_query);
        mysqli_stmt_bind_param($alert_stmt, "idd", $vehicle_id, $latitude, $longitude);
        mysqli_stmt_execute($alert_stmt);
        $alerts[] = 'Battery level low';
    }

    // GPS signal lost alert
    if ($gps_signal_strength === 'none' || $gps_signal_strength === 'poor') {
        $alert_query = "INSERT INTO tracking_alerts (vehicle_id, alert_type, severity, message, latitude, longitude) 
                        VALUES (?, 'gps_signal_lost', 'high', 'GPS signal weak or lost: {$gps_signal_strength}', ?, ?)";
        $alert_stmt = mysqli_prepare($conn, $alert_query);
        mysqli_stmt_bind_param($alert_stmt, "idd", $vehicle_id, $latitude, $longitude);
        mysqli_stmt_execute($alert_stmt);
        $alerts[] = 'GPS signal weak';
    }

    // Engine fault alert
    if ($engine_status === 'maintenance') {
        $alert_query = "INSERT INTO tracking_alerts (vehicle_id, alert_type, severity, message, latitude, longitude) 
                        VALUES (?, 'engine_fault', 'critical', 'Engine requires maintenance', ?, ?)";
        $alert_stmt = mysqli_prepare($conn, $alert_query);
        mysqli_stmt_bind_param($alert_stmt, "idd", $vehicle_id, $latitude, $longitude);
        mysqli_stmt_execute($alert_stmt);
        $alerts[] = 'Engine maintenance required';
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully',
        'data' => [
            'vehicle_id' => $vehicle_id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'status' => $status,
            'gps_signal_strength' => $gps_signal_strength,
            'alerts_generated' => $alerts
        ]
    ]);

} catch (Exception $e) {
    error_log("GPS tracking update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
