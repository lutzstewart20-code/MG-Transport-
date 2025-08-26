<?php
/**
 * Security Features for MG Transport
 * Comprehensive security implementation
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Security Configuration
class Security {
    private static $instance = null;
    private $csrf_token;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Security();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initSecurity();
    }
    
    /**
     * Initialize security features
     */
    private function initSecurity() {
        // Set secure headers
        $this->setSecurityHeaders();
        
        // Initialize CSRF protection
        $this->initCSRF();
        
        // Set secure session parameters
        $this->secureSession();
    }
    
    /**
     * Set security headers
     */
    private function setSecurityHeaders() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none';");
        
        // XSS Protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Frame options
        header("X-Frame-Options: DENY");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Permissions Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }
    
    /**
     * Initialize CSRF protection
     */
    private function initCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->csrf_token = $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF token
     */
    public function getCSRFToken() {
        return $this->csrf_token;
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Secure session configuration
     */
    private function secureSession() {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($pattern, $password);
    }
    
    /**
     * Rate limiting
     */
    public function checkRateLimit($action, $max_attempts = 5, $time_window = 300) {
        $key = "rate_limit_{$action}_" . $_SERVER['REMOTE_ADDR'];
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
        }
        
        $rate_data = $_SESSION[$key];
        
        // Reset if time window has passed
        if (time() - $rate_data['first_attempt'] > $time_window) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($rate_data['attempts'] >= $max_attempts) {
            return false;
        }
        
        // Increment attempts
        $_SESSION[$key]['attempts']++;
        return true;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event, $details = '', $level = 'INFO') {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'event' => $event,
            'details' => $details,
            'level' => $level
        ];
        
        $log_file = __DIR__ . '/../logs/security.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check for suspicious activity
     */
    public function detectSuspiciousActivity() {
        $suspicious = false;
        $reasons = [];
        
        // Check for SQL injection patterns
        $sql_patterns = ['/union\s+select/i', '/drop\s+table/i', '/insert\s+into/i', '/delete\s+from/i'];
        foreach ($sql_patterns as $pattern) {
            if (preg_match($pattern, $_SERVER['REQUEST_URI']) || 
                preg_match($pattern, json_encode($_POST)) || 
                preg_match($pattern, json_encode($_GET))) {
                $suspicious = true;
                $reasons[] = 'SQL injection attempt';
                break;
            }
        }
        
        // Check for XSS patterns
        $xss_patterns = ['/<script/i', '/javascript:/i', '/on\w+\s*=/i'];
        foreach ($xss_patterns as $pattern) {
            if (preg_match($pattern, json_encode($_POST)) || preg_match($pattern, json_encode($_GET))) {
                $suspicious = true;
                $reasons[] = 'XSS attempt';
                break;
            }
        }
        
        // Check for file upload abuse
        if (isset($_FILES) && !empty($_FILES)) {
            foreach ($_FILES as $file) {
                if (isset($file['type'])) {
                    $dangerous_types = ['application/x-php', 'text/php', 'application/x-executable'];
                    if (in_array($file['type'], $dangerous_types)) {
                        $suspicious = true;
                        $reasons[] = 'Dangerous file upload attempt';
                        break;
                    }
                }
            }
        }
        
        if ($suspicious) {
            $this->logSecurityEvent('Suspicious Activity Detected', implode(', ', $reasons), 'WARNING');
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate secure random token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Initialize security
$security = Security::getInstance();

// Check for suspicious activity
if ($security->detectSuspiciousActivity()) {
    http_response_code(403);
    exit('Access denied');
}
?>
