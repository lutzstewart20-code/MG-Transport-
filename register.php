<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    
    $errors = [];
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!validateEmail($email)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one number.";
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $first_name)) {
        $errors[] = "First name can only contain letters and spaces.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $last_name)) {
        $errors[] = "Last name can only contain letters and spaces.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    
    // File upload validation and processing (temporarily disabled until database is updated)
    $user_photo_path = null;
    $driver_license_path = null;
    
    // TODO: Re-enable photo upload after database schema is updated
    // Create uploads directory if it doesn't exist
    // $uploads_dir = 'uploads/user_photos/';
    // if (!file_exists($uploads_dir)) {
    //     mkdir($uploads_dir, 0755, true);
    // }
    
    // Process user photo upload (temporarily disabled)
    // if (isset($_FILES['user_photo']) && $_FILES['user_photo']['error'] === UPLOAD_ERR_OK) {
    //     $user_photo = $_FILES['user_photo'];
    //     $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    //     $max_size = 5 * 1024 * 1024; // 5MB
    //     
    //     if (!in_array($user_photo['type'], $allowed_types)) {
    //         $errors[] = "User photo must be a JPEG or PNG file.";
    //     } elseif ($user_photo['size'] > $max_size) {
    //         $errors[] = "User photo must be less than 5MB.";
    //     } else {
    //         $file_extension = pathinfo($user_photo['name'], PATHINFO_EXTENSION);
    //         $user_photo_filename = 'user_' . time() . '_' . uniqid() . '.' . $file_extension;
    //         $user_photo_path = $uploads_dir . $user_photo_filename;
    //         
    //         if (!move_uploaded_file($user_photo['tmp_name'], $user_photo_path)) {
    //             $errors[] = "Failed to upload user photo.";
    //         }
    //     }
    // } else {
    //     $errors[] = "User photo is required.";
    // }
    
    // Process driver's license photo upload (temporarily disabled)
    // if (isset($_FILES['driver_license_photo']) && $_FILES['driver_license_photo']['error'] === UPLOAD_ERR_OK) {
    //     $driver_license = $_FILES['driver_license_photo'];
    //     $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    //     $max_size = 5 * 1024 * 1024; // 5MB
    //     
    //     if (!in_array($driver_license['type'], $allowed_types)) {
    //         $errors[] = "Driver's license photo must be a JPEG or PNG file.";
    //     } elseif ($driver_license['size'] > $max_size) {
    //         $errors[] = "Driver's license photo must be less than 5MB.";
    //     } else {
    //         $file_extension = pathinfo($driver_license['name'], PATHINFO_EXTENSION);
    //         $driver_license_filename = 'license_' . time() . '_' . uniqid() . '.' . $file_extension;
    //         $driver_license_path = $uploads_dir . $driver_license_filename;
    //         
    //         if (!move_uploaded_file($driver_license['tmp_name'], $driver_license_path)) {
    //             $errors[] = "Failed to upload driver's license photo.";
    //         }
    //     }
    // } else {
    //     $errors[] = "Driver's license photo is required.";
    // }
    
    // Check if username already exists
    $check_username = "SELECT id FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $check_username);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
        $errors[] = "Username already exists.";
    }
    
    // Check if email already exists
    $check_email = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
        $errors[] = "Email already exists.";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user (temporarily without photo columns until database is updated)
        $insert_query = "INSERT INTO users (username, email, password, first_name, last_name, phone, address, role) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sssssss", $username, $email, $hashed_password, $first_name, $last_name, $phone, $address);
        
        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            
            // Log activity
            logActivity($user_id, 'User Registration', 'New user registered successfully', $conn);
            
            // Send welcome email
            $subject = "Welcome to MG Transport Services";
            $message = "
            <h2>Welcome to MG Transport Services!</h2>
            <p>Dear $first_name $last_name,</p>
            <p>Thank you for registering with MG Transport Services. Your account has been created successfully.</p>
            <p>You can now:</p>
            <ul>
                <li>Browse our vehicle fleet</li>
                <li>Make bookings online</li>
                <li>Track your bookings</li>
                <li>Manage your profile</li>
            </ul>
            <p>Best regards,<br>MG Transport Services Team</p>";
            
            // Set success message and show confirmation on the same page
            $success_message = "Account created successfully! Welcome to MG Transport Services.";
            $show_success_modal = true;
            
            // Store user details for success modal
            $_SESSION['new_user_username'] = $username;
            $_SESSION['new_user_email'] = $email;
            $_SESSION['new_user_name'] = $first_name . ' ' . $last_name;
            
            // Clear form data
            $_POST = array();
            
            // Don't redirect - stay on page to show success message
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .register-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fbbf24;
            box-shadow: 0 8px 16px rgba(251, 191, 36, 0.3);
            margin-bottom: 1rem;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #fbbf24;
            box-shadow: 0 0 0 0.15rem rgba(251, 191, 36, 0.25);
        }
        
        .input-group-text {
            border-radius: 8px 0 0 8px;
            border: 1px solid #e5e7eb;
            border-right: none;
            background: #fbbf24;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 8px 8px 0;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: none;
            color: #1e3a8a;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(251, 191, 36, 0.4);
            font-size: 0.95rem;
        }
        
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.5);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1e3a8a;
        }
        
        /* File upload styling */
        .form-control[type="file"] {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]:focus {
            border-color: #fbbf24;
            box-shadow: 0 0 0 0.15rem rgba(251, 191, 36, 0.25);
            background-color: #fff;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button {
            background: #fbbf24;
            color: #1e3a8a;
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .form-control[type="file"]::-webkit-file-upload-button:hover {
            background: #f59e0b;
            transform: translateY(-1px);
        }
        
        .file-upload-info {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Password Strength Styles */
        .password-strength {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #e5e7eb;
        }
        
        .strength-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        
        .strength-fill.very-weak { width: 20%; background: #ef4444; }
        .strength-fill.weak { width: 40%; background: #f97316; }
        .strength-fill.fair { width: 60%; background: #eab308; }
        .strength-fill.good { width: 80%; background: #22c55e; }
        .strength-fill.strong { width: 100%; background: #16a34a; }
        
        .strength-text {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .strength-text.very-weak { color: #ef4444; }
        .strength-text.weak { color: #f97316; }
        .strength-text.fair { color: #eab308; }
        .strength-text.good { color: #22c55e; }
        .strength-text.strong { color: #16a34a; }
        
        .requirement {
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
        }
        
        .requirement.met {
            color: #22c55e;
        }
        
        .requirement.met i {
            color: #22c55e !important;
        }
        
        /* Password Match Indicator */
        .password-match {
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .password-match.match {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .password-match.no-match {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        /* View Password Button */
        .btn-outline-secondary {
            border-color: #e5e7eb;
            color: #6b7280;
            background: #f8fafc;
        }
        
        .btn-outline-secondary:hover {
            background: #e5e7eb;
            border-color: #d1d5db;
            color: #374151;
        }
        
        .btn-outline-secondary:focus {
            box-shadow: 0 0 0 0.15rem rgba(107, 114, 128, 0.25);
        }
        
        /* Validation Styles */
        .form-control.is-valid {
            border-color: #198754;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.94-.94 2.02 2.02L7.7 4.73l.94.94L5.26 8.77z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 2.4 2.4m0-2.4L5.8 7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #198754;
        }
        
        /* Loading state for submit button */
        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        
        /* Success modal enhancements */
        .modal-header.bg-success {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%) !important;
        }
        
        .modal-body .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 1px solid #90caf9;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="register-card">
                        <div class="register-header">
                            <img src="assets/images/MG Logo.jpg" alt="MG Transport Services">
                            <h3 class="fw-bold mb-2">MG TRANSPORT SERVICES</h3>
                            <p class="mb-0 text-warning">VEHICLE HIRE</p>
                        </div>
                        
                        <div class="register-body">
                            <h4 class="text-center mb-4" style="color: #1e3a8a; font-weight: 700;">Create Your Account</h4>
                            <p class="text-center text-muted mb-4">Join MG Transport Services today</p>
                            
                            <!-- Registration Progress -->
                            <div class="registration-progress mb-4">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" id="registrationProgress"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">Account Details</small>
                                    <small class="text-muted">Contact Info</small>
                                    <small class="text-muted">Security</small>
                                    <small class="text-muted">Complete</small>
                                </div>
                            </div>
                            
                            <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label fw-bold" style="color: #1e3a8a;">First Name</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                                       placeholder="Enter your first name"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label fw-bold" style="color: #1e3a8a;">Last Name</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-user"></i>
                                                </span>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                                       placeholder="Enter your last name"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label fw-bold" style="color: #1e3a8a;">Username</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-at"></i>
                                                </span>
                                                <input type="text" class="form-control" id="username" name="username" 
                                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                                       placeholder="Choose a username"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label fw-bold" style="color: #1e3a8a;">Email</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-envelope"></i>
                                                </span>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                                       placeholder="Enter your email"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label fw-bold" style="color: #1e3a8a;">Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       placeholder="Create a password"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                                        style="border-left: none; border-radius: 0 8px 8px 0;">
                                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                                </button>
                                            </div>
                                            <!-- Password Strength Indicator -->
                                            <div class="password-strength mt-2" id="passwordStrength" style="display: none;">
                                                <div class="strength-bar">
                                                    <div class="strength-fill" id="strengthFill"></div>
                                                </div>
                                                <div class="strength-text mt-1" id="strengthText"></div>
                                                <div class="strength-requirements mt-2" id="strengthRequirements">
                                                    <small class="text-muted">
                                                        <div class="requirement" id="reqLength"><i class="fas fa-circle text-muted"></i> At least 8 characters</div>
                                                        <div class="requirement" id="reqUppercase"><i class="fas fa-circle text-muted"></i> One uppercase letter</div>
                                                        <div class="requirement" id="reqLowercase"><i class="fas fa-circle text-muted"></i> One lowercase letter</div>
                                                        <div class="requirement" id="reqNumber"><i class="fas fa-circle text-muted"></i> One number</div>
                                                        <div class="requirement" id="reqSpecial"><i class="fas fa-circle text-muted"></i> One special character</div>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label fw-bold" style="color: #1e3a8a;">Confirm Password</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                       placeholder="Confirm your password"
                                                       required>
                                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" 
                                                        style="border-left: none; border-radius: 0 8px 8px 0;">
                                                    <i class="fas fa-eye" id="toggleConfirmIcon"></i>
                                                </button>
                                            </div>
                                            <!-- Password Match Indicator -->
                                            <div class="password-match mt-2" id="passwordMatch" style="display: none;">
                                                <small id="matchText"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label fw-bold" style="color: #1e3a8a;">Phone Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-phone"></i>
                                                </span>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                                       placeholder="Enter your phone number"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="address" class="form-label fw-bold" style="color: #1e3a8a;">Address</label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </span>
                                                <input type="text" class="form-control" id="address" name="address" 
                                                       value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>" 
                                                       placeholder="Enter your address"
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Photo upload fields temporarily disabled until database is updated -->
                                <!-- <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="user_photo" class="form-label fw-bold" style="color: #1e3a8a;">User Photo</label>
                                            <input type="file" class="form-control" id="user_photo" name="user_photo" accept="image/*" required>
                                            <div class="file-upload-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Upload a clear photo of yourself (JPEG/PNG, max 5MB)
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="driver_license_photo" class="form-label fw-bold" style="color: #1e3a8a;">Driver's License Photo</label>
                                            <input type="file" class="form-control" id="driver_license_photo" name="driver_license_photo" accept="image/*" required>
                                            <div class="file-upload-info">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Upload a clear photo of your driver's license (JPEG/PNG, max 5MB)
                                            </div>
                                        </div>
                                    </div>
                                </div> -->
                                
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="terms" required>
                                    <label class="form-check-label text-muted" for="terms">
                                        I agree to the <a href="#" style="color: #fbbf24;">Terms of Service</a> and <a href="#" style="color: #fbbf24;">Privacy Policy</a>
                                    </label>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-register btn-lg">
                                        <i class="fas fa-user-plus me-2"></i> Create Account
                                    </button>
                                </div>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="mb-0 text-muted">Already have an account? 
                                    <a href="login.php" class="text-decoration-none fw-bold" style="color: #fbbf24;">Sign in</a>
                                </p>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="index.php" class="text-decoration-none text-muted">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password Strength Validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        const matchText = document.getElementById('matchText');
        
        // Password requirements
        const requirements = {
            length: document.getElementById('reqLength'),
            uppercase: document.getElementById('reqUppercase'),
            lowercase: document.getElementById('reqLowercase'),
            number: document.getElementById('reqNumber'),
            special: document.getElementById('reqSpecial')
        };
        
        // Password strength checker
        function checkPasswordStrength(password) {
            let score = 0;
            let feedback = [];
            
            // Check length
            if (password.length >= 8) {
                score += 20;
                requirements.length.classList.add('met');
                requirements.length.innerHTML = '<i class="fas fa-check-circle text-success"></i> At least 8 characters';
            } else {
                requirements.length.classList.remove('met');
                requirements.length.innerHTML = '<i class="fas fa-circle text-muted"></i> At least 8 characters';
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                score += 20;
                requirements.uppercase.classList.add('met');
                requirements.uppercase.innerHTML = '<i class="fas fa-check-circle text-success"></i> One uppercase letter';
            } else {
                requirements.uppercase.classList.remove('met');
                requirements.uppercase.innerHTML = '<i class="fas fa-circle text-muted"></i> One uppercase letter';
            }
            
            // Check lowercase
            if (/[a-z]/.test(password)) {
                score += 20;
                requirements.lowercase.classList.add('met');
                requirements.lowercase.innerHTML = '<i class="fas fa-check-circle text-success"></i> One lowercase letter';
            } else {
                requirements.lowercase.classList.remove('met');
                requirements.lowercase.innerHTML = '<i class="fas fa-circle text-muted"></i> One lowercase letter';
            }
            
            // Check numbers
            if (/\d/.test(password)) {
                score += 20;
                requirements.number.classList.add('met');
                requirements.number.innerHTML = '<i class="fas fa-check-circle text-success"></i> One number';
            } else {
                requirements.number.classList.remove('met');
                requirements.number.innerHTML = '<i class="fas fa-circle text-muted"></i> One number';
            }
            
            // Check special characters
            if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                score += 20;
                requirements.special.classList.add('met');
                requirements.special.innerHTML = '<i class="fas fa-check-circle text-success"></i> One special character';
            } else {
                requirements.special.classList.remove('met');
                requirements.special.innerHTML = '<i class="fas fa-circle text-muted"></i> One special character';
            }
            
            // Determine strength level
            let strength = '';
            let strengthClass = '';
            
            if (score <= 20) {
                strength = 'Very Weak';
                strengthClass = 'very-weak';
            } else if (score <= 40) {
                strength = 'Weak';
                strengthClass = 'weak';
            } else if (score <= 60) {
                strength = 'Fair';
                strengthClass = 'fair';
            } else if (score <= 80) {
                strength = 'Good';
                strengthClass = 'good';
            } else {
                strength = 'Strong';
                strengthClass = 'strong';
            }
            
            // Update UI
            strengthFill.className = `strength-fill ${strengthClass}`;
            strengthText.className = `strength-text ${strengthClass}`;
            strengthText.textContent = `Password Strength: ${strength}`;
            
            return score;
        }
        
        // Check password match
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword === '') {
                passwordMatch.style.display = 'none';
                return;
            }
            
            if (password === confirmPassword) {
                passwordMatch.style.display = 'block';
                passwordMatch.className = 'password-match match';
                matchText.innerHTML = '<i class="fas fa-check-circle me-1"></i> Passwords match!';
            } else {
                passwordMatch.style.display = 'block';
                passwordMatch.className = 'password-match no-match';
                matchText.innerHTML = '<i class="fas fa-times-circle me-1"></i> Passwords do not match';
            }
        }
        
        // Event listeners
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password === '') {
                passwordStrength.style.display = 'none';
            } else {
                passwordStrength.style.display = 'block';
                checkPasswordStrength(password);
            }
            
            // Check password match when password changes
            if (confirmPasswordInput.value !== '') {
                checkPasswordMatch();
            }
        });
        
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        // View Password Toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Check password strength
            const strength = checkPasswordStrength(password);
            if (strength < 40) {
                e.preventDefault();
                alert('Please choose a stronger password. Your password should meet at least 2 requirements.');
                return;
            }
            
            // Check password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password correctly.');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            submitBtn.disabled = true;
            
            // Re-enable after a delay (in case of validation errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Real-time validation feedback and progress tracking
        const inputs = document.querySelectorAll('input[required]');
        const progressBar = document.getElementById('registrationProgress');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
                updateProgress();
            });
            
            input.addEventListener('input', function() {
                clearFieldValidation(this);
                updateProgress();
            });
        });
        
        function updateProgress() {
            const totalFields = inputs.length;
            let filledFields = 0;
            
            inputs.forEach(input => {
                if (input.value.trim() !== '') {
                    filledFields++;
                }
            });
            
            const progress = (filledFields / totalFields) * 100;
            progressBar.style.width = progress + '%';
            
            // Update progress bar color based on completion
            if (progress < 25) {
                progressBar.className = 'progress-bar bg-danger';
            } else if (progress < 50) {
                progressBar.className = 'progress-bar bg-warning';
            } else if (progress < 75) {
                progressBar.className = 'progress-bar bg-info';
            } else {
                progressBar.className = 'progress-bar bg-success';
            }
        }
        
        function validateField(field) {
            const value = field.value.trim();
            const fieldName = field.name;
            let isValid = true;
            let errorMessage = '';
            
            // Remove existing validation classes
            field.classList.remove('is-valid', 'is-invalid');
            
            // Field-specific validation
            switch(fieldName) {
                case 'username':
                    if (value.length < 3) {
                        isValid = false;
                        errorMessage = 'Username must be at least 3 characters long.';
                    } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Username can only contain letters, numbers, and underscores.';
                    }
                    break;
                    
                case 'email':
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address.';
                    }
                    break;
                    
                case 'first_name':
                case 'last_name':
                    if (!/^[a-zA-Z\s]+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Name can only contain letters and spaces.';
                    }
                    break;
                    
                case 'phone':
                    if (!/^[\d\s\-\+\(\)]+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid phone number.';
                    }
                    break;
            }
            
            // Apply validation styling
            if (isValid && value !== '') {
                field.classList.add('is-valid');
            } else if (!isValid) {
                field.classList.add('is-invalid');
                showFieldError(field, errorMessage);
            }
        }
        
        function clearFieldValidation(field) {
            field.classList.remove('is-valid', 'is-invalid');
            const errorElement = field.parentNode.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.remove();
            }
        }
        
        function showFieldError(field, message) {
            // Remove existing error message
            const existingError = field.parentNode.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }
            
            // Create new error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.innerHTML = message;
            field.parentNode.appendChild(errorDiv);
        }
        
        // Show success modal if registration was successful
        <?php if (isset($show_success_modal) && $show_success_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            
            // Populate account summary
            const username = '<?php echo isset($_SESSION['new_user_username']) ? htmlspecialchars($_SESSION['new_user_username']) : ''; ?>';
            const email = '<?php echo isset($_SESSION['new_user_email']) ? htmlspecialchars($_SESSION['new_user_email']) : ''; ?>';
            
            if (username) document.getElementById('accountUsername').textContent = username;
            if (email) document.getElementById('accountEmail').textContent = email;
            
            successModal.show();
            
            // Clear session variables after modal is shown
            <?php if (isset($_SESSION['new_user_username'])): ?>
            // Clear session variables
            <?php 
            unset($_SESSION['new_user_username']);
            unset($_SESSION['new_user_email']);
            unset($_SESSION['new_user_name']);
            ?>
            <?php endif; ?>
        });
        <?php endif; ?>
    </script>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>
                        Account Created Successfully!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-check fa-4x text-success mb-3"></i>
                        <h4 class="text-success">Welcome to MG Transport Services!</h4>
                        <p class="text-muted">Your account has been created successfully.</p>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>What's Next?</h6>
                        <ul class="text-start mb-0">
                            <li>You can now log in with your username and password</li>
                            <li>Browse our vehicle fleet and make bookings</li>
                            <li>Track your bookings and manage your profile</li>
                            <li>Receive notifications about your account status</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Account Summary</h6>
                        <div class="row text-start">
                            <div class="col-6">
                                <small><strong>Username:</strong> <span id="accountUsername"></span></small><br>
                                <small><strong>Email:</strong> <span id="accountEmail"></span></small>
                            </div>
                            <div class="col-6">
                                <small><strong>Role:</strong> Customer</small><br>
                                <small><strong>Status:</strong> Active</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <a href="login.php" class="btn btn-success w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login Now
                            </a>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary w-100" data-bs-dismiss="modal">
                                <i class="fas fa-home me-2"></i>Stay Here
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 