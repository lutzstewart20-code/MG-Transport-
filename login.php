<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin($conn)) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Log activity
            logActivity($user['id'], 'User Login', 'User logged in successfully', $conn);
            
            // Redirect based on role
            if (in_array($user['role'], ['admin', 'super_admin'])) {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MG Transport Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fbbf24;
            box-shadow: 0 8px 16px rgba(251, 191, 36, 0.3);
            margin-bottom: 1rem;
        }
        
        .login-body {
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
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.5);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #1e3a8a;
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
        
        .demo-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 15px;
            border-left: 4px solid #fbbf24;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="login-card">
                        <div class="login-header">
                            <img src="assets/images/MG Logo.jpg" alt="MG Transport Services">
                            <h3 class="fw-bold mb-2">MG TRANSPORT SERVICES</h3>
                            <p class="mb-0 text-warning">VEHICLE HIRE</p>
                        </div>
                        
                        <div class="login-body">
                            <h4 class="text-center mb-4" style="color: #1e3a8a; font-weight: 700;">Welcome Back</h4>
                            <p class="text-center text-muted mb-4">Sign in to your account</p>
                            
                            <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label fw-bold" style="color: #1e3a8a;">Username or Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                               placeholder="Enter your username or email"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-bold" style="color: #1e3a8a;">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter your password"
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" 
                                                style="border-left: none; border-radius: 0 8px 8px 0;">
                                            <i class="fas fa-eye" id="toggleIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label text-muted" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-login btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                    </button>
                                </div>
                            </form>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="mb-0 text-muted">Don't have an account? 
                                    <a href="register.php" class="text-decoration-none fw-bold" style="color: #fbbf24;">Sign up</a>
                                </p>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="index.php" class="text-decoration-none text-muted">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Home
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Demo Credentials -->
                    <div class="demo-card mt-3 p-3">
                        <h6 class="fw-bold mb-2" style="color: #1e3a8a;">Demo Credentials:</h6>
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">
                                    <strong>Admin:</strong><br>
                                    Username: admin<br>
                                    Password: admin123
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <strong>Customer:</strong><br>
                                    Register a new account<br>
                                    to get started
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // View Password Toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    </script>
</body>
</html> 