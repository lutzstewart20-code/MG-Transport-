<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user's bookings with tracking info
$user_id = $_SESSION['user_id'];
$query = "SELECT b.*, v.name as vehicle_name, v.image_url, v.vehicle_type, 
          t.latitude, t.longitude, t.speed, t.status as tracking_status, t.last_updated
          FROM bookings b 
          LEFT JOIN vehicles v ON b.vehicle_id = v.id 
          LEFT JOIN vehicle_tracking t ON v.id = t.vehicle_id 
          WHERE b.user_id = ? AND b.status IN ('confirmed', 'in_progress', 'completed')
          ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$bookings = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Tracking - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .tracking-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .tracking-card:hover {
            transform: translateY(-5px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .tracking-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .vehicle-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .tracking-details {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .real-time-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .speed-indicator {
            background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
            height: 4px;
            border-radius: 2px;
            margin: 0.5rem 0;
        }
        
        .speed-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/MG Logo.jpg" alt="MG Transport Services" class="me-2">
                <span class="d-none d-md-inline">MG Transport Services</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking.php">Book Now</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-bookings.php">My Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tracking.php">Track Vehicle</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="my-bookings.php">My Bookings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-satellite-dish"></i> Real-Time Vehicle Tracking
                </h1>
                
                <div class="tracking-info">
                    <h4><i class="fas fa-info-circle"></i> Tracking Information</h4>
                    <p class="mb-0">Track your hired vehicles in real-time. Location updates every 30 seconds.</p>
                </div>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>No Active Bookings</h5>
                    <p>You don't have any active bookings to track. <a href="booking.php" class="alert-link">Book a vehicle</a> to start tracking.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card tracking-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marked-alt"></i> Live Map
                            <span class="real-time-indicator ms-2"></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="trackingMap" class="map-container"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card tracking-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-car"></i> Active Vehicles
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($bookings as $booking): ?>
                        <div class="tracking-details mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($booking['vehicle_name']); ?>" 
                                     class="rounded me-3" style="width: 60px; height: 40px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($booking['vehicle_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($booking['vehicle_type']); ?></small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Booking ID</small>
                                    <div class="fw-bold">#<?php echo htmlspecialchars($booking['id']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Status</small>
                                    <div>
                                        <span class="badge bg-<?php echo getStatusColor($booking['status']); ?> status-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($booking['latitude'] && $booking['longitude']): ?>
                            <div class="vehicle-info">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Speed</small>
                                        <div class="speed-value"><?php echo $booking['speed'] ?? 0; ?> km/h</div>
                                        <div class="speed-indicator"></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Last Updated</small>
                                        <div class="fw-bold"><?php echo date('H:i:s', strtotime($booking['last_updated'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <small class="text-muted">Location</small>
                                    <div class="fw-bold"><?php echo $booking['latitude']; ?>, <?php echo $booking['longitude']; ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning mt-2">
                                <small><i class="fas fa-exclamation-triangle"></i> GPS signal not available</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map
        const map = L.map('trackingMap').setView([-9.4438, 147.1803], 10); // Papua New Guinea coordinates
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Vehicle markers
        const vehicleMarkers = {};
        
        <?php foreach ($bookings as $booking): ?>
        <?php if ($booking['latitude'] && $booking['longitude']): ?>
        const marker<?php echo $booking['id']; ?> = L.marker([<?php echo $booking['latitude']; ?>, <?php echo $booking['longitude']; ?>], {
            icon: L.divIcon({
                className: 'vehicle-marker',
                html: '<i class="fas fa-car" style="color: #007bff; font-size: 24px;"></i>',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            })
        }).addTo(map);
        
        marker<?php echo $booking['id']; ?>.bindPopup(`
            <div class="text-center">
                <h6><?php echo htmlspecialchars($booking['vehicle_name']); ?></h6>
                <p class="mb-1"><strong>Speed:</strong> <?php echo $booking['speed'] ?? 0; ?> km/h</p>
                <p class="mb-1"><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?></p>
                <small class="text-muted">Last updated: <?php echo date('H:i:s', strtotime($booking['last_updated'])); ?></small>
            </div>
        `);
        
        vehicleMarkers[<?php echo $booking['id']; ?>] = marker<?php echo $booking['id']; ?>;
        <?php endif; ?>
        <?php endforeach; ?>
        
        // Real-time updates
        function updateTracking() {
            fetch('api/tracking-update.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(vehicle => {
                        if (vehicleMarkers[vehicle.booking_id]) {
                            const marker = vehicleMarkers[vehicle.booking_id];
                            marker.setLatLng([vehicle.latitude, vehicle.longitude]);
                            
                            // Update popup content
                            marker.getPopup().setContent(`
                                <div class="text-center">
                                    <h6>${vehicle.vehicle_name}</h6>
                                    <p class="mb-1"><strong>Speed:</strong> ${vehicle.speed} km/h</p>
                                    <p class="mb-1"><strong>Status:</strong> ${vehicle.status}</p>
                                    <small class="text-muted">Last updated: ${vehicle.last_updated}</small>
                                </div>
                            `);
                        }
                    });
                })
                .catch(error => console.error('Error updating tracking:', error));
        }
        
        // Update every 30 seconds
        setInterval(updateTracking, 30000);
        
        // Auto-refresh page every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html> 