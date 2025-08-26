<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirectWithMessage('login.php', 'Please login to book a vehicle', 'warning');
}

$user_id = $_SESSION['user_id'];
$selected_vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log the POST data
    error_log('POST data received: ' . print_r($_POST, true));
    
    $vehicle_id = (int)$_POST['vehicle_id'];
    $start_date = sanitizeInput($_POST['start_date']);
    $end_date = sanitizeInput($_POST['end_date']);
    $pickup_location = sanitizeInput($_POST['pickup_location']);
    $dropoff_location = sanitizeInput($_POST['dropoff_location']);
    $special_requests = sanitizeInput($_POST['special_requests']);
    
    // Debug: Log the processed data
    error_log('Processed data - Vehicle ID: ' . $vehicle_id . ', Start Date: ' . $start_date . ', End Date: ' . $end_date);
    
    // Validate dates
    $date_validation = validateBookingDates($start_date, $end_date);
    if (!$date_validation['valid']) {
        redirectWithMessage('booking.php', $date_validation['message'], 'error');
    }
    
    // Check vehicle availability
    if (!isVehicleAvailable($vehicle_id, $start_date, $end_date, $conn)) {
        redirectWithMessage('booking.php', 'Vehicle is not available for selected dates', 'error');
    }
    
    // Get vehicle details
    $vehicle = getVehicleDetails($vehicle_id, $conn);
    if (!$vehicle) {
        redirectWithMessage('booking.php', 'Vehicle not found', 'error');
    }
    
    // Calculate booking details
    $total_days = getDaysBetween($start_date, $end_date);
    $booking_totals = calculateBookingTotal($vehicle['rate_per_day'], $total_days, $conn);
    
    // Create booking with pending status (no payment required yet)
    $query = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_days, 
              rate_per_day, subtotal, gst_amount, total_amount, pickup_location, dropoff_location, 
              special_requests, payment_method, payment_status, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'pending', 'pending')";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iissidddssss", $user_id, $vehicle_id, $start_date, $end_date, 
                          $total_days, $vehicle['rate_per_day'], $booking_totals['subtotal'], 
                          $booking_totals['gst_amount'], $booking_totals['total_amount'], 
                          $pickup_location, $dropoff_location, $special_requests);
    
    if (mysqli_stmt_execute($stmt)) {
        $booking_id = mysqli_insert_id($conn);
        error_log('Booking created successfully with ID: ' . $booking_id);
        
        // Generate invoice
        $invoice_number = generateInvoiceNumber($conn);
        $due_date = date('Y-m-d', strtotime('+7 days'));
        
        $invoice_query = "INSERT INTO invoices (booking_id, invoice_number, issue_date, due_date, 
                         subtotal, gst_amount, total_amount, status) 
                         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'sent')";
        
        $invoice_stmt = mysqli_prepare($conn, $invoice_query);
        mysqli_stmt_bind_param($invoice_stmt, "issddd", $booking_id, $invoice_number, $due_date, 
                              $booking_totals['subtotal'], $booking_totals['gst_amount'], $booking_totals['total_amount']);
        mysqli_stmt_execute($invoice_stmt);
        
        // Send email notification
        $user = getUserDetails($user_id, $conn);
        $subject = "Booking Request Submitted - MG Transport Services";
        $message = "
        <h2>Booking Request Submitted Successfully!</h2>
        <p>Dear {$user['first_name']} {$user['last_name']},</p>
        <p>Your booking request has been submitted successfully and is pending admin approval.</p>
        
        <h3>Booking Details:</h3>
        <ul>
            <li><strong>Vehicle:</strong> {$vehicle['name']}</li>
            <li><strong>Start Date:</strong> " . formatDate($start_date) . "</li>
            <li><strong>End Date:</strong> " . formatDate($end_date) . "</li>
            <li><strong>Total Days:</strong> $total_days</li>
            <li><strong>Total Amount:</strong> " . formatCurrency($booking_totals['total_amount']) . "</li>
            <li><strong>Pickup Location:</strong> $pickup_location</li>
            <li><strong>Dropoff Location:</strong> $dropoff_location</li>
        </ul>
        
        <p><strong>Next Steps:</strong></p>
        <ol>
            <li>Our admin team will review your booking request</li>
            <li>Once approved, you'll receive a confirmation email</li>
            <li>You can then proceed with payment</li>
            <li>After payment, you'll be able to fill out the vehicle agreement form</li>
        </ol>
        
        <p>Thank you for choosing MG Transport Services!</p>";
        
        sendEmail($user['email'], $subject, $message);
        
        // Create notification for the user
        createBookingNotification($conn, $user_id, $booking_id, 'created');
        
        // Create notification for all admins
        createAdminBookingNotification($conn, $booking_id, 'new_booking');
        
        // Set success message for popup
        $_SESSION['booking_success'] = [
            'booking_id' => $booking_id,
            'vehicle_name' => $vehicle['name'],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_amount' => $booking_totals['total_amount']
        ];
        
        // Redirect to bookings page
        redirectWithMessage('my-bookings.php', 'Booking request submitted successfully! Please wait for admin approval.', 'success');
    } else {
        error_log('Error creating booking: ' . mysqli_stmt_error($stmt));
        redirectWithMessage('booking.php', 'Error creating booking. Please try again.', 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Vehicle Booking - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Additional compact styles for booking form */
        .booking-form .form-control {
            font-size: 0.8rem;
            padding: 0.375rem 0.5rem;
        }
        
        .booking-form .form-label {
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        
        .booking-form .btn {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
        }
        
        .booking-summary {
            font-size: 0.875rem;
        }
        
        .booking-summary h5 {
            font-size: 1rem;
        }
        
        .booking-summary p {
            font-size: 0.8rem;
        }
        
        /* File upload styling for booking form */
        .form-control[type="file"] {
            padding: 0.375rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
            background-color: #fff;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 0.375rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button:hover {
            background: #0b5ed7;
            transform: translateY(-1px);
        }
        
        .form-text {
            font-size: 0.75rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Request Vehicle Booking</h2>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>New Booking Workflow:</strong> Submit your booking request first, then wait for admin approval. 
                    Payment will be required only after your booking is confirmed.
                </div>
                <?php displayMessage(); ?>
                
                <form method="POST" id="bookingForm" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vehicle_id" class="form-label fw-bold">
                                <i class="fas fa-car me-2"></i>Select Vehicle
                            </label>
                            <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                <option value="">Choose a vehicle...</option>
                                <?php
                                $query = "SELECT * FROM vehicles WHERE status = 'available' ORDER BY name";
                                $result = mysqli_query($conn, $query);
                                while ($vehicle = mysqli_fetch_assoc($result)):
                                ?>
                                <option value="<?php echo $vehicle['id']; ?>" 
                                        data-rate="<?php echo $vehicle['rate_per_day']; ?>"
                                        <?php echo ($selected_vehicle_id == $vehicle['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehicle['name']); ?> - 
                                    <?php echo formatCurrency($vehicle['rate_per_day']); ?>/day
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        

                    </div>
                    

                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label fw-bold">
                                <i class="fas fa-calendar-plus me-2"></i>Start Date
                            </label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label fw-bold">
                                <i class="fas fa-calendar-check me-2"></i>End Date
                            </label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pickup_location" class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt me-2"></i>Pickup Location
                            </label>
                            <input type="text" class="form-control" id="pickup_location" name="pickup_location" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="dropoff_location" class="form-label fw-bold">
                                <i class="fas fa-map-marker-alt me-2"></i>Dropoff Location
                            </label>
                            <input type="text" class="form-control" id="dropoff_location" name="dropoff_location" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_requests" class="form-label fw-bold">
                            <i class="fas fa-comment me-2"></i>Special Requests (Optional)
                        </label>
                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Submit Booking Request
                    </button>
                </form>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator"></i> Booking Summary</h5>
                    </div>
                    <div class="card-body">
                        <div id="bookingSummary">
                            <p class="text-muted">Select a vehicle and dates to see pricing details.</p>
                        </div>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Booking Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-clock text-primary"></i> 24/7 Customer Support</li>
                            <li><i class="fas fa-shield-alt text-success"></i> Fully Insured Vehicles</li>
                            <li><i class="fas fa-tools text-warning"></i> Regular Maintenance</li>
                            <li><i class="fas fa-credit-card text-info"></i> Secure Payment</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            console.log('Form validation started');
            
            const vehicleId = document.getElementById('vehicle_id').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const pickupLocation = document.getElementById('pickup_location').value;
            const dropoffLocation = document.getElementById('dropoff_location').value;
            
            console.log('Vehicle ID:', vehicleId);
            console.log('Start Date:', startDate);
            console.log('End Date:', endDate);
            console.log('Pickup Location:', pickupLocation);
            console.log('Dropoff Location:', dropoffLocation);
            
            if (!vehicleId) {
                alert('Please select a vehicle');
                return false;
            }
            
            if (!startDate) {
                alert('Please select a start date');
                return false;
            }
            
            if (!endDate) {
                alert('Please select an end date');
                return false;
            }
            
            if (!pickupLocation.trim()) {
                alert('Please enter pickup location');
                return false;
            }
            
            if (!dropoffLocation.trim()) {
                alert('Please enter dropoff location');
                return false;
            }
            
            if (startDate >= endDate) {
                alert('End date must be after start date');
                return false;
            }
            
            console.log('Form validation passed');
            return true;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicle_id');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const bookingSummary = document.getElementById('bookingSummary');
            
            function updateBookingSummary() {
                const selectedVehicle = vehicleSelect.options[vehicleSelect.selectedIndex];
                const start = startDate.value;
                const end = endDate.value;
                
                if (selectedVehicle.value && start && end) {
                    const rate = parseFloat(selectedVehicle.dataset.rate);
                    const startDate = new Date(start);
                    const endDate = new Date(end);
                    const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
                    
                    if (days > 0) {
                        const subtotal = rate * days;
                        const gstRate = 10; // 10% GST
                        const gstAmount = (subtotal * gstRate) / 100;
                        const total = subtotal + gstAmount;
                        
                        bookingSummary.innerHTML = `
                            <div class="mb-3">
                                <strong>Vehicle:</strong> ${selectedVehicle.text}
                            </div>
                            <div class="mb-3">
                                <strong>Duration:</strong> ${days} day(s)
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Daily Rate:</span>
                                <span>PGK ${rate.toFixed(2)}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span>PGK ${subtotal.toFixed(2)}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>GST (${gstRate}%):</span>
                                <span>PGK ${gstAmount.toFixed(2)}</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total:</span>
                                <span class="text-primary">PGK ${total.toFixed(2)}</span>
                            </div>
                        `;
                    } else {
                        bookingSummary.innerHTML = '<p class="text-danger">Invalid date range.</p>';
                    }
                } else {
                    bookingSummary.innerHTML = '<p class="text-muted">Select a vehicle and dates to see pricing details.</p>';
                }
            }
            
            vehicleSelect.addEventListener('change', updateBookingSummary);
            startDate.addEventListener('change', updateBookingSummary);
            endDate.addEventListener('change', updateBookingSummary);
            
            // Set minimum end date based on start date
            startDate.addEventListener('change', function() {
                endDate.min = this.value;
                if (endDate.value && endDate.value < this.value) {
                    endDate.value = this.value;
                }
            });
        });
    </script>
    
    <!-- Include notification display -->
    <?php include 'includes/notification_display.php'; ?>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Booking Submitted Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                        <h6 class="text-success">Your vehicle booking request has been submitted!</h6>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">What happens next?</h6>
                        <ol class="mb-0">
                            <li>Our admin team will review your booking request</li>
                            <li>Once approved, you'll receive a confirmation email</li>
                            <li>You can then proceed with payment</li>
                            <li>After payment, you'll fill out the vehicle agreement form</li>
                        </ol>
                    </div>
                    
                    <div class="text-center">
                        <a href="my-bookings.php" class="btn btn-primary">
                            <i class="fas fa-calendar me-2"></i>View My Bookings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show success modal if booking was successful
        <?php if (isset($_SESSION['booking_success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        });
        <?php 
        // Clear the success data after showing
        unset($_SESSION['booking_success']);
        endif; 
        ?>
    </script>
</body>
</html> 