<?php
// Database configuration for XAMPP
$host = 'localhost';
$dbname = 'mg_transport';
$username = 'root';
$password = '';

// First, connect to MySQL server without specifying a database
$conn = mysqli_connect($host, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");

// Create database if it doesn't exist
$create_db_query = "CREATE DATABASE IF NOT EXISTS $dbname";
if (!mysqli_query($conn, $create_db_query)) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select the database
if (!mysqli_select_db($conn, $dbname)) {
    die("Error selecting database: " . mysqli_error($conn));
}

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('customer', 'admin', 'super_admin') DEFAULT 'customer',
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        phone VARCHAR(20),
        address TEXT,
        user_photo VARCHAR(255),
        driver_license_photo VARCHAR(255),
        status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        model VARCHAR(100),
        year INT,
        registration_number VARCHAR(20) UNIQUE,
        vehicle_type ENUM('land_cruiser_78_series', 'land_cruiser_79_series', 'land_cruiser_10_seater', 'land_cruiser_open_back', 'land_cruiser_4_door', 'land_cruiser_5_door', 'hilux', 'harrier', 'bus', 'sedan', 'suv', 'truck', 'van') NOT NULL,
        description TEXT,
        rate_per_day DECIMAL(10,2) NOT NULL,
        status ENUM('available', 'booked', 'maintenance', 'out_of_service') DEFAULT 'available',
        image_url VARCHAR(255),
        seats INT,
        fuel_type ENUM('petrol', 'diesel', 'electric', 'hybrid'),
        transmission ENUM('manual', 'automatic'),
        insurance_expiry DATE,
        registration_expiry DATE,
        last_service_date DATE,
        next_service_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        vehicle_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_days INT NOT NULL,
        rate_per_day DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        gst_amount DECIMAL(10,2) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'paid', 'pending_verification', 'failed', 'refunded') DEFAULT 'pending',
        payment_method ENUM('bank_transfer', 'sms_payment', 'online_payment', 'cash') DEFAULT NULL,
        payment_details TEXT,
        pickup_location VARCHAR(255),
        dropoff_location VARCHAR(255),
        special_requests TEXT,
        driver_license_path VARCHAR(255),
        payment_receipt_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS maintenance_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehicle_id INT NOT NULL,
        service_type ENUM('regular', 'major', 'emergency') NOT NULL,
        scheduled_date DATE NOT NULL,
        completed_date DATE NULL,
        description TEXT,
        cost DECIMAL(10,2),
        mechanic_name VARCHAR(100),
        status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        invoice_number VARCHAR(50) UNIQUE NOT NULL,
        issue_date DATE NOT NULL,
        due_date DATE NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        gst_amount DECIMAL(10,2) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        related_id INT NULL,
        related_type VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    // Add missing columns to existing notifications table if they don't exist
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS related_id INT NULL",
    "ALTER TABLE notifications ADD COLUMN IF NOT EXISTS related_type VARCHAR(50) NULL",
    
    "CREATE TABLE IF NOT EXISTS payment_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        payment_method ENUM('bsp_online', 'sms_banking', 'manual_transfer', 'cash') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_data TEXT,
        transaction_id VARCHAR(100),
        status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS payment_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        transaction_id VARCHAR(100) NOT NULL,
        amount_paid DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        reference_number VARCHAR(100),
        receipt_file VARCHAR(255) NOT NULL,
        notes TEXT,
        status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        verified_by INT NULL,
        verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS verification_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        booking_id INT NOT NULL,
        code VARCHAR(10) NOT NULL,
        purpose ENUM('bank_transfer', 'payment_confirmation', 'account_verification') NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        INDEX idx_code_phone (code, phone_number),
        INDEX idx_expires (expires_at)
    )",
    
    "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
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
        bsp_merchant_id VARCHAR(100) DEFAULT 'MG_TRANSPORT_001',
        bsp_api_key VARCHAR(255) DEFAULT '',
        bsp_api_url VARCHAR(255) DEFAULT 'https://api.bsp.com.pg/payment',
        whatsapp_number VARCHAR(50) DEFAULT '+675 1234 5678',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS vehicle_agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        vehicle_id INT NOT NULL,
        organization_company VARCHAR(255) NOT NULL,
        business_address TEXT NOT NULL,
        contact_name VARCHAR(255) NOT NULL,
        telephone_email VARCHAR(255) NOT NULL,
        position VARCHAR(100) NOT NULL,
        division_branch_section VARCHAR(100) NOT NULL,
        vehicle_registration VARCHAR(50) NOT NULL,
        vehicle_make_type VARCHAR(100) NOT NULL,
        vehicle_model VARCHAR(100) NOT NULL,
        vehicle_colour VARCHAR(50) NOT NULL,
        vehicle_mileage VARCHAR(50) NOT NULL,
        pickup_date DATE NOT NULL,
        return_date DATE NOT NULL,
        pickup_time TIME NOT NULL,
        dropoff_time TIME NOT NULL,
        number_of_days INT NOT NULL,
        agreement_status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
        admin_notes TEXT,
        admin_approved_by INT NULL,
        admin_approved_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_approved_by) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        payment_method ENUM('bank_transfer', 'sms_payment', 'online_payment', 'cash') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reference_number VARCHAR(100) NOT NULL,
        payment_date DATE NOT NULL,
        receipt_file VARCHAR(255) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        admin_notes TEXT,
        processed_by INT NULL,
        processed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
    )"
];

// Execute table creation queries
foreach ($tables as $table_query) {
    if (!mysqli_query($conn, $table_query)) {
        echo "Error creating table: " . mysqli_error($conn);
    }
}

// Insert default system settings if table is empty
$check_settings = "SELECT id FROM system_settings LIMIT 1";
$settings_result = mysqli_query($conn, $check_settings);

if (mysqli_num_rows($settings_result) == 0) {
    $insert_settings = "INSERT INTO system_settings (id, company_name, company_email, company_phone, 
                       company_address, gst_rate, smtp_host, smtp_port, smtp_username, 
                       smtp_password, smtp_encryption, maintenance_mode, max_booking_days, 
                       currency, timezone) VALUES 
                       (1, 'MG Transport Services', 'info@mgtransport.com', '+675 1234 5678', 
                       'Torokina Estate, Madang Province, North Coast, Papua New Guinea', 
                       10.0, 'smtp.gmail.com', '587', 'noreply@mgtransport.com', 
                       '', 'tls', 0, 30, 'PGK', 'Pacific/Port_Moresby')";
    mysqli_query($conn, $insert_settings);
}

// Insert default admin user if not exists
$admin_check = "SELECT id FROM users WHERE username = 'admin'";
$admin_result = mysqli_query($conn, $admin_check);

if (mysqli_num_rows($admin_result) == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_insert = "INSERT INTO users (username, email, password, role, first_name, last_name) 
                     VALUES ('admin', 'admin@mgtransport.com', '$admin_password', 'super_admin', 'System', 'Administrator')";
    mysqli_query($conn, $admin_insert);
}

// Insert sample vehicles if none exist
$vehicle_check = "SELECT id FROM vehicles LIMIT 1";
$vehicle_result = mysqli_query($conn, $vehicle_check);

if (mysqli_num_rows($vehicle_result) == 0) {
    $sample_vehicles = [
        "INSERT INTO vehicles (name, model, year, registration_number, vehicle_type, description, rate_per_day, seats, fuel_type, transmission, image_url) 
         VALUES ('Toyota Camry', 'Camry', 2022, 'ABC123', 'sedan', 'Comfortable sedan perfect for city driving', 80.00, 5, 'petrol', 'automatic', 'assets/images/camry.jpg')",
        
        "INSERT INTO vehicles (name, model, year, registration_number, vehicle_type, description, rate_per_day, seats, fuel_type, transmission, image_url) 
         VALUES ('Honda CR-V', 'CR-V', 2021, 'DEF456', 'suv', 'Spacious SUV for family trips', 120.00, 7, 'petrol', 'automatic', 'assets/images/crv.jpg')",
        
        "INSERT INTO vehicles (name, model, year, registration_number, vehicle_type, description, rate_per_day, seats, fuel_type, transmission, image_url) 
         VALUES ('BMW 3 Series', '3 Series', 2023, 'GHI789', 'sedan', 'Luxury sedan with premium features', 150.00, 5, 'petrol', 'automatic', 'assets/images/bmw.jpg')",
        
        "INSERT INTO vehicles (name, model, year, registration_number, vehicle_type, description, rate_per_day, seats, fuel_type, transmission, image_url) 
         VALUES ('Ford Transit', 'Transit', 2021, 'JKL012', 'van', 'Large van for cargo and passengers', 100.00, 12, 'diesel', 'manual', 'assets/images/transit.jpg')"
    ];
    
    foreach ($sample_vehicles as $vehicle_query) {
        mysqli_query($conn, $vehicle_query);
    }
}

    // Create vehicle_specifications table
    $sql = "CREATE TABLE IF NOT EXISTS vehicle_specifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        value VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!mysqli_query($conn, $sql)) {
        die("Error creating vehicle_specifications table: " . mysqli_error($conn));
    }
    
    // Insert default specification values
    $default_specs = [
        ['fuel_type', 'Petrol'],
        ['fuel_type', 'Diesel'],
        ['body_color', 'White'],
        ['body_color', 'Blue'],
        ['body_color', 'Metallic Grey'],
        ['body_color', 'Red'],
        ['body_color', 'Beige'],
        ['transmission', 'Automatic'],
        ['transmission', 'Manual'],
        ['seat_capacity', '5 seats double cab'],
        ['seat_capacity', 'Single cab 2 seat'],
        ['seat_capacity', '10 seater Troop Carrier'],
        ['seat_capacity', '4 Door Double cab']
    ];
    
    // Check if specifications already exist
    $check_query = "SELECT COUNT(*) as count FROM vehicle_specifications";
    $result = mysqli_query($conn, $check_query);
    $row = mysqli_fetch_assoc($result);
    
    if ($row['count'] == 0) {
        // Insert default specifications
        foreach ($default_specs as $spec) {
            $query = "INSERT INTO vehicle_specifications (type, value) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $spec[0], $spec[1]);
            mysqli_stmt_execute($stmt);
        }
    }

    // Add new specification columns to vehicles table
    $alter_queries = [
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS fuel_type VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS body_color VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS transmission VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS seat_capacity VARCHAR(50) DEFAULT NULL"
    ];
    
    foreach ($alter_queries as $query) {
        mysqli_query($conn, $query);
    }
    
    // Create tracking tables
    $tracking_tables = [
        "CREATE TABLE IF NOT EXISTS vehicle_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            speed DECIMAL(5, 2) DEFAULT 0.00,
            heading DECIMAL(5, 2) DEFAULT 0.00,
            altitude DECIMAL(8, 2) DEFAULT 0.00,
            status ENUM('moving', 'stopped', 'idle', 'offline') DEFAULT 'offline',
            fuel_level DECIMAL(5, 2) DEFAULT NULL,
            engine_status ENUM('running', 'stopped', 'maintenance') DEFAULT 'stopped',
            gps_signal_strength ENUM('excellent', 'good', 'fair', 'poor', 'none') DEFAULT 'none',
            battery_level DECIMAL(5, 2) DEFAULT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            UNIQUE KEY unique_vehicle (vehicle_id)
        )",
        "CREATE TABLE IF NOT EXISTS vehicle_tracking_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            speed DECIMAL(5, 2) DEFAULT 0.00,
            heading DECIMAL(5, 2) DEFAULT 0.00,
            altitude DECIMAL(8, 2) DEFAULT 0.00,
            status ENUM('moving', 'stopped', 'idle', 'offline') DEFAULT 'offline',
            fuel_level DECIMAL(5, 2) DEFAULT NULL,
            engine_status ENUM('running', 'stopped', 'maintenance') DEFAULT 'stopped',
            gps_signal_strength ENUM('excellent', 'good', 'fair', 'poor', 'none') DEFAULT 'none',
            battery_level DECIMAL(5, 2) DEFAULT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS tracking_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            driver_id INT NOT NULL,
            booking_id INT DEFAULT NULL,
            session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            session_end TIMESTAMP NULL,
            total_distance DECIMAL(10, 2) DEFAULT 0.00,
            total_fuel_consumed DECIMAL(8, 2) DEFAULT 0.00,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS tracking_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            alert_type ENUM('speed_limit', 'geofence_breach', 'fuel_low', 'battery_low', 'engine_fault', 'gps_signal_lost') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            message TEXT NOT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            is_resolved BOOLEAN DEFAULT FALSE,
            resolved_at TIMESTAMP NULL,
            resolved_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];
    
    foreach ($tracking_tables as $table_query) {
        if (!mysqli_query($conn, $table_query)) {
            error_log("Error creating tracking table: " . mysqli_error($conn));
        }
    }
    
    // Add tracking columns to vehicles table
    $vehicle_tracking_columns = [
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS is_tracked BOOLEAN DEFAULT FALSE",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS tracking_device_id VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS last_tracking_update TIMESTAMP NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS current_driver_id INT DEFAULT NULL",
        "ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS current_booking_id INT DEFAULT NULL"
    ];
    
    foreach ($vehicle_tracking_columns as $column_query) {
        mysqli_query($conn, $column_query);
    }

    // Add missing columns to vehicle_tracking table if they don't exist
    $vehicle_tracking_alter_columns = [
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS fuel_level DECIMAL(5, 2) DEFAULT NULL",
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS heading DECIMAL(5, 2) DEFAULT 0.00",
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS altitude DECIMAL(8, 2) DEFAULT 0.00",
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS gps_signal_strength ENUM('excellent', 'good', 'fair', 'poor', 'none') DEFAULT 'none'",
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS battery_level DECIMAL(5, 2) DEFAULT NULL",
        "ALTER TABLE vehicle_tracking ADD COLUMN IF NOT EXISTS engine_status ENUM('running', 'stopped', 'maintenance') DEFAULT 'stopped'"
    ];
    foreach ($vehicle_tracking_alter_columns as $column_query) {
        mysqli_query($conn, $column_query);
    }

    // Add missing columns to vehicle_tracking_history table if they don't exist
    $vehicle_tracking_history_alter_columns = [
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS fuel_level DECIMAL(5, 2) DEFAULT NULL",
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS heading DECIMAL(5, 2) DEFAULT 0.00",
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS altitude DECIMAL(8, 2) DEFAULT 0.00",
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS gps_signal_strength ENUM('excellent', 'good', 'fair', 'poor', 'none') DEFAULT 'none'",
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS battery_level DECIMAL(5, 2) DEFAULT NULL",
        "ALTER TABLE vehicle_tracking_history ADD COLUMN IF NOT EXISTS engine_status ENUM('running', 'stopped', 'maintenance') DEFAULT 'stopped'"
    ];
    foreach ($vehicle_tracking_history_alter_columns as $column_query) {
        mysqli_query($conn, $column_query);
    }
?> 