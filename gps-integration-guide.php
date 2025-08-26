<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isAdmin($conn)) {
    redirectWithMessage('../login.php', 'Access denied. Admin privileges required.', 'error');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Integration Guide - MG Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .guide-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .step-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            margin-bottom: 1.5rem;
        }
        .step-card:hover {
            transform: translateY(-5px);
        }
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .device-example {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="guide-header">
        <div class="container">
            <h1><i class="fas fa-satellite-dish me-3"></i>PNG GPS Device Integration Guide</h1>
            <p class="mb-0">Learn how to connect GPS tracking devices to your fleet vehicles in Papua New Guinea</p>
                         <div class="mt-2">
                 <span class="badge bg-light text-dark me-2">Momase Region</span>
                 <span class="badge bg-light text-dark me-2">Highlands Region</span>
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
    </div>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Integration Steps -->
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-ol me-2"></i>Integration Steps</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-4">
                            <div class="step-number">1</div>
                            <div>
                                <h6>Install GPS Device</h6>
                                <p class="text-muted">Mount the GPS tracking device in your vehicle, typically under the dashboard or in the engine compartment.</p>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Recommended:</strong> Professional installation ensures proper power connection and GPS signal reception.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-4">
                            <div class="step-number">2</div>
                            <div>
                                <h6>Configure Device Settings</h6>
                                <p class="text-muted">Set up the GPS device with your tracking server details and update frequency.</p>
                                <div class="code-block">
                                    <strong>Server URL:</strong> <?php echo $_SERVER['HTTP_HOST']; ?>/admin/api/update-location.php<br>
                                    <strong>Update Frequency:</strong> 30 seconds (recommended)<br>
                                    <strong>Data Format:</strong> JSON
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start mb-4">
                            <div class="step-number">3</div>
                            <div>
                                <h6>Test Connection</h6>
                                <p class="text-muted">Verify that the GPS device is successfully sending data to the tracking system.</p>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    You can monitor real-time data on the <a href="tracking-dashboard.php" class="alert-link">GPS Tracking Dashboard</a>.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <div class="step-number">4</div>
                            <div>
                                <h6>Monitor and Maintain</h6>
                                <p class="text-muted">Regularly check device status, battery levels, and GPS signal strength.</p>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Set up alerts for low battery, poor GPS signal, or device offline status.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Documentation -->
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i>API Endpoint Documentation</h5>
                    </div>
                    <div class="card-body">
                        <h6>Update Vehicle Location</h6>
                        <p class="text-muted">Send GPS data to update vehicle location and status.</p>
                        
                        <div class="code-block">
                            <strong>Endpoint:</strong> POST /admin/api/update-location.php<br>
                            <strong>Content-Type:</strong> application/json<br><br>
                            <strong>Required Fields:</strong><br>
                            • vehicle_id (integer)<br>
                            • latitude (decimal)<br>
                            • longitude (decimal)<br><br>
                            <strong>Optional Fields:</strong><br>
                            • speed (decimal, km/h)<br>
                            • status (string: moving/stopped/idle/offline)<br>
                            • fuel_level (decimal, 0-100%)<br>
                            • heading (decimal, degrees)<br>
                            • altitude (decimal, meters)<br>
                            • gps_signal_strength (string: excellent/good/fair/poor/none)<br>
                            • battery_level (decimal, 0-100%)<br>
                            • engine_status (string: running/stopped/maintenance)
                        </div>

                        <h6 class="mt-3">Example Request</h6>
                        <div class="code-block">
{<br>
&nbsp;&nbsp;"vehicle_id": 1,<br>
&nbsp;&nbsp;"latitude": -5.1477,<br>
&nbsp;&nbsp;"longitude": 145.7899,<br>
&nbsp;&nbsp;"speed": 45.5,<br>
&nbsp;&nbsp;"status": "moving",<br>
&nbsp;&nbsp;"fuel_level": 75.0,<br>
&nbsp;&nbsp;"gps_signal_strength": "excellent",<br>
&nbsp;&nbsp;"battery_level": 95.0,<br>
&nbsp;&nbsp;"engine_status": "running"<br>
}
                        </div>

                        <h6 class="mt-3">Response Format</h6>
                        <div class="code-block">
{<br>
&nbsp;&nbsp;"success": true,<br>
&nbsp;&nbsp;"message": "Location updated successfully",<br>
&nbsp;&nbsp;"data": {<br>
&nbsp;&nbsp;&nbsp;&nbsp;"vehicle_id": 1,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"latitude": -5.1477,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"longitude": 145.7899,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"speed": 45.5,<br>
&nbsp;&nbsp;&nbsp;&nbsp;"status": "moving",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"gps_signal_strength": "excellent",<br>
&nbsp;&nbsp;&nbsp;&nbsp;"alerts_generated": []<br>
&nbsp;&nbsp;}<br>
}
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Supported GPS Devices -->
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-microchip me-2"></i>Supported GPS Devices</h5>
                    </div>
                    <div class="card-body">
                        <div class="device-example">
                            <h6><i class="fas fa-satellite me-2"></i>GPS Trackers</h6>
                            <ul class="list-unstyled">
                                <li>• OBD-II GPS Trackers</li>
                                <li>• Hardwired GPS Units</li>
                                <li>• Battery-powered Trackers</li>
                                <li>• Fleet Management Devices</li>
                            </ul>
                        </div>

                        <div class="device-example">
                            <h6><i class="fas fa-mobile-alt me-2"></i>Mobile Apps</h6>
                            <ul class="list-unstyled">
                                <li>• Driver Mobile Apps</li>
                                <li>• GPS-enabled Phones</li>
                                <li>• Tablet-based Solutions</li>
                            </ul>
                        </div>

                        <div class="device-example">
                            <h6><i class="fas fa-car me-2"></i>Vehicle Integration</h6>
                            <ul class="list-unstyled">
                                <li>• CAN Bus Integration</li>
                                <li>• Engine Diagnostics</li>
                                <li>• Fuel Monitoring</li>
                                <li>• Temperature Sensors</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="tracking-dashboard.php" class="btn btn-primary">
                                <i class="fas fa-map-marked-alt me-2"></i>View GPS Dashboard
                            </a>
                            <a href="tracking-alerts.php" class="btn btn-outline-warning">
                                <i class="fas fa-bell me-2"></i>Check Alerts
                            </a>
                            <a href="tracking-management.php" class="btn btn-outline-info">
                                <i class="fas fa-cog me-2"></i>Tracking Settings
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Support Information -->
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Need Help?</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">If you encounter issues with GPS integration:</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Check device power connection</li>
                            <li><i class="fas fa-check text-success me-2"></i>Verify GPS signal reception</li>
                            <li><i class="fas fa-check text-success me-2"></i>Confirm API endpoint URL</li>
                            <li><i class="fas fa-check text-success me-2"></i>Test with sample data</li>
                        </ul>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tip:</strong> Start with a single vehicle for testing before deploying to your entire fleet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
