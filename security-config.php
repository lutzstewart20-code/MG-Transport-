<?php
/**
 * Security Configuration for MG Transport
 * Centralized security settings and constants
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Security Configuration Constants
define('SECURITY_CONFIG', true);

// Session Security
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_REGEN_TIME', 300); // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('ACCOUNT_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_EXPIRY_DAYS', 90);

// File Upload Security
define('MAX_FILE_SIZE', 5242880); // 5MB
define('MAX_IMAGE_DIMENSIONS', 8000); // 8000x8000 pixels
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'txt']);
define('ALLOWED_RECEIPT_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// Rate Limiting
define('RATE_LIMIT_LOGIN', 5); // 5 attempts
define('RATE_LIMIT_LOGIN_WINDOW', 300); // 5 minutes
define('RATE_LIMIT_API', 100); // 100 requests
define('RATE_LIMIT_API_WINDOW', 3600); // 1 hour

// Password Requirements
define('MIN_PASSWORD_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', true);

// CSRF Protection
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Security Headers
define('SECURITY_HEADERS', [
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none';",
    'X-XSS-Protection' => '1; mode=block',
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
]);

// Database Security
define('DB_MAX_QUERY_TIME', 30); // 30 seconds
define('DB_MAX_CONNECTIONS', 100);
define('DB_CONNECTION_TIMEOUT', 10);

// API Security
define('API_RATE_LIMIT_ENABLED', true);
define('API_AUTH_REQUIRED', true);
define('API_LOG_REQUESTS', true);
define('API_MAX_REQUEST_SIZE', 1048576); // 1MB

// Logging Configuration
define('SECURITY_LOG_ENABLED', true);
define('SECURITY_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
define('SECURITY_LOG_RETENTION_DAYS', 90);
define('SECURITY_LOG_MAX_SIZE', 10485760); // 10MB

// IP Whitelist/Blacklist
define('IP_WHITELIST_ENABLED', false);
define('IP_BLACKLIST_ENABLED', true);
define('IP_WHITELIST', []); // Add trusted IPs here
define('IP_BLACKLIST', []); // Add blocked IPs here

// Two-Factor Authentication
define('2FA_ENABLED', false);
define('2FA_METHOD', 'TOTP'); // TOTP, SMS, Email
define('2FA_ISSUER', 'MG Transport');
define('2FA_ALGORITHM', 'sha1');
define('2FA_DIGITS', 6);
define('2FA_PERIOD', 30);

// Encryption Settings
define('ENCRYPTION_ALGORITHM', 'AES-256-GCM');
define('ENCRYPTION_KEY_LENGTH', 32);
define('HASH_ALGORITHM', 'PASSWORD_ARGON2ID');
define('HASH_MEMORY_COST', 65536);
define('HASH_TIME_COST', 4);
define('HASH_THREADS', 3);

// Backup Security
define('BACKUP_ENCRYPTION_ENABLED', true);
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_STORAGE_SECURE', true);

// Monitoring and Alerting
define('SECURITY_MONITORING_ENABLED', true);
define('ALERT_ON_FAILED_LOGIN', true);
define('ALERT_ON_SUSPICIOUS_ACTIVITY', true);
define('ALERT_ON_FILE_UPLOAD_VIOLATION', true);
define('ALERT_ON_DATABASE_ERROR', true);

// Development vs Production Settings
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    // Relaxed security for development
    define('SESSION_TIMEOUT', 7200); // 2 hours
    define('SECURITY_LOG_LEVEL', 'DEBUG');
    define('IP_WHITELIST_ENABLED', false);
    define('2FA_ENABLED', false);
} else {
    // Strict security for production
    define('SESSION_TIMEOUT', 1800); // 30 minutes
    define('SECURITY_LOG_LEVEL', 'WARNING');
    define('IP_WHITELIST_ENABLED', true);
    define('2FA_ENABLED', true);
}

// Security Functions
class SecurityConfig {
    
    /**
     * Get security setting value
     */
    public static function get($setting) {
        return defined($setting) ? constant($setting) : null;
    }
    
    /**
     * Check if security feature is enabled
     */
    public static function isEnabled($feature) {
        $setting = strtoupper($feature) . '_ENABLED';
        return defined($setting) && constant($setting);
    }
    
    /**
     * Get allowed file types for category
     */
    public static function getAllowedFileTypes($category) {
        $setting = 'ALLOWED_' . strtoupper($category) . '_TYPES';
        return defined($setting) ? constant($setting) : [];
    }
    
    /**
     * Get security headers
     */
    public static function getSecurityHeaders() {
        return SECURITY_HEADERS;
    }
    
    /**
     * Validate IP address
     */
    public static function validateIP($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
        return false;
    }
    
    /**
     * Check if IP is whitelisted
     */
    public static function isIPWhitelisted($ip) {
        if (!self::isEnabled('IP_WHITELIST')) {
            return true;
        }
        
        return in_array($ip, IP_WHITELIST);
    }
    
    /**
     * Check if IP is blacklisted
     */
    public static function isIPBlacklisted($ip) {
        if (!self::isEnabled('IP_BLACKLIST')) {
            return false;
        }
        
        return in_array($ip, IP_BLACKLIST);
    }
    
    /**
     * Get password requirements
     */
    public static function getPasswordRequirements() {
        return [
            'min_length' => MIN_PASSWORD_LENGTH,
            'require_uppercase' => PASSWORD_REQUIRE_UPPERCASE,
            'require_lowercase' => PASSWORD_REQUIRE_LOWERCASE,
            'require_numbers' => PASSWORD_REQUIRE_NUMBERS,
            'require_special' => PASSWORD_REQUIRE_SPECIAL
        ];
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $requirements = self::getPasswordRequirements();
        
        if (strlen($password) < $requirements['min_length']) {
            return false;
        }
        
        if ($requirements['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        if ($requirements['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        if ($requirements['require_numbers'] && !preg_match('/\d/', $password)) {
            return false;
        }
        
        if ($requirements['require_special'] && !preg_match('/[@$!%*?&]/', $password)) {
            return false;
        }
        
        return true;
    }
}

// Initialize security configuration
if (!defined('SECURITY_INITIALIZED')) {
    define('SECURITY_INITIALIZED', true);
    
    // Set default timezone
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('UTC');
    }
    
    // Set error reporting based on environment
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
    
    // Set secure PHP settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    
    // Set upload limits
    ini_set('upload_max_filesize', MAX_FILE_SIZE);
    ini_set('post_max_size', MAX_FILE_SIZE * 2);
    ini_set('max_execution_time', 300);
    ini_set('max_input_time', 300);
    ini_set('memory_limit', '256M');
}
?>
