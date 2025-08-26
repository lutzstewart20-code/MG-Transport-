<?php
define('SECURE_ACCESS', true);
require_once 'includes/security-middleware.php';

// Get vehicles with current tracking data
$vehicles_query = "SELECT v.id, v.name, v.registration_number, v.image_url,
                   vt.latitude, vt.longitude, vt.speed, vt.status as tracking_status, 
                   vt.fuel_level, vt.battery_level, vt.gps_signal_strength, vt.last_updated,
                   vt.heading, vt.altitude, vt.engine_status,
                   b.id as booking_id, b.start_date, b.end_date,
                   u.first_name as driver_first_name, u.last_name as driver_last_name
                   FROM vehicles v 
                   LEFT JOIN vehicle_tracking vt ON v.id = vt.vehicle_id
                   LEFT JOIN bookings b ON v.current_booking_id = b.id
                   LEFT JOIN users u ON v.current_driver_id = u.id
                   ORDER BY v.name";
$vehicles_result = mysqli_query($conn, $vehicles_query);
if (!$vehicles_result) {
    error_log("Error in vehicles query: " . mysqli_error($conn));
    $vehicles_result = false;
}

// Get tracking statistics
$stats_query = "SELECT 
    COUNT(*) as total_vehicles,
    SUM(CASE WHEN vt.status = 'moving' THEN 1 ELSE 0 END) as moving_vehicles,
    SUM(CASE WHEN vt.status = 'stopped' THEN 1 ELSE 0 END) as stopped_vehicles,
    SUM(CASE WHEN vt.status = 'offline' THEN 1 ELSE 0 END) as offline_vehicles,
    SUM(CASE WHEN vt.gps_signal_strength IN ('excellent', 'good') THEN 1 ELSE 0 END) as good_gps_signal,
    SUM(CASE WHEN vt.fuel_level < 20 THEN 1 ELSE 0 END) as low_fuel_vehicles
    FROM vehicles v 
    LEFT JOIN vehicle_tracking vt ON v.id = vt.vehicle_id";
$stats_result = mysqli_query($conn, $stats_query);
if (!$stats_result) {
    error_log("Error in stats query: " . mysqli_error($conn));
    $stats = ['total_vehicles' => 0, 'moving_vehicles' => 0, 'stopped_vehicles' => 0, 'offline_vehicles' => 0, 'good_gps_signal' => 0, 'low_fuel_vehicles' => 0];
} else {
    $stats = mysqli_fetch_assoc($stats_result);
}

// Get recent alerts
$alerts_query = "SELECT ta.*, v.name as vehicle_name, v.registration_number 
                 FROM tracking_alerts ta 
                 JOIN vehicles v ON ta.vehicle_id = v.id 
                 WHERE ta.is_resolved = FALSE 
                 ORDER BY ta.created_at DESC LIMIT 5";
$alerts_result = mysqli_query($conn, $alerts_query);
if (!$alerts_result) {
    error_log("Error in alerts query: " . mysqli_error($conn));
    $alerts_result = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Vehicle Tracking Dashboard - MG Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .tracking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .vehicle-marker {
            width: 100%;
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-moving { background-color: #28a745; }
        .status-stopped { background-color: #ffc107; }
        .status-idle { background-color: #17a2b8; }
        .status-offline { background-color: #dc3545; }
        .gps-excellent { color: #28a745; }
        .gps-good { color: #17a2b8; }
        .gps-fair { color: #ffc107; }
        .gps-poor { color: #fd7e14; }
        .gps-none { color: #dc3545; }
        .map-container {
            height: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .vehicle-info-panel {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            color: white;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="tracking-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-satellite-dish me-3"></i>PNG GPS Vehicle Tracking Dashboard</h1>
                                         <p class="mb-0">Real-time GPS tracking and monitoring of fleet vehicles across Papua New Guinea Momase & Highlands Regions</p>
                                         <div class="mt-2">
                         <span class="badge bg-light text-dark me-2">Sandaun Province</span>
                         <span class="badge bg-light text-dark me-2">East Sepik Province</span>
                         <span class="badge bg-light text-dark me-2">Madang Province</span>
                         <span class="badge bg-light text-dark me-2">Morobe Province</span>
                         <span class="badge bg-light text-dark me-2">Eastern Highlands</span>
                         <span class="badge bg-light text-dark me-2">Western Highlands</span>
                         <span class="badge bg-light text-dark me-2">Simbu Province</span>
                         <span class="badge bg-light text-dark">Jiwaka Province</span>
                     </div>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn refresh-btn" onclick="refreshTrackingData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Data
                    </button>
                    <div class="mt-2">
                        <small class="text-light">PNG Time: <span id="png-time"></span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt-4">
        <!-- PNG Regional Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                                         <div class="card-header bg-primary text-white">
                         <h5 class="mb-0"><i class="fas fa-flag me-2"></i>Papua New Guinea - Momase & Highlands Regions Fleet Overview</h5>
                     </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-truck fa-2x text-primary mb-2"></i>
                                    <h4><?php echo $stats['total_vehicles'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Vehicles</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-route fa-2x text-success mb-2"></i>
                                    <h4><?php echo $stats['moving_vehicles'] ?? 0; ?></h4>
                                    <small class="text-muted">Moving</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-pause-circle fa-2x text-warning mb-2"></i>
                                    <h4><?php echo $stats['stopped_vehicles'] ?? 0; ?></h4>
                                    <small class="text-muted">Stopped</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-satellite fa-2x text-info mb-2"></i>
                                    <h4><?php echo $stats['good_gps_signal'] ?? 0; ?></h4>
                                    <small class="text-muted">Good GPS Signal</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                    <h4><?php echo $stats['low_fuel_vehicles'] ?? 0; ?></h4>
                                    <small class="text-muted">Low Fuel</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stats-card p-3 text-center">
                                    <i class="fas fa-wifi fa-2x text-secondary mb-2"></i>
                                    <h4><?php echo $stats['offline_vehicles'] ?? 0; ?></h4>
                                    <small class="text-muted">Offline</small>
                                </div>
                            </div>
                        </div>
                        
                                                 <!-- PNG Regional Info -->
                         <div class="row mt-3">
                             <div class="col-md-6">
                                 <div class="alert alert-info">
                                     <h6><i class="fas fa-map me-2"></i>Regional Coverage</h6>
                                     <div class="row text-center">
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-success text-white">
                                                 <strong>Sandaun</strong><br>
                                                 <small>West Sepik</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-info text-white">
                                                 <strong>East Sepik</strong><br>
                                                 <small>Wewak Area</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-warning text-dark">
                                                 <strong>Madang</strong><br>
                                                 <small>Coastal</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-danger text-white">
                                                 <strong>Morobe</strong><br>
                                                 <small>Lae Area</small>
                                             </div>
                                         </div>
                                     </div>
                                     <div class="row text-center mt-2">
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-primary text-white">
                                                 <strong>Eastern Highlands</strong><br>
                                                 <small>Goroka</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-secondary text-white">
                                                 <strong>Western Highlands</strong><br>
                                                 <small>Mount Hagen</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-dark text-white">
                                                 <strong>Simbu</strong><br>
                                                 <small>Kundiawa</small>
                                             </div>
                                         </div>
                                         <div class="col-3">
                                             <div class="border rounded p-2 bg-success text-white">
                                                 <strong>Jiwaka</strong><br>
                                                 <small>Banz</small>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-md-6">
                                 <div class="alert alert-success">
                                     <h6><i class="fas fa-info-circle me-2"></i>PNG Tracking Features</h6>
                                     <ul class="list-unstyled mb-0">
                                         <li><i class="fas fa-check text-success me-2"></i>Momase & Highlands Regions overlay</li>
                                         <li><i class="fas fa-check text-success me-2"></i>Major PNG cities marked</li>
                                         <li><i class="fas fa-check text-success me-2"></i>Province-specific tracking</li>
                                         <li><i class="fas fa-check text-success me-2"></i>Local time display (PNG)</li>
                                         <li><i class="fas fa-check text-success me-2"></i>Highlands mountain routes</li>
                                     </ul>
                                 </div>
                             </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Live GPS Map -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center bg-success text-white">
                                                 <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>PNG Momase & Highlands Regions - Live GPS Tracking Map</h5>
                        <div>
                            <span class="badge bg-light text-dark me-2"><?php echo $stats['moving_vehicles'] ?? 0; ?> Active</span>
                            <span class="badge bg-light text-dark me-2"><?php echo $stats['stopped_vehicles'] ?? 0; ?> Stopped</span>
                            <span class="badge bg-light text-dark"><?php echo $stats['offline_vehicles'] ?? 0; ?> Offline</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="tracking-map" class="map-container"></div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information Panel -->
            <div class="col-md-4">
                <div class="vehicle-info-panel">
                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Vehicle Information</h5>
                    
                    <!-- Recent Alerts -->
                    <div class="mb-4">
                        <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Recent Alerts</h6>
                        <?php if ($alerts_result && mysqli_num_rows($alerts_result) > 0): ?>
                            <?php while ($alert = mysqli_fetch_assoc($alerts_result)): ?>
                                <div class="alert alert-danger alert-sm py-2 mb-2">
                                    <strong><?php echo htmlspecialchars($alert['vehicle_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($alert['message']); ?></small>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted small">No active alerts</p>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mb-4">
                        <h6><i class="fas fa-cogs me-2"></i>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="tracking-alerts.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-bell me-2"></i>View All Alerts
                            </a>
                            <a href="tracking-management.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-cog me-2"></i>Tracking Settings
                            </a>
                        </div>
                    </div>

                    <!-- GPS Status -->
                    <div>
                        <h6><i class="fas fa-satellite me-2"></i>GPS Status</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-signal text-success fa-lg"></i>
                                    <div class="small"><?php echo $stats['good_gps_signal'] ?? 0; ?></div>
                                    <div class="text-muted small">Good Signal</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                                    <div class="small"><?php echo ($stats['total_vehicles'] ?? 0) - ($stats['good_gps_signal'] ?? 0); ?></div>
                                    <div class="text-muted small">Poor Signal</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <i class="fas fa-times-circle text-danger fa-lg"></i>
                                    <div class="small"><?php echo $stats['offline_vehicles'] ?? 0; ?></div>
                                    <div class="text-muted small">Offline</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Status Grid -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Vehicle Status Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($vehicles_result && mysqli_num_rows($vehicles_result) > 0): ?>
                                <?php while ($vehicle = mysqli_fetch_assoc($vehicles_result)): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="status-indicator status-<?php echo $vehicle['tracking_status'] ?? 'offline'; ?>"></span>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($vehicle['name']); ?></h6>
                                                </div>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($vehicle['registration_number']); ?></p>
                                                
                                                <?php if ($vehicle['latitude'] && $vehicle['longitude']): ?>
                                                    <div class="row text-center mb-2">
                                                        <div class="col-4">
                                                            <div class="small text-muted">Speed</div>
                                                            <div class="fw-bold"><?php echo $vehicle['speed'] ?? 0; ?> km/h</div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="small text-muted">Fuel</div>
                                                            <div class="fw-bold"><?php echo $vehicle['fuel_level'] ? $vehicle['fuel_level'] . '%' : 'N/A'; ?></div>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="small text-muted">GPS</div>
                                                            <div class="fw-bold gps-<?php echo $vehicle['gps_signal_strength'] ?? 'none'; ?>">
                                                                <i class="fas fa-satellite"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($vehicle['driver_first_name']): ?>
                                                        <div class="small text-muted mb-1">
                                                            <i class="fas fa-user me-1"></i>
                                                            <?php echo htmlspecialchars($vehicle['driver_first_name'] . ' ' . $vehicle['driver_last_name']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="small text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        Last update: <?php echo $vehicle['last_updated'] ? date('H:i', strtotime($vehicle['last_updated'])) : 'Never'; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted">
                                                        <i class="fas fa-satellite fa-2x mb-2"></i>
                                                        <div>No GPS Data</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12 text-center">
                                    <div class="py-5">
                                        <i class="fas fa-satellite fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Vehicles Found</h5>
                                        <p class="text-muted">Add vehicles to your fleet to start tracking them.</p>
                                        <a href="add-vehicle.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add Vehicle
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize map centered on Papua New Guinea, covering both Momase and Highlands Regions
        const map = L.map('tracking-map').setView([-5.0, 144.0], 6); // Centered to show both regions
        
        // Use OpenStreetMap with PNG-specific styling
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add PNG regional boundaries for both Momase and Highlands
        const momaseRegion = {
            "type": "Feature",
            "properties": {
                "name": "Momase Region",
                "description": "Papua New Guinea Momase Region"
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[
                    [141.0, -2.5], // West Sepik
                    [142.0, -2.5], // Sandaun Province
                    [144.0, -3.0], // East Sepik
                    [145.0, -4.0], // Madang Province
                    [144.5, -5.0], // Morobe Province
                    [143.0, -5.5], // Gulf Province
                    [141.0, -4.5], // Back to West
                    [141.0, -2.5]  // Close polygon
                ]]
            }
        };

        const highlandsRegion = {
            "type": "Feature",
            "properties": {
                "name": "Highlands Region",
                "description": "Papua New Guinea Highlands Region"
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[
                    [143.0, -5.5], // Southern boundary
                    [145.0, -6.0], // Eastern boundary
                    [146.0, -6.5], // Southern Highlands
                    [144.5, -7.0], // Western boundary
                    [143.0, -6.5], // Back to start
                    [143.0, -5.5]  // Close polygon
                ]]
            }
        };

        // Add regional boundary overlays
        L.geoJSON(momaseRegion, {
            style: {
                color: '#ff6b35',
                weight: 3,
                fillColor: '#ff6b35',
                fillOpacity: 0.1
            }
        }).addTo(map).bindPopup('<strong>Momase Region</strong><br>Papua New Guinea<br>Includes: Sandaun, East Sepik, Madang, Morobe');

        L.geoJSON(highlandsRegion, {
            style: {
                color: '#28a745',
                weight: 3,
                fillColor: '#28a745',
                fillOpacity: 0.1
            }
        }).addTo(map).bindPopup('<strong>Highlands Region</strong><br>Papua New Guinea<br>Includes: Eastern Highlands, Western Highlands, Simbu, Jiwaka');

        // Add major PNG cities and landmarks
        const pngLandmarks = [
            {name: "Port Moresby", coords: [-9.4438, 147.1803], type: "capital"},
            {name: "Lae", coords: [-6.7150, 146.9841], type: "city"},
            {name: "Madang", coords: [-5.2250, 145.7850], type: "city"},
            {name: "Wewak", coords: [-3.5833, 143.6667], type: "city"},
            {name: "Vanimo", coords: [-2.6833, 141.3000], type: "city"},
            {name: "Goroka", coords: [-6.0833, 145.3833], type: "city"},
            {name: "Mount Hagen", coords: [-5.8667, 144.2167], type: "city"},
            {name: "Kokopo", coords: [-4.3500, 152.2667], type: "city"}
        ];

        // Add landmark markers
        pngLandmarks.forEach(landmark => {
            const icon = L.divIcon({
                className: 'landmark-marker',
                html: `<i class="fas fa-map-marker-alt" style="color: ${landmark.type === 'capital' ? '#dc3545' : '#17a2b8'}; font-size: 20px;"></i>`,
                iconSize: [20, 20]
            });
            
            L.marker(landmark.coords, {icon: icon})
                .addTo(map)
                .bindPopup(`<strong>${landmark.name}</strong><br>${landmark.type === 'capital' ? 'Capital City' : 'Major City'}<br>Papua New Guinea`);
        });

        // Add PNG provinces information for both regions
        const pngProvinces = [
            // Momase Region Provinces
            {name: "Sandaun (West Sepik)", coords: [141.5, -3.0], color: "#28a745"},
            {name: "East Sepik", coords: [143.5, -3.5], color: "#17a2b8"},
            {name: "Madang", coords: [144.5, -4.5], color: "#ffc107"},
            {name: "Morobe", coords: [144.0, -6.0], color: "#dc3545"},
            // Highlands Region Provinces
            {name: "Eastern Highlands", coords: [145.5, -6.0], color: "#007bff"},
            {name: "Western Highlands", coords: [144.5, -5.5], color: "#6c757d"},
            {name: "Simbu Province", coords: [144.8, -6.2], color: "#343a40"},
            {name: "Jiwaka Province", coords: [144.7, -5.8], color: "#20c997"}
        ];

        pngProvinces.forEach(province => {
            L.circle(province.coords, {
                color: province.color,
                fillColor: province.color,
                fillOpacity: 0.3,
                radius: 50000
            }).addTo(map).bindPopup(`<strong>${province.name}</strong><br>Momase Region, PNG`);
        });

        // Vehicle markers
        const vehicleMarkers = {};
        
        // Function to refresh tracking data
        function refreshTrackingData() {
            location.reload();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshTrackingData, 30000);

        // Update PNG time display
        function updatePNGTime() {
            const pngTime = new Date().toLocaleString("en-US", {
                timeZone: "Pacific/Port_Moresby",
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('png-time').textContent = pngTime;
        }
        
        // Update time every second
        updatePNGTime();
        setInterval(updatePNGTime, 1000);

        // Add vehicle markers to map
        <?php 
        mysqli_data_seek($vehicles_result, 0);
        while ($vehicle = mysqli_fetch_assoc($vehicles_result)): 
            if ($vehicle['latitude'] && $vehicle['longitude']):
        ?>
            const marker<?php echo $vehicle['id']; ?> = L.marker([<?php echo $vehicle['latitude']; ?>, <?php echo $vehicle['longitude']; ?>])
                .addTo(map)
                .bindPopup(`
                    <strong><?php echo addslashes($vehicle['name']); ?></strong><br>
                    Reg: <?php echo addslashes($vehicle['registration_number']); ?><br>
                    Speed: <?php echo $vehicle['speed'] ?? 0; ?> km/h<br>
                    Status: <?php echo ucfirst($vehicle['tracking_status'] ?? 'offline'); ?><br>
                    GPS: <?php echo ucfirst($vehicle['gps_signal_strength'] ?? 'none'); ?><br>
                    Location: PNG Momase Region
                `);
            
            vehicleMarkers[<?php echo $vehicle['id']; ?>] = marker<?php echo $vehicle['id']; ?>;
        <?php 
            endif;
        endwhile; 
        ?>

        // Fit map to show PNG and all markers
        if (Object.keys(vehicleMarkers).length > 0) {
            const group = new L.featureGroup(Object.values(vehicleMarkers));
            map.fitBounds(group.getBounds());
        } else {
            // If no vehicles, focus on both regions
            map.setView([-5.0, 144.0], 6);
        }

        // Add PNG-specific map controls for both regions
        const pngControl = L.control({position: 'topright'});
        pngControl.onAdd = function() {
            const div = L.DomUtil.create('div', 'info legend');
            div.innerHTML = `
                <h4>PNG Regions</h4>
                <div><i class="fas fa-map-marker-alt" style="color: #dc3545;"></i> Capital City</div>
                <div><i class="fas fa-map-marker-alt" style="color: #17a2b8;"></i> Major Cities</div>
                <div style="border: 2px solid #ff6b35; background: rgba(255, 107, 53, 0.1); padding: 5px; margin: 5px 0;">Momase Region Boundary</div>
                <div style="border: 2px solid #28a745; background: rgba(40, 167, 69, 0.1); padding: 5px; margin: 5px 0;">Highlands Region Boundary</div>
                <h6 class="mt-2">Momase Provinces:</h6>
                <div><i class="fas fa-circle" style="color: #28a745;"></i> Sandaun Province</div>
                <div><i class="fas fa-circle" style="color: #17a2b8;"></i> East Sepik Province</div>
                <div><i class="fas fa-circle" style="color: #ffc107;"></i> Madang Province</div>
                <div><i class="fas fa-circle" style="color: #dc3545;"></i> Morobe Province</div>
                <h6 class="mt-2">Highlands Provinces:</h6>
                <div><i class="fas fa-circle" style="color: #007bff;"></i> Eastern Highlands</div>
                <div><i class="fas fa-circle" style="color: #6c757d;"></i> Western Highlands</div>
                <div><i class="fas fa-circle" style="color: #343a40;"></i> Simbu Province</div>
                <div><i class="fas fa-circle" style="color: #20c997;"></i> Jiwaka Province</div>
            `;
            return div;
        };
        pngControl.addTo(map);
    </script>
</body>
</html>
