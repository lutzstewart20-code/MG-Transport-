<?php
/**
 * Secure File Upload Handler
 * Prevents malicious file uploads and ensures security
 */

if (!defined('SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class SecureUpload {
    private $allowed_types;
    private $max_file_size;
    private $upload_directory;
    private $security;
    
    public function __construct($upload_dir = 'uploads/', $max_size = 5242880) { // 5MB default
        $this->upload_directory = rtrim($upload_dir, '/') . '/';
        $this->max_file_size = $max_size;
        $this->security = Security::getInstance();
        
        // Define allowed file types with MIME types
        $this->allowed_types = [
            'image' => [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ],
            'document' => [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'txt' => 'text/plain'
            ],
            'receipt' => [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf'
            ]
        ];
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->upload_directory)) {
            mkdir($this->upload_directory, 0755, true);
        }
    }
    
    /**
     * Upload file with security checks
     */
    public function uploadFile($file, $category = 'image', $custom_name = null) {
        try {
            // Check if file was uploaded
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
                return $this->getUploadError($file['error'] ?? 'Unknown error');
            }
            
            // Validate file size
            if ($file['size'] > $this->max_file_size) {
                $this->security->logSecurityEvent('File Upload - Size Exceeded', 
                    "File: {$file['name']}, Size: {$file['size']}", 'WARNING');
                return ['success' => false, 'message' => 'File size exceeds limit'];
            }
            
            // Get file extension and MIME type
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_mime = $file['type'];
            $file_name = $file['name'];
            
            // Validate file type
            if (!$this->isAllowedFileType($file_extension, $file_mime, $category)) {
                $this->security->logSecurityEvent('File Upload - Invalid Type', 
                    "File: {$file_name}, Type: {$file_mime}, Category: {$category}", 'WARNING');
                return ['success' => false, 'message' => 'File type not allowed'];
            }
            
            // Check for dangerous file extensions
            if ($this->isDangerousFile($file_extension, $file_mime)) {
                $this->security->logSecurityEvent('File Upload - Dangerous File', 
                    "File: {$file_name}, Type: {$file_mime}", 'CRITICAL');
                return ['success' => false, 'message' => 'File type not allowed for security reasons'];
            }
            
            // Validate file content
            if (!$this->validateFileContent($file['tmp_name'], $file_mime)) {
                $this->security->logSecurityEvent('File Upload - Invalid Content', 
                    "File: {$file_name}, Type: {$file_mime}", 'WARNING');
                return ['success' => false, 'message' => 'File content validation failed'];
            }
            
            // Generate secure filename
            $secure_filename = $this->generateSecureFilename($file_extension, $custom_name);
            $upload_path = $this->upload_directory . $secure_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                $this->security->logSecurityEvent('File Upload - Move Failed', 
                    "File: {$file_name}", 'ERROR');
                return ['success' => false, 'message' => 'Failed to save file'];
            }
            
            // Set proper permissions
            chmod($upload_path, 0644);
            
            // Log successful upload
            $this->security->logSecurityEvent('File Upload - Success', 
                "File: {$secure_filename}, Category: {$category}", 'INFO');
            
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $secure_filename,
                'path' => $upload_path,
                'size' => $file['size'],
                'type' => $file_mime
            ];
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent('File Upload - Exception', 
                "Error: {$e->getMessage()}", 'ERROR');
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check if file type is allowed
     */
    private function isAllowedFileType($extension, $mime_type, $category) {
        if (!isset($this->allowed_types[$category])) {
            return false;
        }
        
        foreach ($this->allowed_types[$category] as $ext => $mime) {
            if ($extension === $ext && $mime_type === $mime) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if file is dangerous
     */
    private function isDangerousFile($extension, $mime_type) {
        $dangerous_extensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'pl', 'py', 'jsp', 'asp', 'sh', 'bat', 'cmd',
            'exe', 'dll', 'so', 'dylib', 'jar', 'war', 'ear', 'class', 'swf', 'fla'
        ];
        
        $dangerous_mimes = [
            'application/x-php', 'text/php', 'application/x-executable', 'application/x-shockwave-flash',
            'application/java-archive', 'application/x-java-applet'
        ];
        
        return in_array($extension, $dangerous_extensions) || in_array($mime_type, $dangerous_mimes);
    }
    
    /**
     * Validate file content
     */
    private function validateFileContent($tmp_path, $mime_type) {
        // Check file header for common file types
        $file_header = file_get_contents($tmp_path, false, null, 0, 8);
        
        if (strpos($mime_type, 'image/') === 0) {
            // Validate image files
            $image_info = getimagesize($tmp_path);
            if ($image_info === false) {
                return false;
            }
            
            // Check for valid image dimensions
            if ($image_info[0] > 8000 || $image_info[1] > 8000) {
                return false;
            }
        }
        
        // Check for PHP tags in text files
        if (strpos($mime_type, 'text/') === 0 || strpos($mime_type, 'application/') === 0) {
            $content = file_get_contents($tmp_path);
            if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($extension, $custom_name = null) {
        if ($custom_name) {
            $custom_name = $this->security->sanitizeInput($custom_name);
            $custom_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $custom_name);
            return $custom_name . '_' . time() . '.' . $extension;
        }
        
        return uniqid('file_', true) . '_' . time() . '.' . $extension;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadError($error_code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return [
            'success' => false,
            'message' => $errors[$error_code] ?? 'Unknown upload error'
        ];
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile($filename) {
        $file_path = $this->upload_directory . $filename;
        
        if (file_exists($file_path) && unlink($file_path)) {
            $this->security->logSecurityEvent('File Deleted', "File: {$filename}", 'INFO');
            return true;
        }
        
        return false;
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($filename) {
        $file_path = $this->upload_directory . $filename;
        
        if (!file_exists($file_path)) {
            return false;
        }
        
        return [
            'name' => $filename,
            'size' => filesize($file_path),
            'type' => mime_content_type($file_path),
            'modified' => filemtime($file_path),
            'path' => $file_path
        ];
    }
    
    /**
     * Scan directory for suspicious files
     */
    public function scanDirectory() {
        $suspicious_files = [];
        $files = glob($this->upload_directory . '*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if ($this->isDangerousFile($extension, mime_content_type($file))) {
                $suspicious_files[] = $filename;
            }
        }
        
        if (!empty($suspicious_files)) {
            $this->security->logSecurityEvent('Suspicious Files Found', 
                "Files: " . implode(', ', $suspicious_files), 'WARNING');
        }
        
        return $suspicious_files;
    }
}

// Usage example:
// $uploader = new SecureUpload('uploads/receipts/', 10485760); // 10MB limit
// $result = $uploader->uploadFile($_FILES['receipt'], 'receipt');
?>
