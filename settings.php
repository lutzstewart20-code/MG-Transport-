<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin($conn)) {
    header('Location: ../login.php');
    exit();
}

// Ensure system_settings table exists with proper structure
$check_table = "SHOW TABLES LIKE 'system_settings'";
$table_exists = mysqli_query($conn, $check_table);

if (mysqli_num_rows($table_exists) == 0) {
    // Create table if it doesn't exist
    $create_table = "CREATE TABLE system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_name VARCHAR(255) DEFAULT 'MG Transport Services',
        company_email VARCHAR(255) DEFAULT 'info@mgtransport.com',
        company_phone VARCHAR(50) DEFAULT '+675 1234 5678',
        company_address TEXT DEFAULT 'Torokina Estate, Madang Province, North Coast, Papua New Guinea',
        gst_rate DECIMAL(5,2) DEFAULT 10.00,
        smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com',
        smtp_port VARCHAR(10) DEFAULT '587',
        smtp_username VARCHAR(255) DEFAULT 'noreply@mgtransport.com',
        smtp_password VARCHAR(255) DEFAULT '',
        smtp_encryption VARCHAR(10) DEFAULT 'tls',
        maintenance_mode TINYINT(1) DEFAULT 0,
        max_booking_days INT DEFAULT 30,
        currency VARCHAR(10) DEFAULT 'PGK',
        timezone VARCHAR(100) DEFAULT 'Pacific/Port_Moresby',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!mysqli_query($conn, $create_table)) {
        die("Error creating system_settings table: " . mysqli_error($conn));
    }
} else {
    // Check if all required columns exist
    $check_columns = "SHOW COLUMNS FROM system_settings LIKE 'company_name'";
    $column_exists = mysqli_query($conn, $check_columns);
    
    if (mysqli_num_rows($column_exists) == 0) {
        // Drop and recreate table if structure is wrong
        if (!mysqli_query($conn, "DROP TABLE system_settings")) {
            die("Error dropping system_settings table: " . mysqli_error($conn));
        }
        $create_table = "CREATE TABLE system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            company_name VARCHAR(255) DEFAULT 'MG Transport Services',
            company_email VARCHAR(255) DEFAULT 'info@mgtransport.com',
            company_phone VARCHAR(50) DEFAULT '+675 1234 5678',
            company_address TEXT DEFAULT 'Torokina Estate, Madang Province, North Coast, Papua New Guinea',
            gst_rate DECIMAL(5,2) DEFAULT 10.00,
            smtp_host VARCHAR(255) DEFAULT 'smtp.gmail.com',
            smtp_port VARCHAR(10) DEFAULT '587',
            smtp_username VARCHAR(255) DEFAULT 'noreply@mgtransport.com',
            smtp_password VARCHAR(255) DEFAULT '',
            smtp_encryption VARCHAR(10) DEFAULT 'tls',
            maintenance_mode TINYINT(1) DEFAULT 0,
            max_booking_days INT DEFAULT 30,
            currency VARCHAR(10) DEFAULT 'PGK',
            timezone VARCHAR(100) DEFAULT 'Pacific/Port_Moresby',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        if (!mysqli_query($conn, $create_table)) {
            die("Error recreating system_settings table: " . mysqli_error($conn));
        }
    }
}

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_company':
                $company_name = sanitizeInput($_POST['company_name']);
                $company_email = sanitizeInput($_POST['company_email']);
                $company_phone = sanitizeInput($_POST['company_phone']);
                $company_address = sanitizeInput($_POST['company_address']);
                $gst_rate = sanitizeInput($_POST['gst_rate']);
                
                if (empty($company_name) || empty($company_email)) {
                    $message = 'Please fill in all required fields.';
                    $message_type = 'danger';
                } else {
                    // Check if settings record exists, if not create it
                    $check_query = "SELECT id FROM system_settings WHERE id = 1";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) == 0) {
                        // Insert default settings first
                        $insert_query = "INSERT INTO system_settings (id, company_name, company_email, company_phone, 
                                       company_address, gst_rate, smtp_host, smtp_port, smtp_username, 
                                       smtp_password, smtp_encryption, maintenance_mode, max_booking_days, 
                                       currency, timezone) VALUES 
                                       (1, 'MG Transport Services', 'info@mgtransport.com', '+675 1234 5678', 
                                       'Torokina Estate, Madang Province, North Coast, Papua New Guinea', 
                                       10.0, 'smtp.gmail.com', '587', 'noreply@mgtransport.com', 
                                       '', 'tls', 0, 30, 'PGK', 'Pacific/Port_Moresby')";
                        if (!mysqli_query($conn, $insert_query)) {
                            $message = 'Error creating default settings: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                    
                    // Update company settings in database
                    $update_query = "UPDATE system_settings SET 
                                   company_name = ?, company_email = ?, company_phone = ?, 
                                   company_address = ?, gst_rate = ? WHERE id = 1";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "ssssd", $company_name, $company_email, $company_phone, $company_address, $gst_rate);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Company settings updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating company settings.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_email':
                $smtp_host = sanitizeInput($_POST['smtp_host']);
                $smtp_port = sanitizeInput($_POST['smtp_port']);
                $smtp_username = sanitizeInput($_POST['smtp_username']);
                $smtp_password = sanitizeInput($_POST['smtp_password']);
                $smtp_encryption = sanitizeInput($_POST['smtp_encryption']);
                
                if (empty($smtp_host) || empty($smtp_port) || empty($smtp_username)) {
                    $message = 'Please fill in all required SMTP fields.';
                    $message_type = 'danger';
                } else {
                    // Check if settings record exists, if not create it
                    $check_query = "SELECT id FROM system_settings WHERE id = 1";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) == 0) {
                        // Insert default settings first
                        $insert_query = "INSERT INTO system_settings (id, company_name, company_email, company_phone, 
                                       company_address, gst_rate, smtp_host, smtp_port, smtp_username, 
                                       smtp_password, smtp_encryption, maintenance_mode, max_booking_days, 
                                       currency, timezone) VALUES 
                                       (1, 'MG Transport Services', 'info@mgtransport.com', '+675 1234 5678', 
                                       'Torokina Estate, Madang Province, North Coast, Papua New Guinea', 
                                       10.0, 'smtp.gmail.com', '587', 'noreply@mgtransport.com', 
                                       '', 'tls', 0, 30, 'PGK', 'Pacific/Port_Moresby')";
                        if (!mysqli_query($conn, $insert_query)) {
                            $message = 'Error creating default settings: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                    
                    // Update email settings
                    $update_query = "UPDATE system_settings SET 
                                   smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                                   smtp_password = ?, smtp_encryption = ? WHERE id = 1";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "sssss", $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_encryption);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Email settings updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating email settings.';
                        $message_type = 'danger';
                    }
                }
                break;
                
            case 'update_system':
                $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
                $max_booking_days = sanitizeInput($_POST['max_booking_days']);
                $currency = sanitizeInput($_POST['currency']);
                $timezone = sanitizeInput($_POST['timezone']);
                
                if (empty($max_booking_days) || empty($currency)) {
                    $message = 'Please fill in all required system fields.';
                    $message_type = 'danger';
                } else {
                    // Check if settings record exists, if not create it
                    $check_query = "SELECT id FROM system_settings WHERE id = 1";
                    $check_result = mysqli_query($conn, $check_query);
                    
                    if (mysqli_num_rows($check_result) == 0) {
                        // Insert default settings first
                        $insert_query = "INSERT INTO system_settings (id, company_name, company_email, company_phone, 
                                       company_address, gst_rate, smtp_host, smtp_port, smtp_username, 
                                       smtp_password, smtp_encryption, maintenance_mode, max_booking_days, 
                                       currency, timezone) VALUES 
                                       (1, 'MG Transport Services', 'info@mgtransport.com', '+675 1234 5678', 
                                       'Torokina Estate, Madang Province, North Coast, Papua New Guinea', 
                                       10.0, 'smtp.gmail.com', '587', 'noreply@mgtransport.com', 
                                       '', 'tls', 0, 30, 'PGK', 'Pacific/Port_Moresby')";
                        if (!mysqli_query($conn, $insert_query)) {
                            $message = 'Error creating default settings: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                    
                    // Update system settings
                    $update_query = "UPDATE system_settings SET 
                                   maintenance_mode = ?, max_booking_days = ?, 
                                   currency = ?, timezone = ? WHERE id = 1";
                    $stmt = mysqli_prepare($conn, $update_query);
                    mysqli_stmt_bind_param($stmt, "iiss", $maintenance_mode, $max_booking_days, $currency, $timezone);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'System settings updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating system settings.';
                        $message_type = 'danger';
                    }
                }
                break;
        }
    }
}

// Get current settings
$settings_query = "SELECT * FROM system_settings WHERE id = 1";
$settings_result = mysqli_query($conn, $settings_query);
$settings = mysqli_fetch_assoc($settings_result);

// If no settings exist, create default settings
if (!$settings) {
    // Insert default settings
    $default_settings = "INSERT INTO system_settings (id, company_name, company_email, company_phone, 
                        company_address, gst_rate, smtp_host, smtp_port, smtp_username, 
                        smtp_password, smtp_encryption, maintenance_mode, max_booking_days, 
                        currency, timezone) VALUES 
                        (1, 'MG Transport Services', 'info@mgtransport.com', '+675 1234 5678', 
                        'Torokina Estate, Madang Province, North Coast, Papua New Guinea', 
                        10.0, 'smtp.gmail.com', '587', 'noreply@mgtransport.com', 
                        '', 'tls', 0, 30, 'PGK', 'Pacific/Port_Moresby')";
    mysqli_query($conn, $default_settings);
    $settings = mysqli_fetch_assoc(mysqli_query($conn, $settings_query));
}

// Ensure all required keys exist in settings array
$default_settings_array = [
    'company_name' => 'MG Transport Services',
    'company_email' => 'info@mgtransport.com',
    'company_phone' => '+675 1234 5678',
    'company_address' => 'Torokina Estate, Madang Province, North Coast, Papua New Guinea',
    'gst_rate' => 10.0,
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

// Merge with existing settings, using defaults for missing keys
$settings = array_merge($default_settings_array, $settings ?: []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - MG Transport Services Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.15rem rgba(251, 191, 36, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../assets/images/MG Logo.jpg" alt="MG Transport Services">
                <span class="ms-2">MG Transport Services Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bookings.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="vehicles.php">Vehicles</a>
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
                        <a class="nav-link active" href="settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cog text-primary"></i> System Settings</h2>
        </div>

        <!-- Display Messages -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Company Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_company">
                            
                                                         <div class="mb-3">
                                 <label for="company_name" class="form-label">Company Name *</label>
                                 <input type="text" class="form-control" id="company_name" name="company_name" 
                                        value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="company_email" class="form-label">Company Email *</label>
                                 <input type="email" class="form-control" id="company_email" name="company_email" 
                                        value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="company_phone" class="form-label">Company Phone</label>
                                 <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                        value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>">
                             </div>
                             
                             <div class="mb-3">
                                 <label for="company_address" class="form-label">Company Address</label>
                                 <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="gst_rate" class="form-label">GST Rate (%)</label>
                                 <input type="number" class="form-control" id="gst_rate" name="gst_rate" 
                                        value="<?php echo htmlspecialchars($settings['gst_rate'] ?? ''); ?>" step="0.1" min="0" max="100">
                             </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Company Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_email">
                            
                                                         <div class="mb-3">
                                 <label for="smtp_host" class="form-label">SMTP Host *</label>
                                 <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                        value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="smtp_port" class="form-label">SMTP Port *</label>
                                 <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                        value="<?php echo htmlspecialchars($settings['smtp_port'] ?? ''); ?>" required>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="smtp_username" class="form-label">SMTP Username *</label>
                                 <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                        value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                             </div>
                             
                             <div class="mb-3">
                                 <label for="smtp_password" class="form-label">SMTP Password</label>
                                 <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                        value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                 <small class="text-muted">Leave blank to keep current password</small>
                             </div>
                            
                            <div class="mb-3">
                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Update Email Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        Maintenance Mode
                                    </label>
                                </div>
                                <small class="text-muted">When enabled, only administrators can access the system</small>
                            </div>
                            
                                                         <div class="mb-3">
                                 <label for="max_booking_days" class="form-label">Maximum Booking Days *</label>
                                 <input type="number" class="form-control" id="max_booking_days" name="max_booking_days" 
                                        value="<?php echo htmlspecialchars($settings['max_booking_days'] ?? ''); ?>" required>
                             </div>
                            
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency *</label>
                                <select class="form-select" id="currency" name="currency" required>
                                    <option value="PGK" <?php echo $settings['currency'] === 'PGK' ? 'selected' : ''; ?>>PGK (Papua New Guinea Kina)</option>
                                    <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                    <option value="AUD" <?php echo $settings['currency'] === 'AUD' ? 'selected' : ''; ?>>AUD (Australian Dollar)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="Pacific/Port_Moresby" <?php echo $settings['timezone'] === 'Pacific/Port_Moresby' ? 'selected' : ''; ?>>Pacific/Port_Moresby</option>
                                    <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="Australia/Sydney" <?php echo $settings['timezone'] === 'Australia/Sydney' ? 'selected' : ''; ?>>Australia/Sydney</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-save me-2"></i>Update System Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>PHP Version</h6>
                                <p class="text-muted"><?php echo phpversion(); ?></p>
                                
                                <h6>MySQL Version</h6>
                                <p class="text-muted"><?php echo mysqli_get_server_info($conn); ?></p>
                                
                                <h6>Server Software</h6>
                                <p class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Upload Max Size</h6>
                                <p class="text-muted"><?php echo ini_get('upload_max_filesize'); ?></p>
                                
                                <h6>Memory Limit</h6>
                                <p class="text-muted"><?php echo ini_get('memory_limit'); ?></p>
                                
                                <h6>Max Execution Time</h6>
                                <p class="text-muted"><?php echo ini_get('max_execution_time'); ?> seconds</p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-grid gap-2">
                            
                            <a href="../reset_database.php" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-database me-2"></i>Reset Database
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 