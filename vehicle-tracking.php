<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}

// Get tracked vehicles
$query = "SELECT v.*, 
          COUNT(th.id) as tracking_points,
          MAX(th.recorded_at) as last_tracking
          FROM vehicles v 
          LEFT JOIN vehicle_tracking_history th ON v.id = th.vehicle_id 
          WHERE v.is_tracked = TRUE 
          GROUP BY v.id 
          ORDER BY v.name";
$result = mysqli_query($conn, $query);
$tracked_vehicles = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get active tracking sessions
$sessions_query = "SELECT ts.*, v.name as vehicle_name, v.registration_number,
                   b.pickup_date, b.return_date, u.name as customer_name
                   FROM tracking_sessions ts
                   JOIN vehicles v ON ts.vehicle_id = v.id
                   LEFT JOIN bookings b ON ts.booking_id = b.id
                   LEFT JOIN users u ON b.user_id = u.id
                   WHERE ts.status = 'active'
                   ORDER BY ts.start_time DESC";
$sessions_result = mysqli_query($conn, $sessions_query);
$active_sessions = mysqli_fetch_all($sessions_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Tracking - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #tracking-map {
            height: 500px;
            width: 100%;
            border-radius: 10px;
        }
        .vehicle-card {
            transition: transform 0.2s;
        }
        .vehicle-card:hover {
            transform: translateY(-2px);
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-active { background-color: #28a745; }
        .status-inactive { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2">
                <span class="d-none d-md-inline">MG Transport Services Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="vehicle-tracking.php">GPS Tracking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">Maintenance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">Settings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-satellite-dish me-2"></i>Vehicle GPS Tracking
                    </h1>
                    <div>
                        <button class="btn btn-primary" onclick="refreshTracking()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                        <button class="btn btn-success" onclick="startTrackingSession()">
                            <i class="fas fa-play me-2"></i>Start Session
                        </button>
                    </div>
                </div>
                <?php displayMessage(); ?>
            </div>
        </div>

        <!-- Real-time Map -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marked-alt me-2"></i>Live Vehicle Locations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="tracking-map"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Tracked Vehicles</h6>
                                <h3><?php echo count($tracked_vehicles); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-satellite fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Active Sessions</h6>
                                <h3><?php echo count($active_sessions); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-play-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Tracking Points</h6>
                                <h3><?php echo array_sum(array_column($tracked_vehicles, 'tracking_points')); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-map-pin fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Last Update</h6>
                                <h6><?php echo date('H:i:s'); ?></h6>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracked Vehicles -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-car me-2"></i>Tracked Vehicles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($tracked_vehicles as $vehicle): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card vehicle-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title"><?php echo htmlspecialchars($vehicle['name']); ?></h6>
                                                    <p class="text-muted small mb-2">
                                                        <?php echo htmlspecialchars($vehicle['registration_number']); ?>
                                                    </p>
                                                    <div class="mb-2">
                                                        <?php 
                                                        $status_class = '';
                                                        $status_text = '';
                                                        if ($vehicle['last_location_update']) {
                                                            $last_update = new DateTime($vehicle['last_location_update']);
                                                            $now = new DateTime();
                                                            $diff = $now->diff($last_update);
                                                            
                                                            if ($diff->i < 5) {
                                                                $status_class = 'status-active';
                                                                $status_text = 'Active';
                                                            } elseif ($diff->i < 30) {
                                                                $status_class = 'status-warning';
                                                                $status_text = 'Warning';
                                                            } else {
                                                                $status_class = 'status-inactive';
                                                                $status_text = 'Inactive';
                                                            }
                                                        } else {
                                                            $status_class = 'status-inactive';
                                                            $status_text = 'No Data';
                                                        }
                                                        ?>
                                                        <span class="status-indicator <?php echo $status_class; ?>"></span>
                                                        <small><?php echo $status_text; ?></small>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">
                                                        <?php echo $vehicle['tracking_points']; ?> points
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php 
                                                        if ($vehicle['last_location_update']) {
                                                            echo date('M j, H:i', strtotime($vehicle['last_location_update']));
                                                        } else {
                                                            echo 'Never';
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php if ($vehicle['last_latitude'] && $vehicle['last_longitude']): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo number_format($vehicle['last_latitude'], 6); ?>, 
                                                        <?php echo number_format($vehicle['last_longitude'], 6); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <div class="btn-group w-100" role="group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewVehicleHistory(<?php echo $vehicle['id']; ?>)">
                                                    <i class="fas fa-history"></i> History
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" 
                                                        onclick="viewOnMap(<?php echo $vehicle['id']; ?>)">
                                                    <i class="fas fa-map"></i> Map
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Sessions -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-play-circle me-2"></i>Active Tracking Sessions
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_sessions)): ?>
                            <p class="text-muted text-center">No active tracking sessions</p>
                        <?php else: ?>
                            <?php foreach ($active_sessions as $session): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <h6><?php echo htmlspecialchars($session['vehicle_name']); ?></h6>
                                    <p class="text-muted small mb-1">
                                        Session started: <?php echo date('M j, H:i', strtotime($session['start_time'])); ?>
                                    </p>
                                    <?php if ($session['customer_name']): ?>
                                        <p class="text-muted small mb-1">
                                            Customer: <?php echo htmlspecialchars($session['customer_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="endTrackingSession(<?php echo $session['id']; ?>)">
                                        <i class="fas fa-stop"></i> End Session
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('tracking-map').setView([-9.4438, 147.1803], 10); // Papua New Guinea coordinates
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Vehicle markers
        const vehicleMarkers = {};
        
        // Load vehicle locations
        function loadVehicleLocations() {
            fetch('api/get_vehicle_locations.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(vehicle => {
                        if (vehicle.latitude && vehicle.longitude) {
                            const marker = L.marker([vehicle.latitude, vehicle.longitude])
                                .bindPopup(`
                                    <strong>${vehicle.name}</strong><br>
                                    ${vehicle.registration_number}<br>
                                    Last update: ${vehicle.last_location_update}
                                `)
                                .addTo(map);
                            
                            vehicleMarkers[vehicle.id] = marker;
                        }
                    });
                })
                .catch(error => console.error('Error loading vehicle locations:', error));
        }

        // Refresh tracking data
        function refreshTracking() {
            location.reload();
        }

        // View vehicle history
        function viewVehicleHistory(vehicleId) {
            window.open(`vehicle-tracking-history.php?vehicle_id=${vehicleId}`, '_blank');
        }

        // View vehicle on map
        function viewOnMap(vehicleId) {
            if (vehicleMarkers[vehicleId]) {
                map.setView(vehicleMarkers[vehicleId].getLatLng(), 15);
                vehicleMarkers[vehicleId].openPopup();
            }
        }

        // Start tracking session
        function startTrackingSession() {
            // Implementation for starting tracking session
            alert('Start tracking session functionality will be implemented');
        }

        // End tracking session
        function endTrackingSession(sessionId) {
            if (confirm('Are you sure you want to end this tracking session?')) {
                fetch('api/end_tracking_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ session_id: sessionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error ending session: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(loadVehicleLocations, 30000);

        // Initial load
        loadVehicleLocations();
    </script>
</body>
</html> 