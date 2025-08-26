-- Add GPS tracking fields to vehicles table
ALTER TABLE vehicles 
ADD COLUMN gps_device_id VARCHAR(50) NULL,
ADD COLUMN last_latitude DECIMAL(10,8) NULL,
ADD COLUMN last_longitude DECIMAL(10,8) NULL,
ADD COLUMN last_location_update TIMESTAMP NULL,
ADD COLUMN is_tracked BOOLEAN DEFAULT FALSE;

-- Create GPS tracking history table
CREATE TABLE vehicle_tracking_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(10,8) NOT NULL,
    speed DECIMAL(5,2) NULL,
    heading INT NULL,
    fuel_level DECIMAL(5,2) NULL,
    engine_status ENUM('on', 'off') NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    INDEX idx_vehicle_timestamp (vehicle_id, recorded_at)
);

-- Create active tracking sessions table
CREATE TABLE tracking_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    booking_id INT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_vehicle_status (vehicle_id, status)
); 