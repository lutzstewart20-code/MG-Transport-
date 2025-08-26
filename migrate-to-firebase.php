<?php
/**
 * MG Transport - Data Migration Script
 * 
 * This script helps migrate your existing MySQL data to Firebase Firestore
 * Run this script after setting up your Firebase project
 */

// Include your existing database configuration
require_once 'config/database.php';

// Firebase Admin SDK configuration
// You'll need to download your Firebase service account key from Firebase Console
$firebaseConfig = [
    'project_id' => 'YOUR_PROJECT_ID', // Replace with your Firebase project ID
    'private_key_id' => 'YOUR_PRIVATE_KEY_ID',
    'private_key' => 'YOUR_PRIVATE_KEY',
    'client_email' => 'YOUR_CLIENT_EMAIL',
    'client_id' => 'YOUR_CLIENT_ID',
    'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
    'token_uri' => 'https://oauth2.googleapis.com/token',
    'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
    'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/YOUR_CLIENT_EMAIL'
];

// Function to get Firebase access token
function getFirebaseToken($config) {
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    
    $payload = [
        'iss' => $config['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => time() + 3600,
        'iat' => time()
    ];
    
    // This is a simplified version - you'll need a proper JWT library
    // For production, use: composer require firebase/php-jwt
    $token = base64_encode(json_encode($header)) . '.' . 
             base64_encode(json_encode($payload)) . '.' . 
             'signature'; // You'll need proper signing
    
    return $token;
}

// Function to migrate users
function migrateUsers($conn, $firebaseConfig) {
    echo "Migrating users...\n";
    
    $query = "SELECT * FROM users";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying users: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'role' => $row['role'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    // Save to JSON file for manual import
    file_put_contents('migrated_users.json', json_encode($users, JSON_PRETTY_PRINT));
    echo "Users exported to migrated_users.json\n";
    echo "Total users: " . count($users) . "\n\n";
}

// Function to migrate vehicles
function migrateVehicles($conn, $firebaseConfig) {
    echo "Migrating vehicles...\n";
    
    $query = "SELECT * FROM vehicles";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying vehicles: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $vehicles[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'model' => $row['model'],
            'year' => $row['year'],
            'registration_number' => $row['registration_number'],
            'vehicle_type' => $row['vehicle_type'],
            'description' => $row['description'],
            'rate_per_day' => (float)$row['rate_per_day'],
            'status' => $row['status'],
            'image_url' => $row['image_url'],
            'seats' => (int)$row['seats'],
            'fuel_type' => $row['fuel_type'],
            'transmission' => $row['transmission'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    file_put_contents('migrated_vehicles.json', json_encode($vehicles, JSON_PRETTY_PRINT));
    echo "Vehicles exported to migrated_vehicles.json\n";
    echo "Total vehicles: " . count($vehicles) . "\n\n";
}

// Function to migrate bookings
function migrateBookings($conn, $firebaseConfig) {
    echo "Migrating bookings...\n";
    
    $query = "SELECT * FROM bookings";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying bookings: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'vehicle_id' => $row['vehicle_id'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'total_days' => (int)$row['total_days'],
            'rate_per_day' => (float)$row['rate_per_day'],
            'subtotal' => (float)$row['subtotal'],
            'gst_amount' => (float)$row['gst_amount'],
            'total_amount' => (float)$row['total_amount'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'payment_method' => $row['payment_method'],
            'pickup_location' => $row['pickup_location'],
            'dropoff_location' => $row['dropoff_location'],
            'special_requests' => $row['special_requests'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    file_put_contents('migrated_bookings.json', json_encode($bookings, JSON_PRETTY_PRINT));
    echo "Bookings exported to migrated_bookings.json\n";
    echo "Total bookings: " . count($bookings) . "\n\n";
}

// Function to migrate vehicle agreements
function migrateVehicleAgreements($conn, $firebaseConfig) {
    echo "Migrating vehicle agreements...\n";
    
    $query = "SELECT * FROM vehicle_agreements";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying vehicle agreements: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $agreements = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $agreements[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'vehicle_id' => $row['vehicle_id'],
            'organization_company' => $row['organization_company'],
            'business_address' => $row['business_address'],
            'contact_name' => $row['contact_name'],
            'telephone_email' => $row['telephone_email'],
            'position' => $row['position'],
            'division_branch_section' => $row['division_branch_section'],
            'vehicle_registration' => $row['vehicle_registration'],
            'vehicle_make_type' => $row['vehicle_make_type'],
            'vehicle_model' => $row['vehicle_model'],
            'vehicle_colour' => $row['vehicle_colour'],
            'vehicle_mileage' => $row['vehicle_mileage'],
            'pickup_date' => $row['pickup_date'],
            'return_date' => $row['return_date'],
            'pickup_time' => $row['pickup_time'],
            'dropoff_time' => $row['dropoff_time'],
            'number_of_days' => (int)$row['number_of_days'],
            'agreement_status' => $row['agreement_status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    file_put_contents('migrated_agreements.json', json_encode($agreements, JSON_PRETTY_PRINT));
    echo "Vehicle agreements exported to migrated_agreements.json\n";
    echo "Total agreements: " . count($agreements) . "\n\n";
}

// Function to migrate payments
function migratePayments($conn, $firebaseConfig) {
    echo "Migrating payments...\n";
    
    $query = "SELECT * FROM payments";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying payments: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = [
            'id' => $row['id'],
            'booking_id' => $row['booking_id'],
            'payment_method' => $row['payment_method'],
            'amount' => (float)$row['amount'],
            'reference_number' => $row['reference_number'],
            'payment_date' => $row['payment_date'],
            'receipt_file' => $row['receipt_file'],
            'status' => $row['status'],
            'admin_notes' => $row['admin_notes'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    file_put_contents('migrated_payments.json', json_encode($payments, JSON_PRETTY_PRINT));
    echo "Payments exported to migrated_payments.json\n";
    echo "Total payments: " . count($payments) . "\n\n";
}

// Function to migrate notifications
function migrateNotifications($conn, $firebaseConfig) {
    echo "Migrating notifications...\n";
    
    $query = "SELECT * FROM notifications";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error querying notifications: " . mysqli_error($conn) . "\n";
        return;
    }
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'is_read' => (bool)$row['is_read'],
            'related_id' => $row['related_id'],
            'related_type' => $row['related_type'],
            'created_at' => $row['created_at']
        ];
    }
    
    file_put_contents('migrated_notifications.json', json_encode($notifications, JSON_PRETTY_PRINT));
    echo "Notifications exported to migrated_notifications.json\n";
    echo "Total notifications: " . count($notifications) . "\n\n";
}

// Function to create Firestore import script
function createFirestoreImportScript() {
    echo "Creating Firestore import script...\n";
    
    $importScript = '<?php
/**
 * Firestore Import Script
 * 
 * This script imports the migrated data into Firestore
 * Run this after setting up your Firebase project
 */

require_once "vendor/autoload.php";

use Google\Cloud\Firestore\FirestoreClient;

// Initialize Firestore
$firestore = new FirestoreClient([
    "projectId" => "YOUR_PROJECT_ID" // Replace with your project ID
]);

// Import users
function importUsers($firestore) {
    $users = json_decode(file_get_contents("migrated_users.json"), true);
    $collection = $firestore->collection("users");
    
    foreach ($users as $user) {
        $userId = $user["id"];
        unset($user["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $user["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($user["created_at"]));
        $user["updated_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($user["updated_at"]));
        
        $collection->document($userId)->set($user);
        echo "Imported user: " . $user["email"] . "\n";
    }
}

// Import vehicles
function importVehicles($firestore) {
    $vehicles = json_decode(file_get_contents("migrated_vehicles.json"), true);
    $collection = $firestore->collection("vehicles");
    
    foreach ($vehicles as $vehicle) {
        $vehicleId = $vehicle["id"];
        unset($vehicle["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $vehicle["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($vehicle["created_at"]));
        $vehicle["updated_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($vehicle["updated_at"]));
        
        $collection->document($vehicleId)->set($vehicle);
        echo "Imported vehicle: " . $vehicle["name"] . "\n";
    }
}

// Import bookings
function importBookings($firestore) {
    $bookings = json_decode(file_get_contents("migrated_bookings.json"), true);
    $collection = $firestore->collection("bookings");
    
    foreach ($bookings as $booking) {
        $bookingId = $booking["id"];
        unset($booking["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $booking["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($booking["created_at"]));
        $booking["updated_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($booking["updated_at"]));
        
        $collection->document($bookingId)->set($booking);
        echo "Imported booking ID: " . $bookingId . "\n";
    }
}

// Import vehicle agreements
function importAgreements($firestore) {
    $agreements = json_decode(file_get_contents("migrated_agreements.json"), true);
    $collection = $firestore->collection("vehicle_agreements");
    
    foreach ($agreements as $agreement) {
        $agreementId = $agreement["id"];
        unset($agreement["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $agreement["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($agreement["created_at"]));
        $agreement["updated_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($agreement["updated_at"]));
        
        $collection->document($agreementId)->set($agreement);
        echo "Imported agreement ID: " . $agreementId . "\n";
    }
}

// Import payments
function importPayments($firestore) {
    $payments = json_decode(file_get_contents("migrated_payments.json"), true);
    $collection = $firestore->collection("payments");
    
    foreach ($payments as $payment) {
        $paymentId = $payment["id"];
        unset($payment["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $payment["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($payment["created_at"]));
        $payment["updated_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($payment["updated_at"]));
        
        $collection->document($paymentId)->set($payment);
        echo "Imported payment ID: " . $paymentId . "\n";
    }
}

// Import notifications
function importNotifications($firestore) {
    $notifications = json_decode(file_get_contents("migrated_notifications.json"), true);
    $collection = $firestore->collection("notifications");
    
    foreach ($notifications as $notification) {
        $notificationId = $notification["id"];
        unset($notification["id"]); // Remove MySQL ID
        
        // Convert timestamps
        $notification["created_at"] = new \Google\Cloud\Core\Timestamp(new DateTime($notification["created_at"]));
        
        $collection->document($notificationId)->set($notification);
        echo "Imported notification ID: " . $notificationId . "\n";
    }
}

// Main import function
function runImport() {
    global $firestore;
    
    echo "Starting Firestore import...\n\n";
    
    try {
        importUsers($firestore);
        echo "\n";
        
        importVehicles($firestore);
        echo "\n";
        
        importBookings($firestore);
        echo "\n";
        
        importAgreements($firestore);
        echo "\n";
        
        importPayments($firestore);
        echo "\n";
        
        importNotifications($firestore);
        echo "\n";
        
        echo "Import completed successfully!\n";
        
    } catch (Exception $e) {
        echo "Import failed: " . $e->getMessage() . "\n";
    }
}

// Run import if script is executed directly
if (php_sapi_name() === "cli" || !defined("ABSPATH")) {
    runImport();
}
';
    
    file_put_contents('import-to-firestore.php', $importScript);
    echo "Firestore import script created: import-to-firestore.php\n\n";
}

// Function to create composer.json for Firebase dependencies
function createComposerJson() {
    $composerJson = '{
    "require": {
        "google/cloud-firestore": "^1.0",
        "kreait/firebase-php": "^7.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}';
    
    file_put_contents('composer.json', $composerJson);
    echo "Composer.json created for Firebase dependencies\n\n";
}

// Main migration function
function runMigration($conn, $firebaseConfig) {
    echo "=== MG Transport Data Migration to Firebase ===\n\n";
    
    // Check database connection
    if (!$conn) {
        echo "Error: Database connection failed\n";
        return;
    }
    
    echo "Database connected successfully\n\n";
    
    // Run migrations
    migrateUsers($conn, $firebaseConfig);
    migrateVehicles($conn, $firebaseConfig);
    migrateBookings($conn, $firebaseConfig);
    migrateVehicleAgreements($conn, $firebaseConfig);
    migratePayments($conn, $firebaseConfig);
    migrateNotifications($conn, $firebaseConfig);
    
    // Create import scripts
    createFirestoreImportScript();
    createComposerJson();
    
    echo "=== Migration Summary ===\n";
    echo "✅ All data exported to JSON files\n";
    echo "✅ Firestore import script created\n";
    echo "✅ Composer.json created for dependencies\n\n";
    
    echo "Next steps:\n";
    echo "1. Install Firebase dependencies: composer install\n";
    echo "2. Update Firebase configuration in import-to-firestore.php\n";
    echo "3. Run the import script: php import-to-firestore.php\n";
    echo "4. Deploy your Firebase project\n\n";
    
    echo "Migration completed successfully!\n";
}

// Run migration if script is executed directly
if (php_sapi_name() === "cli" || !defined("ABSPATH")) {
    runMigration($conn, $firebaseConfig);
}
?>
