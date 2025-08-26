<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Handle tracking updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_location':
                $vehicle_id = (int)$_POST['vehicle_id'];
                $latitude = (float)$_POST['latitude'];
                $longitude = (float)$_POST['longitude'];
                $speed = (float)$_POST['speed'];
                $status = $_POST['status'];
                
                $query = "INSERT INTO vehicle_tracking (vehicle_id, latitude, longitude, speed, status) 
                         VALUES (?, ?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE 
                         latitude = VALUES(latitude),
                         longitude = VALUES(longitude),
                         speed = VALUES(speed),
                         status = VALUES(status),
                         last_updated = CURRENT_TIMESTAMP";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "iddds", $vehicle_id, $latitude, $longitude, $speed, $status);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Vehicle location updated successfully!";
                } else {
                    $error_message = "Error updating vehicle location: " . mysqli_error($conn);
                }
                break;
                
            case 'simulate_movement':
                // Simulate vehicle movement for demo purposes
                $vehicle_id = (int)$_POST['vehicle_id'];
                $current_lat = (float)$_POST['current_lat'];
                $current_lng = (float)$_POST['current_lng'];
                
                // Add small random movement
                $new_lat = $current_lat + (rand(-100, 100) / 10000);
                $new_lng = $current_lng + (rand(-100, 100) / 10000);
                $new_speed = rand(0, 80);
                
                $query = "UPDATE vehicle_tracking SET 
                         latitude = ?, longitude = ?, speed = ?, last_updated = CURRENT_TIMESTAMP 
                         WHERE vehicle_id = ?";
                
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "dddi", $new_lat, $new_lng, $new_speed, $vehicle_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Vehicle movement simulated!";
                } else {
                    $error_message = "Error simulating movement: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get all vehicles with tracking data
$query = "SELECT v.*, vt.latitude, vt.longitude, vt.speed, vt.status as tracking_status, 
          vt.last_updated, COUNT(b.id) as active_bookings
          FROM vehicles v 
          LEFT JOIN vehicle_tracking vt ON v.id = vt.vehicle_id
          LEFT JOIN bookings b ON v.id = b.vehicle_id AND b.status IN ('confirmed', 'in_progress')
          GROUP BY v.id
          ORDER BY vt.last_updated DESC";

$result = mysqli_query($conn, $query);
$vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Management - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .tracking-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .tracking-card:hover {
            transform: translateY(-2px);
        }
        
        .map-container {
            height: 500px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .vehicle-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #6c757d; }
        .status-maintenance { background-color: #ffc107; }
        .status-offline { background-color: #dc3545; }
        
        .speed-indicator {
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            height: 4px;
            border-radius: 2px;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-satellite-dish"></i> Vehicle Tracking Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrackingModal">
                            <i class="fas fa-plus"></i> Add Tracking Device
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card tracking-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marked-alt"></i> Live Vehicle Map
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="adminTrackingMap" class="map-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card tracking-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-car"></i> Vehicle Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($vehicles as $vehicle): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <img src="<?php echo htmlspecialchars($vehicle['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($vehicle['name']); ?>" 
                                             class="rounded me-3" style="width: 60px; height: 40px; object-fit: cover;">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($vehicle['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></small>
                                        </div>
                                        <span class="vehicle-status status-<?php echo $vehicle['tracking_status'] ?? 'inactive'; ?>"></span>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Speed</small>
                                            <div class="fw-bold"><?php echo $vehicle['speed'] ?? 0; ?> km/h</div>
                                            <div class="speed-indicator"></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Bookings</small>
                                            <div class="fw-bold"><?php echo $vehicle['active_bookings']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($vehicle['latitude'] && $vehicle['longitude']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Location</small>
                                        <div class="fw-bold"><?php echo $vehicle['latitude']; ?>, <?php echo $vehicle['longitude']; ?></div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">Last Updated</small>
                                        <div class="fw-bold"><?php echo $vehicle['last_updated'] ? date('H:i:s', strtotime($vehicle['last_updated'])) : 'Never'; ?></div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="updateVehicleLocation(<?php echo $vehicle['id']; ?>)">
                                            <i class="fas fa-edit"></i> Update Location
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" 
                                                onclick="simulateMovement(<?php echo $vehicle['id']; ?>, <?php echo $vehicle['latitude']; ?>, <?php echo $vehicle['longitude']; ?>)">
                                            <i class="fas fa-random"></i> Simulate Movement
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-warning mt-2">
                                        <small><i class="fas fa-exclamation-triangle"></i> No tracking data available</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Update Location Modal -->
    <div class="modal fade" id="updateLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Vehicle Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_location">
                        <input type="hidden" name="vehicle_id" id="updateVehicleId">
                        
                        <div class="mb-3">
                            <label class="form-label">Vehicle</label>
                            <input type="text" class="form-control" id="updateVehicleName" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="0.00000001" class="form-control" name="latitude" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="0.00000001" class="form-control" name="longitude" required>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-6">
                                <label class="form-label">Speed (km/h)</label>
                                <input type="number" step="0.1" class="form-control" name="speed" value="0" min="0" max="200">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="offline">Offline</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize admin map
        const adminMap = L.map('adminTrackingMap').setView([-9.4438, 147.1803], 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(adminMap);
        
        // Vehicle markers for admin
        const adminVehicleMarkers = {};
        
        <?php foreach ($vehicles as $vehicle): ?>
        <?php if ($vehicle['latitude'] && $vehicle['longitude']): ?>
        const adminMarker<?php echo $vehicle['id']; ?> = L.marker([<?php echo $vehicle['latitude']; ?>, <?php echo $vehicle['longitude']; ?>], {
            icon: L.divIcon({
                className: 'admin-vehicle-marker',
                html: '<i class="fas fa-car" style="color: #007bff; font-size: 24px;"></i>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(adminMap);
        
        adminMarker<?php echo $vehicle['id']; ?>.bindPopup(`
            <div class="text-center">
                <h6><?php echo htmlspecialchars($vehicle['name']); ?></h6>
                <p class="mb-1"><strong>Speed:</strong> <?php echo $vehicle['speed'] ?? 0; ?> km/h</p>
                <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst($vehicle['tracking_status'] ?? 'inactive'); ?></p>
                <p class="mb-1"><strong>Active Bookings:</strong> <?php echo $vehicle['active_bookings']; ?></p>
                <small class="text-muted">Last updated: <?php echo $vehicle['last_updated'] ? date('H:i:s', strtotime($vehicle['last_updated'])) : 'Never'; ?></small>
            </div>
        `);
        
        adminVehicleMarkers[<?php echo $vehicle['id']; ?>] = adminMarker<?php echo $vehicle['id']; ?>;
        <?php endif; ?>
        <?php endforeach; ?>
        
        // Update vehicle location function
        function updateVehicleLocation(vehicleId) {
            const vehicleName = document.querySelector(`[data-vehicle-id="${vehicleId}"] .vehicle-name`).textContent;
            document.getElementById('updateVehicleId').value = vehicleId;
            document.getElementById('updateVehicleName').value = vehicleName;
            
            const modal = new bootstrap.Modal(document.getElementById('updateLocationModal'));
            modal.show();
        }
        
        // Simulate movement function
        function simulateMovement(vehicleId, currentLat, currentLng) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="simulate_movement">
                <input type="hidden" name="vehicle_id" value="${vehicleId}">
                <input type="hidden" name="current_lat" value="${currentLat}">
                <input type="hidden" name="current_lng" value="${currentLng}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 