<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if this is a payment integration request
$payment_integration = isset($_GET['payment_integration']) && $_GET['payment_integration'] == '1';
$booking_id = intval($_GET['booking_id'] ?? 0);

    if ($payment_integration && $booking_id) {
        // Get booking details for payment integration
        $booking_query = "SELECT b.id, b.vehicle_id, b.start_date, b.end_date, b.total_days, b.status, b.pickup_location, b.dropoff_location,
                          v.name, v.model, v.image_url, v.registration_number, v.vehicle_type, v.seats, v.rate_per_day 
                          FROM bookings b 
                          JOIN vehicles v ON b.vehicle_id = v.id 
                          WHERE b.id = ? AND b.user_id = ? AND b.status = 'confirmed'";
        $booking_stmt = mysqli_prepare($conn, $booking_query);
        mysqli_stmt_bind_param($booking_stmt, "ii", $booking_id, $_SESSION['user_id']);
        mysqli_stmt_execute($booking_stmt);
        $booking_result = mysqli_stmt_get_result($booking_stmt);
        $confirmed_booking = mysqli_fetch_assoc($booking_result);
        
        if (!$confirmed_booking) {
            header('Location: my-bookings.php?error=invalid_booking');
            exit();
        }
        
        $vehicle_id = $confirmed_booking['vehicle_id'];
        $vehicle = [
            'id' => $confirmed_booking['vehicle_id'],
            'name' => $confirmed_booking['name'],
            'model' => $confirmed_booking['model'],
            'image_url' => $confirmed_booking['image_url'],
            'registration_number' => $confirmed_booking['registration_number'],
            'vehicle_type' => $confirmed_booking['vehicle_type'],
            'seats' => $confirmed_booking['seats'],
            'rate_per_day' => $confirmed_booking['rate_per_day']
        ];
    
    // Check if agreement already exists for this booking
    $existing_agreement_query = "SELECT * FROM vehicle_agreements WHERE booking_id = ?";
    $existing_agreement_stmt = mysqli_prepare($conn, $existing_agreement_query);
    mysqli_stmt_bind_param($existing_agreement_stmt, "i", $booking_id);
    mysqli_stmt_execute($existing_agreement_stmt);
    $existing_agreement_result = mysqli_stmt_get_result($existing_agreement_stmt);
    $existing_agreement = mysqli_fetch_assoc($existing_agreement_result);
    
    if ($existing_agreement) {
        if ($existing_agreement['agreement_status'] === 'approved') {
            $success_message = 'Your vehicle agreement has been approved! You can now collect your vehicle.';
            $show_agreement_form = false;
        } elseif ($existing_agreement['agreement_status'] === 'pending') {
            $info_message = 'Your vehicle agreement is pending approval. Please wait for admin confirmation.';
            $show_agreement_form = false;
        } elseif ($existing_agreement['agreement_status'] === 'rejected') {
            $error_message = 'Your vehicle agreement was rejected. Please contact admin for details.';
            $show_agreement_form = false;
        }
    } else {
        $show_agreement_form = true;
    }
    
} else {
    // Original flow - get vehicle ID from URL parameter
    $vehicle_id = intval($_GET['vehicle_id'] ?? 0);
    
    if (!$vehicle_id) {
        header('Location: vehicles.php');
        exit();
    }
    
    // Get vehicle details
    $vehicle_query = "SELECT * FROM vehicles WHERE id = ?";
    $vehicle_stmt = mysqli_prepare($conn, $vehicle_query);
    mysqli_stmt_bind_param($vehicle_stmt, "i", $vehicle_id);
    mysqli_stmt_execute($vehicle_stmt);
    $vehicle_result = mysqli_stmt_get_result($vehicle_stmt);
    $vehicle = mysqli_fetch_assoc($vehicle_result);
    
    if (!$vehicle) {
        header('Location: vehicles.php');
        exit();
    }
    
    // Check if user has a confirmed booking for this vehicle
    $booking_query = "SELECT b.*, v.name as vehicle_name, v.registration_number 
                      FROM bookings b 
                      JOIN vehicles v ON b.vehicle_id = v.id 
                      WHERE b.user_id = ? AND b.vehicle_id = ? AND b.status = 'confirmed' 
                      ORDER BY b.created_at DESC LIMIT 1";
    $booking_stmt = mysqli_prepare($conn, $booking_query);
    mysqli_stmt_bind_param($booking_stmt, "ii", $_SESSION['user_id'], $vehicle_id);
    mysqli_stmt_execute($booking_stmt);
    $booking_result = mysqli_stmt_get_result($booking_stmt);
    $confirmed_booking = mysqli_fetch_assoc($booking_result);
    
    if (!$confirmed_booking) {
        $error_message = 'You must have a confirmed booking for this vehicle before you can fill out the agreement form.';
        $show_agreement_form = false;
    } else {
        $show_agreement_form = true;
        // Check if agreement already exists for this booking
        $existing_agreement_query = "SELECT * FROM vehicle_agreements WHERE booking_id = ?";
        $existing_agreement_stmt = mysqli_prepare($conn, $existing_agreement_query);
        mysqli_stmt_bind_param($existing_agreement_stmt, "i", $confirmed_booking['id']);
        mysqli_stmt_execute($existing_agreement_stmt);
        $existing_agreement_result = mysqli_stmt_get_result($existing_agreement_stmt);
        $existing_agreement = mysqli_fetch_assoc($existing_agreement_result);
        
        if ($existing_agreement) {
            if ($existing_agreement['agreement_status'] === 'approved') {
                $success_message = 'Your vehicle agreement has been approved! You can now collect your vehicle.';
                $show_agreement_form = false;
            } elseif ($existing_agreement['agreement_status'] === 'pending') {
                $info_message = 'Your vehicle agreement is pending approval. Please wait for admin confirmation.';
                $show_agreement_form = false;
            } elseif ($existing_agreement['agreement_status'] === 'rejected') {
                $error_message = 'Your vehicle agreement was rejected. Please contact admin for details.';
                $show_agreement_form = false;
            }
        }
    }
}

// Check if vehicle_agreements table exists and has correct structure
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'vehicle_agreements'");
if (mysqli_num_rows($table_check) == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE IF NOT EXISTS vehicle_agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
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
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    )";
    
    if (mysqli_query($conn, $create_table)) {
        error_log("Vehicle agreements table created successfully");
    } else {
        error_log("Failed to create vehicle agreements table: " . mysqli_error($conn));
        die("Failed to create vehicle agreements table. Please contact administrator.");
    }
}

// Get user details
$user_id = intval($_SESSION['user_id']);
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Initialize variables
$number_of_days = 1;
$success_message = $success_message ?? '';
$error_message = $error_message ?? '';
$info_message = $info_message ?? '';
$show_agreement_form = $show_agreement_form ?? false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $organization_company = trim($_POST['organization_company']);
    $business_address = trim($_POST['business_address']);
    $contact_name = trim($_POST['contact_name']);
    $telephone_email = trim($_POST['telephone_email']);
    $position = trim($_POST['position']);
    $division_branch_section = trim($_POST['division_branch_section']);
    $vehicle_registration = trim($_POST['vehicle_registration']);
    $vehicle_make_type = trim($_POST['vehicle_make_type']);
    $vehicle_model = trim($_POST['vehicle_model']);
    $vehicle_colour = trim($_POST['vehicle_colour']);
    $vehicle_mileage = trim($_POST['vehicle_mileage']);
    $pickup_date = $_POST['pickup_date'];
    $return_date = $_POST['return_date'];
    $pickup_time = $_POST['pickup_time'];
    $dropoff_time = $_POST['dropoff_time'];
    
    // Calculate number of days
    $pickup_datetime = new DateTime($pickup_date);
    $return_datetime = new DateTime($return_date);
    $number_of_days = intval($pickup_datetime->diff($return_datetime)->days + 1);
    
    // Get payment method and details
    $payment_method = trim($_POST['payment_method'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    
    // Validate required fields
    if (empty($organization_company) || empty($business_address) || empty($contact_name) || 
        empty($telephone_email) || empty($position) || empty($division_branch_section) ||
        empty($vehicle_registration) || empty($vehicle_make_type) || empty($vehicle_model) ||
        empty($vehicle_colour) || empty($vehicle_mileage) || empty($pickup_date) || 
        empty($return_date) || empty($pickup_time) || empty($dropoff_time)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (empty($payment_method)) {
        $error_message = 'Please select a payment method.';
    } elseif ($payment_method !== 'cash' && (empty($reference_number) || empty($payment_date))) {
        $error_message = 'Please provide reference number and payment date for non-cash payments.';
    } elseif ($pickup_date >= $return_date) {
        $error_message = 'End date must be after start date.';
    } else {
        // Handle file upload for non-cash payments
        $receipt_file_path = null;
        if ($payment_method !== 'cash') {
            if (!isset($_FILES['receipt_file']) || $_FILES['receipt_file']['error'] !== UPLOAD_ERR_OK) {
                $error_message = 'Please upload a valid payment receipt.';
            } else {
                $file = $_FILES['receipt_file'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = 'Invalid file type. Please upload JPG, PNG, or PDF files only.';
                } elseif ($file['size'] > $max_size) {
                    $error_message = 'File size too large. Maximum size is 5MB.';
                } else {
                    // Create uploads directory if it doesn't exist
                    $upload_dir = 'uploads/receipts/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'receipt_' . uniqid() . '_' . $user_id . '.' . $file_extension;
                    $receipt_file_path = $upload_dir . $filename;
                    
                    if (!move_uploaded_file($file['tmp_name'], $receipt_file_path)) {
                        $error_message = 'Failed to upload receipt file. Please try again.';
                    }
                }
            }
        }
        
        if (empty($error_message)) {
            // Get the correct booking_id for this agreement
            $agreement_booking_id = null;
            
            if ($payment_integration && isset($_SESSION['payment_booking_id'])) {
                $agreement_booking_id = $_SESSION['payment_booking_id'];
            } elseif (isset($confirmed_booking['id'])) {
                $agreement_booking_id = $confirmed_booking['id'];
            } else {
                $error_message = 'Unable to determine booking ID. Please try again.';
            }
            
            if ($agreement_booking_id) {
                // Check if user already has a pending agreement for this booking
                $check_query = "SELECT id FROM vehicle_agreements WHERE booking_id = ? AND agreement_status IN ('pending', 'approved')";
                $check_stmt = mysqli_prepare($conn, $check_query);
                mysqli_stmt_bind_param($check_stmt, "i", $agreement_booking_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
        
                if (mysqli_num_rows($check_result) > 0) {
                    $error_message = 'You already have a pending or approved agreement for this vehicle.';
                } else {
                    // Insert agreement
                    $insert_query = "INSERT INTO vehicle_agreements (
                        booking_id, user_id, vehicle_id, organization_company, business_address, contact_name, 
                        telephone_email, position, division_branch_section, vehicle_registration, 
                        vehicle_make_type, vehicle_model, vehicle_colour, vehicle_mileage, 
                        pickup_date, return_date, pickup_time, dropoff_time, number_of_days
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    if ($insert_stmt) {
                        $types = 'iiisssssssssssssssi';
                        $bind_result = mysqli_stmt_bind_param($insert_stmt, $types, 
                            $agreement_booking_id, $user_id, $vehicle_id, $organization_company, $business_address, $contact_name,
                            $telephone_email, $position, $division_branch_section, $vehicle_registration,
                            $vehicle_make_type, $vehicle_model, $vehicle_colour, $vehicle_mileage,
                            $pickup_date, $return_date, $pickup_time, $dropoff_time, $number_of_days
                        );
                        
                        if ($bind_result && mysqli_stmt_execute($insert_stmt)) {
                            $agreement_id = mysqli_insert_id($conn);
                            
                            // Process payment
                            if ($payment_method !== 'cash') {
                                // Insert payment record
                                $payment_query = "INSERT INTO payments (
                                    booking_id, payment_method, amount, reference_number, 
                                    payment_date, receipt_file, status
                                ) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
                                
                                $payment_stmt = mysqli_prepare($conn, $payment_query);
                                if ($payment_stmt) {
                                    $total_amount = ($confirmed_booking['rate_per_day'] ?? 0) * $number_of_days;
                                    mysqli_stmt_bind_param($payment_stmt, "isdsss", 
                                        $agreement_booking_id, $payment_method, $total_amount,
                                        $reference_number, $payment_date, $receipt_file_path
                                    );
                                    
                                    if (mysqli_stmt_execute($payment_stmt)) {
                                        // Update booking payment status
                                        $update_booking = "UPDATE bookings SET payment_status = 'pending_verification' WHERE id = ?";
                                        $update_stmt = mysqli_prepare($conn, $update_booking);
                                        mysqli_stmt_bind_param($update_stmt, "i", $agreement_booking_id);
                                        mysqli_stmt_execute($update_stmt);
                                        
                                        // Create notification
                                        createEnhancedNotification($conn, $user_id, 'Agreement & Payment Submitted', 
                                            'Your vehicle agreement and payment have been submitted successfully and are pending admin review.', 
                                            'success', $agreement_id, 'agreement');
                                        
                                        $success_message = 'Vehicle agreement and payment submitted successfully! Our team will review and contact you soon.';
                                    } else {
                                        $error_message = 'Agreement submitted but payment processing failed. Please contact admin.';
                                    }
                                } else {
                                    $error_message = 'Agreement submitted but payment processing failed. Please contact admin.';
                                }
                            } else {
                                // For cash payment, mark as paid
                                $update_booking = "UPDATE bookings SET payment_status = 'paid', payment_method = 'cash' WHERE id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_booking);
                                mysqli_stmt_bind_param($update_stmt, "i", $agreement_booking_id);
                                mysqli_stmt_execute($update_stmt);
                                
                                // Create notification
                                createEnhancedNotification($conn, $user_id, 'Agreement Submitted', 
                                    'Your vehicle agreement has been submitted successfully. Please bring cash payment when collecting the vehicle.', 
                                    'success', $agreement_id, 'agreement');
                                
                                $success_message = 'Vehicle agreement submitted successfully! Please bring cash payment when collecting the vehicle.';
                            }
                            
                            // Clear form data after successful submission
                            $_POST = array();
                            $show_agreement_form = false;
                        } else {
                            $error_message = 'Error executing statement: ' . mysqli_stmt_error($insert_stmt);
                        }
                    } else {
                        $error_message = 'Error preparing statement: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Agreement Form - MG Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-file-contract me-2"></i>
                            Vehicle Agreement Form
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($info_message)): ?>
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php echo htmlspecialchars($info_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_agreement_form && isset($vehicle)): ?>
                            <!-- Vehicle Information -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5 class="text-primary">Vehicle Details</h5>
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <?php if (!empty($vehicle['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($vehicle['image_url']); ?>" 
                                                             alt="Vehicle Image" class="img-fluid rounded">
                                                    <?php else: ?>
                                                        <div class="bg-light text-center p-3 rounded">
                                                            <i class="fas fa-car fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8">
                                                    <h6 class="mb-2"><?php echo htmlspecialchars($vehicle['name']); ?></h6>
                                                    <p class="mb-1"><strong>Model:</strong> <?php echo htmlspecialchars($vehicle['model']); ?></p>
                                                    <p class="mb-1"><strong>Registration:</strong> <?php echo htmlspecialchars($vehicle['registration_number']); ?></p>
                                                    <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($vehicle['vehicle_type']); ?></p>
                                                    <p class="mb-1"><strong>Seats:</strong> <?php echo htmlspecialchars($vehicle['seats']); ?></p>
                                                    <p class="mb-0"><strong>Rate:</strong> <?php echo formatCurrency($vehicle['rate_per_day']); ?>/day</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="text-primary">Booking Information</h5>
                                    <div class="card">
                                        <div class="card-body">
                                            <?php if (isset($confirmed_booking)): ?>
                                                <p class="mb-1"><strong>Booking ID:</strong> #<?php echo $confirmed_booking['id']; ?></p>
                                                <p class="mb-1"><strong>Status:</strong> 
                                                    <span class="badge bg-success">Confirmed</span>
                                                </p>
                                                <p class="mb-1"><strong>Start Date:</strong> 
                                                    <?php 
                                                    if (isset($confirmed_booking['start_date']) && !empty($confirmed_booking['start_date'])) {
                                                        echo date('M d, Y', strtotime($confirmed_booking['start_date']));
                                                    } else {
                                                        echo '<span class="text-muted">Not specified</span>';
                                                    }
                                                    ?>
                                                </p>
                                                <p class="mb-1"><strong>End Date:</strong> 
                                                    <?php 
                                                    if (isset($confirmed_booking['end_date']) && !empty($confirmed_booking['end_date'])) {
                                                        echo date('M d, Y', strtotime($confirmed_booking['end_date']));
                                                    } else {
                                                        echo '<span class="text-muted">Not specified</span>';
                                                    }
                                                    ?>
                                                </p>
                                                <p class="mb-0"><strong>Total Days:</strong> 
                                                    <?php 
                                                    if (isset($confirmed_booking['total_days']) && $confirmed_booking['total_days'] > 0) {
                                                        echo $confirmed_booking['total_days'] . ' days';
                                                    } elseif (isset($confirmed_booking['start_date']) && isset($confirmed_booking['end_date']) && 
                                                             !empty($confirmed_booking['start_date']) && !empty($confirmed_booking['end_date'])) {
                                                        try {
                                                            $start = new DateTime($confirmed_booking['start_date']);
                                                            $end = new DateTime($confirmed_booking['end_date']);
                                                            $days = $start->diff($end)->days + 1;
                                                            echo $days . ' days';
                                                        } catch (Exception $e) {
                                                            echo '<span class="text-muted">Unable to calculate</span>';
                                                        }
                                                    } else {
                                                        echo '<span class="text-muted">Not specified</span>';
                                                    }
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Agreement Form -->
                            <form method="POST" enctype="multipart/form-data" id="agreementForm">
                                <div class="row">
                                    <!-- Organization Information -->
                                    <div class="col-md-6">
                                        <h5 class="text-primary mb-3">Organization Information</h5>
                                        
                                        <div class="mb-3">
                                            <label for="organization_company" class="form-label">Organization/Company Name *</label>
                                            <input type="text" class="form-control" id="organization_company" name="organization_company" 
                                                   value="<?php echo htmlspecialchars($_POST['organization_company'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="business_address" class="form-label">Business Address *</label>
                                            <textarea class="form-control" id="business_address" name="business_address" rows="3" required><?php echo htmlspecialchars($_POST['business_address'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="contact_name" class="form-label">Contact Person Name *</label>
                                            <input type="text" class="form-control" id="contact_name" name="contact_name" 
                                                   value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="telephone_email" class="form-label">Telephone/Email *</label>
                                            <input type="text" class="form-control" id="telephone_email" name="telephone_email" 
                                                   value="<?php echo htmlspecialchars($_POST['telephone_email'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="position" class="form-label">Position *</label>
                                            <input type="text" class="form-control" id="position" name="position" 
                                                   value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="division_branch_section" class="form-label">Division/Branch/Section *</label>
                                            <input type="text" class="form-control" id="division_branch_section" name="division_branch_section" 
                                                   value="<?php echo htmlspecialchars($_POST['division_branch_section'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Vehicle Information -->
                                    <div class="col-md-6">
                                        <h5 class="text-primary mb-3">Vehicle Information</h5>
                                        
                                        <div class="mb-3">
                                            <label for="vehicle_registration" class="form-label">Vehicle Registration Number *</label>
                                            <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" 
                                                   value="<?php echo htmlspecialchars($_POST['vehicle_registration'] ?? ($vehicle['registration_number'] ?? '')); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="vehicle_make_type" class="form-label">Vehicle Make/Type *</label>
                                            <input type="text" class="form-control" id="vehicle_make_type" name="vehicle_make_type" 
                                                   value="<?php echo htmlspecialchars($_POST['vehicle_make_type'] ?? ($vehicle['name'] ?? '')); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="vehicle_model" class="form-label">Vehicle Model *</label>
                                            <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" 
                                                   value="<?php echo htmlspecialchars($_POST['vehicle_model'] ?? ($vehicle['model'] ?? '')); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="vehicle_colour" class="form-label">Vehicle Colour *</label>
                                            <input type="text" class="form-control" id="vehicle_colour" name="vehicle_colour" 
                                                   value="<?php echo htmlspecialchars($_POST['vehicle_colour'] ?? ''); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="vehicle_mileage" class="form-label">Vehicle Mileage *</label>
                                            <input type="text" class="form-control" id="vehicle_mileage" name="vehicle_mileage" 
                                                   value="<?php echo htmlspecialchars($_POST['vehicle_mileage'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rental Details -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5 class="text-primary mb-3">Rental Details</h5>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="pickup_date" class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                                   value="<?php echo $_POST['pickup_date'] ?? (isset($confirmed_booking['start_date']) && !empty($confirmed_booking['start_date']) ? $confirmed_booking['start_date'] : ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="return_date" class="form-label">End Date *</label>
                                            <input type="date" class="form-control" id="return_date" name="return_date" 
                                                   value="<?php echo $_POST['return_date'] ?? (isset($confirmed_booking['end_date']) && !empty($confirmed_booking['end_date']) ? $confirmed_booking['end_date'] : ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="pickup_time" class="form-label">Start Time *</label>
                                            <input type="time" class="form-control" id="pickup_time" name="pickup_time" 
                                                   value="<?php echo $_POST['pickup_time'] ?? '09:00'; ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="dropoff_time" class="form-label">End Time *</label>
                                            <input type="time" class="form-control" id="dropoff_time" name="dropoff_time" 
                                                   value="<?php echo $_POST['dropoff_time'] ?? '17:00'; ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Information -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5 class="text-primary mb-3">Payment Information</h5>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">Payment Method *</label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select Payment Method</option>
                                                <option value="cash" <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                                <option value="sms" <?php echo ($_POST['payment_method'] ?? '') === 'sms' ? 'selected' : ''; ?>>SMS Payment</option>
                                                <option value="online" <?php echo ($_POST['payment_method'] ?? '') === 'online' ? 'selected' : ''; ?>>Online Payment</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="reference_number" class="form-label">Reference Number</label>
                                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                                   value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>" 
                                                   placeholder="Transaction/Reference number">
                                            <small class="form-text text-muted">Required for non-cash payments</small>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="payment_date" class="form-label">Payment Date</label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                                   value="<?php echo $_POST['payment_date'] ?? date('Y-m-d'); ?>">
                                            <small class="form-text text-muted">Required for non-cash payments</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Receipt Upload -->
                                <div class="row mt-3" id="receipt_upload_section" style="display: none;">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="receipt_file" class="form-label">Payment Receipt *</label>
                                            <input type="file" class="form-control" id="receipt_file" name="receipt_file" 
                                                   accept=".jpg,.jpeg,.png,.pdf">
                                            <small class="form-text text-muted">Upload JPG, PNG, or PDF file (max 5MB)</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="row mt-4">
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Submit Agreement & Payment
                                        </button>
                                        <a href="my-bookings.php" class="btn btn-secondary btn-lg ms-3">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            Back to Bookings
                                        </a>
                                    </div>
                                </div>
                            </form>
                        <?php elseif (!$show_agreement_form): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
                                <h5 class="text-muted">No Action Required</h5>
                                <p class="text-muted">You don't need to fill out an agreement form at this time.</p>
                                <a href="my-bookings.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Bookings
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                <h5 class="text-muted">Vehicle Not Found</h5>
                                <p class="text-muted">The requested vehicle could not be found.</p>
                                <a href="vehicles.php" class="btn btn-primary">
                                    <i class="fas fa-car me-2"></i>
                                    Browse Vehicles
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>
                        Success!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Your vehicle agreement has been submitted successfully!</p>
                    <p>Our team will review your submission and contact you soon.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide receipt upload based on payment method
        document.getElementById('payment_method').addEventListener('change', function() {
            const receiptSection = document.getElementById('receipt_upload_section');
            const receiptFile = document.getElementById('receipt_file');
            const referenceNumber = document.getElementById('reference_number');
            const paymentDate = document.getElementById('payment_date');
            
            if (this.value === 'cash') {
                receiptSection.style.display = 'none';
                receiptFile.removeAttribute('required');
                referenceNumber.removeAttribute('required');
                paymentDate.removeAttribute('required');
            } else {
                receiptSection.style.display = 'block';
                receiptFile.setAttribute('required', 'required');
                referenceNumber.setAttribute('required', 'required');
                paymentDate.setAttribute('required', 'required');
            }
        });

        // Form validation
        document.getElementById('agreementForm').addEventListener('submit', function(e) {
            const paymentMethod = document.getElementById('payment_method').value;
            const referenceNumber = document.getElementById('reference_number').value;
            const paymentDate = document.getElementById('payment_date').value;
            
            if (paymentMethod !== 'cash') {
                if (!referenceNumber.trim()) {
                    e.preventDefault();
                    alert('Please provide a reference number for non-cash payments.');
                    return false;
                }
                if (!paymentDate) {
                    e.preventDefault();
                    alert('Please provide a payment date for non-cash payments.');
                    return false;
                }
            }
        });

        // Show success modal if there's a success message
        <?php if (!empty($success_message)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>
