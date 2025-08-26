<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Get tracking history with filters
$vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$where_conditions = [];
$params = [];
$types = '';

if ($vehicle_id > 0) {
    $where_conditions[] = "th.vehicle_id = ?";
    $params[] = $vehicle_id;
    $types .= 'i';
}

$where_conditions[] = "DATE(th.recorded_at) BETWEEN ? AND ?";
$params[] = $date_from;
$params[] = $date_to;
$types .= 'ss';

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT th.*, v.name as vehicle_name, v.vehicle_type, v.image_url
          FROM tracking_history th
          LEFT JOIN vehicles v ON th.vehicle_id = v.id
          $where_clause
          ORDER BY th.recorded_at DESC
          LIMIT 1000";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tracking_history = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get all vehicles for filter
$vehicles_query = "SELECT id, name, vehicle_type FROM vehicles ORDER BY name";
$vehicles_result = mysqli_query($conn, $vehicles_query);
$vehicles = mysqli_fetch_all($vehicles_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking History - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .history-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .history-card:hover {
            transform: translateY(-2px);
        }
        
        .map-container {
            height: 400px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .tracking-point {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #007bff;
            display: inline-block;
            margin-right: 8px;
        }
        
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
                        <i class="fas fa-history"></i> Tracking History
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" onclick="exportHistory()">
                            <i class="fas fa-download"></i> Export Data
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-filter"></i> Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Vehicle</label>
                                <select class="form-select" name="vehicle_id">
                                    <option value="">All Vehicles</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['id']; ?>" 
                                            <?php echo ($vehicle_id == $vehicle['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vehicle['name']); ?> (<?php echo htmlspecialchars($vehicle['vehicle_type']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="tracking-history.php" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card history-card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marked-alt"></i> Tracking Route Map
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="historyMap" class="map-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card history-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Tracking History
                                </h5>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($tracking_history)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                                    <p>No tracking history found for the selected criteria.</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($tracking_history as $record): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="tracking-point"></span>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($record['vehicle_name']); ?></h6>
                                            <small class="text-muted"><?php echo date('M j, Y H:i:s', strtotime($record['recorded_at'])); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Speed</small>
                                            <div class="fw-bold"><?php echo $record['speed']; ?> km/h</div>
                                            <div class="speed-indicator"></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Location</small>
                                            <div class="fw-bold small"><?php echo $record['latitude']; ?>, <?php echo $record['longitude']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($record['heading']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Heading</small>
                                        <div class="fw-bold"><?php echo $record['heading']; ?>°</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($record['altitude']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Altitude</small>
                                        <div class="fw-bold"><?php echo $record['altitude']; ?> m</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize history map
        const historyMap = L.map('historyMap').setView([-9.4438, 147.1803], 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(historyMap);
        
        // Add tracking points to map
        const trackingPoints = [];
        
        <?php foreach ($tracking_history as $record): ?>
        const point<?php echo $record['id']; ?> = L.marker([<?php echo $record['latitude']; ?>, <?php echo $record['longitude']; ?>], {
            icon: L.divIcon({
                className: 'history-point',
                html: '<i class="fas fa-circle" style="color: #007bff; font-size: 12px;"></i>',
                iconSize: [12, 12],
                iconAnchor: [6, 6]
            })
        }).addTo(historyMap);
        
        point<?php echo $record['id']; ?>.bindPopup(`
            <div class="text-center">
                <h6><?php echo htmlspecialchars($record['vehicle_name']); ?></h6>
                <p class="mb-1"><strong>Speed:</strong> <?php echo $record['speed']; ?> km/h</p>
                <p class="mb-1"><strong>Time:</strong> <?php echo date('H:i:s', strtotime($record['recorded_at'])); ?></p>
                <p class="mb-1"><strong>Date:</strong> <?php echo date('M j, Y', strtotime($record['recorded_at'])); ?></p>
                <?php if ($record['heading']): ?>
                <p class="mb-1"><strong>Heading:</strong> <?php echo $record['heading']; ?>°</p>
                <?php endif; ?>
                <?php if ($record['altitude']): ?>
                <p class="mb-1"><strong>Altitude:</strong> <?php echo $record['altitude']; ?> m</p>
                <?php endif; ?>
            </div>
        `);
        
        trackingPoints.push(point<?php echo $record['id']; ?>);
        <?php endforeach; ?>
        
        // Fit map to show all points
        if (trackingPoints.length > 0) {
            const group = new L.featureGroup(trackingPoints);
            historyMap.fitBounds(group.getBounds().pad(0.1));
        }
        
        // Export history function
        function exportHistory() {
            const currentUrl = new URL(window.location.href);
            const exportUrl = currentUrl.origin + currentUrl.pathname + '?export=1' + currentUrl.search;
            window.open(exportUrl, '_blank');
        }
    </script>
</body>
</html> 