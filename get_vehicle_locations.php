<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Get vehicles with GPS tracking
$query = "SELECT id, name, registration_number, last_latitude, last_longitude, 
          last_location_update, is_tracked 
          FROM vehicles 
          WHERE is_tracked = TRUE 
          AND last_latitude IS NOT NULL 
          AND last_longitude IS NOT NULL
          ORDER BY name";

$result = mysqli_query($conn, $query);
$vehicles = [];

while ($row = mysqli_fetch_assoc($result)) {
    $vehicles[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'registration_number' => $row['registration_number'],
        'latitude' => (float)$row['last_latitude'],
        'longitude' => (float)$row['last_longitude'],
        'last_location_update' => $row['last_location_update'],
        'is_tracked' => (bool)$row['is_tracked']
    ];
}

echo json_encode($vehicles);
?> 