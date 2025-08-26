<?php
/**
 * SMS Service for MG Transport
 * Handles sending verification codes and notifications via SMS
 */

class SMSService {
    private $api_key;
    private $api_url;
    private $sender_id;
    private $log_file;
    
    public function __construct() {
        // SMS API Configuration - Update with actual SMS gateway credentials
        $this->api_key = 'sms_api_key_here'; // Replace with actual SMS API key
        $this->api_url = 'https://api.smsgateway.com/send'; // Replace with actual SMS API URL
        $this->sender_id = 'MG_TRANSPORT';
        
        // Setup logging
        $this->log_file = 'logs/sms_log.txt';
        $this->ensureLogDirectory();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
    }
    
    /**
     * Generate a cryptographically secure verification code
     */
    public function generateVerificationCode($length = 6) {
        // Use random_int for better security than rand()
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
    
    /**
     * Send verification code via SMS
     */
    public function sendVerificationCode($phone_number, $code, $purpose = 'bank_transfer') {
        try {
            // Validate phone number
            if (!$this->validatePhoneNumber($phone_number)) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format',
                    'error_code' => 'INVALID_PHONE'
                ];
            }
            
            $message = $this->getVerificationMessage($code, $purpose);
            $result = $this->sendSMS($phone_number, $message);
            
            // Log the attempt
            $this->logSMSEvent($phone_number, $message, $result['success'], $result['message']);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('SMS sending error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMS service temporarily unavailable. Please try again.',
                'error_code' => 'SERVICE_ERROR'
            ];
        }
    }
    
    /**
     * Validate phone number format
     */
    private function validatePhoneNumber($phone) {
        // Basic validation for PNG phone numbers
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // PNG phone number patterns
        $patterns = [
            '/^\+675[0-9]{7}$/',  // +675 followed by 7 digits
            '/^675[0-9]{7}$/',    // 675 followed by 7 digits
            '/^[0-9]{8}$/',       // 8 digits (local format)
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get verification message based on purpose
     */
    private function getVerificationMessage($code, $purpose) {
        $expiry_minutes = 10;
        
        switch ($purpose) {
            case 'bank_transfer':
                return "MG Transport: Your bank transfer verification code is {$code}. Valid for {$expiry_minutes} minutes. Do not share this code.";
            case 'payment_confirmation':
                return "MG Transport: Your payment verification code is {$code}. Valid for {$expiry_minutes} minutes. Do not share this code.";
            case 'account_verification':
                return "MG Transport: Your account verification code is {$code}. Valid for {$expiry_minutes} minutes. Do not share this code.";
            default:
                return "MG Transport: Your verification code is {$code}. Valid for {$expiry_minutes} minutes. Do not share this code.";
        }
    }
    
    /**
     * Send SMS via API
     */
    private function sendSMS($phone_number, $message) {
        // For demo purposes, we'll simulate SMS sending
        // In production, replace this with actual SMS API call
        
        $data = [
            'api_key' => $this->api_key,
            'to' => $phone_number,
            'message' => $message,
            'sender_id' => $this->sender_id,
            'timestamp' => time()
        ];
        
        // Simulate API call with realistic delays
        $response = $this->simulateSMSSend($data);
        
        return $response;
    }
    
    /**
     * Simulate SMS sending for demo purposes
     */
    private function simulateSMSSend($data) {
        // Simulate network delay
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds
        
        // Simulate occasional failures (5% failure rate for testing)
        if (rand(1, 100) <= 5) {
            $this->logError('Simulated SMS failure for testing');
            return [
                'success' => false,
                'message' => 'SMS delivery failed. Please try again.',
                'error_code' => 'DELIVERY_FAILED'
            ];
        }
        
        // Log the SMS attempt
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phone' => $data['to'],
            'message' => $data['message'],
            'status' => 'sent',
            'message_id' => 'SMS_' . time() . '_' . rand(1000, 9999)
        ];
        
        file_put_contents($this->log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
        
        // Return success response
        return [
            'success' => true,
            'message_id' => $log_entry['message_id'],
            'status' => 'sent',
            'message' => 'SMS sent successfully (demo mode)'
        ];
    }
    
    /**
     * Verify SMS code with enhanced security
     */
    public function verifyCode($stored_code, $input_code, $expiry_time) {
        try {
            // Check if code has expired
            if (time() > $expiry_time) {
                $this->logSecurityEvent('Code verification failed: expired code', [
                    'stored_code' => substr($stored_code, 0, 2) . '****',
                    'input_code' => substr($input_code, 0, 2) . '****',
                    'expiry_time' => date('Y-m-d H:i:s', $expiry_time)
                ]);
                return ['valid' => false, 'message' => 'Verification code has expired. Please request a new one.'];
            }
            
            // Check if codes match (case-insensitive for user convenience)
            if (strtolower(trim($stored_code)) === strtolower(trim($input_code))) {
                $this->logSecurityEvent('Code verification successful', [
                    'stored_code' => substr($stored_code, 0, 2) . '****',
                    'input_code' => substr($input_code, 0, 2) . '****'
                ]);
                return ['valid' => true, 'message' => 'Verification code is valid'];
            }
            
            // Log failed attempt
            $this->logSecurityEvent('Code verification failed: invalid code', [
                'stored_code' => substr($stored_code, 0, 2) . '****',
                'input_code' => substr($input_code, 0, 2) . '****'
            ]);
            
            return ['valid' => false, 'message' => 'Invalid verification code. Please check and try again.'];
            
        } catch (Exception $e) {
            $this->logError('Code verification error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Verification error. Please try again.'];
        }
    }
    
    /**
     * Log SMS events
     */
    private function logSMSEvent($phone, $message, $success, $result_message) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phone' => $this->maskPhoneNumber($phone),
            'message_length' => strlen($message),
            'success' => $success,
            'result' => $result_message
        ];
        
        file_put_contents($this->log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event, $data = []) {
        $security_log = 'logs/security.log';
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'data' => $data
        ];
        
        file_put_contents($security_log, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log errors
     */
    private function logError($error_message) {
        $error_log = 'logs/error.log';
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $error_message
        ];
        
        file_put_contents($error_log, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Mask phone number for logging (privacy)
     */
    private function maskPhoneNumber($phone) {
        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }
        return substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 4) . substr($phone, -2);
    }
    
    /**
     * Get SMS statistics
     */
    public function getSMSStats() {
        if (!file_exists($this->log_file)) {
            return [
                'total_sent' => 0,
                'successful' => 0,
                'failed' => 0,
                'last_24h' => 0
            ];
        }
        
        $logs = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $total = count($logs);
        $successful = 0;
        $failed = 0;
        $last_24h = 0;
        
        foreach ($logs as $log) {
            $entry = json_decode($log, true);
            if ($entry) {
                if ($entry['success'] ?? false) {
                    $successful++;
                } else {
                    $failed++;
                }
                
                // Check if within last 24 hours
                if (strtotime($entry['timestamp']) > time() - 86400) {
                    $last_24h++;
                }
            }
        }
        
        return [
            'total_sent' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'last_24h' => $last_24h
        ];
    }
    
    /**
     * Clean old logs (keep last 30 days)
     */
    public function cleanOldLogs() {
        $logs = file($this->log_file, FILE_IGNORE_NEW_LINES);
        $cutoff_time = time() - (30 * 86400); // 30 days ago
        $new_logs = [];
        
        foreach ($logs as $log) {
            $entry = json_decode($log, true);
            if ($entry && strtotime($entry['timestamp']) > $cutoff_time) {
                $new_logs[] = $log;
            }
        }
        
        // Write back only recent logs
        file_put_contents($this->log_file, implode("\n", $new_logs) . "\n");
        
        return count($logs) - count($new_logs); // Return number of cleaned logs
    }
}

// Initialize SMS service
$sms_service = new SMSService();
?>
