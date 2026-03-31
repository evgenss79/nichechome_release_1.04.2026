<?php
/**
 * Email Mailer Module
 * Handles email sending via PHPMailer SMTP
 */

require_once __DIR__ . '/../../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/log.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configuration constants
define('SMTP_ERROR_MESSAGE_MAX_LENGTH', 100);

/**
 * Load email settings from JSON file
 * @return array Email settings
 */
function loadEmailSettings(): array {
    $settingsFile = __DIR__ . '/../../data/email_settings.json';
    
    if (!file_exists($settingsFile)) {
        return [
            'enabled' => false,
            'smtp' => [
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password_encrypted' => '',
                'from_email' => '',
                'from_name' => ''
            ],
            'routing' => [
                'admin_orders_email' => '',
                'support_email' => '',
                'reply_to_email' => ''
            ]
        ];
    }
    
    $json = file_get_contents($settingsFile);
    if ($json === false) {
        error_log("Failed to read email settings file: $settingsFile");
        return [];
    }
    
    $settings = json_decode($json, true);
    
    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse email settings JSON: " . json_last_error_msg());
        return [];
    }
    
    if (!is_array($settings)) {
        error_log("Email settings is not an array");
        return [];
    }
    
    return $settings;
}

/**
 * Save email settings to JSON file
 * @param array $settings Email settings
 * @return bool Success status
 */
function saveEmailSettings(array $settings): bool {
    $settingsFile = __DIR__ . '/../../data/email_settings.json';
    
    // Add timestamp to track last save
    $settings['last_saved_at'] = date('Y-m-d H:i:s');
    
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($json === false) {
        return false;
    }
    
    return file_put_contents($settingsFile, $json, LOCK_EX) !== false;
}

/**
 * Send an email via SMTP
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $html HTML body
 * @param string $text Plain text body
 * @param string|null $replyTo Reply-to email address (optional)
 * @param string $eventType Event type for logging (default 'general')
 * @param array $context Additional context for logging (e.g., customer_email, request_id)
 * @return array ['success' => bool, 'error' => string]
 */
function sendEmailViaSMTP(string $to, string $subject, string $html, string $text = '', ?string $replyTo = null, string $eventType = 'general', array $context = []): array {
    $settings = loadEmailSettings();
    
    // Check if email is enabled
    if (empty($settings['enabled'])) {
        $error = 'Email sending is disabled in configuration';
        logEmailEvent($eventType, $to, false, $error, $context);
        return ['success' => false, 'error' => $error];
    }
    
    // Validate SMTP settings
    $smtp = $settings['smtp'] ?? [];
    if (empty($smtp['host']) || empty($smtp['username']) || empty($smtp['from_email'])) {
        $error = 'SMTP settings are incomplete';
        logEmailEvent($eventType, $to, false, $error, $context);
        return ['success' => false, 'error' => $error];
    }
    
    try {
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Enable SMTP debug if EMAIL_DEBUG is enabled
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = function($str, $level) {
                // Mask password in debug output - handle various formats
                $str = preg_replace('/(?:PASS|Password)[:\s]+.*/i', 'PASS ***', $str);
                error_log("[SMTP DEBUG] " . trim($str));
            };
        }
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        
        // Decrypt password
        $password = '';
        if (!empty($smtp['password_encrypted'])) {
            try {
                $password = decryptEmailPassword($smtp['password_encrypted']);
            } catch (\Exception $e) {
                // Catch any exception from decryption (not just PHPMailer\Exception)
                // Log detailed error server-side, but show generic message to user
                error_log("Email decryption failure in sendEmailViaSMTP: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                logEmailEvent($eventType, $to, false, 'Password decryption failed', $context);
                
                // User-facing message (no technical details)
                $error = 'SMTP password cannot be decrypted. Please re-enter your SMTP password in Email Settings.';
                return ['success' => false, 'error' => $error, 'needs_password_reset' => true];
            }
        }
        
        if (empty($password)) {
            $error = 'SMTP password is not set. Please configure SMTP password in Email Settings.';
            logEmailEvent($eventType, $to, false, $error, $context);
            return ['success' => false, 'error' => $error, 'needs_password_reset' => true];
        }
        
        $mail->Password = $password;
        
        // Encryption
        $encryption = strtolower($smtp['encryption'] ?? 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = (int)($smtp['port'] ?? 587);
        $mail->CharSet = 'UTF-8';
        
        // Set reasonable timeout
        $mail->Timeout = 30;
        
        // From
        $mail->setFrom($smtp['from_email'], $smtp['from_name'] ?? '');
        
        // To
        $mail->addAddress($to);
        
        // Reply-To
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        } elseif (!empty($settings['routing']['reply_to_email'])) {
            $mail->addReplyTo($settings['routing']['reply_to_email']);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        
        if (!empty($text)) {
            $mail->AltBody = $text;
        } else {
            // Generate plain text from HTML
            $mail->AltBody = strip_tags($html);
        }
        
        // Send
        $mail->send();
        
        logEmailEvent($eventType, $to, true, '', $context);
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logEmailEvent($eventType, $to, false, $error, $context);
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Test SMTP connection with detailed step-by-step diagnostics
 * @return array ['success' => bool, 'error' => string, 'details' => array]
 */
function testSMTPConnection(): array {
    $settings = loadEmailSettings();
    $details = [];
    $steps = [];
    
    // Step 1: Configuration validation
    $steps[] = ['step' => 'Configuration Check', 'status' => 'checking'];
    
    if (empty($settings['enabled'])) {
        $steps[0]['status'] = 'failed';
        $steps[0]['message'] = 'Email system is disabled';
        return ['success' => false, 'error' => 'Email sending is disabled in configuration', 'details' => ['steps' => $steps]];
    }
    
    $smtp = $settings['smtp'] ?? [];
    if (empty($smtp['host'])) {
        $steps[0]['status'] = 'failed';
        $steps[0]['message'] = 'SMTP host not configured';
        return ['success' => false, 'error' => 'SMTP host is not configured', 'details' => ['steps' => $steps]];
    }
    if (empty($smtp['username'])) {
        $steps[0]['status'] = 'failed';
        $steps[0]['message'] = 'SMTP username not configured';
        return ['success' => false, 'error' => 'SMTP username is not configured', 'details' => ['steps' => $steps]];
    }
    
    $steps[0]['status'] = 'ok';
    $steps[0]['message'] = 'Configuration is valid';
    
    $details['host'] = $smtp['host'];
    $details['port'] = (int)($smtp['port'] ?? 587);
    $details['encryption'] = strtoupper($smtp['encryption'] ?? 'TLS');
    $details['username'] = $smtp['username'];
    
    // Step 2: DNS resolution
    $steps[] = ['step' => 'DNS Resolution', 'status' => 'checking'];
    $ip = gethostbyname($smtp['host']);
    if ($ip === $smtp['host']) {
        $steps[1]['status'] = 'failed';
        $steps[1]['message'] = 'Cannot resolve hostname';
        $details['steps'] = $steps;
        return ['success' => false, 'error' => 'Failed to resolve hostname: ' . $smtp['host'], 'details' => $details];
    }
    $steps[1]['status'] = 'ok';
    $steps[1]['message'] = "Resolved to IP: $ip";
    $details['resolved_ip'] = $ip;
    
    // Step 3: Port connectivity
    $steps[] = ['step' => 'Port Connectivity', 'status' => 'checking'];
    $port = (int)($smtp['port'] ?? 587);
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($smtp['host'], $port, $errno, $errstr, 10);
    if (!$socket) {
        $steps[2]['status'] = 'failed';
        $steps[2]['message'] = "Cannot connect to port $port: $errstr (error $errno)";
        $details['steps'] = $steps;
        return ['success' => false, 'error' => "Cannot connect to {$smtp['host']}:$port - $errstr", 'details' => $details];
    }
    fclose($socket);
    $steps[2]['status'] = 'ok';
    $steps[2]['message'] = "Port $port is reachable";
    
    // Step 4: Password decryption
    $steps[] = ['step' => 'Password Decryption', 'status' => 'checking'];
    $password = '';
    if (!empty($smtp['password_encrypted'])) {
        try {
            $password = decryptEmailPassword($smtp['password_encrypted']);
            $steps[3]['status'] = 'ok';
            $steps[3]['message'] = 'Password decrypted successfully';
        } catch (\Exception $e) {
            // Catch any exception from decryption (not just PHPMailer\Exception)
            $steps[3]['status'] = 'failed';
            $steps[3]['message'] = 'Failed to decrypt: ' . substr($e->getMessage(), 0, 100);
            $details['steps'] = $steps;
            $details['error_type'] = 'Decryption Error';
            $details['suggestion'] = 'The encryption key may have changed. Please re-enter your SMTP password in the SMTP Settings tab and save it again.';
            error_log("Password decryption failed in testSMTPConnection: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to decrypt password. Please re-enter your SMTP password.', 'details' => $details, 'needs_password_reset' => true];
        }
    } else {
        $steps[3]['status'] = 'failed';
        $steps[3]['message'] = 'No password configured';
        $details['steps'] = $steps;
        $details['error_type'] = 'Configuration Error';
        $details['suggestion'] = 'Please enter your SMTP password in the SMTP Settings tab.';
        return ['success' => false, 'error' => 'No password configured', 'details' => $details, 'needs_password_reset' => true];
    }
    
    // Step 5: SMTP connection and authentication
    $steps[] = ['step' => 'SMTP Connection & Auth', 'status' => 'checking'];
    
    try {
        $mail = new PHPMailer(true);
        
        // Enable debug if EMAIL_DEBUG is enabled
        $debugOutput = '';
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = 3;
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                // Mask password in debug output
                $str = preg_replace('/PASS\s+.+/', 'PASS ***', $str);
                $debugOutput .= trim($str) . "\n";
            };
        }
        
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $password;
        
        $encryption = strtolower($smtp['encryption'] ?? 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $mail->Port = $port;
        $mail->Timeout = 15;
        
        if (!$mail->smtpConnect()) {
            $steps[4]['status'] = 'failed';
            $steps[4]['message'] = 'SMTP connection failed';
            $details['steps'] = $steps;
            if (!empty($debugOutput)) {
                $details['debug_output'] = $debugOutput;
            }
            return ['success' => false, 'error' => 'Failed to connect to SMTP server', 'details' => $details];
        }
        
        $steps[4]['status'] = 'ok';
        $steps[4]['message'] = 'Connection and authentication successful';
        
        $mail->smtpClose();
        
        $details['steps'] = $steps;
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG && !empty($debugOutput)) {
            $details['debug_output'] = $debugOutput;
        }
        
        return ['success' => true, 'error' => '', 'details' => $details];
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        $steps[4]['status'] = 'failed';
        
        // Parse common error types for user-friendly messages
        if (stripos($errorMsg, 'AUTH') !== false || stripos($errorMsg, 'authentication') !== false) {
            $steps[4]['message'] = 'Authentication failed';
            $details['error_type'] = 'Authentication Error';
            $details['suggestion'] = 'Check your username and password. Make sure they are correct.';
        } elseif (stripos($errorMsg, 'connect') !== false || stripos($errorMsg, 'connection') !== false) {
            $steps[4]['message'] = 'Connection failed';
            $details['error_type'] = 'Connection Error';
            $details['suggestion'] = 'Check host, port, and network connectivity. Verify firewall settings.';
        } elseif (stripos($errorMsg, 'tls') !== false || stripos($errorMsg, 'ssl') !== false || stripos($errorMsg, 'crypto') !== false) {
            $steps[4]['message'] = 'TLS/SSL handshake failed';
            $details['error_type'] = 'Encryption Error';
            $details['suggestion'] = 'Port 587 requires TLS, port 465 requires SSL. Check your encryption setting matches the port.';
        } elseif (stripos($errorMsg, 'timeout') !== false) {
            $steps[4]['message'] = 'Connection timeout';
            $details['error_type'] = 'Timeout Error';
            $details['suggestion'] = 'Server is not responding. Check if the host and port are correct, and if the server is running.';
        } else {
            $steps[4]['message'] = 'Unknown error: ' . substr($errorMsg, 0, SMTP_ERROR_MESSAGE_MAX_LENGTH);
            $details['error_type'] = 'Unknown Error';
            $details['suggestion'] = 'Check the error message for more details.';
        }
        
        $details['steps'] = $steps;
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG && !empty($debugOutput)) {
            $details['debug_output'] = $debugOutput;
        }
        
        return ['success' => false, 'error' => $errorMsg, 'details' => $details];
    }
}
