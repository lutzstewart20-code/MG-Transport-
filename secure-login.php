<?php
define('SECURE_ACCESS', true);
session_start();

require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/functions.php';

$security = Security::getInstance();
$error_message = '';
$success_message = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!$security->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid request. Please try again.';
        $security->logSecurityEvent('CSRF Token Mismatch', 'Login attempt with invalid CSRF token', 'WARNING');
    } else {
        // Check rate limiting
        if (!$security->checkRateLimit('login', 5, 300)) { // 5 attempts per 5 minutes
            $error_message = 'Too many login attempts. Please wait 5 minutes before trying again.';
            $security->logSecurityEvent('Rate Limit Exceeded', 'Login attempts exceeded limit', 'WARNING');
        } else {
            $email = $security->sanitizeInput($_POST['email'], 'email');
            $password = $_POST['password'];
            
            // Validate email format
            if (!$security->validateEmail($email)) {
                $error_message = 'Please enter a valid email address.';
            } else {
                // Check if user exists
                $stmt = mysqli_prepare($conn, "SELECT id, email, password, first_name, last_name, role, is_active, last_login_attempt, failed_attempts FROM users WHERE email = ?");
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($result) === 1) {
                    $user = mysqli_fetch_assoc($result);
                    
                    // Check if account is active
                    if (!$user['is_active']) {
                        $error_message = 'Account is deactivated. Please contact administrator.';
                        $security->logSecurityEvent('Login Attempt - Deactivated Account', "Email: {$email}", 'INFO');
                    } else {
                        // Check for account lockout
                        $lockout_time = 900; // 15 minutes
                        $max_failed_attempts = 5;
                        
                        if ($user['failed_attempts'] >= $max_failed_attempts && 
                            (time() - strtotime($user['last_login_attempt'])) < $lockout_time) {
                            $remaining_time = $lockout_time - (time() - strtotime($user['last_login_attempt']));
                            $error_message = "Account temporarily locked. Please try again in " . ceil($remaining_time / 60) . " minutes.";
                            $security->logSecurityEvent('Login Attempt - Locked Account', "Email: {$email}", 'WARNING');
                        } else {
                            // Verify password
                            if ($security->verifyPassword($password, $user['password'])) {
                                // Reset failed attempts on successful login
                                $update_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?");
                                mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                                mysqli_stmt_execute($update_stmt);
                                
                                // Set session variables
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['user_email'] = $user['email'];
                                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                                $_SESSION['user_role'] = $user['role'];
                                $_SESSION['login_time'] = time();
                                $_SESSION['last_activity'] = time();
                                
                                // Log successful login
                                $security->logSecurityEvent('Successful Login', "Email: {$email}, Role: {$user['role']}", 'INFO');
                                
                                // Redirect based on role
                                if ($user['role'] === 'admin') {
                                    header('Location: admin/dashboard.php');
                                } else {
                                    header('Location: customer/dashboard.php');
                                }
                                exit;
                            } else {
                                // Increment failed attempts
                                $new_failed_attempts = $user['failed_attempts'] + 1;
                                $update_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = ?, last_login_attempt = NOW() WHERE id = ?");
                                mysqli_stmt_bind_param($update_stmt, "ii", $new_failed_attempts, $user['id']);
                                mysqli_stmt_execute($update_stmt);
                                
                                $error_message = 'Invalid email or password.';
                                $security->logSecurityEvent('Failed Login Attempt', "Email: {$email}, Failed attempts: {$new_failed_attempts}", 'WARNING');
                            }
                        }
                    }
                } else {
                    // Don't reveal if email exists or not
                    $error_message = 'Invalid email or password.';
                    $security->logSecurityEvent('Failed Login Attempt - Invalid Email', "Email: {$email}", 'INFO');
                }
            }
        }
    }
}

// Generate new CSRF token for the form
$csrf_token = $security->getCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - MG Transport</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .security-features {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        .security-features i {
            color: #28a745;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <h2><i class="fas fa-shield-alt me-2"></i>Secure Login</h2>
                        <p class="mb-0">MG Transport Management System</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="loginForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required autocomplete="email">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me for 30 days
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-login text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="forgot-password.php" class="text-decoration-none">
                                <i class="fas fa-key me-1"></i>Forgot Password?
                            </a>
                        </div>
                        
                        <div class="security-features">
                            <h6 class="mb-2"><i class="fas fa-shield-alt"></i>Security Features</h6>
                            <div class="small text-muted">
                                <div><i class="fas fa-check"></i>CSRF Protection</div>
                                <div><i class="fas fa-check"></i>Rate Limiting</div>
                                <div><i class="fas fa-check"></i>Account Lockout</div>
                                <div><i class="fas fa-check"></i>Secure Headers</div>
                                <div><i class="fas fa-check"></i>Activity Logging</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
